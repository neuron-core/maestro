<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Orchestrator;

use NeuronAI\Agent\AgentHandler;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\Events\AfterChatEvent;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentStreamChunkEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\BeforeChatEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Events\ToolExecutedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

use function assert;

/**
 * Drives a chat turn over the agent and translates it into Maestro events.
 *
 * Uses the agent's streaming mode so text chunks are dispatched as they arrive
 * (see AgentStreamChunkEvent). Tool approval pauses the turn via WorkflowInterrupt;
 * we resume in streaming mode — Agent::compose() is idempotent after first
 * bootstrap, so the StreamingNode survives the interrupt/resume round-trip.
 */
class AgentOrchestrator
{
    public function __construct(
        private readonly MaestroAgent $agent,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Run one chat turn for the given user input.
     */
    public function chat(string $input): void
    {
        $this->dispatcher->dispatch(new BeforeChatEvent($this->agent, $input));
        $this->dispatcher->dispatch(new AgentThinkingEvent($this->agent));

        $this->runStream($this->agent->stream(new UserMessage($input)), $input);
    }

    private function runStream(AgentHandler $handler, string $input): void
    {
        try {
            foreach ($handler->events() as $event) {
                if ($event instanceof TextChunk) {
                    $this->dispatcher->dispatch(new AgentStreamChunkEvent($event->content));
                } elseif ($event instanceof ToolResultChunk) {
                    $tool = $event->tool;
                    $this->dispatcher->dispatch(new ToolExecutedEvent(
                        $tool->getName(),
                        $tool->getInputs(),
                        $tool->getResult(),
                    ));
                }
            }

            $content = $handler->getMessage()->getContent() ?? '';

            $this->dispatcher->dispatch(new AfterChatEvent($this->agent, $input, $content));
            $this->dispatcher->dispatch(new AgentResponseEvent($content));
        } catch (WorkflowInterrupt $interrupt) {
            $this->handleInterrupt($interrupt, $input);
        }
    }

    private function handleInterrupt(WorkflowInterrupt $interrupt, string $input): void
    {
        $approvalRequest = $interrupt->getRequest();
        assert($approvalRequest instanceof ApprovalRequest);

        // The listener (TUI presenter) collects the user's decision and mutates
        // the ApprovalRequest actions before returning; control then resumes here.
        $this->dispatcher->dispatch(new ToolApprovalRequestedEvent($approvalRequest));

        $this->dispatcher->dispatch(new AgentThinkingEvent($this->agent));

        $this->runStream($this->agent->stream(interrupt: $approvalRequest), $input);
    }
}
