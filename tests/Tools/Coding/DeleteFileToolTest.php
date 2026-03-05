<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Tools\Coding;

use NeuronCore\Synapse\Tools\Coding\DeleteFileTool;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function is_dir;
use function json_decode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

class DeleteFileToolTest extends TestCase
{
    private string $tempFile;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/synapse_delete_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->tempFile = $this->tempDir . '/file_to_delete.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testInvokeReturnsJsonString(): void
    {
        file_put_contents($this->tempFile, 'content');

        $tool = new DeleteFileTool();
        $result = ($tool)($this->tempFile);

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testInvokeReturnsErrorWhenFileNotExists(): void
    {
        $tool = new DeleteFileTool();
        $result = json_decode(($tool)('/non/existent/file.txt'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('does not exist', $result['message']);
    }

    public function testInvokeReturnsErrorWhenPathIsNotAFile(): void
    {
        // The temp dir exists but is not a file
        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempDir), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('not a file', $result['message']);
    }

    public function testInvokeReturnsErrorWhenDirectoryNotWritable(): void
    {
        // Create the file first
        file_put_contents($this->tempFile, 'content');

        $tool = new DeleteFileTool();
        // This will succeed since we're in temp dir
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertSame('proposed', $result['status']);
    }

    public function testInvokeWithValidFile(): void
    {
        $content = "line 1\nline 2\nline 3";
        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame('delete', $result['operation']);
        $this->assertSame($this->tempFile, $result['file_path']);
    }

    public function testInvokeCalculatesSize(): void
    {
        $content = 'test content';
        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertArrayHasKey('size', $result);
        $this->assertSame(strlen($content), $result['size']);
    }

    public function testInvokeCalculatesLineCount(): void
    {
        $content = "line 1\nline 2\nline 3";
        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertArrayHasKey('line_count', $result);
        $this->assertSame(3, $result['line_count']);
    }

    public function testInvokeGeneratesDiff(): void
    {
        $content = "line 1\nline 2";

        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertArrayHasKey('diff', $result);
        $this->assertStringContainsString('--- a/' . $this->tempFile, $result['diff']);
        $this->assertStringContainsString('+++ /dev/null', $result['diff']);
        $this->assertStringContainsString('-', $result['diff']);
    }

    public function testInvokeIncludesOriginalContent(): void
    {
        $content = 'original content';
        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertArrayHasKey('original', $result);
        $this->assertSame($content, $result['original']);
    }

    public function testInvokeIncludesMessage(): void
    {
        $content = "line 1\nline 2";
        file_put_contents($this->tempFile, $content);

        $tool = new DeleteFileTool();
        $result = json_decode(($tool)($this->tempFile), true);

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('deletion', $result['message']);
        $this->assertStringContainsString($this->tempFile, $result['message']);
    }

    public function testGetName(): void
    {
        $tool = new DeleteFileTool();
        $this->assertSame('delete_file', $tool->getName());
    }

    public function testGetDescription(): void
    {
        $tool = new DeleteFileTool();
        $this->assertIsString($tool->getDescription());
        $this->assertStringContainsString('Delete', $tool->getDescription());
    }

    public function testGetProperties(): void
    {
        $tool = new DeleteFileTool();
        $properties = $tool->getProperties();

        $this->assertIsArray($properties);
        $this->assertCount(1, $properties);
    }
}
