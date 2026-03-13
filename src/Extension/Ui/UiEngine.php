<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

use Symfony\Component\Console\Output\OutputInterface;

use function implode;

/**
 * Main UI engine for rendering CLI output with themes and slots.
 */
class UiEngine
{
    public function __construct(
        protected ThemeInterface $theme,
        protected readonly SlotRegistry $slots,
        protected readonly WidgetRegistry $widgets,
    ) {
        // Initialize default slots
        $this->slots->slot(SlotType::HEADER);
        $this->slots->slot(SlotType::STATUS_BAR);
        $this->slots->slot(SlotType::CONTENT);
        $this->slots->slot(SlotType::FOOTER);
    }

    /**
     * Render a complete output with all active slots.
     */
    public function render(OutputInterface $output): void
    {
        $this->renderHeader($output);
        $this->renderContent($output);
        $this->renderFooter($output);
    }

    /**
     * Render header slot.
     */
    public function renderHeader(OutputInterface $output): void
    {
        $content = $this->slots->slot(SlotType::HEADER);
        if ($content->isEmpty()) {
            return;
        }

        foreach ($content->sorted() as $item) {
            $output->writeln($item);
        }
        $output->writeln('');
    }

    /**
     * Render main content slot.
     */
    public function renderContent(OutputInterface $output): void
    {
        $content = $this->slots->slot(SlotType::CONTENT);
        foreach ($content->sorted() as $line) {
            $output->writeln($line);
        }
    }

    /**
     * Render footer slot.
     */
    public function renderFooter(OutputInterface $output): void
    {
        $content = $this->slots->slot(SlotType::FOOTER);
        if ($content->isEmpty()) {
            return;
        }

        $output->writeln('');
        foreach ($content->sorted() as $item) {
            $output->writeln($item);
        }
    }

    /**
     * Render status bar (typically inline with content).
     */
    public function renderStatus(OutputInterface $output): void
    {
        $content = $this->slots->slot(SlotType::STATUS_BAR);
        if ($content->isEmpty()) {
            return;
        }

        // Status bar is rendered inline (no newline)
        $output->write(implode('', $content->sorted()));
    }

    /**
     * Get the current theme.
     */
    public function theme(): ThemeInterface
    {
        return $this->theme;
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

    /**
     * Create a UiBuilder for extensions, bound to this engine.
     */
    public function createBuilder(): UiBuilder
    {
        $builder = new UiBuilder(
            $this->theme,
            $this->slots,
            $this->widgets,
        );
        $builder->setEngine($this);
        return $builder;
    }

    /**
     * Set the current theme.
     */
    public function setTheme(ThemeInterface $theme): void
    {
        $this->theme = $theme;
    }

    /**
     * Clear all slots.
     */
    public function clearSlots(): void
    {
        $this->slots->clearAll();
    }
}
