<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

/**
 * One line of a unified diff.
 *
 * - 'add': present in the new content only
 * - 'del': present in the old content only
 * - 'context': unchanged
 */
final class DiffLine
{
    public function __construct(
        public readonly string $type,
        public readonly string $text,
    ) {
    }
}
