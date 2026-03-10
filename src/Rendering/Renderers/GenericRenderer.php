<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Rendering\ToolRenderer;

use function sprintf;

class GenericRenderer implements ToolRenderer
{
    public function render(string $toolName, string $arguments): string
    {
        return Text::content(sprintf("● %s(%s)\n", $toolName, $arguments))->yellow()->build();
    }
}
