<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tools\Coding;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function is_readable;
use function is_writable;
use function json_encode;
use function preg_match;
use function sprintf;
use function array_filter;
use function implode;
use function max;
use function min;
use function preg_split;
use function str_starts_with;
use function substr;

/**
 * Apply a unified diff patch to a file.
 * Returns a structured change description for CLI rendering.
 */
class PatchFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'patch_file',
            description: 'Apply a unified diff patch to a file. The patch follows standard unified diff format. Returns a structured description of changes for review before applying.',
        );
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Path to the file to patch.',
                required: true,
            ),
            ToolProperty::make(
                name: 'patch',
                type: PropertyType::STRING,
                description: 'Unified diff patch to apply.',
                required: true,
            ),
        ];
    }

    /**
     * @param string $file_path Path to the file
     * @param string $patch Unified diff patch
     * @return string JSON-encoded structured change description
     */
    public function __invoke(string $file_path, string $patch): string
    {
        // Validate file exists
        if (!file_exists($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' does not exist.",
                'operation' => 'patch',
                'file_path' => $file_path,
            ]);
        }

        if (!is_readable($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' is not readable.",
                'operation' => 'patch',
                'file_path' => $file_path,
            ]);
        }

        if (!is_writable($file_path)) {
            return json_encode([
                'status' => 'error',
                'message' => "File '{$file_path}' is not writable.",
                'operation' => 'patch',
                'file_path' => $file_path,
            ]);
        }

        $originalContent = file_get_contents($file_path);
        if ($originalContent === false) {
            return json_encode([
                'status' => 'error',
                'message' => "Unable to read file '{$file_path}'.",
                'operation' => 'patch',
                'file_path' => $file_path,
            ]);
        }

        $originalLines = preg_split('/\r\n|\r|\n/', $originalContent);
        if ($originalLines === false) {
            $originalLines = [$originalContent];
        }

        // Parse and apply the patch
        $result = $this->applyPatch($originalLines, $patch);

        if ($result['status'] === 'error') {
            return json_encode([
                'status' => 'error',
                'message' => $result['message'],
                'operation' => 'patch',
                'file_path' => $file_path,
                'patch' => $patch,
            ]);
        }

        // Generate unified diff of applied changes
        $diff = $this->generateUnifiedDiff($file_path, $originalLines, $result['lines']);

        // Calculate statistics
        $stats = $this->calculateStats($originalLines, $result['lines']);

        $newContent = implode("\n", $result['lines']);

        return json_encode([
            'status' => 'proposed',
            'operation' => 'patch',
            'file_path' => $file_path,
            'hunks_applied' => $result['hunks_applied'],
            'stats' => $stats,
            'diff' => $diff,
            'original' => $originalContent,
            'new' => $newContent,
            'message' => "Proposed patch with {$result['hunks_applied']} hunk(s) to file '{$file_path}'",
        ]);
    }

    /**
     * Parse and apply a unified diff patch.
     *
     * @param array $originalLines Original file lines
     * @param string $patch Unified diff patch
     * @return array Result with 'status', 'message', 'lines', and 'hunks_applied'
     */
    private function applyPatch(array $originalLines, string $patch): array
    {
        $lines = $originalLines;
        $patchLines = explode("\n", $patch);
        $hunksApplied = 0;

        // Parse patch
        $hunks = $this->parseHunks($patchLines);

        if (count($hunks) === 0) {
            return [
                'status' => 'error',
                'message' => 'No valid hunks found in patch.',
            ];
        }

        // Apply each hunk
        foreach ($hunks as $hunk) {
            $result = $this->applyHunk($lines, $hunk);
            if ($result['status'] === 'error') {
                return $result;
            }
            $lines = $result['lines'];
            $hunksApplied++;
        }

        return [
            'status' => 'success',
            'lines' => $lines,
            'hunks_applied' => $hunksApplied,
        ];
    }

    /**
     * Parse hunks from a unified diff.
     *
     * @param array $patchLines Lines from the patch
     * @return array Array of hunk data
     */
    private function parseHunks(array $patchLines): array
    {
        $hunks = [];
        $currentHunk = null;

        foreach ($patchLines as $line) {
            // Match hunk header: @@ -original_start,original_count +new_start,new_count @@
            if (preg_match('/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/', (string) $line, $matches)) {
                if ($currentHunk !== null) {
                    $hunks[] = $currentHunk;
                }

                $currentHunk = [
                    'original_start' => (int) $matches[1],
                    'original_count' => isset($matches[2]) ? (int) $matches[2] : 1,
                    'new_start' => (int) $matches[3],
                    'new_count' => isset($matches[4]) ? (int) $matches[4] : 1,
                    'lines' => [],
                ];
            } elseif ($currentHunk !== null && $line !== '') {
                // Parse hunk line
                $type = ' ';
                $content = $line;

                if (str_starts_with((string) $line, '+')) {
                    $type = '+';
                    $content = substr((string) $line, 1);
                } elseif (str_starts_with((string) $line, '-')) {
                    $type = '-';
                    $content = substr((string) $line, 1);
                } elseif (str_starts_with((string) $line, ' ')) {
                    $content = substr((string) $line, 1);
                }

                $currentHunk['lines'][] = [
                    'type' => $type,
                    'content' => $content,
                ];
            }
        }

        if ($currentHunk !== null) {
            $hunks[] = $currentHunk;
        }

        return $hunks;
    }

    /**
     * Apply a single hunk to the file lines.
     *
     * @param array $lines Current file lines
     * @param array $hunk Hunk data
     * @return array Result with 'status', 'message', and 'lines'
     */
    private function applyHunk(array $lines, array $hunk): array
    {
        $newLines = [];
        $lineIndex = max(0, $hunk['original_start'] - 1);

        // Copy lines before the hunk
        for ($i = 0; $i < $lineIndex; $i++) {
            if ($i < count($lines)) {
                $newLines[] = $lines[$i];
            }
        }

        // Process hunk lines
        foreach ($hunk['lines'] as $hunkLine) {
            if ($hunkLine['type'] === '-') {
                // Skip original line
                $lineIndex++;
            } elseif ($hunkLine['type'] === '+') {
                // Add new line
                $newLines[] = $hunkLine['content'];
            } elseif ($lineIndex < count($lines) && $lines[$lineIndex] === $hunkLine['content']) {
                // Context line - verify it matches
                $newLines[] = $lines[$lineIndex];
                $lineIndex++;
            } else {
                // Context mismatch
                return [
                    'status' => 'error',
                    'message' => sprintf(
                        'Context mismatch at line %d. Expected "%s", got "%s".',
                        $lineIndex + 1,
                        $hunkLine['content'],
                        $lines[$lineIndex] ?? '<end of file>'
                    ),
                ];
            }
        }
        $counter = count($lines);

        // Copy remaining lines
        for ($i = $lineIndex; $i < $counter; $i++) {
            $newLines[] = $lines[$i];
        }

        return [
            'status' => 'success',
            'lines' => $newLines,
        ];
    }

    /**
     * Generate unified diff format.
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
                // Close hunk
                if (count($hunkLines) > 0) {
                    $originalCount = count(array_filter($hunkLines, fn (array $l): bool => $l['type'] === ' ' || $l['type'] === '-'));
                    $newCount = count(array_filter($hunkLines, fn (array $l): bool => $l['type'] === ' ' || $l['type'] === '+'));

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
            $originalCount = count(array_filter($hunkLines, fn (array $l): bool => $l['type'] === ' ' || $l['type'] === '-'));
            $newCount = count(array_filter($hunkLines, fn (array $l): bool => $l['type'] === ' ' || $l['type'] === '+'));

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
