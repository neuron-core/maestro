<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\Widget;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * A bordered transcript card with a title line and a body widget.
 *
 * Styling (border, padding, vertical layout) comes from the `.card` stylesheet
 * rule; this class just assembles the title + body children.
 */
final class Card extends ContainerWidget
{
    private TextWidget $titleWidget;
    private AbstractWidget $body;

    public function __construct(string $title, AbstractWidget $body)
    {
        $this->addStyleClass('card');
        $this->titleWidget = (new TextWidget($title))->addStyleClass('card-title');
        $this->body = $body;
        $this->add($this->titleWidget);
        $this->add($this->body);
    }

    /**
     * Replace the card's title text (e.g. to reflect an updated call count).
     */
    public function setTitle(string $title): void
    {
        $this->titleWidget->setText($title);
    }

    /**
     * Replace the body widget, keeping the card in its transcript position.
     */
    public function setBody(AbstractWidget $body): void
    {
        $this->remove($this->body);
        $this->body = $body;
        $this->add($this->body);
    }
}
