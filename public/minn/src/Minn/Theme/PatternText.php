<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * Block-theme patterns are PHP files whose only code is a handful of
 * echo-and-escape calls around literal strings. The engine never executes
 * them: it interprets that small text grammar (string literals, "."
 * concatenation, the i18n and escaping wrappers, the theme URI helper, and
 * printf with %s) and leaves anything else out. The header comment block
 * is dropped, and every byte outside the PHP tags is kept as-is.
 */
final class PatternText
{
    /** Wrappers whose value is their first argument, unchanged. */
    private const PASSTHROUGH = ['__', '_x', 'esc_url', 'wp_kses_post', 'esc_url_raw'];

    /** Wrappers whose value is their first argument with HTML special characters encoded. */
    private const ESCAPING = ['esc_html__', 'esc_html_x', 'esc_attr__', 'esc_attr_x', 'esc_html', 'esc_attr'];

    private string $themeUri;
    private string $source;
    private int $position = 0;

    private function __construct(string $source, string $themeUri)
    {
        $this->source = $source;
        $this->themeUri = $themeUri;
    }

    /** A pattern file's HTML with its PHP interpreted, never executed. */
    public static function render(string $file, string $themeUri): string
    {
        // The header: the leading docblock inside its own PHP tags, dropped whole.
        $file = preg_replace('/^<\?php\s*\/\*\*.*?\*\/\s*\?>\r?\n?/s', '', $file, 1);
        // Like PHP itself, a single newline directly after the closing tag is swallowed.
        return preg_replace_callback(
            '/<\?php(.*?)\?>\r?\n?/s',
            static fn (array $m) => (new self(trim($m[1]), $themeUri))->statements(),
            $file,
        );
    }

    private function statements(): string
    {
        $out = '';
        while ($this->position < strlen($this->source)) {
            $this->skipSpace();
            if ($this->position >= strlen($this->source)) {
                break;
            }
            $name = $this->identifier();
            if ($name === null) {
                return $out;
            }
            $out .= match ($name) {
                'echo' => (string) $this->expression(),
                '_e' => (string) ($this->arguments()[0] ?? ''),
                'esc_html_e', 'esc_attr_e' => self::escape((string) ($this->arguments()[0] ?? '')),
                'printf' => $this->printf($this->arguments()),
                default => '',
            };
            $this->skipSpace();
            if (($this->source[$this->position] ?? '') === ';') {
                $this->position++;
            }
        }
        return $out;
    }

    private function printf(array $arguments): string
    {
        $format = (string) array_shift($arguments);
        try {
            return vsprintf($format, array_map(strval(...), $arguments));
        } catch (\ValueError | \ArgumentCountError) {
            return '';
        }
    }

    /** A concatenation of terms. */
    private function expression(): ?string
    {
        $value = $this->term();
        if ($value === null) {
            return null;
        }
        while (true) {
            $this->skipSpace();
            if (($this->source[$this->position] ?? '') !== '.') {
                return $value;
            }
            $this->position++;
            $value .= (string) $this->term();
        }
    }

    private function term(): ?string
    {
        $this->skipSpace();
        $char = $this->source[$this->position] ?? '';
        if ($char === "'" || $char === '"') {
            return $this->stringLiteral($char);
        }
        $name = $this->identifier();
        if ($name === null) {
            return null;
        }
        $arguments = $this->arguments();
        if ($name === 'get_template_directory_uri' || $name === 'get_stylesheet_directory_uri') {
            return $this->themeUri;
        }
        if ($name === 'sprintf') {
            return $this->printf($arguments);
        }
        if (in_array($name, self::PASSTHROUGH, true)) {
            return (string) ($arguments[0] ?? '');
        }
        if (in_array($name, self::ESCAPING, true)) {
            return self::escape((string) ($arguments[0] ?? ''));
        }
        return '';
    }

    /** Special characters encoded once; entities already present are kept. */
    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /** @return list<string|null> */
    private function arguments(): array
    {
        $this->skipSpace();
        if (($this->source[$this->position] ?? '') !== '(') {
            return [];
        }
        $this->position++;
        $arguments = [];
        while (true) {
            $this->skipSpace();
            $char = $this->source[$this->position] ?? '';
            if ($char === ')' || $char === '') {
                $this->position++;
                return $arguments;
            }
            $before = $this->position;
            $arguments[] = $this->expression();
            $this->skipSpace();
            if (($this->source[$this->position] ?? '') === ',') {
                $this->position++;
            } elseif ($this->position === $before) {
                // Something this grammar does not know: skip it rather than spin.
                $this->position++;
            }
        }
    }

    private function stringLiteral(string $quote): string
    {
        $this->position++;
        $out = '';
        while ($this->position < strlen($this->source)) {
            $char = $this->source[$this->position++];
            if ($char === '\\' && $this->position < strlen($this->source)) {
                $next = $this->source[$this->position++];
                $out .= match ($next) {
                    'n' => $quote === '"' ? "\n" : '\\n',
                    't' => $quote === '"' ? "\t" : '\\t',
                    default => $next,
                };
                continue;
            }
            if ($char === $quote) {
                return $out;
            }
            $out .= $char;
        }
        return $out;
    }

    private function identifier(): ?string
    {
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $this->source, $m, 0, $this->position)) {
            $this->position += strlen($m[0]);
            return $m[0];
        }
        return null;
    }

    /** Whitespace and comments, anywhere a token may follow. */
    private function skipSpace(): void
    {
        $length = strlen($this->source);
        while ($this->position < $length) {
            $char = $this->source[$this->position];
            if (ctype_space($char)) {
                $this->position++;
            } elseif (str_starts_with(substr($this->source, $this->position, 2), '/*')) {
                $end = strpos($this->source, '*/', $this->position + 2);
                $this->position = $end === false ? $length : $end + 2;
            } elseif (str_starts_with(substr($this->source, $this->position, 2), '//')) {
                $end = strpos($this->source, "\n", $this->position);
                $this->position = $end === false ? $length : $end + 1;
            } else {
                return;
            }
        }
    }
}
