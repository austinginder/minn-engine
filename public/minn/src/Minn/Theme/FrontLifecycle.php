<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Http\Response;

/**
 * WordPress's front-end request steps around the main query, as WP::main
 * runs them (front lifecycle trace): parse the request (do_parse_request
 * may take it over; query_vars names the public variables, a plugin's own
 * read from the query string; request filters the result; parse_request
 * follows), the main query, the 404 decision (pre_handle_404 first), the
 * globals, and the headers (wp_headers, the status, send_headers). The
 * engine has already resolved the URL; these steps tell plugins about it
 * in the reference's order and let them change what the query asks.
 */
final class FrontLifecycle
{
    /**
     * Parses the request: false when a plugin's do_parse_request took the
     * parse over (the main query then does not run).
     *
     * @param array<string, mixed> $vars the variables the engine resolved
     * @param array<string, mixed> $given the query string and form, where a plugin's own variables are read
     */
    public static function parseRequest(\WP $wp, array $vars, array $given): bool
    {
        if (!\apply_filters('do_parse_request', true, $wp, $wp->extra_query_vars)) {
            return false;
        }
        $known = (array) $wp->public_query_vars;
        $wp->public_query_vars = (array) \apply_filters('query_vars', $known);
        foreach (\get_post_types([], 'objects') as $type) {
            // The reference asks each type whether it is viewable as it maps type query vars.
            \is_post_type_viewable($type);
        }
        foreach (array_diff($wp->public_query_vars, $known) as $var) {
            if (!isset($vars[$var]) && isset($given[$var]) && $given[$var] !== '') {
                $vars[$var] = is_array($given[$var]) ? $given[$var] : (string) $given[$var];
            }
        }
        $wp->query_vars = (array) \apply_filters('request', $vars);
        \do_action_ref_array('parse_request', [&$wp]);
        return true;
    }

    /**
     * The 404 decision: a plugin's pre_handle_404 may make it; otherwise a
     * request the engine could not resolve is a 404 (the query says so, the
     * status and no-cache headers follow), anything else is a 200.
     */
    public static function handle404(\WP_Query $query, bool $notFound): void
    {
        if (\apply_filters('pre_handle_404', false, $query) !== false || $query->is_404()) {
            return;
        }
        if ($notFound) {
            $query->set_404();
            \status_header(404);
            \nocache_headers();
            return;
        }
        \status_header(200);
    }

    /**
     * The headers the reference sends for the request: no caching for a
     * signed-in reader or a 404, the content type, a pingback address for a
     * single post that takes pings; then wp_headers, the status an error
     * variable names, and send_headers.
     */
    public static function sendHeaders(\WP $wp): void
    {
        $headers = \is_user_logged_in() ? \wp_get_nocache_headers() : [];
        $status = !empty($wp->query_vars['error']) ? (int) $wp->query_vars['error'] : null;
        if ($status === 404 && !\is_user_logged_in()) {
            $headers = array_merge($headers, \wp_get_nocache_headers());
        }
        if ($status === null || $status === 404) {
            $headers['Content-Type'] = \get_option('html_type') . '; charset=' . \get_option('blog_charset');
        }
        if (\is_singular()) {
            $post = \get_queried_object();
            if ($post instanceof \WP_Post && \pings_open($post)) {
                $headers['X-Pingback'] = \get_bloginfo('pingback_url', 'display');
            }
        }
        $headers = (array) \apply_filters('wp_headers', $headers, $wp);
        if ($status !== null) {
            \status_header($status);
        }
        if (isset($headers['Last-Modified']) && $headers['Last-Modified'] === false) {
            unset($headers['Last-Modified']);
        }
        foreach ($headers as $name => $value) {
            Response::emitHeader("{$name}: {$value}");
        }
        \do_action_ref_array('send_headers', [&$wp]);
    }
}
