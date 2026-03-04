<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\XAI\Grok;
use NeuronAI\Providers\Deepseek\Deepseek;
use RuntimeException;

use function array_keys;
use function implode;
use function is_array;
use function sprintf;
use function strtolower;

/**
 * Factory for creating AI provider instances from settings.
 */
class ProviderFactory implements ProviderFactoryInterface
{
    /**
     * @var array<string, callable>
     */
    private array $factories = [];

    public function __construct()
    {
        $this->registerDefaultFactories();
    }

    /**
     * Register a custom provider factory for a provider type.
     *
     * @param string $provider Provider name (e.g., 'anthropic', 'openai')
     * @param callable $factory Function that receives settings and returns AIProviderInterface
     */
    public function register(string $provider, callable $factory): self
    {
        $this->factories[strtolower($provider)] = $factory;
        return $this;
    }

    /**
     * Create a provider instance based on the settings array.
     *
     * @throws RuntimeException if provider cannot be created
     */
    public function create(array $config): AIProviderInterface
    {
        // Enforce strict format: provider must be a nested object with 'type' key
        if (!isset($config['provider']) || !is_array($config['provider']) || !isset($config['provider']['type'])) {
            throw new RuntimeException(
                'Invalid provider configuration. Expected format: {"provider": {"type": "anthropic", ...}}'
            );
        }

        $type = strtolower((string) $config['provider']['type']);

        if (!isset($this->factories[$type])) {
            throw new RuntimeException(
                sprintf('Unknown provider "%s". Available providers: %s', $type, implode(', ', array_keys($this->factories)))
            );
        }

        // Pass only the provider config to the factory methods
        return ($this->factories[$type])($config['provider']);
    }

    /**
     * Register all default provider factories.
     */
    private function registerDefaultFactories(): void
    {
        $this->factories['anthropic'] = fn (array $settings): \NeuronAI\Providers\Anthropic\Anthropic => $this->createAnthropic($settings);
        $this->factories['openai'] = fn (array $settings): \NeuronAI\Providers\OpenAI\OpenAI => $this->createOpenAI($settings);
        $this->factories['gemini'] = fn (array $settings): \NeuronAI\Providers\Gemini\Gemini => $this->createGemini($settings);
        $this->factories['cohere'] = fn (array $settings): \NeuronAI\Providers\Cohere\Cohere => $this->createCohere($settings);
        $this->factories['mistral'] = fn (array $settings): \NeuronAI\Providers\Mistral\Mistral => $this->createMistral($settings);
        $this->factories['ollama'] = fn (array $settings): \NeuronAI\Providers\Ollama\Ollama => $this->createOllama($settings);
        $this->factories['xai'] = $this->factories['grok'] = fn (array $settings): \NeuronAI\Providers\XAI\Grok => $this->createGrok($settings);
        $this->factories['deepseek'] = fn (array $settings): \NeuronAI\Providers\Deepseek\Deepseek => $this->createDeepseek($settings);
    }

    private function createAnthropic(array $settings): Anthropic
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Anthropic API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        return new Anthropic(
            key: $apiKey,
            model: $settings['model'] ?? 'claude-sonnet-4-20250514',
            max_tokens: $settings['max_tokens'] ?? 8192,
        );
    }

    private function createOpenAI(array $settings): OpenAI
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'OpenAI API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new OpenAI(
            key: $apiKey,
            model: $settings['model'] ?? 'gpt-4',
            parameters: $parameters,
        );
    }

    private function createGemini(array $settings): Gemini
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Gemini API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Gemini(
            key: $apiKey,
            model: $settings['model'] ?? 'gemini-pro',
            parameters: $parameters,
        );
    }

    private function createCohere(array $settings): Cohere
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Cohere API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Cohere(
            key: $apiKey,
            model: $settings['model'] ?? 'command',
            parameters: $parameters,
        );
    }

    private function createMistral(array $settings): Mistral
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Mistral API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Mistral(
            key: $apiKey,
            model: $settings['model'] ?? 'mistral-tiny',
            parameters: $parameters,
        );
    }

    private function createOllama(array $settings): Ollama
    {
        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Ollama(
            url: $settings['base_url'] ?? 'http://localhost:11434',
            model: $settings['model'] ?? 'llama2',
            parameters: $parameters,
        );
    }

    private function createGrok(array $settings): Grok
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'xAI API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Grok(
            key: $apiKey,
            model: $settings['model'] ?? 'grok-beta',
            parameters: $parameters,
        );
    }

    private function createDeepseek(array $settings): Deepseek
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Deepseek API key is not configured. Add "api_key" to provider object in .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Deepseek(
            key: $apiKey,
            model: $settings['model'] ?? 'deepseek-chat',
            parameters: $parameters,
        );
    }
}
