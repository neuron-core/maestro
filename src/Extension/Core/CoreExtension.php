<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core;

use NeuronCore\Maestro\Extension\Core\Commands\ExtensionsInlineCommand;
use NeuronCore\Maestro\Extension\Core\Commands\ProviderInlineCommand;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\ExtensionInterface;

/**
 * Registers built-in Maestro inline commands. User extensions load after this
 * and can register more.
 */
class CoreExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'maestro.core';
    }

    public function register(ExtensionApi $api): void
    {
        $api->registerCommand(new ExtensionsInlineCommand($api->settings()));
        $api->registerCommand(new ProviderInlineCommand($api->settings()));
    }
}
