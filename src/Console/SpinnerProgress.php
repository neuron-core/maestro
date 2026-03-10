<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class SpinnerProgress
{
    protected ProgressBar $progressBar;

    public function __construct(OutputInterface $output, int $max = 0)
    {
        $this->progressBar = new ProgressBar($output, $max);
        $this->progressBar->setBarCharacter('');
        $this->progressBar->setEmptyBarCharacter('');
        $this->progressBar->setProgressCharacter('');
        $this->progressBar->setFormat('%message%');
        $this->progressBar->setMessage('');
    }

    public function setMessage(string $message): void
    {
        $this->progressBar->setMessage($message);
    }

    public function start(): void
    {
        $this->progressBar->start();
    }

    public function finish(): void
    {
        $this->progressBar->clear();
    }
}
