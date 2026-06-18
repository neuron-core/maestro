<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Agent;

use NeuronAI\Agent\Middleware\ToolApproval;
use NeuronAI\Tools\ToolInterface;

/**
 * ToolApproval middleware driven by a shared {@see ToolApprovalPolicy}.
 *
 * Honors auto-mode (skip all approval) and the always/session allowlists. Plan
 * mode is handled by the TUI (it auto-rejects tools that reach approval); the
 * middleware filter is identical for normal and plan mode.
 */
final class MaestroToolApproval extends ToolApproval
{
    public function __construct(
        private readonly ToolApprovalPolicy $policy,
    ) {
        parent::__construct();
    }

    /**
     * @param ToolInterface[] $tools
     *
     * @return ToolInterface[]
     */
    protected function filterToolsRequiringApproval(array $tools): array
    {
        if ($this->policy->autoMode) {
            return [];
        }

        $need = [];
        foreach ($tools as $tool) {
            if ($this->policy->isAllowed($tool->getName())) {
                continue;
            }
            $need[] = $tool;
        }

        return $need;
    }
}
