<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Extension\Ui\Text;
use NeuronCore\Maestro\Extension\Coding\CodingExtension;
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
    name: 'init',
    description: 'Initialize Maestro AI provider settings interactively',
)]
class InitCommand extends Command
{
    private const PROVIDER_NAMES = [
        'anthropic' => 'Anthropic (Claude)',
        'openai' => 'OpenAI',
        'openailike' => 'OpenAI-Compatible',
        'gemini' => 'Google Gemini',
        'cohere' => 'Cohere',
        'mistral' => 'Mistral AI',
        'ollama' => 'Ollama (Local)',
        'xai' => 'xAI (Grok)',
        'deepseek' => 'Deepseek',
        'zai' => 'ZAI (GLM models)',
    ];

    private const DEFAULT_MODELS = [
        'anthropic' => 'claude-sonnet-4-6',
        'openai' => 'gpt-5',
        'gemini' => 'gemini-3-pro-preview',
        'cohere' => 'command-a-reasoning-08-2025',
        'mistral' => 'mistral-medium-latest',
        'ollama' => 'gemma3',
        'xai' => 'grok-4',
        'deepseek' => 'deepseek-chat',
        'openailike' => 'gpt-5',
        'zai' => 'glm-4.7'
    ];

    private const PROVIDERS_REQUIRING_API_KEY = [
        'anthropic', 'openai', 'gemini', 'cohere', 'mistral', 'xai', 'deepseek', 'zai',
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
        $output->writeln(Text::content('Welcome to Maestro Configuration')->primary()->bold()->build());
        $output->writeln('');

        $settings = new Settings();

        // Check if the settings file already exists
        if ($settings->fileExists()) {
            $output->writeln(Text::content('A settings file already exists at: ' . $settings->getSettingsPath())->warning()->build());
            $output->writeln(Text::content('This configuration will overwrite existing settings.')->warning()->build());
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
            $apiKeyQuestion = new Question(Text::content('Enter API Key: ')->warning()->build());
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);

            $apiKey = $questionHelper->ask($input, $output, $apiKeyQuestion);
            $output->writeln('');

            $urlQuestion = new Question(
                Text::content('Enter Base URL: ')->warning()->build(),
            );
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_API_KEY, true)) {
            // Providers requiring only the API key
            $apiKeyQuestion = new Question(Text::content('Enter API Key: ')->warning()->build());
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);

            $apiKey = $questionHelper->ask($input, $output, $apiKeyQuestion);
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_BASE_URL, true)) {
            // Providers requiring only base URL
            $urlQuestion = new Question(
                Text::content('Enter Base URL [http://localhost:11434]: ')->warning()->build(),
                'http://localhost:11434'
            );
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } else {
            $output->writeln(Text::content('Unknown provider type selected.')->error()->build());
            return Command::FAILURE;
        }

        // Step 3: Collect model name
        $defaultModel = self::DEFAULT_MODELS[$selectedProvider];
        $modelQuestion = new Question(
            Text::content('Enter Model [' . $defaultModel . ']: ')->warning()->build(),
            $defaultModel
        );
        $model = trim((string) $questionHelper->ask($input, $output, $modelQuestion));
        $output->writeln('');

        // Build configuration
        $config = [
            'default' => $selectedProvider,
        ];

        $config['providers'] = [];

        if (isset($apiKey)) {
            $config['providers'][$selectedProvider]['api_key'] = $apiKey;
        }

        if (isset($baseUrl)) {
            $config['providers'][$selectedProvider]['base_url'] = $baseUrl;
        }

        $config['providers'][$selectedProvider]['model'] = $model;

        // Add default extensions configuration
        $config['extensions'] = [
            [
                'class' => CodingExtension::class,
                'enabled' => true,
            ],
        ];

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

        $output->writeln(Text::content('Configuration saved successfully!')->success()->build());
        $output->writeln(Text::content('Settings file: ' . $settings->getSettingsPath())->primary()->build());
        $output->writeln('');

        return Command::SUCCESS;
    }
}
