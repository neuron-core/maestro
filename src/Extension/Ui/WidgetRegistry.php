<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

use function array_filter;
use function array_keys;
use function array_values;

/**
 * Registry for UI widgets.
 */
class WidgetRegistry
{
    /** @var array<string, WidgetInterface> */
    protected array $widgets = [];

    /**
     * Register a widget.
     */
    public function register(WidgetInterface $widget): void
    {
        $this->widgets[$widget->name()] = $widget;
    }

    /**
     * Get a widget by name.
     */
    public function get(string $name): ?WidgetInterface
    {
        return $this->widgets[$name] ?? null;
    }

    /**
     * Check if a widget is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->widgets[$name]);
    }

    /**
     * Get all widgets for a content type.
     *
     * @return array<int, WidgetInterface>
     */
    public function forType(ContentType $contentType): array
    {
        return array_values(
            array_filter(
                $this->widgets,
                fn (WidgetInterface $w): bool => $w->contentType() === $contentType,
            ),
        );
    }

    /**
     * Get all widgets.
     *
     * @return array<string, WidgetInterface>
     */
    public function all(): array
    {
        return $this->widgets;
    }

    /**
     * Get all widget names.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->widgets);
    }
}
