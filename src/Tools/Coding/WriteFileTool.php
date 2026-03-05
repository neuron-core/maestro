<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tools\Coding;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

use function file_exists;
use function file_get_contents;
use function is_writable;
use function mb_strlen;
use function preg_split;
use function rtrim;
use function array_fill;
use function count;
use function dirname;
use function in_array;
use function json_encode;
use function max;

/**
 * Write or overwrite a file with new content.
 * Returns a structured change description with diff for CLI rendering.
 */
class WriteFileTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            name: 'write_file',
            description: 'Write content to a file. Creates a new file or overwrites an existing one. Returns a structured diff of changes for review before applying.',
        );
    }

    protected function properties(): array
    {
        return [
            ToolProperty::make(
                name: 'file_path',
                type: PropertyType::STRING,
                description: 'Path to the file to write.',
                required: true,
            ),
            ToolProperty::make(
                name: 'content',
                type: PropertyType::STRING,
                description: 'The content to write to the file.',
                required: true,
            ),
        ];
    }

    /**
     * @param string $file_path Path to the file
     * @param string $content New file content
     * @return string JSON-encoded structured change description
     */
    public function __invoke(string $file_path, string $content): string
    {
        // Check directory exists and is writable
        $directory = dirname($file_path);
        if (!is_writable($directory)) {
            return json_encode([
                'status' => 'error',
                'message' => "Directory '{$directory}' is not writable.",
                'operation' => 'write',
                'file_path' => $file_path,
            ]);
        }

        $originalContent = '';
        $originalLines = [];
        $isExisting = file_exists($file_path);

        if ($isExisting) {
            if (!is_writable($file_path)) {
                return json_encode([
                    'status' => 'error',
                    'message' => "File '{$file_path}' is not writable.",
                    'operation' => 'write',
                    'file_path' => $file_path,
                ]);
            }

            $originalContent = file_get_contents($file_path);
            if ($originalContent === false) {
                $originalContent = '';
            }
            $originalLines = preg_split('/\r\n|\r|\n/', rtrim($originalContent, "\r\n"));
            if ($originalLines === false) {
                $originalLines = [$originalContent];
            }
        }

        // Generate unified diff
        $contentTrimmed = rtrim($content, "\r\n");
        $newLines = preg_split('/\r\n|\r|\n/', $contentTrimmed);
        if ($newLines === false) {
            $newLines = [$contentTrimmed];
        }

        $diff = $this->generateUnifiedDiff(
            $file_path,
            $originalLines,
            $newLines
        );

        // Calculate statistics
        $stats = $this->calculateStats($originalLines, $newLines);

        return json_encode([
            'status' => 'proposed',
            'operation' => 'write',
            'file_path' => $file_path,
            'is_new' => !$isExisting,
            'original_size' => mb_strlen($originalContent),
            'new_size' => mb_strlen($content),
            'stats' => $stats,
            'diff' => $diff,
            'original' => $originalContent,
            'new' => $content,
            'message' => $isExisting
                ? "Proposed overwrite of file '{$file_path}'"
                : "Proposed new file '{$file_path}'",
        ]);
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

        // Compute longest common subsequence
        $lcs = $this->longestCommonSubsequence($originalLines, $newLines);

        // Build hunks
        $hunks = $this->buildHunks($originalLines, $newLines, $lcs);

        // Format hunks
        foreach ($hunks as $hunk) {
            $diff .= "@@ -{$hunk['original_start']},{$hunk['original_count']} +{$hunk['new_start']},{$hunk['new_count']} @@\n";

            foreach ($hunk['lines'] as $line) {
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
     * @return array Statistics with 'added', 'removed', 'changed' counts
     */
    private function calculateStats(array $originalLines, array $newLines): array
    {
        $added = 0;
        $removed = 0;

        $lcs = $this->longestCommonSubsequence($originalLines, $newLines);
        $hunks = $this->buildHunks($originalLines, $newLines, $lcs);

        foreach ($hunks as $hunk) {
            foreach ($hunk['lines'] as $line) {
                if ($line['type'] === '+') {
                    $added++;
                } elseif ($line['type'] === '-') {
                    $removed++;
                }
            }
        }

        // Changed lines are counted when a context line has both removal and addition nearby
        $changed = 0;
        $counter = count($hunks);
        for ($i = 0; $i < $counter; $i++) {
            $lines = $hunks[$i]['lines'];
            for ($j = 1; $j < count($lines) - 1; $j++) {
                if ($lines[$j]['type'] === '-' && $lines[$j + 1]['type'] === '+') {
                    $changed++;
                    $j++;
                }
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * Compute the longest common subsequence between two arrays.
     *
     * @param array $a First array
     * @param array $b Second array
     * @return array LCS indices map
     */
    private function longestCommonSubsequence(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);

        // Dynamic programming table
        $dp = [];
        for ($i = 0; $i <= $m; $i++) {
            $dp[$i] = array_fill(0, $n + 1, 0);
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $a[$i - 1] === $b[$j - 1] ? $dp[$i - 1][$j - 1] + 1 : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }

        // Backtrack to find the LCS
        $lcs = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $lcs[$i - 1] = $j - 1;
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] > $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $lcs;
    }

    /**
     * Build diff hunks from LCS.
     *
     * @param array $originalLines Original file lines
     * @param array $newLines New file lines
     * @param array $lcs LCS indices map
     * @return array Array of hunk data
     */
    private function buildHunks(array $originalLines, array $newLines, array $lcs): array
    {
        $hunks = [];
        $hunkLines = [];

        $originalIndex = 0;
        $newIndex = 0;
        $originalCount = count($originalLines);
        $newCount = count($newLines);

        while ($originalIndex < $originalCount || $newIndex < $newCount) {
            $originalHasMatch = isset($lcs[$originalIndex]);
            $newHasMatch = in_array($newIndex, $lcs, true);

            if ($originalHasMatch && $newHasMatch && $lcs[$originalIndex] === $newIndex) {
                // Lines match - add to context
                $hunkLines[] = [
                    'type' => ' ',
                    'content' => $originalLines[$originalIndex],
                ];
                $originalIndex++;
                $newIndex++;

                // End hunk if we have enough context after changes
                if (count($hunkLines) > 6) {
                    $this->finalizeHunk($hunks, $hunkLines);
                }
            } else {
                // Lines differ - add changes
                if ($originalIndex < $originalCount && (!$newHasMatch || !$originalHasMatch || $lcs[$originalIndex] > $newIndex)) {
                    $hunkLines[] = [
                        'type' => '-',
                        'content' => $originalLines[$originalIndex],
                    ];
                    $originalIndex++;
                }

                if ($newIndex < $newCount && (!$originalHasMatch || !$newHasMatch || $lcs[$originalIndex] !== $newIndex)) {
                    $hunkLines[] = [
                        'type' => '+',
                        'content' => $newLines[$newIndex],
                    ];
                    $newIndex++;
                }
            }
        }

        // Finalize any remaining hunk
        if (count($hunkLines) > 0) {
            $this->finalizeHunk($hunks, $hunkLines);
        }

        return $hunks;
    }

    /**
     * Finalize a hunk and add it to the hunks array.
     *
     * @param array $hunks Hunks array to add to
     * @param array $hunkLines Hunk lines array
     */
    private function finalizeHunk(array &$hunks, array &$hunkLines): void
    {
        if (count($hunkLines) === 0) {
            return;
        }

        // Calculate hunk metadata
        $originalStart = 1;
        $originalCount = 0;
        $newStart = 1;
        $newCount = 0;

        foreach ($hunkLines as $line) {
            if ($line['type'] === ' ' || $line['type'] === '-') {
                if ($originalCount === 0) {
                    $originalStart = 1; // Will be adjusted after
                }
                $originalCount++;
            }
            if ($line['type'] === ' ' || $line['type'] === '+') {
                if ($newCount === 0) {
                    $newStart = 1; // Will be adjusted after
                }
                $newCount++;
            }
        }

        // Adjust start lines based on previous hunks
        if ($hunks !== []) {
            $lastHunk = $hunks[count($hunks) - 1];
            $originalStart = $lastHunk['original_start'] + $lastHunk['original_count'];
            $newStart = $lastHunk['new_start'] + $lastHunk['new_count'];
        }

        // Add hunk
        $hunks[] = [
            'original_start' => $originalStart,
            'original_count' => $originalCount,
            'new_start' => $newStart,
            'new_count' => $newCount,
            'lines' => $hunkLines,
        ];

        // Clear hunk lines
        $hunkLines = [];
    }
}
