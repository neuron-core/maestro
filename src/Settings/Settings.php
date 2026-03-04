<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\MCP\McpConnector;
use Throwable;

use function error_log;
use function explode;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function is_array;
use function json_decode;
use function sprintf;
use function str_contains;

/**
 * Loads and manages agent configuration from .neuron/settings.json.
 */
class Settings implements SettingsInterface
{
    private array $settings = [];
    private readonly string $settingsPath;
    private bool $fileExists = false;

    public function __construct(?string $settingsPath = null, private ?ProviderFactoryInterface $providerFactory = new ProviderFactory())
    {
        $this->settingsPath = $settingsPath ?? getcwd() . '/.neuron/settings.json';
        $this->load();
    }

    /**
     * Load settings from the specified path or default location.
     */
    private function load(): void
    {
        $this->fileExists = file_exists($this->settingsPath);

        if ($this->fileExists) {
            $content = file_get_contents($this->settingsPath);
            $this->settings = json_decode($content, true) ?? [];
        }
    }

    /**
     * Check if the settings file exists.
     */
    public function fileExists(): bool
    {
        return $this->fileExists;
    }

    /**
     * Get the settings file path.
     */
    public function getSettingsPath(): string
    {
        return $this->settingsPath;
    }

    /**
     * Check if the settings have valid provider configuration.
     */
    public function hasValidProvider(): bool
    {
        return isset($this->settings['provider']) && isset($this->settings['provider']['type']);
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
            } catch (Throwable $e) {
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
