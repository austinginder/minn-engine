<?php

declare(strict_types=1);

namespace Minn\Theme;


use Minn\Http\Response;
use Minn\Runtime\Runtime;

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
     * @param (\Closure(list<string>): array<string, mixed>)|null $parse the reference's parse vars for the public vars, when the request is known
     */
    public static function parseRequest(\WP $wp, array $vars, array $given, ?\Closure $parse = null): bool
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
        foreach ($parse === null ? array_diff($wp->public_query_vars, $known) : [] as $var) {
            if (!isset($vars[$var]) && isset($given[$var]) && $given[$var] !== '') {
                $vars[$var] = is_array($given[$var]) ? $given[$var] : (string) $given[$var];
            }
        }
        $wp->query_vars = (array) \apply_filters('request', $parse === null ? $vars : $parse($wp->public_query_vars));
        \do_action_ref_array('parse_request', [&$wp]);
        $wp->build_query_string();
        return true;
    }

    /**
     * The 404 decision, the one place it is made: a plugin's
     * pre_handle_404 may make it; otherwise a request for something the
     * site does not have is a 404, as is an empty page past the first of
     * any listing and an empty date archive that names nothing else (an
     * existing term, author or type with no posts, a date inside one of
     * those or inside a search, an empty search, the front: a 200); the
     * status and no-cache headers follow. A feed is never a 404: one of
     * nothing is an empty feed.
     */
    public static function handle404(\WP_Query $query, bool $notFound): void
    {
        if (\apply_filters('pre_handle_404', false, $query) !== false || $query->is_404()) {
            return;
        }
        $empty = empty($query->posts) && !$query->is_robots() && !$query->is_favicon();
        $bareDate = $query->is_date() && !$query->is_search() && $query->get_queried_object() === null;
        if (!$query->is_feed() && ($notFound || ($empty && ($query->is_paged() || $bareDate)))) {
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
        $fresh = false;
        if ($status === 404 && !\is_user_logged_in()) {
            $headers = array_merge($headers, \wp_get_nocache_headers());
        }
        if ($status === 404 || ($status === null && empty($wp->query_vars['feed']))) {
            $headers['Content-Type'] = Runtime::options()->filtered('html_type') . '; charset=' . Runtime::options()->filtered('blog_charset');
        } elseif ($status === null) {
            [$feed, $fresh] = FeedHeaders::for($wp->query_vars, Runtime::current()->request);
            $headers = array_merge($headers, $feed);
            $status = $fresh ? 304 : null;
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
        // A reader whose copy of the feed is current is told so, and nothing more.
        if ($fresh) {
            throw new NotModified();
        }
        \do_action_ref_array('send_headers', [&$wp]);
    }
}
