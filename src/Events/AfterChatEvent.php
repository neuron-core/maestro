<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Events;

use NeuronCore\Maestro\Agent\MaestroAgent;

/**
 * Dispatched after the agent has processed a message and returned a response.
 *
 * Extensions can use this event to:
 * - Read the full chat history after each interaction
 * - Track context consumption (input + output tokens)
 * - Analyze responses for patterns or metrics
 * - Persist conversation state to external systems
 */
class AfterChatEvent
{
    public function __construct(
        public readonly MaestroAgent $agent,
        public readonly string $userInput,
        public readonly string $responseContent,
    ) {
    }
}
