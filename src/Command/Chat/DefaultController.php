<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Command\Chat;

use Minicli\Command\CommandController;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\WorkflowException;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronCore\CodingAgent\Agent\CodingAgent;
use function str_repeat;

/**
 * ChatCommand - Interactive chat with the Coding Agent.
 *
 * Usage: php neuron chat "your message here"
 *        php neuron chat (for interactive mode)
 */
class DefaultController extends CommandController
{
    protected ?CodingAgent $agent = null;

    /**
     * Handle the chat command.
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->agent = CodingAgent::make();

        $message = $this->hasParam('message')
            ? $this->getParam('message')
            : ($this->getArgs()[0] ?? null);

        if ($message !== null) {
            $this->singleMessage($message);
            return;
        }

        $this->interactiveMode();
    }

    /**
     * Handle a single message and print the response.
     * @throws \Throwable
     */
    protected function singleMessage(string $message): void
    {
        $this->out("Thinking...", "default");

        try {
            $response = $this->agent->chat(new UserMessage($message))->getMessage();

            // Clear "Thinking..." line
            $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");

            // Print response
            $this->newline();
            $content = $response->getContent() ?? 'No response received.';
            $this->display($content);
            $this->newline();
        } catch (WorkflowInterrupt $interrupt) {
            $this->handleWorkflowInterrupt($interrupt);
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->newline();
        }
    }

    /**
     * Interactive mode for continuous conversation.
     * @throws \Throwable
     */
    protected function interactiveMode(): void
    {
        $this->info("=== Coding Agent - Interactive Mode ===");
        $this->info("Type 'exit' or 'quit' to end the conversation.");
        $this->newline();

        while (true) {
            $input = readline('> ');

            if ($input === false) {
                break;
            }

            $input = trim($input);

            if ($input === '' || $input === 'exit' || $input === 'quit') {
                break;
            }

            $this->processUserInput($input);
        }

        $this->info("Goodbye!");
    }

    /**
     * Process user input in interactive mode.
     *
     * @param string $input The user's input message
     * @throws \Throwable
     */
    protected function processUserInput(string $input): void
    {
        $this->out("Thinking...", "default");

        try {
            $response = $this->agent->chat(new UserMessage($input))->getMessage();

            // Clear the "Thinking..." line
            $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");

            // Print the response
            $content = $response->getContent() ?? 'No response received.';
            $this->display($content);
            $this->newline();
        } catch (WorkflowInterrupt $interrupt) {
            $this->handleWorkflowInterrupt($interrupt);
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->newline();
        }
    }

    /**
     * Handle workflow interrupt for tool approval.
     *
     * @throws WorkflowInterrupt
     * @throws WorkflowException
     * @throws \Throwable
     */
    protected function handleWorkflowInterrupt(WorkflowInterrupt $interrupt): void
    {
        $approvalRequest = $interrupt->getRequest();

        // Clear any "Thinking..." output
        $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");

        // Display approval request header
        $this->newline();
        $this->info("─── Tool Approval Required ───", true);
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

        $this->info("─── Approval Complete ───", true);
        $this->newline();

        // Resume the workflow with updated approvals
        // Resume the chat with the updated approval request
        $this->rawOutput("Resuming workflow...");
        $response = $this->agent->chat(interrupt: $approvalRequest)->getMessage();

        // Clear the "Resuming workflow..." message
        $this->rawOutput("\r" . str_repeat(' ', 50) . "\r");

        // Print the response
        $content = $response->getContent() ?? 'No response received.';
        $this->display($content);
        $this->newline();
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

            if ($decision === '' || $decision === 'y' || $decision === 'yes') {
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
     * @return void
     */
    private function processDecision(object $action, string $decision): void
    {
        if ($decision === 'y') {
            $action->approve();
            $this->success(sprintf("Approved: %s", $action->name));
        } else {
            $action->reject();
            $this->error(sprintf("Rejected: %s", $action->name));
        }
        $this->newline();
    }
}
