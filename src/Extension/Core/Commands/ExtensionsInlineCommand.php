<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core\Commands;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Console\SelectMenuHelper;
use NeuronCore\Maestro\Extension\Ui\Text;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function fgets;
use function sprintf;
use function strtolower;
use function trim;
use function end;
use function explode;

use const STDIN;

/**
 * Inline command to list and manage Maestro extensions.
 */
class ExtensionsInlineCommand implements InlineCommand
{
    public function __construct(
        protected readonly Settings $settings
    ) {
    }

    public function getName(): string
    {
        return 'extensions';
    }

    public function getDescription(): string
    {
        return 'List and enable/disable Maestro extensions';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $extensions = $this->settings->getExtensions();

        if ($extensions === []) {
            $this->showNoExtensionsMessage($output);
            return;
        }

        $this->showExtensionsList($output, $extensions);

        // Ask if the user wants to toggle an extension
        $output->writeln('');
        $output->writeln(Text::content('Toggle an extension? (y/n): ')->warning()->build());
        $response = trim(fgets(STDIN));

        if (strtolower($response) !== 'y') {
            $output->writeln(Text::content('Cancelled.')->info()->build());
            $output->writeln('');
            return;
        }

        $this->toggleExtension($output, $this->settings, $extensions);
    }

    /**
     * Show message when no extensions are configured.
     */
    protected function showNoExtensionsMessage(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln(Text::content('No extensions configured in settings.json.')->warning()->build());
        $output->writeln('');
        $output->writeln(Text::content('To add an extension, add it to the extensions array:')->muted()->build());
        $output->writeln(Text::content('  "extensions": [')->muted()->build());
        $output->writeln(Text::content('    { "class": "Your\\\\Extension\\\\Class", "enabled": true }')->muted()->build());
        $output->writeln(Text::content('  ]')->muted()->build());
        $output->writeln('');
    }

    /**
     * Show the list of extensions with their status.
     *
     * @param array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}> $extensions
     */
    protected function showExtensionsList(OutputInterface $output, array $extensions): void
    {
        $output->writeln('');
        $output->writeln(Text::content('Installed Extensions:')->bold()->build());
        $output->writeln('');

        foreach ($extensions as $i => $extension) {
            $className = $extension['class'] ?? 'Unknown';
            $enabled = $extension['enabled'] ?? true;
            $status = $enabled
                ? Text::content(' [ENABLED] ')->success()->bold()->build()
                : Text::content(' [DISABLED]')->error()->bold()->build();
            $displayName = $this->getDisplayName($className);

            $output->writeln(sprintf('  %d) %s%s', $i + 1, $status, $displayName));
            $output->writeln(Text::content('     ' . $className)->muted()->build());
        }
    }

    /**
     * Show a toggle menu and handle the user's selection.
     *
     * @param array<int, array{class: string, enabled?: bool, config?: array<string, mixed>}> $extensions
     */
    protected function toggleExtension(OutputInterface $output, Settings $settings, array $extensions): void
    {
        $menu = new SelectMenuHelper($output);

        // Build menu options with status indicators
        $options = [];
        foreach ($extensions as $extension) {
            $className = $extension['class'] ?? 'Unknown';
            $enabled = $extension['enabled'] ?? true;
            $displayName = $this->getDisplayName($className);
            $status = $enabled ? '[ON] ' : '[OFF]';
            $options[] = $status . $displayName;
        }

        $output->writeln('');
        $selectedIndex = $menu->ask(
            Text::content('Select an extension to toggle:')->warning()->build(),
            $options
        );

        $selectedExtension = $extensions[$selectedIndex];
        $className = $selectedExtension['class'] ?? '';
        $currentlyEnabled = $selectedExtension['enabled'] ?? true;

        if ($currentlyEnabled) {
            $settings->disableExtension($className);
            $output->writeln('');
            $output->writeln(Text::content('Extension disabled.')->info()->build());
        } else {
            $settings->enableExtension($className);
            $output->writeln('');
            $output->writeln(Text::content('Extension enabled.')->info()->build());
        }

        $output->writeln('');
        $output->writeln(Text::content('Note: You may need to restart Maestro for changes to take full effect.')->warning()->build());
        $output->writeln('');
    }

    /**
     * Get a display-friendly name from a fully qualified class name.
     */
    protected function getDisplayName(string $className): string
    {
        // Fallback to the short class name
        $parts = explode('\\', $className);
        return end($parts);
    }
}
