<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

use NeuronCore\Synapse\Rendering\Renderers\EditFileRenderer;
use NeuronCore\Synapse\Rendering\Renderers\FileChangeRenderer;
use NeuronCore\Synapse\Rendering\Renderers\GenericRenderer;
use NeuronCore\Synapse\Rendering\Renderers\SnippetRenderer;

class ToolRendererMap
{
    /** @var array<string, ToolRenderer> */
    protected array $map = [];

    public function __construct(private readonly ToolRenderer $fallback)
    {
    }

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
            ->register('read_file', new SnippetRenderer(['file_path']))
            ->register('preview_file', new SnippetRenderer(['file_path']))
            ->register('parse_file', new SnippetRenderer(['file_path']))
            ->register('grep_file_content', new SnippetRenderer(['pattern', 'file_path']))
            ->register('glob_path', new SnippetRenderer(['pattern', 'directory']))
            ->register('describe_directory_content', new SnippetRenderer(['directory']))
            ->register('bash', new SnippetRenderer(['command']))
            ->register('edit_file', new EditFileRenderer())
            ->register('write_file', $fileChange)
            ->register('delete_file', $fileChange);
    }
}
