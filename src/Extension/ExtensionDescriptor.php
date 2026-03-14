<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension;

/**
 * Describes a loaded extension and its state.
 */
class ExtensionDescriptor
{
    public function __construct(
        public readonly string $className,
        public readonly string $name,
        public bool $enabled = true,
        public array $config = [],
        public readonly string $source = 'manual',
    ) {
    }

    /**
     * Create a disabled descriptor.
     */
    public static function disabled(string $className, string $name): self
    {
        return new self($className, $name, enabled: false);
    }
}
