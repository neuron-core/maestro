<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Agent;

use function in_array;

/**
 * Mutable, shared tool-approval policy consulted by {@see MaestroToolApproval}
 * and the TUI approval flow.
 *
 * - autoMode: skip approval entirely (no interrupts).
 * - planMode: the TUI auto-rejects every tool that reaches approval (read-only
 *   tools are auto-allowed by the middleware, so only mutating tools reach it).
 * - sessionAllowed: tools the user has approved "for this session".
 * - alwaysAllowed: read-only tools that never prompt.
 */
final class ToolApprovalPolicy
{
    public bool $autoMode = false;
    public bool $planMode = false;

    /** @var array<int, string> */
    public array $sessionAllowed = [];

    /** @var array<int, string> */
    public array $alwaysAllowed;

    /** @var array<int, string> */
    public array $mutatingTools;

    public function __construct()
    {
        $this->alwaysAllowed = ['read_file', 'parse_file', 'glob_path', 'grep_file_content'];
        $this->mutatingTools = ['edit_file', 'write_file', 'delete_file', 'bash'];
    }

    public function allowForSession(string $toolName): void
    {
        if (!in_array($toolName, $this->sessionAllowed, true)) {
            $this->sessionAllowed[] = $toolName;
        }
    }

    public function isAllowed(string $toolName): bool
    {
        return in_array($toolName, $this->alwaysAllowed, true)
            || in_array($toolName, $this->sessionAllowed, true);
    }
}
