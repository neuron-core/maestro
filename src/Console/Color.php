<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;

final class Color
{
    private static ?OutputFormatter $formatter = null;

    public static function text(string $text): StyledText
    {
        return new StyledText($text);
    }

    public static function red(string $text): StyledText
    {
        return self::text($text)->red();
    }

    public static function green(string $text): StyledText
    {
        return self::text($text)->green();
    }

    public static function cyan(string $text): StyledText
    {
        return self::text($text)->cyan();
    }

    public static function yellow(string $text): StyledText
    {
        return self::text($text)->yellow();
    }

    public static function gray(string $text): StyledText
    {
        return self::text($text)->gray();
    }

    public static function white(string $text): StyledText
    {
        return self::text($text)->white();
    }

    public static function register(string $name, OutputFormatterStyleInterface $style): void
    {
        self::formatter()->setStyle($name, $style);
    }

    public static function formatter(): OutputFormatter
    {
        self::$formatter ??= new OutputFormatter(true);
        return self::$formatter;
    }
}
