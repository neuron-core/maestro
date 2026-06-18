<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Tui;

use NeuronAI\Workflow\Interrupt\Action;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronCore\Maestro\Agent\MaestroAgent;
use NeuronCore\Maestro\Agent\ToolApprovalPolicy;
use NeuronCore\Maestro\EventBus\EventDispatcher;
use NeuronCore\Maestro\Events\ToolApprovalRequestedEvent;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use NeuronCore\Maestro\Extension\Registry\MemoryRegistry;
use NeuronCore\Maestro\Extension\Registry\ToolRegistry;
use NeuronCore\Maestro\Orchestrator\AgentOrchestrator;
use NeuronCore\Maestro\Settings\Settings;
use NeuronCore\Maestro\Tui\MaestroTui;
use NeuronCore\Maestro\Tui\ToolView\ToolViewRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_PRETTY_PRINT;

class MaestroTuiTest extends TestCase
{
    private string $tempSettingsPath;

    protected function setUp(): void
    {
        $this->tempSettingsPath = sys_get_temp_dir() . '/maestro_tui_' . uniqid() . '.json';
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
     * Driving start/tick/stop against a VirtualTerminal renders the widget tree
     * synchronously (no Revolt loop needed), catching stylesheet/layout errors.
     */
    public function test_renders_status_bar_with_provider_and_model(): void
    {
        [$maestro, $terminal] = $this->buildMaestro(new ToolApprovalPolicy());

        $tui = $maestro->tui();
        $tui->start();
        $tui->tick();
        $tui->stop();

        $output = $terminal->getOutput();
        self::assertStringContainsString('maestro', $output);
        self::assertStringContainsString('openai/gpt-4-test', $output);
    }

    public function test_apply_decision_once_approves(): void
    {
        $policy = new ToolApprovalPolicy();
        [$maestro] = $this->buildMaestro($policy);

        $request = new ApprovalRequest('msg', [new Action('id1', 'edit_file')]);

        $maestro->applyDecision($request, 'once');

        self::assertTrue($request->getActions()[0]->isApproved());
        self::assertFalse($policy->isAllowed('edit_file'));
    }

    public function test_apply_decision_session_approves_and_allowlists(): void
    {
        $policy = new ToolApprovalPolicy();
        [$maestro] = $this->buildMaestro($policy);

        $request = new ApprovalRequest('msg', [new Action('id1', 'edit_file')]);

        $maestro->applyDecision($request, 'session');

        self::assertTrue($request->getActions()[0]->isApproved());
        self::assertTrue($policy->isAllowed('edit_file'));
    }

    public function test_apply_decision_reject_rejects(): void
    {
        $policy = new ToolApprovalPolicy();
        [$maestro] = $this->buildMaestro($policy);

        $request = new ApprovalRequest('msg', [new Action('id1', 'edit_file')]);

        $maestro->applyDecision($request, 'reject');

        self::assertTrue($request->getActions()[0]->isRejected());
    }

    /**
     * In plan mode, approval auto-rejects without suspending (safe to dispatch
     * synchronously in a test).
     */
    public function test_plan_mode_auto_rejects_without_prompt(): void
    {
        $policy = new ToolApprovalPolicy();
        $policy->planMode = true;
        [$maestro, , $dispatcher] = $this->buildMaestro($policy);

        $request = new ApprovalRequest('msg', [new Action('id1', 'edit_file')]);
        $dispatcher->dispatch(new ToolApprovalRequestedEvent($request));

        self::assertTrue($request->getActions()[0]->isRejected());
    }

    /**
     * @return array{0: MaestroTui, 1: VirtualTerminal, 2: EventDispatcher}
     */
    private function buildMaestro(ToolApprovalPolicy $policy): array
    {
        $settings = new Settings($this->tempSettingsPath);
        $agent = new MaestroAgent($settings, new ToolRegistry(), new MemoryRegistry(), $policy);
        $dispatcher = new EventDispatcher();
        $orchestrator = new AgentOrchestrator($agent, $dispatcher);
        $terminal = new VirtualTerminal(80, 24);

        $maestro = new MaestroTui(
            $settings,
            $orchestrator,
            $dispatcher,
            $policy,
            ToolViewRegistry::defaultRegistry(),
            new CommandRegistry(),
            $terminal,
        );

        return [$maestro, $terminal, $dispatcher];
    }
}
