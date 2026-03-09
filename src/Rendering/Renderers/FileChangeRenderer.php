<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Rendering\Renderers;

use NeuronCore\Maestro\Console\Color;
use NeuronCore\Maestro\Rendering\ToolRenderer;

use function escapeshellarg;
use function explode;
use function fclose;
use function file_exists;
use function file_get_contents;
use function fwrite;
use function implode;
use function json_decode;
use function shell_exec;
use function sprintf;
use function str_starts_with;
use function stream_get_meta_data;
use function tmpfile;

use const PHP_OS_FAMILY;

class FileChangeRenderer implements ToolRenderer
{
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
            if (!$this->isDiffAvailable()) {
                return $this->header($toolName, $path)
                    . $args['content']
                    . "\n\n" . Color::yellow("Tip: install the \"diff\" command to see a formatted diff: "
                    . $this->diffInstallHint());
            }

            $diff = $this->generateDiff($path, $current, $args['content']);
            return $this->header($toolName, $path) . $this->colorizeDiff($diff);
        }

        return (new GenericRenderer())->render($toolName, $arguments);
    }

    protected function header(string $toolName, string $path): string
    {
        return sprintf("\n● %s( %s )\n\n", $toolName, $path);
    }

    protected function isDiffAvailable(): bool
    {
        return shell_exec('command -v diff 2>/dev/null') !== null;
    }

    protected function diffInstallHint(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin'  => 'brew install diffutils',
            'Windows' => '',
            default   => 'sudo apt-get install diffutils  (or the equivalent for your distro)',
        };
    }

    protected function generateDiff(string $filename, string $current, string $proposed): string
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
