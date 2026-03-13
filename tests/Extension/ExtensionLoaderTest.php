<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\ExtensionInterface;
use NeuronCore\Maestro\Extension\ExtensionLoader;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

class ExtensionLoaderTest extends TestCase
{
    private ToolRegistry $tools;
    private CommandRegistry $commands;
    private RendererRegistry $renderers;
    private EventRegistry $events;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->commands = new CommandRegistry();
        $this->renderers = new RendererRegistry($this->createMockRenderer());
        $this->events = new EventRegistry();
    }

    public function testLoadWithEmptySettingsReturnsEmptyArray(): void
    {
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

        $result = $loader->load([]);

        $this->assertSame([], $result);
    }

    public function testLoadSkipsNonExistentClass(): void
    {
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

        $result = $loader->load([
            'extensions' => [
                ['class' => 'NonExistent\\Class'],
            ],
        ]);

        $this->assertSame([], $result);
    }

    public function testLoadSkipsDisabledExtension(): void
    {
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

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
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

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
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

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

        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

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
        $loader = ExtensionLoader::create($this->createMockRenderer());

        $this->assertInstanceOf(ExtensionLoader::class, $loader);
    }

    public function testDescriptorsReturnsLoadedDescriptors(): void
    {
        $loader = new ExtensionLoader(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
        );

        $loader->load([
            'extensions' => [
                ['class' => TestExtension::class],
            ],
        ]);

        $descriptors = $loader->descriptors();

        $this->assertCount(1, $descriptors);
        $this->assertSame(TestExtension::class, $descriptors[0]->className);
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
