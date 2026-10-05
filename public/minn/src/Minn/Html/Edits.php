<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * The edits pending on the current token: attribute sets and removals,
 * class additions and removals, and a text replacement, in call order.
 * They answer reads before they are written, and become the source
 * replacements the reader splices in when it moves on.
 */
final class Edits
{
    /** @var array<string, array{name: string, value: string|true|null}> pending attribute sets (null = remove), by lowercase name, in call order */
    private array $attributes = [];
    /** @var array<string, bool> pending class additions (true) and removals (false), in call order */
    private array $classes = [];
    private ?string $text = null;

    /** Whether an attribute name may be set at all, by the reference's rules. */
    public static function validName(string $name): bool
    {
        if ($name === '' || preg_match('/[\s"\'>\/=\x00-\x1F\x7F]/', $name) || preg_match('/[\p{Cc}\p{Zs}]/u', $name) && preg_match('/\s/', $name)) {
            return false;
        }
        return !preg_match('/[^\x21-\x7E\x80-\xFF]/', $name);
    }

    /** Records a set; setting class outright forgets the class edits before it. */
    public function setAttribute(string $name, string|true $value): void
    {
        $lower = strtolower($name);
        if ($lower === 'class') {
            $this->classes = [];
        }
        $this->attributes[$lower] = ['name' => $name, 'value' => $value];
    }

    /** Records a removal of an attribute the source has. */
    public function removeAttribute(string $name): void
    {
        $lower = strtolower($name);
        if ($lower === 'class') {
            $this->classes = [];
        }
        unset($this->attributes[$lower]);
        $this->attributes[$lower] = ['name' => $name, 'value' => null];
    }

    /** Forgets a pending set that never reached the source: cancelling an addition is not a removal. */
    public function cancelAttribute(string $name): void
    {
        $lower = strtolower($name);
        if ($lower === 'class') {
            $this->classes = [];
        }
        unset($this->attributes[$lower]);
    }

    /** The pending set or removal for a lowercase name, or null when there is none. @return array{name: string, value: string|true|null}|null */
    public function attribute(string $lower): ?array
    {
        return $this->attributes[$lower] ?? null;
    }

    /** Records a class to add; a later removal of the same class wins. */
    public function addClass(string $class): void
    {
        unset($this->classes[$class]);
        $this->classes[$class] = true;
    }

    /** Records a class to remove; a later addition of the same class wins. */
    public function removeClass(string $class): void
    {
        unset($this->classes[$class]);
        $this->classes[$class] = false;
    }

    /** Whether any class edit is pending. */
    public function hasClassEdits(): bool
    {
        return $this->classes !== [];
    }

    /** Whether any attribute or class edit is pending. */
    public function hasTagEdits(): bool
    {
        return $this->attributes !== [] || $this->classes !== [];
    }

    /**
     * The class list after the pending edits: additions appended once,
     * removals gone.
     *
     * @param list<string> $base
     * @return list<string>
     */
    public function classesAfter(array $base): array
    {
        $list = [];
        foreach ($base as $class) {
            if (!in_array($class, $list, true)) {
                $list[] = $class;
            }
        }
        foreach ($this->classes as $class => $add) {
            $class = (string) $class;
            if ($add && !in_array($class, $list, true)) {
                $list[] = $class;
            } elseif (!$add) {
                $list = array_values(array_filter($list, static fn (string $c) => $c !== $class));
            }
        }
        return $list;
    }

    /**
     * Records a text replacement for a token of a kind that takes one: text
     * (escaped), an ordinary HTML comment, or a raw-text element's body
     * (script and style bodies get their closers escaped). False when the
     * kind takes none, or the text would end the element early.
     *
     * @param ?string $tagName the lowercase tag name of an opening tag, null for anything else
     */
    public function setTextFor(?string $type, ?string $tagName, ?string $commentType, string $text): bool
    {
        if ($type === Tags::TEXT) {
            $this->text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
            return true;
        }
        if ($type === Tags::COMMENT) {
            if ($commentType !== Tags::COMMENT_HTML || str_contains($text, '-->')) {
                return false;
            }
            $this->text = $text;
            return true;
        }
        if ($type === Tags::TAG && $tagName !== null) {
            if ($tagName === 'script' || $tagName === 'style') {
                $this->text = preg_replace_callback('#</(' . $tagName[0] . ')(?=' . substr($tagName, 1) . ')#i', static fn (array $m) => '</\\u00' . dechex(ord($m[1])), $text) ?? $text;
                return true;
            }
            if ($tagName === 'title' || $tagName === 'textarea') {
                // Only the element's own closer would end it early; that is all the text escapes.
                $this->text = preg_replace('#</(' . $tagName . ')#i', '&lt;/$1', $text) ?? $text;
                return true;
            }
            if (in_array($tagName, Tags::RAW_TEXT, true)) {
                if (stripos($text, '</' . $tagName) !== false) {
                    return false;
                }
                $this->text = $text;
                return true;
            }
        }
        return false;
    }

    /** The pending text replacement, taken: it is cleared on read. */
    public function takeText(): ?string
    {
        $text = $this->text;
        $this->text = null;
        return $text;
    }

    /**
     * The source replacements the attribute and class edits amount to, and
     * forgets them: a changed attribute is rewritten in place (its duplicates
     * removed), a removed one is cut, a new one is inserted after the tag name.
     *
     * @param list<array{name: string, lower: string, start: int, end: int, value: ?string}> $attributes the tag's own
     * @return list<array{int, int, string}> start, end, text
     */
    public function takeReplacements(array $attributes, int $nameEnd, ?string $baseClassValue): array
    {
        $replacements = [];
        $inserts = [];
        $updates = $this->attributes;
        if ($this->classes !== []) {
            $classValue = $this->rebuiltClassValue($baseClassValue ?? '');
            $existing = self::existingName($attributes, 'class');
            unset($updates['class']);
            if ($classValue !== '' || $existing !== null) {
                $updates['class'] = ['name' => $existing ?? 'class', 'value' => $classValue === '' ? null : $classValue];
            }
        }
        foreach ($updates as $lower => $update) {
            $lower = (string) $lower;
            $text = $update['value'] === true ? $update['name'] : $update['name'] . '="' . self::escape((string) $update['value']) . '"';
            $matched = false;
            foreach ($attributes as $attr) {
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
            $replacements[] = [$nameEnd, $nameEnd, implode('', array_reverse($inserts))];
        }
        $this->attributes = [];
        $this->classes = [];
        return $replacements;
    }

    /** Forgets everything pending. */
    public function clear(): void
    {
        $this->attributes = [];
        $this->classes = [];
        $this->text = null;
    }

    /** The class attribute after edits: source order and inner whitespace kept, removed classes gone with the space before them, additions appended. */
    private function rebuiltClassValue(string $base): string
    {
        $seen = [];
        preg_match_all('/([ \t\n\r\f]*)([^ \t\n\r\f]+)/', $base, $m, PREG_SET_ORDER);
        $out = '';
        foreach ($m as $part) {
            $class = $part[2];
            if (isset($this->classes[$class]) && $this->classes[$class] === false) {
                continue;
            }
            if (in_array($class, $seen, true)) {
                continue;
            }
            $seen[] = $class;
            $out .= ($out === '' ? '' : $part[1]) . $class;
        }
        foreach ($this->classes as $class => $add) {
            $class = (string) $class;
            if ($add && !in_array($class, $seen, true)) {
                $seen[] = $class;
                $out .= ($out === '' ? '' : ' ') . $class;
            }
        }
        return $out;
    }

    /** @param list<array{name: string, lower: string}> $attributes */
    private static function existingName(array $attributes, string $lower): ?string
    {
        foreach ($attributes as $attr) {
            if ($attr['lower'] === $lower) {
                return $attr['name'];
            }
        }
        return null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8', true);
    }

    /**
     * Splices replacements into the source, ascending, and moves the bookmarks
     * and the cursor by what the token at [$tokenStart, $tokenEnd] gained or lost.
     *
     * @param list<array{int, int, string}> $replacements
     * @param array<string, array{0: int, 1: int}> $bookmarks
     * @return array{0: string, 1: array<string, array{0: int, 1: int}>, 2: int} the source, the bookmarks, the cursor
     */
    public static function splice(string $html, array $replacements, array $bookmarks, int $tokenStart, int $tokenEnd): array
    {
        usort($replacements, static fn (array $a, array $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $out = '';
        $cursor = 0;
        $delta = 0;
        foreach ($replacements as [$start, $end, $text]) {
            $out .= substr($html, $cursor, $start - $cursor) . $text;
            $cursor = $end;
            $delta += strlen($text) - ($end - $start);
        }
        $out .= substr($html, $cursor);
        foreach ($bookmarks as $name => [$bStart, $bEnd]) {
            if ($bStart > $tokenStart) {
                $bookmarks[$name] = [$bStart + $delta, $bEnd + $delta];
            } elseif ($bStart === $tokenStart) {
                $bookmarks[$name] = [$bStart, $tokenEnd + $delta];
            }
        }
        return [$out, $bookmarks, $tokenEnd + $delta];
    }

    /**
     * The class attribute's decoded value before class edits: a pending set, else the source's; null when there is none.
     *
     * @param array{value: string|true}|null $pending
     * @param list<array{lower: string, value: ?string}> $attributes
     */
    public static function classValue(?array $pending, array $attributes): ?string
    {
        if ($pending !== null) {
            return $pending['value'] === true ? '' : $pending['value'];
        }
        foreach ($attributes as $attr) {
            if ($attr['lower'] === 'class') {
                return $attr['value'] === null ? '' : Decoder::attribute($attr['value']);
            }
        }
        return null;
    }

    /**
     * The class names before class edits.
     *
     * @param array{value: string|true}|null $pending
     * @param list<array{lower: string, value: ?string}> $attributes
     * @return list<string>
     */
    public static function classList(?array $pending, array $attributes): array
    {
        $value = self::classValue($pending, $attributes);
        return $value === null || $value === '' ? [] : (preg_split('/[ \t\n\r\f]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
