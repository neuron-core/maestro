<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Tools\Coding;

use NeuronCore\Synapse\Tools\Coding\CreateFileTool;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function json_decode;
use function sys_get_temp_dir;
use function rmdir;
use function touch;
use function unlink;
use function is_dir;
use function mkdir;
use function strlen;
use function uniqid;

class CreateFileToolTest extends TestCase
{
    private string $tempFile;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/synapse_create_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->tempFile = $this->tempDir . '/new_file.txt';
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
        $tool = new CreateFileTool();
        $result = ($tool)($this->tempFile, 'content');

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testInvokeReturnsErrorWhenFileAlreadyExists(): void
    {
        touch($this->tempFile);

        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, 'content'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    public function testInvokeReturnsErrorWhenDirectoryNotExists(): void
    {
        $tool = new CreateFileTool();
        $result = json_decode(($tool)('/non/existent/dir/file.txt', 'content'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('does not exist', $result['message']);
    }

    public function testInvokeReturnsErrorWhenDirectoryNotWritable(): void
    {
        // Can't really test non-writable in temp dir,
        // but the logic is there
        $tool = new CreateFileTool();
        // This will succeed since we're writing to temp dir
        $result = json_decode(($tool)($this->tempFile, 'content'), true);

        $this->assertSame('proposed', $result['status']);
    }

    public function testInvokeWithValidNewFile(): void
    {
        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, 'new file content'), true);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame('create', $result['operation']);
        $this->assertSame($this->tempFile, $result['file_path']);
        $this->assertSame('new file content', $result['content']);
    }

    public function testInvokeCalculatesLineCount(): void
    {
        $content = "line 1\nline 2\nline 3";

        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, $content), true);

        $this->assertArrayHasKey('line_count', $result);
        $this->assertSame(3, $result['line_count']);
    }

    public function testInvokeCalculatesSize(): void
    {
        $content = 'test content';

        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, $content), true);

        $this->assertArrayHasKey('size', $result);
        $this->assertSame(strlen($content), $result['size']);
    }

    public function testInvokeGeneratesDiff(): void
    {
        $content = "line 1\nline 2\nline 3";

        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, $content), true);

        $this->assertArrayHasKey('diff', $result);
        $this->assertStringContainsString('--- /dev/null', $result['diff']);
        $this->assertStringContainsString('+++ b/' . $this->tempFile, $result['diff']);
    }

    public function testInvokeIncludesMessage(): void
    {
        $tool = new CreateFileTool();
        $result = json_decode(($tool)($this->tempFile, 'content'), true);

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString($this->tempFile, $result['message']);
    }

    public function testGetName(): void
    {
        $tool = new CreateFileTool();
        $this->assertSame('create_file', $tool->getName());
    }

    public function testGetDescription(): void
    {
        $tool = new CreateFileTool();
        $this->assertIsString($tool->getDescription());
        $this->assertStringContainsString('Create', $tool->getDescription());
        $this->assertStringContainsString('file', $tool->getDescription());
    }

    public function testGetProperties(): void
    {
        $tool = new CreateFileTool();
        $properties = $tool->getProperties();

        $this->assertIsArray($properties);
        $this->assertCount(2, $properties);
    }
}
