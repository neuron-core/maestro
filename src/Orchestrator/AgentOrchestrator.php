<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Orchestrator;

use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\Events\AfterChatEvent;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentThinkingEvent;
use NeuronCore\Maestro\Events\BeforeChatEvent;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function assert;

class AgentOrchestrator
{
    public function __construct(
        private readonly MaestroAgent $agent,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function chat(string $input): void
    {
        $this->dispatcher->dispatch(new BeforeChatEvent($this->agent, $input));
        $this->dispatcher->dispatch(new AgentThinkingEvent($this->agent));

        try {
            $response = $this->agent->chat(new UserMessage($input))->getMessage();
            $content = $response->getContent() ?? '';
            $this->dispatcher->dispatch(new AfterChatEvent($this->agent, $input, $content));
            $this->dispatcher->dispatch(new AgentResponseEvent($content));
        } catch (WorkflowInterrupt $interrupt) {
            $this->handleInterrupt($interrupt, $input);
        }
    }

    /**
     * @throws Throwable
     */
    private function handleInterrupt(WorkflowInterrupt $interrupt, string $input): void
    {
        $approvalRequest = $interrupt->getRequest();
        assert($approvalRequest instanceof ApprovalRequest);

        $this->dispatcher->dispatch(new ToolApprovalRequestedEvent($approvalRequest));

        $this->dispatcher->dispatch(new AgentThinkingEvent($this->agent));

        try {
            $response = $this->agent->chat(interrupt: $approvalRequest)->getMessage();
            $content = $response->getContent() ?? '';
            $this->dispatcher->dispatch(new AfterChatEvent($this->agent, $input, $content));
            $this->dispatcher->dispatch(new AgentResponseEvent($content));
        } catch (WorkflowInterrupt $nested) {
            $this->handleInterrupt($nested, $input);
        }
    }
}
