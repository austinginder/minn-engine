<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * A streaming HTML tokenizer with in-place edits: tags, text, comments,
 * doctypes, and processing instructions in document order, attribute reads
 * with character references decoded, attribute and class edits applied to
 * the source without reserialising anything else, bookmarks to seek back to.
 * Behaviour pinned by the html-tag-processor probe fixture.
 */
final class Tags
{
    public const TAG = '#tag';
    public const TEXT = '#text';
    public const COMMENT = '#comment';
    public const DOCTYPE = '#doctype';
    public const PI = '#processing-instruction';
    public const FUNKY = '#funky-comment';
    public const CDATA = '#cdata-section';

    public const COMMENT_HTML = 'COMMENT_AS_HTML_COMMENT';
    public const COMMENT_ABRUPT = 'COMMENT_AS_ABRUPTLY_CLOSED_COMMENT';
    public const COMMENT_INVALID = 'COMMENT_AS_INVALID_HTML';
    public const COMMENT_CDATA = 'COMMENT_AS_CDATA_LOOKALIKE';
    public const COMMENT_PI = 'COMMENT_AS_PI_NODE_LOOKALIKE';

    /** Elements whose body is raw text up to the closer. */
    public const RAW_TEXT = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes'];
    private const RAW_DECODED = ['textarea', 'title'];
    private const MAX_BOOKMARKS = 10;

    private string $html;
    private int $cursor = 0;
    private bool $ended = false;
    private bool $paused = false;

    private ?string $type = null;
    private ?string $name = null;
    private int $start = 0;
    private int $end = 0;
    private int $nameEnd = 0;
    private bool $closer = false;
    private bool $selfClosing = false;
    /** @var list<array{name: string, lower: string, start: int, end: int, value: ?string}> */
    private array $attributes = [];
    private int $textStart = 0;
    private int $textLength = 0;
    private ?string $commentType = null;
    private int $fullTextStart = 0;
    private int $fullTextLength = 0;

    private Edits $edits;
    /** @var array<string, array{0: int, 1: int}> */
    private array $bookmarks = [];

    public function __construct(string $html)
    {
        $this->html = $html;
        $this->edits = new Edits();
    }

    /** Advances to the next token; false at the end or at an incomplete one. */
    public function nextToken(): bool
    {
        $this->flush();
        if ($this->ended || $this->paused) {
            return false;
        }
        $this->resetToken();
        $html = $this->html;
        $length = strlen($html);
        $at = $this->cursor;
        if ($at >= $length) {
            $this->ended = true;
            return false;
        }
        if ($html[$at] !== '<') {
            $lt = strpos($html, '<', $at);
            $end = $lt === false ? $length : $lt;
            $this->setToken(self::TEXT, '#text', $at, $end, $at, $end - $at);
            $this->cursor = $end;
            return true;
        }
        $next = $html[$at + 1] ?? '';
        if ($next === '!') {
            return $this->take(Scanner::markupDeclaration($html, $at));
        }
        if ($next === '?') {
            return $this->take(Scanner::question($html, $at));
        }
        if ($next === '/') {
            $third = $html[$at + 2] ?? '';
            if ($third !== '' && ctype_alpha($third)) {
                return $this->takeTag($at, true);
            }
            if ($third === '>') {
                // "</>" is nothing at all: skip it.
                $this->cursor = $at + 3;
                return $this->nextToken();
            }
            $gt = strpos($html, '>', $at + 2);
            if ($gt === false) {
                $this->paused = true;
                return false;
            }
            $this->setToken(self::FUNKY, '#funky-comment', $at, $gt + 1, $at + 2, $gt - $at - 2);
            $this->cursor = $gt + 1;
            return true;
        }
        if ($next !== '' && ctype_alpha($next)) {
            return $this->takeTag($at, false);
        }
        // A lone "<" is text.
        $lt = strpos($html, '<', $at + 1);
        $end = $lt === false ? $length : $lt;
        $this->setToken(self::TEXT, '#text', $at, $end, $at, $end - $at);
        $this->cursor = $end;
        return true;
    }

    private function setToken(string $type, string $name, int $start, int $end, int $textStart, int $textLength): void
    {
        $this->type = $type;
        $this->name = $name;
        $this->start = $start;
        $this->end = $end;
        $this->textStart = $textStart;
        $this->textLength = $textLength;
    }

    private function resetToken(): void
    {
        $this->type = null;
        $this->name = null;
        $this->closer = false;
        $this->selfClosing = false;
        $this->attributes = [];
        $this->commentType = null;
        $this->textLength = 0;
        $this->fullTextLength = 0;
    }

    /** A tag the scanner read at $at becomes the current token; a raw-text element carries its body to its closer. */
    private function takeTag(int $at, bool $closer): bool
    {
        $tag = Scanner::tag($this->html, $at, $at + ($closer ? 2 : 1));
        if ($tag === null) {
            $this->paused = true;
            return false;
        }
        $this->setToken(self::TAG, strtoupper($tag['name']), $at, $tag['end'], $tag['end'], 0);
        $this->nameEnd = $tag['nameEnd'];
        $this->closer = $closer;
        $this->selfClosing = $tag['selfClosing'];
        $this->attributes = $closer ? [] : $tag['attributes'];
        $this->cursor = $tag['end'];
        if (!$closer && in_array($tag['name'], self::RAW_TEXT, true)) {
            $raw = Scanner::rawText($this->html, $tag['name'], $tag['end']);
            if ($raw === null) {
                $this->paused = true;
                $this->resetToken();
                return false;
            }
            $this->textStart = $raw['textStart'];
            $this->textLength = $raw['textLength'];
            $this->end = $raw['end'];
            $this->cursor = $raw['end'];
        }
        return true;
    }

    /** A comment, doctype, or processing instruction the scanner read becomes the current token; null pauses. */
    private function take(?array $found): bool
    {
        if ($found === null) {
            $this->paused = true;
            return false;
        }
        $type = match ($found['kind']) { 'comment' => self::COMMENT, 'doctype' => self::DOCTYPE, default => self::PI };
        $name = match ($found['kind']) { 'comment' => '#comment', 'doctype' => 'html', default => '#processing-instruction' };
        $this->setToken($type, $name, $found['start'], $found['end'], $found['textStart'], $found['textLength']);
        if ($found['kind'] === 'comment') {
            $this->commentType = $found['commentType'];
            $this->fullTextStart = $found['fullStart'];
            $this->fullTextLength = $found['fullLength'];
        } elseif ($found['kind'] === 'pi') {
            $this->name = 'php';
        }
        $this->cursor = $found['end'];
        return true;
    }

    /**
     * Advances to the next tag matching the query; false when none.
     *
     * @param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query
     */
    public function nextTag(array $query): bool
    {
        $tagName = isset($query['tag_name']) ? strtoupper((string) $query['tag_name']) : null;
        $className = $query['class_name'] ?? null;
        $offset = max(0, (int) ($query['match_offset'] ?? 1));
        $visitClosers = ($query['tag_closers'] ?? 'skip') === 'visit';
        if ($offset === 0) {
            $this->flush();
            return false;
        }
        $seen = 0;
        while ($this->nextToken()) {
            if ($this->type !== self::TAG) {
                continue;
            }
            if ($this->closer && !$visitClosers) {
                continue;
            }
            if ($tagName !== null && $tagName !== $this->name) {
                continue;
            }
            if ($className !== null && !$this->hasClass((string) $className)) {
                continue;
            }
            if (++$seen === $offset) {
                return true;
            }
        }
        return false;
    }

    /** Whether the scan stopped inside an unfinished token. */
    public function pausedAtIncompleteToken(): bool
    {
        return $this->paused;
    }

    /** The current token's kind. */
    public function tokenType(): ?string
    {
        return $this->type;
    }

    /** The current token's name. */
    public function tokenName(): ?string
    {
        return match ($this->type) {
            self::TAG => $this->name,
            self::DOCTYPE => 'html',
            null => null,
            default => $this->type,
        };
    }

    /** The current tag name, upper-cased. */
    public function tag(): ?string
    {
        return match ($this->type) {
            self::TAG, self::PI => $this->name,
            default => null,
        };
    }

    /** Whether the current tag is a closer. */
    public function isCloser(): bool
    {
        return $this->type === self::TAG && $this->closer;
    }

    /** Whether the current tag has the self-closing flag. */
    public function selfClosing(): bool
    {
        return $this->type === self::TAG && $this->selfClosing;
    }

    /** The current comment's kind. */
    public function commentType(): ?string
    {
        return $this->type === self::COMMENT ? $this->commentType : null;
    }

    /** The current comment's whole text. */
    public function fullCommentText(): ?string
    {
        return $this->type === self::COMMENT ? substr($this->html, $this->fullTextStart, $this->fullTextLength) : null;
    }

    /** The current token's text as a reader sees it. */
    public function modifiableText(): string
    {
        if ($this->type === null) {
            return '';
        }
        $raw = substr($this->html, $this->textStart, $this->textLength);
        if ($this->type === self::TEXT || ($this->type === self::TAG && in_array(strtolower((string) $this->name), self::RAW_DECODED, true))) {
            return Decoder::text($raw);
        }
        return $raw;
    }

    /** Doctype pieces, or null when the token is not a doctype. @return array{name: ?string, public: ?string, system: ?string}|null */
    public function doctype(): ?array
    {
        if ($this->type !== self::DOCTYPE) {
            return null;
        }
        return Scanner::doctype(substr($this->html, $this->textStart, $this->textLength));
    }

    /** The decoded value, true for a bare attribute, null when absent; a pending edit answers first. */
    public function attribute(string $name): string|true|null
    {
        if ($this->type !== self::TAG || $this->closer) {
            return null;
        }
        $lower = strtolower($name);
        if ($lower === 'class' && $this->edits->hasClassEdits()) {
            $list = $this->classes();
            return $list === [] ? null : implode(' ', $list);
        }
        $pending = $this->edits->attribute($lower);
        if ($pending !== null) {
            return $pending['value'];
        }
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === $lower) {
                return $attr['value'] === null ? true : Decoder::attribute($attr['value']);
            }
        }
        return null;
    }

    /**
     * The current tag's attribute names with a prefix, lower-cased.
     *
     * @return list<string>|null lowercase names, in source order, null off a tag
     */
    public function attributeNames(string $prefix): ?array
    {
        if ($this->type !== self::TAG || $this->closer) {
            return null;
        }
        $prefix = strtolower($prefix);
        $names = [];
        foreach ($this->attributes as $attr) {
            if (str_starts_with($attr['lower'], $prefix) && !in_array($attr['lower'], $names, true)) {
                $names[] = $attr['lower'];
            }
        }
        return $names;
    }

    /**
     * The current tag's classes.
     *
     * @return list<string> distinct class names after pending edits
     */
    public function classes(): array
    {
        if ($this->type !== self::TAG || $this->closer) {
            return [];
        }
        return $this->edits->classesAfter($this->baseClassList());
    }

    /** Whether the current tag has a class. */
    public function hasClass(string $class): bool
    {
        return in_array($class, $this->classes(), true);
    }

    /** @return list<string> */
    private function baseClassList(): array
    {
        $value = $this->baseClassValue();
        if ($value === null || $value === '') {
            return [];
        }
        return preg_split('/[ \t\n\r\f]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** The class attribute's decoded value before class edits: a pending set, else the source. */
    private function baseClassValue(): ?string
    {
        $pending = $this->edits->attribute('class');
        if ($pending !== null) {
            return $pending['value'] === true ? '' : $pending['value'];
        }
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === 'class') {
                return $attr['value'] === null ? '' : Decoder::attribute($attr['value']);
            }
        }
        return null;
    }

    /** Sets an attribute on the current tag. */
    public function setAttribute(string $name, string|bool|int|float|null $value): bool
    {
        if ($this->type !== self::TAG || $this->closer || $value === null) {
            return false;
        }
        if ($value === false) {
            return $this->removeAttribute($name);
        }
        if (!Edits::validName($name)) {
            return false;
        }
        $this->edits->setAttribute($name, $value === true ? true : (string) $value);
        return true;
    }

    /** Removes an attribute from the current tag. */
    public function removeAttribute(string $name): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        $lower = strtolower($name);
        $exists = false;
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === $lower) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            // Only a pending addition: cancelling it is not a removal.
            $this->edits->cancelAttribute($name);
            return false;
        }
        $this->edits->removeAttribute($name);
        return true;
    }

    /** Adds a class to the current tag. */
    public function addClass(string $class): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        $this->edits->addClass($class);
        return true;
    }

    /** Removes a class from the current tag. */
    public function removeClass(string $class): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        $this->edits->removeClass($class);
        return true;
    }

    /** Replaces the current token's text, when its kind allows it. */
    public function setModifiableText(string $text): bool
    {
        return $this->edits->setTextFor($this->type, $this->closer ? null : strtolower((string) $this->name), $this->commentType, $text);
    }

    /** Names the current token's position. */
    public function setBookmark(string $name): bool
    {
        if ($this->type === null || $this->paused) {
            return false;
        }
        if (!isset($this->bookmarks[$name]) && count($this->bookmarks) >= self::MAX_BOOKMARKS) {
            return false;
        }
        $this->bookmarks[$name] = [$this->start, $this->end];
        return true;
    }

    /** Forgets a bookmark. */
    public function releaseBookmark(string $name): bool
    {
        if (!isset($this->bookmarks[$name])) {
            return false;
        }
        unset($this->bookmarks[$name]);
        return true;
    }

    /** Whether a bookmark exists. */
    public function hasBookmark(string $name): bool
    {
        return isset($this->bookmarks[$name]);
    }

    /** Moves to a bookmark. */
    public function seek(string $name): bool
    {
        if (!isset($this->bookmarks[$name])) {
            return false;
        }
        $this->flush();
        $this->cursor = $this->bookmarks[$name][0];
        $this->ended = false;
        $this->paused = false;
        return $this->nextToken();
    }

    /** The document with every update written in. */
    public function html(): string
    {
        $this->flush();
        return $this->html;
    }

    /** Writes every pending update into the source, keeping bookmarks and the current token aligned. */
    private function flush(): void
    {
        if ($this->type === null) {
            return;
        }
        $replacements = [];
        $text = $this->edits->takeText();
        if ($text !== null) {
            $replacements[] = [$this->textStart, $this->textStart + $this->textLength, $text];
        }
        if ($this->type === self::TAG && !$this->closer && $this->edits->hasTagEdits()) {
            $replacements = [...$replacements, ...$this->edits->takeReplacements($this->attributes, $this->nameEnd, $this->baseClassValue())];
        }
        if ($replacements === []) {
            return;
        }
        $this->applyReplacements($replacements);
        $this->rescanCurrent();
    }

    /**
     * Splices the edits into the source, ascending, and moves the bookmarks
     * and the cursor by what the token gained or lost.
     *
     * @param list<array{int, int, string}> $replacements
     */
    private function applyReplacements(array $replacements): void
    {
        usort($replacements, static fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $out = '';
        $cursor = 0;
        $delta = 0;
        foreach ($replacements as [$start, $end, $text]) {
            $out .= substr($this->html, $cursor, $start - $cursor) . $text;
            $cursor = $end;
            $delta += strlen($text) - ($end - $start);
        }
        $out .= substr($this->html, $cursor);
        $tokenStart = $this->start;
        $tokenEnd = $this->end;
        foreach ($this->bookmarks as $name => [$bStart, $bEnd]) {
            if ($bStart > $tokenStart) {
                $this->bookmarks[$name] = [$bStart + $delta, $bEnd + $delta];
            } elseif ($bStart === $tokenStart) {
                $this->bookmarks[$name] = [$bStart, $tokenEnd + $delta];
            }
        }
        $this->html = $out;
        $this->cursor = $tokenEnd + $delta;
    }

    /** Re-reads the current token so attribute offsets match the new source. */
    private function rescanCurrent(): void
    {
        $tokenStart = $this->start;
        $wasEnded = $this->ended;
        $wasPaused = $this->paused;
        $resume = $this->cursor;
        $this->cursor = $tokenStart;
        $this->ended = false;
        $this->paused = false;
        $this->edits->clear();
        $this->rescan();
        $this->cursor = $resume;
        $this->ended = $wasEnded;
        $this->paused = $wasPaused;
    }

    private function rescan(): void
    {
        $type = $this->type;
        $name = $this->name;
        $this->type = null;
        $saved = $this->bookmarks;
        $this->nextToken();
        $this->bookmarks = $saved;
        if ($this->type === null) {
            $this->type = $type;
            $this->name = $name;
        }
    }

}
