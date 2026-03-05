<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tools\Coding;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

use function dirname;
use function file_exists;
use function is_dir;
use function is_writable;
use function json_encode;
use function mb_strlen;
use function implode;
use function preg_match_all;
use function preg_split;

/**
 * Create a new file with content.
 * Returns a structured change description for CLI rendering.
 * Fails if the file already exists.
 */
class CreateFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'create_file',
            description: 'Create a new file with the specified content. Fails if the file already exists. Returns a structured description of the new file for review before creating.',
        );
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Path to the new file to create.',
                required: true,
            ),
            ToolProperty::make(
                name: 'content',
                type: PropertyType::STRING,
                description: 'The content to write to the new file.',
                required: true,
            ),
        ];
    }

    /**
     * @param string $file_path Path to the new file
     * @param string $content File content
     * @return string JSON-encoded structured change description
     */
    public function __invoke(string $file_path, string $content): string
    {
        // Check if file already exists
        if (file_exists($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' already exists. Use write_file to overwrite.",
                'operation' => 'create',
                'file_path' => $file_path,
            ]);
        }

        // Check directory exists and is writable
        $directory = dirname($file_path);
        if (!is_dir($directory)) {
            return json_encode([
                'status' => 'error',
                'message' => "Directory '{$directory}' does not exist.",
                'operation' => 'create',
                'file_path' => $file_path,
            ]);
        }

        if (!is_writable($directory)) {
            return json_encode([
                'status' => 'error',
                'message' => "Directory '{$directory}' is not writable.",
                'operation' => 'create',
                'file_path' => $file_path,
            ]);
        }

        // Calculate line count
        $lineCount = preg_match_all('/\r\n|\r|\n/', $content, $matches);
        $lineCount += 1; // Add 1 for the last line

        return json_encode([
            'status' => 'proposed',
            'operation' => 'create',
            'file_path' => $file_path,
            'size' => mb_strlen($content),
            'line_count' => $lineCount,
            'content' => $content,
            'diff' => "--- /dev/null\n" .
                      "+++ b/{$file_path}\n" .
                      "@@ -0,0 +1,{$lineCount} @@\n" .
                      implode("\n", preg_split('/\r\n|\r|\n/', $content)) . "\n",
            'message' => "Proposed new file '{$file_path}' ({$lineCount} lines)",
        ]);
    }
}
