<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;

/**
 * An address read as WordPress's request parse reads it (suites
 * permalinks, request-vars, plugin-rules): against the site's rewrite
 * rules (made and stored when there are none, a plugin's own among them,
 * each place's endpoints too), the first rule the address fits winning, as
 * typed or decoded; a page's rule fits only when a page stands at the path
 * it captured, under verbose page rules. A match is the rule's query vars
 * with the matches put in. Which of them count is the request parse's
 * business (the public vars, after the query_vars filter).
 */
final class RuleTable
{
    /**
     * The vars the site's rules give a path, or null when no rule fits it
     * (or the site has none: plain permalinks).
     *
     * @return array<string, string>|null
     */
    public static function vars(string $path): ?array
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        if (!$rewrite instanceof \WP_Rewrite) {
            return null;
        }
        $rules = $rewrite->wp_rewrite_rules();
        if (!is_array($rules) || $rules === []) {
            return null;
        }
        $matched = self::first($rules, self::subject($path), (bool) $rewrite->use_verbose_page_rules, self::pageAt(...));
        return $matched === null ? null : self::varsOf($matched[1], $matched[2]);
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
     * @param array<mixed, mixed> $rules regex => query
     * @param Closure(string): bool $pageAt whether a page stands at a path
     * @return array{0: string, 1: string, 2: array<int|string, string>}|null
     */
    private static function first(array $rules, string $subject, bool $verbosePages, Closure $pageAt): ?array
    {
        $decoded = urldecode($subject);
        foreach ($rules as $regex => $query) {
            if (!is_string($regex) || !is_string($query)) {
                continue;
            }
            $pattern = '#^' . str_replace('#', '\#', $regex) . '#';
            if (@preg_match($pattern, $subject, $matches) !== 1 && @preg_match($pattern, $decoded, $matches) !== 1) {
                continue;
            }
            if ($verbosePages && preg_match('/pagename=\$matches\[([0-9]+)\]/', $query, $var) === 1 && !$pageAt((string) ($matches[(int) $var[1]] ?? ''))) {
                continue;
            }
            return [$regex, $query, $matches];
        }
        return null;
    }

    /**
     * A matched rule's query vars: its query with the matches put in
     * (urlencoded, so a captured "&x=1" cannot split into extra vars).
     *
     * @param array<int|string, string> $matches
     * @return array<string, string>
     */
    private static function varsOf(string $query, array $matches): array
    {
        $query = (string) preg_replace('!^.+\?!', '', $query);
        $query = (string) preg_replace_callback(
            '/\$matches\[(\d+)\]/',
            static fn (array $m): string => urlencode((string) ($matches[(int) $m[1]] ?? '')),
            $query,
        );
        parse_str($query, $vars);
        return array_map('strval', array_filter($vars, 'is_scalar'));
    }

    /** Whether a page stands at a path (not one in the trash). */
    private static function pageAt(string $path): bool
    {
        $page = \get_page_by_path($path);
        return $page instanceof \WP_Post && !in_array($page->post_status, ['trash', 'auto-draft'], true);
    }
}
