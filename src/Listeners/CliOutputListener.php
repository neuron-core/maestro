<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Listeners;

use NeuronAI\Workflow\Interrupt\Action;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Console\SpinnerProgress;
use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotType;
use NeuronCore\Maestro\Extension\Ui\UiEngine;
use NeuronCore\Maestro\Rendering\MarkdownRenderer;
use NeuronCore\Maestro\Settings\SettingsInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use function in_array;
use function str_repeat;

class CliOutputListener
{
    protected array $sessionAllowedActions = [
        'read_file'
    ];
    protected ?SpinnerProgress $spinner = null;

    public function __construct(
        protected readonly InputInterface $input,
        protected readonly OutputInterface $output,
        protected readonly SettingsInterface $settings,
        protected readonly RendererRegistry $renderers,
        protected readonly UiEngine $uiEngine,
        protected readonly MarkdownRenderer $markdownRenderer,
    ) {
    }

    public function onThinking(AgentThinkingEvent $event): void
    {
        $this->spinner = new SpinnerProgress();
        $this->spinner->start();
    }

    public function onResponse(AgentResponseEvent $event): void
    {
        $this->clearLine();
        $content = $this->markdownRenderer->render($event->content);
        $this->uiEngine->slots()->slot(SlotType::CONTENT)->add($content);
        $this->renderCycle();
    }

    public function onToolApprovalRequested(ToolApprovalRequestedEvent $event): void
    {
        $this->clearLine();

        foreach ($event->approvalRequest->getPendingActions() as $action) {
            $this->uiEngine->slots()->slot(SlotType::CONTENT)->add(
                $this->renderers->render($action->name, $action->description) . "\n"
            );
            $this->renderCycle();

            if (in_array($action->name, $this->sessionAllowedActions, true)) {
                $action->approve();
                continue;
            }

            $decision = $this->askDecision();
            $this->processDecision($action, $decision);
        }
    }

    protected function renderCycle(): void
    {
        $this->uiEngine->renderContent($this->output);
        $this->uiEngine->slots()->clear(SlotType::CONTENT);
        $this->uiEngine->renderStatus($this->output);
        $this->uiEngine->renderFooter($this->output);
    }

    protected function askDecision(): string
    {
        $values = ['allow', 'session', 'reject'];

        $index = (new SelectMenuHelper($this->output))->ask("Options: ", [
            'Allow once',
            'Allow for session',
            'Reject',
        ]);

        return $values[$index];
    }

    protected function processDecision(Action $action, string $decision): void
    {
        if (in_array($decision, ['allow', 'session'], true)) {
            $action->approve();

            if ($decision === 'session') {
                $this->sessionAllowedActions[] = $action->name;
            }
        } else {
            $feedback = $this->askFeedback();
            $action->reject($feedback ?: null);
        }
        $this->output->writeln('');
    }

    protected function askFeedback(): ?string
    {
        $helper = new QuestionHelper();
        $question = new Question(
            Text::content('Tell me what to do instead (press Enter to skip): ')->yellow()->build()
        );

        return $helper->ask($this->input, $this->output, $question);
    }

    protected function clearLine(): void
    {
        if ($this->spinner instanceof SpinnerProgress) {
            $this->spinner->finish();
            $this->spinner = null;
        }
        $this->output->write("\r" . str_repeat(' ', 50) . "\r");
    }
}
