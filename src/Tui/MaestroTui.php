<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui;

use NeuronAI\Workflow\Interrupt\Action;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronCore\Maestro\Agent\ToolApprovalPolicy;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Settings\Settings;
use NeuronCore\Maestro\Tui\Style\MaestroStyleSheet;
use NeuronCore\Maestro\Tui\ToolView\ToolViewRegistry;
use NeuronCore\Maestro\Tui\Widget\Card;
use NeuronCore\Maestro\Tui\Widget\TranscriptView;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Throwable;

use function array_map;
use function implode;
use function in_array;
use function preg_split;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;
use function rtrim;

/**
 * Boots the full-screen Maestro terminal UI and wires it to the agent.
 *
 * Layout (vertical): status bar / transcript (fills) / input editor.
 *
 * Agent work runs on a deferred Revolt fiber; the non-blocking Amp HTTP client
 * suspends it on network I/O, so the loop keeps rendering live. Tool approval
 * suspends that same fiber while the user decides on a SelectListWidget, then
 * resumes — the orchestrator's synchronous interrupt/resume logic is untouched.
 */
final class MaestroTui
{
    private Tui $tui;
    private TextWidget $statusBar;
    private TranscriptView $transcript;
    private EditorWidget $input;
    private bool $busy = false;

    /** @var Suspension<mixed>|null */
    private ?Suspension $approvalSuspension = null;

    /**
     * Commands that need a normal (non-raw) terminal and so cannot run inside
     * the TUI.
     */
    private const INTERACTIVE_COMMANDS = ['init'];

    public function __construct(
        private readonly Settings $settings,
        private readonly AgentOrchestrator $orchestrator,
        EventDispatcher $dispatcher,
        private readonly ToolApprovalPolicy $policy,
        ToolViewRegistry $toolViews,
        private readonly CommandRegistry $commands,
        ?TerminalInterface $terminal = null,
    ) {
        $this->tui = $terminal !== null
            ? new Tui(styleSheet: new MaestroStyleSheet(), terminal: $terminal)
            : new Tui(styleSheet: new MaestroStyleSheet());

        $this->build();
        $this->wire($dispatcher, $toolViews);
    }

    /**
     * Run the event loop until the user quits. Returns the process exit code.
     */
    public function run(): int
    {
        $this->tui->run();

        return 0;
    }

    /**
     * The underlying Tui instance (for tests driving start/tick/stop).
     */
    public function tui(): Tui
    {
        return $this->tui;
    }

    public function policy(): ToolApprovalPolicy
    {
        return $this->policy;
    }

    private function build(): void
    {
        $this->statusBar = (new TextWidget($this->renderStatus()))
            ->addStyleClass('status-bar');

        $this->transcript = new TranscriptView();

        $this->input = (new EditorWidget())->setMinVisibleLines(3);
        $this->input->setFocused(true);

        $this->tui->add($this->statusBar);
        $this->tui->add($this->transcript);
        $this->tui->add($this->input);

        $this->tui->setFocus($this->input);
    }

    private function wire(EventDispatcher $dispatcher, ToolViewRegistry $toolViews): void
    {
        (new TuiPresenter($this->transcript, $this->tui, $toolViews))->register($dispatcher);

        // Approval is handled here (not the presenter) because it needs focus
        // management and the input reference.
        $dispatcher->subscribe(
            ToolApprovalRequestedEvent::class,
            fn (ToolApprovalRequestedEvent $e) => $this->onApproval($e->approvalRequest),
        );

        $this->input->onSubmit(fn (SubmitEvent $event) => $this->handleSubmit($event->getValue()));

        // Raw mode strips signal interpretation: intercept Ctrl+C (\x03) and
        // Ctrl+D (\x04) globally to quit the application.
        $this->tui->addListener(function (InputEvent $event): void {
            $data = $event->getData();
            if ($data === "\x03" || $data === "\x04") {
                $event->stopPropagation();
                $this->tui->stop();
            }
        });
    }

    private function handleSubmit(string $value): void
    {
        $text = trim($value);
        if ($text === '') {
            return;
        }

        // "exit"/"quit" close the CLI (works even mid-turn; Ctrl+C also quits).
        if (in_array(strtolower($text), ['exit', 'quit'], true)) {
            $this->tui->stop();

            return;
        }

        if ($this->busy) {
            return;
        }

        // Echo the user's prompt into the transcript.
        $this->transcript->addCard(
            (new TextWidget($text))->addStyleClass('user-message')
        );

        // Clear the editor for the next prompt.
        $this->input->setText('');
        $this->tui->setFocus($this->input);

        if ($this->handleModeCommand($text)) {
            return;
        }

        if (str_starts_with($text, '/')) {
            [$name, $args] = $this->parseInlineCommand($text);
            $this->runInlineCommand($name, $args);

            return;
        }

        $this->runAgent($text);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseInlineCommand(string $input): array
    {
        $body = substr($input, 1);
        $parts = preg_split('/\s+/', $body, 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Run an inline command, capturing its output into a transcript card.
     * Interactive commands (the init wizard) are refused — they need a normal
     * terminal, not the raw-mode TUI.
     */
    private function runInlineCommand(string $name, string $args): void
    {
        if (in_array($name, self::INTERACTIVE_COMMANDS, true)) {
            $this->addNotice("/{$name} is interactive — run `maestro` from your shell.", 'warning');

            return;
        }

        $command = $this->commands->get($name);
        if ($command === null) {
            $this->addNotice("Unknown command: /{$name} — type /help.", 'warning');

            return;
        }

        $buffer = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);

        try {
            $command->execute($args, new ArgvInput(), $buffer);
            $captured = $buffer->fetch();
            if (trim($captured) !== '') {
                $this->transcript->addCard(new TextWidget(rtrim($captured)));
            }
        } catch (Throwable $e) {
            $this->addNotice('Command error: ' . $e->getMessage(), 'error');
        }

        $this->tui->requestRender();
    }

    private function addNotice(string $message, string $styleClass): void
    {
        $this->transcript->addCard((new TextWidget($message))->addStyleClass($styleClass));
        $this->tui->requestRender();
    }

    /**
     * Handle the mode-toggle slash commands. Returns true if the input was one.
     */
    private function handleModeCommand(string $text): bool
    {
        if ($text === '/auto') {
            $this->policy->autoMode = !$this->policy->autoMode;
            $this->refreshStatus();

            return true;
        }

        if ($text === '/plan') {
            $this->policy->planMode = !$this->policy->planMode;
            $this->refreshStatus();

            return true;
        }

        return false;
    }

    /**
     * Suspend the agent fiber until the user picks an approval decision, then
     * apply it. In plan mode, auto-reject without prompting.
     */
    private function onApproval(ApprovalRequest $request): void
    {
        if ($this->policy->planMode) {
            $this->rejectAll($request, 'Plan mode is active: changes are not applied.');

            return;
        }

        $names = implode(', ', array_map(fn (Action $a): string => $a->name, $request->getActions()));

        $select = new SelectListWidget($this->approvalItems());
        $select->onSelect(fn (SelectEvent $e) => $this->resumeApproval($e->getValue()));

        $body = new ContainerWidget();
        $body->add((new TextWidget('Tool call: ' . $names))->addStyleClass('tool-call'));
        $body->add($select);

        $card = new Card('approval required', $body);
        $this->transcript->addCard($card);
        $this->tui->setFocus($select);
        $this->tui->requestRender();

        // Suspend the agent fiber; resumeApproval() runs on the loop fiber when
        // the user selects, and resumes us with the chosen decision value.
        $this->approvalSuspension = EventLoop::getSuspension();
        $decision = $this->approvalSuspension->suspend();

        $this->applyDecision($request, (string) $decision);

        $this->transcript->remove($card);
        $this->tui->setFocus($this->input);
        $this->tui->requestRender();
    }

    /**
     * @return list<array{value: string, label: string, description?: string}>
     */
    private function approvalItems(): array
    {
        return [
            ['value' => 'once', 'label' => 'Allow once', 'description' => 'Run this tool call'],
            ['value' => 'session', 'label' => 'Allow for session', 'description' => "Don't ask again this session"],
            ['value' => 'reject', 'label' => 'Reject', 'description' => 'Block and tell the agent'],
        ];
    }

    private function resumeApproval(string $decision): void
    {
        if ($this->approvalSuspension === null) {
            return;
        }

        $suspension = $this->approvalSuspension;
        $this->approvalSuspension = null;
        $suspension->resume($decision);
    }

    /**
     * Apply a user decision to every action in the request. Pure (testable).
     */
    public function applyDecision(ApprovalRequest $request, string $decision): void
    {
        foreach ($request->getActions() as $action) {
            if ($decision === 'session') {
                $action->approve();
                $this->policy->allowForSession($action->name);
            } elseif ($decision === 'reject') {
                $action->reject('User rejected this tool call.');
            } else {
                $action->approve(); // 'once'
            }
        }
    }

    private function rejectAll(ApprovalRequest $request, string $reason): void
    {
        foreach ($request->getActions() as $action) {
            if ($action->isPending()) {
                $action->reject($reason);
            }
        }
    }

    /**
     * Run the agent turn on a deferred fiber so the UI keeps rendering.
     */
    private function runAgent(string $input): void
    {
        $this->busy = true;

        EventLoop::defer(function () use ($input): void {
            try {
                $this->orchestrator->chat($input);
            } catch (Throwable $e) {
                $this->transcript->addCard(
                    (new TextWidget('Error: ' . $e->getMessage()))->addStyleClass('error')
                );
            } finally {
                $this->busy = false;
                $this->tui->setFocus($this->input);
                $this->tui->requestRender();
            }
        });
    }

    private function refreshStatus(): void
    {
        $this->statusBar->setText($this->renderStatus());
        $this->tui->requestRender();
    }

    private function renderStatus(): string
    {
        $provider = $this->settings->getDefaultProvider() ?? 'maestro';
        $model = $this->settings->get('providers.' . $provider . '.model');

        $modes = [];
        if ($this->policy->autoMode) {
            $modes[] = 'AUTO';
        }
        if ($this->policy->planMode) {
            $modes[] = 'PLAN';
        }
        $modeSuffix = $modes !== [] ? ' · ' . implode('/', $modes) : '';

        $identity = $model !== null
            ? $provider . '/' . $model
            : $provider;

        return sprintf('maestro · %s%s · Ctrl+C to exit', $identity, $modeSuffix);
    }
}
