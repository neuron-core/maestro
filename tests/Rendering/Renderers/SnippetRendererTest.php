<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Rendering\Renderers;

use NeuronCore\Maestro\Rendering\Renderers\SnippetRenderer;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;

use function preg_replace;

class SnippetRendererTest extends TestCase
{
    private function stripAnsiCodes(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    public function testImplementsToolRenderer(): void
    {
        $this->assertInstanceOf(ToolRenderer::class, new SnippetRenderer(['key']));
    }

    public function testRenderExtractsSingleKey(): void
    {
        $renderer = new SnippetRenderer(['file_path']);
        $result = $renderer->render('read_file', '{"file_path": "src/Foo.php"}');

        $this->assertSame('● read_file(src/Foo.php)', $this->stripAnsiCodes($result));
    }

    public function testRenderExtractsMultipleKeys(): void
    {
        $renderer = new SnippetRenderer(['pattern', 'file_path']);
        $result = $renderer->render('grep_file_content', '{"pattern": "TODO", "file_path": "src/Foo.php"}');

        $this->assertSame("● grep_file_content(\n  pattern: TODO\n  file_path: src/Foo.php)", $this->stripAnsiCodes($result));
    }

    public function testRenderSkipsMissingKeys(): void
    {
        $renderer = new SnippetRenderer(['pattern', 'file_path']);
        $result = $renderer->render('grep_file_content', '{"pattern": "TODO"}');

        $this->assertSame('● grep_file_content(TODO)', $this->stripAnsiCodes($result));
    }

    public function testRenderFallsBackToRawArgumentsWhenNoKeysMatch(): void
    {
        $renderer = new SnippetRenderer(['file_path']);
        $result = $renderer->render('some_tool', '{"other_key": "value"}');

        $this->assertSame('● some_tool()', $this->stripAnsiCodes($result));
    }

    public function testRenderFallsBackToRawArgumentsOnInvalidJson(): void
    {
        $renderer = new SnippetRenderer(['file_path']);
        $result = $renderer->render('some_tool', 'not-json');

        $this->assertSame('● some_tool()', $this->stripAnsiCodes($result));
    }

    public function testRenderEncodesNonStringValues(): void
    {
        $renderer = new SnippetRenderer(['args']);
        $result = $renderer->render('bash', '{"args": ["--flag", "--verbose"]}');

        $this->assertSame('● bash(["--flag","--verbose"])', $this->stripAnsiCodes($result));
    }

    public function testRenderWithEmptyKeysArray(): void
    {
        $renderer = new SnippetRenderer([]);
        $result = $renderer->render('read_file', '{"file_path": "foo.php"}');

        $this->assertSame('● read_file()', $this->stripAnsiCodes($result));
    }
}
