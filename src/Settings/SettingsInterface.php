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
}
