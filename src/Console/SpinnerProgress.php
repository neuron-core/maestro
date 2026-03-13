<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use function function_exists;
use function pcntl_fork;
use function pcntl_signal;
use function pcntl_signal_dispatch;
use function pcntl_waitpid;
use function posix_kill;
use function usleep;
use function count;

use const SIGTERM;
use const SIGUSR1;

class SpinnerProgress
{
    protected const FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    protected const FRAME_INTERVAL = 100000; // 100ms

    protected ?int $pid = null;
    protected ?string $lastMessage = null;
    protected bool $useFork = false;

    public function __construct()
    {
        $this->useFork = $this->canFork();
    }

    public function setMessage(string $message): void
    {
        $this->lastMessage = $message;
    }

    public function start(): void
    {
        if ($this->useFork) {
            $this->startForked();
        } else {
            $this->startSimple();
        }
    }

    public function finish(): void
    {
        $this->stop();
        $this->clearLine();
    }

    protected function startForked(): void
    {
        $this->pid = pcntl_fork();

        if ($this->pid === -1) {
            // Fork failed, fall back to simple mode
            $this->useFork = false;
            $this->startSimple();
            return;
        }

        if ($this->pid === 0) {
            // Child process: animate spinner
            $this->animateChild();
            exit(0);
        }

        // Parent process: continue with the main flow
    }

    protected function startSimple(): void
    {
        // Just show the first frame - will be static during blocking operations
        $this->renderFrame(0);
    }

    protected function animateChild(): void
    {
        // Set up signal handler for graceful shutdown
        pcntl_signal(SIGTERM, static fn () => exit(0));
        pcntl_signal(SIGUSR1, static fn () => exit(0));

        $frameIndex = 0;

        /** @phpstan-ignore while.alwaysTrue */
        while (true) {
            // Render current frame
            $this->renderFrame($frameIndex);

            // Move to next frame
            $frameIndex = ($frameIndex + 1) % count(self::FRAMES);

            // Wait before next frame
            usleep(self::FRAME_INTERVAL);

            // Check for signals
            pcntl_signal_dispatch();
        }
    }

    protected function renderFrame(int $frameIndex): void
    {
        $frame = self::FRAMES[$frameIndex];
        $message = $this->lastMessage ?? 'Working...';

        // Use ANSI escape codes to redraw the line
        // \r moves cursor to start of line, then we overwrite with new frame
        $output = "\r\033[K{$frame} {$message}";

        // Write directly to stdout for performance
        echo $output;
    }

    protected function stop(): void
    {
        if ($this->pid !== null) {
            // Send signal to child process to stop
            posix_kill($this->pid, SIGTERM);
            // Wait for child to exit
            pcntl_waitpid($this->pid, $status);
            $this->pid = null;
        }
    }

    protected function clearLine(): void
    {
        // Clear the entire line
        echo "\r\033[K";
    }

    protected function canFork(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('posix_kill')
            && function_exists('pcntl_waitpid');
    }
}
