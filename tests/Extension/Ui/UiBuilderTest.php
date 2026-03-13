<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension\Ui;

use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\ContentType;
use NeuronCore\Maestro\Extension\Ui\SlotRegistry;
use NeuronCore\Maestro\Extension\Ui\SlotType;
use NeuronCore\Maestro\Extension\Ui\StyleName;
use NeuronCore\Maestro\Extension\Ui\ThemeInterface;
use NeuronCore\Maestro\Extension\Ui\UiBuilder;
use NeuronCore\Maestro\Extension\Ui\WidgetInterface;
use NeuronCore\Maestro\Extension\Ui\WidgetRegistry;
use PHPUnit\Framework\TestCase;

class UiBuilderTest extends TestCase
{
    private function createTheme(array $colorMap = [], array $styleMap = []): ThemeInterface
    {
        $mock = $this->createMock(ThemeInterface::class);
        $mock->method('name')->willReturn('test');
        $mock->method('color')->willReturnMap($colorMap ?: [
            [ColorName::PRIMARY, 'cyan'],
            [ColorName::SUCCESS, 'green'],
            [ColorName::WARNING, 'yellow'],
            [ColorName::ERROR,   'red'],
        ]);
        $mock->method('style')->willReturnMap($styleMap ?: [
            [StyleName::BOLD,      'options=bold'],
            [StyleName::DIM,       'options=dim'],
            [StyleName::UNDERLINE, 'options=underscore'],
            [StyleName::DEFAULT,   ''],
        ]);
        $mock->method('icon')->willReturn('');
        return $mock;
    }

    public function testFormatTextWithColor(): void
    {
        $theme = $this->createTheme([[ColorName::PRIMARY, 'cyan']]);
        $builder = new UiBuilder($theme, new SlotRegistry(), new WidgetRegistry());

        $this->assertSame('<fg=cyan>test</>', $builder->formatText('test', ColorName::PRIMARY));
    }

    public function testFormatTextWithStyle(): void
    {
        $theme = $this->createTheme([[ColorName::PRIMARY, 'blue']], [[StyleName::BOLD, 'options=bold']]);
        $builder = new UiBuilder($theme, new SlotRegistry(), new WidgetRegistry());

        $this->assertSame('<fg=blue;options=bold>test</>', $builder->formatText('test', ColorName::PRIMARY, StyleName::BOLD));
    }

    public function testFormatTextWithColorAndStyle(): void
    {
        $theme = $this->createTheme([[ColorName::SUCCESS, 'green']], [[StyleName::DIM, 'options=dim']]);
        $builder = new UiBuilder($theme, new SlotRegistry(), new WidgetRegistry());

        $this->assertSame('<fg=green;options=dim>test</>', $builder->formatText('test', ColorName::SUCCESS, StyleName::DIM));
    }

    public function testFormatTextWithNoFormatting(): void
    {
        $theme = $this->createTheme([[ColorName::PRIMARY, '']], [[StyleName::DEFAULT, '']]);
        $builder = new UiBuilder($theme, new SlotRegistry(), new WidgetRegistry());

        $this->assertSame('test', $builder->formatText('test'));
    }

    public function testAddToSlot(): void
    {
        $slots = new SlotRegistry();
        $builder = new UiBuilder($this->createTheme(), $slots, new WidgetRegistry());

        $builder->addToSlot(SlotType::HEADER, 'Header content', 100);
        $builder->addToSlot(SlotType::HEADER, 'Another header', 200);

        $this->assertSame(['Another header', 'Header content'], $slots->slot(SlotType::HEADER)->sorted());
    }

    public function testClearSlot(): void
    {
        $slots = new SlotRegistry();
        $builder = new UiBuilder($this->createTheme(), $slots, new WidgetRegistry());

        $builder->addToSlot(SlotType::STATUS_BAR, 'Status');
        $slot = $slots->slot(SlotType::STATUS_BAR);

        $this->assertFalse($slot->isEmpty());

        $builder->clearSlot(SlotType::STATUS_BAR);

        $this->assertTrue($slot->isEmpty());
    }

    public function testRegisterWidget(): void
    {
        $widgets = new WidgetRegistry();
        $builder = new UiBuilder($this->createTheme(), new SlotRegistry(), $widgets);

        $widget = $this->createMock(WidgetInterface::class);
        $widget->method('name')->willReturn('test_widget');
        $widget->method('contentType')->willReturn(ContentType::STATUS);

        $builder->registerWidget($widget);

        $this->assertTrue($widgets->has('test_widget'));
    }

    public function testTheme(): void
    {
        $theme = $this->createTheme();
        $builder = new UiBuilder($theme, new SlotRegistry(), new WidgetRegistry());

        $this->assertSame($theme, $builder->theme());
    }

    public function testSlots(): void
    {
        $slots = new SlotRegistry();
        $builder = new UiBuilder($this->createTheme(), $slots, new WidgetRegistry());

        $this->assertSame($slots, $builder->slots());
    }

    public function testWidgets(): void
    {
        $widgets = new WidgetRegistry();
        $builder = new UiBuilder($this->createTheme(), new SlotRegistry(), $widgets);

        $this->assertSame($widgets, $builder->widgets());
    }
}
