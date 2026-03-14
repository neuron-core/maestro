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

    /**
     * Get the agent instance for accessing chat history and state.
     */
    public function agent(): MaestroAgent
    {
        return $this->agent;
    }

    /**
     * Get the user input that was sent to the agent.
     */
    public function userInput(): string
    {
        return $this->userInput;
    }

    /**
     * Get the response content returned by the agent.
     */
    public function responseContent(): string
    {
        return $this->responseContent;
    }
}
