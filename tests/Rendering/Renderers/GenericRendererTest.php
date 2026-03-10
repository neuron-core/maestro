<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Rendering\Renderers;

use NeuronCore\Maestro\Rendering\Renderers\GenericRenderer;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;

use function preg_replace;

class GenericRendererTest extends TestCase
{
    private GenericRenderer $renderer;

    private function stripAnsiCodes(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    protected function setUp(): void
    {
        $this->renderer = new GenericRenderer();
    }

    public function testImplementsToolRenderer(): void
    {
        $this->assertInstanceOf(ToolRenderer::class, $this->renderer);
    }

    public function testRenderFormatsOutput(): void
    {
        $result = $this->renderer->render('read_file', '{"file_path": "foo.php"}');

        $this->assertStringContainsString('read_file', $this->stripAnsiCodes($result));
        $this->assertStringContainsString('foo.php', $this->stripAnsiCodes($result));
    }

    public function testRenderWithEmptyArguments(): void
    {
        $result = $this->renderer->render('list_files', '');

        $this->assertStringContainsString('list_files', $this->stripAnsiCodes($result));
    }

    public function testRenderWithDifferentToolNames(): void
    {
        $result = $this->renderer->render('bash', 'ls -la');

        $this->assertStringContainsString('bash', $this->stripAnsiCodes($result));
        $this->assertStringContainsString('ls -la', $this->stripAnsiCodes($result));
    }
}
