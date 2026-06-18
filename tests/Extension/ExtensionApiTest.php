<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension;

use NeuronAI\Tools\ToolInterface;
use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\EventRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Settings\Settings;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function sys_get_temp_dir;
use function unlink;

class ExtensionApiTest extends TestCase
{
    private ToolRegistry $tools;
    private CommandRegistry $commands;
    private EventRegistry $events;
    private MemoryRegistry $memories;
    private Settings&\PHPUnit\Framework\MockObject\MockObject $settings;
    private ExtensionApi $api;

    protected function setUp(): void
    {
        $this->tools = new ToolRegistry();
        $this->commands = new CommandRegistry();
        $this->events = new EventRegistry();
        $this->memories = new MemoryRegistry();
        $this->settings = $this->createMock(Settings::class);

        $this->api = new ExtensionApi(
            $this->tools,
            $this->commands,
            $this->events,
            $this->memories,
            $this->settings,
        );
    }

    public function testRegisterToolAddsToRegistry(): void
    {
        $this->api->registerTool($this->createMockTool('test_tool'));

        $this->assertTrue($this->tools->has('test_tool'));
    }

    public function testRegisterCommandAddsToRegistry(): void
    {
        $this->api->registerCommand($this->createCommandMock('test', 'Test command'));

        $this->assertTrue($this->commands->has('test'));
    }

    public function testOnAddsEventHandlerToRegistry(): void
    {
        $this->api->on('test.event', fn () => null);

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

    public function testEventsReturnsSameRegistry(): void
    {
        $this->assertSame($this->events, $this->api->events());
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
}
