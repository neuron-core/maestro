<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use NeuronCore\Maestro\Commands\InitCommand;
use NeuronCore\Maestro\Console\Text;
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
 * Allows running "maestro init" from within the interactive console via "/init".
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

        // Check if settings already exist
        if ($settings->fileExists()) {
            $output->writeln('');
            $output->writeln(Text::content('Settings file already exists:')->yellow()->build());
            $output->writeln(Text::content('  ' . $settings->getSettingsPath())->white()->build());
            $output->writeln('');
            $output->writeln(Text::content('Re-running init will overwrite your existing settings.')->yellow()->build());
            $output->writeln('');
            $output->writeln(Text::content('Continue? (y/n): ')->yellow()->build());

            $response = trim(fgets(STDIN));
            if (strtolower($response) !== 'y') {
                $output->writeln(Text::content('Cancelled.')->cyan()->build());
                $output->writeln('');
                return;
            }
        }

        // Run the InitCommand directly
        $commandInput = new ArrayInput([]);
        $commandInput->setInteractive(true);
        $this->initCommand->run($commandInput, $output);

        // Show a message about restarting
        $output->writeln('');
        $output->writeln(Text::content('Settings updated. You can continue using Maestro.')->cyan()->build());
        $output->writeln('');
    }
}
