<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\Style;

use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\MarkdownWidget;

/**
 * Maestro's single, opinionated dark stylesheet.
 *
 * Replaces the old theme/slot/Text customization system. There is intentionally
 * no user-facing theming API in v2 — styling lives here and in widget class names.
 */
final class MaestroStyleSheet extends StyleSheet
{
    public function __construct()
    {
        parent::__construct([
            // Screen background + default text color (Tokyo Night-ish palette)
            ':root' => (new Style())
                ->withBackground('#0b0e14')
                ->withColor('#a9b1d6'),

            '.status-bar' => (new Style())
                ->withColor('#7aa2f7')
                ->withBold()
                ->withPadding([0, 1]),

            '.transcript' => (new Style())
                ->withDirection(Direction::Vertical)
                ->withGap(1)
                ->withPadding([0, 1])
                ->withFlex(1),

            '.card' => (new Style())
                ->withDirection(Direction::Vertical)
                ->withGap(0)
                ->withPadding([0, 1])
                ->withBorder([1], 'rounded', '#3b4261'),

            '.card-title' => (new Style())
                ->withBold()
                ->withPadding([0, 0, 0, 0]),

            '.user-message' => (new Style())
                ->withColor('#e0af68')
                ->withPadding([0, 1])
                ->withBorder([1], 'rounded', '#e0af68'),

            '.assistant-message' => (new Style())
                ->withPadding([0, 0]),

            '.tool-call' => (new Style())->withColor('#bb9af7')->withBold(),
            '.tool-result' => (new Style())->withColor('#7dcfff'),
            '.tool-error' => (new Style())->withColor('#f7768e'),

            '.diff-add' => (new Style())->withColor('#9ece6a'),
            '.diff-del' => (new Style())->withColor('#f7768e'),
            '.diff-hunk' => (new Style())->withColor('#565f89'),
            '.diff-meta' => (new Style())->withColor('#7aa2f7')->withBold(),

            '.muted' => (new Style())->withColor('#565f89'),
            '.error' => (new Style())->withColor('#f7768e'),
            '.warning' => (new Style())->withColor('#e0af68'),
            '.success' => (new Style())->withColor('#9ece6a'),
            '.accent' => (new Style())->withColor('#7aa2f7'),

            // Markdown readability on the dark background
            MarkdownWidget::class.'::heading' => (new Style())->withColor('#7aa2f7')->withBold(),
            MarkdownWidget::class.'::code' => (new Style())->withColor('#e0af68'),
            MarkdownWidget::class.'::code-block-border' => (new Style())->withColor('#3b4261'),
            MarkdownWidget::class.'::link' => (new Style())->withColor('#7dcfff')->withUnderline(),
            MarkdownWidget::class.'::link-url' => (new Style())->withColor('#565f89'),
            MarkdownWidget::class.'::quote' => (new Style())->withItalic()->withColor('#9aa5ce'),
            MarkdownWidget::class.'::list-bullet' => (new Style())->withColor('#7dcfff'),
            MarkdownWidget::class.'::hr' => (new Style())->withColor('#3b4261'),
        ]);
    }
}
