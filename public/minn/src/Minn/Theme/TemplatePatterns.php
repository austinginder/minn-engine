<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Blocks\Attributes;
use Minn\Blocks\Block;
use Minn\Blocks\Parser;

/**
 * A template can name a pattern instead of carrying its blocks, and the
 * reference hands the caller the blocks: every "wp:pattern" delimiter is
 * replaced by the pattern's own markup before a template or part is
 * served. A slug the theme does not know stays exactly as it is, which is
 * what tells an editor the pattern went missing rather than the block.
 */
final readonly class TemplatePatterns
{
    private const TAG = '<!-- wp:pattern';
    private const DEPTH = 5;

    /** Markup with its pattern blocks replaced by the patterns' content. */
    public static function expand(string $markup, Theme $theme, int $depth = 0): string
    {
        if ($depth >= self::DEPTH || !str_contains($markup, self::TAG)) {
            return $markup;
        }
        $out = '';
        $offset = 0;
        while (($start = strpos($markup, self::TAG, $offset)) !== false) {
            $after = $start + strlen(self::TAG);
            $spaces = strspn($markup, " \t\n\r", $after);
            $attributes = Attributes::objectAt($markup, $after + $spaces);
            $end = self::closerAt($markup, $after + $spaces + strlen((string) $attributes));
            $content = $attributes === null ? null : self::pattern($attributes, $theme);
            if ($end === null || $content === null) {
                $out .= substr($markup, $offset, $after - $offset);
                $offset = $after;
                continue;
            }
            $out .= substr($markup, $offset, $start - $offset) . self::expand($content, $theme, $depth + 1);
            $offset = $end;
        }
        return $out . substr($markup, $offset);
    }

    /** The offset past a self-closing delimiter's "/-->", or null when it is not one. */
    private static function closerAt(string $markup, int $at): ?int
    {
        $at += strspn($markup, " \t\n\r", $at);
        return str_starts_with(substr($markup, $at, 4), '/-->') ? $at + 4 : null;
    }

    private static function pattern(string $attributes, Theme $theme): ?string
    {
        $slug = (json_decode($attributes, true) ?: [])['slug'] ?? null;
        if (!is_string($slug) || $slug === '') {
            return null;
        }
        $content = $theme->pattern($slug);
        if ($content === null) {
            return null;
        }
        return self::stamped(trim($content), $theme->patternHeader($slug) ?? ['patternName' => $slug, 'name' => '']);
    }

    /**
     * The pattern's own name rides on the block it opens, so an editor can
     * still see which pattern a stretch of blocks came from. Only a pattern
     * that IS one block gets the stamp: several blocks have no single
     * wrapper to carry the name, and the reference leaves those anonymous.
     * The stamp goes on the pattern's raw markup, before any pattern nested
     * inside it is spliced in, so when that one block is itself a pattern
     * reference the stamp goes away with it.
     *
     * @param array<string, mixed> $header
     */
    private static function stamped(string $markup, array $header): string
    {
        if (!self::isOneBlock($markup)) {
            return $markup;
        }
        if (preg_match('/<!-- wp:[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?/', $markup, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $markup;
        }
        $after = (int) $match[0][1] + strlen((string) $match[0][0]);
        $spaces = strspn($markup, " \t\n\r", $after);
        $attributes = Attributes::objectAt($markup, $after + $spaces);
        $decoded = $attributes === null ? [] : (array) json_decode($attributes, true);
        $decoded['metadata'] = array_merge((array) ($decoded['metadata'] ?? []), $header);
        $encoded = (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return substr($markup, 0, $after) . ' ' . $encoded
            . substr($markup, $after + $spaces + strlen((string) $attributes));
    }

    private static function isOneBlock(string $markup): bool
    {
        $blocks = array_filter(
            Parser::parse($markup),
            static fn (Block $block) => $block->name !== null || trim($block->innerHtml) !== '',
        );
        return count($blocks) === 1;
    }
}
