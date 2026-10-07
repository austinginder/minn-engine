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
 * own var brings post_type and name; a path no rule matches is error=404.
 */
final class RequestParse
{
    /**
     * The parse's vars, in the reference's order.
     *
     * @param array<string, string> $rule the matched rule's vars
     * @param list<string> $publicVars the public query vars, after the query_vars filter
     * @param array<string, mixed> $given the query string's and the form's values
     * @return array<string, string>
     */
    public static function vars(array $rule, array $publicVars, array $given): array
    {
        $vars = [];
        foreach ($publicVars as $var) {
            $value = $given[$var] ?? $rule[$var] ?? null;
            if ($value !== null && is_scalar($value)) {
                $vars[$var] = (string) $value;
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
