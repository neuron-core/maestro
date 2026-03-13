<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

use function array_keys;

/**
 * Registry for managing UI slots.
 */
class SlotRegistry
{
    /** @var array<string, SlotContent> */
    protected array $slots = [];

    /**
     * Get or create a slot by type.
     */
    public function slot(SlotType $slot): SlotContent
    {
        return $this->slots[$slot->value] ??= new SlotContent($slot->value);
    }

    /**
     * Clear a specific slot.
     */
    public function clear(SlotType $slot): void
    {
        $this->slot($slot)->clear();
    }

    /**
     * Clear all slots.
     */
    public function clearAll(): void
    {
        foreach ($this->slots as $slot) {
            $slot->clear();
        }
    }

    /**
     * Get all slot names.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->slots);
    }

    /**
     * Get all slots.
     *
     * @return array<string, SlotContent>
     */
    public function all(): array
    {
        return $this->slots;
    }
}
