<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tui\Widget;

use NeuronCore\Maestro\Tui\ToolView\DiffLine;
use NeuronCore\Maestro\Tui\ToolView\LineDiffer;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;

use function sprintf;

/**
 * Renders a unified diff for a file change: a meta header (path) followed by
 * +/- lines colored green/red. Lines are truncated (not wrapped) to fit.
 *
 * For an edit, pass the old (search) and new (replace) content. For a new file,
 * pass an empty old string so every line is an addition.
 */
final class DiffView extends AbstractWidget
{
    /** @var list<DiffLine> */
    private array $lines;

    public function __construct(
        private readonly string $path,
        string $old,
        string $new,
    ) {
        $this->lines = LineDiffer::diff($old, $new);
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $out = [];

        $out[] = AnsiUtils::truncateToWidth(
            (new Style(color: '#7aa2f7', bold: true))->apply(sprintf('✎ %s', $this->path)),
            $columns,
        );

        foreach ($this->lines as $line) {
            $rendered = match ($line->type) {
                'add' => (new Style(color: '#9ece6a'))->apply('+ ' . $line->text),
                'del' => (new Style(color: '#f7768e'))->apply('- ' . $line->text),
                default => '  ' . $line->text,
            };
            $out[] = AnsiUtils::truncateToWidth($rendered, $columns, '');
        }

        return $out;
    }
}
