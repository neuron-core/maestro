<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Tui;

use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\AgentResponseEvent;
use NeuronCore\Maestro\Events\AgentStreamChunkEvent;
use NeuronCore\Maestro\Events\BeforeChatEvent;
use NeuronCore\Maestro\Events\ToolExecutedEvent;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Settings\Settings;
use NeuronCore\Maestro\Tui\Style\MaestroStyleSheet;
use NeuronCore\Maestro\Tui\ToolView\ToolViewRegistry;
use NeuronCore\Maestro\Tui\TuiPresenter;
use NeuronCore\Maestro\Tui\Widget\Card;
use NeuronCore\Maestro\Tui\Widget\DiffView;
use NeuronCore\Maestro\Tui\Widget\TranscriptView;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\TextWidget;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_PRETTY_PRINT;

class TuiPresenterTest extends TestCase
{
    private string $tempSettingsPath;

    protected function setUp(): void
    {
        $this->tempSettingsPath = sys_get_temp_dir() . '/maestro_presenter_' . uniqid() . '.json';
        file_put_contents($this->tempSettingsPath, (string) json_encode([
            'default' => 'openai',
            'providers' => [
                'openai' => ['api_key' => 'sk-test', 'model' => 'gpt-4-test'],
            ],
        ], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        @unlink($this->tempSettingsPath);
    }

    /**
     * Streaming chunks accumulate into a single Markdown widget, finalized by
     * the response event. Uses primitive-payload events so no live agent is
     * needed.
     */
    public function test_streaming_accumulates_into_markdown(): void
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: new MaestroStyleSheet(), terminal: $terminal);
        $transcript = new TranscriptView();
        $tui->add($transcript);
        $tui->start();

        $bus = new EventDispatcher();
        (new TuiPresenter($transcript, $tui, ToolViewRegistry::defaultRegistry()))->register($bus);

        $bus->dispatch(new AgentStreamChunkEvent('Hello '));
        $bus->dispatch(new AgentStreamChunkEvent('world'));
        $bus->dispatch(new AgentResponseEvent('Hello world'));

        $tui->tick();
        $tui->stop();

        $cards = $transcript->all();
        self::assertCount(1, $cards);
        self::assertInstanceOf(MarkdownWidget::class, $cards[0]);
        self::assertSame('Hello world', $cards[0]->getText());
    }

    /**
     * An edit_file execution renders as a Card whose body is a DiffView.
     */
    public function test_tool_execution_renders_diff_card(): void
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: new MaestroStyleSheet(), terminal: $terminal);
        $transcript = new TranscriptView();
        $tui->add($transcript);
        $tui->start();

        $bus = new EventDispatcher();
        (new TuiPresenter($transcript, $tui, ToolViewRegistry::defaultRegistry()))->register($bus);

        $bus->dispatch(new ToolExecutedEvent('edit_file', [
            'file_path' => 'src/Foo.php',
            'search' => "return 'old';",
            'replace' => "return 'new';",
        ], 'ok'));

        $tui->tick();
        $tui->stop();

        $cards = $transcript->all();
        self::assertCount(1, $cards);
        $card = $cards[0];
        self::assertInstanceOf(Card::class, $card);
        $children = $card->all();
        self::assertInstanceOf(DiffView::class, $children[1]);
    }

    /**
     * Repeated calls of the same tool collapse into one card: the title carries
     * a running count and each read path is appended to the body as a list.
     */
    public function test_repeated_tool_collapses_into_one_card_with_count(): void
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: new MaestroStyleSheet(), terminal: $terminal);
        $transcript = new TranscriptView();
        $tui->add($transcript);
        $tui->start();

        $bus = new EventDispatcher();
        (new TuiPresenter($transcript, $tui, ToolViewRegistry::defaultRegistry()))->register($bus);

        $bus->dispatch(new ToolExecutedEvent('read_file', ['file_path' => 'src/Foo.php'], ''));
        $bus->dispatch(new ToolExecutedEvent('read_file', ['file_path' => 'src/Bar.php'], ''));
        $bus->dispatch(new ToolExecutedEvent('read_file', ['file_path' => 'src/Baz.php'], ''));

        $tui->tick();
        $tui->stop();

        $cards = $transcript->all();
        self::assertCount(1, $cards);

        $card = $cards[0];
        self::assertInstanceOf(Card::class, $card);
        $children = $card->all();
        $title = $children[0];
        $body = $children[1];
        self::assertInstanceOf(TextWidget::class, $title);
        self::assertInstanceOf(TextWidget::class, $body);
        self::assertSame('read_file (×3)', $title->getText());
        // Paths accumulate oldest-first as a bulleted list.
        self::assertSame("• src/Foo.php\n• src/Bar.php\n• src/Baz.php", $body->getText());
    }

    /**
     * The dedup maps clear at the start of each turn, so a tool called again in
     * a later turn gets a fresh card instead of merging with the previous turn.
     */
    public function test_tool_cards_reset_each_turn(): void
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(styleSheet: new MaestroStyleSheet(), terminal: $terminal);
        $transcript = new TranscriptView();
        $tui->add($transcript);
        $tui->start();

        $bus = new EventDispatcher();
        (new TuiPresenter($transcript, $tui, ToolViewRegistry::defaultRegistry()))->register($bus);

        $bus->dispatch(new ToolExecutedEvent('read_file', ['file_path' => 'src/Foo.php'], ''));
        $bus->dispatch(new BeforeChatEvent($this->buildAgent(), 'again'));
        $bus->dispatch(new ToolExecutedEvent('read_file', ['file_path' => 'src/Bar.php'], ''));

        $tui->tick();
        $tui->stop();

        // Two turns -> two cards; the second turn's call is fresh (no count).
        $cards = $transcript->all();
        self::assertCount(2, $cards);

        $second = $cards[1];
        self::assertInstanceOf(Card::class, $second);
        $title = $second->all()[0];
        self::assertInstanceOf(TextWidget::class, $title);
        self::assertSame('read_file', $title->getText());
    }

    private function buildAgent(): MaestroAgent
    {
        return new MaestroAgent(new Settings($this->tempSettingsPath), new ToolRegistry());
    }
}
