<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use Exception;
use NeuronCore\Maestro\Agent\CodingAgent;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Listeners\CliOutputListener;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Rendering\ToolRendererMap;
use NeuronCore\Maestro\Settings\Settings;
use NeuronCore\Maestro\Terminal\Color;
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
    description: 'Synapse Coding Agent - built with Neuron AI framework',
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
            $output->writeln((string) Color::red('Warning: Settings file not found at ' . $settings->getSettingsPath()));
            $output->writeln((string) Color::red('The agent requires AI provider connection information.'));
            $output->writeln('');
            $output->writeln((string) Color::cyan('Create a settings.json file with your AI provider configuration:'));
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
            $output->writeln((string) Color::red('Warning: Settings file is missing valid provider configuration.'));
            $output->writeln((string) Color::red("The 'provider.type' setting is required."));
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
        $output->writeln((string) Color::cyan("  __  __                 _             ")->bold());
        $output->writeln((string) Color::cyan(" |  \\/  |               | |            ")->bold());
        $output->writeln((string) Color::cyan(" | \\  / | __ _  ___  ___| |_ _ __ ___  ")->bold());
        $output->writeln((string) Color::cyan(" | |\\/| |/ _` |/ _ \\/ __| __| '__/ _ \\ ")->bold());
        $output->writeln((string) Color::cyan(" | |  | | (_| |  __/\\__ \\ |_| | | (_) |")->bold());
        $output->writeln((string) Color::cyan(" |_|  |_|\\__,_|\\___||___/\\__|_|  \\___/ ")->bold());
        $output->writeln("");
        $output->writeln((string) Color::white(" Coding Agent  •  Powered by Neuron AI framework (https://docs.neuron-ai.dev) ")->bold());
        $output->writeln("\n");

        while (true) {
            $userInput = trim($this->readInput('> '));

            if (in_array($userInput, ['', 'exit'], true)) {
                break;
            }

            try {
                $orchestrator->chat($userInput);
            } catch (Exception $e) {
                $output->writeln((string) Color::red('Error: ' . $e->getMessage()));
                $output->writeln('');
            }
        }

        $output->writeln((string) Color::cyan('Goodbye!'));
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
