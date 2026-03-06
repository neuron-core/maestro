<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering\Renderers;

use NeuronCore\Synapse\Rendering\ToolRenderer;

use function escapeshellarg;
use function fclose;
use function fwrite;
use function json_decode;
use function shell_exec;
use function sprintf;
use function stream_get_meta_data;
use function tmpfile;
use function explode;
use function implode;
use function str_starts_with;

class EditFileRenderer implements ToolRenderer
{
    protected const ESC = "\033";
    protected const RESET = self::ESC . "[0m";
    protected const RED = self::ESC . "[31;1m";
    protected const GREEN = self::ESC . "[32;1m";
    protected const CYAN = self::ESC . "[36;1m";
    protected const YELLOW = self::ESC . "[33;1m";
    protected const GRAY = self::ESC . "[90m";

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
            return $header . "<info>No changes (search and replace are identical)</info>\n";
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
            if (str_starts_with($line, '---') || str_starts_with($line, '+++')) {
                // Skip file headers
                continue;
            } elseif (str_starts_with($line, '@@')) {
                // Skip hunk headers
                continue;
            } elseif (str_starts_with($line, '-')) {
                // Deletions - red
                $colored[] = self::RED . $line . self::RESET;
            } elseif (str_starts_with($line, '+')) {
                // Additions - green
                $colored[] = self::GREEN . $line . self::RESET;
            } elseif (str_starts_with($line, ' ')) {
                // Context - gray
                $colored[] = self::GRAY . $line . self::RESET;
            } elseif (str_starts_with($line, '\ No newline')) {
                // Skip diff metadata lines
                continue;
            } else {
                // Keep other non-empty lines
                if ($line !== '') {
                    $colored[] = $line;
                }
            }
        }

        return implode("\n", $colored);
    }
}
