<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

/**
 * Builder API for extensions to customize UI.
 */
class UiBuilder
{
    protected ?UiEngine $engine = null;

    public function __construct(
        protected ThemeInterface $theme,
        protected readonly SlotRegistry $slots,
        protected readonly WidgetRegistry $widgets,
    ) {
    }

    /**
     * Bind this builder to a UiEngine so theme changes are reflected globally.
     * Called by UiEngine::createBuilder().
     */
    public function setEngine(UiEngine $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * Register a theme to be used for UI rendering.
     */
    public function registerTheme(ThemeInterface $theme): void
    {
        if ($this->engine instanceof UiEngine) {
            $this->engine->setTheme($theme);
        }
        $this->theme = $theme;
    }

    /**
     * Add content to a UI slot.
     */
    public function addToSlot(SlotType $slot, string $content, int $priority = 500): void
    {
        $this->slots->slot($slot)->add($content, $priority);
    }

    /**
     * Clear a specific UI slot.
     */
    public function clearSlot(SlotType $slot): void
    {
        $this->slots->clear($slot);
    }

    /**
     * Register a custom widget for rendering specific content types.
     */
    public function registerWidget(WidgetInterface $widget): void
    {
        $this->widgets->register($widget);
    }

    /**
     * Get the current theme (from engine if bound, otherwise local).
     */
    public function theme(): ThemeInterface
    {
        return $this->engine instanceof UiEngine ? $this->engine->theme() : $this->theme;
    }

    /**
     * Get the slot registry.
     */
    public function slots(): SlotRegistry
    {
        return $this->slots;
    }

    /**
     * Get the widget registry.
     */
    public function widgets(): WidgetRegistry
    {
        return $this->widgets;
    }
}
