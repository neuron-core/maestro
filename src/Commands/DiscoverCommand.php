<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Commands;

use NeuronCore\Maestro\Extension\Ui\Text;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;
use function file_put_contents;
use function is_dir;
use function is_file;
use function var_export;
use function array_merge;
use function array_unique;
use function count;
use function dirname;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;

use const JSON_PRETTY_PRINT;

/**
 * Discover and register Maestro extensions from installed Composer packages.
 *
 * Scans all installed packages for the `extra.maestro.extensions` field
 * and generates a manifest file containing all discovered extensions.
 *
 * Usage in package composer.json:
 * ```json
 * {
 *     "extra": {
 *         "maestro": {
 *             "extensions": [
 *                 "Vendor\\Package\\MyExtension"
 *             ]
 *         }
 *     }
 * }
 * ```
 */
#[AsCommand(
    name: 'discover',
    description: 'Discover Maestro extensions from installed Composer packages',
)]
class DiscoverCommand extends Command
{
    protected const MANIFEST_PATH = '.maestro/manifest.php';
    protected const COMPOSER_JSON_PATH = 'composer.json';
    protected const COMPOSER_LOCK_PATH = 'composer.lock';
    protected const VENDOR_DIR = 'vendor/composer';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');

        $packages = $this->loadInstalledPackages();

        if ($packages === []) {
            $output->writeln(Text::content('No installed packages found. Run `composer install` first.')->warning()->build());
            $output->writeln('');
            return Command::FAILURE;
        }

        $manifest = $this->scanPackagesForExtensions($packages);

        if ($manifest === []) {
            $output->writeln(Text::content('No Maestro extensions discovered.')->muted()->build());
            $output->writeln('');
            $output->writeln(Text::content('To add extensions to a package, add the following to its composer.json:')->muted()->build());
            $output->writeln(json_encode([
                'extra' => [
                    'maestro' => [
                        'extensions' => ['Vendor\\Package\\MyExtension'],
                    ],
                ],
            ], JSON_PRETTY_PRINT));
            $output->writeln('');
            return Command::SUCCESS;
        }

        $this->writeManifest($manifest);

        $extensionCount = count($manifest);
        $packageCount = count(array_unique(array_map(fn (array $e): string => $e['package'], $manifest)));

        $output->writeln(Text::content("Discovered {$extensionCount} extension(s) from {$packageCount} package(s):")->success()->build());
        $output->writeln('');

        foreach ($manifest as $entry) {
            $output->writeln(Text::content("  • {$entry['class']}")->info()->build());
            $output->writeln(Text::content("    from: {$entry['package']}")->muted()->build());
        }

        $output->writeln('');
        $output->writeln(Text::content('Manifest written to: ' . self::MANIFEST_PATH)->muted()->build());
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Load all installed packages from composer's installed.json.
     *
     * @return array<array{name: string, extra: array<string, mixed>}>
     */
    protected function loadInstalledPackages(): array
    {
        // First, load the root composer.json
        $packages = $this->loadRootComposerJson();

        // Try to load from vendor/composer/installed.json
        $installedJson = self::VENDOR_DIR . '/installed.json';

        if (!is_file($installedJson)) {
            // Fall back to parsing composer.lock
            $vendorPackages = $this->loadPackagesFromLock();
        } else {
            $content = file_get_contents($installedJson);
            if ($content === false) {
                return $packages;
            }

            $installed = json_decode($content, true);
            if (!is_array($installed)) {
                return $packages;
            }

            // Handle both old and new format
            $vendorPackagesData = $installed['packages'] ?? $installed;

            if (!is_array($vendorPackagesData)) {
                return $packages;
            }

            $vendorPackages = array_map(fn (array $pkg): array => [
                'name' => $pkg['name'] ?? '',
                'extra' => $pkg['extra'] ?? [],
            ], $vendorPackagesData);
        }

        return array_merge($packages, $vendorPackages);
    }

    /**
     * Load the root composer.json for extension declarations.
     *
     * @return array<array{name: string, extra: array<string, mixed>}>
     */
    protected function loadRootComposerJson(): array
    {
        if (!is_file(self::COMPOSER_JSON_PATH)) {
            return [];
        }

        $content = file_get_contents(self::COMPOSER_JSON_PATH);
        if ($content === false) {
            return [];
        }

        $root = json_decode($content, true);
        if (!is_array($root)) {
            return [];
        }

        return [
            [
                'name' => $root['name'] ?? 'root',
                'extra' => $root['extra'] ?? [],
            ],
        ];
    }

    /**
     * Load packages from composer.lock file (fallback).
     *
     * @return array<array{name: string, extra: array<string, mixed>}>
     */
    protected function loadPackagesFromLock(): array
    {
        if (!is_file(self::COMPOSER_LOCK_PATH)) {
            return [];
        }

        $content = file_get_contents(self::COMPOSER_LOCK_PATH);
        if ($content === false) {
            return [];
        }

        $lock = json_decode($content, true);
        if (!is_array($lock)) {
            return [];
        }

        $packages = $lock['packages'] ?? [];
        $packagesDev = $lock['packages-dev'] ?? [];

        $allPackages = array_merge($packages, $packagesDev);

        return array_map(fn (array $pkg): array => [
            'name' => $pkg['name'] ?? '',
            'extra' => $pkg['extra'] ?? [],
        ], $allPackages);
    }

    /**
     * Scan all installed packages for Maestro extension declarations.
     *
     * @param array<array{name: string, extra: array<string, mixed>}> $packages
     * @return array<int, array{package: string, class: string, enabled: bool}>
     */
    protected function scanPackagesForExtensions(array $packages): array
    {
        $manifest = [];

        foreach ($packages as $package) {
            $packageName = $package['name'] ?? '';
            $extra = $package['extra'] ?? [];

            if (!isset($extra['maestro']['extensions'])) {
                continue;
            }

            $extensions = $extra['maestro']['extensions'];

            if (!is_array($extensions)) {
                continue;
            }

            foreach ($extensions as $extensionClass) {
                if (!is_string($extensionClass)) {
                    continue;
                }
                if ($extensionClass === '') {
                    continue;
                }
                $manifest[] = [
                    'package' => $packageName,
                    'class' => $extensionClass,
                    'enabled' => true,
                ];
            }
        }

        return $manifest;
    }

    /**
     * Write the manifest file to disk.
     *
     * @param array<int, array{package: string, class: string, enabled: bool}> $manifest
     */
    protected function writeManifest(array $manifest): void
    {
        $manifestDir = dirname(self::MANIFEST_PATH);

        if (!is_dir($manifestDir)) {
            mkdir($manifestDir, 0755, true);
        }

        $content = '<?php' . "\n" . 'declare(strict_types=1);' . "\n\n";
        $content .= '/**' . "\n";
        $content .= ' * Auto-generated Maestro extension manifest.' . "\n";
        $content .= ' * DO NOT EDIT - This file is regenerated by composer.' . "\n";
        $content .= ' *' . "\n";
        $content .= ' * Run `php bin/maestro maestro discover` to regenerate.' . "\n";
        $content .= ' */' . "\n\n";
        $content .= 'return ' . var_export($manifest, true) . ";\n";

        file_put_contents(self::MANIFEST_PATH, $content);
    }
}
