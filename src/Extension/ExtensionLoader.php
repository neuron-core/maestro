<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension;

use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\UiEngine;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use NeuronCore\Maestro\Settings\Settings;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

use function class_exists;
use function file_exists;
use function is_array;
use function sprintf;
use function array_key_first;
use function array_values;
use function is_string;

/**
 * Loads and initializes extensions from configuration.
 */
class ExtensionLoader
{
    protected const MANIFEST_PATH = '.maestro/manifest.php';

    /** @var array<ExtensionDescriptor> */
    protected array $descriptors = [];

    public function __construct(
        protected readonly ToolRegistry $tools,
        protected readonly CommandRegistry $commands,
        protected readonly RendererRegistry $renderers,
        protected readonly EventRegistry $events,
        protected readonly MemoryRegistry $memories,
        protected readonly Settings $settings,
        protected ?UiEngine $uiEngine = null,
        protected readonly string $manifestPath = self::MANIFEST_PATH,
    ) {
    }

    /**
     * Register core (built-in) extensions directly, without a descriptor.
     */
    public function register(ExtensionInterface ...$extensions): void
    {
        foreach ($extensions as $extension) {
            $this->initializeExtension($extension);
        }
    }

    /**
     * Load extensions from the manifest and settings array.
     *
     * Extensions are loaded in the following order:
     * 1. Manifest extensions (auto-discovered from composer packages)
     * 2. Settings extensions (manually configured in settings.json)
     *
     * Settings can override manifest extension enabled status and config using
     * the class name as the key.
     *
     * @return array<ExtensionDescriptor>
     */
    public function load(array $extensions): array
    {
        $manifest = $this->loadManifest();

        $extensions = $this->mergeExtensions($manifest, $extensions);

        foreach ($extensions as $descriptor) {
            // Only include extensions where the class exists
            if (!class_exists($descriptor->className)) {
                continue;
            }

            if ($descriptor->enabled) {
                $this->initialize($descriptor);
            }

            $this->descriptors[] = $descriptor;
        }

        return $this->descriptors;
    }

    /**
     * Load extensions from the auto-generated manifest file.
     *
     * @return array<ExtensionDescriptor>
     */
    protected function loadManifest(): array
    {
        if (!file_exists($this->manifestPath)) {
            return [];
        }

        $manifest = require $this->manifestPath;

        if (!is_array($manifest)) {
            return [];
        }

        $descriptors = [];

        foreach ($manifest as $entry) {
            $className = $entry['class'] ?? null;
            $packageName = $entry['package'] ?? null;
            $enabled = $entry['enabled'] ?? true;

            if ($className === null) {
                continue;
            }

            $descriptors[$className] = new ExtensionDescriptor(
                className: $className,
                name: $packageName ?? $className,
                enabled: $enabled,
                config: [],
                source: 'manifest',
            );
        }

        return $descriptors;
    }

    /**
     * Merge manifest extensions with settings extensions.
     *
     * Settings can:
     * - Override enabled status of manifest extensions
     * - Add config to manifest extensions
     * - Add new extensions not in the manifest
     *
     * Settings format can be either:
     * - Legacy array format: [{"class": "...", "enabled": true, "config": {...}}]
     * - New keyed format: {"Fully\\Qualified\\Class": {"enabled": false, "config": {...}}}
     *
     * @param array<ExtensionDescriptor> $manifest
     * @param array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}>|array<string, array{enabled?: bool, config?: array<string, mixed>}> $settingsExtensions
     * @return array<ExtensionDescriptor>
     */
    protected function mergeExtensions(array $manifest, array $settingsExtensions): array
    {
        $merged = [];

        // First, add all manifest extensions
        foreach ($manifest as $className => $descriptor) {
            $merged[$className] = $descriptor;
        }

        // Process settings extensions - detect format by checking if keys are strings (new format)
        $isNewFormat = $settingsExtensions !== [] && is_string(array_key_first($settingsExtensions));

        if ($isNewFormat) {
            // New keyed format: className => {enabled, config}
            foreach ($settingsExtensions as $className => $config) {
                if (!is_string($className)) {
                    continue;
                }

                if (isset($merged[$className])) {
                    // Override existing manifest extension
                    $descriptor = $merged[$className];
                    $merged[$className] = new ExtensionDescriptor(
                        className: $className,
                        name: $descriptor->name,
                        enabled: $config['enabled'] ?? $descriptor->enabled,
                        config: $config['config'] ?? $descriptor->config,
                        source: $descriptor->source,
                    );
                } else {
                    // Add new extension not in manifest
                    $merged[$className] = new ExtensionDescriptor(
                        className: $className,
                        name: $className,
                        enabled: $config['enabled'] ?? true,
                        config: $config['config'] ?? [],
                        source: 'settings',
                    );
                }
            }
        } else {
            // Legacy array format: [{class: "...", enabled: true, config: {...}}]
            foreach ($settingsExtensions as $config) {
                $className = $config['class'] ?? null;

                if ($className === null) {
                    continue;
                }

                if (isset($merged[$className])) {
                    // Override existing manifest extension
                    $descriptor = $merged[$className];
                    $merged[$className] = new ExtensionDescriptor(
                        className: $className,
                        name: $descriptor->name,
                        enabled: $config['enabled'] ?? $descriptor->enabled,
                        config: $config['config'] ?? $descriptor->config,
                        source: $descriptor->source,
                    );
                } else {
                    // Add new extension not in manifest
                    $merged[$className] = new ExtensionDescriptor(
                        className: $className,
                        name: $className,
                        enabled: $config['enabled'] ?? true,
                        config: $config['config'] ?? [],
                        source: 'settings',
                    );
                }
            }
        }

        return array_values($merged);
    }

    /**
     * Initialize an extension by instantiating it and calling register().
     *
     * @throws InvalidArgumentException if the class doesn't implement ExtensionInterface
     * @throws RuntimeException if the extension fails to initialize
     */
    protected function initialize(ExtensionDescriptor $descriptor): void
    {
        try {
            $instance = $descriptor->config !== []
                ? new $descriptor->className($descriptor->config)
                : new $descriptor->className();

            if (!$instance instanceof ExtensionInterface) {
                throw new InvalidArgumentException(
                    sprintf('Extension class "%s" must implement %s.', $descriptor->className, ExtensionInterface::class)
                );
            }

            $this->initializeExtension($instance);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf('Failed to initialize extension "%s": %s', $descriptor->className, $e->getMessage()), $e->getCode(), previous: $e);
        }
    }

    /**
     * Call register() on an extension instance with a fully wired ExtensionApi.
     */
    protected function initializeExtension(ExtensionInterface $extension): void
    {
        $api = new ExtensionApi(
            tools: $this->tools,
            commands: $this->commands,
            renderers: $this->renderers,
            events: $this->events,
            ui: $this->uiEngine()->createBuilder(),
            memories: $this->memories,
            settings: $this->settings,
        );

        $extension->register($api);
    }

    /**
     * Get all loaded extension descriptors.
     *
     * @return array<ExtensionDescriptor>
     */
    public function descriptors(): array
    {
        return $this->descriptors;
    }

    /**
     * Get the tool registry.
     */
    public function tools(): ToolRegistry
    {
        return $this->tools;
    }

    /**
     * Get the command registry.
     */
    public function commands(): CommandRegistry
    {
        return $this->commands;
    }

    /**
     * Get the renderer registry.
     */
    public function renderers(): RendererRegistry
    {
        return $this->renderers;
    }

    /**
     * Get the event registry.
     */
    public function events(): EventRegistry
    {
        return $this->events;
    }

    /**
     * Get the memory registry.
     */
    public function memories(): MemoryRegistry
    {
        return $this->memories;
    }

    /**
     * Get the UiEngine instance, creating a default one if not injected.
     */
    public function uiEngine(): UiEngine
    {
        return $this->uiEngine ??= new UiEngine(
            new DarkTheme(),
            new SlotRegistry(),
            new WidgetRegistry(),
        );
    }

    /**
     * Create a loader with default registries.
     */
    public static function create(ToolRenderer $fallbackRenderer, Settings $settings, string $manifestPath = self::MANIFEST_PATH): self
    {
        return new self(
            tools: new ToolRegistry(),
            commands: new CommandRegistry(),
            renderers: new RendererRegistry($fallbackRenderer),
            events: new EventRegistry(),
            memories: new MemoryRegistry(),
            settings: $settings,
            uiEngine: new UiEngine(
                new DarkTheme(),
                new SlotRegistry(),
                new WidgetRegistry(),
            ),
            manifestPath: $manifestPath,
        );
    }
}
