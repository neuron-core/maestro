<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering;

use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\StyleName;
use NeuronCore\Maestro\Extension\Ui\ThemeInterface;

use function preg_replace;
use function preg_replace_callback;
use function implode;
use function preg_split;

class MarkdownRenderer
{
    public function __construct(
        protected readonly ThemeInterface $theme,
    ) {
    }

    /**
     * Convert Markdown text to Symfony Console formatted text.
     */
    public function render(string $markdown): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown);

        foreach ($lines as &$line) {
            $line = $this->renderLine($line);
        }

        return implode("\n", $lines);
    }

    /**
     * Render a single line with Markdown support.
     */
    protected function renderLine(string $line): string
    {
        // Headers (keep the marker, add bold)
        $line = preg_replace_callback(
            '/^(#{1,6})\s+(.+)$/u',
            fn (array $matches): string => $this->formatHeader($matches[1], $matches[2]),
            $line
        );

        // Bold **text**
        $line = preg_replace(
            '/\*\*(.+?)\*\*/su',
            $this->wrapStyle(StyleName::BOLD, '$1'),
            (string) $line
        );

        // Italic *text* or _text_
        $line = preg_replace(
            '/(?<!\*)\*([^*]+)\*(?!\*)|(?<!_)_([^_]+)_(?!_)/u',
            $this->wrapStyle(StyleName::UNDERLINE, '$1$2'),
            (string) $line
        );

        // Inline code `text`
        $line = preg_replace(
            '/`([^`]+)`/u',
            $this->wrapColor(ColorName::INFO, '$1'),
            (string) $line
        );

        // Links [text](url)
        $line = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/u',
            fn (array $matches): string => $this->formatLink($matches[1], $matches[2]),
            (string) $line
        );

        return $line;
    }

    /**
     * Format a header line.
     */
    protected function formatHeader(string $marker, string $text): string
    {
        $boldStyle = $this->theme->style(StyleName::BOLD);
        $header = $marker . ' ' . $text;

        if ($boldStyle === '') {
            return $header;
        }

        return "<{$boldStyle}>{$header}</>";
    }

    /**
     * Format a link.
     */
    protected function formatLink(string $text, string $url): string
    {
        $linkStyle = $this->wrapStyle(StyleName::UNDERLINE, $text);
        $mutedColor = $this->theme->color(ColorName::MUTED);

        if ($mutedColor === '') {
            return "{$linkStyle} ({$url})";
        }

        return "{$linkStyle} <fg={$mutedColor}>({$url})</>";
    }

    /**
     * Wrap text in a style tag.
     */
    protected function wrapStyle(StyleName $style, string $text): string
    {
        $styleValue = $this->theme->style($style);

        if ($styleValue === '') {
            return $text;
        }

        return "<{$styleValue}>{$text}</>";
    }

    /**
     * Wrap text in a color tag.
     */
    protected function wrapColor(ColorName $color, string $text): string
    {
        $colorValue = $this->theme->color($color);

        if ($colorValue === '') {
            return $text;
        }

        return "<fg={$colorValue}>{$text}</>";
    }
}
