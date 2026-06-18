<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

/**
 * Maps tool names to their {@see ToolView}. Falls back to a generic view for
 * tools without a specific renderer.
 */
final class ToolViewRegistry
{
    private ToolView $default;

    /** @var array<string, ToolView> */
    private array $views = [];

    public function __construct(?ToolView $default = null)
    {
        $this->default = $default ?? new GenericToolView();
    }

    public function register(string $toolName, ToolView $view): void
    {
        $this->views[$toolName] = $view;
    }

    public function viewFor(string $toolName): ToolView
    {
        return $this->views[$toolName] ?? $this->default;
    }

    /**
     * Registry pre-loaded with views for the built-in filesystem tools.
     */
    public static function defaultRegistry(): self
    {
        $registry = new self();
        $filesystem = new FileSystemToolView();
        foreach (['read_file', 'parse_file', 'edit_file', 'write_file'] as $toolName) {
            $registry->register($toolName, $filesystem);
        }

        return $registry;
    }
}
