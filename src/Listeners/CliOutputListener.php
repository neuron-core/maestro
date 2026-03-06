<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Listeners;

use NeuronCore\Synapse\Events\AgentResponseEvent;
use NeuronCore\Synapse\Events\AgentThinkingEvent;
use NeuronCore\Synapse\Events\ToolApprovalRequestedEvent;
use NeuronCore\Synapse\Rendering\ToolResultRendererRegistry;
use NeuronCore\Synapse\Settings\SettingsInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function fgets;
use function function_exists;
use function in_array;
use function readline;
use function sprintf;
use function str_repeat;
use function strtolower;
use function trim;

use const STDIN;

class CliOutputListener
{
    private array $sessionAllowedActions = [];
    private array $alwaysAllowedActions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly SettingsInterface $settings,
        private readonly ToolResultRendererRegistry $rendererRegistry,
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
            $rendered = $this->rendererRegistry->render($action->name, $action->description);

            if ($rendered !== null) {
                $this->output->write($rendered);
            } else {
                $this->output->writeln(sprintf('%s( %s )', $action->name, $action->description));
            }

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
        $this->output->writeln('Options:');
        $this->output->writeln('  1) Allow');
        $this->output->writeln('  2) Session allow');
        $this->output->writeln('  3) Always allow');
        $this->output->writeln('  4) Reject');
        $this->output->writeln('');

        while (true) {
            $decision = strtolower(trim($this->readInput('Enter your choice (1/2/3/4): ')));

            if (in_array($decision, ['', '1', 'allow'], true)) {
                return 'allow';
            }

            if (in_array($decision, ['2', 'session', 's'], true)) {
                return 'session';
            }

            if (in_array($decision, ['3', 'always', 'a'], true)) {
                return 'always';
            }

            if (in_array($decision, ['4', 'reject', 'no', 'n', 'r'], true)) {
                return 'reject';
            }

            $this->output->writeln('<error>Invalid choice. Please enter 1, 2, 3, or 4.</error>');
        }
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

    private function readInput(string $prompt): string
    {
        if (function_exists('readline')) {
            return (string) readline($prompt);
        }

        echo $prompt;
        return (string) fgets(STDIN);
    }
}
