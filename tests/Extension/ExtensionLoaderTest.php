<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\ExtensionInterface;
use NeuronCore\Maestro\Extension\ExtensionLoader;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Extension\Ui\UiEngine;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use NeuronCore\Maestro\Settings\Settings;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

class ExtensionLoaderTest extends TestCase
{
    private ToolRegistry $tools;
    private CommandRegistry $commands;
    private RendererRegistry $renderers;
    private EventRegistry $events;
    private MemoryRegistry $memories;
    private Settings $settings;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->commands = new CommandRegistry();
        $this->renderers = new RendererRegistry($this->createMockRenderer());
        $this->events = new EventRegistry();
        $this->memories = new MemoryRegistry();
        $this->settings = $this->createMock(Settings::class);
    }

    /**
     * Create a loader instance with a non-existent manifest path for tests.
     */
    private function createLoader(): ExtensionLoader
    {
        return new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
            $this->memories,
            $this->settings,
            null,
            __DIR__ . '/non-existent-manifest.php',
        );
    }

    public function testLoadWithEmptySettingsReturnsEmptyArray(): void
    {
        $loader = $this->createLoader();

        $result = $loader->load([]);

        $this->assertSame([], $result);
    }

    public function testLoadSkipsNonExistentClass(): void
    {
        $loader = $this->createLoader();

        $result = $loader->load([
            'extensions' => [
                ['class' => 'NonExistent\\Class'],
            ],
        ]);

        $this->assertSame([], $result);
    }

    public function testLoadSkipsDisabledExtension(): void
    {
        $loader = $this->createLoader();

        $result = $loader->load([
            'extensions' => [
                [
                    'class' => TestExtension::class,
                    'enabled' => false,
                ],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertFalse($this->commands->has('test_command'));
    }

    public function testLoadCallsRegisterOnEnabledExtension(): void
    {
        $loader = $this->createLoader();

        $result = $loader->load([
            'extensions' => [
                [
                    'class' => TestExtension::class,
                    'enabled' => true,
                ],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertTrue($this->commands->has('test_command'));
    }

    public function testLoadPassesConfigToDescriptor(): void
    {
        $loader = $this->createLoader();

        $result = $loader->load([
            'extensions' => [
                [
                    'class' => TestExtension::class,
                    'config' => ['key' => 'value'],
                ],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(['key' => 'value'], $result[0]->config);
    }

    public function testLoadThrowsForNonExtensionInterfaceClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Failed to initialize extension "%s"',
                InvalidExtension::class
            )
        );

        $loader = $this->createLoader();

        $loader->load([
            'extensions' => [
                [
                    'class' => InvalidExtension::class,
                    'enabled' => true,
                ],
            ],
        ]);
    }

    public function testCreateReturnsLoaderInstance(): void
    {
        $loader = ExtensionLoader::create($this->createMockRenderer(), $this->settings);

        $this->assertInstanceOf(ExtensionLoader::class, $loader);
    }

    public function testUiEngineReturnsUiEngineInstance(): void
    {
        $loader = $this->createLoader();

        $this->assertInstanceOf(UiEngine::class, $loader->uiEngine());
    }

    public function testUiEngineReturnsSameInstance(): void
    {
        $loader = $this->createLoader();

        $this->assertSame($loader->uiEngine(), $loader->uiEngine());
    }

    public function testRegisterCoreCallsRegisterOnEachExtension(): void
    {
        $loader = $this->createLoader();

        $ext1 = $this->createMock(ExtensionInterface::class);
        $ext1->expects($this->once())->method('register');

        $ext2 = $this->createMock(ExtensionInterface::class);
        $ext2->expects($this->once())->method('register');

        $loader->registerCore($ext1, $ext2);
    }

    public function testDescriptorsReturnsLoadedDescriptors(): void
    {
        $loader = $this->createLoader();

        $loader->load([
            'extensions' => [
                ['class' => TestExtension::class],
            ],
        ]);

        $descriptors = $loader->descriptors();

        $this->assertCount(1, $descriptors);
        $this->assertSame(TestExtension::class, $descriptors[0]->className);
    }

    public function testMemoriesReturnsMemoryRegistry(): void
    {
        $loader = $this->createLoader();

        $this->assertSame($this->memories, $loader->memories());
    }

    private function createMockRenderer(): ToolRenderer
    {
        return $this->createMock(ToolRenderer::class);
    }
}

// Test fixture classes
class TestExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function register(ExtensionApi $api): void
    {
        $api->registerCommand(new TestInlineCommand());
    }
}

class TestInlineCommand implements InlineCommand
{
    public function getName(): string
    {
        return 'test_command';
    }

    public function getDescription(): string
    {
        return 'Test command';
    }

    public function execute(string $args, mixed $input, mixed $output): void
    {
    }
}

class InvalidExtension
{
    public function name(): string
    {
        return 'invalid';
    }
}
