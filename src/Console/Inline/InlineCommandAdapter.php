<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_unshift;
use function preg_replace;
use function preg_split;

/**
 * Adapter that wraps a Symfony Console command as an inline command.
 *
 * This allows reusing existing Symfony commands (like InitCommand) within
 * the interactive console without rewriting their logic.
 */
class InlineCommandAdapter implements InlineCommand
{
    private readonly string $name;
    private readonly string $description;

    /**
     * @param string|null $overrideName Optional override for the inline command name
     */
    public function __construct(private readonly Command $command, ?string $overrideName = null)
    {
        $this->name = $overrideName ?? $this->extractCommandName($this->command->getName());
        $this->description = $this->command->getDescription();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        // Convert args string to array for Symfony's ArrayInput
        $argsArray = $args !== '' ? preg_split('/\s+/', $args) : [];

        // Add the command name (full Symfony command name)
        array_unshift($argsArray, $this->command->getName());

        // Create a new input for the wrapped command
        $commandInput = new ArrayInput($argsArray);

        // Set interactive to true to preserve interactive prompts
        $commandInput->setInteractive(true);

        $exitCode = $this->command->run($commandInput, $output);

        if ($exitCode !== Command::SUCCESS) {
            $output->writeln('');
            $output->writeln("<fg=yellow>Command exited with code: $exitCode</>");
            $output->writeln('');
        }
    }

    /**
     * Extract the last part of a Symfony command name.
     * "maestro:init" -> "init"
     */
    private function extractCommandName(string $fullName): string
    {
        return (string) preg_replace('/^.*?:/', '', $fullName);
    }
}
