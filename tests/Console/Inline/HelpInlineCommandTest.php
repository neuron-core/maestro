<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Tests\Console\Inline;

use NeuronCore\Maestro\Console\Inline\HelpInlineCommand;
use NeuronCore\Maestro\Extension\Registry\CommandRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

class HelpInlineCommandTest extends TestCase
{
    public function test_lists_registered_commands_without_old_text_dependency(): void
    {
        $registry = new CommandRegistry();
        $registry->register(new HelpInlineCommand($registry));

        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        (new HelpInlineCommand($registry))->execute('', new ArgvInput(), $output);

        $rendered = $output->fetch();
        self::assertStringContainsString('Available Commands', $rendered);
        self::assertStringContainsString('/help', $rendered);
        // No leftover Symfony tag markup should reach the rendered output.
        self::assertStringNotContainsString('<info>', $rendered);
        self::assertStringNotContainsString('<options=bold>', $rendered);
    }
}
