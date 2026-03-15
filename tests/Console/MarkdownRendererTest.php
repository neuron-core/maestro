<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Console;

use NeuronCore\Maestro\Extension\Ui\Theme\DarkTheme;
use NeuronCore\Maestro\Rendering\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer(new DarkTheme());
    }

    public function testPlainText(): void
    {
        $input = 'Hello world';
        $output = $this->renderer->render($input);

        $this->assertSame('Hello world', $output);
    }

    public function testBold(): void
    {
        $input = 'This is **bold** text';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('<options=bold>bold</>', $output);
    }

    public function testItalic(): void
    {
        $input = 'This is *italic* text';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('<options=underscore>italic</>', $output);
    }

    public function testItalicWithUnderscore(): void
    {
        $input = 'This is _italic_ text';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('<options=underscore>italic</>', $output);
    }

    public function testInlineCode(): void
    {
        $input = 'This is `code`';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('<fg=blue>code</>', $output);
    }

    public function testLinks(): void
    {
        $input = 'Check [GitHub](https://github.com)';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('<options=underscore>GitHub</>', $output);
        $this->assertStringContainsString('(https://github.com)', $output);
    }

    public function testHeadersKeepMarker(): void
    {
        $input = '### Heading';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('### Heading', $output);
        $this->assertStringContainsString('<options=bold>', $output);
    }

    public function testMultipleHeaders(): void
    {
        $input = '# Main
## Sub
### Sub-sub';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('# Main', $output);
        $this->assertStringContainsString('## Sub', $output);
        $this->assertStringContainsString('### Sub-sub', $output);
    }

    public function testMixedMarkdown(): void
    {
        $input = '## Summary
This is **bold** and *italic*.
Check [docs](https://example.com) and use `code`.';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('## Summary', $output);
        $this->assertStringContainsString('<options=bold>bold</>', $output);
        $this->assertStringContainsString('<options=underscore>italic</>', $output);
        $this->assertStringContainsString('<options=underscore>docs</>', $output);
        $this->assertStringContainsString('<fg=blue>code</>', $output);
    }

    public function testDoesNotEscapeAsterisksInUrl(): void
    {
        $input = '[Link](https://example.com/test*file)';
        $output = $this->renderer->render($input);

        $this->assertStringContainsString('test*file', $output);
    }

    public function testPreservesNewlines(): void
    {
        $input = "Line 1\nLine 2\nLine 3";
        $output = $this->renderer->render($input);

        $this->assertStringContainsString("Line 1\nLine 2\nLine 3", $output);
    }
}
