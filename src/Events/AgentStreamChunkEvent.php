<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Events;

/**
 * Dispatched for each streamed text chunk as it arrives from the provider.
 *
 * The TUI presenter accumulates these into the live assistant Markdown widget.
 * Reasoning/tool chunks are handled separately; this event only carries text.
 */
class AgentStreamChunkEvent
{
    public function __construct(public readonly string $chunk)
    {
    }
}
