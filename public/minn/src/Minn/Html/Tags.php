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

    private const RAW_TEXT = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes'];
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

    /** @var array<string, array{name: string, value: string|true|null}> pending attribute sets (null = remove), by lowercase name, in call order */
    private array $attributeUpdates = [];
    /** @var array<string, bool> pending class additions (true) and removals (false), in call order */
    private array $classUpdates = [];
    private ?string $textUpdate = null;
    /** @var array<string, array{0: int, 1: int}> */
    private array $bookmarks = [];

    public function __construct(string $html)
    {
        $this->html = $html;
    }

    // ---- Scanning.

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
            return $this->scanMarkupDeclaration($at);
        }
        if ($next === '?') {
            return $this->scanQuestion($at);
        }
        if ($next === '/') {
            $third = $html[$at + 2] ?? '';
            if ($third !== '' && ctype_alpha($third)) {
                return $this->scanTag($at, true);
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
            return $this->scanTag($at, false);
        }
        // A lone "<" is text.
        $lt = strpos($html, '<', $at + 1);
        $end = $lt === false ? $length : $lt;
        $this->setToken(self::TEXT, '#text', $at, $end, $at, $end - $at);
        $this->cursor = $end;
        return true;
    }

    private function scanTag(int $at, bool $closer): bool
    {
        $html = $this->html;
        $length = strlen($html);
        $nameStart = $at + ($closer ? 2 : 1);
        $i = $nameStart;
        while ($i < $length && !self::isSpace($html[$i]) && $html[$i] !== '/' && $html[$i] !== '>') {
            $i++;
        }
        $name = strtolower(substr($html, $nameStart, $i - $nameStart));
        $nameEnd = $i;
        $attributes = [];
        $selfClosing = false;
        while (true) {
            while ($i < $length && (self::isSpace($html[$i]) || $html[$i] === '/')) {
                $i++;
            }
            if ($i >= $length) {
                $this->paused = true;
                return false;
            }
            if ($html[$i] === '>') {
                $selfClosing = $i > $nameEnd && $html[$i - 1] === '/' && ($i - 1 > $nameEnd || !$closer);
                // The flag is the slash immediately before ">" that is not part of an unquoted value.
                if ($attributes !== [] && end($attributes)['end'] === $i && end($attributes)['value'] !== null && !end($attributes)['quoted']) {
                    $selfClosing = false;
                }
                $i++;
                break;
            }
            // Attribute name: runs to whitespace, "/", ">" or "=" (a leading "=" is part of the name).
            $attrStart = $i;
            if ($html[$i] === '=') {
                $i++;
            }
            while ($i < $length && !self::isSpace($html[$i]) && $html[$i] !== '/' && $html[$i] !== '>' && $html[$i] !== '=') {
                $i++;
            }
            $attrName = substr($html, $attrStart, $i - $attrStart);
            $j = $i;
            while ($j < $length && self::isSpace($html[$j])) {
                $j++;
            }
            $value = null;
            $quoted = false;
            $attrEnd = $i;
            if ($j < $length && $html[$j] === '=') {
                $j++;
                while ($j < $length && self::isSpace($html[$j])) {
                    $j++;
                }
                if ($j >= $length) {
                    $this->paused = true;
                    return false;
                }
                $quote = $html[$j];
                if ($quote === '"' || $quote === "'") {
                    $close = strpos($html, $quote, $j + 1);
                    if ($close === false) {
                        $this->paused = true;
                        return false;
                    }
                    $value = substr($html, $j + 1, $close - $j - 1);
                    $quoted = true;
                    $attrEnd = $close + 1;
                } else {
                    $k = $j;
                    while ($k < $length && !self::isSpace($html[$k]) && $html[$k] !== '>') {
                        $k++;
                    }
                    $value = substr($html, $j, $k - $j);
                    $attrEnd = $k;
                }
                $i = $attrEnd;
            }
            if ($attrName === '') {
                // A stray character such as a quote before "=": consume it as a name.
                $i = max($i, $attrStart + 1);
                continue;
            }
            $attributes[] = ['name' => $attrName, 'lower' => strtolower($attrName), 'start' => $attrStart, 'end' => $attrEnd, 'value' => $value, 'quoted' => $quoted];
        }
        $this->setToken(self::TAG, strtoupper($name), $at, $i, $i, 0);
        $this->nameEnd = $nameEnd;
        $this->closer = $closer;
        $this->selfClosing = $selfClosing;
        $this->attributes = $closer ? [] : $attributes;
        $this->cursor = $i;
        if (!$closer && in_array($name, self::RAW_TEXT, true)) {
            $closeAt = stripos($html, '</' . $name, $i);
            if ($closeAt === false) {
                $this->paused = true;
                $this->resetToken();
                return false;
            }
            $gt = strpos($html, '>', $closeAt);
            if ($gt === false) {
                $this->paused = true;
                $this->resetToken();
                return false;
            }
            $this->textStart = $i;
            $this->textLength = $closeAt - $i;
            $this->end = $gt + 1;
            $this->cursor = $gt + 1;
        }
        return true;
    }

    private function scanMarkupDeclaration(int $at): bool
    {
        $html = $this->html;
        if (substr($html, $at, 4) === '<!--') {
            if (substr($html, $at, 5) === '<!-->') {
                $this->setComment($at, $at + 5, $at + 4, 0, self::COMMENT_ABRUPT, $at + 4, 0);
                return true;
            }
            if (substr($html, $at, 6) === '<!--->') {
                $this->setComment($at, $at + 6, $at + 4, 0, self::COMMENT_ABRUPT, $at + 4, 0);
                return true;
            }
            $search = $at + 4;
            while (true) {
                $dashes = strpos($html, '--', $search);
                if ($dashes === false) {
                    $this->paused = true;
                    return false;
                }
                $after = substr($html, $dashes + 2, 2);
                if (($after[0] ?? '') === '>') {
                    $this->setComment($at, $dashes + 3, $at + 4, $dashes - $at - 4, self::COMMENT_HTML, $at + 4, $dashes - $at - 4);
                    return true;
                }
                if ($after === '!>') {
                    $this->setComment($at, $dashes + 4, $at + 4, $dashes - $at - 4, self::COMMENT_HTML, $at + 4, $dashes - $at - 4);
                    return true;
                }
                $search = $dashes + 1;
            }
        }
        if (strtoupper(substr($html, $at, 9)) === '<!DOCTYPE') {
            $gt = strpos($html, '>', $at + 9);
            if ($gt === false) {
                $this->paused = true;
                return false;
            }
            $this->setToken(self::DOCTYPE, 'html', $at, $gt + 1, $at + 9, $gt - $at - 9);
            $this->cursor = $gt + 1;
            return true;
        }
        // Bogus comment: "<!" up to the next ">".
        $gt = strpos($html, '>', $at + 2);
        if ($gt === false) {
            $this->paused = true;
            return false;
        }
        if (substr($html, $at, 9) === '<![CDATA[' && substr($html, $gt - 2, 2) === ']]' && $gt - 2 >= $at + 9) {
            $this->setComment($at, $gt + 1, $at + 9, $gt - 2 - $at - 9, self::COMMENT_CDATA, $at + 2, $gt - $at - 2);
            return true;
        }
        $this->setComment($at, $gt + 1, $at + 2, $gt - $at - 2, self::COMMENT_INVALID, $at + 2, $gt - $at - 2);
        return true;
    }

    private function scanQuestion(int $at): bool
    {
        $html = $this->html;
        $gt = strpos($html, '>', $at + 2);
        if ($gt === false) {
            $this->paused = true;
            return false;
        }
        if (strtolower(substr($html, $at, 5)) === '<?php' && substr($html, $gt - 1, 1) === '?') {
            // PHP tags read as processing instructions with a "php" target.
            $bodyStart = $at + 5;
            if ($bodyStart < $gt - 1 && self::isSpace($html[$bodyStart])) {
                $bodyStart++;
            }
            $this->setToken(self::PI, '#processing-instruction', $at, $gt + 1, $bodyStart, max(0, $gt - 1 - $bodyStart));
            $this->name = 'php';
            $this->cursor = $gt + 1;
            return true;
        }
        if ($html[$gt - 1] === '?' && preg_match('/^<\?([a-zA-Z][a-zA-Z0-9]*)/', substr($html, $at, min(64, $gt - $at)), $m)) {
            $bodyStart = $at + 2 + strlen($m[1]);
            $this->setComment($at, $gt + 1, $bodyStart, max(0, $gt - 1 - $bodyStart), self::COMMENT_PI, $at + 1, $gt - $at - 1);
            return true;
        }
        $this->setComment($at, $gt + 1, $at + 2, $gt - $at - 2, self::COMMENT_HTML, $at + 2, $gt - $at - 2);
        return true;
    }

    private function setComment(int $start, int $end, int $textStart, int $textLength, string $type, int $fullStart, int $fullLength): void
    {
        $this->setToken(self::COMMENT, '#comment', $start, $end, $textStart, $textLength);
        $this->commentType = $type;
        $this->fullTextStart = $fullStart;
        $this->fullTextLength = $fullLength;
        $this->cursor = $end;
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

    private static function isSpace(string $c): bool
    {
        return $c === ' ' || $c === "\t" || $c === "\n" || $c === "\r" || $c === "\f";
    }

    /** @param array{tag_name?: ?string, class_name?: ?string, match_offset?: int, tag_closers?: string} $query */
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

    public function pausedAtIncompleteToken(): bool
    {
        return $this->paused;
    }

    // ---- Reads.

    public function tokenType(): ?string
    {
        return $this->type;
    }

    public function tokenName(): ?string
    {
        return match ($this->type) {
            self::TAG => $this->name,
            self::DOCTYPE => 'html',
            null => null,
            default => $this->type,
        };
    }

    public function tag(): ?string
    {
        return match ($this->type) {
            self::TAG, self::PI => $this->name,
            default => null,
        };
    }

    public function isCloser(): bool
    {
        return $this->type === self::TAG && $this->closer;
    }

    public function selfClosing(): bool
    {
        return $this->type === self::TAG && $this->selfClosing;
    }

    public function commentType(): ?string
    {
        return $this->type === self::COMMENT ? $this->commentType : null;
    }

    public function fullCommentText(): ?string
    {
        return $this->type === self::COMMENT ? substr($this->html, $this->fullTextStart, $this->fullTextLength) : null;
    }

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
        $body = trim(substr($this->html, $this->textStart, $this->textLength));
        if ($body === '') {
            return ['name' => null, 'public' => null, 'system' => null];
        }
        preg_match('/^(\S+)\s*(.*)$/s', $body, $m);
        $name = strtolower($m[1]);
        $rest = $m[2] ?? '';
        $public = null;
        $system = null;
        if (preg_match('/^PUBLIC\s+(["\'])(.*?)\1\s*(?:(["\'])(.*?)\3)?/is', $rest, $pm)) {
            $public = $pm[2];
            $system = isset($pm[4]) ? $pm[4] : null;
        } elseif (preg_match('/^SYSTEM\s+(["\'])(.*?)\1/is', $rest, $sm)) {
            $system = $sm[2];
        }
        return ['name' => $name, 'public' => $public, 'system' => $system];
    }

    /** The decoded value, true for a bare attribute, null when absent; a pending edit answers first. */
    public function attribute(string $name): string|true|null
    {
        if ($this->type !== self::TAG || $this->closer) {
            return null;
        }
        $lower = strtolower($name);
        if ($lower === 'class' && $this->classUpdates !== []) {
            $list = $this->classes();
            return $list === [] ? null : implode(' ', $list);
        }
        if (array_key_exists($lower, $this->attributeUpdates)) {
            $value = $this->attributeUpdates[$lower]['value'];
            return $value === true ? true : ($value === null ? null : $value);
        }
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === $lower) {
                return $attr['value'] === null ? true : Decoder::attribute($attr['value']);
            }
        }
        return null;
    }

    /** @return list<string>|null lowercase names, in source order, null off a tag */
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

    /** @return list<string> distinct class names after pending edits */
    public function classes(): array
    {
        if ($this->type !== self::TAG || $this->closer) {
            return [];
        }
        $list = [];
        foreach ($this->baseClassList() as $class) {
            if (!in_array($class, $list, true)) {
                $list[] = $class;
            }
        }
        foreach ($this->classUpdates as $class => $add) {
            $class = (string) $class;
            if ($add && !in_array($class, $list, true)) {
                $list[] = $class;
            } elseif (!$add) {
                $list = array_values(array_filter($list, static fn (string $c) => $c !== $class));
            }
        }
        return $list;
    }

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
        if (array_key_exists('class', $this->attributeUpdates)) {
            $value = $this->attributeUpdates['class']['value'];
            return $value === true ? '' : $value;
        }
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === 'class') {
                return $attr['value'] === null ? '' : Decoder::attribute($attr['value']);
            }
        }
        return null;
    }

    // ---- Edits.

    public function setAttribute(string $name, string|bool|int|float|null $value): bool
    {
        if ($this->type !== self::TAG || $this->closer || $value === null) {
            return false;
        }
        if ($value === false) {
            return $this->removeAttribute($name);
        }
        if ($name === '' || preg_match('/[\s"\'>\/=\x00-\x1F\x7F]/', $name) || preg_match('/[\p{Cc}\p{Zs}]/u', $name) && preg_match('/\s/', $name)) {
            return false;
        }
        if (preg_match('/[^\x21-\x7E\x80-\xFF]/', $name)) {
            return false;
        }
        $lower = strtolower($name);
        if ($lower === 'class') {
            $this->classUpdates = [];
        }
        $this->attributeUpdates[$lower] = ['name' => $name, 'value' => $value === true ? true : (string) $value];
        return true;
    }

    public function removeAttribute(string $name): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        $lower = strtolower($name);
        if ($lower === 'class') {
            $this->classUpdates = [];
        }
        $exists = false;
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === $lower) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            // Only a pending addition: cancelling it is not a removal.
            unset($this->attributeUpdates[$lower]);
            return false;
        }
        unset($this->attributeUpdates[$lower]);
        $this->attributeUpdates[$lower] = ['name' => $name, 'value' => null];
        return true;
    }

    public function addClass(string $class): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        unset($this->classUpdates[$class]);
        $this->classUpdates[$class] = true;
        return true;
    }

    public function removeClass(string $class): bool
    {
        if ($this->type !== self::TAG || $this->closer) {
            return false;
        }
        unset($this->classUpdates[$class]);
        $this->classUpdates[$class] = false;
        return true;
    }

    public function setModifiableText(string $text): bool
    {
        if ($this->type === self::TEXT) {
            $this->textUpdate = strtr($text, ['&' => '&amp;', '<' => '&lt;']);
            return true;
        }
        if ($this->type === self::COMMENT) {
            if ($this->commentType !== self::COMMENT_HTML || str_contains($text, '-->')) {
                return false;
            }
            $this->textUpdate = $text;
            return true;
        }
        if ($this->type === self::TAG && !$this->closer) {
            $name = strtolower((string) $this->name);
            if ($name === 'script' || $name === 'style') {
                $this->textUpdate = preg_replace_callback('#</(' . $name[0] . ')(?=' . substr($name, 1) . ')#i', static fn (array $m) => '</\\u00' . dechex(ord($m[1])), $text) ?? $text;
                return true;
            }
            if (in_array($name, self::RAW_TEXT, true)) {
                if (stripos($text, '</' . $name) !== false) {
                    return false;
                }
                $this->textUpdate = $text;
                return true;
            }
        }
        return false;
    }

    // ---- Bookmarks.

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

    public function releaseBookmark(string $name): bool
    {
        if (!isset($this->bookmarks[$name])) {
            return false;
        }
        unset($this->bookmarks[$name]);
        return true;
    }

    public function hasBookmark(string $name): bool
    {
        return isset($this->bookmarks[$name]);
    }

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

    // ---- Output.

    public function html(): string
    {
        $this->flush();
        return $this->html;
    }

    /** Applies the pending edits to the source and re-reads the current token at its (possibly shifted) place. */
    private function flush(): void
    {
        if ($this->type === null) {
            return;
        }
        $replacements = [];
        if ($this->textUpdate !== null) {
            $replacements[] = [$this->textStart, $this->textStart + $this->textLength, $this->textUpdate];
            $this->textUpdate = null;
        }
        if ($this->type === self::TAG && !$this->closer && ($this->attributeUpdates !== [] || $this->classUpdates !== [])) {
            $inserts = [];
            $updates = $this->attributeUpdates;
            if ($this->classUpdates !== []) {
                $classValue = $this->rebuiltClassValue();
                unset($updates['class']);
                $updates['class'] = ['name' => $this->existingName('class') ?? 'class', 'value' => $classValue === '' ? null : $classValue];
                if ($classValue === '' && $this->existingName('class') === null) {
                    unset($updates['class']);
                }
            }
            foreach ($updates as $lower => $update) {
                $lower = (string) $lower;
                $text = $update['value'] === true ? $update['name'] : $update['name'] . '="' . self::escape((string) $update['value']) . '"';
                $matched = false;
                foreach ($this->attributes as $attr) {
                    if ($attr['lower'] !== $lower) {
                        continue;
                    }
                    if ($update['value'] === null) {
                        $replacements[] = [$attr['start'], $attr['end'], ''];
                        continue;
                    }
                    if (!$matched) {
                        $replacements[] = [$attr['start'], $attr['end'], $text];
                    }
                    $matched = true;
                }
                if (!$matched && $update['value'] !== null) {
                    $inserts[] = ' ' . $text;
                }
            }
            if ($inserts !== []) {
                $replacements[] = [$this->nameEnd, $this->nameEnd, implode('', array_reverse($inserts))];
            }
            $this->attributeUpdates = [];
            $this->classUpdates = [];
        }
        if ($replacements === []) {
            return;
        }
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
        // Re-read the token so attribute offsets match the new source.
        $wasEnded = $this->ended;
        $wasPaused = $this->paused;
        $resume = $this->cursor;
        $this->cursor = $tokenStart;
        $this->ended = false;
        $this->paused = false;
        $this->attributeUpdates = [];
        $this->classUpdates = [];
        $this->textUpdate = null;
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

    private function existingName(string $lower): ?string
    {
        foreach ($this->attributes as $attr) {
            if ($attr['lower'] === $lower) {
                return $attr['name'];
            }
        }
        return null;
    }

    /** The class attribute after edits: source order and inner whitespace kept, removed classes gone with the space before them, additions appended. */
    private function rebuiltClassValue(): string
    {
        $base = $this->baseClassValue() ?? '';
        $keep = [];
        $seen = [];
        preg_match_all('/([ \t\n\r\f]*)([^ \t\n\r\f]+)/', $base, $m, PREG_SET_ORDER);
        $out = '';
        foreach ($m as $part) {
            $class = $part[2];
            if (isset($this->classUpdates[$class]) && $this->classUpdates[$class] === false) {
                continue;
            }
            if (in_array($class, $seen, true)) {
                continue;
            }
            $seen[] = $class;
            $out .= ($out === '' ? '' : $part[1]) . $class;
        }
        foreach ($this->classUpdates as $class => $add) {
            $class = (string) $class;
            if ($add && !in_array($class, $seen, true)) {
                $seen[] = $class;
                $out .= ($out === '' ? '' : ' ') . $class;
            }
        }
        return $out;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', true);
    }
}
