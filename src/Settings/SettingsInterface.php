<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Settings;

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
     * Get the list of tools that are always allowed (no approval required).
     *
     * @return string[]
     */
    public function getAllowedTools(): array;

    /**
     * Add a tool to the always allowed list.
     *
     * @param string $toolName The tool name to add
     * @return bool True if added, false if already exists
     */
    public function addAllowedTool(string $toolName): bool;

    /**
     * Remove a tool from the always allowed list.
     *
     * @param string $toolName The tool name to remove
     * @return bool True if removed, false if not found
     */
    public function removeAllowedTool(string $toolName): bool;

    /**
     * Get the path to the context file containing project-specific instructions.
     *
     * Checks for the 'context_file' option in settings, falls back to 'Agents.md'.
     * Returns null if the file doesn't exist.
     *
     * @return string|null The file path or null if not found
     */
    public function getAgentInstructionsFile(): ?string;
}
