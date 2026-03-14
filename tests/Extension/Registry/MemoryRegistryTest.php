<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Extension\Registry;

use InvalidArgumentException;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function file_exists;
use function file_put_contents;

class MemoryRegistryTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'maestro_memory_');
        file_put_contents($this->tempFile, 'Test memory content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testRegisterAddsMemory(): void
    {
        $registry = new MemoryRegistry();
        $registry->register('test.memory', $this->tempFile);

        $this->assertTrue($registry->has('test.memory'));
        $this->assertSame($this->tempFile, $registry->get('test.memory'));
    }

    public function testRegisterThrowsForDuplicateKey(): void
    {
        $registry = new MemoryRegistry();
        $registry->register('test.memory', $this->tempFile);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory with key "test.memory" is already registered.');

        $registry->register('test.memory', $this->tempFile);
    }

    public function testRegisterThrowsForNonExistentFile(): void
    {
        $registry = new MemoryRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory file does not exist');

        $registry->register('test.memory', '/nonexistent/file.md');
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $registry = new MemoryRegistry();

        $this->assertNull($registry->get('nonexistent'));
    }

    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $registry = new MemoryRegistry();

        $this->assertFalse($registry->has('nonexistent'));
    }

    public function testAllReturnsAllMemories(): void
    {
        $registry = new MemoryRegistry();
        $registry->register('first', $this->tempFile);

        $tempFile2 = tempnam(sys_get_temp_dir(), 'maestro_memory_2_');
        file_put_contents($tempFile2, 'Another memory');
        $registry->register('second', $tempFile2);

        $all = $registry->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('first', $all);
        $this->assertArrayHasKey('second', $all);

        unlink($tempFile2);
    }

    public function testPathsReturnsAllFilePaths(): void
    {
        $registry = new MemoryRegistry();
        $registry->register('first', $this->tempFile);

        $paths = $registry->paths();

        $this->assertCount(1, $paths);
        $this->assertContains($this->tempFile, $paths);
    }

    public function testRemoveReturnsTrueForExistingKey(): void
    {
        $registry = new MemoryRegistry();
        $registry->register('test.memory', $this->tempFile);

        $result = $registry->remove('test.memory');

        $this->assertTrue($result);
        $this->assertFalse($registry->has('test.memory'));
    }

    public function testRemoveReturnsFalseForNonExistentKey(): void
    {
        $registry = new MemoryRegistry();

        $result = $registry->remove('nonexistent');

        $this->assertFalse($result);
    }

    public function testCountReturnsNumberOfMemories(): void
    {
        $registry = new MemoryRegistry();

        $this->assertSame(0, $registry->count());

        $registry->register('first', $this->tempFile);

        $this->assertSame(1, $registry->count());

        $tempFile2 = tempnam(sys_get_temp_dir(), 'maestro_memory_2_');
        file_put_contents($tempFile2, 'Another memory');
        $registry->register('second', $tempFile2);

        $this->assertSame(2, $registry->count());

        unlink($tempFile2);
    }
}
