<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Listeners;

use NeuronAI\Workflow\Interrupt\Action;
use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Console\SpinnerProgress;
use NeuronCore\Maestro\Rendering\ToolRendererMap;
use NeuronCore\Maestro\Settings\SettingsInterface;
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
    private ?SpinnerProgress $spinner = null;

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
        $this->spinner = new SpinnerProgress($this->output);
        $this->spinner->setMessage('Thinking...');
        $this->spinner->start();
        $this->spinner->display();
    }

    public function onResponse(AgentResponseEvent $event): void
    {
        $this->clearLine();
        $this->output->writeln($event->content);
    }

    public function onToolApprovalRequested(ToolApprovalRequestedEvent $event): void
    {
        $this->clearLine();

        foreach ($event->approvalRequest->getPendingActions() as $action) {
            $this->output->writeln($this->rendererMap->render($action->name, $action->description));

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

        $index = (new SelectMenuHelper($this->output))->ask("Options: ", [
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
                $this->output->writeln(
                    Text::content("Tool '{$action->name}' is now always allowed.")->cyan()->build()
                );
            }
        } else {
            // Prompt for feedback when rejecting
            $feedback = $this->askFeedback();
            $action->reject($feedback ?: null);
        }
    }

    private function askFeedback(): ?string
    {
        $helper = new QuestionHelper();
        $question = new Question(
            Text::content('Tell me what to do instead (press Enter to skip): ')->yellow()->build()
        );

        return $helper->ask($this->input, $this->output, $question);
    }

    private function clearLine(): void
    {
        $this->output->write("\r" . str_repeat(' ', 50) . "\r");
        if ($this->spinner instanceof \NeuronCore\Maestro\Console\SpinnerProgress) {
            $this->spinner->finish();
            $this->spinner = null;
        }
    }
}
