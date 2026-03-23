<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Events;

use NeuronCore\Maestro\Agent\MaestroAgent;

/**
 * Dispatched when the agent is about to think/process.
 *
 * This event is dispatched before the agent makes an API call to the AI provider.
 */
class AgentThinkingEvent
{
    public function __construct(
        public readonly MaestroAgent $agent,
    ) {
    }
}
