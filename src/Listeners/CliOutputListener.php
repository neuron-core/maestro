<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Listeners;

use NeuronAI\Workflow\Interrupt\Action;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Rendering\ToolRendererMap;
use NeuronCore\Maestro\Settings\SettingsInterface;
use NeuronCore\Maestro\Terminal\Color;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use function in_array;
use function str_repeat;

class CliOutputListener
{
    private array $sessionAllowedActions = [];
    private array $alwaysAllowedActions;

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly SettingsInterface $settings,
        private readonly ToolRendererMap $rendererMap,
    ) {
        $this->alwaysAllowedActions = $settings->getAllowedTools();
    }

    public function onThinking(AgentThinkingEvent $event): void
    {
        $this->output->write("\nThinking...");
    }

    public function onResponse(AgentResponseEvent $event): void
    {
        $this->clearLine();
        $this->output->writeln($event->content);
        $this->output->writeln('');
    }

    public function onToolApprovalRequested(ToolApprovalRequestedEvent $event): void
    {
        $this->clearLine();

        foreach ($event->approvalRequest->getPendingActions() as $action) {
            $this->output->write($this->rendererMap->render($action->name, $action->description));
            $this->output->writeln('');

            if (in_array($action->name, $this->alwaysAllowedActions, true) ||
                in_array($action->name, $this->sessionAllowedActions, true)) {
                $action->approve();
                continue;
            }

            $decision = $this->askDecision();
            $this->processDecision($action, $decision);
        }
    }

    private function askDecision(): string
    {
        $values = ['allow', 'session', 'always', 'reject'];

        $index = (new SelectMenuHelper($this->output))->ask('Options:', [
            'Allow once',
            'Allow for session',
            'Always allow',
            'Reject',
        ]);

        return $values[$index];
    }

    private function processDecision(Action $action, string $decision): void
    {
        if (in_array($decision, ['allow', 'session', 'always'], true)) {
            $action->approve();

            if ($decision === 'session') {
                $this->sessionAllowedActions[] = $action->name;
            } elseif ($decision === 'always') {
                $this->alwaysAllowedActions[] = $action->name;
                $this->sessionAllowedActions[] = $action->name;
                $this->settings->addAllowedTool($action->name);
                $this->output->writeln((string) Color::cyan("Tool '{$action->name}' is now always allowed."));
            }
        } else {
            // Prompt for feedback when rejecting
            $feedback = $this->askFeedback();
            $action->reject($feedback ?: null);
        }

        $this->output->writeln('');
    }

    private function askFeedback(): ?string
    {
        $helper = new QuestionHelper();
        $question = new Question((string) Color::yellow('Tell me what to do instead (press Enter to skip): '));

        return $helper->ask($this->input, $this->output, $question);
    }

    private function clearLine(): void
    {
        $this->output->write("\r" . str_repeat(' ', 50) . "\r");
    }
}
