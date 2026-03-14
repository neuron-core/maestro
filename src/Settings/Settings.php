<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Settings;

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
use function array_search;
use function array_values;
use function file_put_contents;
use function in_array;
use function json_encode;
use function dirname;

use const JSON_PRETTY_PRINT;

/**
 * Loads and manages agent configuration from .maestro/settings.json.
 */
class Settings implements SettingsInterface
{
    protected array $settings = [];
    protected readonly string $settingsPath;
    protected bool $fileExists = false;

    public function __construct(?string $settingsPath = null, protected ?ProviderFactoryInterface $providerFactory = new ProviderFactory())
    {
        $this->settingsPath = $settingsPath ?? getcwd() . '/.maestro/settings.json';
        $this->load();
    }

    public function dirPath(): string
    {
        return dirname($this->settingsPath);
    }

    public function filePath(): string
    {
        return $this->settingsPath;
    }

    /**
     * Save settings to the settings file.
     */
    protected function save(): void
    {
        file_put_contents(
            $this->settingsPath,
            json_encode($this->settings, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Load settings from the specified path or default location.
     */
    protected function load(): void
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
        return isset($this->settings['providers']) && isset($this->settings['default']);
    }

    /**
     * Get the configured AI provider.
     */
    public function provider(): AIProviderInterface
    {
        return $this->providerFactory->create($this->settings['default'], $this->settings['providers']);
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

    /**
     * Get the list of tools that are always allowed (no approval required).
     *
     * @return string[]
     */
    public function getAllowedTools(): array
    {
        return $this->settings['allowed_tools'] ?? [];
    }

    /**
     * Add a tool to the always allowed list.
     *
     * @param string $toolName The tool name to add
     * @return bool True if added, false if already exists
     */
    public function addAllowedTool(string $toolName): bool
    {
        if (!isset($this->settings['allowed_tools'])) {
            $this->settings['allowed_tools'] = [];
        }

        if (in_array($toolName, $this->settings['allowed_tools'], true)) {
            return false;
        }

        $this->settings['allowed_tools'][] = $toolName;
        $this->save();
        return true;
    }

    /**
     * Remove a tool from the always allowed list.
     *
     * @param string $toolName The tool name to remove
     * @return bool True if removed, false if not found
     */
    public function removeAllowedTool(string $toolName): bool
    {
        if (!isset($this->settings['allowed_tools'])) {
            return false;
        }

        $key = array_search($toolName, $this->settings['allowed_tools'], true);
        if ($key === false) {
            return false;
        }

        unset($this->settings['allowed_tools'][$key]);
        // Re-index the array
        $this->settings['allowed_tools'] = array_values($this->settings['allowed_tools']);
        $this->save();
        return true;
    }

    /**
     * Get the path to the agent instructions file.
     *
     * Checks for the 'context_file' option in settings, falls back to 'Agent.md'.
     * Returns null if the file doesn't exist.
     *
     * @return string|null The file path or null if not found
     */
    public function getAgentInstructionsFile(): ?string
    {
        $file = $this->get('context_file');

        // Fall back to Agent.md if not specified
        if ($file === null) {
            $file = 'Agents.md';
        }

        // Make the path relative to the settings directory
        $fullPath = $this->dirPath() . '/' . $file;

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Get all configured extensions.
     *
     * @return array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}>
     */
    public function getExtensions(): array
    {
        return $this->settings['extensions'] ?? [];
    }

    /**
     * Enable an extension by class name.
     *
     * @param string $className The fully qualified class name of the extension
     * @return bool True if enabled, false if extension not found or already enabled
     */
    public function enableExtension(string $className): bool
    {
        if (!isset($this->settings['extensions'])) {
            return false;
        }

        foreach ($this->settings['extensions'] as &$extension) {
            if (($extension['class'] ?? null) === $className) {
                if (($extension['enabled'] ?? true) === true) {
                    return false; // Already enabled
                }
                $extension['enabled'] = true;
                $this->save();
                return true;
            }
        }

        return false; // Extension not found
    }

    /**
     * Disable an extension by class name.
     *
     * @param string $className The fully qualified class name of the extension
     * @return bool True if disabled, false if extension not found or already disabled
     */
    public function disableExtension(string $className): bool
    {
        if (!isset($this->settings['extensions'])) {
            return false;
        }

        foreach ($this->settings['extensions'] as &$extension) {
            if (($extension['class'] ?? null) === $className) {
                if (($extension['enabled'] ?? true) === false) {
                    return false; // Already disabled
                }
                $extension['enabled'] = false;
                $this->save();
                return true;
            }
        }

        return false; // Extension not found
    }
}
