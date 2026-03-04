# Coding Agent

> AI-powered coding assistant built entirely in PHP with Neuron AI framework.

Coding Agent is a command-line tool that helps developers with software engineering tasks. It runs locally on your machine and provides intelligent assistance for coding, debugging, code reviews, and more using multiple AI providers.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

- **🤖 Multi-Provider Support**: Compatible with Anthropic Claude, OpenAI, Gemini, Cohere, Mistral, Ollama, Grok, and Deepseek
- **📁 Filesystem Integration**: Read, search, and analyze code in any project directory
- **🔌 MCP Support**: Integrate Model Context Protocol servers for extended capabilities
- **💻 CLI-Native**: Built with Minicli for a seamless terminal experience
- **🔧 Context-Aware**: Understands project structure before making suggestions
- **🔒 Secure**: Your code never leaves your local machine (except for AI API calls)
- **🎯 Coding Focus**: Specialized system prompt for software engineering tasks

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

### Global Installation (Recommended)

Install Coding Agent globally on your system to use it from any directory:

```bash
composer global require neuron-core/neuron
```

Make sure Composer's global bin directory is in your PATH:

```bash
# Add this to your shell profile (~/.bashrc, ~/.zshrc, etc.)
export PATH="$HOME/.config/composer/vendor/bin:$PATH"

# Or run this to see your global bin directory
composer global config bin-dir --absolute
```

## Configuration

Before using Coding Agent, you need to configure your AI provider and API key.

### Setting Up `.neuron/settings.json`

Create a `.neuron` directory in your project and add a `settings.json` file:

```bash
mkdir -p .neuron && printf "{\n}" > .neuron/settings.json
```

#### Anthropic (Default)

```json
{
  "provider": "anthropic",
  "anthropic": {
    "api_key": "sk-ant-your-api-key-here",
    "model": "claude-sonnet-4-20250514",
    "max_tokens": 8192
  }
}
```

#### OpenAI

```json
{
  "provider": "openai",
  "openai": {
    "api_key": "sk-your-openai-key-here",
    "model": "gpt-4",
    "max_tokens": 8192
  }
}
```

#### Google Gemini

```json
{
  "provider": "gemini",
  "gemini": {
    "api_key": "your-gemini-api-key",
    "model": "gemini-pro",
    "max_tokens": 8192
  }
}
```

#### Local Ollama

```json
{
  "provider": "ollama",
  "ollama": {
    "base_url": "http://localhost:11434",
    "model": "llama2"
  }
}
```

#### Other Providers

The following providers are also supported with similar configuration:
- **Cohere**: Set `provider: "cohere"` with `cohere.api_key`
- **Mistral**: Set `provider: "mistral"` with `mistral.api_key`
- **Grok (xAI)**: Set `provider: "xai"` with `xai.api_key` (or `grok.api_key`)
- **Deepseek**: Set `provider: "deepseek"` with `deepseek.api_key`

### Global API Key

You can also use a global `api_key` that works with all providers:

```json
{
  "provider": "anthropic",
  "api_key": "sk-ant-your-api-key-here",
  "anthropic": {
    "model": "claude-sonnet-4-20250514"
  }
}
```

### MCP Server Configuration

Add Model Context Protocol servers to extend the agent's capabilities:

```json
{
  "provider": "anthropic",
  "anthropic": {
    "api_key": "sk-ant-your-api-key-here"
  },
  "mcp_servers": {
    "filesystem": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-filesystem", "/path/to/workspace"]
    },
    "brave-search": {
      "command": "uvx",
      "args": ["mcp-brave-search"]
    },
    "github": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-github"],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "your-github-token"
      }
    }
  }
}
```

**Note**: The `.neuron/settings.json` file should be located in your current working directory when running `neuron`.

## Usage

### Basic Chat

Start an interactive chat session:

```bash
neuron
```

### Single Question

Ask a single question:

```bash
neuron "How do I fix this PHP error?"
```

### Working with Projects

Navigate to your project directory and start chatting:

```bash
cd /path/to/your/project
neuron
```

The agent can read and analyze files in your current directory to provide context-aware assistance.

**Example interactions:**

```bash
# Ask about your codebase
> What does this project do?

# Request code review
> Review the UserController.php file for security issues

# Get help debugging
> I'm getting a "Class not found" error in Auth.php

# Request refactoring
> Can you refactor the UserService class to use dependency injection?
```

### Available Commands

```bash
neuron              # Start interactive chat
neuron "text"        # Ask a single question
```

## How It Works

Coding Agent consists of several components:

1. **Neuron AI Framework**: Provides the agent architecture and tool integration
2. **Settings Module**: Loads configuration from `.neuron/settings.json` with support for multiple AI providers
3. **Provider Factory**: Creates provider instances dynamically based on configuration
4. **Minicli**: Handles the CLI interface and command routing

The agent has access to filesystem tools that allow it to:

- List directory contents
- Read files
- Search for patterns in files
- Find files by glob patterns
- Parse documents (PDF, HTML, etc.)

## Supported AI Providers

| Provider | Key Required | Notes |
|----------|---------------|-------|
| Anthropic | `anthropic.api_key` | Default provider, Claude models |
| OpenAI | `openai.api_key` | GPT models |
| Gemini | `gemini.api_key` | Google AI models |
| Cohere | `cohere.api_key` | Command models |
| Mistral | `mistral.api_key` | Mistral AI models |
| Ollama | None required | Local inference |
| Grok | `xai.api_key` or `grok.api_key` | xAI models |
| Deepseek | `deepseek.api_key` | Deepseek models |

## Security

- Your code is processed locally and only sent to your configured AI provider's API
- No code is stored on external servers (except for API request logs by the provider)
- Filesystem tools only read files - no writing or execution without explicit commands
- API keys are stored in your local settings file only

## Development

### Running Tests

```bash
composer test
```

### Code Analysis

```bash
composer analyse  # PHPStan
```

### Code Formatting

```bash
composer format  # PHP CS Fixer + Rector
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Built with:
- [Neuron AI](https://docs.neuron-ai.dev/) - PHP agentic framework
- [Minicli](https://docs.minicli.dev/) - CLI framework

## Support

- 📖 [Documentation](https://docs.neuron-ai.dev/)
- 🐛 [Issue Tracker](https://github.com/neuron-core/coding-agent/issues)
- 💬 [Discussions](https://github.com/neuron-core/coding-agent/discussions)

---

Made with ❤️ by [Inspector](https://inspector.dev)
