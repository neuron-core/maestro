<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension;

use NeuronAI\Tools\ToolInterface;
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Extension\Ui\ContentType;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\UiBuilder;
use NeuronCore\Maestro\Extension\Ui\WidgetInterface;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;

class ExtensionApiTest extends TestCase
{
    private ToolRegistry $tools;
    private CommandRegistry $commands;
    private RendererRegistry $renderers;
    private EventRegistry $events;
    private ExtensionApi $api;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->commands = new CommandRegistry();
        $this->renderers = new RendererRegistry($this->createMockRenderer());
        $this->events = new EventRegistry();

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
        $api = new ExtensionApi($this->tools, $this->commands, $this->renderers, $this->events, $ui);

        $widget = $this->createMock(WidgetInterface::class);
        $widget->method('name')->willReturn('my_widget');
        $widget->method('contentType')->willReturn(ContentType::STATUS);

        $api->registerWidget($widget);

        $this->assertTrue($widgets->has('my_widget'));
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
