<?php

// Canonical redirect helpers (probe canonical-helpers). redirect_canonical itself is in query.php, where the engine's
// resolution hands it the move it found for the request.

use Minn\Theme\Printed;

/** A URL without its fragment: rebuilt from its parts when it names a host (scheme, host, port, path, query; credentials dropped), returned as given otherwise. */
function strip_fragment_from_url($url)
{
    $parts = wp_parse_url((string) $url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $url;
    }
    $built = (empty($parts['scheme']) ? '' : $parts['scheme'] . ':') . '//' . $parts['host'] . (empty($parts['port']) ? '' : ':' . $parts['port']);
    return $built . ($parts['path'] ?? '') . (empty($parts['query']) ? '' : '?' . $parts['query']);
}

/** A query string without those of the args to check that the URL's own query does not carry (by exact name, with or without a value). */
function _remove_qs_args_if_not_in_url($query_string, array $args_to_check, $url)
{
    $carried = [];
    wp_parse_str((string) wp_parse_url((string) $url, PHP_URL_QUERY), $carried);
    foreach ($args_to_check as $arg) {
        if (!array_key_exists($arg, $carried)) {
            $query_string = remove_query_arg($arg, $query_string);
        }
    }
    return $query_string;
}

/**
 * Where a 404 for a name probably meant to go: the first published post
 * whose name starts with it (or is it, under
 * strict_redirect_guess_404_permalink), among the searchable public types
 * or those of them the request names, on the date the request names. Its
 * comments feed when a feed was asked for, its page when a page past the
 * first was. do_redirect_guess_404_permalink turns guessing off;
 * pre_redirect_guess_404_permalink answers first.
 */
function redirect_guess_404_permalink()
{
    global $wpdb;
    if (apply_filters('do_redirect_guess_404_permalink', true) === false) {
        return false;
    }
    $pre = apply_filters('pre_redirect_guess_404_permalink', null);
    if ($pre !== null) {
        return $pre;
    }
    $name = get_query_var('name');
    $types = $name ? _minn_guess_post_types(get_query_var('post_type')) : '';
    if ($types === '') {
        return false;
    }
    $where = apply_filters('strict_redirect_guess_404_permalink', false) ? $wpdb->prepare('post_name = %s', $name) : $wpdb->prepare('post_name LIKE %s', $wpdb->esc_like($name) . '%');
    $id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE {$where}{$types}" . _minn_query_date_sql('post_date') . " AND post_status IN ('publish')");
    return $id > 0 ? _minn_guessed_link($id) : false;
}

/** @internal the post type clause a guess searches under: the searchable public types, or the ones named among them; '' when none is left */
function _minn_guess_post_types($named): string
{
    global $wpdb;
    $searchable = array_values(get_post_types(['public' => true, 'exclude_from_search' => false]));
    if (is_string($named) && $named !== '') {
        return in_array($named, $searchable, true) ? $wpdb->prepare(' AND post_type = %s', $named) : '';
    }
    $types = is_array($named) && $named !== [] ? array_values(array_intersect($named, $searchable)) : $searchable;
    return $types === [] ? '' : " AND post_type IN ('" . implode("', '", esc_sql($types)) . "')";
}

/** @internal the main query's year, month and day as clauses on a date column, each where it is set */
function _minn_query_date_sql(string $column): string
{
    global $wpdb;
    $sql = '';
    foreach (['year' => 'YEAR', 'monthnum' => 'MONTH', 'day' => 'DAYOFMONTH'] as $var => $part) {
        $value = get_query_var($var);
        $sql .= $value ? $wpdb->prepare(" AND {$part}({$column}) = %d", $value) : '';
    }
    return $sql;
}

/** @internal a guessed post's address: its comments feed when the request asked for a feed, its page when one past the first */
function _minn_guessed_link(int $id): string
{
    $feed = get_query_var('feed');
    if ($feed) {
        return get_post_comments_feed_link($id, $feed);
    }
    $page = get_query_var('page');
    return $page > 1 ? trailingslashit(get_permalink($id)) . user_trailingslashit($page, 'single_paged') : get_permalink($id);
}

/** On a 404 for the admin or login addresses people type (admin, dashboard, wp-admin; login, login.php, wp-login.php), a redirect to the real one. */
function wp_redirect_admin_locations()
{
    if (!is_404()) {
        return;
    }
    $request = untrailingslashit((string) ($_SERVER['REQUEST_URI'] ?? ''));
    $typed = static fn (array $paths) => array_merge(array_map(static fn ($path) => home_url($path, 'relative'), $paths), array_map(static fn ($path) => site_url($path, 'relative'), $paths));
    $location = match (true) {
        in_array($request, $typed(['wp-admin', 'dashboard', 'admin']), true) => admin_url(),
        in_array($request, $typed(['wp-login.php', 'login.php', 'login']), true) => wp_login_url(),
        default => null,
    };
    if ($location !== null && wp_redirect($location)) {
        throw new Printed();
    }
}
