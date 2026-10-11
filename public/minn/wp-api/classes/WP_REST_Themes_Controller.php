<?php

use Minn\Rest\SchemaData;
use Minn\Runtime\ThemeSupports;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * The themes endpoints as a class (probe rest-themes-controller). The
 * engine answers wp/v2/themes itself; this is the class plugins construct
 * or call, WordPress-shaped over wp_get_themes() and wp_get_theme(), with
 * the engine's theme shape (InstalledThemesController::fields) and support
 * shaping (ThemeSupports::prepared).
 */
class WP_REST_Themes_Controller extends WP_REST_Controller
{
    const PATTERN = '[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?';

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'themes';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_item_schema'],
        ]);
        register_rest_route($this->namespace, sprintf('/%s/(?P<stylesheet>%s)', $this->rest_base, self::PATTERN), [
            'args' => ['stylesheet' => ['description' => __("The theme's stylesheet. This uniquely identifies the theme."), 'type' => 'string', 'sanitize_callback' => [$this, '_sanitize_stylesheet_callback']]],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function _sanitize_stylesheet_callback($stylesheet)
    {
        return urldecode((string) $stylesheet);
    }

    public function get_items_permissions_check($request)
    {
        // Who switches themes lists them all; asked only for the active one, who may read it.
        if (current_user_can('switch_themes') || current_user_can('manage_network_themes')) {
            return true;
        }
        if ($request['status'] === ['active']) {
            return $this->check_read_active_theme_permission();
        }
        return new WP_Error('rest_cannot_view_themes', __('Sorry, you are not allowed to view themes.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_item_permissions_check($request)
    {
        if (current_user_can('switch_themes') || current_user_can('manage_network_themes')) {
            return true;
        }
        if ($this->is_same_theme(wp_get_theme($request['stylesheet']), wp_get_theme())) {
            return $this->check_read_active_theme_permission();
        }
        return new WP_Error('rest_cannot_view_themes', __('Sorry, you are not allowed to view themes.'), ['status' => rest_authorization_required_code()]);
    }

    protected function check_read_active_theme_permission()
    {
        // Anyone who edits posts, or any type shown in REST, may read the active theme.
        if (current_user_can('edit_posts')) {
            return true;
        }
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return new WP_Error('rest_cannot_view_active_theme', __('Sorry, you are not allowed to view the active theme.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_item($request)
    {
        $theme = wp_get_theme($request['stylesheet']);
        if (!$theme->exists()) {
            return new WP_Error('rest_theme_not_found', __('Theme not found.'), ['status' => 404]);
        }
        return rest_ensure_response($this->prepare_item_for_response($theme, $request));
    }

    public function get_items($request)
    {
        // The installed themes with a status asked for (any when none is), and the counts.
        $themes = [];
        $current = wp_get_theme();
        foreach (wp_get_themes() as $theme) {
            $status = $this->is_same_theme($theme, $current) ? 'active' : 'inactive';
            if (is_array($request['status']) && !in_array($status, $request['status'], true)) {
                continue;
            }
            $themes[] = $this->prepare_response_for_collection($this->prepare_item_for_response($theme, $request));
        }
        $response = rest_ensure_response($themes);
        $response->header('X-WP-Total', count($themes));
        $response->header('X-WP-TotalPages', 1);
        return $response;
    }

    public function prepare_item_for_response($item, $request)
    {
        // The engine's theme shape, the fields asked for, its links when the fields take them; through rest_prepare_theme.
        $data = WP_REST_Server::minn_engine_api($request)->themeFields($item, $request);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($data);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($item));
        }
        return apply_filters('rest_prepare_theme', $response, $item, $request);
    }

    protected function prepare_links($theme)
    {
        // The engine's links for the theme, one a rel: the self link without its target hints (the server adds those), the export link with its own.
        $links = [];
        $engine = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/wp/v2/themes'))->themeFields($theme, new WP_REST_Request('GET', '/wp/v2/themes'))['_links'] ?? [];
        foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => $engine])->get_links() as $rel => $items) {
            $attributes = (array) $items[0]['attributes'];
            $links[$rel] = ['href' => $items[0]['href']] + ($rel === 'self' ? array_diff_key($attributes, ['targetHints' => true]) : $attributes);
        }
        return $links;
    }

    protected function is_same_theme($theme_a, $theme_b)
    {
        return $theme_a->get_stylesheet() === $theme_b->get_stylesheet();
    }

    protected function prepare_theme_support($support, $args, $feature, $request)
    {
        return ThemeSupports::prepared($support, (array) $args, (string) $feature, $request);
    }

    /** The theme fields captured from the reference (data/rest-schemas.json), each registered feature shown in REST among the supports. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = SchemaData::of('theme-properties');
        foreach (get_registered_theme_features() as $feature => $config) {
            if (!empty($config['show_in_rest']) && is_array($config['show_in_rest'])) {
                $properties['theme_supports']['properties'][$config['show_in_rest']['name'] ?? $feature] = $config['show_in_rest']['schema'];
            }
        }
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'theme', 'type' => 'object', 'properties' => $properties];
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['status' => ['description' => __('Limit result set to themes assigned one or more statuses.'), 'type' => 'array', 'items' => ['enum' => ['active', 'inactive'], 'type' => 'string']]];
    }

    public function sanitize_theme_status($statuses, $request, $parameter)
    {
        // Deprecated by the reference; the statuses as a list.
        _deprecated_function(__METHOD__, '5.7.0');
        return wp_parse_list($statuses);
    }
}
