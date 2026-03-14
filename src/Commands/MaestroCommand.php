<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use Exception;
use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\Console\Inline\HelpInlineCommand;
use NeuronCore\Maestro\Console\Inline\InitInlineCommand;
use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Extension\Coding\CodingExtension;
use NeuronCore\Maestro\Extension\Core\CoreExtension;
use NeuronCore\Maestro\Extension\ExtensionLoader;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Listeners\CliOutputListener;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Rendering\Renderers\GenericRenderer;
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
use function preg_split;
use function readline;
use function str_starts_with;
use function substr;
use function trim;

use const JSON_PRETTY_PRINT;
use const STDIN;

#[AsCommand(
    name: 'maestro',
    description: 'Maestro - coding agent built with Neuron AI framework',
)]
class MaestroCommand extends Command
{
    protected ExtensionLoader $loader;

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
            $output->writeln(Text::content('  maestro init')->white()->build());
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

        $this->loader = ExtensionLoader::create(new GenericRenderer());

        // Register core extensions first so user extensions can override them
        $this->loader->registerCore(
            new CoreExtension(),
            new CodingExtension(),
        );

        // Load user extensions from settings
        try {
            $this->loader->load($settings->all());
        } catch (Exception $e) {
            $output->writeln(Text::content('Failed to load extensions: ' . $e->getMessage())->red()->build());
            $output->writeln('');
        }

        $this->registerCoreCommands($this->loader->commands());

        $dispatcher = new EventDispatcher();
        foreach ($this->loader->events()->registeredEvents() as $event) {
            foreach ($this->loader->events()->handlersFor($event) as $handler) {
                $dispatcher->subscribe($event, $handler);
            }
        }

        $listener = new CliOutputListener(
            $input,
            $output,
            $settings,
            $this->loader->renderers(),
            $this->loader->uiEngine(),
        );

        $dispatcher->subscribe(AgentThinkingEvent::class, $listener->onThinking(...));
        $dispatcher->subscribe(AgentResponseEvent::class, $listener->onResponse(...));
        $dispatcher->subscribe(ToolApprovalRequestedEvent::class, $listener->onToolApprovalRequested(...));

        $orchestrator = new AgentOrchestrator(
            new MaestroAgent($settings, $this->loader->tools(), $this->loader->memories()),
            $dispatcher,
        );

        $this->loader->uiEngine()->renderHeader($output);

        while (true) {
            $output->writeln('');
            $userInput = trim($this->readInput('> '));
            $output->writeln('');

            if (in_array($userInput, ['', 'exit'], true)) {
                break;
            }

            if (str_starts_with($userInput, '/')) {
                [$commandName, $args] = $this->readInlineCommand($userInput);

                if ($this->loader->commands()->has($commandName)) {
                    try {
                        $this->loader->commands()->get($commandName)->execute($args, $input, $output);
                    } catch (Exception $e) {
                        $output->writeln(Text::content('Command error: ' . $e->getMessage())->red()->build() . "\n");
                    }
                    continue;
                }

                $output->writeln(Text::content("Unknown command: /{$commandName}")->yellow()->build());
                $output->writeln(Text::content('Type /help to list available commands.')->gray()->build());
                $output->writeln('');
                continue;
            }

            try {
                $orchestrator->chat($userInput);
            } catch (Exception $e) {
                $output->writeln(Text::content('Error: ' . $e->getMessage())->red()->build()."\n");
            }
        }

        $output->writeln(Text::content('Goodbye!')->cyan()->build());
        return Command::SUCCESS;
    }

    protected function readInput(string $prompt): string
    {
        if (function_exists('readline')) {
            return (string) readline($prompt);
        }

        echo $prompt;
        return (string) fgets(STDIN);
    }

    protected function readInlineCommand(string $input): array
    {
        $commandString = substr($input, 1);
        $parts = preg_split('/\s+/', $commandString, 2);
        $commandName = $parts[0];
        $args = $parts[1] ?? '';
        return [$commandName, $args];
    }

    protected function registerCoreCommands(CommandRegistry $registry): void
    {
        $registry->register(new InitInlineCommand());
        $registry->register(new HelpInlineCommand($registry));
    }
}
