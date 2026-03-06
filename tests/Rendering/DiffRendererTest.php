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

    public function testGetHighlighterReturnsHighlighterInstance(): void
    {
        $this->assertInstanceOf(Highlighter::class, $this->renderer->getHighlighter());
    }

    public function testRenderReturnsString(): void
    {
        $diff = "--- a/file.txt\n+++ b/file.txt\n@@ -1 +1 @@\n-old\n+new\n";

        $result = $this->renderer->render($diff);

        $this->assertNotEmpty($result);
    }

    public function testRenderPreservesDiffContent(): void
    {
        $diff = "--- a/file.txt\n+++ b/file.txt\n@@ -1 +1 @@\n-old line\n+new line\n";

        $result = $this->renderer->render($diff);

        $this->assertStringContainsString('old line', $result);
        $this->assertStringContainsString('new line', $result);
    }

    public function testRenderEmptyDiff(): void
    {
        $result = $this->renderer->render('');

        $this->assertSame('', $result);
    }
}
