<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Command\Synapse;

use Minicli\Command\CommandController;
use Minicli\Input;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronCore\Synapse\Agent\CodingAgent;
use NeuronCore\Synapse\Command\CommandHelper;
use NeuronCore\Synapse\Settings\Settings;
use NeuronCore\Synapse\Settings\SettingsInterface;
use Exception;
use Throwable;

use function in_array;
use function json_encode;
use function readline;
use function sprintf;
use function strtolower;
use function trim;

use const JSON_PRETTY_PRINT;

/**
 * ChatCommand - Interactive chat with the Coding Agent.
 *
 * Usage: synapse (starts interactive mode)
 */
class DefaultController extends CommandController
{
    use CommandHelper;

    protected ?CodingAgent $agent = null;

    /**
     * Handle the chat command.
     * @throws Throwable
     */
    public function handle(): void
    {
        // Load and validate settings
        $settings = new Settings();

        if (!$settings->fileExists()) {
            $this->error("Warning: Settings file not found at " . $settings->getSettingsPath());
            $this->error("The agent requires AI provider connection information.");
            $this->newline();
            $this->info("Create a settings.json file with your AI provider configuration:");
            $this->display(json_encode([
                'provider' => [
                    'type' => 'openai',
                    'api_key' => 'your-api-key',
                    'model' => 'gpt-4',
                ],
            ], JSON_PRETTY_PRINT));
            $this->newline();
            return;
        }

        if (!$settings->hasValidProvider()) {
            $this->error("Warning: Settings file is missing valid provider configuration.");
            $this->error("The 'provider.type' setting is required.");
            $this->newline();
            return;
        }

        $this->interactiveMode($settings);
    }

    /**
     * Interactive mode for continuous conversation.
     * @throws Throwable
     */
    protected function interactiveMode(SettingsInterface $settings): void
    {
        // Initialize agent
        $this->agent = CodingAgent::make($settings);

        $this->info("=== Synapse Coding Agent - built with Neuron AI framework ===");
        $this->info("Type 'exit' or 'quit' to end the conversation.");
        $this->newline();

        while (true) {
            $input=new Input(prompt: "> ");
            $userInput = $input->read();

            $userInput = trim($userInput);

            if (in_array($userInput, ['', 'exit', 'quit'], true)) {
                break;
            }

            $this->processUserInput($userInput);
        }

        $this->info("Goodbye!");
    }

    /**
     * Process user input in interactive mode.
     *
     * @param string $input The user's input message
     * @throws Throwable
     */
    protected function processUserInput(string $input): void
    {
        $this->out("Thinking...", "default");

        try {
            $response = $this->agent->chat(new UserMessage($input))->getMessage();

            // Clear the "Thinking..." line
            $this->clearOutput();

            // Print the response
            $content = $response->getContent() ?? 'No response received.';
            $this->display($content);
            $this->newline();
        } catch (WorkflowInterrupt $interrupt) {
            $this->clearOutput();
            $this->handleWorkflowInterrupt($interrupt);
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->newline();
        }
    }

    /**
     * Handle workflow interrupt for tool approval.
     *
     * @throws WorkflowInterrupt
     * @throws WorkflowException
     * @throws Throwable
     */
    protected function handleWorkflowInterrupt(WorkflowInterrupt $interrupt): void
    {
        /** @var ApprovalRequest $approvalRequest */
        $approvalRequest = $interrupt->getRequest();

        // Display approval request message
        $this->display($approvalRequest->getMessage());
        $this->newline();

        // Display each action and get user approval
        foreach ($approvalRequest->getPendingActions() as $action) {
            $this->info(sprintf("%s( %s )", $action->name, $action->description), true);
            $this->newline();

            // Get user decision
            $decision = $this->askDecision();

            // Process the decision
            $this->processDecision($action, $decision);
        }

        $this->newline();

        // Resume the workflow with updated approvals
        $this->out("Thinking...", "default");
        try {
            $response = $this->agent->chat(interrupt: $approvalRequest)->getMessage();

            $this->clearOutput();

            // Print the response
            $content = $response->getContent() ?? 'No response received.';
            $this->display($content);
            $this->newline();
        } catch (WorkflowInterrupt $nestedInterrupt) {
            $this->clearOutput();
            // Handle the next interruption that occurred during resumption
            $this->handleWorkflowInterrupt($nestedInterrupt);
        }
    }

    /**
     * Ask the user for their decision on an action.
     *
     * @return string The user's decision ('y' or 'n')
     */
    private function askDecision(): string
    {
        while (true) {
            $decision = $this->ask('Approve this action? [Y/n]: ', 'info');
            $decision = strtolower(trim($decision));

            if (in_array($decision, ['', 'y', 'yes'], true)) {
                return 'y';
            }

            if ($decision === 'n' || $decision === 'no') {
                return 'n';
            }

            $this->error("Invalid choice. Please enter 'y' or 'n'.");
        }
    }

    /**
     * Process the user's decision on an action.
     *
     * @param object $action The action to process
     * @param string $decision The user's decision ('y' or 'n')
     */
    private function processDecision(object $action, string $decision): void
    {
        if ($decision === 'y') {
            $action->approve();
        } else {
            $action->reject();
        }
        $this->newline();
    }
}
