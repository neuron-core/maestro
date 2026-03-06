<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering\Renderers;

use NeuronCore\Synapse\Rendering\ToolRenderer;

use function sprintf;

class GenericRenderer implements ToolRenderer
{
    public function render(string $toolName, string $arguments): string
    {
        return sprintf("● %s( %s )\n", $toolName, $arguments);
    }
}
