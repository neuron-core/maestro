<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Rendering\Renderers;

use NeuronCore\Synapse\Rendering\DiffRenderer;
use NeuronCore\Synapse\Rendering\Renderers\FileChangeRenderer;
use NeuronCore\Synapse\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;

class FileChangeRendererTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $diffRenderer;
    private FileChangeRenderer $renderer;

    protected function setUp(): void
    {
        $this->diffRenderer = $this->createMock(DiffRenderer::class);
        $this->renderer = new FileChangeRenderer($this->diffRenderer);
    }

    public function testImplementsToolRenderer(): void
    {
        $this->assertInstanceOf(ToolRenderer::class, $this->renderer);
    }

    public function testFallsBackToGenericRendererWhenNoPath(): void
    {
        $this->diffRenderer->expects($this->never())->method('render');

        $result = $this->renderer->render('write_file', '{"content": "hello"}');

        $this->assertSame("● write_file( {\"content\": \"hello\"} )\n", $result);
    }

    public function testFallsBackToGenericRendererWhenNoContent(): void
    {
        $this->diffRenderer->expects($this->never())->method('render');

        $result = $this->renderer->render('edit_file', '{"file_path": "/tmp/foo.php"}');

        $this->assertSame("● edit_file( {\"file_path\": \"/tmp/foo.php\"} )\n", $result);
    }

    public function testFallsBackToGenericRendererOnInvalidJson(): void
    {
        $this->diffRenderer->expects($this->never())->method('render');

        $result = $this->renderer->render('write_file', 'not-json');

        $this->assertSame("● write_file( not-json )\n", $result);
    }

    public function testRendersHeaderWithToolNameAndPath(): void
    {
        $this->diffRenderer->method('render')->willReturn('');

        $result = $this->renderer->render('write_file', '{"file_path": "/tmp/foo.php", "content": "<?php"}');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('/tmp/foo.php', $result);
    }

    public function testIncludesDiffRendererOutput(): void
    {
        $this->diffRenderer->method('render')->willReturn('DIFF_OUTPUT');

        $result = $this->renderer->render('write_file', '{"file_path": "/tmp/foo.php", "content": "new content"}');

        $this->assertStringContainsString('DIFF_OUTPUT', $result);
    }

    public function testAcceptsPathKeyAsAlternative(): void
    {
        $this->diffRenderer->method('render')->willReturn('');

        $result = $this->renderer->render('delete_file', '{"path": "/tmp/bar.php", "content": "x"}');

        $this->assertStringContainsString('/tmp/bar.php', $result);
    }

    public function testDiffRendererReceivesDiffString(): void
    {
        $this->diffRenderer
            ->expects($this->once())
            ->method('render')
            ->with($this->isType('string'))
            ->willReturn('');

        $this->renderer->render('write_file', '{"file_path": "/tmp/foo.php", "content": "hello"}');
    }
}
