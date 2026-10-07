<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * The WP_User_Query arguments a user list request makes, as the reference
 * makes them before rest_user_query (probe rest-user-lists): each declared
 * parameter the request carries under its query name (a search wrapped in
 * wildcards), the offset (the page's when none is given), the orderby name
 * mapped to the query's, the published authors (every REST post type, for
 * a reader who may not list users), the authors shorthand, the search
 * columns (a reader who may not list users searches names and logins
 * only), and ids alone for a HEAD request.
 */
final class UserListArgs
{
    private const MAPPINGS = ['exclude' => 'exclude', 'include' => 'include', 'order' => 'order', 'per_page' => 'number', 'search' => 'search', 'roles' => 'role__in', 'capabilities' => 'capability__in', 'slug' => 'nicename__in'];
    private const ORDERBY = ['id' => 'ID', 'include' => 'include', 'name' => 'display_name', 'registered_date' => 'registered', 'slug' => 'user_nicename', 'include_slugs' => 'nicename__in', 'email' => 'user_email', 'url' => 'user_url'];
    private const COLUMNS = ['email' => 'user_email', 'name' => 'display_name', 'id' => 'ID', 'username' => 'user_login', 'slug' => 'user_nicename'];
    private const PUBLIC_COLUMNS = ['ID', 'user_login', 'user_nicename', 'display_name'];

    /**
     * The arguments before plugins see them, for a reader who is a 'lister'
     * (may list users) or 'public'.
     *
     * @param array<string, mixed> $registered the collection, as rest_user_collection_params left it
     * @param array<string, string> $types the REST post types, by name
     * @return array<string, mixed>
     */
    public static function of(\WP_REST_Request $wp, array $registered, string $method, array $types, string $reader): array
    {
        $args = [];
        foreach (self::MAPPINGS as $param => $var) {
            if (isset($registered[$param], $wp[$param])) {
                $args[$var] = $param === 'search' ? '*' . $wp[$param] . '*' : $wp[$param];
            }
        }
        $args['offset'] = isset($wp['offset']) ? $wp['offset'] : (int) ($args['number'] ?? 0) * (abs((int) ($wp['page'])) - 1);
        $args['orderby'] = self::ORDERBY[(string) $wp['orderby']] ?? 'display_name';
        $published = $wp['has_published_posts'];
        if ($published === true || (is_array($published) && $published !== [])) {
            $args['has_published_posts'] = $published === true ? $types : $published;
        } elseif ($reader === 'public') {
            $args['has_published_posts'] = $types;
        }
        if (!empty($wp['who'])) {
            $args['who'] = $wp['who'];
        }
        $columns = array_values(array_filter(array_map(static fn ($column) => self::COLUMNS[(string) $column] ?? null, (array) $wp['search_columns'])));
        if ($reader === 'public' && isset($args['search'])) {
            $columns = array_values(array_intersect(self::PUBLIC_COLUMNS, $columns)) ?: self::PUBLIC_COLUMNS;
        }
        if ($columns !== []) {
            $args['search_columns'] = $columns;
        }
        if ($method === 'HEAD') {
            $args['fields'] = 'id';
        }
        return $args;
    }
}
