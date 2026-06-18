<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

use function array_fill;
use function array_pop;
use function count;
use function end;
use function explode;
use function max;

/**
 * Computes a line-level unified diff between two strings using LCS.
 *
 * No external dependency; adequate for file edits (small inputs). Returns a
 * sequence of {@see DiffLine} values that {@see \NeuronCore\Maestro\Tui\Widget\DiffView} renders.
 */
final class LineDiffer
{
    /**
     * @return list<DiffLine>
     */
    public static function diff(string $old, string $new): array
    {
        $a = self::lines($old);
        $b = self::lines($new);
        $m = count($a);
        $n = count($b);

        /** @var array<int, array<int, int>> $dp LCS-length table */
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = $m - 1; $i >= 0; --$i) {
            for ($j = $n - 1; $j >= 0; --$j) {
                $dp[$i][$j] = $a[$i] === $b[$j]
                    ? $dp[$i + 1][$j + 1] + 1
                    : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $result = [];
        $i = 0;
        $j = 0;
        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $result[] = new DiffLine('context', $a[$i]);
                ++$i;
                ++$j;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $result[] = new DiffLine('del', $a[$i]);
                ++$i;
            } else {
                $result[] = new DiffLine('add', $b[$j]);
                ++$j;
            }
        }
        while ($i < $m) {
            $result[] = new DiffLine('del', $a[$i]);
            ++$i;
        }
        while ($j < $n) {
            $result[] = new DiffLine('add', $b[$j]);
            ++$j;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $lines = explode("\n", $text);
        // Drop the single empty element produced by a trailing newline.
        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
