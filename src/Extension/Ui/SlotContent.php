<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

use function array_column;
use function usort;

/**
 * Represents content to be rendered in a UI slot.
 */
class SlotContent
{
    /**
     * @param array<int, array{content: string, priority: int}> $items Content items with priority
     */
    public function __construct(
        protected readonly string $slotName,
        protected array $items = [],
    ) {
    }

    /**
     * Add content to this slot.
     *
     * @param string $content The content to add
     * @param int $priority Higher number = higher priority (default: 500)
     */
    public function add(string $content, int $priority = 500): void
    {
        $this->items[] = [
            'content' => $content,
            'priority' => $priority,
        ];
    }

    /**
     * Clear all content from this slot.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * Get all items sorted by priority.
     *
     * @return array<int, string>
     */
    public function sorted(): array
    {
        $sorted = $this->items;
        usort($sorted, fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
        return array_column($sorted, 'content');
    }

    /**
     * Check if slot has content.
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function slotName(): string
    {
        return $this->slotName;
    }
}
