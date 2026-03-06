<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Events;

use NeuronAI\Workflow\Interrupt\ApprovalRequest;

class ToolApprovalRequestedEvent
{
    public function __construct(public readonly ApprovalRequest $approvalRequest) {}
}
