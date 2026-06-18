<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use Exception;
use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\Agent\ToolApprovalPolicy;
use NeuronCore\Maestro\Console\Inline\DiscoverInlineCommand;
use NeuronCore\Maestro\Console\Inline\HelpInlineCommand;
use NeuronCore\Maestro\Console\Inline\InitInlineCommand;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Extension\ExtensionLoader;
use NeuronCore\Maestro\Extension\ManifestManager;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Settings\Settings;
use NeuronCore\Maestro\Tui\MaestroTui;
use NeuronCore\Maestro\Tui\ToolView\ToolViewRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Thin entry point: validates settings, assembles the agent pipeline
 * (extensions, tools, memories, events, orchestrator), then hands control to
 * the Symfony TUI. All interaction lives in {@see MaestroTui}.
 */
#[AsCommand(
    name: 'maestro',
    description: 'Maestro - coding agent built with Neuron AI (Symfony TUI)',
)]
class MaestroCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = $this->bootstrapSettings($input, $output);
        if ($settings === null) {
            return Command::FAILURE;
        }

        // Load extensions (contribute tools, memories, inline commands, event handlers)
        $loader = ExtensionLoader::create($settings);
        (new ManifestManager())->ensureManifestExists();

        try {
            $loader->load($settings->getExtensions());
        } catch (Exception $e) {
            $output->writeln('<error>Failed to load extensions: ' . $e->getMessage() . '</error>');
        }

        $this->registerCoreCommands($loader->commands());

        // Shared event bus: extension handlers + the TUI presenter subscribe here.
        $dispatcher = new EventDispatcher();
        foreach ($loader->events()->registeredEvents() as $event) {
            foreach ($loader->events()->handlersFor($event) as $handler) {
                $dispatcher->subscribe($event, $handler);
            }
        }

        // Shared approval policy: consulted by the agent's middleware and the TUI.
        $policy = new ToolApprovalPolicy();

        $orchestrator = new AgentOrchestrator(
            new MaestroAgent($settings, $loader->tools(), $loader->memories(), $policy),
            $dispatcher,
        );

        $exit = (new MaestroTui(
            $settings,
            $orchestrator,
            $dispatcher,
            $policy,
            ToolViewRegistry::defaultRegistry(),
            $loader->commands(),
        ))->run();

        $output->writeln('');
        $output->writeln('<info>Goodbye!</info>');

        return $exit;
    }

    private function bootstrapSettings(InputInterface $input, OutputInterface $output): ?Settings
    {
        $settings = new Settings();

        if (!$settings->fileExists()) {
            $output->writeln('<comment>Welcome to Maestro — let us configure your settings.</comment>');
            (new InitInlineCommand())->execute('', $input, $output);
            $settings = new Settings();

            if (!$settings->fileExists()) {
                $output->writeln('<error>Failed to initialize settings.</error>');

                return null;
            }
        }

        if (!$settings->hasValidProvider()) {
            $output->writeln('<error>Settings file is missing a valid provider configuration. Run the init wizard.</error>');

            return null;
        }

        return $settings;
    }

    private function registerCoreCommands(CommandRegistry $registry): void
    {
        $registry->register(new InitInlineCommand());
        $registry->register(new DiscoverInlineCommand());
        $registry->register(new HelpInlineCommand($registry));
    }
}
