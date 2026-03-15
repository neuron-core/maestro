<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Listeners;

use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Extension\Registry\RendererRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotType;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\UiEngine;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use NeuronCore\Maestro\Listeners\CliOutputListener;
use NeuronCore\Maestro\Rendering\MarkdownRenderer;
use NeuronCore\Maestro\Rendering\Renderers\GenericRenderer;
use NeuronCore\Maestro\Settings\SettingsInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class CliOutputListenerTest extends TestCase
{
    private function createListener(UiEngine $uiEngine, ?BufferedOutput $output = null): CliOutputListener
    {
        $settings = $this->createMock(SettingsInterface::class);

        return new CliOutputListener(
            $this->createMock(InputInterface::class),
            $output ?? new BufferedOutput(),
            $settings,
            new RendererRegistry(new GenericRenderer()),
            $uiEngine,
            new MarkdownRenderer($uiEngine->theme()),
        );
    }

    private function createEngine(): UiEngine
    {
        return new UiEngine(new DarkTheme(), new SlotRegistry(), new WidgetRegistry());
    }

    public function testOnResponseWritesContentToOutput(): void
    {
        $output = new BufferedOutput();
        $listener = $this->createListener($this->createEngine(), $output);

        $listener->onResponse(new AgentResponseEvent('Hello from agent'));

        $this->assertStringContainsString('Hello from agent', $output->fetch());
    }

    public function testOnResponseClearsContentSlotAfterRender(): void
    {
        $engine = $this->createEngine();
        $listener = $this->createListener($engine);

        $listener->onResponse(new AgentResponseEvent('some content'));

        $this->assertTrue($engine->slots()->slot(SlotType::CONTENT)->isEmpty());
    }

    public function testOnResponseRendersStatusBarContent(): void
    {
        $output = new BufferedOutput();
        $engine = $this->createEngine();
        $engine->slots()->slot(SlotType::STATUS_BAR)->add(' branch: main ');

        $listener = $this->createListener($engine, $output);
        $listener->onResponse(new AgentResponseEvent('content'));

        $this->assertStringContainsString(' branch: main ', $output->fetch());
    }

    public function testStatusBarIsNotClearedAfterRenderCycle(): void
    {
        $engine = $this->createEngine();
        $engine->slots()->slot(SlotType::STATUS_BAR)->add(' branch: main ');

        $listener = $this->createListener($engine);
        $listener->onResponse(new AgentResponseEvent('first'));
        $listener->onResponse(new AgentResponseEvent('second'));

        $this->assertFalse($engine->slots()->slot(SlotType::STATUS_BAR)->isEmpty());
    }

    public function testHeaderSlotIsNotRenderedByRenderCycle(): void
    {
        $output = new BufferedOutput();
        $engine = $this->createEngine();
        $engine->slots()->slot(SlotType::HEADER)->add('Project: my-app');

        $listener = $this->createListener($engine, $output);
        $listener->onResponse(new AgentResponseEvent('hello'));

        $this->assertStringNotContainsString('Project: my-app', $output->fetch());
    }
}
