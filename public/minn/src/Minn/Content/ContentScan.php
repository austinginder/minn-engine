<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Blocks\Parser;

/**
 * What a site's stored content asks of the engine: shortcodes, block
 * names, extra tables, extra post types. Preflight lights the findings;
 * the scan itself is pure string and list work so a suite can pin it
 * without a database.
 */
final class ContentScan
{
    /** Tables the engine already speaks. Anything else is plugin data. */
    public const CORE_TABLES = [
        'commentmeta',
        'comments',
        'links',
        'options',
        'postmeta',
        'posts',
        'term_relationships',
        'term_taxonomy',
        'termmeta',
        'terms',
        'usermeta',
        'users',
    ];

    /**
     * Built-in types the engine stores. Public ones are in data/types.json;
     * the rest are silent core types that still occupy the posts table.
     */
    public const CORE_TYPES = [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
        'wp_font_family',
        'wp_font_face',
    ];

    /** post_content that a visitor (or a theme template) can actually see. */
    public const CONTENT_TYPES = [
        'post',
        'page',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_navigation',
    ];

    /**
     * Opening shortcode tags in $content. Escaped [[tag]] is skipped;
     * closers [/tag] never match because a tag name starts with a letter.
     *
     * @return array<string, int> tag => occurrences
     */
    public static function shortcodes(string $content): array
    {
        if (!str_contains($content, '[')) {
            return [];
        }
        $found = [];
        if (preg_match_all('/\[(\[?)([a-zA-Z][\w-]*)(?![\w-])/', $content, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                if ($match[1] === '[') {
                    continue;
                }
                $found[$match[2]] = ($found[$match[2]] ?? 0) + 1;
            }
        }
        ksort($found);
        return $found;
    }

    /**
     * Block names in $content, namespace included. A delimiter without a
     * namespace is core/, matching the parser.
     *
     * @return array<string, int> name => occurrences
     */
    public static function blocks(string $content): array
    {
        if (!str_contains($content, '<!-- wp:')) {
            return [];
        }
        $found = [];
        self::walk(Parser::parse($content), $found);
        ksort($found);
        return $found;
    }

    /**
     * @param list<string> $tables names from SHOW TABLES
     * @return list<string> bare names after stripping the prefix
     */
    public static function extraTables(array $tables, string $prefix): array
    {
        $out = [];
        foreach ($tables as $table) {
            if (!str_starts_with($table, $prefix)) {
                continue;
            }
            $bare = substr($table, strlen($prefix));
            if (!in_array($bare, self::CORE_TABLES, true)) {
                $out[] = $bare;
            }
        }
        sort($out);
        return $out;
    }

    /**
     * Group extra tables by the first underscore segment. Wordfence's
     * tables are wfHits and wfls_* with no shared underscore prefix, so
     * those two stems collapse to one family.
     *
     * @param list<string> $bare
     * @return list<string>
     */
    public static function tableFamilies(array $bare): array
    {
        $families = [];
        foreach ($bare as $name) {
            if (preg_match('/^(wf|wfls)/', $name) === 1) {
                $families['wordfence'] = true;
            } elseif (preg_match('/^([a-z]+[0-9]*)_/i', $name, $match) === 1) {
                $families[strtolower($match[1])] = true;
            } else {
                $families[$name] = true;
            }
        }
        $names = array_keys($families);
        sort($names);
        return $names;
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    public static function extraTypes(array $types): array
    {
        $out = array_values(array_filter(
            $types,
            static fn (string $type) => !in_array($type, self::CORE_TYPES, true),
        ));
        sort($out);
        return $out;
    }

    /**
     * @param array<string, int> $named
     * @return array<string, int>
     */
    public static function thirdParty(array $named): array
    {
        return array_filter(
            $named,
            static fn (string $name) => !str_starts_with($name, 'core/'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @param list<string> $names */
    public static function listed(array $names, int $cap = 40): string
    {
        sort($names);
        if (count($names) <= $cap) {
            return implode(', ', $names);
        }
        return implode(', ', array_slice($names, 0, $cap)) . ' and ' . (count($names) - $cap) . ' more';
    }

    /** @param list<\Minn\Blocks\Block> $blocks @param array<string, int> $found */
    private static function walk(array $blocks, array &$found): void
    {
        foreach ($blocks as $block) {
            if ($block->name === null) {
                continue;
            }
            $found[$block->name] = ($found[$block->name] ?? 0) + 1;
            self::walk($block->innerBlocks, $found);
        }
    }
}
