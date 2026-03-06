<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering\Renderers;

use NeuronCore\Synapse\Rendering\ToolRenderer;

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
    public function __construct(private readonly array $keys) {}

    public function render(string $toolName, string $arguments): string
    {
        $args = json_decode($arguments, true) ?? [];

        $parts = [];
        foreach ($this->keys as $key) {
            if (isset($args[$key])) {
                $parts[] = is_string($args[$key]) ? $args[$key] : json_encode($args[$key]);
            }
        }

        $display = $parts ? implode(', ', $parts) : $arguments;

        return sprintf("● %s( %s )\n", $toolName, $display);
    }
}
