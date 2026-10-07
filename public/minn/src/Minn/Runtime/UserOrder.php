<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A user query's ORDER BY keys as the reference writes them (probe
 * wp-user-query-sql): the user columns by their short or full names, the
 * display name, a post count (its subquery joined in), the id, the meta
 * key or meta value, the order include or a name list gives, or a named
 * meta clause; '' for a key it does not know.
 */
final class UserOrder
{
    private const SHORT = ['login', 'nicename', 'email', 'url', 'registered'];
    private const FULL = ['user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered'];

    /** ASC when asked for, DESC for anything else. */
    public static function direction(mixed $order): string
    {
        if (!is_string($order) || $order === '') {
            return 'DESC';
        }
        return strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /** The column or expression one orderby key stands for. */
    public static function clause(\WP_User_Query $query, string $orderby, object $wpdb): string
    {
        $vars = $query->query_vars;
        $clauses = (array) $query->meta_query->get_clauses();
        return match (true) {
            in_array($orderby, self::SHORT, true) => 'user_' . $orderby,
            in_array($orderby, self::FULL, true) => $orderby,
            $orderby === 'name' || $orderby === 'display_name' => 'display_name',
            $orderby === 'post_count' => self::postCount($query, $wpdb),
            $orderby === 'ID' || $orderby === 'id' => 'ID',
            $orderby === 'meta_value' || $query->get('meta_key') === $orderby => "{$wpdb->usermeta}.meta_value",
            $orderby === 'meta_value_num' => "{$wpdb->usermeta}.meta_value+0",
            $orderby === 'include' && !empty($vars['include']) => "FIELD( {$wpdb->users}.ID, " . implode(',', \wp_parse_id_list($vars['include'])) . ' )',
            $orderby === 'nicename__in' => "FIELD( user_nicename, '" . implode("','", array_map('esc_sql', (array) $vars['nicename__in'])) . "' )",
            $orderby === 'login__in' => "FIELD( user_login, '" . implode("','", array_map('esc_sql', (array) $vars['login__in'])) . "' )",
            isset($clauses[$orderby]) => sprintf('CAST(%s.meta_value AS %s)', \esc_sql($clauses[$orderby]['alias']), \esc_sql($clauses[$orderby]['cast'])),
            default => '',
        };
    }

    /** Joins each author's published post count in, and orders by it. */
    private static function postCount(\WP_User_Query $query, object $wpdb): string
    {
        $where = \get_posts_by_author_sql('post');
        $query->query_from .= " LEFT OUTER JOIN (\n\t\t\t\tSELECT post_author, COUNT(*) as post_count\n\t\t\t\tFROM {$wpdb->posts}\n\t\t\t\t{$where}\n\t\t\t\tGROUP BY post_author\n\t\t\t) p ON ({$wpdb->users}.ID = p.post_author)";
        return 'post_count';
    }
}
