<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function intdiv;
use function microtime;

class SpinnerProgress
{
    protected const CHARS = ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇'];

    protected ProgressBar $progressBar;

    protected ?float $startTime = null;

    protected int $delay = 100000; // microseconds (100ms)

    public function __construct(OutputInterface $output, int $max = 0)
    {
        $this->progressBar = new ProgressBar($output, $max);
        $this->progressBar->setBarCharacter('✔');
        $this->progressBar->setFormat('%bar%  %message%');
        $this->progressBar->setBarWidth(1);
    }

    public function setMessage(string $message): void
    {
        $this->progressBar->setMessage($message, 'message');
    }

    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->progressBar->start();
    }

    public function display(): void
    {
        if ($this->startTime === null) {
            return;
        }

        $elapsed = (microtime(true) - $this->startTime) * 1000000; // Convert to microseconds
        $this->progressBar->setProgressCharacter(self::CHARS[intdiv((int) $elapsed, $this->delay) % count(self::CHARS)]);
        $this->progressBar->display();
    }

    public function finish(): void
    {
        $this->startTime = null;
        $this->progressBar->finish();
    }

    public function getProgressBar(): ProgressBar
    {
        return $this->progressBar;
    }
}
