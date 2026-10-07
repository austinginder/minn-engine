<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\Runtime;

/**
 * Rewrite rules a plugin registered through add_rewrite_rule(): the
 * recorded rules match against the request path the way the reference
 * matches them, ahead of the engine's own resolution for 'top' rules and
 * behind it for 'bottom' ones. A match yields the substituted query vars,
 * kept only when the reference would recognise them (the built-in public
 * vars plus whatever the query_vars filter admits, which is how a plugin
 * registers its own).
 */
final class PluginRules
{
    public const STATE = 'rule_query_vars';

    /** Set when a plugin's own rule matched the request (not merely an endpoint). */
    public const MATCHED = 'rule_matched';

    /** The reference's public query vars a rule's query string may set. */
    private const PUBLIC_VARS = [
        'm', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence',
        'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum',
        'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'pagename',
        'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots',
        'favicon', 'taxonomy', 'term', 'cpage', 'post_type', 'embed',
    ];

    /**
     * A plugin's rewrite rule that matches the path, or null.
     *
     * @return array<string, string>|null the query vars of the first matching rule, null with no match
     */
    public static function match(string $path, bool $top): ?array
    {
        if (!Runtime::booted()) {
            return null;
        }
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $rules = (array) ($top ? ($rewrite->extra_rules_top ?? []) : ($rewrite->extra_rules ?? []));
        if ($rules === []) {
            return null;
        }
        $subject = trim(rawurldecode($path), '/');
        foreach ($rules as $regex => $query) {
            if (!is_string($regex) || !is_string($query)) {
                continue;
            }
            // The reference anchors every rule at the start of the request path.
            if (@preg_match('#^' . str_replace('#', '\#', $regex) . '#', $subject, $matches) !== 1) {
                continue;
            }
            // The first matching rule wins even when its vars all filter away
            // (the reference then runs the home query under that rule).
            return self::varsOf($query, $matches);
        }
        return null;
    }

    /**
     * A matched rule's query vars: its query with the matches put in
     * (urlencoded like the reference's WP_MatchesMapRegex, so a captured
     * '&x=1' cannot split into extra vars), only those the reference would
     * recognise (the public vars and the query_vars filter's).
     *
     * @param array<int|string, string> $matches
     * @return array<string, string>
     */
    public static function varsOf(string $query, array $matches): array
    {
        $query = (string) preg_replace('!^.+\?!', '', $query);
        $query = (string) preg_replace_callback(
            '/\$matches\[(\d+)\]/',
            static fn (array $m): string => urlencode((string) ($matches[(int) $m[1]] ?? '')),
            $query,
        );
        parse_str($query, $vars);
        $allowed = (array) \apply_filters('query_vars', self::PUBLIC_VARS);
        $kept = [];
        foreach ($vars as $name => $value) {
            if (in_array((string) $name, $allowed, true) && is_scalar($value)) {
                $kept[(string) $name] = (string) $value;
            }
        }
        return $kept;
    }

    /**
     * The query vars the matched rule stashed.
     *
     * @return array<string, string> the vars the matched rule stashed for this request
     */
    public static function stashed(): array
    {
        $vars = Runtime::booted() ? Runtime::current()->get(self::STATE) : null;
        return is_array($vars) ? $vars : [];
    }
}
