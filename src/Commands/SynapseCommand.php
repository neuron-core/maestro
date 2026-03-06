<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Commands;

use Exception;
use NeuronCore\Synapse\Agent\CodingAgent;
use NeuronCore\Synapse\EventBus\EventDispatcher;
use NeuronCore\Synapse\Events\AgentResponseEvent;
use NeuronCore\Synapse\Events\AgentThinkingEvent;
use NeuronCore\Synapse\Events\ToolApprovalRequestedEvent;
use NeuronCore\Synapse\Listeners\CliOutputListener;
use NeuronCore\Synapse\Orchestrator\AgentOrchestrator;
use NeuronCore\Synapse\Rendering\ToolRendererMap;
use NeuronCore\Synapse\Settings\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function fgets;
use function function_exists;
use function in_array;
use function json_encode;
use function readline;
use function trim;

use const JSON_PRETTY_PRINT;
use const STDIN;

#[AsCommand(
    name: 'synapse',
    description: 'Synapse Coding Agent - built with Neuron AI framework',
)]
class SynapseCommand extends Command
{
    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = new Settings();

        if (!$settings->fileExists()) {
            $output->writeln('<error>Warning: Settings file not found at ' . $settings->getSettingsPath() . '</error>');
            $output->writeln('<error>The agent requires AI provider connection information.</error>');
            $output->writeln('');
            $output->writeln('<info>Create a settings.json file with your AI provider configuration:</info>');
            $output->writeln(json_encode([
                'provider' => [
                    'type' => 'openai',
                    'api_key' => 'your-api-key',
                    'model' => 'gpt-5',
                ],
            ], JSON_PRETTY_PRINT));
            $output->writeln('');
            return Command::FAILURE;
        }

        if (!$settings->hasValidProvider()) {
            $output->writeln('<error>Warning: Settings file is missing valid provider configuration.</error>');
            $output->writeln("<error>The 'provider.type' setting is required.</error>");
            $output->writeln('');
            return Command::FAILURE;
        }

        $dispatcher = new EventDispatcher();
        $listener = new CliOutputListener($input, $output, $settings, ToolRendererMap::default());

        $dispatcher->subscribe(AgentThinkingEvent::class, $listener->onThinking(...));
        $dispatcher->subscribe(AgentResponseEvent::class, $listener->onResponse(...));
        $dispatcher->subscribe(ToolApprovalRequestedEvent::class, $listener->onToolApprovalRequested(...));

        $orchestrator = new AgentOrchestrator(CodingAgent::make($settings), $dispatcher);

        $output->writeln("\n");
        $output->writeln("<fg=cyan;options=bold>  __  __                 _             </>");
        $output->writeln("<fg=cyan;options=bold> |  \/  |               | |            </>");
        $output->writeln("<fg=cyan;options=bold> | \  / | __ _  ___  ___| |_ _ __ ___  </>");
        $output->writeln("<fg=cyan;options=bold> | |\/| |/ _` |/ _ \/ __| __| '__/ _ \ </>");
        $output->writeln("<fg=cyan;options=bold> | |  | | (_| |  __/\__ \ |_| | | (_) |</>");
        $output->writeln("<fg=cyan;options=bold> |_|  |_|\__,_|\___||___/\__|_|  \___/ </>");
        $output->writeln("");
        $output->writeln("<fg=white;options=bold> Coding Agent  •  Powered by Neuron AI framework (https://docs.neuron-ai.dev) </>");
        $output->writeln("\n");

        while (true) {
            $userInput = trim($this->readInput('> '));

            if (in_array($userInput, ['', 'exit'], true)) {
                break;
            }

            try {
                $orchestrator->chat($userInput);
            } catch (Exception $e) {
                $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
                $output->writeln('');
            }
        }

        $output->writeln('<info>Goodbye!</info>');
        return Command::SUCCESS;
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
