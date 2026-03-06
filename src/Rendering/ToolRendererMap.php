<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

use NeuronCore\Synapse\Rendering\Renderers\FileChangeRenderer;
use NeuronCore\Synapse\Rendering\Renderers\GenericRenderer;
use NeuronCore\Synapse\Rendering\Renderers\SnippetRenderer;

class ToolRendererMap
{
    /** @var array<string, ToolRenderer> */
    private array $map = [];

    public function __construct(private readonly ToolRenderer $fallback) {}

    public function register(string $toolName, ToolRenderer $renderer): self
    {
        $this->map[$toolName] = $renderer;
        return $this;
    }

    public function render(string $toolName, string $arguments): string
    {
        return ($this->map[$toolName] ?? $this->fallback)->render($toolName, $arguments);
    }

    public static function default(): self
    {
        $diffRenderer = new DiffRenderer();
        $fileChange = new FileChangeRenderer($diffRenderer);

        return (new self(new GenericRenderer()))
            // FileSystemToolkit read-only tools
            ->register('read_file',                  new SnippetRenderer(['file_path']))
            ->register('preview_file',               new SnippetRenderer(['file_path']))
            ->register('parse_file',                 new SnippetRenderer(['file_path']))
            ->register('grep_file_content',          new SnippetRenderer(['pattern', 'file_path']))
            ->register('glob_path',                  new SnippetRenderer(['pattern', 'directory']))
            ->register('describe_directory_content', new SnippetRenderer(['directory']))
            // Execution
            ->register('bash',                       new SnippetRenderer(['command']))
            // File change tools (CodingToolkit)
            ->register('write_file',                 $fileChange)
            ->register('edit_file',                  $fileChange)
            ->register('patch_file',                 $fileChange)
            ->register('create_file',                $fileChange)
            ->register('delete_file',                $fileChange);
    }
}
