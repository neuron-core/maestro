<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Rendering\ToolRenderer;

use function implode;
use function is_string;
use function json_decode;
use function json_encode;
use function sprintf;

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
                $parts[] = "<info>{$key}:</info> " . (is_string($args[$key]) ? $args[$key] : json_encode($args[$key]));
            }
        }

        $display = $parts !== [] ? "\n".implode("\n", $parts)."\n" : $arguments;

        return sprintf("● %s( %s )\n", $toolName, $display);
    }
}
