# Maestro v2 — Symfony TUI Refactor Plan

> Status: **Proposal — awaiting decisions (see §10)**
> Scope: New major version. Backward compatibility is explicitly **not** a goal.
> Date: 2026-06-18

## 1. Goal

Replace the Symfony-Console-based CLI with a full-screen terminal UI built on the
new **`symfony/tui`** component, focused on the coding-agent use case:

- Live, streaming AI responses rendered as **Markdown with syntax highlighting**.
- **Tool-execution feedback** (tool call + result cards in a scrollable transcript).
- **Tool approval** managed inside the TUI (modal selection, non-blocking).
- **File diffs** on file edits, rendered as a real diff view.
- **Status surface** for modes the user needs to see: *auto* (skip approval),
  *plan* (read-only), current provider/model.

The custom "templating / theming / slot / widget-customization" system
(`src/Extension/Ui/*`, `src/Rendering/*`) is **removed entirely**. Styling moves to
TUI stylesheets; tool presentation moves to a small, typed view layer.

## 2. What changes, what stays

| Layer | Disposition |
|---|---|
| `src/Agent/*`, `src/Agent/Middleware/*` | **Keep** (add streaming wiring) |
| `src/Orchestrator/AgentOrchestrator` | **Keep & adapt** (add a streaming path + mode-aware approval policy) |
| `src/Events/*` + `src/EventBus/*` | **Keep** (add `AgentStreamChunkEvent`, `ToolExecutedEvent`) |
| `src/Extension/*` (minus `Ui`) | **Keep** the extension/registry core; trim `ExtensionApi` |
| `src/Settings/*` | **Keep** (add `auto_mode`, `plan_mode`, `destructive_tools`) |
| `src/Console/Inline/*` | **Keep the abstraction**; retarget output from Console to the transcript |
| `src/Extension/Ui/*` | **Delete** (slots, widgets, themes, `Text`, `UiEngine`, `UiBuilder`) |
| `src/Rendering/*` + `RendererRegistry` | **Delete** (replaced by typed `ToolView`s) |
| `src/Listeners/CliOutputListener` | **Delete** (replaced by `TuiPresenter`) |
| `src/Console/SelectMenuHelper`, `SpinnerProgress` | **Delete** (replaced by TUI widgets) |
| `src/Commands/MaestroCommand` | **Replace** the REPL with a thin command that boots the `Tui` |

## 3. Target architecture

```
bin/maestro
  └─ Symfony Console Application
       └─ MaestroCommand::execute()        ← thin: reads settings, builds Tui, run()
            └─ src/Tui/MaestroTui           ← owns the Tui, the widget tree, the fiber bridge
                 ├─ builds widget tree (status bar / transcript / input)
                 ├─ onTick / addListener     ← drives agent from a suspended fiber
                 └─ TuiPresenter             ← agent events → widget mutations
                      ├─ subscribes to Maestro PSR-14 bus (agent lifecycle)
                      └─ subscribed by the Tui symfony dispatcher (widget events)

AgentOrchestrator (unchanged contract) → Maestro event bus → TuiPresenter → widgets
```

Two event buses, bridged by the presenter (each stays single-purpose):

- **Maestro agent bus** (PSR-14, unchanged): `BeforeChatEvent`, `AgentThinkingEvent`,
  `AgentStreamChunkEvent` *(new)*, `AgentResponseEvent`, `AfterChatEvent`,
  `ToolApprovalRequestedEvent`, `ToolExecutedEvent` *(new)*.
- **TUI widget bus** (`symfony/event-dispatcher`, owned by `Tui`): `SubmitEvent`,
  `SelectEvent`, `CancelEvent`, `TickEvent`, `InputEvent`, `QuitEvent`.

> Decision §10.1 — whether to migrate the agent bus to `symfony/event-dispatcher` too.

## 4. The concurrency model (centerpiece)

The TUI runs on PHP Fibers + Revolt. `Tui::run()` suspends a fiber and the loop keeps
rendering + reading input. This lets us keep the orchestrator's **synchronous,
blocking** interrupt/resume logic *exactly as it is* and still get a responsive UI.

### One chat turn

1. User submits prompt → `SubmitEvent` (TUI bus).
2. Presenter **defers the agent call onto its own fiber**:
   `EventLoop::defer(fn() => $this->orchestrator->chat($input))`.
3. The agent runs on that fiber. Meanwhile the main loop renders a `LoaderWidget`
   ("thinking…") and keeps handling input/resizes.
4. **Streaming**: orchestrator iterates `$agent->stream()` and, per chunk, dispatches
   `AgentStreamChunkEvent`. Presenter appends to the live `MarkdownWidget`
   (`->setText($accumulated)`) — the dirty-tracking re-renders only what changed.
5. **Tool approval** (the interesting part) — see §6.3.
6. On completion, `AgentResponseEvent`/`AfterChatEvent` finalize the transcript card
   and re-focus the input.

### Approval via fiber suspension (no orchestrator changes)

The orchestrator already dispatches `ToolApprovalRequestedEvent` **synchronously inside
the agent fiber** and blocks on the listener's return. We exploit that:

1. Presenter receives `ToolApprovalRequestedEvent`. It builds an approval card
   (`SelectListWidget`: *Allow once / Allow for session / Reject*) and inserts it,
   then **suspends the current fiber**:
   `$this->approvalSuspension = EventLoop::getSuspension(); $decision = $this->approvalSuspension->suspend();`
2. Control returns to the loop → the approval card renders → the user picks.
3. The card's `onSelect` runs **on the loop fiber** and resumes the agent fiber:
   `$this->approvalSuspension->resume($decision);`
4. Presenter applies the decision to the `Action`(s) (`approve()`/`reject($feedback)`),
   removes the card, and **returns** from the listener — the orchestrator resumes the
   workflow with the mutated `ApprovalRequest` exactly as it does today.

Net effect: the orchestrator's `WorkflowInterrupt` → dispatch → resume round-trip is
**untouched**. Only the *how the decision is collected* changes (TUI modal + fiber
suspend instead of a blocking `QuestionHelper`).

## 5. UI layout

Single full-screen `ContainerWidget` (vertical), top to bottom:

```
┌─ StatusBar ──────────────────────────────────────┐  TextWidget (1 line)
│ maestro · anthropic/claude… · AUTO · 1.2k tok    │
├─ Transcript (expandVertically, scrollable) ──────┤  ContainerWidget
│  ╭ user ───────────────────────────────────╮     │   repeated "cards":
│  │ refactor Foo::bar() to return int       │     │     UserCard
│  ╰─────────────────────────────────────────╯     │     AssistantCard (MarkdownWidget)
│  ⠋ thinking…                                     │     ThinkingCard (LoaderWidget)
│  ▸ edit_file  src/Foo.php                        │     ToolCallCard
│  │  @@ diff ───────────────────────────────      │     DiffView (inside ToolResultCard)
│  ╰────────────────────────────────────────────╯  │     ToolResultCard
│  ╭ approval required ──────────────────────╮     │     ApprovalCard (SelectListWidget)
│  │ Allow once / Allow for session / Reject  │     │
│  ╰─────────────────────────────────────────╯     │
├─ Input ──────────────────────────────────────────┤  EditorWidget (multi-line)
│ > _                                              │     (slash commands detected on submit)
└──────────────────────────────────────────────────┘
```

`OverlayWidget` (TUI) is currently a *follow-up PR* and undocumented in the installed
release, so **modals are rendered inline as transcript cards**, not floating overlays.
This is simpler and sufficient. (Revisit when Overlay ships.)

## 6. Component designs

### 6.1 Transcript + streaming Markdown
- A vertical `ContainerWidget` holding an ordered list of *cards*.
- `AssistantCard` wraps a `MarkdownWidget`. During streaming the presenter calls
  `setText($accumulatedChunk)` per chunk; `MarkdownWidget` auto-invalidates, so the
  re-render is incremental. Code highlighting comes for free (tempest/highlight).
- Old `src/Rendering/MarkdownRenderer` is **deleted** — `MarkdownWidget` replaces it.

### 6.2 Tool-execution feedback
Two card types per tool call (both populated from structured data, not pre-formatted
strings):

- **`ToolCallCard`**: tool name, target (e.g. file path), and a one-line arg summary.
  Built from the `Action` (name + parsed `description` JSON) at approval time.
- **`ToolResultCard`**: outcome (ok/error), duration, and a body that is either:
  - a `DiffView` for `edit_file` / `write_file` (§6.4),
  - a `SnippetView` for `read_file` (first/changed lines, language-detected),
  - a generic JSON/`TextWidget` fallback.

A new `ToolExecutedEvent` (tool name, inputs, result, status, ms) fires from the
orchestrator after each tool runs; the presenter builds the result card from it.

### 6.3 Tool approval (modal + fiber suspension)
- Approval decision comes from a `SelectListWidget` with items
  `[{value:'once',…},{value:'session',…},{value:'reject',…}]`.
- "Reject" reveals a follow-up `EditorWidget` for feedback (mirrors today's flow).
- **Auto mode** (`settings.auto_mode`): the orchestrator's `ToolApproval` middleware is
  **not attached** (or attached with an explicit allow-only list), so no interrupt is
  thrown — approval cards never appear. StatusBar shows `AUTO`.
- **Session allowlist** retained (e.g. `read_file` auto-approved for the session).
- **Plan mode** (`settings.plan_mode`): a tool-approval policy that **auto-rejects**
  mutating tools with feedback *"plan mode: not applying changes"*, while read-only
  tools run normally. The agent still "plans" and diffs are still *shown* (as proposed
  changes), just not applied. StatusBar shows `PLAN`.

### 6.4 File diffs
- New `DiffView`: a small widget (extends `AbstractWidget`) that takes a structured
  `FileDiff{ path, hunks: DiffHunk[] }` DTO and renders a unified diff with
  `+`/`-` lines colored via `Style` (green/red) and a `@@` hunk header.
- Source of truth: parse the edit tool's inputs from the `Action->description` /
  `ToolExecutedEvent` payload (old/new content), compute the diff in PHP (small
  line-diff helper — no new dependency).
- This **replaces** `EditFileRenderer` / `FileChangeRenderer`, which return flat
  colored strings. The new contract is data-in, widget-out.

### 6.5 Status bar
A 1-line `TextWidget` rebuilt on each interesting event:
`maestro · {provider}/{model} · [AUTO] · [PLAN] · {tokens} · {cwd}`.
Modes are derived from settings + toggles (`/auto`, `/plan` inline commands or
keybindings).

### 6.6 Input + slash commands
- `EditorWidget` for multi-line prompts (single-line `InputWidget` is the fallback for
  narrow terminals). Enter submits; a binding (e.g. Alt+Enter) inserts a newline.
- On submit, if the text starts with `/`, dispatch to the **kept**
  `InlineCommandRegistry` instead of the agent. Inline commands no longer write to a
  Console `OutputInterface`; they push a `Card` into the transcript (a small adapter
  bridges the existing `InlineCommand::execute()` signature).

### 6.7 Styling
- One `MaestroStyleSheet` (replaces `DarkTheme`/`LightTheme` + `Text`/`ColorName`).
  Defines classes for `.user-card`, `.assistant-card`, `.tool-call`, `.diff-add`,
  `.diff-del`, `.status-bar`, `.approval`, etc., using TUI Tailwind-style colors.
- No user-facing theming API in v2 (the complexity being removed). A single, opinionated
  dark stylesheet. Light theme = a follow-up if requested.

## 7. New extension contract (v2)

`ExtensionApi` changes — **breaking** (acceptable for a major version):

**Removed**
- `ui(): UiBuilder`, `registerWidget()`, `registerRenderer()`
- All of `src/Extension/Ui/*` (themes, slots, `Text`, `ContentType`, `IconName`, …)

**Kept (unchanged shape)**
- `registerTool()`, `registerCommand()` (`InlineCommand`), `registerMemory()`, `on()`
- `tools()`, `commands()`, `memories()`, `events()`, `settings()`

**Added**
- `registerToolView(string $toolName, ToolViewFactory $factory)` — lets an extension
  provide a custom transcript card for its tool's call and/or result. A `ToolView`
  returns one or more widgets. Built-ins: `read_file`→snippet, `edit_file`/`write_file`
  →diff, default→generic JSON. This is the typed replacement for `ToolRenderer`.

## 8. Removal checklist

Delete (24 src + ~10 tests):
- `src/Extension/Ui/**` (all 17 files incl. `Theme/`)
- `src/Rendering/**` incl. `Renderers/*` and `MarkdownRenderer`
- `src/Extension/Registry/RendererRegistry.php`
- `src/Listeners/CliOutputListener.php`
- `src/Console/SelectMenuHelper.php`, `src/Console/SpinnerProgress.php`
- corresponding `tests/Extension/Ui/**`, `tests/Rendering/**`,
  `tests/Console/{MarkdownRendererTest,SelectMenuHelperTest}.php`,
  `tests/Listeners/CliOutputListenerTest.php`

Rewire the dependents: `ExtensionLoader`, `ExtensionApi`, `MaestroCommand`,
`CoreExtension` (drop the slot-based banner → emit a `TextWidget` header instead).

## 9. Phased implementation

Each phase ends with a green verification gate.

**Phase 0 — Foundations** → `composer` clean, `phpstan`/`rector` green on touched files
- Bump `php` to `^8.4` (done), keep `symfony/tui`, `league/commonmark`, `tempest/highlight`,
  `revolt/event-loop` (done).
- Decide §10.1 (dispatcher) and §10.2 (streaming scope) before Phase 2.

**Phase 1 — New TUI skeleton (parallel to old code, not yet wired)**
- `src/Tui/MaestroTui.php`: build StatusBar + Transcript + Input; `$tui->run()` shows an
  empty shell; `SubmitEvent` echoes the prompt as a user card.
- `src/Tui/Style/MaestroStyleSheet.php`.
- Verify: `bin/maestro` opens a full-screen UI, typed text appears, Enter adds a card,
  `Ctrl+C`/`q` exits cleanly and restores the terminal.

**Phase 2 — Agent wiring (blocking first, then streaming)**
- Thin `MaestroCommand` boots `MaestroTui`; presenter subscribes to the agent bus.
- Blocking path: `chat()` → `AgentResponseEvent` → one `AssistantCard`. Verify a real
  provider round-trip renders Markdown.
- *(If §10.2 = yes)* Streaming path: orchestrator `stream()`, new
  `AgentStreamChunkEvent`, presenter appends chunks. Verify tokens appear live.

**Phase 3 — Tool execution + diffs**
- `ToolExecutedEvent` from orchestrator; `ToolView` registry + built-in views; `DiffView`
  widget. Verify an edit produces a colored diff card.

**Phase 4 — Approval + modes**
- Fiber-suspension approval card; auto/session-allowlist; `/auto`, `/plan`; status bar.
- Verify: rejecting with feedback changes agent behavior; auto-mode skips prompts;
  plan-mode auto-rejects edits.

**Phase 5 — Inline commands + polish**
- Retarget `InlineCommand` output to transcript cards; `/help`, `/provider`, etc.
- Scroll/keybindings/resize; error cards for exceptions.

**Phase 6 — Demolition**
- Delete §8 files and tests; rewire dependents; full `composer test` + `analyse` green.

## 10. Decisions required before implementation

### 10.1 Agent event bus: keep PSR-14 vs migrate to symfony/event-dispatcher
- **Recommend: keep PSR-14** (zero churn to the extension `$api->on()` contract; both
  dispatchers are synchronous so the fiber-suspension trick works either way).
- Migrate only if you want priority ordering / named events and one fewer standard.

### 10.2 Streaming: now or later
- **Recommend: now** (Phase 2). It's the single biggest UX win for a coding agent and
  Neuron AI already exposes `Agent::stream()`. The cost is one new event + the
  orchestrator streaming path. Skipping it means `MarkdownWidget` only updates once per
  full response — a visible downgrade from the goal.

### 10.3 Plan mode depth
- **Recommend: minimal** (auto-reject mutating tools, show proposed diffs, status
  indicator). Full "produce a written plan document" semantics can be a follow-up.

### 10.4 Custom diff widget vs markdown code-fence
- **Recommend: custom `DiffView`** — colored unified diff is a core coding-agent feature
  and `MarkdownWidget`'s code blocks don't model `+`/`-` hunks. It's ~80 lines.

## 11. Testing strategy
- TUI ships `Terminal/VirtualTerminal` (+ `TeeTerminal`) for headless rendering tests.
- New tests assert *rendered lines* against `VirtualTerminal` snapshots for: a streamed
  response, a diff card, an approval card.
- `MaestroTui` and the presenter take a `TerminalInterface` so they're injectable.
- Keep all non-UI unit tests (agent, settings, registries, events) — they're unaffected.

## 12. Risks / unknowns
- **`symfony/tui` is `@experimental`** with no stable docs; API may shift between now and
  a tagged release. Mitigation: thin adapter (`MaestroTui`) isolates the rest of the
  codebase from the TUI's surface.
- **Overlay/mouse/tabs** are listed as "follow-up PR" in the blog and absent from the
  installed package — plan assumes inline cards, not overlays.
- **Fiber re-entrancy**: `Tui::tick()` already guards against re-entrant ticks; the
  presenter must not mutate the widget tree from two fibers at once. Mitigation: all
  widget mutation happens on the loop fiber (the agent fiber only dispatches events;
  the presenter, running synchronously inside that dispatch, just stashes state + a
  suspension and returns — actual tree edits happen after resume, back on the loop).
- **WSL/Terminal capability**: the target dev environment is WSL2; verify Kitty/bracketed
  paste degrade gracefully (the TUI falls back to plain ANSI).
