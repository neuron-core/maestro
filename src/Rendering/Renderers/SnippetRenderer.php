<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Console\Color;
use NeuronCore\Maestro\Rendering\ToolRenderer;

use function implode;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;
use function mb_strlen;
use function mb_substr;

class SnippetRenderer implements ToolRenderer
{
    /**
     * @param string[] $keys Argument keys to extract and display, in order
     */
    public function __construct(protected readonly array $keys)
    {
    }

    public function render(string $toolName, string $arguments): string
    {
        $args = json_decode($arguments, true) ?? [];

        $parts = [];
        foreach ($this->keys as $key) {
            if (isset($args[$key])) {
                $content = is_string($args[$key]) ? $args[$key] : json_encode($args[$key]);
                $content = mb_strlen($content) > 150 ? mb_substr($content, 0, 150) . '...' : $content;
                $parts[] = Color::cyan("{$key}:").' '.Color::gray($content);
            }
        }

        $display = $parts !== [] ? "\n ".implode("\n ", $parts)."\n" : $arguments;

        return sprintf("● %s(%s)\n", $toolName, $display);
    }
}
