<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use NeuronCore\Maestro\Console\Color;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Settings\ProviderFactory;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

use function dirname;
use function file_exists;
use function file_put_contents;
use function in_array;
use function json_encode;
use function mkdir;
use function ucfirst;
use function trim;

use const JSON_PRETTY_PRINT;

#[AsCommand(
    name: 'maestro configure',
    description: 'Configure Maestro AI provider settings interactively',
)]
class ConfigureCommand extends Command
{
    private const PROVIDER_NAMES = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI',
        'gemini' => 'Google Gemini',
        'cohere' => 'Cohere',
        'mistral' => 'Mistral AI',
        'ollama' => 'Ollama (Local)',
        'xai' => 'xAI (Grok)',
        'deepseek' => 'Deepseek',
        'openailike' => 'OpenAI-Compatible',
    ];

    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-haiku-4-5',
        'openai' => 'gpt-5',
        'gemini' => 'gemini-3-pro-preview',
        'cohere' => 'command-a-reasoning-08-2025',
        'mistral' => 'mistral-medium-latest',
        'ollama' => 'gemma3',
        'xai' => 'grok-4',
        'deepseek' => 'deepseek-chat',
        'openailike' => 'gpt-5',
    ];

    private const PROVIDERS_REQUIRING_API_KEY = [
        'anthropic', 'openai', 'gemini', 'cohere', 'mistral', 'xai', 'deepseek',
    ];

    private const PROVIDERS_REQUIRING_BASE_URL = [
        'ollama', 'openailike',
    ];

    private const PROVIDERS_REQUIRING_BOTH = [
        'openailike',
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln((string) Color::cyan('Welcome to Maestro Configuration')->bold());
        $output->writeln('');

        $settings = new Settings();

        // Check if the settings file already exists
        if ($settings->fileExists()) {
            $output->writeln((string) Color::yellow('A settings file already exists at: ' . $settings->getSettingsPath()));
            $output->writeln((string) Color::yellow('This configuration will overwrite existing settings.'));
            $output->writeln('');
        }

        // Step 1: Select the provider type
        $providerFactory = new ProviderFactory();
        $providerTypes = $providerFactory->getSupportedProviders();

        $providerOptions = [];
        foreach ($providerTypes as $type) {
            $providerOptions[] = self::PROVIDER_NAMES[$type] ?? ucfirst($type);
        }

        $selectedIndex = (new SelectMenuHelper($output))->ask(
            'Select AI Provider:',
            $providerOptions,
            0
        );

        $selectedProvider = $providerTypes[$selectedIndex];
        $output->writeln('');

        // Step 2: Collect API key and/or base URL
        $questionHelper = new QuestionHelper();

        if (in_array($selectedProvider, self::PROVIDERS_REQUIRING_BOTH, true)) {
            // Providers requiring both API key and base URL
            $apiKeyQuestion = new Question((string) Color::yellow('Enter API Key: '));
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);

            $apiKey = $questionHelper->ask($input, $output, $apiKeyQuestion);
            $output->writeln('');

            $urlQuestion = new Question(
                (string) Color::yellow('Enter Base URL: '),
            );
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_API_KEY, true)) {
            // Providers requiring only API key
            $apiKeyQuestion = new Question((string) Color::yellow('Enter API Key: '));
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);

            $apiKey = $questionHelper->ask($input, $output, $apiKeyQuestion);
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_BASE_URL, true)) {
            // Providers requiring only base URL
            $urlQuestion = new Question(
                (string) Color::yellow('Enter Base URL [http://localhost:11434]: '),
                'http://localhost:11434'
            );
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } else {
            $output->writeln((string) Color::red('Unknown provider type selected.'));
            return Command::FAILURE;
        }

        // Step 3: Collect model name
        $defaultModel = self::DEFAULT_MODELS[$selectedProvider];
        $modelQuestion = new Question(
            (string) Color::yellow('Enter Model [' . $defaultModel . ']: '),
            $defaultModel
        );
        $model = trim((string) $questionHelper->ask($input, $output, $modelQuestion));
        $output->writeln('');

        // Build configuration
        $config = [
            'provider' => [
                'type' => $selectedProvider,
            ],
        ];

        if (isset($apiKey)) {
            $config['provider']['api_key'] = $apiKey;
        }

        if (isset($baseUrl)) {
            $config['provider']['base_url'] = $baseUrl;
        }

        $config['provider']['model'] = $model;

        // Ensure directory exists
        $settingsDir = dirname($settings->getSettingsPath());
        if (!file_exists($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        // Save configuration
        file_put_contents(
            $settings->getSettingsPath(),
            json_encode($config, JSON_PRETTY_PRINT)
        );

        $output->writeln((string) Color::green('Configuration saved successfully!'));
        $output->writeln((string) Color::cyan('Settings file: ' . $settings->getSettingsPath()));
        $output->writeln('');
        $output->writeln((string) Color::cyan('You can now run: php maestro'));
        $output->writeln('');

        return Command::SUCCESS;
    }
}
