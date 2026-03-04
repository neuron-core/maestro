<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Tests\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronCore\CodingAgent\Settings\ProviderFactory;
use NeuronCore\CodingAgent\Settings\ProviderFactoryInterface;
use PHPUnit\Framework\TestCase;

class ProviderFactoryTest extends TestCase
{
    private ProviderFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ProviderFactory();
    }

    public function testImplementsProviderFactoryInterface(): void
    {
        $this->assertInstanceOf(ProviderFactoryInterface::class, $this->factory);
    }

    /**
     * @dataProvider validAnthropicSettingsProvider
     */
    public function testCreateAnthropicProvider(array $settings): void
    {
        $settings['provider'] = 'anthropic';
        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderWithDefaultSettings(): void
    {
        $settings = [
            'provider' => 'anthropic',
            'anthropic' => [
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderWithGlobalApiKey(): void
    {
        $settings = [
            'provider' => 'anthropic',
            'api_key' => 'test-key',
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Anthropic API key is not configured');

        $this->factory->create(['provider' => 'anthropic']);
    }

    public function testCreateOpenAIProvider(): void
    {
        $settings = [
            'provider' => 'openai',
            'openai' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateOpenAIProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');

        $this->factory->create(['provider' => 'openai']);
    }

    public function testCreateGeminiProvider(): void
    {
        $settings = [
            'provider' => 'gemini',
            'gemini' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGeminiProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gemini API key is not configured');

        $this->factory->create(['provider' => 'gemini']);
    }

    public function testCreateCohereProvider(): void
    {
        $settings = [
            'provider' => 'cohere',
            'cohere' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateCohereProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cohere API key is not configured');

        $this->factory->create(['provider' => 'cohere']);
    }

    public function testCreateMistralProvider(): void
    {
        $settings = [
            'provider' => 'mistral',
            'mistral' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateMistralProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mistral API key is not configured');

        $this->factory->create(['provider' => 'mistral']);
    }

    public function testCreateOllamaProvider(): void
    {
        $settings = [
            'provider' => 'ollama',
            'ollama' => ['model' => 'llama2'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateOllamaProviderWithDefaults(): void
    {
        $settings = ['provider' => 'ollama'];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProvider(): void
    {
        $settings = [
            'provider' => 'xai',
            'xai' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProviderUsingGrokAlias(): void
    {
        $settings = [
            'provider' => 'grok',
            'grok' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('xAI API key is not configured');

        $this->factory->create(['provider' => 'xai']);
    }

    public function testCreateDeepseekProvider(): void
    {
        $settings = [
            'provider' => 'deepseek',
            'deepseek' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateDeepseekProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deepseek API key is not configured');

        $this->factory->create(['provider' => 'deepseek']);
    }

    public function testCreateUnknownProviderThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown provider "unknown"');

        $this->factory->create(['provider' => 'unknown']);
    }

    public function testCreateWithNoProviderDefaultsToAnthropic(): void
    {
        $settings = ['anthropic' => ['api_key' => 'test-key']];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testRegisterCustomProvider(): void
    {
        $mockProvider = $this->createMock(AIProviderInterface::class);

        $this->factory->register('custom', function () use ($mockProvider) {
            return $mockProvider;
        });

        $provider = $this->factory->create(['provider' => 'custom']);

        $this->assertSame($mockProvider, $provider);
    }

    public function testRegisterCustomProviderOverwritesDefault(): void
    {
        $mockProvider = $this->createMock(AIProviderInterface::class);

        $this->factory->register('anthropic', function () use ($mockProvider) {
            return $mockProvider;
        });

        $provider = $this->factory->create([
            'provider' => 'anthropic',
            'anthropic' => ['api_key' => 'test-key'],
        ]);

        $this->assertSame($mockProvider, $provider);
    }

    public function testProviderNameIsCaseInsensitive(): void
    {
        $settings = [
            'provider' => 'ANTHROPIC',
            'anthropic' => ['api_key' => 'test-key'],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    /**
     * @return array<array<string, mixed>>
     */
    public static function validAnthropicSettingsProvider(): array
    {
        return [
            'minimal' => [['anthropic' => ['api_key' => 'test-key']]],
            'with_model' => [['anthropic' => ['api_key' => 'test-key', 'model' => 'claude-3-opus']]],
            'with_max_tokens' => [['anthropic' => ['api_key' => 'test-key', 'max_tokens' => 4096]]],
            'complete' => [[
                'anthropic' => [
                    'api_key' => 'test-key',
                    'model' => 'claude-3-opus',
                    'max_tokens' => 4096,
                ],
            ]],
        ];
    }
}
