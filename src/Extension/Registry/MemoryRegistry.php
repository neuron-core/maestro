<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Registry;

use InvalidArgumentException;

use function array_values;
use function sprintf;
use function is_file;
use function is_readable;
use function count;

/**
 * Registry for memory files that extensions can register.
 *
 * Extensions can register memory file paths that will be loaded
 * and injected into the AI agent's system prompt via MemoryMiddleware.
 */
class MemoryRegistry
{
    /** @var array<string, string> Map of memory key to file path */
    protected array $memories = [];

    /**
     * Register a memory file.
     *
     * @param string $key Unique identifier for this memory (e.g., "extension_name.memory_name")
     * @param string $filePath Absolute path to the memory file
     * @throws InvalidArgumentException if a memory with the same key is already registered
     * @throws InvalidArgumentException if the file does not exist or is not readable
     */
    public function register(string $key, string $filePath): void
    {
        if (isset($this->memories[$key])) {
            throw new InvalidArgumentException(
                sprintf('Memory with key "%s" is already registered.', $key)
            );
        }

        if (!is_file($filePath)) {
            throw new InvalidArgumentException(
                sprintf('Memory file does not exist: "%s".', $filePath)
            );
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException(
                sprintf('Memory file is not readable: "%s".', $filePath)
            );
        }

        $this->memories[$key] = $filePath;
    }

    /**
     * Get a registered memory file path by key.
     */
    public function get(string $key): ?string
    {
        return $this->memories[$key] ?? null;
    }

    /**
     * Check if a memory is registered.
     */
    public function has(string $key): bool
    {
        return isset($this->memories[$key]);
    }

    /**
     * Get all registered memories as key => path array.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->memories;
    }

    /**
     * Get all memory file paths as a list.
     *
     * @return string[]
     */
    public function paths(): array
    {
        return array_values($this->memories);
    }

    /**
     * Remove a memory by key.
     *
     * @return bool True if removed, false if not found
     */
    public function remove(string $key): bool
    {
        if (!isset($this->memories[$key])) {
            return false;
        }

        unset($this->memories[$key]);
        return true;
    }

    /**
     * Get the count of registered memories.
     */
    public function count(): int
    {
        return count($this->memories);
    }
}
