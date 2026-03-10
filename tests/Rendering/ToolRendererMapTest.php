<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Rendering;

use NeuronCore\Maestro\Rendering\ToolRenderer;
use NeuronCore\Maestro\Rendering\ToolRendererMap;
use PHPUnit\Framework\TestCase;

use function preg_replace;

class ToolRendererMapTest extends TestCase
{
    private function stripAnsiCodes(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    public function testRegisterReturnsSelf(): void
    {
        $fallback = $this->createMock(ToolRenderer::class);
        $map = new ToolRendererMap($fallback);
        $renderer = $this->createMock(ToolRenderer::class);

        $result = $map->register('some_tool', $renderer);

        $this->assertSame($map, $result);
    }

    public function testRenderUsesRegisteredRenderer(): void
    {
        $fallback = $this->createMock(ToolRenderer::class);
        $fallback->expects($this->never())->method('render');

        $registered = $this->createMock(ToolRenderer::class);
        $registered->expects($this->once())
            ->method('render')
            ->with('my_tool', '{}')
            ->willReturn('registered output');

        $map = (new ToolRendererMap($fallback))->register('my_tool', $registered);

        $this->assertSame('registered output', $map->render('my_tool', '{}'));
    }

    public function testRenderUsesFallbackForUnknownTool(): void
    {
        $fallback = $this->createMock(ToolRenderer::class);
        $fallback->expects($this->once())
            ->method('render')
            ->with('unknown_tool', '{}')
            ->willReturn('fallback output');

        $map = new ToolRendererMap($fallback);

        $this->assertSame('fallback output', $map->render('unknown_tool', '{}'));
    }

    public function testDefaultReturnsToolRendererMapInstance(): void
    {
        $map = ToolRendererMap::default();

        $this->assertInstanceOf(ToolRendererMap::class, $map);
    }

    public function testDefaultHasReadFileToolRegistered(): void
    {
        $map = ToolRendererMap::default();

        $result = $map->render('read_file', '{"file_path": "foo.php"}');

        $this->assertStringContainsString('read_file', $this->stripAnsiCodes($result));
        $this->assertStringContainsString('foo.php', $this->stripAnsiCodes($result));
    }

    public function testDefaultHasBashToolRegistered(): void
    {
        $map = ToolRendererMap::default();

        $result = $map->render('bash', '{"command": "ls -la"}');

        $this->assertStringContainsString('bash', $this->stripAnsiCodes($result));
        $this->assertStringContainsString('ls -la', $this->stripAnsiCodes($result));
    }

    public function testDefaultFallsBackToGenericRendererForUnknownTool(): void
    {
        $map = ToolRendererMap::default();

        $result = $map->render('unknown_tool', '{"some": "args"}');

        $this->assertStringContainsString('unknown_tool', $this->stripAnsiCodes($result));
    }

    public function testDefaultHasWriteFileRegistered(): void
    {
        $map = ToolRendererMap::default();

        $result = $map->render('write_file', '{"file_path": "/tmp/test.php", "content": "<?php"}');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('/tmp/test.php', $result);
    }
}
