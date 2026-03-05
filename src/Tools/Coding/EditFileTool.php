<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tools\Coding;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

use function count;
use function file_exists;
use function file_get_contents;
use function is_readable;
use function is_writable;
use function json_encode;
use function mb_strlen;
use function preg_replace_callback;
use function str_replace;

/**
 * Edit a file by applying search-and-replace operations.
 * Returns a structured change description with diff for CLI rendering.
 */
class EditFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'edit_file',
            description: 'Edit a file by applying one or more search-and-replace operations. Each edit specifies a string to search for and its replacement. Returns a structured diff of changes for review before applying.',
        );
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Path to the file to edit.',
                required: true,
            ),
            ToolProperty::make(
                name: 'edits',
                type: PropertyType::ARRAY,
                description: 'Array of edits to apply. Each edit has "search" and "replace" strings.',
                required: true,
            ),
        ];
    }

    /**
     * @param string $file_path Path to the file
     * @param array $edits Array of edits with 'search' and 'replace' keys
     * @return string JSON-encoded structured change description
     */
    public function __invoke(string $file_path, array $edits): string
    {
        // Validate file exists
        if (!file_exists($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' does not exist.",
                'operation' => 'edit',
                'file_path' => $file_path,
            ]);
        }

        if (!is_readable($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' is not readable.",
                'operation' => 'edit',
                'file_path' => $file_path,
            ]);
        }

        if (!is_writable($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' is not writable.",
                'operation' => 'edit',
                'file_path' => $file_path,
            ]);
        }

        // Validate edits structure
        foreach ($edits as $i => $edit) {
            if (!isset($edit['search']) || !isset($edit['replace'])) {
                return json_encode([
                    'status' => 'error',
                    'message' => "Edit at index {$i} must have 'search' and 'replace' keys.",
                    'operation' => 'edit',
                    'file_path' => $file_path,
                ]);
            }
        }

        $originalContent = file_get_contents($file_path);
        if ($originalContent === false) {
            return json_encode([
                'status' => 'error',
                'message' => "Unable to read file '{$file_path}'.",
                'operation' => 'edit',
                'file_path' => $file_path,
            ]);
        }

        $newContent = $originalContent;
        $appliedEdits = [];

        // Apply each edit
        foreach ($edits as $i => $edit) {
            $search = $edit['search'];
            $replace = $edit['replace'];

            // Check if search string exists
            if (str_contains($newContent, $search)) {
                $occurrences = substr_count($newContent, $search);
                $newContent = str_replace($search, $replace, $newContent);
                $appliedEdits[] = [
                    'index' => $i,
                    'search' => $search,
                    'replace' => $replace,
                    'occurrences' => $occurrences,
                    'status' => 'applied',
                ];
            } else {
                $appliedEdits[] = [
                    'index' => $i,
                    'search' => $search,
                    'replace' => $replace,
                    'occurrences' => 0,
                    'status' => 'not_found',
                ];
            }
        }

        // Generate unified diff
        $originalLines = preg_split('/\r\n|\r|\n/', $originalContent);
        $newLines = preg_split('/\r\n|\r|\n/', $newContent);
        $diff = $this->generateUnifiedDiff($file_path, $originalLines, $newLines);

        // Calculate statistics
        $stats = $this->calculateStats($originalLines, $newLines);

        return json_encode([
            'status' => 'proposed',
            'operation' => 'edit',
            'file_path' => $file_path,
            'edits' => $appliedEdits,
            'total_edits' => count($edits),
            'successful_edits' => count(array_filter($appliedEdits, fn ($e) => $e['status'] === 'applied')),
            'stats' => $stats,
            'diff' => $diff,
            'original' => $originalContent,
            'new' => $newContent,
            'message' => count($appliedEdits) > 0
                ? "Proposed " . count($appliedEdits) . " edit(s) to file '{$file_path}'"
                : "No edits to apply to file '{$file_path}'",
        ]);
    }

    /**
     * Generate simplified unified diff for edits.
     *
     * @param string $file_path File path for the diff header
     * @param array $originalLines Original file lines
     * @param array $newLines New file lines
     * @return string Unified diff string
     */
    private function generateUnifiedDiff(string $file_path, array $originalLines, array $newLines): string
    {
        if ($originalLines === $newLines) {
            return "No changes detected.\n";
        }

        $diff = "--- a/{$file_path}\n";
        $diff .= "+++ b/{$file_path}\n";

        // Simple line-by-line comparison
        $maxLines = max(count($originalLines), count($newLines));
        $inHunk = false;
        $hunkStartOriginal = 1;
        $hunkStartNew = 1;
        $hunkLines = [];

        for ($i = 0; $i < $maxLines; $i++) {
            $originalLine = $originalLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';

            if ($originalLine !== $newLine) {
                if (!$inHunk) {
                    $inHunk = true;
                    $hunkStartOriginal = $i + 1;
                    $hunkStartNew = $i + 1;
                }

                if ($i < count($originalLines)) {
                    $hunkLines[] = ['type' => '-', 'content' => $originalLine];
                }
                if ($i < count($newLines)) {
                    $hunkLines[] = ['type' => '+', 'content' => $newLine];
                }
            } elseif ($inHunk) {
                // Close hunk on next non-change
                if (count($hunkLines) > 0) {
                    $originalCount = count(array_filter($hunkLines, fn ($l) => $l['type'] === ' ' || $l['type'] === '-'));
                    $newCount = count(array_filter($hunkLines, fn ($l) => $l['type'] === ' ' || $l['type'] === '+'));

                    $diff .= "@@ -{$hunkStartOriginal},{$originalCount} +{$hunkStartNew},{$newCount} @@\n";
                    foreach ($hunkLines as $line) {
                        $diff .= $line['type'] . $line['content'] . "\n";
                    }

                    $hunkLines = [];
                }
                $inHunk = false;
            }
        }

        // Close any remaining hunk
        if (count($hunkLines) > 0) {
            $originalCount = count(array_filter($hunkLines, fn ($l) => $l['type'] === ' ' || $l['type'] === '-'));
            $newCount = count(array_filter($hunkLines, fn ($l) => $l['type'] === ' ' || $l['type'] === '+'));

            $diff .= "@@ -{$hunkStartOriginal},{$originalCount} +{$hunkStartNew},{$newCount} @@\n";
            foreach ($hunkLines as $line) {
                $diff .= $line['type'] . $line['content'] . "\n";
            }
        }

        return $diff;
    }

    /**
     * Calculate change statistics.
     *
     * @param array $originalLines Original file lines
     * @param array $newLines New file lines
     * @return array Statistics
     */
    private function calculateStats(array $originalLines, array $newLines): array
    {
        $added = 0;
        $removed = 0;

        for ($i = 0; $i < max(count($originalLines), count($newLines)); $i++) {
            $originalLine = $originalLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';

            if ($originalLine !== $newLine) {
                if ($i < count($originalLines)) {
                    $removed++;
                }
                if ($i < count($newLines)) {
                    $added++;
                }
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => min($added, $removed),
        ];
    }
}
