<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension\Ui;

use NeuronCore\Maestro\Extension\Ui\ColorName;
use NeuronCore\Maestro\Extension\Ui\StyleName;
use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Extension\Ui\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Text::setTheme(new DarkTheme());
    }

    public function testContentCreatesInstance(): void
    {
        $text = Text::content('hello');
        $this->assertSame('hello', $text->build());
    }

    public function testPrimaryColor(): void
    {
        $result = Text::content('test')->primary()->build();
        $this->assertSame('<fg=cyan>test</>', $result);
    }

    public function testSuccessColor(): void
    {
        $result = Text::content('test')->success()->build();
        $this->assertSame('<fg=green>test</>', $result);
    }

    public function testWarningColor(): void
    {
        $result = Text::content('test')->warning()->build();
        $this->assertSame('<fg=yellow>test</>', $result);
    }

    public function testErrorColor(): void
    {
        $result = Text::content('test')->error()->build();
        $this->assertSame('<fg=red>test</>', $result);
    }

    public function testInfoColor(): void
    {
        $result = Text::content('test')->info()->build();
        $this->assertSame('<fg=blue>test</>', $result);
    }

    public function testMutedColor(): void
    {
        $result = Text::content('test')->muted()->build();
        $this->assertSame('<fg=gray>test</>', $result);
    }

    public function testAccentColor(): void
    {
        $result = Text::content('test')->accent()->build();
        $this->assertSame('<fg=magenta>test</>', $result);
    }

    public function testBoldStyle(): void
    {
        $result = Text::content('test')->bold()->build();
        $this->assertSame('<options=bold>test</>', $result);
    }

    public function testDimStyle(): void
    {
        $result = Text::content('test')->dim()->build();
        $this->assertSame('<options=dim>test</>', $result);
    }

    public function testUnderlineStyle(): void
    {
        $result = Text::content('test')->underline()->build();
        $this->assertSame('<options=underscore>test</>', $result);
    }

    public function testColorAndStyleCombined(): void
    {
        $result = Text::content('test')->primary()->bold()->build();
        $this->assertSame('<fg=cyan;options=bold>test</>', $result);
    }

    public function testChainingPreservesOrder(): void
    {
        $result = Text::content('test')->bold()->primary()->build();
        $this->assertSame('<fg=cyan;options=bold>test</>', $result);
    }

    public function testNoColorOrStyleReturnsPlain(): void
    {
        $result = Text::content('test')->build();
        $this->assertSame('test', $result);
    }

    public function testToStringReturnsSameAsBuild(): void
    {
        $text = Text::content('test')->success();
        $this->assertSame($text->build(), (string) $text);
    }

    public function testMultipleStylesCanOverride(): void
    {
        $result = Text::content('test')->bold()->dim()->build();
        $this->assertSame('<options=dim>test</>', $result);
    }

    public function testMultipleColorsCanOverride(): void
    {
        $result = Text::content('test')->primary()->success()->build();
        $this->assertSame('<fg=green>test</>', $result);
    }

    public function testSetThemeChangesOutput(): void
    {
        $text1 = Text::content('test')->primary()->build();

        $mockTheme = $this->createMock(\NeuronCore\Maestro\Extension\Ui\ThemeInterface::class);
        $mockTheme->method('color')->willReturnMap([
            [ColorName::PRIMARY, 'white'],
            [ColorName::SUCCESS, 'white'],
        ]);
        $mockTheme->method('style')->willReturnMap([
            [StyleName::BOLD, ''],
            [StyleName::DIM, ''],
            [StyleName::UNDERLINE, ''],
            [StyleName::DEFAULT, ''],
        ]);

        Text::setTheme($mockTheme);

        $text2 = Text::content('test')->primary()->build();

        $this->assertSame('<fg=cyan>test</>', $text1);
        $this->assertSame('<fg=white>test</>', $text2);

        // Reset theme
        Text::setTheme(new DarkTheme());
    }
}
