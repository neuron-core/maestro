<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Tui\ToolView;

use NeuronCore\Maestro\Tui\ToolView\FileSystemToolView;
use NeuronCore\Maestro\Tui\Widget\DiffView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\TextWidget;

class FileSystemToolViewTest extends TestCase
{
    public function test_read_file_shows_only_the_path_not_the_content(): void
    {
        $view = new FileSystemToolView();

        $widget = $view->render('read_file', ['file_path' => 'src/Foo.php'], 'the whole file body');

        self::assertInstanceOf(TextWidget::class, $widget);
        self::assertSame('src/Foo.php', $widget->getText());
    }

    public function test_parse_file_shows_only_the_path_not_the_content(): void
    {
        $view = new FileSystemToolView();

        $widget = $view->render('parse_file', ['file_path' => 'docs/spec.pdf'], 'parsed content');

        self::assertInstanceOf(TextWidget::class, $widget);
        self::assertSame('docs/spec.pdf', $widget->getText());
    }

    public function test_edit_file_still_renders_a_diff(): void
    {
        $view = new FileSystemToolView();

        $widget = $view->render('edit_file', [
            'file_path' => 'src/Foo.php',
            'search' => 'old',
            'replace' => 'new',
        ], 'ok');

        self::assertInstanceOf(DiffView::class, $widget);
    }

    public function test_write_file_still_renders_a_diff(): void
    {
        $view = new FileSystemToolView();

        $widget = $view->render('write_file', [
            'file_path' => 'src/Foo.php',
            'content' => 'new file body',
        ], 'ok');

        self::assertInstanceOf(DiffView::class, $widget);
    }
}
