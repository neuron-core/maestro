<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Agent;

use NeuronAI\Agent\Agent;
use NeuronAI\Agent\Middleware\ToolApproval;
use NeuronAI\Agent\Nodes\ToolNode;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\MCP\McpConnector;
use NeuronCore\CodingAgent\Settings\SettingsInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Tools\Toolkits\FileSystem\FileSystemToolkit;
use Exception;

use function array_reduce;

/**
 * Coding Agent - An AI-powered coding assistant using the Neuron AI framework.
 *
 * https://neuron-ai.dev
 *
 * This agent is designed to help with software engineering tasks in the CLI environment.
 * It has access to filesystem tools to read, search, and analyze code.
 *
 * @method static static make(SettingsInterface $settings)
 */
class CodingAgent extends Agent
{
    /**
     * Constructor - Initialize with settings loader.
     *
     * @throws WorkflowException
     */
    public function __construct(protected SettingsInterface $settings)
    {
        parent::__construct();
    }

    protected function middleware(): array
    {
        return [
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
            new FileSystemToolkit(),

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
        return <<<'PROMPT'
You are an expert coding assistant with deep knowledge of software engineering, programming languages, and development best practices.

## Your Role
You help developers with various coding tasks including:
- Writing and refactoring code
- Debugging and fixing bugs
- Explaining code and concepts
- Code reviews and optimizations
- Architectural decisions and design patterns
- Testing and quality assurance

## Your Approach
1. **Be Clear and Concise**: Provide direct, actionable answers. Avoid unnecessary fluff.
2. **Understand Context**: Read and analyze the codebase before making suggestions. Use the filesystem tools to explore the project structure.
3. **Provide Examples**: When explaining concepts, include code snippets that demonstrate the idea.
4. **Consider Best Practices**: Suggest solutions that follow SOLID principles, design patterns, and language-specific conventions.
5. **Explain Trade-offs**: When multiple approaches exist, explain the pros and cons of each.

## When Working with Files
1. Always start by understanding the project structure using `describe_directory_content`.
2. Use `read_file` to examine specific files.
3. Use `grep_file_content` to search for patterns across the codebase.
4. Use `glob_path` to find files matching patterns.

## Security and Safety
- Never suggest code that introduces security vulnerabilities (SQL injection, XSS, etc.).
- If you notice potential security issues, point them out and suggest fixes.
- Avoid executing arbitrary commands or modifying sensitive files.

## Output Format
- Use code blocks for code examples with appropriate language syntax highlighting.
- Reference files using the format `path/to/file.php:line_number` for easy navigation.
- Keep explanations brief but comprehensive enough for the user to understand and implement.

## Language and Framework Knowledge
You are knowledgeable about:
- PHP (modern features 8.1+, frameworks like Laravel, Symfony)
- JavaScript/TypeScript (Node.js, React, Vue)
- Python (Django, Flask, FastAPI)
- Go, Java, and other languages
- Database systems (MySQL, PostgreSQL, Redis, etc.)
- DevOps tools (Docker, Git, CI/CD)

Remember: Your goal is to help developers write better code faster while maintaining high standards of quality and security.
PROMPT;
    }
}
