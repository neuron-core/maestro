<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Settings;

use NeuronAI\Providers\AIProviderInterface;
use NeuronCore\Synapse\Settings\ProviderFactory;
use NeuronCore\Synapse\Settings\ProviderFactoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
    public function testCreateAnthropicProvider(array $providerConfig): void
    {
        $settings = ['provider' => $providerConfig];
        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderWithDefaultSettings(): void
    {
        $settings = [
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderWithGlobalApiKey(): void
    {
        $settings = [
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateAnthropicProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Anthropic API key is not configured');

        $this->factory->create(['provider' => ['type' => 'anthropic']]);
    }

    public function testCreateOpenAIProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'openai',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateOpenAIProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured');

        $this->factory->create(['provider' => ['type' => 'openai']]);
    }

    public function testCreateGeminiProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'gemini',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGeminiProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini API key is not configured');

        $this->factory->create(['provider' => ['type' => 'gemini']]);
    }

    public function testCreateCohereProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'cohere',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateCohereProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cohere API key is not configured');

        $this->factory->create(['provider' => ['type' => 'cohere']]);
    }

    public function testCreateMistralProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'mistral',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateMistralProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mistral API key is not configured');

        $this->factory->create(['provider' => ['type' => 'mistral']]);
    }

    public function testCreateOllamaProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'ollama',
                'model' => 'llama2',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateOllamaProviderWithDefaults(): void
    {
        $settings = ['provider' => ['type' => 'ollama']];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'xai',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProviderUsingGrokAlias(): void
    {
        $settings = [
            'provider' => [
                'type' => 'grok',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateGrokProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('xAI API key is not configured');

        $this->factory->create(['provider' => ['type' => 'xai']]);
    }

    public function testCreateDeepseekProvider(): void
    {
        $settings = [
            'provider' => [
                'type' => 'deepseek',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testCreateDeepseekProviderMissingApiKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Deepseek API key is not configured');

        $this->factory->create(['provider' => ['type' => 'deepseek']]);
    }

    public function testCreateUnknownProviderThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown provider "unknown"');

        $this->factory->create(['provider' => ['type' => 'unknown']]);
    }

    public function testCreateWithNoProviderTypeThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid provider configuration');

        $this->factory->create(['provider' => ['api_key' => 'test-key']]);
    }

    public function testRegisterCustomProvider(): void
    {
        $mockProvider = $this->createMock(AIProviderInterface::class);

        $this->factory->register('custom', fn (): \PHPUnit\Framework\MockObject\MockObject => $mockProvider);

        $provider = $this->factory->create(['provider' => ['type' => 'custom']]);

        $this->assertSame($mockProvider, $provider);
    }

    public function testRegisterCustomProviderOverwritesDefault(): void
    {
        $mockProvider = $this->createMock(AIProviderInterface::class);

        $this->factory->register('anthropic', fn (): \PHPUnit\Framework\MockObject\MockObject => $mockProvider);

        $provider = $this->factory->create([
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ]);

        $this->assertSame($mockProvider, $provider);
    }

    public function testProviderNameIsCaseInsensitive(): void
    {
        $settings = [
            'provider' => [
                'type' => 'ANTHROPIC',
                'api_key' => 'test-key',
            ],
        ];

        $provider = $this->factory->create($settings);

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public static function validAnthropicSettingsProvider(): array
    {
        return [
            'minimal' => [['type' => 'anthropic', 'api_key' => 'test-key']],
            'with_model' => [['type' => 'anthropic', 'api_key' => 'test-key', 'model' => 'claude-3-opus']],
            'with_max_tokens' => [['type' => 'anthropic', 'api_key' => 'test-key', 'max_tokens' => 4096]],
            'complete' => [[
                'type' => 'anthropic',
                'api_key' => 'test-key',
                'model' => 'claude-3-opus',
                'max_tokens' => 4096,
            ]],
        ];
    }
}
