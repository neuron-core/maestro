<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Tui\Widget;

use NeuronCore\Maestro\Tui\Widget\TranscriptView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\TextWidget;

class TranscriptViewTest extends TestCase
{
    public function test_add_card_appends_child(): void
    {
        $view = new TranscriptView();
        $card = (new TextWidget('hello'))->addStyleClass('user-message');

        $view->addCard($card);

        self::assertSame([$card], $view->all());
    }
}
