<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

interface ToolRenderer
{
    public function render(string $toolName, string $arguments): string;
}
