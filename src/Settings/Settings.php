<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\MCP\McpConnector;

/**
 * Loads and manages agent configuration from .neuron/settings.json.
 */
class Settings implements SettingsInterface
{
    private array $settings = [];
    private ProviderFactoryInterface $providerFactory;

    public function __construct(?string $settingsPath = null, ?ProviderFactoryInterface $providerFactory = null)
    {
        $this->providerFactory = $providerFactory ?? new ProviderFactory();
        $this->load($settingsPath);
    }

    /**
     * Load settings from the specified path or default location.
     */
    private function load(?string $path): void
    {
        $settingsPath = $path ?? getcwd() . '/.neuron/settings.json';

        if (file_exists($settingsPath)) {
            $content = file_get_contents($settingsPath);
            $this->settings = json_decode($content, true) ?? [];
        }
    }

    /**
     * Get the configured AI provider.
     */
    public function provider(): AIProviderInterface
    {
        return $this->providerFactory->create($this->settings);
    }

    /**
     * Get all configured MCP connectors.
     *
     * @return array<string, McpConnector>
     */
    public function mcpServers(): array
    {
        $connectors = [];

        if (!isset($this->settings['mcp_servers']) || !is_array($this->settings['mcp_servers'])) {
            return $connectors;
        }

        foreach ($this->settings['mcp_servers'] as $name => $config) {
            try {
                $connectors[$name] = McpConnector::make($config);
            } catch (\Throwable $e) {
                error_log(sprintf('Failed to create MCP connector "%s": %s', $name, $e->getMessage()));
            }
        }

        return $connectors;
    }

    /**
     * Get the settings array.
     */
    public function all(): array
    {
        return $this->settings;
    }

    /**
     * Get a specific setting value using dot notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!str_contains($key, '.')) {
            return $this->settings[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $value = $this->settings;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set the provider factory (useful for testing or custom implementations).
     */
    public function setProviderFactory(ProviderFactoryInterface $factory): self
    {
        $this->providerFactory = $factory;
        return $this;
    }
}
