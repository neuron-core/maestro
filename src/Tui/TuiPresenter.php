<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui;

use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentStreamChunkEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\BeforeChatEvent;
use NeuronCore\Maestro\Events\ToolExecutedEvent;
use NeuronCore\Maestro\Tui\ToolView\ToolViewRegistry;
use NeuronCore\Maestro\Tui\Widget\Card;
use NeuronCore\Maestro\Tui\Widget\TranscriptView;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\LoaderWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\TextWidget;

use function array_map;
use function count;
use function implode;
use function trim;

/**
 * Bridges Maestro agent events to the TUI widget tree.
 *
 * Subscribes to the PSR-14 agent bus and mutates the transcript: a "thinking"
 * spinner while the model infers, a live-updating Markdown widget as text chunks
 * stream in, and (Phase 4) an approval card when a tool needs permission.
 *
 * Listeners run synchronously inside the agent fiber. Widget mutation here and
 * rendering on the loop fiber are serialized by cooperative scheduling, so there
 * is no concurrent tree access.
 */
final class TuiPresenter
{
    private ?LoaderWidget $thinking = null;
    private ?MarkdownWidget $assistant = null;
    private string $accumulated = '';

    /** @var array<string, Card> Map of tool name to its transcript card, so repeat calls refresh in place. */
    private array $toolCards = [];

    /** @var array<string, int<1, max>> */
    private array $toolCounts = [];

    /** @var array<string, list<string>> Accumulated summary lines (e.g. file paths) shown as a list on repeat calls. */
    private array $toolSummaries = [];

    public function __construct(
        private readonly TranscriptView $transcript,
        private readonly Tui $tui,
        private readonly ToolViewRegistry $toolViews,
    ) {
    }

    public function register(EventDispatcher $bus): void
    {
        $bus->subscribe(BeforeChatEvent::class, fn (BeforeChatEvent $e) => $this->onBeforeChat());
        $bus->subscribe(AgentThinkingEvent::class, fn (AgentThinkingEvent $e) => $this->onThinking());
        $bus->subscribe(AgentStreamChunkEvent::class, fn (AgentStreamChunkEvent $e) => $this->onChunk($e->chunk));
        $bus->subscribe(AgentResponseEvent::class, fn (AgentResponseEvent $e) => $this->onResponse($e->content));
        $bus->subscribe(ToolExecutedEvent::class, fn (ToolExecutedEvent $e) => $this->onToolExecuted($e));
    }

    private function onBeforeChat(): void
    {
        $this->assistant = null;
        $this->accumulated = '';
        $this->stopThinking();
        $this->toolCards = [];
        $this->toolCounts = [];
        $this->toolSummaries = [];
    }

    private function onThinking(): void
    {
        if ($this->thinking !== null) {
            return;
        }

        $this->thinking = new LoaderWidget('thinking');
        $this->transcript->addCard($this->thinking);
        $this->thinking->start();
        $this->tui->requestRender();
    }

    private function onChunk(string $chunk): void
    {
        $this->stopThinking();

        if ($this->assistant === null) {
            $this->assistant = (new MarkdownWidget(''))->addStyleClass('assistant-message');
            $this->transcript->addCard($this->assistant);
        }

        $this->accumulated .= $chunk;
        $this->assistant->setText($this->accumulated);
        $this->tui->requestRender();
    }

    private function onResponse(string $content): void
    {
        $this->stopThinking();

        if (trim($content) !== '') {
            if ($this->assistant === null) {
                $this->assistant = (new MarkdownWidget($content))->addStyleClass('assistant-message');
                $this->transcript->addCard($this->assistant);
            } else {
                $this->assistant->setText($content);
            }
        }

        $this->assistant = null;
        $this->accumulated = '';
        $this->tui->requestRender();
    }

    private function onToolExecuted(ToolExecutedEvent $event): void
    {
        // Collapse repeat calls of the same tool into one card: refresh its
        // title with the running count and swap the body for the latest result.
        $count = ($this->toolCounts[$event->toolName] ?? 0) + 1;
        $this->toolCounts[$event->toolName] = $count;
        $title = $count > 1 ? $event->toolName . ' (×' . $count . ')' : $event->toolName;

        // Tools whose result is a single summary line (read/parse → file path)
        // accumulate that line into a list across calls; others show only the
        // latest result.
        $summary = $this->summaryLine($event->toolName, $event->inputs);
        if ($summary !== null) {
            $this->toolSummaries[$event->toolName][] = $summary;
            $body = $this->renderSummaryList($this->toolSummaries[$event->toolName]);
        } else {
            $body = $this->toolViews
                ->viewFor($event->toolName)
                ->render($event->toolName, $event->inputs, $event->result);
        }

        if (isset($this->toolCards[$event->toolName])) {
            $card = $this->toolCards[$event->toolName];
            $card->setTitle($title);
            $card->setBody($body);
        } else {
            $card = new Card($title, $body);
            $this->toolCards[$event->toolName] = $card;
            $this->transcript->addCard($card);
        }

        $this->tui->requestRender();
    }

    /**
     * For tools whose single-call body is a summary line (read/parse → file
     * path), return that line so repeat calls can append it to a list.
     *
     * @param array<string, mixed> $inputs
     */
    private function summaryLine(string $toolName, array $inputs): ?string
    {
        $path = isset($inputs['file_path']) ? (string) $inputs['file_path'] : '';

        return match ($toolName) {
            'read_file', 'parse_file' => $path !== '' ? $path : null,
            default => null,
        };
    }

    /**
     * Render accumulated summary lines as a vertical list, bulleted once there
     * is more than one entry (a single call keeps its plain look).
     *
     * @param list<string> $lines
     */
    private function renderSummaryList(array $lines): AbstractWidget
    {
        $rows = count($lines) > 1
            ? array_map(static fn (string $line): string => '• ' . $line, $lines)
            : $lines;

        return (new TextWidget(implode("\n", $rows)))->addStyleClass('tool-result');
    }

    private function stopThinking(): void
    {
        if ($this->thinking === null) {
            return;
        }

        $this->thinking->stop();
        $this->transcript->remove($this->thinking);
        $this->thinking = null;
    }
}
