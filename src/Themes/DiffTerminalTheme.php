<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Themes;

use Tempest\Highlight\TerminalTheme;
use Tempest\Highlight\Themes\TerminalStyle;
use Tempest\Highlight\Tokens\TokenType;
use Tempest\Highlight\Tokens\TokenTypeEnum;
use Tempest\Highlight\Themes\EscapesTerminalTheme;

/**
 * Terminal theme specifically for highlighting diffs with green for additions and red for deletions.
 */
final class DiffTerminalTheme implements TerminalTheme
{
    use EscapesTerminalTheme;

    public function before(TokenType $tokenType): string
    {
        return match ($tokenType->getValue()) {
            // Diff additions - bright green
            'diff-addition' => TerminalStyle::ESC->value . TerminalStyle::FG_GREEN->value . TerminalStyle::BOLD->value,
            // Diff deletions - bright red
            'diff-deletion' => TerminalStyle::ESC->value . TerminalStyle::FG_RED->value . TerminalStyle::BOLD->value,
            // Diff context - gray
            'diff-context' => TerminalStyle::ESC->value . TerminalStyle::FG_GRAY->value,
            // Diff hunk header - cyan
            'diff-hunk' => TerminalStyle::ESC->value . TerminalStyle::FG_CYAN->value . TerminalStyle::BOLD->value,
            // Diff file header - yellow
            'diff-file' => TerminalStyle::ESC->value . TerminalStyle::FG_YELLOW->value . TerminalStyle::BOLD->value,
            default => TerminalStyle::ESC->value . TerminalStyle::RESET->value,
        };
    }

    public function after(TokenType $tokenType): string
    {
        return TerminalStyle::ESC->value . TerminalStyle::RESET->value;
    }
}
