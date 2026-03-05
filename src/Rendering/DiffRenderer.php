<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Rendering;

use NeuronCore\Synapse\Themes\DiffTerminalTheme;
use Tempest\Highlight\Highlighter;

/**
 * Renders unified diff output with terminal highlighting.
 */
class DiffRenderer
{
    private readonly Highlighter $highlighter;

    public function __construct()
    {
        $this->highlighter = new Highlighter(new DiffTerminalTheme());
    }

    /**
     * Render a unified diff with terminal highlighting.
     *
     * @param string $diff The unified diff string
     * @return string The highlighted diff output
     */
    public function render(string $diff): string
    {
        return $this->highlighter->parse($diff, 'diff');
    }

    /**
     * Get the underlying highlighter instance.
     */
    public function getHighlighter(): Highlighter
    {
        return $this->highlighter;
    }
}
