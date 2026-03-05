<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Rendering;

use NeuronCore\Synapse\Rendering\CodingToolResultRenderer;
use NeuronCore\Synapse\Rendering\DiffRenderer;
use PHPUnit\Framework\TestCase;

use function json_encode;

class CodingToolResultRendererTest extends TestCase
{
    private CodingToolResultRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CodingToolResultRenderer();
    }

    public function testCanRenderWriteFileResult(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'write',
            'file_path' => '/test/file.php',
        ]);

        $this->assertTrue($this->renderer->canRender('write_file', $result));
    }

    public function testCannotRenderFileSystemToolResult(): void
    {
        $result = 'File contents...';
        $this->assertFalse($this->renderer->canRender('read_file', $result));
    }

    public function testCanRenderEditFileResult(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'edit',
        ]);

        $this->assertTrue($this->renderer->canRender('edit_file', $result));
    }

    public function testCanRenderPatchFileResult(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'patch',
        ]);

        $this->assertTrue($this->renderer->canRender('patch_file', $result));
    }

    public function testCanRenderCreateFileResult(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'create',
        ]);

        $this->assertTrue($this->renderer->canRender('create_file', $result));
    }

    public function testCanRenderDeleteFileResult(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'delete',
        ]);

        $this->assertTrue($this->renderer->canRender('delete_file', $result));
    }

    public function testCannotRenderInvalidJson(): void
    {
        $result = 'not a JSON result';
        $this->assertFalse($this->renderer->canRender('write_file', $result));
    }

    public function testCannotRenderMissingStatus(): void
    {
        $result = json_encode([
            'operation' => 'write',
        ]);

        $this->assertFalse($this->renderer->canRender('write_file', $result));
    }

    public function testCannotRenderMissingOperation(): void
    {
        $result = json_encode([
            'status' => 'proposed',
        ]);

        $this->assertFalse($this->renderer->canRender('write_file', $result));
    }

    public function testRenderErrorResult(): void
    {
        $result = json_encode([
            'status' => 'error',
            'message' => 'File not found',
            'operation' => 'write',
            'file_path' => '/test/file.php',
        ]);

        $output = $this->renderer->render('write_file', $result);

        $this->assertStringContainsString('Error:', $output);
        $this->assertStringContainsString('File not found', $output);
    }

    public function testRenderSuccessResultWithDiff(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'write',
            'file_path' => '/test/file.php',
            'stats' => [
                'added' => 5,
                'removed' => 2,
                'changed' => 1,
            ],
            'diff' => '--- a/test.php\n+++ b/test.php\n-removed\n+added\n',
            'message' => 'Proposed overwrite',
        ]);

        $output = $this->renderer->render('write_file', $result);

        $this->assertStringContainsString('Operation: WRITE', $output);
        $this->assertStringContainsString('File: /test/file.php', $output);
        $this->assertStringContainsString('Changes: +5, -2, ~1', $output);
        $this->assertStringContainsString('Proposed overwrite', $output);
    }

    public function testRenderSuccessResultWithSize(): void
    {
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'create',
            'file_path' => '/test/new.php',
            'size' => 1024,
            'diff' => '--- /dev/null\n+++ b/test.php\n@@ -0,0 +1,10 @@\ncontent\n',
        ]);

        $output = $this->renderer->render('create_file', $result);

        $this->assertStringContainsString('Operation: CREATE', $output);
        $this->assertStringContainsString('File: /test/new.php', $output);
        $this->assertStringContainsString('Size: 1024 bytes', $output);
    }

    public function testRenderFallbacksToSimpleDisplayForInvalidJson(): void
    {
        $result = 'Not a JSON result';
        $output = $this->renderer->render('write_file', $result);

        $this->assertStringContainsString('write_file', $output);
        $this->assertStringContainsString('Not a JSON result', $output);
    }

    public function testGetDiffRendererReturnsDiffRenderer(): void
    {
        $diffRenderer = $this->renderer->getDiffRenderer();

        $this->assertInstanceOf(DiffRenderer::class, $diffRenderer);
    }

    public function testUsesCustomDiffRenderer(): void
    {
        $mockRenderer = $this->createMock(DiffRenderer::class);
        $mockRenderer->expects($this->once())
            ->method('render')
            ->willReturn('custom diff');

        $renderer = new CodingToolResultRenderer($mockRenderer);

        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'write',
            'file_path' => '/test/file.php',
            'diff' => 'some diff',
        ]);

        $output = $renderer->render('write_file', $result);

        $this->assertStringContainsString('custom diff', $output);
    }
}
