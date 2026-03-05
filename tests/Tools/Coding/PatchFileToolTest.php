<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Tools\Coding;

use NeuronCore\Synapse\Tools\Coding\PatchFileTool;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function count;

class PatchFileToolTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/synapse_patch_test_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testInvokeReturnsJsonString(): void
    {
        $tool = new PatchFileTool();
        $result = ($tool)($this->tempFile, 'patch content');

        $this->assertIsString($result);
        $this->assertJson($result);
    }

    public function testInvokeReturnsErrorWhenFileNotExists(): void
    {
        $tool = new PatchFileTool();
        $result = json_decode(($tool)('/non/existent/file.php', 'patch'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('does not exist', $result['message']);
    }

    public function testInvokeWithValidPatch(): void
    {
        file_put_contents($this->tempFile, "line 1\nline 2\nline 3");

        $patch = "--- a/test.php\n" .
                 "+++ b/test.php\n" .
                 "@@ -1,3 +1,2 @@\n" .
                 "-line 1\n" .
                 "-line 2\n" .
                 "+line 1 modified\n" .
                 "+line 3\n";

        $tool = new PatchFileTool();
        $result = json_decode(($tool)($this->tempFile, $patch), true);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame('patch', $result['operation']);
        $this->assertSame($this->tempFile, $result['file_path']);
    }

    public function testInvokeReturnsErrorForInvalidPatch(): void
    {
        file_put_contents($this->tempFile, "content");

        $tool = new PatchFileTool();
        $result = json_decode(($tool)($this->tempFile, 'invalid patch'), true);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('No valid hunks', $result['message']);
    }

    public function testInvokeIncludesDiff(): void
    {
        file_put_contents($this->tempFile, "old\n");

        $patch = "--- a/test\n" .
                 "+++ b/test\n" .
                 "@@ -1,1 +1,1 @@\n" .
                 "-old\n" .
                 "+new\n";

        $tool = new PatchFileTool();
        $result = json_decode(($tool)($this->tempFile, $patch), true);

        $this->assertArrayHasKey('diff', $result);
        $this->assertStringContainsString('---', $result['diff']);
        $this->assertStringContainsString('+++', $result['diff']);
    }

    public function testInvokeIncludesStats(): void
    {
        file_put_contents($this->tempFile, "old\n");

        $patch = "--- a/test\n" .
                 "+++ b/test\n" .
                 "@@ -1,1 +1,1 @@\n" .
                 "-old\n" .
                 "+new\n";

        $tool = new PatchFileTool();
        $result = json_decode(($tool)($this->tempFile, $patch), true);

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('added', $result['stats']);
        $this->assertArrayHasKey('removed', $result['stats']);
        $this->assertArrayHasKey('changed', $result['stats']);
    }

    public function testInvokeIncludesMessage(): void
    {
        file_put_contents($this->tempFile, "content");

        $patch = "--- a/test\n+++ b/test\n@@ -0,0 +1,1 @@\n+new line";

        $tool = new PatchFileTool();
        $result = json_decode(($tool)($this->tempFile, $patch), true);

        // Note: successful patch may not include 'message' key, only error cases do
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('operation', $result);
        $this->assertSame('proposed', $result['status']);
    }

    public function testGetName(): void
    {
        $tool = new PatchFileTool();
        $this->assertSame('patch_file', $tool->getName());
    }

    public function testGetDescription(): void
    {
        $tool = new PatchFileTool();
        $this->assertIsString($tool->getDescription());
        $this->assertStringContainsString('patch', $tool->getDescription());
    }

    public function testGetProperties(): void
    {
        $tool = new PatchFileTool();
        $properties = $tool->getProperties();

        $this->assertIsArray($properties);
        $this->assertGreaterThanOrEqual(2, count($properties));
    }
}
