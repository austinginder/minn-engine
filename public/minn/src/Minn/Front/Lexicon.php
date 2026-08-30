<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The WordPress Speak / Hear / Mute glossary, parsed from
 * contracts/lexicon.md. The markdown is the source; this object is what
 * the interactive page renders.
 */
final readonly class Lexicon
{
    /**
     * @param list<LexiconBlock> $blocks
     */
    public function __construct(
        public string $title,
        public string $markdown,
        public array $blocks,
    ) {
    }

    /** Site-root contracts file, then a copy shipped inside minn/data. */
    public static function locate(string $engineDir): ?string
    {
        foreach ([
            dirname($engineDir, 2) . '/contracts/lexicon.md',
            $engineDir . '/data/lexicon.md',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function fromFile(string $path): self
    {
        return self::fromMarkdown((string) file_get_contents($path));
    }

    public static function fromMarkdown(string $markdown): self
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $lines = explode("\n", $markdown);
        $blocks = [];
        $title = 'Lexicon';
        $status = null;
        $n = count($lines);
        $i = 0;
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            if (preg_match('/^---+$/', trim($line)) === 1) {
                $blocks[] = new LexiconBlock(LexiconKind::Rule);
                $i++;
                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $m) === 1) {
                $level = strlen($m[1]);
                $text = trim($m[2]);
                if ($level === 1) {
                    $title = $text;
                    $i++;
                    continue;
                }
                if ($level === 2) {
                    $plain = strtolower($text);
                    $status = in_array($plain, ['speak', 'hear', 'mute'], true) ? $plain : null;
                }
                $blocks[] = new LexiconBlock(
                    LexiconKind::Heading,
                    level: $level,
                    text: $text,
                    id: self::slug($text),
                    status: $status,
                );
                $i++;
                continue;
            }
            if (str_starts_with(ltrim($line), '|')) {
                [$block, $i] = self::readTable($lines, $i, $status);
                $blocks[] = $block;
                continue;
            }
            if (preg_match('/^(\d+\.|-)\s+/', $line) === 1) {
                [$block, $i] = self::readList($lines, $i);
                $blocks[] = $block;
                continue;
            }
            [$block, $i] = self::readParagraph($lines, $i);
            $blocks[] = $block;
        }
        return new self($title, $markdown, $blocks);
    }

    public function familyCount(): int
    {
        $n = 0;
        foreach ($this->blocks as $block) {
            if ($block->filterable && $block->status !== null) {
                $n += count($block->rows);
            }
        }
        return $n;
    }

    public static function slug(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * @param list<string> $lines
     * @return array{0: LexiconBlock, 1: int}
     */
    private static function readTable(array $lines, int $i, ?string $status): array
    {
        $rows = [];
        $n = count($lines);
        while ($i < $n && str_starts_with(ltrim($lines[$i]), '|')) {
            $cells = self::cells($lines[$i]);
            $i++;
            if ($cells === [] || self::isSeparator($cells)) {
                continue;
            }
            $rows[] = $cells;
        }
        $headers = $rows[0] ?? [];
        $body = array_slice($rows, 1);
        // Only Speak / Hear / Mute family tables filter. Intro tables name
        // those words in cells and must stay visible.
        return [new LexiconBlock(
            LexiconKind::Table,
            status: $status,
            headers: $headers,
            rows: $body,
            filterable: $status !== null,
        ), $i];
    }

    /**
     * @param list<string> $lines
     * @return array{0: LexiconBlock, 1: int}
     */
    private static function readList(array $lines, int $i): array
    {
        $items = [];
        $ordered = preg_match('/^\d+\.\s+/', $lines[$i]) === 1;
        $n = count($lines);
        while ($i < $n) {
            $line = $lines[$i];
            if (preg_match('/^(\d+\.|-)\s+(.*)$/', $line, $m) === 1) {
                $items[] = $m[2];
                $i++;
                continue;
            }
            if ($items !== [] && preg_match('/^\s{2,}\S/', $line) === 1) {
                $items[array_key_last($items)] .= ' ' . trim($line);
                $i++;
                continue;
            }
            break;
        }
        return [new LexiconBlock(LexiconKind::List, items: $items, ordered: $ordered), $i];
    }

    /**
     * @param list<string> $lines
     * @return array{0: LexiconBlock, 1: int}
     */
    private static function readParagraph(array $lines, int $i): array
    {
        $parts = [rtrim($lines[$i])];
        $i++;
        $n = count($lines);
        while ($i < $n && !self::startsBlock($lines[$i])) {
            $parts[] = trim($lines[$i]);
            $i++;
        }
        return [new LexiconBlock(LexiconKind::Paragraph, text: implode(' ', $parts)), $i];
    }

    private static function startsBlock(string $line): bool
    {
        $trim = trim($line);
        return $trim === ''
            || preg_match('/^---+$/', $trim) === 1
            || preg_match('/^#{1,3}\s+/', $line) === 1
            || str_starts_with(ltrim($line), '|')
            || preg_match('/^(\d+\.|-)\s+/', $line) === 1;
    }

    /** @return list<string> */
    private static function cells(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        $parts = explode('|', $line);
        return array_map(static fn (string $c) => trim($c), $parts);
    }

    /** @param list<string> $cells */
    private static function isSeparator(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^:?-{3,}:?$/', $cell) !== 1) {
                return false;
            }
        }
        return $cells !== [];
    }

    public static function statusFromCell(string $cell): ?string
    {
        if (preg_match('/\b(speak|hear|mute)\b/i', $cell, $m) === 1) {
            return strtolower($m[1]);
        }
        return null;
    }
}
