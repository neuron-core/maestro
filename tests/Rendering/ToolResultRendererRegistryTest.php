<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Rendering;

use NeuronCore\Synapse\Rendering\CodingToolResultRenderer;
use NeuronCore\Synapse\Rendering\ToolResultRendererInterface;
use NeuronCore\Synapse\Rendering\ToolResultRendererRegistry;
use PHPUnit\Framework\TestCase;

class ToolResultRendererRegistryTest extends TestCase
{
    private ToolResultRendererRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ToolResultRendererRegistry();
    }

    public function testRegisterReturnsSelf(): void
    {
        $renderer = $this->createMock(ToolResultRendererInterface::class);
        $result = $this->registry->register($renderer);

        $this->assertSame($this->registry, $result);
    }

    public function testRegisterAllReturnsSelf(): void
    {
        $renderer1 = $this->createMock(ToolResultRendererInterface::class);
        $renderer2 = $this->createMock(ToolResultRendererInterface::class);
        $result = $this->registry->registerAll([$renderer1, $renderer2]);

        $this->assertSame($this->registry, $result);
    }

    public function testRenderUsesFirstMatchingRenderer(): void
    {
        $renderer1 = $this->createMock(ToolResultRendererInterface::class);
        $renderer1->expects($this->once())
            ->method('canRender')
            ->with('write_file', 'result')
            ->willReturn(false);

        $renderer2 = $this->createMock(ToolResultRendererInterface::class);
        $renderer2->expects($this->once())
            ->method('canRender')
            ->with('write_file', 'result')
            ->willReturn(true);
        $renderer2->expects($this->once())
            ->method('render')
            ->with('write_file', 'result')
            ->willReturn('rendered output');

        $this->registry->registerAll([$renderer1, $renderer2]);
        $output = $this->registry->render('write_file', 'result');

        $this->assertSame('rendered output', $output);
    }

    public function testRenderReturnsNullWhenNoRendererMatches(): void
    {
        $renderer = $this->createMock(ToolResultRendererInterface::class);
        $renderer->expects($this->once())
            ->method('canRender')
            ->willReturn(false);

        $this->registry->register($renderer);
        $output = $this->registry->render('unknown_tool', 'result');

        $this->assertNull($output);
    }

    public function testCanRenderReturnsTrueWhenAnyRendererMatches(): void
    {
        $renderer = $this->createMock(ToolResultRendererInterface::class);
        $renderer->expects($this->once())
            ->method('canRender')
            ->willReturn(true);

        $this->registry->register($renderer);
        $result = $this->registry->canRender('write_file', 'result');

        $this->assertTrue($result);
    }

    public function testCanRenderReturnsFalseWhenNoRendererMatches(): void
    {
        $renderer = $this->createMock(ToolResultRendererInterface::class);
        $renderer->expects($this->once())
            ->method('canRender')
            ->willReturn(false);

        $this->registry->register($renderer);
        $result = $this->registry->canRender('write_file', 'result');

        $this->assertFalse($result);
    }

    public function testGetRenderersReturnsRegisteredRenderers(): void
    {
        $renderer1 = $this->createMock(ToolResultRendererInterface::class);
        $renderer2 = $this->createMock(ToolResultRendererInterface::class);

        $this->registry->registerAll([$renderer1, $renderer2]);
        $renderers = $this->registry->getRenderers();

        $this->assertCount(2, $renderers);
        $this->assertContains($renderer1, $renderers);
        $this->assertContains($renderer2, $renderers);
    }

    public function testWithDefaultsCreatesRegistryWithCodingRenderer(): void
    {
        $registry = ToolResultRendererRegistry::withDefaults();
        $renderers = $registry->getRenderers();

        $this->assertCount(1, $renderers);
        $this->assertInstanceOf(CodingToolResultRenderer::class, $renderers[0]);
    }

    public function testCanRenderCodingToolWithDefaults(): void
    {
        $registry = ToolResultRendererRegistry::withDefaults();
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'write',
        ]);

        $this->assertTrue($registry->canRender('write_file', $result));
    }

    public function testRenderCodingToolWithDefaults(): void
    {
        $registry = ToolResultRendererRegistry::withDefaults();
        $result = json_encode([
            'status' => 'proposed',
            'operation' => 'write',
            'file_path' => '/test/file.php',
            'diff' => 'some diff',
        ]);

        $output = $registry->render('write_file', $result);

        $this->assertStringContainsString('Operation: WRITE', $output);
        $this->assertStringContainsString('some diff', $output);
    }

    public function testCannotRenderNonCodingToolWithDefaults(): void
    {
        $registry = ToolResultRendererRegistry::withDefaults();

        $this->assertFalse($registry->canRender('read_file', 'not a coding tool'));
    }
}
