<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Rendering;

use NeuronCore\Synapse\Rendering\DiffRenderer;
use PHPUnit\Framework\TestCase;
use Tempest\Highlight\Highlighter;

class DiffRendererTest extends TestCase
{
    private DiffRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new DiffRenderer();
    }

    public function testRenderReturnsString(): void
    {
        $diff = "--- a/file.txt\n+++ b/file.txt\n";
        $result = $this->renderer->render($diff);

        $this->assertIsString($result);
    }

    public function testRenderContainsDiffMarkers(): void
    {
        $diff = "-old\n+new\n";
        $result = $this->renderer->render($diff);

        // Check that the diff markers are present
        $this->assertStringContainsString('-', $result);
        $this->assertStringContainsString('+', $result);
        $this->assertStringContainsString('old', $result);
        $this->assertStringContainsString('new', $result);
    }

    public function testRenderSimpleDiff(): void
    {
        $diff = "--- a/test.php\n" .
                 "+++ b/test.php\n" .
                 "@@ -1,2 +1,2 @@\n" .
                 "-old line 1\n" .
                 "-old line 2\n" .
                 "+new line 1\n" .
                 "+new line 2\n";

        $result = $this->renderer->render($diff);

        // The highlighter adds prefixes to diff lines
        $this->assertStringContainsString('a/test.php', $result);
        $this->assertStringContainsString('b/test.php', $result);
        $this->assertStringContainsString('old line 1', $result);
        $this->assertStringContainsString('old line 2', $result);
        $this->assertStringContainsString('new line 1', $result);
        $this->assertStringContainsString('new line 2', $result);
    }

    public function testGetHighlighterReturnsHighlighter(): void
    {
        $highlighter = $this->renderer->getHighlighter();

        $this->assertInstanceOf(Highlighter::class, $highlighter);
    }

    public function testRenderWithDifferentDiffFormats(): void
    {
        // Test with hunk header
        $diff = "--- a/file\n+++ b/file\n@@ -5,3 +5,4 @@";

        $result = $this->renderer->render($diff);

        $this->assertStringContainsString('@@', $result);
    }

    public function testRenderEmptyDiff(): void
    {
        $diff = '';
        $result = $this->renderer->render($diff);

        $this->assertIsString($result);
        $this->assertEmpty($result);
    }
}
