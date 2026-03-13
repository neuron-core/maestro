<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

/**
 * Interface for UI widgets that extensions can register.
 */
interface WidgetInterface
{
    /**
     * Get widget name.
     */
    public function name(): string;

    /**
     * Get the content type this widget handles.
     */
    public function contentType(): ContentType;

    /**
     * Render the widget with given data.
     *
     * @param array<string, mixed> $data The data to render
     * @param UiBuilder $ui The UI builder for formatting
     * @return string The rendered output
     */
    public function render(array $data, UiBuilder $ui): string;
}
