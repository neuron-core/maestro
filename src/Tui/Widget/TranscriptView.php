<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\Widget;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/**
 * The scrollable conversation transcript: an ordered list of "cards"
 * (user prompts, assistant markdown, tool calls, tool results, approvals).
 *
 * Expands vertically to fill the space between the status bar and the input.
 */
final class TranscriptView extends ContainerWidget
{
    public function __construct()
    {
        $this->expandVertically(true);
        $this->addStyleClass('transcript');
    }

    /**
     * Append a card to the bottom of the transcript.
     */
    public function addCard(AbstractWidget $card): void
    {
        $this->add($card);
    }
}
