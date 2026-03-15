<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

use function count;
use function defined;
use function fgets;
use function fread;
use function function_exists;
use function getmypid;
use function max;
use function min;
use function pcntl_async_signals;
use function pcntl_signal;
use function pcntl_signal_get_handler;
use function posix_kill;
use function shell_exec;
use function sprintf;
use function stream_select;
use function trim;

use const SIG_DFL;
use const STDIN;
use const SIGINT;
use const SIGQUIT;
use const SIGTERM;

class SelectMenuHelper
{
    /** @var resource */
    private $inputStream;

    private int $pendingSignal = 0;

    /** @var array<int, callable|int> */
    private array $savedSignalHandlers = [];

    /**
     * @param resource|null $inputStream
     */
    public function __construct(
        private readonly OutputInterface $output,
        mixed $inputStream = null,
    ) {
        $this->inputStream = $inputStream ?? STDIN;
    }

    /**
     * Prompt the user to pick one option from a list.
     * Returns the zero-based index of the chosen option.
     *
     * @param string[] $options
     */
    public function ask(string $title, array $options, int $default = 0): int
    {
        if (!$this->isInteractive()) {
            return $this->fallback($title, $options, $default);
        }

        return $this->interactive($title, $options, $default);
    }

    /**
     * Wraps Terminal::hasSttyAvailable() so tests can override it.
     */
    protected function isInteractive(): bool
    {
        return Terminal::hasSttyAvailable();
    }

    /**
     * @param string[] $options
     */
    private function interactive(string $title, array $options, int $selected): int
    {
        $cursor = new Cursor($this->output, $this->inputStream);
        $count = count($options);
        $sttyState = trim((string) shell_exec('stty -g'));

        $this->pendingSignal = 0;
        $this->installSignalHandlers($sttyState);

        shell_exec('stty -icanon -echo');
        $cursor->hide();

        $this->renderMenu($title, $options, $selected);

        try {
            while (true) {
                // Wait for input while allowing signal handlers to run.
                $read = [$this->inputStream];
                $write = [];
                while (0 === @stream_select($read, $write, $write, 0, 100)) {
                    if ($this->pendingSignal !== 0) {
                        break 2;
                    }
                    $read = [$this->inputStream];
                }

                $char = fread($this->inputStream, 1);

                if ($char === "\033") {
                    fread($this->inputStream, 1); // '['
                    $arrow = fread($this->inputStream, 1);

                    if ($arrow === 'A') {
                        $selected = max(0, $selected - 1);
                    } elseif ($arrow === 'B') {
                        $selected = min($count - 1, $selected + 1);
                    }
                } elseif ($char === "\n" || $char === "\r") {
                    break;
                } elseif ($char === "\003") {
                    // Ctrl+C when pcntl is unavailable
                    $this->pendingSignal = defined('SIGINT') ? SIGINT : 2;
                    break;
                }

                $cursor->moveUp($count + 1);
                $this->renderMenu($title, $options, $selected);
            }
        } finally {
            $cursor->show();
            shell_exec(sprintf('stty %s', $sttyState));
            $this->restoreSignalHandlers();
        }

        if ($this->pendingSignal !== 0) {
            $this->raiseSignal($this->pendingSignal);
        }

        // Clear the menu from the screen
        $cursor->moveUp($count + 1);
        for ($i = 0; $i <= $count; $i++) {
            $cursor->clearLine();
            $cursor->moveDown();
        }
        $cursor->moveUp($count + 1);

        return $selected;
    }

    /**
     * @param string[] $options
     */
    private function fallback(string $title, array $options, int $default): int
    {
        $this->output->writeln($title);

        foreach ($options as $i => $option) {
            $this->output->writeln(sprintf('  %d) %s', $i + 1, $option));
        }

        $this->output->writeln('');
        $max = count($options);

        while (true) {
            $this->output->write(sprintf('Enter choice (1-%d) [%d]: ', $max, $default + 1));
            $input = trim((string) fgets($this->inputStream));

            if ($input === '') {
                return $default;
            }

            $index = (int) $input - 1;

            if ($index >= 0 && $index < $max) {
                return $index;
            }

            $this->output->writeln(sprintf(
                Text::content('Invalid choice. Enter a number between 1 and %d.')->red()->build(),
                $max
            ));
        }
    }

    /**
     * @param string[] $options
     */
    private function renderMenu(string $title, array $options, int $selected): void
    {
        $this->output->writeln($title);

        foreach ($options as $i => $option) {
            if ($i === $selected) {
                $this->output->writeln(sprintf('  %s', Text::content('> ' . $option)->cyan()->build()));
            } else {
                $this->output->writeln(sprintf('    %s', $option));
            }
        }
    }

    private function installSignalHandlers(string $sttyState): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGQUIT, SIGTERM] as $signal) {
            $this->savedSignalHandlers[$signal] = pcntl_signal_get_handler($signal);

            pcntl_signal($signal, function (int $sig) use ($sttyState): void {
                shell_exec(sprintf('stty %s', $sttyState));
                $this->pendingSignal = $sig;
            });
        }
    }

    private function restoreSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        foreach ($this->savedSignalHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }

        $this->savedSignalHandlers = [];
    }

    private function raiseSignal(int $signal): never
    {
        if (function_exists('posix_kill') && function_exists('pcntl_signal')) {
            pcntl_signal($signal, SIG_DFL);
            posix_kill(getmypid(), $signal);
        }

        exit(128 + $signal);
    }
}
