<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Extension\Ui;

use Stringable;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;
use RuntimeException;

use function sprintf;

class Text implements Stringable
{
    protected static ?OutputFormatter $formatter = null;
    protected static ?ThemeInterface $theme = null;

    protected ?ColorName $color = null;
    protected ?StyleName $style = null;

    public function __construct(
        protected readonly string $text
    ) {
    }

    public static function content(string $text): self
    {
        return new self($text);
    }

    public static function setTheme(ThemeInterface $theme): void
    {
        self::$theme = $theme;
    }

    public static function theme(): ThemeInterface
    {
        return self::$theme ?? throw new RuntimeException('Theme not set. Call Text::setTheme() first.');
    }

    public static function formatter(): OutputFormatter
    {
        self::$formatter ??= new OutputFormatter(true);
        return self::$formatter;
    }

    public static function register(string $name, OutputFormatterStyleInterface $style): void
    {
        self::formatter()->setStyle($name, $style);
    }

    // Semantic colors
    public function primary(): self
    {
        $this->color = ColorName::PRIMARY;
        return $this;
    }

    public function success(): self
    {
        $this->color = ColorName::SUCCESS;
        return $this;
    }

    public function warning(): self
    {
        $this->color = ColorName::WARNING;
        return $this;
    }

    public function error(): self
    {
        $this->color = ColorName::ERROR;
        return $this;
    }

    public function info(): self
    {
        $this->color = ColorName::INFO;
        return $this;
    }

    public function muted(): self
    {
        $this->color = ColorName::MUTED;
        return $this;
    }

    public function accent(): self
    {
        $this->color = ColorName::ACCENT;
        return $this;
    }

    // Semantic styles
    public function bold(): self
    {
        $this->style = StyleName::BOLD;
        return $this;
    }

    public function dim(): self
    {
        $this->style = StyleName::DIM;
        return $this;
    }

    public function underline(): self
    {
        $this->style = StyleName::UNDERLINE;
        return $this;
    }

    public function build(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        if (!$this->color && !$this->style) {
            return $this->text;
        }

        $theme = self::theme();
        $colorCode = $this->color instanceof ColorName ? $theme->color($this->color) : '';
        $styleCode = $this->style instanceof StyleName ? $theme->style($this->style) : '';

        if ($colorCode === '' && $styleCode === '') {
            return $this->text;
        }

        if ($colorCode === '') {
            return sprintf('<%s>%s</>', $styleCode, $this->text);
        }

        if ($styleCode === '') {
            return sprintf('<fg=%s>%s</>', $colorCode, $this->text);
        }

        return sprintf('<fg=%s;%s>%s</>', $colorCode, $styleCode, $this->text);
    }
}
