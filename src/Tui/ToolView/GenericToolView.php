<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\TextWidget;

use function json_encode;

use const JSON_PRETTY_PRINT;

/**
 * Fallback view: shows the tool's textual result, or its inputs as JSON when
 * there is no result.
 */
final class GenericToolView implements ToolView
{
    public function render(string $toolName, array $inputs, string $result): AbstractWidget
    {
        $body = $result;
        if ($body === '' && $inputs !== []) {
            $body = (string) (json_encode($inputs, JSON_PRETTY_PRINT) ?: '');
        }
        if ($body === '') {
            $body = '(no output)';
        }

        return (new TextWidget($body))->addStyleClass('tool-result');
    }
}
