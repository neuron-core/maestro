<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core\Commands;

use NeuronCore\Maestro\Console\Inline\InlineCommand;
use NeuronCore\Maestro\Settings\Settings;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function end;
use function explode;
use function sprintf;

/**
 * Lists configured extensions with their status. Output-only (toggling is done
 * by editing settings.json and restarting).
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
        return 'List configured Maestro extensions';
    }

    public function execute(string $args, InputInterface $input, OutputInterface $output): void
    {
        $extensions = $this->settings->getExtensions();

        $output->writeln('');

        if ($extensions === []) {
            $output->writeln('<comment>No extensions configured in settings.json.</comment>');
            $output->writeln('Add one under the "extensions" key, then restart Maestro.');
            $output->writeln('');

            return;
        }

        $output->writeln('<options=bold>Installed Extensions:</>');
        $output->writeln('');

        foreach ($extensions as $i => $extension) {
            $className = $extension['class'] ?? 'Unknown';
            $enabled = $extension['enabled'] ?? true;
            $status = $enabled ? '<info>[ENABLED]</info>' : '<error>[DISABLED]</error>';

            $output->writeln(sprintf('  %d) %s %s', $i + 1, $status, $this->getDisplayName($className)));
            $output->writeln('     ' . $className);
        }

        $output->writeln('');
        $output->writeln('<comment>Edit settings.json to enable/disable extensions, then restart.</comment>');
        $output->writeln('');
    }

    protected function getDisplayName(string $className): string
    {
        $parts = explode('\\', $className);

        return (string) end($parts);
    }
}
