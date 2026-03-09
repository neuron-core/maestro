<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Stringable;

use function array_merge;
use function explode;
use function implode;
use function str_contains;
use function trim;

final class StyledText implements Stringable
{
    private array $styles = [];

    public function __construct(
        private readonly string $text
    ) {
    }

    // Colors
    public function red(): self
    {
        $this->styles['fg'] = 'red';
        return $this;
    }

    public function green(): self
    {
        $this->styles['fg'] = 'green';
        return $this;
    }

    public function cyan(): self
    {
        $this->styles['fg'] = 'cyan';
        return $this;
    }

    public function yellow(): self
    {
        $this->styles['fg'] = 'yellow';
        return $this;
    }

    public function gray(): self
    {
        $this->styles['fg'] = 'gray';
        return $this;
    }

    public function white(): self
    {
        $this->styles['fg'] = 'white';
        return $this;
    }

    public function blue(): self
    {
        $this->styles['fg'] = 'blue';
        return $this;
    }

    public function magenta(): self
    {
        $this->styles['fg'] = 'magenta';
        return $this;
    }

    public function black(): self
    {
        $this->styles['fg'] = 'black';
        return $this;
    }

    // Options (chainable)
    public function bold(): self
    {
        $this->styles['options'][] = 'bold';
        return $this;
    }

    public function underscore(): self
    {
        $this->styles['options'][] = 'underscore';
        return $this;
    }

    public function blink(): self
    {
        $this->styles['options'][] = 'blink';
        return $this;
    }

    public function reverse(): self
    {
        $this->styles['options'][] = 'reverse';
        return $this;
    }

    public function conceal(): self
    {
        $this->styles['options'][] = 'conceal';
        return $this;
    }

    // Background
    public function bgRed(): self
    {
        $this->styles['bg'] = 'red';
        return $this;
    }

    public function bgGreen(): self
    {
        $this->styles['bg'] = 'green';
        return $this;
    }

    public function bgCyan(): self
    {
        $this->styles['bg'] = 'cyan';
        return $this;
    }

    public function bgYellow(): self
    {
        $this->styles['bg'] = 'yellow';
        return $this;
    }

    public function bgGray(): self
    {
        $this->styles['bg'] = 'gray';
        return $this;
    }

    public function bgWhite(): self
    {
        $this->styles['bg'] = 'white';
        return $this;
    }

    public function bgBlue(): self
    {
        $this->styles['bg'] = 'blue';
        return $this;
    }

    public function bgMagenta(): self
    {
        $this->styles['bg'] = 'magenta';
        return $this;
    }

    public function bgBlack(): self
    {
        $this->styles['bg'] = 'black';
        return $this;
    }

    // Raw style string for full flexibility
    public function style(string $style): self
    {
        $this->styles = [];
        return $this->parseStyleString($style);
    }

    public function __toString(): string
    {
        $style = $this->buildStyleString();
        if ($style === '') {
            return $this->text;
        }
        return (string) Color::formatter()->format("<{$style}>{$this->text}</>");
    }

    private function buildStyleString(): string
    {
        $parts = [];

        if (isset($this->styles['fg'])) {
            $parts[] = 'fg=' . $this->styles['fg'];
        }

        if (isset($this->styles['bg'])) {
            $parts[] = 'bg=' . $this->styles['bg'];
        }

        if (!empty($this->styles['options'])) {
            $parts[] = 'options=' . implode(',', $this->styles['options']);
        }

        return implode(';', $parts) ?: '';
    }

    private function parseStyleString(string $style): self
    {
        foreach (explode(';', $style) as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === 'options') {
                $this->styles['options'] = array_merge(
                    $this->styles['options'] ?? [],
                    explode(',', $value)
                );
            } else {
                $this->styles[$key] = $value;
            }
        }
        return $this;
    }
}
