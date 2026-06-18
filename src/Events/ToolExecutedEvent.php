<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Events;

/**
 * Dispatched after a tool has executed.
 *
 * Carries the tool name, its inputs, and its textual result. The TUI presenter
 * uses a ToolView to render this (diff for edits, snippet for reads, generic
 * JSON otherwise).
 */
class ToolExecutedEvent
{
    /**
     * @param string $toolName Tool name, e.g. 'edit_file'.
     * @param array<string, mixed> $inputs The tool's input arguments.
     * @param string $result The tool's textual result.
     */
    public function __construct(
        public readonly string $toolName,
        public readonly array $inputs,
        public readonly string $result,
    ) {
    }
}
