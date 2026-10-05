<?php

declare(strict_types=1);

namespace Minn\Html;

/**
 * A map from words to replacements that reads the longest word at a place
 * in a text (as character reference names are read): words shorter than
 * the key length stand alone, the others are grouped by their first bytes,
 * longest first.
 */
final class TokenMap
{
    /** @var array<string, string> */
    private array $small = [];
    /** @var array<string, array<string, string>> */
    private array $groups = [];

    /** @param array<string, string> $mappings */
    public function __construct(array $mappings, private readonly int $keyLength)
    {
        foreach ($mappings as $word => $replacement) {
            $word = (string) $word;
            if (strlen($word) < $keyLength) {
                $this->small[$word] = (string) $replacement;
                continue;
            }
            $this->groups[substr($word, 0, $keyLength)][$word] = (string) $replacement;
        }
        ksort($this->groups);
        foreach ($this->groups as $key => $words) {
            uksort($words, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
            $this->groups[$key] = $words;
        }
    }

    /** How many leading bytes group the words. */
    public function keyLength(): int
    {
        return $this->keyLength;
    }

    /** Whether the word is in the map ("case-sensitive" or "ascii-case-insensitive"). */
    public function contains(string $word, string $caseSensitivity): bool
    {
        foreach ($this->toArray() as $known => $replacement) {
            if (self::same((string) $known, $word, $caseSensitivity)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The replacement for the longest word at $offset, and the word's length; null when none starts there.
     *
     * @return array{0: string, 1: int}|null
     */
    public function read(string $text, int $offset, string $caseSensitivity): ?array
    {
        $key = substr($text, $offset, $this->keyLength);
        foreach ($this->groups as $groupKey => $words) {
            if (!self::same((string) $groupKey, $key, $caseSensitivity)) {
                continue;
            }
            foreach ($words as $word => $replacement) {
                if (self::same($word, substr($text, $offset, strlen($word)), $caseSensitivity)) {
                    return [$replacement, strlen($word)];
                }
            }
        }
        foreach ($this->small as $word => $replacement) {
            if (self::same((string) $word, substr($text, $offset, strlen((string) $word)), $caseSensitivity)) {
                return [$replacement, strlen((string) $word)];
            }
        }
        return null;
    }

    /**
     * Every word and its replacement: the short words first, then each group longest first.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $all = $this->small;
        foreach ($this->groups as $words) {
            $all += $words;
        }
        return $all;
    }

    private static function same(string $a, string $b, string $caseSensitivity): bool
    {
        return $caseSensitivity === 'ascii-case-insensitive' ? strcasecmp($a, $b) === 0 : $a === $b;
    }
}
