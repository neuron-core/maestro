<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Rendering\Renderers;

use NeuronCore\Maestro\Rendering\Renderers\FileChangeRenderer;
use NeuronCore\Maestro\Rendering\ToolRenderer;
use PHPUnit\Framework\TestCase;

class FileChangeRendererTest extends TestCase
{
    private FileChangeRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FileChangeRenderer();
    }

    public function testImplementsToolRenderer(): void
    {
        $this->assertInstanceOf(ToolRenderer::class, $this->renderer);
    }

    public function testFallsBackToGenericRendererWhenNoPath(): void
    {
        $result = $this->renderer->render('write_file', '{"content": "hello"}');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testFallsBackToGenericRendererWhenNoContent(): void
    {
        $result = $this->renderer->render('edit_file', '{"file_path": "/tmp/foo.php"}');

        $this->assertStringContainsString('edit_file', $result);
        $this->assertStringContainsString('/tmp/foo.php', $result);
    }

    public function testFallsBackToGenericRendererOnInvalidJson(): void
    {
        $result = $this->renderer->render('write_file', 'not-json');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('not-json', $result);
    }

    public function testRendersHeaderWithToolNameAndPath(): void
    {
        $result = $this->renderer->render('write_file', '{"file_path": "/tmp/foo.php", "content": "<?php"}');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('/tmp/foo.php', $result);
    }

    public function testAcceptsPathKeyAsAlternative(): void
    {
        $result = $this->renderer->render('delete_file', '{"path": "/tmp/bar.php", "content": "x"}');

        $this->assertStringContainsString('/tmp/bar.php', $result);
    }

    public function testDiffMetadataLinesAreFiltered(): void
    {
        $result = $this->renderer->render('write_file', '{"file_path": "/tmp/test.php", "content": "new"}');

        // These lines should NOT be in the output
        $this->assertStringNotContainsString('---', $result);
        $this->assertStringNotContainsString('+++', $result);
        $this->assertStringNotContainsString('@@', $result);
        $this->assertStringNotContainsString('No newline at end of file', $result);
    }

    public function testColorizeDiffHandlesEmptyDiff(): void
    {
        // Create an existing file with content, then write the same content
        $result = $this->renderer->render('write_file', '{"file_path": "/tmp/foo.php", "content": "same"}');

        $this->assertStringContainsString('write_file', $result);
        $this->assertStringContainsString('/tmp/foo.php', $result);
    }

}
