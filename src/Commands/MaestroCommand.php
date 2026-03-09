<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use Exception;
use NeuronCore\Maestro\Agent\CodingAgent;
use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Listeners\CliOutputListener;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Rendering\ToolRendererMap;
use NeuronCore\Maestro\Settings\Settings;
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
    name: 'maestro',
    description: 'Maestro - coding agent built with Neuron AI framework',
)]
class MaestroCommand extends Command
{
    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = new Settings();

        if (!$settings->fileExists()) {
            $output->writeln('');
            $output->writeln(Text::content('Warning: Settings file not found at ' . $settings->getSettingsPath())->red()->build());
            $output->writeln(Text::content('The agent requires AI provider connection information.')->red()->build());
            $output->writeln('');
            $output->writeln(Text::content('Run the interactive configuration command to get started:')->cyan()->build());
            $output->writeln(Text::content('  maestro configure')->white()->build());
            $output->writeln('');
            $output->writeln(Text::content('Or create a .maestro/settings.json file manually with your AI provider configuration:')->cyan()->build());
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
            $output->writeln(Text::content('Warning: Settings file is missing valid provider configuration.')->red()->build());
            $output->writeln(Text::content("The 'provider.type' setting is required.")->red()->build());
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
        $output->writeln(Text::content("  __  __                 _             ")->cyan()->bold()->build());
        $output->writeln(Text::content(" |  \\/  |               | |            ")->cyan()->bold()->build());
        $output->writeln(Text::content(" | \\  / | __ _  ___  ___| |_ _ __ ___  ")->cyan()->bold()->build());
        $output->writeln(Text::content(" | |\\/| |/ _` |/ _ \\/ __| __| '__/ _ \\ ")->cyan()->bold()->build());
        $output->writeln(Text::content(" | |  | | (_| |  __/\\__ \\ |_| | | (_) |")->cyan()->bold()->build());
        $output->writeln(Text::content(" |_|  |_|\\__,_|\\___||___/\\__|_|  \\___/ ")->cyan()->bold()->build());
        $output->writeln("");
        $output->writeln(Text::content(" Coding Agent  •  Powered by Neuron AI framework (https://docs.neuron-ai.dev) ")->white()->bold()->build());
        $output->writeln("\n");

        while (true) {
            $userInput = trim($this->readInput('> '));

            if (in_array($userInput, ['', 'exit'], true)) {
                break;
            }

            try {
                $orchestrator->chat($userInput);
            } catch (Exception $e) {
                $output->writeln(Text::content('Error: ' . $e->getMessage())->red()->build());
                $output->writeln('');
            }
        }

        $output->writeln(Text::content('Goodbye!')->cyan()->build());
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
