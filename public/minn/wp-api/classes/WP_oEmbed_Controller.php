<?php

/**
 * oembed/1.0 (probes oembed, rest-embed-details), registered on
 * rest_api_init by wp_oembed_register_route. embed is the provider side,
 * open to anyone: one of the site's own addresses as embed data (the post
 * through url_to_postid and oembed_request_post_id, its data as a plain
 * array), "Not Found" for anything else; format=xml is served as XML on
 * the way out. proxy is the editor's consumer side, for anyone who can
 * edit posts: the site's own addresses answered locally, anything else
 * fetched through WP_oEmbed with the editor's size, its markup through
 * oembed_result, and kept in a transient for a day (rest_oembed_ttl)
 * under the request's arguments. What no provider takes goes to the
 * embed handlers, uncached, with the scripts the handler queued.
 */
#[AllowDynamicProperties]
final class WP_oEmbed_Controller
{
    public function register_routes()
    {
        $width = apply_filters('oembed_default_width', 600);
        $url = ['description' => __('The URL of the resource for which to fetch oEmbed data.'), 'required' => true, 'type' => 'string', 'format' => 'uri'];
        register_rest_route('oembed/1.0', '/embed', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => '__return_true',
                'args' => ['url' => $url, 'format' => ['default' => 'json', 'sanitize_callback' => 'wp_oembed_ensure_format'], 'maxwidth' => ['default' => $width, 'sanitize_callback' => 'absint']],
            ],
        ]);
        register_rest_route('oembed/1.0', '/proxy', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_proxy_item'],
                'permission_callback' => [$this, 'get_proxy_item_permissions_check'],
                'args' => [
                    'url' => $url,
                    'format' => ['description' => __('The oEmbed format to use.'), 'type' => 'string', 'default' => 'json', 'enum' => ['json', 'xml']],
                    'maxwidth' => ['description' => __('The maximum width of the embed frame in pixels.'), 'type' => 'integer', 'default' => $width, 'sanitize_callback' => 'absint'],
                    'maxheight' => ['description' => __('The maximum height of the embed frame in pixels.'), 'type' => 'integer', 'sanitize_callback' => 'absint'],
                    'discover' => ['description' => __('Whether to perform an oEmbed discovery request for unsanctioned providers.'), 'type' => 'boolean', 'default' => true],
                ],
            ],
        ]);
    }

    public function get_item($request)
    {
        $post_id = (int) apply_filters('oembed_request_post_id', url_to_postid((string) $request['url']), $request['url']);
        $data = get_oembed_response_data($post_id, (int) $request['maxwidth']);
        return $data ?: new WP_Error('oembed_invalid_url', get_status_header_desc(404), ['status' => 404]);
    }

    public function get_proxy_item_permissions_check()
    {
        return current_user_can('edit_posts') ? true : new WP_Error('rest_forbidden', __('Sorry, you are not allowed to make proxied oEmbed requests.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_proxy_item($request)
    {
        $args = $request->get_params();
        unset($args['_wpnonce'], $args['rest_route']);
        $url = (string) $request['url'];
        $local = get_oembed_response_data_for_url($url, ['width' => (int) $request['maxwidth']] + $args);
        if ($local) {
            return (object) $local;
        }
        $key = 'oembed_' . md5(serialize($args));
        $cached = get_transient($key);
        if (!empty($cached)) {
            return $cached;
        }
        $fetch = ['width' => (int) $request['maxwidth'], 'discover' => rest_sanitize_boolean($request['discover'] ?? true)] + (isset($request['maxheight']) ? ['height' => (int) $request['maxheight']] : []);
        $data = _wp_oembed_get_object()->get_data($url, $fetch);
        if ($data === false) {
            return $this->minn_handled($url, $fetch) ?? new WP_Error('oembed_invalid_url', get_status_header_desc(404), ['status' => 404]);
        }
        $data->html = apply_filters('oembed_result', _wp_oembed_get_object()->data2html((object) $data, $url), $url, $fetch);
        set_transient($key, $data, (int) apply_filters('rest_oembed_ttl', DAY_IN_SECONDS, $url, $fetch));
        return $data;
    }

    /** @internal what an embed handler makes of an address no provider takes, with the scripts it queued; null when none takes it */
    private function minn_handled(string $url, array $fetch): ?object
    {
        global $wp_embed;
        $before = wp_scripts()->queue;
        $html = $wp_embed instanceof WP_Embed ? $wp_embed->get_embed_handler_html(array_intersect_key($fetch, ['width' => 1, 'height' => 1]), $url) : false;
        if (!$html) {
            return null;
        }
        $scripts = [];
        foreach (array_diff(wp_scripts()->queue, $before) as $handle) {
            $scripts[] = wp_scripts()->registered[$handle]->src ?? '';
        }
        return (object) ['provider_name' => __('Embed Handler'), 'html' => $html, 'scripts' => $scripts];
    }
}
