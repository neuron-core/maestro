<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use NeuronCore\Maestro\Extension\Coding\CodingExtension;
use NeuronCore\Maestro\Settings\ProviderFactory;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

use function array_search;
use function dirname;
use function file_exists;
use function file_put_contents;
use function in_array;
use function json_encode;
use function mkdir;
use function trim;
use function ucfirst;

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
        'zai' => 'glm-4.7',
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
        $output->writeln('<options=bold>Welcome to Maestro Configuration</>');
        $output->writeln('');

        $settings = new Settings();

        if ($settings->fileExists()) {
            $output->writeln('<comment>A settings file already exists at: ' . $settings->getSettingsPath() . '</comment>');
            $output->writeln('<comment>This configuration will overwrite existing settings.</comment>');
            $output->writeln('');
        }

        // Step 1: Select the provider type
        $providerFactory = new ProviderFactory();
        $providerTypes = $providerFactory->getSupportedProviders();

        $providerOptions = [];
        foreach ($providerTypes as $type) {
            $providerOptions[] = self::PROVIDER_NAMES[$type] ?? ucfirst($type);
        }

        $questionHelper = new QuestionHelper();
        $choice = new ChoiceQuestion('Select AI Provider: ', $providerOptions, 0);
        $selectedLabel = (string) $questionHelper->ask($input, $output, $choice);
        $selectedIndex = (int) array_search($selectedLabel, $providerOptions, true);
        $selectedProvider = $providerTypes[$selectedIndex];
        $output->writeln('');

        // Step 2: Collect API key and/or base URL
        $apiKey = null;
        $baseUrl = null;

        if (in_array($selectedProvider, self::PROVIDERS_REQUIRING_BOTH, true)) {
            $apiKeyQuestion = new Question('Enter API Key: ');
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);
            $apiKey = trim((string) $questionHelper->ask($input, $output, $apiKeyQuestion));
            $output->writeln('');

            $urlQuestion = new Question('Enter Base URL: ');
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_API_KEY, true)) {
            $apiKeyQuestion = new Question('Enter API Key: ');
            $apiKeyQuestion->setHidden(true);
            $apiKeyQuestion->setHiddenFallback(false);
            $apiKey = trim((string) $questionHelper->ask($input, $output, $apiKeyQuestion));
            $output->writeln('');
        } elseif (in_array($selectedProvider, self::PROVIDERS_REQUIRING_BASE_URL, true)) {
            $urlQuestion = new Question('Enter Base URL [http://localhost:11434]: ', 'http://localhost:11434');
            $baseUrl = trim((string) $questionHelper->ask($input, $output, $urlQuestion));
            $output->writeln('');
        } else {
            $output->writeln('<error>Unknown provider type selected.</error>');

            return Command::FAILURE;
        }

        // Step 3: Collect model name
        $defaultModel = self::DEFAULT_MODELS[$selectedProvider];
        $modelQuestion = new Question('Enter Model [' . $defaultModel . ']: ', $defaultModel);
        $model = trim((string) $questionHelper->ask($input, $output, $modelQuestion));
        $output->writeln('');

        // Build configuration
        $config = [
            'default' => $selectedProvider,
        ];

        $config['providers'] = [];

        if ($apiKey !== null && $apiKey !== '') {
            $config['providers'][$selectedProvider]['api_key'] = $apiKey;
        }

        if ($baseUrl !== null && $baseUrl !== '') {
            $config['providers'][$selectedProvider]['base_url'] = $baseUrl;
        }

        $config['providers'][$selectedProvider]['model'] = $model;

        $config['extensions'] = [
            [
                'class' => CodingExtension::class,
                'enabled' => true,
            ],
        ];

        $settingsDir = dirname($settings->getSettingsPath());
        if (!file_exists($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        file_put_contents(
            $settings->getSettingsPath(),
            json_encode($config, JSON_PRETTY_PRINT)
        );

        $output->writeln('<info>Configuration saved successfully!</info>');
        $output->writeln('<comment>Settings file: ' . $settings->getSettingsPath() . '</comment>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
