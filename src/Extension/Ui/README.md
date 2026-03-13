# Maestro UI System

The UI system gives extensions full control over what appears in the terminal: layout regions (slots), visual styles (themes), and reusable output components (widgets). All of it is accessible through the `UiBuilder` object exposed by `$api->ui()`.

---

## Layout: Slots

A **slot** is a named region of the terminal output. Maestro renders four slots in order on every relevant output cycle:

```
┌──────────────────────────────────┐
│  header                          │  ← printed once, followed by a blank line
├──────────────────────────────────┤
│  content                         │  ← main response / tool output area
├──────────────────────────────────┤
│  status_bar                      │  ← printed inline (no trailing newline)
├──────────────────────────────────┤
│  footer                          │  ← preceded by a blank line, printed last
└──────────────────────────────────┘
```

| Slot | When rendered | Line ending |
|------|---------------|-------------|
| `header` | Before content, only when non-empty | Followed by a blank line |
| `content` | Every line written as its own `writeln` call | Normal newline per item |
| `status_bar` | After content, only when non-empty | No trailing newline (`write`, not `writeln`) |
| `footer` | After status bar, only when non-empty | Preceded by a blank line |

### Adding content to a slot

```php
$api->ui()->addToSlot(SlotType::HEADER, 'My Custom Header', priority: 100);
```

**What happens when other extensions have already added content to the same slot?**

Nothing is overwritten. Every call to `addToSlot` appends a new item to the slot's internal list. When the slot is rendered, all items are sorted by `priority` — **higher number renders first** — and then printed sequentially. The default priority is `500`.

```php
// Extension A
$api->ui()->addToSlot(SlotType::HEADER, 'Project: my-app', priority: 900);

// Extension B
$api->ui()->addToSlot(SlotType::HEADER, 'Branch: main', priority: 800);

// Extension C (core)
$api->ui()->addToSlot(SlotType::HEADER, 'Version: 1.0', priority: 100);

// Rendered order → Project: my-app  Branch: main  Version: 1.0
```

Use a high priority (e.g. `900`) to appear at the top of a slot and a low priority (e.g. `100`) to appear near the bottom.

### Status bar: appending, not replacing

The `status_bar` slot is designed to be composed by multiple extensions. Each item added with `addToSlot` is appended to the others. Because the slot renders without a trailing newline, all items appear on the same line:

```php
// Extension A
$api->ui()->addToSlot(SlotType::STATUS_BAR, ' ⎇ main ', priority: 700);

// Extension B
$api->ui()->addToSlot(SlotType::STATUS_BAR, ' ✓ 3 tests ', priority: 600);

// Rendered → " ⎇ main  ✓ 3 tests "
```

### Clearing a slot

```php
$api->ui()->clearSlot(SlotType::HEADER);
```

Removes all items from that slot immediately, including anything registered by other extensions. Use carefully — this affects the entire application's output for that slot.

### Slot name constants

Use the `SlotType` enum to avoid typos:

```php
use NeuronCore\Maestro\Extension\Ui\SlotType;

$api->ui()->addToSlot(SlotType::HEADER,'My Header');
$api->ui()->addToSlot(SlotType::STATUS_BAR,' info ');
$api->ui()->addToSlot(SlotType::FOOTER,'Tip: type /help');
```

You can also register content into custom slot names if you coordinate with other extensions, though only the four built-in slots are rendered by the default `UiEngine`.

---

## Themes

A theme defines the mapping from semantic names (e.g. `primary`, `success`) to concrete terminal color codes, text styles, and icon characters.

### Registering a theme

```php
use NeuronCore\Maestro\Extension\Ui\ThemeInterface;

$api->ui()->registerTheme(new MyCompanyTheme());
```

**This replaces the active theme globally.** There is only one active theme at a time. All subsequent calls to `formatText()`, `theme()->color()`, etc. — by any extension — will use the newly registered theme. If multiple extensions each call `registerTheme()`, the last one to register wins, in extension load order.

The default theme is `DarkTheme`.

### Implementing a theme

```php
use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\IconName;
use NeuronCore\Maestro\Extension\Ui\StyleName;
use NeuronCore\Maestro\Extension\Ui\ThemeInterface;

class MyCompanyTheme implements ThemeInterface
{
    public function name(): string
    {
        return 'my-company';
    }

    public function color(ColorName $color): string
    {
        return match ($color) {
            ColorName::PRIMARY => '#0066cc',  // Symfony Console supports hex on modern terminals
            ColorName::SUCCESS => 'green',
            ColorName::WARNING => 'yellow',
            ColorName::ERROR   => 'red',
            ColorName::INFO    => 'cyan',
            ColorName::MUTED   => 'gray',
            ColorName::ACCENT  => '#ff6600',
        };
    }

    public function style(StyleName $style): string
    {
        return match ($style) {
            StyleName::BOLD      => 'options=bold',
            StyleName::DIM       => 'options=dim',
            StyleName::UNDERLINE => 'options=underscore',
            StyleName::DEFAULT   => '',
        };
    }

    public function icon(IconName $icon): string
    {
        return match ($icon) {
            IconName::SUCCESS     => '✔',
            IconName::ERROR       => '✘',
            IconName::WARNING     => '▲',
            IconName::INFO        => '●',
            IconName::SPINNER     => '◌',
            IconName::ARROW_RIGHT => '→',
            IconName::ARROW_DOWN  => '↓',
            IconName::DOT         => '·',
        };
    }
}
```

### Semantic color names

Use the `ColorName` enum for the standard names that all themes are expected to support:

| Constant | Value | Intended use |
|----------|-------|--------------|
| `ColorName::PRIMARY` | `primary` | Main brand / accent color |
| `ColorName::SUCCESS` | `success` | Positive outcomes, completions |
| `ColorName::WARNING` | `warning` | Non-fatal issues, cautions |
| `ColorName::ERROR` | `error` | Failures, fatal issues |
| `ColorName::INFO` | `info` | Neutral informational messages |
| `ColorName::MUTED` | `muted` | De-emphasized, secondary text |
| `ColorName::ACCENT` | `accent` | Highlights, call-to-action |

### Semantic style names

| Constant | Value | Effect |
|----------|-------|--------|
| `StyleName::BOLD` | `bold` | Bold text |
| `StyleName::DIM` | `dim` | Dimmed / faded text |
| `StyleName::UNDERLINE` | `underline` | Underlined text |
| `StyleName::DEFAULT` | `default` | No extra styling |

### Icon names

| Constant | Value | Default glyph | Intended use |
|----------|-------|---------------|--------------|
| `IconName::SUCCESS` | `success` | `` | Operation completed |
| `IconName::ERROR` | `error` | `` | Operation failed |
| `IconName::WARNING` | `warning` | `⚠` | Attention needed |
| `IconName::INFO` | `info` | `` | Informational |
| `IconName::SPINNER` | `spinner` | `⠋` | Loading / in-progress |
| `IconName::ARROW_RIGHT` | `arrow_right` | `` | Navigation, continuation |
| `IconName::ARROW_DOWN` | `arrow_down` | `` | Expansion, detail |
| `IconName::DOT` | `dot` | `·` | Bullet point, separator |

---

## Formatting text

`UiBuilder::formatText()` wraps a string in Symfony Console markup using the active theme's colors and styles:

```php
use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\StyleName;

// Color only → <fg=cyan>text</>
$line = $api->ui()->formatText('text', ColorName::PRIMARY);

// Style only → <options=bold>text</>
$line = $api->ui()->formatText('text', style: StyleName::BOLD);

// Color + style → <fg=cyan;options=bold>text</>
$line = $api->ui()->formatText('text', ColorName::PRIMARY,StyleName::BOLD);

// No formatting (empty strings returned by theme) → text
$line = $api->ui()->formatText('text');
```

The formatted string can be passed directly to a slot or returned from a widget's `render()` method. Symfony Console interprets the markup tags when writing to an output that supports ANSI codes; on non-TTY outputs they are stripped automatically.

To read an icon from the current theme:

```php
use NeuronCore\Maestro\Extension\Ui\IconName;

$icon = $api->ui()->theme()->icon(IconName::SUCCESS);
$line = $api->ui()->formatText("{$icon} Done", ColorName::SUCCESS);
```

---

## Widgets

A **widget** is a named object that knows how to render a specific type of structured data into a terminal string. Widgets let you replace or extend the default rendering for agent responses, tool calls, or any custom content type your extension introduces.

### Where widgets appear

Widgets do not render themselves automatically. They must be invoked by the rendering layer (typically inside a `ToolRenderer` or an event handler) by looking them up from the registry and calling `render()`. Registering a widget makes it available to the rest of the application — it does not cause output on its own.

### Content types

The `ContentType` enum defines the standard types:

| Constant | Value | What it represents |
|----------|-------|--------------------|
| `ContentType::TOOL_CALL` | `tool_call` | The display of a tool invocation and its arguments |
| `ContentType::AGENT_RESPONSE` | `agent_response` | The AI's text response printed to the user |
| `ContentType::AGENT_THINKING` | `agent_thinking` | The "thinking" / loading state before a response |
| `ContentType::STATUS` | `status` | Status bar or contextual status information |

You are not limited to these values. Custom content types (e.g. `'deploy_status'`) work the same way as long as your code is consistent about the name when registering and looking up the widget.

### What happens when two extensions register a widget with the same name?

Widget names must be unique within the registry. Registering a widget with a name that already exists **replaces the previously registered widget**. The last extension to load wins. This is intentional: it allows an extension to override a widget provided by another extension or by the core.

If you want to extend rather than replace, choose a unique name for your widget and register it alongside the existing one. Multiple widgets can share the same `contentType()` — the rendering layer can retrieve all widgets for a type via `WidgetRegistry::forType()`.

### Creating a widget

```php
use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\ContentType;
use NeuronCore\Maestro\Extension\Ui\IconName;
use NeuronCore\Maestro\Extension\Ui\UiBuilder;
use NeuronCore\Maestro\Extension\Ui\WidgetInterface;

class DeployStatusWidget implements WidgetInterface
{
    public function name(): string
    {
        // Unique name — used as the registry key
        return 'deploy_status';
    }

    public function contentType(): ContentType
    {
        // Groups this widget with others of the same type
        return ContentType::STATUS;
    }

    public function render(array $data, UiBuilder $ui): string
    {
        $icon = $ui->theme()->icon(IconName::SUCCESS);
        $env  = $data['environment'] ?? 'unknown';

        return $ui->formatText("{$icon} Deployed to: {$env}", ColorName::SUCCESS);
    }
}
```

The `$data` array is passed by the caller of `render()`. Its structure is defined by convention between the widget and whatever code invokes it.

### Registering a widget

```php
// Shorthand via ExtensionApi
$api->registerWidget(new DeployStatusWidget());

// Equivalent via UiBuilder
$api->ui()->registerWidget(new DeployStatusWidget());
```

Both calls write to the same `WidgetRegistry`, so the result is identical. Use the shorthand when registering from `register()` for consistency with `registerTool()` and `registerCommand()`.

---
