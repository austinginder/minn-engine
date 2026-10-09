<?php

declare(strict_types=1);

namespace Minn\Blocks;

use JsonException;
use Minn\Json;

/**
 * Block markup read one token at a time, as the reference's streaming
 * block processor reads it (probe block-processor). A delimiter is an HTML
 * comment: whitespace after "<!--", an optional "/" (the closing flag),
 * "wp:", a name (lowercase, an optional namespace), whitespace, optional
 * JSON object attributes followed by whitespace, then "-->" or "/-->"
 * (void). Any other comment is HTML. The HTML between delimiters comes out
 * as its own token, reported as void (freeform at the top, inner HTML in a
 * block) before the delimiter that ends it, which is already read, so the
 * closing flag shows that delimiter's. Quirks kept on purpose:
 *   - trailing HTML after the last delimiter is dropped when it holds a
 *     comment;
 *   - an unterminated comment is incomplete input only when it could
 *     still become a delimiter, otherwise reading just stops;
 *   - attributes are parsed only when asked, and only that sets the JSON
 *     error.
 */
final class BlockStream
{
    public const CLOSER = 'closer';
    public const OPENER = 'opener';
    public const VOID = 'void';
    public const READY = 'processor-ready';
    public const MATCHED = 'processor-matched';
    public const HTML_SPAN = 'processor-html-span';
    public const INCOMPLETE_INPUT = 'incomplete-input';
    public const COMPLETE = 'processor-complete';
    private const WHITESPACE = " \t\n\r\f";
    private const DELIMITER = '#^[ \t\n\r\f]+(/)?wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)[ \t\n\r\f]+(?:(\{.*\})[ \t\n\r\f]+)?(/)?$#s';

    private string $state = self::READY;
    private ?string $error = null;
    private int $jsonError = 0;
    private int $cursor = 0;
    /** @var array{at: int, length: int, type: ?string, closing: bool, name: string, json: ?string} the last delimiter read */
    private array $delimiter = ['at' => 0, 'length' => 0, 'type' => null, 'closing' => false, 'name' => '', 'json' => null];
    /** @var array{0: int, 1: int} the HTML span being reported */
    private array $html = [0, 0];
    private bool $delimiterWaiting = false;
    private bool $popNext = false;
    /** @var list<string> */
    private array $open = [];

    /** A stream over block markup, before its first token. */
    public function __construct(private readonly string $text)
    {
    }

    /** The name with core/ in front when it has no namespace. */
    public static function normalize(string $name): string
    {
        return str_contains($name, '/') ? $name : 'core/' . $name;
    }

    /** Moves to the next token, HTML or delimiter; false at the end or on incomplete input. */
    public function next(): bool
    {
        if ($this->state === self::COMPLETE || $this->state === self::INCOMPLETE_INPUT) {
            return false;
        }
        if ($this->popNext) {
            array_pop($this->open);
            $this->popNext = false;
        }
        if ($this->delimiterWaiting) {
            $this->delimiterWaiting = false;
            return $this->matchDelimiter();
        }
        return $this->scan();
    }

    /** The current token's state: one of the class constants. */
    public function state(): string
    {
        return $this->state;
    }

    /** incomplete-input when the text ran out inside what could be a delimiter, else null. */
    public function error(): ?string
    {
        return $this->error;
    }

    /** The JSON error from the last time attributes were asked for. */
    public function jsonError(): int
    {
        return $this->jsonError;
    }

    /** opener, closer or void for a delimiter, void for HTML, null otherwise. */
    public function delimiterType(): ?string
    {
        return match ($this->state) {
            self::MATCHED => $this->delimiter['type'],
            self::HTML_SPAN => self::VOID,
            default => null,
        };
    }

    /** The last delimiter read had the closing flag. */
    public function closing(): bool
    {
        return $this->delimiter['closing'];
    }

    /** The delimiter's block type, null for anything else. */
    public function blockType(): ?string
    {
        return $this->state === self::MATCHED ? $this->delimiter['name'] : null;
    }

    /** The block type, or core/freeform for top-level HTML and #innerHTML for HTML inside a block. */
    public function printableType(): ?string
    {
        return match ($this->state) {
            self::MATCHED => $this->delimiter['name'],
            self::HTML_SPAN => count($this->open) === 1 ? 'core/freeform' : '#innerHTML',
            default => null,
        };
    }

    /** Whether the token is HTML between delimiters. */
    public function isHtml(): bool
    {
        return $this->state === self::HTML_SPAN;
    }

    /** The HTML span's text, null for anything else. */
    public function htmlContent(): ?string
    {
        return $this->state === self::HTML_SPAN ? substr($this->text, $this->html[0], $this->html[1]) : null;
    }

    /** Whether the token is HTML with something other than whitespace in it. */
    public function nonWhitespaceHtml(): bool
    {
        return $this->state === self::HTML_SPAN && strspn($this->text, self::WHITESPACE, $this->html[0], $this->html[1]) < $this->html[1];
    }

    /** The open block types, outermost first, with #html for an HTML span. @return list<string> */
    public function breadcrumbs(): array
    {
        return $this->open;
    }

    /** Whether this token opens a block (of the type, when given): never after a closer, and HTML only at the top. */
    public function opens(?string $type): bool
    {
        if ($this->state === self::HTML_SPAN) {
            return count($this->open) === 1 && ($type === null || self::normalize($type) === 'core/freeform');
        }
        if ($this->delimiter['type'] === self::CLOSER) {
            return false;
        }
        return $type === null || self::normalize($type) === $this->delimiter['name'];
    }

    /** Whether the token is of the type; * matches anything. */
    public function isType(string $type): bool
    {
        return match (true) {
            $type === '*' => true,
            $this->state === self::HTML_SPAN => count($this->open) === 1 && self::normalize($type) === 'core/freeform',
            default => $this->state === self::MATCHED && self::normalize($type) === $this->delimiter['name'],
        };
    }

    /** The token's start and length in the text, null when there is no token. @return array{0: int, 1: int}|null */
    public function span(): ?array
    {
        return match ($this->state) {
            self::MATCHED => [$this->delimiter['at'], $this->delimiter['length']],
            self::HTML_SPAN => $this->html,
            default => null,
        };
    }

    /** The delimiter's attributes, parsed now (setting the JSON error), or null. @return array<mixed>|null */
    public function attributes(): ?array
    {
        $json = $this->state === self::MATCHED ? $this->delimiter['json'] : null;
        if ($json === null) {
            $this->jsonError = JSON_ERROR_NONE;
            return null;
        }
        try {
            $parsed = Json::decode($json);
        } catch (JsonException $e) {
            $this->jsonError = $e->getCode();
            return null;
        }
        $this->jsonError = JSON_ERROR_NONE;
        return is_array($parsed) ? $parsed : null;
    }

    /** From where the last token ended: the HTML before the next delimiter, the delimiter, or the end. */
    private function scan(): bool
    {
        $found = $this->nextDelimiter($this->cursor);
        if ($found === false) {
            return $this->state === self::INCOMPLETE_INPUT ? false : $this->finish(null);
        }
        if ($found === null) {
            $rest = strlen($this->text) - $this->cursor;
            return $rest > 0 ? $this->emitHtml($this->cursor, $rest) : $this->finish(null);
        }
        $this->delimiter = $found;
        if ($found['at'] > $this->cursor) {
            $this->delimiterWaiting = true;
            return $this->emitHtml($this->cursor, $found['at'] - $this->cursor);
        }
        return $this->matchDelimiter();
    }

    private function emitHtml(int $at, int $length): bool
    {
        $this->state = self::HTML_SPAN;
        $this->html = [$at, $length];
        $this->cursor = $at + $length;
        $this->open[] = '#html';
        $this->popNext = true;
        return true;
    }

    private function matchDelimiter(): bool
    {
        $this->state = self::MATCHED;
        $this->cursor = $this->delimiter['at'] + $this->delimiter['length'];
        if ($this->delimiter['type'] === self::CLOSER) {
            array_pop($this->open);
            return true;
        }
        $this->open[] = $this->delimiter['name'];
        $this->popNext = $this->delimiter['type'] === self::VOID;
        return true;
    }

    private function finish(?string $error): bool
    {
        $this->state = $error === null ? self::COMPLETE : self::INCOMPLETE_INPUT;
        $this->error = $error;
        return false;
    }

    /**
     * The next delimiter from a position; null when there is none and no
     * comment came first (the rest is HTML), false when reading stops there
     * (a comment came first, or one is cut off; cut off where a delimiter
     * could still begin is incomplete input).
     *
     * @return array{at: int, length: int, type: string, closing: bool, name: string, json: ?string}|false|null
     */
    private function nextDelimiter(int $from): array|false|null
    {
        $sawComment = false;
        while (($at = strpos($this->text, '<!--', $from)) !== false) {
            $end = $this->commentEnd($at);
            if ($end === null) {
                return $this->couldBeDelimiter($at) ? $this->finish(self::INCOMPLETE_INPUT) : false;
            }
            $inner = substr($this->text, $at + 4, max(0, $end - 3 - $at - 4));
            if (preg_match(self::DELIMITER, $inner, $m) === 1) {
                $void = ($m[4] ?? '') === '/';
                return ['at' => $at, 'length' => $end - $at, 'type' => $void ? self::VOID : ($m[1] === '/' ? self::CLOSER : self::OPENER), 'closing' => $m[1] === '/', 'name' => self::normalize($m[2]), 'json' => ($m[3] ?? '') === '' ? null : $m[3]];
            }
            $sawComment = true;
            $from = $end;
        }
        return $sawComment ? false : null;
    }

    /** Where a comment starting here ends (just past it), or null when it never does. */
    private function commentEnd(int $at): ?int
    {
        foreach (['<!-->', '<!--->'] as $abrupt) {
            if (substr_compare($this->text, $abrupt, $at, strlen($abrupt)) === 0) {
                return $at + strlen($abrupt);
            }
        }
        $close = strpos($this->text, '-->', $at + 4);
        return $close === false ? null : $close + 3;
    }

    /** Whether a comment cut off by the end of the text could still have become a delimiter. */
    private function couldBeDelimiter(int $at): bool
    {
        $rest = substr($this->text, $at + 4);
        if ($rest === '') {
            return true;
        }
        if (strspn($rest, self::WHITESPACE) === 0) {
            return false;
        }
        $rest = ltrim($rest, self::WHITESPACE);
        foreach (['wp:', '/wp:'] as $start) {
            if (str_starts_with($start, $rest) || str_starts_with($rest, $start)) {
                return true;
            }
        }
        return false;
    }
}
