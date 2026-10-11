<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Runtime\Runtime;

/**
 * The query vars the reference's request parse sets for an address
 * (WP::parse_request), which plugins read off $wp->query_vars and
 * get_query_var: each public var the query string or form carries, else
 * the one the matched rewrite rule set (Front\RuleTable), all in the
 * public vars' order (a plugin's own after the core ones); a post type's
 * own var brings post_type and name; a path no rule matches is error=404
 * (the query string's error is ignored).
 */
final class RequestParse
{
    /**
     * The parse's vars, in the reference's order.
     *
     * @param array<string, string> $rule the matched rule's vars
     * @param list<string> $publicVars the public query vars, after the query_vars filter
     * @param array<string, mixed> $given the query string's and the form's values
     * @return array<string, string|array<mixed>>
     */
    public static function vars(array $rule, array $publicVars, array $given): array
    {
        $vars = [];
        $queryable = array_keys(array_filter(Runtime::registry()->postTypes(), static fn (array $type): bool => !empty($type['publicly_queryable'])));
        foreach ($publicVars as $var) {
            // error is the parse's own verdict (no rule matched), never the query string's.
            $value = ($var === 'error' ? null : $given[$var] ?? null) ?? $rule[$var] ?? null;
            // A list stays a list of strings (cat[]=1&cat[]=2), as the reference keeps it.
            $value = is_array($value) ? array_map(static fn ($item) => is_scalar($item) ? (string) $item : $item, $value) : (is_scalar($value) ? (string) $value : null);
            // The post type asked for counts only when it is publicly queryable (not page, not an unknown one).
            if ($var === 'post_type' && $value !== null) {
                $value = is_array($value) ? array_values(array_intersect($value, $queryable)) : (in_array($value, $queryable, true) ? $value : null);
            }
            if ($value !== null) {
                $vars[$var] = $value;
            }
        }
        foreach (Runtime::registry()->postTypes() as $name => $type) {
            $var = $type['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !in_array($name, ['post', 'page', 'attachment'], true) && isset($vars[$var])) {
                $vars['post_type'] = (string) $name;
                $vars['name'] = $vars[$var];
            }
        }
        return isset($rule['error']) ? $vars + ['error' => '404'] : $vars;
    }

    /**
     * Whether the query string and the form give a public var two different
     * values: the reference refuses such a request (A variable mismatch has
     * been detected, a 400) before it reads either.
     *
     * @param list<string> $publicVars
     * @param array<string, mixed> $query
     * @param array<string, mixed> $form
     */
    public static function mismatch(array $publicVars, array $query, array $form): bool
    {
        foreach ($publicVars as $var) {
            if (isset($query[$var], $form[$var]) && $query[$var] !== $form[$var]) {
                return true;
            }
        }
        return false;
    }

    /** The reference's query string for parse vars (WP::build_query_string): each one with a value, encoded. */
    public static function queryString(array $vars): string
    {
        $pairs = [];
        foreach ($vars as $var => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $pairs[] = $var . '=' . rawurlencode((string) $value);
            }
        }
        return implode('&', $pairs);
    }
}
