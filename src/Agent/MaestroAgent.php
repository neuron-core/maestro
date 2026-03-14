<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Agent;

use Inspector\Exceptions\InspectorException;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\Middleware\TodoPlanning;
use NeuronAI\Agent\Middleware\ToolApproval;
use NeuronAI\Agent\Nodes\ChatNode;
use NeuronAI\Agent\Nodes\StreamingNode;
use NeuronAI\Agent\Nodes\StructuredOutputNode;
use NeuronAI\Agent\Nodes\ToolNode;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Observability\InspectorObserver;
use NeuronCore\Maestro\Agent\Middleware\MemoryMiddleware;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
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
     * Constructor - Initialize with settings, tool registry, and optional memory registry.
     *
     * @throws WorkflowException|InspectorException
     */
    public function __construct(
        protected SettingsInterface $settings,
        private readonly ToolRegistry $toolRegistry,
        private readonly ?MemoryRegistry $memoryRegistry = null,
    ) {
        parent::__construct();

        $this->observe(InspectorObserver::instance(
            key: $this->settings->get('inspector_key'),
            autoFlush: true,
        ));
    }

    protected function middleware(): array
    {
        $memory = new MemoryMiddleware(
            $this->settings->dirPath() . '/memories',
            $this->memoryRegistry,
        );

        $todo = new TodoPlanning();

        return [
            ChatNode::class => [$memory, $todo],
            StreamingNode::class => [$memory, $todo],
            StructuredOutputNode::class => [$memory, $todo],

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
     * Get tools from registry plus core tools.
     *
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
            ),

            // Load tools from extensions
            ...$this->toolRegistry->list(),
        ];
    }

    /**
     * System prompt that defines the agent's behavior as a CLI assistant.
     *
     * This is a generic base prompt designed for CLI interactions. Extensions
     * can register memory files with detailed instructions for specific use cases
     * (e.g., coding, testing, documentation). These extension memories are injected
     * via MemoryMiddleware and provide domain-specific behavior.
     */
    protected function instructions(): string
    {
        $instructions = <<<PROMPT
# CORE PRINCIPLES

## Professional Communication
- Be direct, concise, and professional in all responses
- Avoid unnecessary conversational fillers ("Sure!", "I'll now...", "Here's the result")
- Focus on the actual work rather than explaining what you're about to do
- Prioritize clarity and accuracy over being verbose

## Autonomy and Investigation
- Use available tools to gather information independently
- Read and understand existing files before making changes
- Ask questions only when truly necessary for clarification

## Tool Usage
- Read files before editing them
- Use grep or search tools to find relevant information
- Follow the project's established patterns and conventions
- Use tools efficiently - plan before executing

# OUTPUT GUIDELINES
- Keep responses concise and action-oriented
- Avoid emojis in professional CLI interactions
- Provide summaries only when context requires them
- Focus on the solution, not the process

# EXTENSION-BASED INSTRUCTIONS
This agent supports extension-based instruction sets. Extensions can register
memory files with detailed domain-specific instructions (coding, testing, etc.).
These instructions are automatically injected into your context and should be
followed alongside these core principles.
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
