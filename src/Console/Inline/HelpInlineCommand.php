<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Ui\Text;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function max;
use function str_pad;
use function strlen;

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
        $output->writeln(Text::content('Available Commands:')->primary()->bold()->build());
        $output->writeln('');

        $commands = $this->registry->listCommands();

        if ($commands === []) {
            $output->writeln(Text::content('  No inline commands registered.')->muted()->build());
            $output->writeln('');
            return;
        }

        $maxNameLength = 0;
        foreach ($commands as $cmd) {
            $maxNameLength = max($maxNameLength, strlen($cmd['name']));
        }

        foreach ($commands as $cmd) {
            $name = Text::content('  /' . $cmd['name'])->info()->bold()->build();
            $description = Text::content($cmd['description'])->build();

            // Pad the name column for alignment
            $paddedName = str_pad($name, $maxNameLength + 5);

            $output->writeln($paddedName . ' - '. $description);
        }

        $output->writeln('');
        $output->writeln(Text::content('Usage: Type /command_name to run a command.')->muted()->build());
        $output->writeln(Text::content('       Type "exit" or leave blank to quit.')->muted()->build());
        $output->writeln('');
    }
}
