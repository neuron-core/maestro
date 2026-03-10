<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Agent;

use Inspector\Exceptions\InspectorException;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\Middleware\ToolApproval;
use NeuronAI\Agent\Nodes\ChatNode;
use NeuronAI\Agent\Nodes\StreamingNode;
use NeuronAI\Agent\Nodes\StructuredOutputNode;
use NeuronAI\Agent\Nodes\ToolNode;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Observability\InspectorObserver;
use NeuronCore\Maestro\Agent\Middleware\MemoryMiddleware;
use NeuronCore\Maestro\Settings\SettingsInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\Toolkits\FileSystem\FileSystemToolkit;
use Exception;

use function array_reduce;
use function file_get_contents;
use function trim;

/**
 * An AI-powered coding assistant using the Neuron AI framework.
 *
 * https://neuron-ai.dev
 *
 * This agent is designed to help with software engineering tasks in the CLI environment.
 * It has access to filesystem tools to read, search, and analyze code.
 *
 * @method static static make(SettingsInterface $settings)
 */
class MaestroAgent extends Agent
{
    /**
     * Constructor - Initialize with settings loader.
     *
     * @throws WorkflowException|InspectorException
     */
    public function __construct(protected SettingsInterface $settings)
    {
        parent::__construct();

        $this->observe(InspectorObserver::instance(
            key: $this->settings->get('inspector_key'),
            autoFlush: true,
        ));
    }

    protected function middleware(): array
    {
        $memory = new MemoryMiddleware(
            $this->settings->dirPath() . '/memories'
        );

        return [
            ChatNode::class => [$memory],
            StreamingNode::class => [$memory],
            StructuredOutputNode::class => [$memory],

            ToolNode::class => [
                new ToolApproval()
            ],
        ];
    }

    /**
     * Get the settings instance.
     */
    public function settings(): SettingsInterface
    {
        return $this->settings;
    }

    protected function provider(): AIProviderInterface
    {
        return $this->settings->provider();
    }

    /**
     * @throws Exception
     */
    protected function tools(): array
    {
        return [
            FileSystemToolkit::make(),

            // Load tools from MCP servers
            ...array_reduce(
                $this->settings->mcpServers(),
                fn (array $carry, McpConnector $connector): array => [
                    ...$carry,
                    ...$connector->tools(),
                ],
                [],
            )
        ];
    }

    /**
     * System prompt that defines the agent's behavior as a coding assistant.
     */
    protected function instructions(): string
    {
        $instructions = <<<PROMPT
You are a Deep Agent, an AI assistant that helps users accomplish tasks using tools. You respond with text and tool calls. The user can see your responses and tool outputs in real time.

## Core Behavior

- Be concise and direct. Don't over-explain unless asked.
- NEVER add unnecessary preamble ("Sure!", "Great question!", "I'll now...").
- Don't say "I'll now do X" — just do it.
- If the request is ambiguous, ask questions before acting.
- If asked how to approach something, explain first, then act.

## Professional Objectivity

- Prioritize accuracy over validating the user's beliefs
- Disagree respectfully when the user is incorrect
- Avoid unnecessary superlatives, praise, or emotional validation

## Following Conventions

- Read files before editing — understand existing content before making changes
- Mimic existing style, naming conventions, and patterns

## Doing Tasks

When the user asks you to do something:

1. **Understand first** — read relevant files, check existing patterns. Quick but thorough — gather enough evidence to start, then iterate.
2. **Act** — implement the solution. Work quickly but accurately.
3. **Verify** — check your work against what was asked, not against your own output. Your first attempt is rarely correct — iterate.

Keep working until the task is fully complete. Don't stop partway and explain what you would do — just do it. Only yield back to the user when the task is done or you're genuinely blocked.

**When things go wrong:**
- If something fails repeatedly, stop and analyze *why* — don't keep retrying the same approach.
- If you're blocked, tell the user what's wrong and ask for guidance.

## Tool Usage

- Use specialized tools over shell equivalents when available (e.g., `read_file` over `cat`, `edit_file` over `sed`)
- When performing multiple independent operations, make all tool calls in a single response — don't make sequential calls when parallel is possible.

## File Reading Best Practices

When reading multiple files or exploring large files, use pagination to prevent context overflow.
- Start with `read_file(path, limit=100)` to scan structure
- Read targeted sections with offset/limit
- Only read full files when necessary for editing

## Progress Updates

For longer tasks, provide brief progress updates at reasonable intervals — a concise sentence recapping what you've done and what's next.
PROMPT;

        // Append project-specific instructions from Agents.md (or custom context file) if it exists in the settings file
        $agentFile = $this->settings->getAgentInstructionsFile();
        if ($agentFile !== null) {
            $agentInstructions = file_get_contents($agentFile);
            if ($agentInstructions !== false) {
                $instructions .= "\n\n---\n\n## Project-Specific Guidelines\n\n" . trim($agentInstructions);
            }
        }

        return $instructions;
    }
}
