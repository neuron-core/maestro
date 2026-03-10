<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Agent\Middleware;

use NeuronAI\Agent\Events\AIInferenceEvent;
use NeuronAI\Workflow\Events\Event;
use NeuronAI\Workflow\Middleware\WorkflowMiddleware;
use NeuronAI\Workflow\NodeInterface;
use NeuronAI\Workflow\WorkflowState;

use function array_keys;
use function file_get_contents;
use function glob;
use function implode;
use function is_dir;
use function is_file;
use function rtrim;
use function sort;
use function basename;
use function is_readable;

/**
 * Memory Middleware for loading and injecting agent memory into system prompts.
 *
 * This middleware loads memory files from the .maestro/memories directory
 * and injects them into the AI instructions. This enables the agent to
 * remember context across sessions and improve behavior over time.
 *
 * Inspired by LangChain's MemoryMiddleware from the deepagents SDK.
 */
class MemoryMiddleware implements WorkflowMiddleware
{
    protected string $memoriesDir;

    /**
     * @var array<string, string>|null Cache of loaded memories
     */
    protected ?array $cachedMemories = null;

    /**
     * Create a new MemoryMiddleware instance.
     *
     * @param string $memoriesDir Path to the directory containing memory files
     */
    public function __construct(string $memoriesDir)
    {
        $this->memoriesDir = rtrim($memoriesDir, '/');
    }

    /**
     * Get the path to the memories directory.
     */
    public function getMemoriesDir(): string
    {
        return $this->memoriesDir;
    }

    /**
     * Load all memory files from the memories directory.
     *
     * @return array<string, string> Associative array of filename => content
     */
    protected function loadMemories(): array
    {
        if ($this->cachedMemories !== null) {
            return $this->cachedMemories;
        }

        $memories = [];

        if (!is_dir($this->memoriesDir)) {
            $this->cachedMemories = [];
            return [];
        }

        // Get all .md files in the memories directory
        $files = glob($this->memoriesDir . '/*.md');

        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $filename = basename($file);
                    $memories[$filename] = $content;
                }
            }
        }

        $this->cachedMemories = $memories;

        return $memories;
    }

    /**
     * Format memories for injection into the system prompt.
     *
     * @param array<string, string> $memories The loaded memories
     * @return string Formatted memory section
     */
    protected function formatMemories(array $memories): string
    {
        if ($memories === []) {
            return $this->getMemorySystemPrompt('(No memories loaded yet)');
        }

        // Sort keys for consistent ordering
        $filenames = array_keys($memories);
        sort($filenames);

        $sections = [];
        foreach ($filenames as $filename) {
            $sections[] = "### {$filename}\n{$memories[$filename]}";
        }

        $memoryBody = implode("\n\n", $sections);

        return $this->getMemorySystemPrompt($memoryBody);
    }

    /**
     * Get the memory system prompt with guidelines for the agent.
     *
     * @param string $agentMemory The actual memory content
     * @return string Complete system prompt for memory
     */
    protected function getMemorySystemPrompt(string $agentMemory): string
    {
        return <<<PROMPT

---

<agent_memory>
{$agentMemory}
</agent_memory>

<memory_guidelines>
The above <agent_memory> was loaded from files in the .maestro/memories directory.
As you learn from interactions with the user, you can save new knowledge by editing
memory files using the edit_file tool.

**Learning from feedback:**
- One of your MAIN PRIORITIES is to learn from your interactions. These learnings can be implicit or explicit.
- When you need to remember something, updating memory must be your FIRST, IMMEDIATE action - before responding to the user, before calling other tools.
- When user says something is better/worse, capture WHY and encode it as a pattern.
- Each correction is a chance to improve permanently - don't just fix the immediate issue, update your instructions.
- A great opportunity to update memories is when the user interrupts a tool call and provides feedback.
- Look for the underlying principle behind corrections, not just the specific mistake.

**Asking for information:**
- If you lack context to perform an action (e.g., send a Slack DM, requires a user ID/email) ask the user explicitly.
- When the user provides information useful for future use, update your memories immediately.

**When to update memories:**
- When the user explicitly asks you to remember something (e.g., "remember my email", "save this preference")
- When the user describes your role or how you should behave
- When the user gives feedback on your work - capture what was wrong and how to improve
- When the user provides information required for tool use (e.g., slack channel ID, email addresses)
- When the user provides context useful for future tasks (how to use tools, actions in certain situations)
- When you discover new patterns or preferences (coding styles, conventions, workflows)

**When to NOT update memories:**
- When the information is temporary or transient (e.g., "I'm running late", "I'm on my phone")
- When the information is a one-time task request (e.g., "Find me a recipe", "What's 25 * 4?")
- When the information is a simple question that doesn't reveal lasting preferences
- When the information is an acknowledgment or small talk
- When the information is stale or irrelevant in future conversations
- Never store API keys, access tokens, passwords, or any other credentials in memory files.

**Memory file locations:**
- Memory files are located in: .maestro/memories/
- Edit existing memories using: edit_file(".maestro/memories/FILENAME.md", "SEARCH_TEXT", "REPLACE_TEXT")
- Create new memory files using: write_file(".maestro/memories/FILENAME.md", "CONTENT")
- Recommended file names:
  - context.md - for general project context and guidelines
  - preferences.md - for user preferences and behavioral patterns
  - patterns.md - for recurring patterns and best practices
  - technical-notes.md - for technical knowledge and reference information
</memory_guidelines>
PROMPT;
    }

    /**
     * Inject memory into the AI inference event before the node executes.
     *
     * @param NodeInterface $node The node being executed
     * @param Event $event The event being processed
     * @param WorkflowState $state The current workflow state
     */
    public function before(NodeInterface $node, Event $event, WorkflowState $state): void
    {
        if (!$event instanceof AIInferenceEvent) {
            return;
        }

        // Load memories
        $memories = $this->loadMemories();

        // Format and append to instructions
        $memorySection = $this->formatMemories($memories);

        // Append memory to instructions
        $event->instructions .= $memorySection;
    }

    /**
     * Cleanup after the node executes.
     *
     * @param NodeInterface $node The node that executed
     * @param Event $result The result event
     * @param WorkflowState $state The current workflow state
     */
    public function after(NodeInterface $node, Event $result, WorkflowState $state): void
    {
        // Invalidate cache after node execution to pick up any changes
        $this->cachedMemories = null;
    }
}
