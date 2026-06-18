<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function max;
use function str_pad;
use function strlen;
use function sprintf;

/**
 * Displays help information about available inline commands.
 */
class HelpInlineCommand implements InlineCommand
{
    public function __construct(
        private readonly CommandRegistry $registry
    ) {
    }

    public function getName(): string
    {
        return 'help';
    }

    public function getDescription(): string
    {
        return 'Show available inline commands';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<options=bold>Available Commands:</>');
        $output->writeln('');

        $commands = $this->registry->listCommands();

        if ($commands === []) {
            $output->writeln('  No inline commands registered.');
            $output->writeln('');

            return;
        }

        $maxNameLength = 0;
        foreach ($commands as $cmd) {
            $maxNameLength = max($maxNameLength, strlen($cmd['name']));
        }

        foreach ($commands as $cmd) {
            $padded = str_pad('  /' . $cmd['name'], $maxNameLength + 5);
            $output->writeln(sprintf('<info>%s</info> - %s', $padded, $cmd['description']));
        }

        $output->writeln('');
        $output->writeln('Usage: type /command to run it, or type a prompt to chat.');
        $output->writeln('       Ctrl+C to exit.');
        $output->writeln('');
    }
}
