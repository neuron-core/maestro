<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\ToolView;

use NeuronCore\Maestro\Tui\Widget\DiffView;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Renders the FileSystemToolkit tools: a colored diff for file edits/writes,
 * and just the path for reads (the file content is returned to the agent, not
 * echoed back to the user).
 */
final class FileSystemToolView implements ToolView
{
    public function render(string $toolName, array $inputs, string $result): AbstractWidget
    {
        return match ($toolName) {
            'edit_file' => new DiffView(
                (string) ($inputs['file_path'] ?? ''),
                (string) ($inputs['search'] ?? ''),
                (string) ($inputs['replace'] ?? ''),
            ),
            'write_file' => new DiffView(
                (string) ($inputs['file_path'] ?? ''),
                '',
                (string) ($inputs['content'] ?? ''),
            ),
            'read_file', 'parse_file' => (new TextWidget((string) ($inputs['file_path'] ?? '')))
                ->addStyleClass('tool-result'),
            default => (new TextWidget($result !== '' ? $result : '(empty file)'))
                ->addStyleClass('tool-result'),
        };
    }
}
