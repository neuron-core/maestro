<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Command;

use function str_repeat;

trait CommandHelper
{
    protected function clearOutput(): void
    {
        $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");
    }
}
