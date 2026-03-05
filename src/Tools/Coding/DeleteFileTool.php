<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tools\Coding;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

use function dirname;
use function file_exists;
use function file_get_contents;
use function is_file;
use function is_writable;
use function json_encode;
use function max;
use function preg_match_all;
use function preg_split;
use function strlen;

/**
 * Delete a file from the filesystem.
 * Returns a structured description for CLI rendering.
 */
class DeleteFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'delete_file',
            description: 'Delete a file from the filesystem. Returns a structured description of the file being deleted for confirmation.',
        );
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Path to the file to delete.',
                required: true,
            ),
        ];
    }

    /**
     * @param string $file_path Path to the file
     * @return string JSON-encoded structured change description
     */
    public function __invoke(string $file_path): string
    {
        // Check if file exists
        if (!file_exists($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' does not exist.",
                'operation' => 'delete',
                'file_path' => $file_path,
            ]);
        }

        if (!is_file($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "Path '{$file_path}' is not a file.",
                'operation' => 'delete',
                'file_path' => $file_path,
            ]);
        }

        if (!is_writable(dirname($file_path))) {
            return json_encode([
                'status' => 'error',
                'message' => "Cannot delete '{$file_path}': parent directory is not writable.",
                'operation' => 'delete',
                'file_path' => $file_path,
            ]);
        }

        // Get file info
        $content = file_get_contents($file_path);
        if ($content === false) {
            $content = '';
        }

        $size = strlen($content);
        $lineCount = preg_match_all('/\r\n|\r|\n/', $content, $matches);
        $lineCount += 1; // Add 1 for the last line
        $lineCount = max($lineCount, 1);

        // Generate diff showing deletion
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $diff = "--- a/{$file_path}\n" .
                "+++ /dev/null\n" .
                "@@ -1,{$lineCount} +0,0 @@\n";
        foreach ($lines as $line) {
            $diff .= "-{$line}\n";
        }

        return json_encode([
            'status' => 'proposed',
            'operation' => 'delete',
            'file_path' => $file_path,
            'size' => $size,
            'line_count' => $lineCount,
            'diff' => $diff,
            'original' => $content,
            'message' => "Proposed deletion of file '{$file_path}' ({$lineCount} lines, {$size} bytes)",
        ]);
    }
}
