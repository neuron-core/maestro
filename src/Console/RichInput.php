<?php

declare(strict_types=1);

namespace NeuronCore\Maestro\Console;

use Exception;
use Throwable;

use function exec;
use function fflush;
use function fread;
use function fwrite;
use function function_exists;
use function stream_get_meta_data;
use function strlen;
use function substr_replace;
use function fgets;
use function rtrim;

use const STDOUT;
use const STDIN;

/**
 * Provides rich input editing with arrow key navigation, home/end, delete, etc.
 */
class RichInput
{
    /** @var resource Input stream handle */
    private readonly mixed $inputHandle;

    /** @var string Original terminal settings (for restoration) */
    private readonly string $originalStty;

    public function __construct()
    {
        $this->inputHandle = STDIN;

        // Save original terminal settings
        $this->originalStty = $this->getSttySettings();
    }

    public function __destruct()
    {
        $this->restoreTerminal();
    }

    /**
     * Read input with rich editing support.
     *
     * @param string $prompt The prompt to display
     * @return string The user's input
     * @throws Exception If terminal manipulation fails
     */
    public function read(string $prompt): string
    {
        // Check if we're in an interactive terminal
        if (!$this->isInteractive()) {
            // Fallback to simple fgets if not interactive
            fwrite(STDOUT, $prompt);
            $input = (string) fgets($this->inputHandle);
            return $this->cleanUp($input);
        }

        // Set terminal to raw mode for character-by-character input
        $this->setRawMode();

        // Echo the prompt
        fwrite(STDOUT, $prompt);
        fflush(STDOUT);

        $buffer = '';
        $cursorPos = 0;

        try {
            while (true) {
                $char = $this->readChar();

                // Check for escape sequence (special keys)
                if ($char === "\033") {
                    $sequence = $this->readEscapeSequence();
                    [$dx, $deleted] = $this->handleEscapeSequence($sequence, $buffer, $cursorPos);
                    $cursorPos += $dx;
                    if ($deleted) {
                        $buffer = $deleted;
                    }
                    continue;
                }

                // Handle Ctrl+C (interrupt)
                if ($char === "\003") {
                    fwrite(STDOUT, "\n");
                    exit(130); // Standard exit code for Ctrl+C
                }

                // Handle Ctrl+D (EOF)
                if ($char === "\004") {
                    if ($buffer === '') {
                        fwrite(STDOUT, "\n");
                        exit(0);
                    }
                    continue;
                }

                // Handle Enter/Return
                if ($char === "\n" || $char === "\r") {
                    fwrite(STDOUT, "\n");
                    break;
                }

                // Handle Backspace (delete character before cursor)
                if ($char === "\177" || $char === "\010") {
                    if ($cursorPos > 0) {
                        $buffer = substr_replace($buffer, '', $cursorPos - 1, 1);
                        $cursorPos--;
                        $this->redraw($prompt, $buffer, $cursorPos);
                    }
                    continue;
                }

                // Handle Tab (convert to spaces for simplicity)
                if ($char === "\t") {
                    $char = '    ';
                }

                // Handle printable characters
                if ($char >= ' ' && $char <= '~') {
                    // Insert character at cursor position
                    $buffer = substr_replace($buffer, $char, $cursorPos, 0);
                    $cursorPos++;
                    $this->redraw($prompt, $buffer, $cursorPos);
                }
            }
        } catch (Throwable $e) {
            $this->restoreTerminal();
            throw $e;
        }

        $this->restoreTerminal();

        return $this->cleanUp($buffer);
    }

    /**
     * Read a single character from input.
     */
    private function readChar(): string
    {
        $char = fread($this->inputHandle, 1);

        if ($char === false) {
            return "\004"; // EOF
        }

        return $char;
    }

    /**
     * Read and return an escape sequence (e.g., [A for up arrow).
     */
    private function readEscapeSequence(): string
    {
        $sequence = '';

        // Read up to 3 characters for standard escape sequences
        for ($i = 0; $i < 3; $i++) {
            $char = $this->readChar();
            if ($char === "\004" || ($i === 0 && $char !== '[')) {
                // Not a valid escape sequence
                return '';
            }
            $sequence .= $char;

            // Most escape sequences end with a letter (A-Z)
            if ($i > 0 && $char >= 'A' && $char <= 'Z') {
                break;
            }
        }

        return $sequence;
    }

    /**
     * Handle escape sequence and return [cursorDelta, newBufferOrNull].
     *
     * @return array{int, string|null} [cursor position change, new buffer if changed]
     */
    private function handleEscapeSequence(string $sequence, string $buffer, int $cursorPos): array
    {
        return match ($sequence) {
            // Left Arrow: move cursor left
            '[D' => [-1, null],

            // Right Arrow: move cursor right
            '[C' => [1, null],

            // Up Arrow: history (not implemented, return 0)
            '[A' => [0, null],

            // Down Arrow: history (not implemented, return 0)
            '[B' => [0, null],

            // Home: move to start of line
            '[H', '[1~', 'OH' => [-$cursorPos, null],

            // End: move to end of line
            '[F', '[4~', 'OF' => [strlen($buffer) - $cursorPos, null],

            // Delete: delete character at cursor
            '[3~' => [0, substr_replace($buffer, '', $cursorPos, 1)],

            default => [0, null],
        };
    }

    /**
     * Redraw the current input line.
     */
    private function redraw(string $prompt, string $buffer, int $cursorPos): void
    {
        // Clear the entire line and move to start
        fwrite(STDOUT, "\r\033[K");
        fflush(STDOUT);

        // Write prompt and buffer
        fwrite(STDOUT, $prompt . $buffer);
        fflush(STDOUT);

        // Move cursor to correct position using absolute positioning
        $totalLength = strlen($prompt) + $cursorPos;
        // Use escape sequence to move cursor to column N (1-indexed)
        fwrite(STDOUT, "\033[{$totalLength}G");
        fflush(STDOUT);
    }

    /**
     * Set terminal to raw mode for character-by-character input.
     *
     * @throws Exception If stty command fails
     */
    private function setRawMode(): void
    {
        $command = 'stty -icanon -echo min 1 time 0 2>/dev/null';
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Failed to set terminal to raw mode. Ensure you are running in an interactive terminal.');
        }
    }

    /**
     * Restore original terminal settings.
     */
    private function restoreTerminal(): void
    {
        if ($this->originalStty !== '') {
            exec('stty ' . $this->originalStty . ' 2>/dev/null');
        }
    }

    /**
     * Get current terminal settings.
     */
    private function getSttySettings(): string
    {
        $result = exec('stty -g 2>/dev/null', $output, $returnCode);

        return $returnCode === 0 ? (string) $result : '';
    }

    /**
     * Check if running in an interactive terminal.
     */
    private function isInteractive(): bool
    {
        // Check if STDIN is a TTY
        if (function_exists('posix_isatty') && !posix_isatty($this->inputHandle)) {
            return false;
        }

        // Check stream metadata
        $meta = stream_get_meta_data($this->inputHandle);
        if ($meta['stream_type'] !== 'STDIO') {
            return false;
        }

        // Try stty command to verify terminal control
        exec('stty 2>/dev/null', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Clean up input (remove carriage returns and trailing newlines).
     */
    private function cleanUp(string $input): string
    {
        return rtrim($input, "\r\n");
    }
}
