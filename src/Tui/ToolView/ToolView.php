<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Builds the transcript body widget for a tool execution.
 *
 * Extensions can register custom views per tool name (Phase 6 wires this to the
 * extension API). Built-ins cover the FileSystemToolkit tools.
 */
interface ToolView
{
    /**
     * @param string $toolName The tool name, e.g. 'edit_file'.
     * @param array<string, mixed> $inputs The tool's input arguments.
     * @param string $result The tool's textual result.
     */
    public function render(string $toolName, array $inputs, string $result): AbstractWidget;
}
