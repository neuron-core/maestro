<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Settings;

use NeuronCore\Synapse\Settings\ProviderFactoryInterface;
use NeuronCore\Synapse\Settings\Settings;
use NeuronCore\Synapse\Settings\SettingsInterface;
use NeuronAI\Providers\AIProviderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function json_encode;
use function rename;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

class SettingsTest extends TestCase
{
    private string $tempSettingsPath;

    protected function setUp(): void
    {
        $this->tempSettingsPath = sys_get_temp_dir() . '/test_settings_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempSettingsPath)) {
            unlink($this->tempSettingsPath);
        }
    }

    public function testImplementsSettingsInterface(): void
    {
        $settings = new Settings();
        $this->assertInstanceOf(SettingsInterface::class, $settings);
    }

    public function testLoadSettingsFromFile(): void
    {
        $config = [
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);

        $this->assertSame('test-key', $settings->get('provider.api_key'));
        $this->assertSame('anthropic', $settings->get('provider.type'));
    }

    public function testLoadSettingsFromDefaultPath(): void
    {
        $defaultPath = getcwd() . '/.synapse/settings.json';

        if (file_exists($defaultPath)) {
            $original = file_get_contents($defaultPath);
            $backup = sys_get_temp_dir() . '/settings_backup.json';
            file_put_contents($backup, $original);
        }

        try {
            $config = ['test' => 'value'];
            file_put_contents($defaultPath, json_encode($config));

            $settings = new Settings();

            $this->assertSame('value', $settings->get('test'));
        } finally {
            if (file_exists($defaultPath)) {
                unlink($defaultPath);
            }
            if (isset($backup) && file_exists($backup)) {
                rename($backup, $defaultPath);
            }
        }
    }

    public function testLoadSettingsFromNonExistentFileReturnsEmpty(): void
    {
        $settings = new Settings('/non/existent/path/settings.json');

        $this->assertEmpty($settings->all());
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $settings = new Settings();

        $this->assertNull($settings->get('nonexistent'));
        $this->assertSame('default', $settings->get('nonexistent', 'default'));
    }

    public function testGetReturnsValueForExistingKey(): void
    {
        $config = [
            'provider' => [
                'type' => 'openai',
                'model' => 'gpt-4',
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $this->assertSame('openai', $settings->get('provider.type'));
        $this->assertSame('gpt-4', $settings->get('provider.model'));
    }

    public function testGetSupportsDotNotation(): void
    {
        $config = [
            'anthropic' => [
                'api_key' => 'sk-123',
                'model' => 'claude-3',
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $this->assertSame('sk-123', $settings->get('anthropic.api_key'));
        $this->assertSame('claude-3', $settings->get('anthropic.model'));
    }

    public function testGetWithDotNotationReturnsDefaultForMissingKey(): void
    {
        $config = ['anthropic' => ['api_key' => 'test']];
        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $this->assertNull($settings->get('anthropic.nonexistent'));
        $this->assertSame('default', $settings->get('anthropic.nonexistent', 'default'));
    }

    public function testGetWithDeeplyNestedDotNotation(): void
    {
        $config = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep-value',
                ],
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $this->assertSame('deep-value', $settings->get('level1.level2.level3'));
    }

    public function testAllReturnsSettingsArray(): void
    {
        $config = [
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $this->assertSame($config, $settings->all());
    }

    public function testAllReturnsEmptyArrayWhenNoSettings(): void
    {
        $settings = new Settings('/non/existent/path.json');

        $this->assertSame([], $settings->all());
    }

    public function testProviderReturnsAiProvider(): void
    {
        $config = [
            'provider' => [
                'type' => 'anthropic',
                'api_key' => 'test-key',
            ],
        ];

        file_put_contents($this->tempSettingsPath, json_encode($config));
        $settings = new Settings($this->tempSettingsPath);

        $provider = $settings->provider();

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function testProviderThrowsExceptionWhenNoApiKey(): void
    {
        $this->expectException(RuntimeException::class);

        $config = ['provider' => ['type' => 'anthropic']];
        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);
        $settings->provider();
    }

    public function testSetProviderFactory(): void
    {
        $mockFactory = $this->createMock(
            ProviderFactoryInterface::class
        );

        $settings = new Settings($this->tempSettingsPath);
        $result = $settings->setProviderFactory($mockFactory);

        $this->assertSame($settings, $result);
    }

    public function testProviderUsesCustomFactory(): void
    {
        $mockProvider = $this->createMock(AIProviderInterface::class);

        $mockFactory = $this->createMock(
            ProviderFactoryInterface::class
        );
        $mockFactory->expects($this->once())
            ->method('create')
            ->willReturn($mockProvider);

        $config = ['provider' => ['type' => 'anthropic', 'api_key' => 'test']];
        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);
        $settings->setProviderFactory($mockFactory);

        $this->assertSame($mockProvider, $settings->provider());
    }

    public function testMcpServersReturnsEmptyArrayWhenNoServersConfigured(): void
    {
        $config = ['provider' => ['type' => 'anthropic', 'api_key' => 'test']];
        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);
        $servers = $settings->mcpServers();

        $this->assertEmpty($servers);
    }

    public function testMcpServersSkipsInvalidServerConfigurations(): void
    {
        $config = [
            'provider' => ['type' => 'anthropic', 'api_key' => 'test'],
            'mcp_servers' => [
                'filesystem' => [
                    'command' => 'echo',
                    'args' => ['test'],
                ],
            ],
        ];
        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);
        $servers = $settings->mcpServers();

        // The invalid server should be skipped (error is logged)
        $this->assertArrayNotHasKey('filesystem', $servers);
    }

    public function testMcpServersSkipsInvalidConfigurations(): void
    {
        $config = [
            'provider' => ['type' => 'anthropic', 'api_key' => 'test'],
            'mcp_servers' => [
                'valid' => ['command' => 'echo', 'args' => ['test']],
                'invalid' => ['invalid' => 'config'],
            ],
        ];
        file_put_contents($this->tempSettingsPath, json_encode($config));

        $settings = new Settings($this->tempSettingsPath);
        $servers = $settings->mcpServers();

        // Invalid config should be skipped (not throw exception)
        $this->assertArrayNotHasKey('invalid', $servers);
    }

    public function testHandlesInvalidJsonFile(): void
    {
        file_put_contents($this->tempSettingsPath, 'invalid json {{{');

        $settings = new Settings($this->tempSettingsPath);

        $this->assertEmpty($settings->all());
        $this->assertNull($settings->get('any.key'));
    }
}
