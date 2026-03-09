<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Console\Color;
use NeuronCore\Maestro\Rendering\ToolRenderer;

use function escapeshellarg;
use function explode;
use function fclose;
use function fwrite;
use function implode;
use function json_decode;
use function shell_exec;
use function sprintf;
use function str_starts_with;
use function stream_get_meta_data;
use function tmpfile;

class EditFileRenderer implements ToolRenderer
{
    public function render(string $toolName, string $arguments): string
    {
        $args = json_decode($arguments, true) ?? [];
        $path = $args['file_path'] ?? null;
        $search = $args['search'] ?? null;
        $replace = $args['replace'] ?? null;

        if ($path === null || $search === null || $replace === null) {
            return (new GenericRenderer())->render($toolName, $arguments);
        }

        $header = sprintf("● %s( %s )\n\n", $toolName, $path);

        // Generate a diff between search and replace strings
        $diff = $this->generateSearchReplaceDiff($search, $replace);

        if ($diff === '') {
            return $header . Color::cyan("No changes (search and replace are identical)") . "\n";
        }

        // Apply ANSI colors directly to the diff
        return $header . $this->colorizeDiff($diff);
    }

    protected function generateSearchReplaceDiff(string $search, string $replace): string
    {
        $oldFile = tmpfile();
        $newFile = tmpfile();

        fwrite($oldFile, $search);
        fwrite($newFile, $replace);

        $oldPath = escapeshellarg(stream_get_meta_data($oldFile)['uri']);
        $newPath = escapeshellarg(stream_get_meta_data($newFile)['uri']);

        $diff = shell_exec("diff -u --label 'a/search' --label 'b/replace' {$oldPath} {$newPath}") ?? '';

        fclose($oldFile);
        fclose($newFile);

        return $diff;
    }

    protected function colorizeDiff(string $diff): string
    {
        $lines = explode("\n", $diff);
        $colored = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '---')) {
                // Skip file headers
                continue;
            }
            if (str_starts_with($line, '+++')) {
                // Skip file headers
                continue;
            }
            if (str_starts_with($line, '@@')) {
                // Skip hunk headers
                continue;
            }
            if (str_starts_with($line, '-')) {
                // Deletions - red
                $colored[] = (string) Color::red($line);
            } elseif (str_starts_with($line, '+')) {
                // Additions - green
                $colored[] = (string) Color::green($line);
            } elseif (str_starts_with($line, ' ')) {
                // Context - gray
                $colored[] = (string) Color::gray($line);
            } elseif (str_starts_with($line, '\ No newline')) {
                // Skip diff metadata lines
                continue;
            } elseif ($line !== '') {
                // Keep other non-empty lines
                $colored[] = $line;
            }
        }

        return implode("\n", $colored);
    }
}
