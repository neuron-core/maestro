<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console\Inline;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Interface for inline commands that can be invoked from the interactive console.
 *
 * Example: "/init" would call execute() on a command named "init".
 */
interface InlineCommand
{
    /**
     * The command name (without the / prefix).
     */
    public function getName(): string;

    /**
     * Human-readable description of what this command does.
     */
    public function getDescription(): string;

    /**
     * Execute the inline command.
     *
     * @param string $args Arguments passed after the command (e.g., "/init --force" -> "--force")
     * @param InputInterface $input The Symfony console input
     * @param OutputInterface $output The Symfony console output
     */
    public function execute(string $args, InputInterface $input, OutputInterface $output): void;
}
