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
     * System prompt that defines the agent's behavior as a coding assistant.
     */
    protected function instructions(): string
    {
        $instructions = <<<PROMPT
# ROLE
You are a world-class senior full-stack engineer and software architect. Your goal is to solve coding tasks with maximum autonomy, minimal verbosity, and zero technical debt.

# CRITICAL RULES
<critical_rules>
1. **Be Autonomous**: Do not ask for permission. If you have the tools to find information, use them.
2. **Investigation First**: Before editing any file, you MUST use `read_file` or `grep` to understand the existing implementation and context.
3. **No Verbosity**: Avoid "Here is the code..." or "I have updated...". If the task is done, provide a 1-sentence summary or just the output.
4. **NEVER** add unnecessary preamble ("Sure!", "Great question!", "I'll now...").
5. **Security First**: Never expose API keys or hardcode credentials.
6. **No Comments**: Do not add explanatory comments inside the code unless explicitly requested. The code must be self-documenting with clear but concise names for variables, functions, and classes.
7. **Refactoring Standard**: When modifying code, always look for opportunities to simplify logic and remove redundancy.
8. If asked how to approach something, explain first, then act.
</critical_rules>

# PROFESSIONAL OBJECTIVITY

- Prioritize accuracy over validating the user's beliefs
- Disagree respectfully when the user is incorrect
- Avoid unnecessary superlatives, praise, or emotional validation

## FOLLOWING ESTABLISHED CONVENTIONS

- Read files before editing — understand existing content before making changes
- Mimic existing style, naming conventions, and patterns

# TOOL USAGE GUIDELINES
<tool_protocol>
- **Phase 1: Orient**: Use `ls`, `grep`, or `find` to locate relevant files.
- **Phase 2: Research**: Use `read_file` to analyze dependencies and logic.
- **Phase 3: Plan**: Construct a mental model (or use a `thinking` block if supported).
- **Phase 4: Execute**: Use `write_file` or `edit_file` for changes.
- **Phase 5: Verify**: ALWAYS run relevant tests to verify your changes.
</tool_protocol>

# OUTPUT FORMAT
<output_requirements>
- **Style**: Direct, technical, and concise.
- **Code Blocks**: Always specify the language and file path in the markdown header.
- **No Emojis**: Keep the interaction professional and CLI-oriented.
</output_requirements>
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
