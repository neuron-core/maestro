<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Rendering\ToolRenderer;

use function implode;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strlen;
use function mb_substr;
use function array_values;
use function count;

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
                $content = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;
                $parts[$key] = $content;
            }
        }

        $display = '';
        if (count($parts) < 2) {
            $display = Text::content(implode(', ', array_values($parts)))->yellow()->build();
        } else {
            foreach ($parts as $key => $part) {
                $display .= "\n    ".Text::content($key.': ')->blue()->build() . $part;
            }
            $display .= "\n";
        }

        return Text::content("● {$toolName}(")->yellow()->build()
            . $display
            . Text::content(")")->yellow()->build();
    }
}
