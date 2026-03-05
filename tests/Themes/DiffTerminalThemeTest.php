<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Themes;

use NeuronCore\Synapse\Themes\DiffTerminalTheme;
use PHPUnit\Framework\TestCase;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\TerminalStyle;

class DiffTerminalThemeTest extends TestCase
{
    private DiffTerminalTheme $theme;

    protected function setUp(): void
    {
        $this->theme = new DiffTerminalTheme();
    }

    public function testThemeImplementsTerminalTheme(): void
    {
        $this->assertInstanceOf(\Tempest\Highlight\TerminalTheme::class, $this->theme);
    }

    public function testWorksWithHighlighter(): void
    {
        $highlighter = new Highlighter($this->theme);

        $diff = "--- a/file.txt\n" .
                 "+++ b/file.txt\n" .
                 "@@ -1,1 +1,1 @@\n" .
                 "-old line\n" .
                 "+new line\n";

        $result = $highlighter->parse($diff, 'diff');

        // Just check that output contains expected diff content (with highlighter prefixes)
        $this->assertStringContainsString('a/file.txt', $result);
        $this->assertStringContainsString('b/file.txt', $result);
        $this->assertStringContainsString('old line', $result);
        $this->assertStringContainsString('new line', $result);
    }

    public function testWorksWithHighlighterForEmptyDiff(): void
    {
        $highlighter = new Highlighter($this->theme);

        $result = $highlighter->parse('', 'diff');

        $this->assertIsString($result);
    }

    public function testRenderFileHeader(): void
    {
        $highlighter = new Highlighter($this->theme);

        $diff = "--- a/file.php\n+++ b/file.php\n";

        $result = $highlighter->parse($diff, 'diff');

        // Check for file paths (highlighter adds prefixes)
        $this->assertStringContainsString('a/file.php', $result);
        $this->assertStringContainsString('b/file.php', $result);
    }

    public function testRenderHunkHeader(): void
    {
        $highlighter = new Highlighter($this->theme);

        $diff = "@@ -1,1 +1,1 @@";

        $result = $highlighter->parse($diff, 'diff');

        $this->assertStringContainsString('@@', $result);
    }
}
