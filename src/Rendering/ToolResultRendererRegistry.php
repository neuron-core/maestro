<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

/**
 * Registry for tool result renderers.
 *
 * Manages multiple renderers and selects the appropriate one
 * based on the tool name and result format.
 */
class ToolResultRendererRegistry
{
    /**
     * @var ToolResultRendererInterface[] Registered renderers
     */
    private array $renderers = [];

    /**
     * Register a renderer.
     *
     * @param ToolResultRendererInterface $renderer The renderer to register
     */
    public function register(ToolResultRendererInterface $renderer): self
    {
        $this->renderers[] = $renderer;
        return $this;
    }

    /**
     * Register multiple renderers.
     *
     * @param ToolResultRendererInterface[] $renderers The renderers to register
     */
    public function registerAll(array $renderers): self
    {
        $this->renderers = [...$this->renderers, ...$renderers];
        return $this;
    }

    /**
     * Find and render using the appropriate renderer.
     *
     * @param string $toolName The name of the tool
     * @param string $result The tool result
     * @return string The rendered output, or null if no renderer can handle it
     */
    public function render(string $toolName, string $result): ?string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($toolName, $result)) {
                return $renderer->render($toolName, $result);
            }
        }

        return null;
    }

    /**
     * Check if a renderer is available for the given tool and result.
     *
     * @param string $toolName The name of the tool
     * @param string $result The tool result
     * @return bool True if a renderer can handle this
     */
    public function canRender(string $toolName, string $result): bool
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->canRender($toolName, $result)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered renderers.
     *
     * @return ToolResultRendererInterface[]
     */
    public function getRenderers(): array
    {
        return $this->renderers;
    }

    /**
     * Create a registry with default renderers.
     */
    public static function withDefaults(): self
    {
        return (new self())
            ->register(new CodingToolResultRenderer());
    }
}
