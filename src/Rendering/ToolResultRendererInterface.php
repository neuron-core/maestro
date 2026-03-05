<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

/**
 * Interface for rendering tool execution results.
 */
interface ToolResultRendererInterface
{
    /**
     * Check if this renderer can handle the given tool result.
     *
     * @param string $toolName The name of the tool
     * @param string $result The tool result
     * @return bool True if this renderer can handle the result
     */
    public function canRender(string $toolName, string $result): bool;

    /**
     * Render the tool result.
     *
     * @param string $toolName The name of the tool
     * @param string $result The tool result
     * @return string The rendered output
     */
    public function render(string $toolName, string $result): string;
}
