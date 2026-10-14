<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Objects\Post;
use Minn\Objects\Rewrite;
use Minn\Runtime\PostReads;
use Minn\Runtime\SiteUrls;

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
        $match = self::match($path);
        return $match === null || $match['rule'] === '' ? null : $match['vars'];
    }

    /**
     * What the request parse makes of a path (WP::parse_request): the path
     * under the home's, the rule it fits and that rule's query with the
     * matches put in, and its vars; the rule and query empty when none
     * fits. Null when the site has no rules (plain permalinks).
     *
     * @return array{request: string, rule: string, query: string, vars: array<string, string>}|null
     */
    public static function match(string $path): ?array
    {
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $rules = $rewrite instanceof Rewrite ? $rewrite->wp_rewrite_rules() : null;
        if (!is_array($rules) || $rules === []) {
            return null;
        }
        $subject = self::subject($path);
        $matched = self::first($rules, $subject, (bool) $rewrite->use_verbose_page_rules, self::pageAt(...));
        if ($matched === null) {
            return ['request' => $subject, 'rule' => '', 'query' => '', 'vars' => []];
        }
        $query = self::queryOf($matched[1], $matched[2]);
        parse_str($query, $vars);
        return ['request' => $subject, 'rule' => $matched[0], 'query' => $query, 'vars' => array_map('strval', array_filter($vars, 'is_scalar'))];
    }

    /** The address as the rules read it: under the home's own path, without the slashes at its ends. */
    private static function subject(string $path): string
    {
        $subject = trim($path, '/');
        $home = trim((string) parse_url((string) SiteUrls::home(), PHP_URL_PATH), '/');
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
     * A matched rule's query with the matches put in (urlencoded, so a
     * captured "&x=1" cannot split into extra vars).
     *
     * @param array<int|string, string> $matches
     */
    private static function queryOf(string $query, array $matches): string
    {
        $query = (string) preg_replace('!^.+\?!', '', $query);
        return (string) preg_replace_callback(
            '/\$matches\[(\d+)\]/',
            static fn (array $m): string => urlencode((string) ($matches[(int) $m[1]] ?? '')),
            $query,
        );
    }

    /** Whether a page stands at a path (not one in the trash). */
    private static function pageAt(string $path): bool
    {
        $page = PostReads::pageByPath($path);
        return $page instanceof Post && !in_array($page->post_status, ['trash', 'auto-draft'], true);
    }
}
