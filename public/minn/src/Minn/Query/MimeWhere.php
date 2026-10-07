<?php

declare(strict_types=1);

namespace Minn\Query;

/**
 * wp_post_mime_type_where as the reference writes it (probe wp-query-sql):
 * each mime type (a list or a comma-separated string) cleaned to a pattern,
 * a bare group or a star widened with % into a LIKE, an exact type an
 * equality, all OR'd; an empty type or a lone wildcard means no clause.
 */
final class MimeWhere
{
    private const WILDCARDS = ['', '%', '%/%'];

    /** The clause for some mime types, against a table's column when one is named. @param string|list<string> $types */
    public static function sql(string|array $types, string $alias = ''): string
    {
        if (is_string($types)) {
            $types = array_map('trim', explode(',', $types));
        }
        $column = $alias === '' ? 'post_mime_type' : "{$alias}.post_mime_type";
        $clauses = [];
        foreach ($types as $type) {
            $type = (string) preg_replace('/\s/', '', (string) $type);
            if (in_array($type, self::WILDCARDS, true)) {
                return '';
            }
            $pattern = (string) preg_replace('/\*+/', '%', self::pattern($type));
            $clauses[] = str_contains($pattern, '%') ? "{$column} LIKE '{$pattern}'" : "{$column} = '{$pattern}'";
        }
        return $clauses === [] ? '' : ' AND (' . implode(' OR ', $clauses) . ') ';
    }

    /** A type cleaned to group/subgroup (an empty subgroup a star), or a bare group widened to group/*. */
    private static function pattern(string $type): string
    {
        $slash = strpos($type, '/');
        if ($slash === false) {
            $group = (string) preg_replace('/[^-*.a-zA-Z0-9]/', '', $type);
            return str_contains($group, '*') ? $group : $group . '/*';
        }
        $group = (string) preg_replace('/[^-*.a-zA-Z0-9]/', '', substr($type, 0, $slash));
        $subgroup = (string) preg_replace('/[^-*.+a-zA-Z0-9]/', '', substr($type, $slash + 1));
        return $group . '/' . ($subgroup === '' ? '*' : str_replace('/', '', $subgroup));
    }
}
