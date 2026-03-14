# Maestro Extension System

Extensions allow you to customize Maestro by adding custom tools, commands, renderers, and event handlers.

## Creating an Extension

An extension is a PHP class that implements `ExtensionInterface`:

```php
<?php

namespace MyVendor\MyExtension;

use NeuronCore\Maestro\Extension\ExtensionInterface;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronAI\Tools\ToolInterface;
use NeuronCore\Maestro\Rendering\ToolRenderer;

class MyExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'my-extension';
    }

    public function register(ExtensionApi $api): void
    {
        // Register an AI tool
        $api->registerTool($myTool);

        // Register an inline command
        $api->registerCommand($myCommand);

        // Register a custom renderer for a tool
        $api->registerRenderer('my_tool', $myRenderer);

        // Register an event handler
        $api->on('AgentResponseEvent', function ($event, $context) {
            // Handle event
        });
    }
}
```

## Extension API

The `ExtensionApi` provides the following registration methods:

| Method | Purpose |
|---------|-----------|
| `registerTool(ToolInterface $tool)` | Register an AI tool that the agent can use |
| `registerCommand(InlineCommand $command)` | Register an inline command (e.g., `/status`) |
| `registerRenderer(string $toolName, ToolRenderer $renderer)` | Register a custom renderer for tool output |
| `registerMemory(string $key, string $filePath)` | Register a memory file to be injected into the agent's system prompt |
| `on(string $event, callable $handler)` | Register a callback for an event |
| `tools()` | Get the tool registry for advanced registration |
| `commands()` | Get the command registry for advanced registration |
| `renderers()` | Get the renderer registry for advanced registration |
| `events()` | Get the event registry for advanced registration |
| `memories()` | Get the memory registry for advanced registration |

## Registering Memory Files

Extensions can register memory files that will be automatically loaded and injected into the agent's system prompt. This is useful for providing project-specific instructions, guidelines, or context that should be available to the AI agent.

```php
class MyExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'my-extension';
    }

    public function register(ExtensionApi $api): void
    {
        // Register a memory file bundled with your extension
        $api->registerMemory('my-extension.guidelines', __DIR__ . '/memory/guidelines.md');
    }
}
```

The memory file must:
- Exist and be readable at the specified path
- Use absolute paths (use `__DIR__` to reference files within your extension)
- Contain Markdown content that will be formatted and injected into the system prompt

Memory files registered by extensions are loaded alongside files from `.maestro/memories/` and are presented to the agent with the key you provided (e.g., `### my-extension.guidelines`).

## Registering an Inline Command

Inline commands are available in the interactive console (prefix with `/`):

```php
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MyCommand implements InlineCommand
{
    public function getName(): string
    {
        return 'my-command';
    }

    public function getDescription(): string
    {
        return 'My custom command description';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $output->writeln('Hello from my command!');
    }
}
```

## Registering an Event Handler

Extensions can react to application events:

```php
$api->on(AgentThinkingEvent::class, function ($event, $context) {
    // Before AI thinks
});

$api->on(AgentResponseEvent::class, function ($event, $context) {
    // After AI responds
});

$api->on(ToolApprovalRequestedEvent::class, function ($event, $context) {
    // When tool approval is requested
});
```

## Loading Extensions

Add your extension to `.maestro/settings.json`:

```json
{
    "extensions": [
        {
            "class": "MyVendor\\MyExtension\\MyExtension",
            "enabled": true,
            "config": {
                "api_key": "your-api-key"
            }
        }
    ]
}
```

Access config in your extension:

```php
class MyExtension implements ExtensionInterface
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    // ... rest of implementation

    public function register(ExtensionApi $api): void
    {
        $apiKey = $this->config['api_key'] ?? null;
        // Use the config
    }
}
```

## Available Events

| Event | When Fired |
|--------|-------------|
| `AgentThinkingEvent` | Before the AI agent starts thinking |
| `AgentResponseEvent` | After the AI agent responds with content |
| `ToolApprovalRequestedEvent` | When a tool requires user approval |

## Packaging an Extension

Create a Composer package for your extension:

`composer.json`:
```json
{
    "name": "my-vendor/my-extension",
    "type": "library",
    "description": "My Maestro extension",
    "require": {
        "neuron-core/maestro": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\MyExtension\\": "src/"
        }
    }
}
```

### Auto-Discovery

To enable automatic extension discovery, add the `extra.maestro` field to your `composer.json`:

```json
{
    "name": "my-vendor/my-extension",
    "type": "library",
    "description": "My Maestro extension",
    "require": {
        "neuron-core/maestro": "^1.0"
    },
    "extra": {
        "maestro": {
            "extensions": [
                "MyVendor\\MyExtension\\MyExtension"
            ]
        }
    },
    "autoload": {
        "psr-4": {
            "MyVendor\\MyExtension\\": "src/"
        }
    }
}
```

The `extra.maestro.extensions` array should contain fully qualified class names of your extension classes. When users install your package via Composer and run `composer dump-autoload`, Maestro's discovery command will automatically detect and register your extensions.

**Multiple Extensions:** A single package can declare multiple extensions:

```json
{
    "extra": {
        "maestro": {
            "extensions": [
                "MyVendor\\Package\\CoreExtension",
                "MyVendor\\Package\\AdminExtension",
                "MyVendor\\Package\\ReportingExtension"
            ]
        }
    }
}
```

**Disabling Extensions:** Users can disable auto-discovered extensions in their `.maestro/settings.json`:

```json
{
    "extensions": {
        "MyVendor\\Package\\CoreExtension": {
            "enabled": false
        },
        "MyVendor\\Package\\AdminExtension": {
            "enabled": true,
            "config": {
                "api_key": "your-api-key"
            }
        }
    }
}
```

**Manual Registration:** Extensions can still be manually registered in `.maestro/settings.json` even if they are not auto-discovered:

```json
{
    "extensions": {
        "MyVendor\\AnotherExtension\\CustomExtension": {
            "enabled": true,
            "config": {
                "option": "value"
            }
        }
    }
}
```

Users can then install your extension:

```bash
composer require my-vendor/my-extension
```

After installation, run `composer dump-autoload` to trigger auto-discovery, or manually run `maestro discover`. Extensions can be configured in `.maestro/settings.json`.

## UI Customization

Extensions can fully customize the terminal interface through slots, themes, and widgets via `$api->ui()`:

```php
$api->ui()->addToSlot(SlotType::HEADER, 'My Custom Header', priority: 100);
$api->ui()->addToSlot(SlotType::STATUS_BAR, ' ⎇ main ', priority: 500);
$api->registerWidget(new MyStatusWidget());
$api->ui()->registerTheme(new MyCompanyTheme());
```

For complete documentation on slots, themes, text formatting, icons, and widgets — including how multiple extensions interact and what each call visually produces — see the **[UI System README](Ui/README.md)**.
