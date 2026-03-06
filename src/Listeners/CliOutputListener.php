<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Listeners;

use NeuronCore\Synapse\Console\SelectMenuHelper;
use NeuronCore\Synapse\Events\AgentResponseEvent;
use NeuronCore\Synapse\Events\AgentThinkingEvent;
use NeuronCore\Synapse\Events\ToolApprovalRequestedEvent;
use NeuronCore\Synapse\Rendering\ToolRendererMap;
use NeuronCore\Synapse\Settings\SettingsInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function str_repeat;

class CliOutputListener
{
    private array $sessionAllowedActions = [];
    private array $alwaysAllowedActions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly SettingsInterface $settings,
        private readonly ToolRendererMap $rendererMap,
    ) {
        $this->alwaysAllowedActions = $settings->getAllowedTools();
    }

    public function onThinking(AgentThinkingEvent $event): void
    {
        $this->output->write('Thinking...');
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

    private function processDecision(object $action, string $decision): void
    {
        if (in_array($decision, ['allow', 'session', 'always'], true)) {
            $action->approve();

            if ($decision === 'session') {
                $this->sessionAllowedActions[] = $action->name;
            } elseif ($decision === 'always') {
                $this->alwaysAllowedActions[] = $action->name;
                $this->sessionAllowedActions[] = $action->name;
                $this->settings->addAllowedTool($action->name);
                $this->output->writeln("<info>Tool '{$action->name}' is now always allowed.</info>");
            }
        } else {
            $action->reject();
        }

        $this->output->writeln('');
    }

    private function clearLine(): void
    {
        $this->output->write("\r" . str_repeat(' ', 50) . "\r");
    }
}
