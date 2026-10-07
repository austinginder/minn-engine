<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\RewriteRules;
use Minn\Runtime\Runtime;

/**
 * Addresses a plugin's change to the rewrite rules decides (suite
 * plugin-rules). WordPress matches a request against its stored rules, the
 * first that fits winning (a page's rule only when a page is at that
 * path). The engine reads addresses itself, which comes to the same while
 * the stored rules are the ones it would make. When a plugin changed them
 * (through a filter or action as they were made, taking rules away or
 * putting them in), the rule the stored list fits first is set against the
 * one the engine's own list would: the same rule, and the engine's reading
 * stands; another, and that rule's vars decide (no rule fitting at all is
 * a 404).
 */
final class StoredRules
{
    /**
     * The vars the stored rules give an address, when they are not what the
     * engine's own reading follows; null when they are.
     *
     * @return array<string, string>|null
     */
    public static function route(string $path): ?array
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        if (!Runtime::booted() || !$rewrite instanceof \WP_Rewrite || !$rewrite->using_permalinks()) {
            return null;
        }
        $subject = self::subject($path);
        if ($subject === '') {
            return null;
        }
        $stored = $rewrite->wp_rewrite_rules();
        $own = RewriteRules::unfiltered($rewrite);
        if (!is_array($stored) || $stored === [] || $stored === $own) {
            return null;
        }
        $fits = self::first($stored, $subject, $rewrite);
        $expected = self::first($own, $subject, $rewrite);
        if ($fits === null) {
            return $expected === null ? null : ['error' => '404'];
        }
        if ($expected !== null && $expected[0] === $fits[0] && $expected[1] === $fits[1]) {
            return null;
        }
        return PluginRules::varsOf($fits[1], $fits[2]);
    }

    /** The address as the rules read it: under the home's own path, without the slashes at its ends. */
    private static function subject(string $path): string
    {
        $subject = trim($path, '/');
        $home = trim((string) parse_url((string) \home_url(), PHP_URL_PATH), '/');
        if ($home !== '' && ($subject === $home || str_starts_with($subject, $home . '/'))) {
            $subject = trim(substr($subject, strlen($home)), '/');
        }
        return $subject;
    }

    /**
     * The first rule an address fits, as typed or decoded; a page's rule
     * only when a page is at the path it captured, under verbose page rules.
     *
     * @param array<mixed, mixed> $rules
     * @return array{0: string, 1: string, 2: array<int|string, string>}|null
     */
    private static function first(array $rules, string $subject, \WP_Rewrite $rewrite): ?array
    {
        $verbosePages = (bool) $rewrite->use_verbose_page_rules;
        $decoded = urldecode($subject);
        foreach ($rules as $regex => $query) {
            if (!is_string($regex) || !is_string($query)) {
                continue;
            }
            $pattern = '#^' . str_replace('#', '\#', $regex) . '#';
            if (@preg_match($pattern, $subject, $matches) !== 1 && @preg_match($pattern, $decoded, $matches) !== 1) {
                continue;
            }
            if ($verbosePages && preg_match('/pagename=\$matches\[([0-9]+)\]/', $query, $var) === 1 && !self::pageAt((string) ($matches[(int) $var[1]] ?? ''))) {
                continue;
            }
            return [$regex, $query, $matches];
        }
        return null;
    }

    /** Whether a page stands at a path (not one in the trash). */
    private static function pageAt(string $path): bool
    {
        $page = \get_page_by_path($path);
        return $page instanceof \WP_Post && !in_array($page->post_status, ['trash', 'auto-draft'], true);
    }
}
