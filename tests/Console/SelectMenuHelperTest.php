<?php

declare(strict_types=1);

namespace NeuronCore\Synapse\Tests\Console;

use NeuronCore\Synapse\Console\SelectMenuHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function fwrite;
use function rewind;
use function tmpfile;

class SelectMenuHelperTest extends TestCase
{
    /**
     * Returns a SelectMenuHelper that always uses the fallback (non-interactive) path.
     *
     * @param resource $inputStream
     */
    private function makeFallback(BufferedOutput $output, $inputStream): SelectMenuHelper
    {
        return new class ($output, $inputStream) extends SelectMenuHelper {
            protected function isInteractive(): bool
            {
                return false;
            }
        };
    }

    /**
     * @param resource $inputStream
     */
    private function writeInput($inputStream, string $content): void
    {
        fwrite($inputStream, $content);
        rewind($inputStream);
    }

    public function testReturnsChosenIndex(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "2\n");

        $index = $this->makeFallback($output, $stream)->ask('Pick:', ['Alpha', 'Beta', 'Gamma']);

        $this->assertSame(1, $index);
    }

    public function testReturnsDefaultOnEmptyInput(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "\n");

        $index = $this->makeFallback($output, $stream)->ask('Pick:', ['Alpha', 'Beta', 'Gamma'], 2);

        $this->assertSame(2, $index);
    }

    public function testDefaultIsZeroWhenNotSpecified(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "\n");

        $index = $this->makeFallback($output, $stream)->ask('Pick:', ['Alpha', 'Beta']);

        $this->assertSame(0, $index);
    }

    public function testRetriesOnInvalidInputThenReturnsChoice(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "99\n3\n");

        $index = $this->makeFallback($output, $stream)->ask('Pick:', ['Alpha', 'Beta', 'Gamma']);

        $this->assertSame(2, $index);
        $this->assertStringContainsString('Invalid choice', $output->fetch());
    }

    public function testOutputContainsAllOptions(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "1\n");

        $this->makeFallback($output, $stream)->ask('Choose:', ['Foo', 'Bar', 'Baz']);

        $text = $output->fetch();
        $this->assertStringContainsString('Foo', $text);
        $this->assertStringContainsString('Bar', $text);
        $this->assertStringContainsString('Baz', $text);
    }

    public function testOutputContainsTitle(): void
    {
        $output = new BufferedOutput();
        $stream = tmpfile();
        $this->writeInput($stream, "1\n");

        $this->makeFallback($output, $stream)->ask('My title:', ['Option A']);

        $this->assertStringContainsString('My title:', $output->fetch());
    }
}
