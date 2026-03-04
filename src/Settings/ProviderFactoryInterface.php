<?php

declare(strict_types=1);

namespace NeuronCore\CodingAgent\Settings;

use NeuronAI\Providers\AIProviderInterface;

/**
 * Interface for creating AI provider instances from settings.
 */
interface ProviderFactoryInterface
{
    /**
     * Create a provider instance based on the settings array.
     *
     * @throws \RuntimeException if provider cannot be created
     */
    public function create(array $settings): AIProviderInterface;
}
