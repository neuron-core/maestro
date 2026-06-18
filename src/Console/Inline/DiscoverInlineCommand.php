<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use NeuronCore\Maestro\Commands\DiscoverCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Inline wrapper for DiscoverCommand.
 *
 * Allows running "maestro discover" from within the session via "/discover".
 */
class DiscoverInlineCommand implements InlineCommand
{
    private readonly DiscoverCommand $discoverCommand;

    public function __construct()
    {
        $this->discoverCommand = new DiscoverCommand();
    }

    public function getName(): string
    {
        return 'discover';
    }

    public function getDescription(): string
    {
        return 'Discover Maestro extensions from installed Composer packages';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('');

        $commandInput = new ArrayInput([]);
        $this->discoverCommand->run($commandInput, $output);

        $output->writeln('');
        $output->writeln('<info>Extensions will be loaded on the next Maestro restart.</info>');
        $output->writeln('To reload extensions immediately, exit and restart Maestro.');
        $output->writeln('');
    }
}
