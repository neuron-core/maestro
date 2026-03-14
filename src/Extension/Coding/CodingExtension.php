<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Coding;

use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\ExtensionInterface;

use function realpath;

/**
 * Coding extension that provides the core coding agent instructions.
 *
 * This extension registers the coding.md memory file which contains
 * the detailed system prompt for the Maestro coding agent.
 */
class CodingExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'maestro.coding';
    }

    public function register(ExtensionApi $api): void
    {
        // Register the coding.md memory file
        $codingMemoryPath = realpath(__DIR__.'/memories/coding.md');

        if ($codingMemoryPath !== false) {
            $api->registerMemory('coding', $codingMemoryPath);
        }
    }
}
