<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Tools\Coding;

use NeuronCore\Synapse\Tools\Coding\WriteFileTool;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function unlink;
use function count;
use function uniqid;

class WriteFileToolTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/synapse_write_test_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testInvokeReturnsJsonString(): void
    {
        $tool = new WriteFileTool();
        $result = ($tool)($this->tempFile, 'content');

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testInvokeWithNewFile(): void
    {
        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, 'test content'), true);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame('write', $result['operation']);
        $this->assertSame($this->tempFile, $result['file_path']);
        $this->assertTrue($result['is_new']);
    }

    public function testInvokeWithExistingFile(): void
    {
        // Create file first
        file_put_contents($this->tempFile, 'old content');

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, 'new content'), true);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame('write', $result['operation']);
        $this->assertSame($this->tempFile, $result['file_path']);
        $this->assertFalse($result['is_new']);
    }

    public function testInvokeReturnsErrorWhenDirectoryNotWritable(): void
    {
        $tool = new WriteFileTool();
        $result = json_decode(($tool)('/non/existent/dir/file.txt', 'content'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('not writable', $result['message']);
    }

    public function testInvokeIncludesOriginalContentForExistingFile(): void
    {
        file_put_contents($this->tempFile, 'original content');

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, 'new content'), true);

        $this->assertSame('original content', $result['original']);
    }

    public function testInvokeIncludesNewContent(): void
    {
        $newContent = 'line 1' . "\n" . 'line 2';
        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, $newContent), true);

        $this->assertSame($newContent, $result['new']);
    }

    public function testInvokeGeneratesDiff(): void
    {
        file_put_contents($this->tempFile, "old line 1\nold line 2");

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, "new line 1\nnew line 2"), true);

        $this->assertArrayHasKey('diff', $result);
        $this->assertStringContainsString('---', $result['diff']);
        $this->assertStringContainsString('+++', $result['diff']);
    }

    public function testInvokeIncludesStats(): void
    {
        file_put_contents($this->tempFile, "line 1\nline 2");

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, "line 1\nline 2\nline 3"), true);

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('added', $result['stats']);
        $this->assertArrayHasKey('removed', $result['stats']);
        $this->assertArrayHasKey('changed', $result['stats']);
    }

    public function testInvokeIncludesMessage(): void
    {
        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, 'content'), true);

        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString($this->tempFile, $result['message']);
    }

    public function testDiffIsEmptyWhenContentIsSame(): void
    {
        $content = "same content\n";
        file_put_contents($this->tempFile, $content);

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, $content), true);

        $this->assertStringContainsString('No changes detected', $result['diff']);
    }

    public function testStatsAreZeroForNoChanges(): void
    {
        $content = "same content\n";
        file_put_contents($this->tempFile, $content);

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, $content), true);

        $this->assertSame(0, $result['stats']['added']);
        $this->assertSame(0, $result['stats']['removed']);
        $this->assertSame(0, $result['stats']['changed']);
    }

    public function testStatsCorrectForAdditions(): void
    {
        file_put_contents($this->tempFile, "old\n");
        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, "old\nnew line"), true);

        $this->assertSame(1, $result['stats']['added']);
    }

    public function testStatsCorrectForRemovals(): void
    {
        file_put_contents($this->tempFile, "old\nnew\n");

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, "old"), true);

        $this->assertSame(1, $result['stats']['removed']);
    }

    public function testStatsCorrectForChanged(): void
    {
        // Changed = min(added, removed) where lines are at same position
        // We can't easily test this without examining the actual diff logic,
        // but we can test that it's calculated correctly
        file_put_contents($this->tempFile, "old\nold2");

        $tool = new WriteFileTool();
        $result = json_decode(($tool)($this->tempFile, "modified\nmodified2"), true);

        // When replacing one line and adding one line, changed should be 1
        $this->assertSame(1, $result['stats']['changed']);
    }

    public function testGetName(): void
    {
        $tool = new WriteFileTool();
        $this->assertSame('write_file', $tool->getName());
    }

    public function testGetDescription(): void
    {
        $tool = new WriteFileTool();
        $this->assertIsString($tool->getDescription());
        $this->assertStringContainsString('Write', $tool->getDescription());
    }

    public function testGetProperties(): void
    {
        $tool = new WriteFileTool();
        $properties = $tool->getProperties();

        $this->assertIsArray($properties);
        $this->assertGreaterThanOrEqual(2, count($properties));
    }
}
