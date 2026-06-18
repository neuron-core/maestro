<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension;

use NeuronAI\Tools\ToolInterface;
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Settings\Settings;

/**
 * API passed to extensions for registering components.
 *
 * v2 removed the slot/widget/theme/renderer customization surface. Tool result
 * presentation is handled by the TUI's ToolView layer (built-in for the
 * filesystem tools); an extension hook for custom tool views may return later.
 */
class ExtensionApi
{
    public function __construct(
        protected readonly ToolRegistry $tools,
        protected readonly CommandRegistry $commands,
        protected readonly EventRegistry $events,
        protected readonly MemoryRegistry $memories,
        protected readonly Settings $settings,
    ) {
    }

    /**
     * Register a tool that the AI agent can use.
     */
    public function registerTool(ToolInterface $tool): void
    {
        $this->tools->register($tool);
    }

    /**
     * Register an inline command available in the session.
     */
    public function registerCommand(InlineCommand $command): void
    {
        $this->commands->register($command);
    }

    /**
     * Register a callback for a specific event class.
     */
    public function on(string $event, callable $handler): void
    {
        $this->events->register($event, $handler);
    }

    /**
     * Register a memory file injected into the agent's system prompt.
     *
     * @param string $key      Unique identifier (e.g. "extension_name.memory").
     * @param string $filePath Absolute path to the memory file.
     */
    public function registerMemory(string $key, string $filePath): void
    {
        $this->memories->register($key, $filePath);
    }

    public function tools(): ToolRegistry
    {
        return $this->tools;
    }

    public function commands(): CommandRegistry
    {
        return $this->commands;
    }

    public function events(): EventRegistry
    {
        return $this->events;
    }

    public function memories(): MemoryRegistry
    {
        return $this->memories;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}
