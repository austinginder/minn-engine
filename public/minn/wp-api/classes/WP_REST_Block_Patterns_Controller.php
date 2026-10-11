<?php

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * The registered block patterns as a class (probe
 * rest-block-patterns-controller), over the patterns registry. The engine
 * serves no wp/v2/block-patterns route of its own (the editor reads it); a
 * plugin that registers this controller gets the reference's answer. The
 * remote patterns are wordpress.org's and stay dead ends.
 */
class WP_REST_Block_Patterns_Controller extends WP_REST_Controller
{
    private $remote_patterns_loaded = NULL;
    protected static $categories_migration = ['buttons' => 'call-to-action', 'columns' => 'text', 'query' => 'posts'];

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'block-patterns/patterns';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        // Anyone who edits posts, or any type shown in REST, may read the patterns.
        if (current_user_can('edit_posts')) {
            return true;
        }
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to view the registered block patterns.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        // The remote patterns once (dead ends here), then every registered pattern, its categories migrated.
        if (!$this->remote_patterns_loaded) {
            _load_remote_block_patterns();
            _load_remote_featured_patterns();
            // The theme's remote patterns wait on a decision (they are wordpress.org's); called when the runtime has them.
            if (function_exists('_register_remote_theme_patterns')) {
                _register_remote_theme_patterns();
            }
            $this->remote_patterns_loaded = true;
        }
        $response = [];
        foreach (WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern) {
            $response[] = $this->prepare_response_for_collection($this->prepare_item_for_response($this->migrate_pattern_categories($pattern), $request));
        }
        return rest_ensure_response($response);
    }

    protected function migrate_pattern_categories($pattern)
    {
        // An old category name becomes its new one, wherever it stands in the list.
        if (isset($pattern['categories']) && is_array($pattern['categories'])) {
            foreach ($pattern['categories'] as $index => $category) {
                if (isset(static::$categories_migration[$category])) {
                    $pattern['categories'][$index] = static::$categories_migration[$category];
                }
            }
        }
        return $pattern;
    }

    public function prepare_item_for_response($item, $request)
    {
        // The pattern's fields it has, under their REST names, the ones asked for; whatever the context.
        $keys = ['name' => 'name', 'title' => 'title', 'content' => 'content', 'description' => 'description', 'viewportWidth' => 'viewport_width', 'inserter' => 'inserter', 'categories' => 'categories', 'keywords' => 'keywords', 'blockTypes' => 'block_types', 'postTypes' => 'post_types', 'templateTypes' => 'template_types', 'source' => 'source'];
        $fields = $this->get_fields_for_response($request);
        $data = [];
        foreach ($keys as $item_key => $rest_key) {
            if (isset($item[$item_key]) && rest_is_field_included($rest_key, $fields)) {
                $data[$rest_key] = $item[$item_key];
            }
        }
        return rest_ensure_response($data);
    }

    /** The pattern fields captured from the reference (data/rest-schemas.json). */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $this->schema = Minn\Rest\SchemaData::of('block-pattern');
        return $this->add_additional_fields_schema($this->schema);
    }
}
