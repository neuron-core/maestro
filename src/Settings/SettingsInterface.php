<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\MCP\McpConnector;

/**
 * Interface for loading and accessing agent settings.
 */
interface SettingsInterface
{
    public function dirPath(): string;

    public function filePath(): string;

    /**
     * Get the configured AI provider.
     */
    public function provider(): AIProviderInterface;

    /**
     * Get all configured MCP connectors.
     *
     * @return array<string, McpConnector>
     */
    public function mcpServers(): array;

    /**
     * Get the settings array.
     */
    public function all(): array;

    /**
     * Get a specific setting value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Check if the settings file exists.
     */
    public function fileExists(): bool;

    /**
     * Check if the settings have valid provider configuration.
     */
    public function hasValidProvider(): bool;

    /**
     * Get the settings file path.
     */
    public function getSettingsPath(): string;

    /**
     * Get the path to the context file containing project-specific instructions.
     *
     * Checks for the 'context_file' option in settings, falls back to 'Agents.md'.
     * Returns null if the file doesn't exist.
     *
     * @return string|null The file path or null if not found
     */
    public function getAgentInstructionsFile(): ?string;

    /**
     * Get all configured extensions.
     *
     * @return array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}>
     */
    public function getExtensions(): array;

    /**
     * Enable an extension by class name.
     *
     * @param string $className The fully qualified class name of the extension
     * @return bool True if enabled, false if extension not found or already enabled
     */
    public function enableExtension(string $className): bool;

    /**
     * Disable an extension by class name.
     *
     * @param string $className The fully qualified class name of the extension
     * @return bool True if disabled, false if extension not found or already disabled
     */
    public function disableExtension(string $className): bool;

    /**
     * Get the context window size for the current provider.
     *
     * @return int The context window size in tokens (default: 100000)
     */
    public function getContextWindow(): int;
}
