<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core\Commands;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists configured AI providers. Output-only (the default provider is changed
 * by editing settings.json and restarting — interactive selection does not fit
 * the raw-mode TUI).
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
        return 'List configured AI providers';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $providers = $this->settings->getProviders();

        $output->writeln('');

        if ($providers === []) {
            $output->writeln('<comment>No providers configured in settings.json.</comment>');
            $output->writeln('');

            return;
        }

        $default = $this->settings->getDefaultProvider();

        $output->writeln('<options=bold>Configured Providers:</>');
        $output->writeln('');

        foreach ($providers as $provider) {
            if ($provider === $default) {
                $output->writeln('<info>  * ' . $provider . ' (default)</info>');
            } else {
                $output->writeln('    ' . $provider);
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Edit settings.json to change the default provider, then restart.</comment>');
        $output->writeln('');
    }
}
