<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

use function passthru;

/**
 * Composer plugin that runs discovery after install/update.
 *
 * This plugin ensures "maestro discover" runs even when the package
 * is installed globally via "composer global require" or "composer global update".
 */
class MaestroPlugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;
    protected IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do
    }

    /**
     * @return array<string, string|int>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'onPostAutoloadDump',
        ];
    }

    public function onPostAutoloadDump(): void
    {
        $this->io->write('<info>Running Maestro discovery...</info>');

        $binDir = $this->composer->getConfig()->get('bin-dir');
        $discoverScript = $binDir.'/maestro discover';

        // Execute the discover command
        passthru($discoverScript, $exitCode);

        if ($exitCode !== 0) {
            $this->io->writeError('<warning>Maestro discovery completed with warnings</warning>');
        } else {
            $this->io->write('<info>Maestro discovery completed successfully</info>');
        }
    }
}
