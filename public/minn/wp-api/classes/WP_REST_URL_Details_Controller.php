<?php

use Minn\Rest\SchemaData;
use Minn\Rest\UrlDetails;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp-block-editor/v1/url-details (probe rest-embed-details): a page's
 * title, icon, description and image for the editor's link previews, for
 * anyone who can edit posts. The address loses its trailing slashes and
 * is fetched with wp_safe_remote_get (150 KB at most, a WP-URLDetails
 * user agent, both through rest_url_details_http_request_args); the page
 * itself is kept for an hour (rest_url_details_cache_expiration) in a
 * site transient named for the address, so a second ask reads the cache.
 * A failed fetch or any answer but 200 is no_response, an empty page
 * no_content, both 404. The details are read by Minn\Rest\UrlDetails and
 * the response passes through rest_prepare_url_details.
 */
class WP_REST_URL_Details_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp-block-editor/v1';
        $this->rest_base = 'url-details';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'parse_url_details'],
                'args' => ['url' => ['required' => true, 'description' => __('The URL to process.'), 'validate_callback' => 'wp_http_validate_url', 'sanitize_callback' => 'sanitize_url', 'type' => 'string', 'format' => 'uri']],
                'permission_callback' => [$this, 'permissions_check'],
            ],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_item_schema()
    {
        return SchemaData::of('url-details');
    }

    public function parse_url_details($request)
    {
        $url = untrailingslashit((string) $request['url']);
        if ($url === '') {
            return new WP_Error('rest_invalid_url', __('Invalid URL'), ['status' => 404]);
        }
        $key = 'g_url_details_response_' . md5($url);
        $html = get_site_transient($key);
        if (!$html) {
            $html = $this->minn_fetch($url);
            if (is_wp_error($html)) {
                return $html;
            }
            set_site_transient($key, $html, (int) apply_filters('rest_url_details_cache_expiration', HOUR_IN_SECONDS));
        }
        $response = rest_ensure_response(UrlDetails::of((string) $html, $url));
        return apply_filters('rest_prepare_url_details', $response, $url, $request, $html);
    }

    public function permissions_check()
    {
        return current_user_can('edit_posts') ? true : new WP_Error('rest_cannot_view_url_details', __('Sorry, you are not allowed to process remote URLs.'), ['status' => rest_authorization_required_code()]);
    }

    /** @internal the page's markup, or why there is none */
    private function minn_fetch(string $url)
    {
        $args = ['limit_response_size' => 150 * KB_IN_BYTES, 'user-agent' => 'WP-URLDetails/' . get_bloginfo('version') . ' (+' . get_bloginfo('url') . ')'];
        $response = wp_safe_remote_get($url, apply_filters('rest_url_details_http_request_args', $args, $url));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('no_response', __('URL not found. Response returned a non-200 status code for this URL.'), ['status' => WP_Http::NOT_FOUND]);
        }
        $body = wp_remote_retrieve_body($response);
        return $body === '' ? new WP_Error('no_content', __('Unable to retrieve body from response at this URL.'), ['status' => WP_Http::NOT_FOUND]) : $body;
    }
}
