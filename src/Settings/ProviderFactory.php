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
     * @throws \RuntimeException if provider cannot be created
     */
    public function create(array $settings): AIProviderInterface
    {
        $provider = $settings['provider'] ?? 'anthropic';
        $providerKey = strtolower($provider);

        if (!isset($this->factories[$providerKey])) {
            throw new \RuntimeException(
                sprintf('Unknown provider "%s". Available providers: %s', $provider, implode(', ', array_keys($this->factories)))
            );
        }

        return ($this->factories[$providerKey])($settings);
    }

    /**
     * Register all default provider factories.
     */
    private function registerDefaultFactories(): void
    {
        $this->factories['anthropic'] = fn(array $settings) => $this->createAnthropic($settings);
        $this->factories['openai'] = fn(array $settings) => $this->createOpenAI($settings);
        $this->factories['gemini'] = fn(array $settings) => $this->createGemini($settings);
        $this->factories['cohere'] = fn(array $settings) => $this->createCohere($settings);
        $this->factories['mistral'] = fn(array $settings) => $this->createMistral($settings);
        $this->factories['ollama'] = fn(array $settings) => $this->createOllama($settings);
        $this->factories['xai'] = $this->factories['grok'] = fn(array $settings) => $this->createGrok($settings);
        $this->factories['deepseek'] = fn(array $settings) => $this->createDeepseek($settings);
    }

    private function createAnthropic(array $settings): Anthropic
    {
        $apiKey = $settings['anthropic']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'Anthropic API key is not configured. Add "anthropic.api_key" or "api_key" to .neuron/settings.json.'
            );

        return new Anthropic(
            key: $apiKey,
            model: $settings['anthropic']['model'] ?? $settings['model'] ?? 'claude-sonnet-4-20250514',
            max_tokens: $settings['anthropic']['max_tokens'] ?? 8192,
        );
    }

    private function createOpenAI(array $settings): OpenAI
    {
        $apiKey = $settings['openai']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'OpenAI API key is not configured. Add "openai.api_key" or "api_key" to .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['openai']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['openai']['max_tokens'];
        }

        return new OpenAI(
            key: $apiKey,
            model: $settings['openai']['model'] ?? $settings['model'] ?? 'gpt-4',
            parameters: $parameters,
        );
    }

    private function createGemini(array $settings): Gemini
    {
        $apiKey = $settings['gemini']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'Gemini API key is not configured. Add "gemini.api_key" or "api_key" to .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['gemini']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['gemini']['max_tokens'];
        }

        return new Gemini(
            key: $apiKey,
            model: $settings['gemini']['model'] ?? $settings['model'] ?? 'gemini-pro',
            parameters: $parameters,
        );
    }

    private function createCohere(array $settings): Cohere
    {
        $apiKey = $settings['cohere']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'Cohere API key is not configured. Add "cohere.api_key" or "api_key" to .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['cohere']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['cohere']['max_tokens'];
        }

        return new Cohere(
            key: $apiKey,
            model: $settings['cohere']['model'] ?? $settings['model'] ?? 'command',
            parameters: $parameters,
        );
    }

    private function createMistral(array $settings): Mistral
    {
        $apiKey = $settings['mistral']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'Mistral API key is not configured. Add "mistral.api_key" or "api_key" to .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['mistral']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['mistral']['max_tokens'];
        }

        return new Mistral(
            key: $apiKey,
            model: $settings['mistral']['model'] ?? $settings['model'] ?? 'mistral-tiny',
            parameters: $parameters,
        );
    }

    private function createOllama(array $settings): Ollama
    {
        $parameters = [];
        if (isset($settings['ollama']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['ollama']['max_tokens'];
        }

        return new Ollama(
            url: $settings['ollama']['base_url'] ?? 'http://localhost:11434',
            model: $settings['ollama']['model'] ?? $settings['model'] ?? 'llama2',
            parameters: $parameters,
        );
    }

    private function createGrok(array $settings): Grok
    {
        $apiKey = $settings['xai']['api_key'] ?? $settings['grok']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'xAI API key is not configured. Add "xai.api_key", "grok.api_key", or "api_key" to .neuron/settings.json.'
            );

        $maxTokensKey = $settings['xai']['max_tokens'] ?? $settings['grok']['max_tokens'] ?? null;
        $parameters = [];
        if ($maxTokensKey !== null) {
            $parameters['max_tokens'] = $maxTokensKey;
        }

        return new Grok(
            key: $apiKey,
            model: $settings['xai']['model'] ?? $settings['grok']['model'] ?? $settings['model'] ?? 'grok-beta',
            parameters: $parameters,
        );
    }

    private function createDeepseek(array $settings): Deepseek
    {
        $apiKey = $settings['deepseek']['api_key'] ?? $settings['api_key']
            ?? throw new \RuntimeException(
                'Deepseek API key is not configured. Add "deepseek.api_key" or "api_key" to .neuron/settings.json.'
            );

        $parameters = [];
        if (isset($settings['deepseek']['max_tokens'])) {
            $parameters['max_tokens'] = $settings['deepseek']['max_tokens'];
        }

        return new Deepseek(
            key: $apiKey,
            model: $settings['deepseek']['model'] ?? $settings['model'] ?? 'deepseek-chat',
            parameters: $parameters,
        );
    }
}
