<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering\Renderers;

use NeuronCore\Synapse\Rendering\DiffRenderer;
use NeuronCore\Synapse\Rendering\ToolRenderer;

use function escapeshellarg;
use function fclose;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function json_decode;
use function shell_exec;
use function sprintf;
use function str_replace;
use function stream_get_meta_data;
use function tmpfile;

class FileChangeRenderer implements ToolRenderer
{
    public function __construct(private readonly DiffRenderer $diffRenderer) {}

    public function render(string $toolName, string $arguments): string
    {
        $args = json_decode($arguments, true) ?? [];
        $path = $args['file_path'] ?? $args['path'] ?? null;

        if ($path === null) {
            return (new GenericRenderer())->render($toolName, $arguments);
        }

        $current = file_exists($path) ? (string) file_get_contents($path) : '';

        // write_file / create_file: full content replacement
        if (isset($args['content'])) {
            $diff = $this->generateDiff($path, $current, $args['content']);
            return $this->header($toolName, $path) . $this->diffRenderer->render($diff);
        }

        return (new GenericRenderer())->render($toolName, $arguments);
    }

    private function header(string $toolName, string $path): string
    {
        return sprintf("\n● %s( %s )\n\n", $toolName, $path);
    }

    private function generateDiff(string $filename, string $current, string $proposed): string
    {
        $oldFile = tmpfile();
        $newFile = tmpfile();

        fwrite($oldFile, $current);
        fwrite($newFile, $proposed);

        $oldPath = escapeshellarg(stream_get_meta_data($oldFile)['uri']);
        $newPath = escapeshellarg(stream_get_meta_data($newFile)['uri']);
        $label = escapeshellarg($filename);

        $diff = shell_exec("diff -u --label {$label} --label {$label} {$oldPath} {$newPath}") ?? '';

        fclose($oldFile);
        fclose($newFile);

        return $diff;
    }
}
