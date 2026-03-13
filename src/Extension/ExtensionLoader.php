<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension;

use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\UiEngine;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use Throwable;
use InvalidArgumentException;
use RuntimeException;

use function class_exists;
use function sprintf;

/**
 * Loads and initializes extensions from configuration.
 */
class ExtensionLoader
{
    /** @var array<ExtensionDescriptor> */
    protected array $descriptors = [];

    public function __construct(
        protected readonly ToolRegistry $tools,
        protected readonly CommandRegistry $commands,
        protected readonly RendererRegistry $renderers,
        protected readonly EventRegistry $events,
        protected ?UiEngine $uiEngine = null,
    ) {
    }

    /**
     * Register core (built-in) extensions directly, without a descriptor.
     */
    public function registerCore(ExtensionInterface ...$extensions): void
    {
        foreach ($extensions as $extension) {
            $this->initializeExtension($extension);
        }
    }

    /**
     * Load extensions from the settings array.
     *
     * @param array{extensions?: array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}>} $settings
     * @return array<ExtensionDescriptor>
     */
    public function load(array $settings): array
    {
        $extensions = $settings['extensions'] ?? [];

        foreach ($extensions as $config) {
            $className = $config['class'] ?? null;
            $enabled = $config['enabled'] ?? true;
            $extensionConfig = $config['config'] ?? [];
            if ($className === null) {
                continue;
            }
            if (!class_exists($className)) {
                continue;
            }

            $descriptor = new ExtensionDescriptor(
                className: $className,
                name: $className,
                enabled: $enabled,
                config: $extensionConfig,
            );

            if ($enabled) {
                $this->initialize($descriptor);
            }

            $this->descriptors[] = $descriptor;
        }

        return $this->descriptors;
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
    public static function create(ToolRenderer $fallbackRenderer): self
    {
        return new self(
            tools: new ToolRegistry(),
            commands: new CommandRegistry(),
            renderers: new RendererRegistry($fallbackRenderer),
            events: new EventRegistry(),
            uiEngine: new UiEngine(
                new DarkTheme(),
                new SlotRegistry(),
                new WidgetRegistry(),
            ),
        );
    }
}
