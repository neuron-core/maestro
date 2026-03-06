<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Orchestrator;

use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronCore\Synapse\Agent\CodingAgent;
use NeuronCore\Synapse\Events\AgentResponseEvent;
use NeuronCore\Synapse\Events\AgentThinkingEvent;
use NeuronCore\Synapse\Events\ToolApprovalRequestedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function assert;

class AgentOrchestrator
{
    public function __construct(
        private readonly CodingAgent $agent,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function chat(string $input): void
    {
        $this->dispatcher->dispatch(new AgentThinkingEvent());

        try {
            $response = $this->agent->chat(new UserMessage($input))->getMessage();
            $this->dispatcher->dispatch(new AgentResponseEvent($response->getContent() ?? ''));
        } catch (WorkflowInterrupt $interrupt) {
            $this->handleInterrupt($interrupt);
        }
    }

    /**
     * @throws Throwable
     */
    private function handleInterrupt(WorkflowInterrupt $interrupt): void
    {
        $approvalRequest = $interrupt->getRequest();
        assert($approvalRequest instanceof ApprovalRequest);

        $this->dispatcher->dispatch(new ToolApprovalRequestedEvent($approvalRequest));

        $this->dispatcher->dispatch(new AgentThinkingEvent());

        try {
            $response = $this->agent->chat(interrupt: $approvalRequest)->getMessage();
            $this->dispatcher->dispatch(new AgentResponseEvent($response->getContent() ?? ''));
        } catch (WorkflowInterrupt $nested) {
            $this->handleInterrupt($nested);
        }
    }
}
