<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Tui\ToolView;

use NeuronCore\Maestro\Tui\ToolView\DiffLine;
use NeuronCore\Maestro\Tui\ToolView\LineDiffer;
use PHPUnit\Framework\TestCase;

use function array_map;

class LineDifferTest extends TestCase
{
    public function test_diff_emits_context_add_and_del_in_order(): void
    {
        $lines = LineDiffer::diff("a\nb\nc", "a\nx\nc");

        $pairs = array_map(static fn (DiffLine $l): string => $l->type . ':' . $l->text, $lines);

        self::assertSame(
            ['context:a', 'del:b', 'add:x', 'context:c'],
            $pairs,
        );
    }

    public function test_empty_old_marks_everything_as_add(): void
    {
        $lines = LineDiffer::diff('', "x\ny");

        self::assertSame(['add', 'add'], array_map(static fn (DiffLine $l): string => $l->type, $lines));
    }

    public function test_identical_inputs_are_all_context(): void
    {
        $lines = LineDiffer::diff("a\nb", "a\nb");

        self::assertSame(['context', 'context'], array_map(static fn (DiffLine $l): string => $l->type, $lines));
    }
}
