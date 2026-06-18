<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use NeuronCore\Maestro\Commands\InitCommand;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function fgets;
use function strtolower;
use function trim;

use const STDIN;

/**
 * Inline wrapper for the InitCommand.
 *
 * The settings wizard is interactive and runs against a normal (non-raw)
 * terminal — it is used by the bootstrap before the TUI starts, and is not
 * routed inside the TUI.
 */
class InitInlineCommand implements InlineCommand
{
    private readonly InitCommand $initCommand;

    public function __construct()
    {
        $this->initCommand = new InitCommand();
    }

    public function getName(): string
    {
        return 'init';
    }

    public function getDescription(): string
    {
        return 'Initialize Maestro settings file';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $settings = new Settings();

        if ($settings->fileExists()) {
            $output->writeln('');
            $output->writeln('<comment>Settings file already exists: ' . $settings->getSettingsPath() . '</comment>');
            $output->writeln('<comment>Re-running init will overwrite it. Continue? (y/n):</comment>');

            $response = trim((string) fgets(STDIN));
            if (strtolower($response) !== 'y') {
                $output->writeln('<info>Cancelled.</info>');
                $output->writeln('');

                return;
            }
        }

        $commandInput = new ArrayInput([]);
        $commandInput->setInteractive(true);
        $this->initCommand->run($commandInput, $output);

        $output->writeln('');
        $output->writeln('<info>Settings updated. You can continue using Maestro.</info>');
        $output->writeln('');
    }
}
