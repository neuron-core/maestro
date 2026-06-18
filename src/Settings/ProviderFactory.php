<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Settings;

use NeuronAI\HttpClient\AmpHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\Cohere\Cohere;
use NeuronAI\Providers\Mistral\Mistral;
use NeuronAI\Providers\Ollama\Ollama;
use NeuronAI\Providers\XAI\Grok;
use NeuronAI\Providers\Deepseek\Deepseek;
use NeuronAI\Providers\ZAI\ZAI;
use RuntimeException;

use function array_keys;
use function implode;
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
    /**
     * Get all supported provider types.
     *
     * @return string[]
     */
    public function getSupportedProviders(): array
    {
        return array_keys($this->factories);
    }

    public function register(string $provider, callable $factory): self
    {
        $this->factories[strtolower($provider)] = $factory;
        return $this;
    }

    /**
     * Create a provider instance based on the settings array.
     *
     * @throws RuntimeException if the provider cannot be created
     */
    public function create(string $type, array $list): AIProviderInterface
    {
        $lowerType = strtolower($type);

        if (!isset($this->factories[$lowerType])) {
            throw new RuntimeException(
                sprintf('Unknown provider "%s". Available providers: %s', $type, implode(', ', array_keys($this->factories)))
            );
        }

        // Pass only the provider config to the factory methods
        return ($this->factories[$lowerType])($list[$lowerType]);
    }

    /**
     * Non-blocking HTTP client shared by every provider.
     *
     * The TUI runs on PHP Fibers + the Revolt event loop. Amp's HTTP client
     * suspends the fiber on network I/O so the loop keeps rendering (live token
     * streaming, animated spinner) while inference is in flight. Guzzle would
     * block the fiber and freeze the UI.
     */
    protected function nonBlockingHttpClient(): HttpClientInterface
    {
        return new AmpHttpClient();
    }

    /**
     * Register all default provider factories.
     */
    private function registerDefaultFactories(): void
    {
        $this->factories['anthropic'] = fn (array $settings): AIProviderInterface => $this->createAnthropic($settings);
        $this->factories['openai'] = fn (array $settings): AIProviderInterface => $this->createOpenAI($settings);
        $this->factories['openailike'] = fn (array $settings): AIProviderInterface => $this->createOpenAILike($settings);
        $this->factories['gemini'] = fn (array $settings): AIProviderInterface => $this->createGemini($settings);
        $this->factories['cohere'] = fn (array $settings): AIProviderInterface => $this->createCohere($settings);
        $this->factories['mistral'] = fn (array $settings): AIProviderInterface => $this->createMistral($settings);
        $this->factories['ollama'] = fn (array $settings): AIProviderInterface => $this->createOllama($settings);
        $this->factories['xai'] = $this->factories['grok'] = fn (array $settings): AIProviderInterface => $this->createGrok($settings);
        $this->factories['deepseek'] = fn (array $settings): AIProviderInterface => $this->createDeepseek($settings);
        $this->factories['zai'] = fn (array $settings): AIProviderInterface => $this->createZai($settings);
    }

    private function createAnthropic(array $settings): Anthropic
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Anthropic API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        return new Anthropic(
            key: $apiKey,
            model: $settings['model'] ?? 'claude-sonnet-4-20250514',
            max_tokens: $settings['max_tokens'] ?? 8192,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createOpenAI(array $settings): OpenAI
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'OpenAI API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new OpenAI(
            key: $apiKey,
            model: $settings['model'] ?? 'gpt-4',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createOpenAILike(array $settings): OpenAILike
    {
        $baseUrl = $settings['base_url']
            ?? throw new RuntimeException(
                'OpenAI-Compatible base URL is not configured. Add "base_url" to provider object in .maestro/settings.json.'
            );

        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'OpenAI-Compatible API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new OpenAILike(
            baseUri: $baseUrl,
            key: $apiKey,
            model: $settings['model'] ?? 'gpt-4',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createGemini(array $settings): Gemini
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Gemini API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Gemini(
            key: $apiKey,
            model: $settings['model'] ?? 'gemini-pro',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createCohere(array $settings): Cohere
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Cohere API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Cohere(
            key: $apiKey,
            model: $settings['model'] ?? 'command',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createMistral(array $settings): Mistral
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Mistral API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Mistral(
            key: $apiKey,
            model: $settings['model'] ?? 'mistral-tiny',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
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
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createGrok(array $settings): Grok
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'xAI API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Grok(
            key: $apiKey,
            model: $settings['model'] ?? 'grok-beta',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    private function createDeepseek(array $settings): Deepseek
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Deepseek API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        $parameters = [];
        if (isset($settings['max_tokens'])) {
            $parameters['max_tokens'] = $settings['max_tokens'];
        }

        return new Deepseek(
            key: $apiKey,
            model: $settings['model'] ?? 'deepseek-chat',
            parameters: $parameters,
            httpClient: $this->nonBlockingHttpClient(),
        );
    }

    protected function createZai(array $settings): ZAI
    {
        $apiKey = $settings['api_key']
            ?? throw new RuntimeException(
                'Deepseek API key is not configured. Add "api_key" to provider object in .maestro/settings.json.'
            );

        return new ZAI(
            key: $apiKey,
            model: $settings['model'] ?? 'glm-4.7',
            httpClient: $this->nonBlockingHttpClient(),
        );
    }
}
