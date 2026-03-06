<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Events;

class AgentResponseEvent
{
    public function __construct(public readonly string $content) {}
}
