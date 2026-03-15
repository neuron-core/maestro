<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension;

use NeuronAI\Tools\ToolInterface;
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Extension\Ui\ContentType;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\UiBuilder;
use NeuronCore\Maestro\Extension\Ui\WidgetInterface;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use NeuronCore\Maestro\Settings\Settings;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function unlink;

class ExtensionApiTest extends TestCase
{
    private ToolRegistry $tools;
    private CommandRegistry $commands;
    private RendererRegistry $renderers;
    private EventRegistry $events;
    private MemoryRegistry $memories;
    private Settings $settings;
    private ExtensionApi $api;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->commands = new CommandRegistry();
        $this->renderers = new RendererRegistry($this->createMockRenderer());
        $this->events = new EventRegistry();
        $this->memories = new MemoryRegistry();
        $this->settings = $this->createMock(Settings::class);

        $ui = new UiBuilder(
            new DarkTheme(),
            new SlotRegistry(),
            new WidgetRegistry(),
        );

        $this->api = new ExtensionApi(
            $this->tools,
            $this->commands,
            $this->renderers,
            $this->events,
            $ui,
            $this->memories,
            $this->settings,
        );
    }

    public function testRegisterToolAddsToRegistry(): void
    {
        $tool = $this->createMockTool('test_tool');

        $this->api->registerTool($tool);

        $this->assertTrue($this->tools->has('test_tool'));
    }

    public function testRegisterCommandAddsToRegistry(): void
    {
        $command = $this->createCommandMock('test', 'Test command');

        $this->api->registerCommand($command);

        $this->assertTrue($this->commands->has('test'));
    }

    public function testRegisterRendererAddsToRegistry(): void
    {
        $renderer = $this->createMockRenderer();

        $this->api->registerRenderer('my_tool', $renderer);

        $this->assertTrue($this->renderers->has('my_tool'));
    }

    public function testOnAddsEventHandlerToRegistry(): void
    {
        $handler = fn () => null;

        $this->api->on('test.event', $handler);

        $this->assertTrue($this->events->has('test.event'));
    }

    public function testToolsReturnsSameRegistry(): void
    {
        $this->assertSame($this->tools, $this->api->tools());
    }

    public function testCommandsReturnsSameRegistry(): void
    {
        $this->assertSame($this->commands, $this->api->commands());
    }

    public function testRenderersReturnsSameRegistry(): void
    {
        $this->assertSame($this->renderers, $this->api->renderers());
    }

    public function testEventsReturnsSameRegistry(): void
    {
        $this->assertSame($this->events, $this->api->events());
    }

    public function testRegisterWidgetAddsToWidgetRegistry(): void
    {
        $widgets = new WidgetRegistry();
        $ui = new UiBuilder(new DarkTheme(), new SlotRegistry(), $widgets);
        $api = new ExtensionApi($this->tools, $this->commands, $this->renderers, $this->events, $ui, $this->memories, $this->settings);

        $widget = $this->createMock(WidgetInterface::class);
        $widget->method('name')->willReturn('my_widget');
        $widget->method('contentType')->willReturn(ContentType::STATUS);

        $api->registerWidget($widget);

        $this->assertTrue($widgets->has('my_widget'));
    }

    public function testRegisterMemoryAddsToRegistry(): void
    {
        $tempFile = sys_get_temp_dir() . '/maestro_test_memory.md';
        file_put_contents($tempFile, 'Test memory content');

        $this->api->registerMemory('extension.test', $tempFile);

        $this->assertTrue($this->memories->has('extension.test'));
        $this->assertSame($tempFile, $this->memories->get('extension.test'));

        unlink($tempFile);
    }

    public function testMemoriesReturnsSameRegistry(): void
    {
        $this->assertSame($this->memories, $this->api->memories());
    }

    public function testSettingsReturnsSettingsInstance(): void
    {
        $this->assertSame($this->settings, $this->api->settings());
    }

    private function createMockTool(string $name): ToolInterface
    {
        $mock = $this->createMock(ToolInterface::class);
        $mock->method('getName')->willReturn($name);
        return $mock;
    }

    private function createCommandMock(string $name, string $description): InlineCommand
    {
        $mock = $this->createMock(InlineCommand::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('getDescription')->willReturn($description);
        return $mock;
    }

    private function createMockRenderer(): ToolRenderer
    {
        return $this->createMock(ToolRenderer::class);
    }
}
