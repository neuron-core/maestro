<?php

namespace NeuronCore\Synapse\Command;

trait CommandHelper
{
    protected function clearOutput(): void
    {
        $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");
    }
}
