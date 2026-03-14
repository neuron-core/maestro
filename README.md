# Maestro - The First PHP-Based AI Coding Agent

**Maestro** is the first coding agent built entirely in PHP with the [Neuron AI framework](https://docs.neuron-ai.dev).
It brings powerful AI-assisted development to the PHP ecosystem through an elegant CLI tool that combines intelligent code analysis
with interactive tool approval.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

[![](assets/maestro-agent-clip.gif)](https://neuron-ai.dev)

## About Maestro

While most AI coding agents are written in Python or TypeScript, Maestro demonstrates that PHP can deliver a world-class AI coding experience. Built on the modern [Neuron AI framework](https://docs.neuron-ai.dev), Maestro provides:

- **Native PHP Architecture**: Every component—agent orchestration, CLI interface, event system—is implemented in PHP
- **Tool Approval System**: Interactive confirmation before the agent executes filesystem operations
- **Multi-Provider AI Support**: Choose from Anthropic Claude, OpenAI, Gemini, Cohere, Mistral, Ollama, Grok, or Deepseek
- **MCP Integration**: Extend capabilities with Model Context Protocol servers
- **Sophisticated Output Rendering**: Beautiful diffs, colored syntax highlighting, and intuitive tool call visualization
- **Customizable with Extensions**: Powerful extension system that allows you to customize the agent by adding custom tools, inline commands, and more

## Full Introduction

For a full introduction to the project architecture, you can read the article below:

https://inspector.dev/building-a-coding-agent-in-php-a-walk-through-maestro/

## Requirements

- PHP >= 8.1
- Composer - [Install Composer](https://getcomposer.org/download/)

## Installation

If you are on a Windows machine, you should install and run Maestro in a WSL environment.

Install Maestro globally to use it in any project:

```bash
composer global require neuron-core/maestro
```

Ensure Composer's global bin directory is in your shell profile. Run `echo $0` to find the current shell.

**bash**

```bash
echo 'export PATH="$(composer config -g home)/vendor/bin:$PATH"' >> ~/.bashrc
```

**zsh**

```bash
echo 'export PATH="$(composer config -g home)/vendor/bin:$PATH"' >> ~/.zshrc
```

## Future Updates

To keep the tool up to date, run the global update:

```bash
composer global update
```

## Configuration

Before using Maestro, you need to configure your AI provider and API key.
Navigate to the project directory you want to use Maestro in and run the command below:

```bash
maestro init
```

It will start an interactive setup wizard that guides you through the configuration process.

#### Anthropic

```json
{
    "default": "anthropic",
    "providers": {
        "anthropic": {
            "api_key": "sk-ant-your-api-key-here",
            "model": "claude-sonnet-4-6"
        }
    }
}
```

#### Ollama

```json
{
    "default": "ollama",
    "providers": {
        "ollama": {
            "base_url": "http://localhost:11434",
            "model": "llama2"
        }
    }
}
```

For all supported providers you can check out the Neuron AI documentation: https://docs.neuron-ai.dev/providers/ai-provider

### Context File Configuration

You can provide project-specific instructions by adding a `context_file` setting. The agent will load this file and append its content to its system instructions.

If no `context_file` is specified, the agent will look for `Agents.md` in the project root. If the file doesn't exist, no additional context is attached.

```json
{
    "providers": {
        ...
    },
    "context_file": "CLAUDE.md"
}
```

### MCP Servers

Add Model Context Protocol servers to extend the agent's capabilities:

```json
{
    "providers": {
        ...
    },
    "mcp_servers": {
        "tavily": {
            "url": "https://mcp.tavily.com/mcp/?tavilyApiKey=<your-api-key>"
        }
    }
}
```

**Note**: The `.maestro/settings.json` file should be located in your current working directory when running `maestro`.

## Usage

Start an interactive chat session:

```bash
# Mavigate to the project directory 
cd path/project 

# Start the maestro CLI
maestro
```

### Tool Approval

When the agent proposes a tool operation, you'll be prompted to approve it. The human-in-the-loop system is powered by the
Neuron AI [Tool Approval](https://docs.neuron-ai.dev/agent/middleware#tool-approval-human-in-the-loop) middleware.

You can Choose from:

- **Allow once**: Approve this specific operation
- **Allow for session**: Approve all operations of this type during the current session
- **Deny**: Reject this operation

## Monitoring Maestro sessions

Neuron AI is natively integrated with [Inspector](https://inspector.dev), allowing you to monitor and analyze your AI coding sessions.
To enable agent monitoring you just need to add the `inspector_key` field to your `.maestro/settings.json` file:

```json
{
    "providers": {
        ...
    },
    "inspector_key": "INSPECTOR_INGESTION_KEY"
}
```

You can get an `INSPECTOR_INGESTION_KEY` from the [Inspector dashboard](https://app.inspector.dev/register).

## Creating Extensions

Maestro supports a powerful extension system that allows you to customize the agent by adding custom tools, inline commands, renderers, event handlers, memory files, and UI customizations.

Create an extension class that implements `ExtensionInterface`:

```php
class MyExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'my-extension';
    }

    public function register(ExtensionApi $api): void
    {
        // Register tools, commands, renderers, events, etc.
        $api->registerTool($myTool);
        $api->registerCommand($myCommand);
        $api->registerRenderer('my_tool', $myRenderer);
    }
}
```

For a comprehensive guide on building extensions, including auto-discovery, UI customization, packaging, and all available APIs, see the **[Extension README](src/Extension/README.md)**.

This repository also includes the [skills](./skills) directory to provide detailed instructions to AI coding assistants for extension development.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the FSL License - see the [LICENSE](LICENSE) file for details.

## Credits

Built with:
- [Neuron AI](https://docs.neuron-ai.dev/) - PHP agentic framework
- [Symfony Console](https://symfony.com/doc/current/console.html) - CLI component

---

Made with ❤️ by [Inspector](https://inspector.dev) team
