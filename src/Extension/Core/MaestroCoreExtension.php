<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Core;

use NeuronCore\Maestro\Console\Text;
use NeuronCore\Maestro\Extension\ExtensionApi;
use NeuronCore\Maestro\Extension\ExtensionInterface;
use NeuronCore\Maestro\Extension\Ui\SlotType;
use NeuronCore\Maestro\Rendering\Renderers\EditFileRenderer;
use NeuronCore\Maestro\Rendering\Renderers\FileChangeRenderer;
use NeuronCore\Maestro\Rendering\Renderers\SnippetRenderer;

use function implode;

/**
 * Registers all built-in Maestro defaults: intro banner and tool renderers.
 * User extensions load after this and can override any individual registration.
 */
class MaestroCoreExtension implements ExtensionInterface
{
    public function name(): string
    {
        return 'maestro.core';
    }

    public function register(ExtensionApi $api): void
    {
        $this->registerUi($api);
        $this->registerRenderers($api);
    }

    protected function registerUi(ExtensionApi $api): void
    {
        $api->ui()->addToSlot(SlotType::HEADER, $this->buildIntro(), priority: 1000);
    }

    protected function registerRenderers(ExtensionApi $api): void
    {
        $fileChange = new FileChangeRenderer();

        $api->registerRenderer('read_file', new SnippetRenderer(['file_path']));
        $api->registerRenderer('parse_file', new SnippetRenderer(['file_path']));
        $api->registerRenderer('grep_file_content', new SnippetRenderer(['pattern', 'file_path']));
        $api->registerRenderer('glob_path', new SnippetRenderer(['pattern', 'directory']));
        $api->registerRenderer('bash', new SnippetRenderer(['command']));
        $api->registerRenderer('edit_file', new EditFileRenderer());
        $api->registerRenderer('write_file', $fileChange);
        $api->registerRenderer('delete_file', $fileChange);
    }

    protected function buildIntro(): string
    {
        return implode("\n", [
            '',
            Text::content("  __  __                 _             ")->cyan()->bold()->build(),
            Text::content(" |  \\/  |               | |            ")->cyan()->bold()->build(),
            Text::content(" | \\  / | __ _  ___  ___| |_ _ __ ___  ")->cyan()->bold()->build(),
            Text::content(" | |\\/| |/ _` |/ _ \\/ __| __| '__/ _ \\ ")->cyan()->bold()->build(),
            Text::content(" | |  | | (_| |  __/\\__ \\ |_| | | (_) |")->cyan()->bold()->build(),
            Text::content(" |_|  |_|\\__,_|\\___||___/\\__|_|  \\___/ ")->cyan()->bold()->build(),
            '',
            Text::content(" Powered by Neuron AI framework (https://docs.neuron-ai.dev) ")->white()->bold()->build(),
            '',
            Text::content(" Tip: Type /help to see available commands.")->gray()->build(),
        ]);
    }
}
