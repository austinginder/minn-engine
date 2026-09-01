<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Edits one start tag's attributes in place the way the reference's tag
 * processor does: a replaced value keeps its position, a new attribute goes
 * right after the tag name (after any earlier insertion), and a removal takes
 * only the attribute's own text.
 */
final class TagEditor
{
    /** @var list<array{name: string, value: ?string, start: int, end: int}> */
    private array $attrs;
    private string $tag;
    private int $insertAt;

    /** @param list<array{name: string, value: ?string, start: int, end: int}> $attrs offsets relative to the tag */
    public function __construct(string $tag, array $attrs)
    {
        $this->tag = $tag;
        preg_match('/^<[a-zA-Z][^\s\/>]*/', $tag, $m);
        $this->insertAt = strlen($m[0]);
        $this->attrs = $attrs;
    }

    /** An attribute's value, or null. */
    public function get(string $name): ?string
    {
        foreach ($this->attrs as $a) {
            if (strcasecmp($a['name'], $name) === 0) {
                return $a['value'] ?? '';
            }
        }
        return null;
    }

    /** Whether the tag has an attribute. */
    public function has(string $name): bool
    {
        foreach ($this->attrs as $a) {
            if (strcasecmp($a['name'], $name) === 0) {
                return true;
            }
        }
        return false;
    }

    /** true sets a bare boolean attribute. */
    public function set(string $name, string|bool $value): void
    {
        $text = $value === true ? $name : $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '"';
        foreach ($this->attrs as $i => $a) {
            if (strcasecmp($a['name'], $name) === 0) {
                $this->splice($a['start'], $a['end'], $text);
                $this->attrs[$i]['value'] = $value === true ? null : (string) $value;
                return;
            }
        }
        $this->splice($this->insertAt, $this->insertAt, ' ' . $text);
        $this->attrs[] = ['name' => $name, 'value' => $value === true ? null : (string) $value, 'start' => $this->insertAt + 1, 'end' => $this->insertAt + 1 + strlen($text)];
        $this->insertAt += 1 + strlen($text);
    }

    /** Removes an attribute. */
    public function remove(string $name): void
    {
        foreach ($this->attrs as $i => $a) {
            if (strcasecmp($a['name'], $name) === 0) {
                $this->splice($a['start'], $a['end'], '');
                unset($this->attrs[$i]);
                $this->attrs = array_values($this->attrs);
                return;
            }
        }
    }

    /** Adds or removes a class. */
    public function toggleClass(string $class, bool $on): void
    {
        $current = $this->get('class');
        $classes = $current === null ? [] : preg_split('/\s+/', trim($current), -1, PREG_SPLIT_NO_EMPTY);
        $present = in_array($class, $classes, true);
        if ($on && !$present) {
            $classes[] = $class;
        } elseif (!$on && $present) {
            $classes = array_values(array_filter($classes, static fn (string $c) => $c !== $class));
        } else {
            return;
        }
        if ($classes === []) {
            $this->remove('class');
        } else {
            $this->set('class', implode(' ', $classes));
        }
    }

    /** Sets or removes one inline style property. */
    public function setStyle(string $property, ?string $value): void
    {
        $current = $this->get('style');
        $declarations = [];
        foreach ($current === null ? [] : explode(';', $current) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }
            [$prop, $val] = explode(':', $declaration, 2);
            if (trim($prop) === $property) {
                continue;
            }
            $declarations[] = trim($prop) . ':' . trim($val) . ';';
        }
        if ($value !== null && $value !== '') {
            $declarations[] = $property . ':' . $value . ';';
        }
        if ($declarations === []) {
            $this->remove('style');
        } else {
            $this->set('style', implode('', $declarations));
        }
    }

    /** The tag as edited. */
    public function html(): string
    {
        return $this->tag;
    }

    private function splice(int $start, int $end, string $text): void
    {
        $this->tag = substr($this->tag, 0, $start) . $text . substr($this->tag, $end);
        $delta = strlen($text) - ($end - $start);
        foreach ($this->attrs as &$a) {
            if ($a['start'] >= $end) {
                $a['start'] += $delta;
                $a['end'] += $delta;
            }
        }
        if ($this->insertAt >= $end && $end !== $start) {
            $this->insertAt += $delta;
        }
    }
}
