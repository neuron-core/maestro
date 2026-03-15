<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core\Commands;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function fgets;
use function strtolower;
use function trim;

use const STDIN;

/**
 * Inline command to list and select the default AI provider.
 */
class ProviderInlineCommand implements InlineCommand
{
    public function __construct(
        protected readonly Settings $settings
    ) {
    }

    public function getName(): string
    {
        return 'provider';
    }

    public function getDescription(): string
    {
        return 'List and select the default AI provider';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $providers = $this->settings->getProviders();

        if ($providers === []) {
            $this->showNoProvidersMessage($output);
            return;
        }

        $this->showProvidersList($output, $providers);
    }

    /**
     * Show message when no providers are configured.
     */
    protected function showNoProvidersMessage(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln(Text::content('No providers configured in settings.json.')->yellow()->build());
        $output->writeln('');
        $output->writeln(Text::content('Run /init to set up your provider configuration.')->gray()->build());
        $output->writeln('');
    }

    /**
     * Show the list of providers and allow selection.
     *
     * @param array<string> $providers
     */
    protected function showProvidersList(OutputInterface $output, array $providers): void
    {
        $defaultProvider = $this->settings->getDefaultProvider();

        $output->writeln('');
        $output->writeln(Text::content('Configured Providers:')->white()->bold()->build());
        $output->writeln('');

        foreach ($providers as $provider) {
            $isDefault = $provider === $defaultProvider;
            $prefix = $isDefault ? '  * ' : '    ';
            $label = $isDefault
                ? Text::content($prefix . $provider . ' (default)')->green()->bold()->build()
                : Text::content($prefix . $provider)->white()->build();

            $output->writeln($label);
        }

        // Ask if the user wants to change the default
        $output->writeln('');
        $output->writeln(Text::content('Change default provider? (y/n): ')->yellow()->build());
        $response = trim(fgets(STDIN));

        if (strtolower($response) !== 'y') {
            $output->writeln(Text::content('Cancelled.')->cyan()->build());
            $output->writeln('');
            return;
        }

        $this->selectProvider($output, $providers, $defaultProvider);
    }

    /**
     * Show a selection menu and handle the user's choice.
     *
     * @param array<string> $providers
     */
    protected function selectProvider(OutputInterface $output, array $providers, ?string $defaultProvider): void
    {
        $menu = new SelectMenuHelper($output);

        // Build menu options with default indicator
        $options = [];
        $defaultIndex = 0;
        foreach ($providers as $i => $provider) {
            $isDefault = $provider === $defaultProvider;
            $label = $isDefault ? $provider . ' (current)' : $provider;
            $options[] = $label;
            if ($isDefault) {
                $defaultIndex = $i;
            }
        }

        $output->writeln('');
        $selectedIndex = $menu->ask(
            Text::content('Select default provider:')->yellow()->build(),
            $options,
            $defaultIndex
        );

        $selectedProvider = $providers[$selectedIndex];

        if ($selectedProvider === $defaultProvider) {
            $output->writeln('');
            $output->writeln(Text::content('No change. The selected provider is already the default.')->yellow()->build());
            $output->writeln('');
            return;
        }

        $success = $this->settings->setDefaultProvider($selectedProvider);

        if ($success) {
            $output->writeln('');
            $output->writeln(Text::content('Default provider changed to: ' . $selectedProvider)->green()->build());
            $output->writeln('');
            $output->writeln(Text::content('Note: Restart Maestro for the change to take effect.')->yellow()->build());
        } else {
            $output->writeln('');
            $output->writeln(Text::content('Failed to set default provider.')->red()->build());
        }

        $output->writeln('');
    }
}
