<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

use function is_array;
use function json_decode;
use function sprintf;

/**
 * Renders coding tool results with formatted diff output.
 */
class CodingToolResultRenderer implements ToolResultRendererInterface
{
    private DiffRenderer $diffRenderer;

    /**
     * List of coding tool names that this renderer handles.
     */
    private array $supportedTools = [
        'write_file',
        'edit_file',
        'patch_file',
        'create_file',
        'delete_file',
    ];

    public function __construct(?DiffRenderer $diffRenderer = null)
    {
        $this->diffRenderer = $diffRenderer ?? new DiffRenderer();
    }

    public function canRender(string $toolName, string $result): bool
    {
        return in_array($toolName, $this->supportedTools, true)
            && $this->isValidCodingToolResult($result);
    }

    public function render(string $toolName, string $result): string
    {
        $data = json_decode($result, true);

        if (!is_array($data) || !isset($data['status'])) {
            // Fallback to the simple display if JSON parsing fails
            return sprintf("%s( %s )", $toolName, $result);
        }

        if ($data['status'] === 'error') {
            return $this->renderError($toolName, $data);
        }

        return $this->renderSuccess($toolName, $data);
    }

    /**
     * Check if the result is a valid coding tool JSON response.
     *
     * @param string $result The tool result
     * @return bool True if valid
     */
    private function isValidCodingToolResult(string $result): bool
    {
        $data = json_decode($result, true);

        if (!is_array($data)) {
            return false;
        }

        return isset($data['status']) && isset($data['operation']);
    }

    /**
     * Render an error result.
     *
     * @param string $toolName The tool name
     * @param array $data The parsed error data
     * @return string Formatted error output
     */
    private function renderError(string $toolName, array $data): string
    {
        $message = $data['message'] ?? 'Unknown error';
        return sprintf("Error: %s( %s )", $toolName, $message);
    }

    /**
     * Render a successful result with diff.
     *
     * @param string $toolName The tool name
     * @param array $data The parsed success data
     * @return string Formatted output with diff
     */
    private function renderSuccess(string $toolName, array $data): string
    {
        $output = '';

        // Display operation header
        $output .= "\n";
        $operation = $data['operation'] ?? 'unknown';
        $filePath = $data['file_path'] ?? 'unknown';
        $output .= sprintf("Operation: %s\n", strtoupper($operation));
        $output .= sprintf("File: %s\n", $filePath);

        // Display statistics or size
        if (isset($data['stats'])) {
            $stats = $data['stats'];
            $output .= sprintf(
                "Changes: +%d, -%d, ~%d\n",
                $stats['added'] ?? 0,
                $stats['removed'] ?? 0,
                $stats['changed'] ?? 0
            );
        } elseif (isset($data['size'])) {
            $output .= sprintf("Size: %d bytes\n", $data['size']);
        }

        $output .= "\n";

        // Render the diff
        if (isset($data['diff'])) {
            $output .= $this->diffRenderer->render($data['diff']);
            $output .= "\n";
        }

        // Display message if available
        if (isset($data['message'])) {
            $output .= "\n";
            $output .= $data['message'];
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Get the diff renderer instance.
     *
     * @return DiffRenderer
     */
    public function getDiffRenderer(): DiffRenderer
    {
        return $this->diffRenderer;
    }
}
