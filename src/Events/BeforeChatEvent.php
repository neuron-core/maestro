<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Events;

use NeuronCore\Maestro\Agent\MaestroAgent;

/**
 * Dispatched before the agent processes a user message.
 *
 * Extensions can use this event to:
 * - Read the chat history before the AI processes the message
 * - Track context usage before each request
 * - Modify behavior based on conversation state
 * - Log or audit incoming messages
 */
class BeforeChatEvent
{
    public function __construct(
        public readonly MaestroAgent $agent,
        public readonly string $userInput,
    ) {
    }

    /**
     * Get the agent instance for accessing chat history and state.
     */
    public function agent(): MaestroAgent
    {
        return $this->agent;
    }

    /**
     * Get the user input that will be sent to the agent.
     */
    public function userInput(): string
    {
        return $this->userInput;
    }
}
