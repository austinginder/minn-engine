<?php

use Minn\Rest\SchemaData;
use Minn\Theme\TemplateRecord;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * The templates and template parts endpoints as a class (probe
 * rest-templates-controller). The engine answers wp/v2/templates and
 * wp/v2/template-parts itself; this is the class plugins construct or
 * call, WordPress-shaped over the runtime's block template functions
 * (get_block_templates, get_block_template, wp_insert_post and the trash)
 * and the engine's template shape.
 */
class WP_REST_Templates_Controller extends WP_REST_Controller
{
    protected $post_type = NULL;

    public function __construct($post_type)
    {
        $this->post_type = $post_type;
        $object = get_post_type_object($post_type);
        $this->rest_base = !empty($object->rest_base) ? $object->rest_base : $object->name;
        $this->namespace = !empty($object->rest_namespace) ? $object->rest_namespace : 'wp/v2';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/lookup', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_template_fallback'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => [
                'slug' => ['description' => __('The slug of the template to get the fallback for'), 'type' => 'string', 'required' => true],
                'is_custom' => ['description' => __('Indicates if a template is custom or part of the template hierarchy'), 'type' => 'boolean'],
                'template_prefix' => ['description' => __('The template prefix for the created template. This is used to extract the main template type, e.g. in `taxonomy-books` extracts the `taxonomy`'), 'type' => 'string'],
            ]],
        ]);
        $id = ['id' => ['description' => __('The id of a template'), 'type' => 'string', 'sanitize_callback' => [$this, '_sanitize_template_id']]];
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>([^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?)[\/\w%-]+)', [
            'args' => $id,
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Whether to bypass Trash and force deletion.')]]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_template_fallback($request)
    {
        // The first template the slug's hierarchy names that the site has, shown as this route's type shows an item.
        $hierarchy = get_template_hierarchy((string) $request['slug'], (bool) $request['is_custom'], (string) $request['template_prefix']);
        $found = [];
        foreach (get_block_templates(['slug__in' => $hierarchy], 'wp_template') as $template) {
            $found[$template->slug] ??= $template;
        }
        foreach ($hierarchy as $slug) {
            if (isset($found[$slug])) {
                return rest_ensure_response($this->prepare_item_for_response($found[$slug], $request));
            }
        }
        return new WP_Error('rest_template_not_found', __('No templates exist with that id.'), ['status' => 404]);
    }

    protected function permissions_check($request)
    {
        // Changing templates is the theme's options' business.
        if (current_user_can('edit_theme_options')) {
            return true;
        }
        return new WP_Error('rest_cannot_manage_templates', __('Sorry, you are not allowed to access the templates on this site.'), ['status' => rest_authorization_required_code()]);
    }

    public function _sanitize_template_id($id)
    {
        // Decoded, and the theme and slug parted by two slashes at the last slash when only one stands there.
        $id = urldecode((string) $id);
        $last = strrpos($id, '/');
        if ($last === false || ($last > 0 && $id[$last - 1] === '/')) {
            return $id;
        }
        return substr($id, 0, $last) . '//' . substr($id, $last + 1);
    }

    public function get_items_permissions_check($request)
    {
        // Reading the layout is open to anyone who edits posts, as well as to who manages the theme.
        if (current_user_can('edit_theme_options') || current_user_can('edit_posts')) {
            return true;
        }
        return new WP_Error('rest_cannot_manage_templates', __('Sorry, you are not allowed to access the templates on this site.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        // The type's templates the request's filters keep, each as it is listed.
        $query = array_filter(['wp_id' => $request['wp_id'], 'area' => $request['area'], 'post_type' => $request['post_type']], static fn ($value) => $value !== null);
        $templates = [];
        foreach (get_block_templates($query, $this->post_type) as $template) {
            $templates[] = $this->prepare_response_for_collection($this->prepare_item_for_response($template, $request));
        }
        return rest_ensure_response($templates);
    }

    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }

    public function get_item($request)
    {
        $template = get_block_template($request['id'], $this->post_type);
        if (!$template) {
            return new WP_Error('rest_template_not_found', __('No templates exist with that id.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response($template, $request);
    }

    public function update_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    /**
     * A template saved: the site's own copy updated, or a theme's template
     * saved over in a new one, its after hooks firing as it saves (probe
     * rest-templates-controller); then
     * rest_after_insert_{type} and wp_after_insert_post once more, and the template in
     * the edit context.
     */
    public function update_item($request)
    {
        $template = get_block_template($request['id'], $this->post_type);
        if (!$template) {
            return new WP_Error('rest_template_not_found', __('No templates exist with that id.'), ['status' => 404]);
        }
        $changes = $this->prepare_item_for_database($request);
        if (is_wp_error($changes)) {
            return $changes;
        }
        $update = $template->source === 'custom';
        $before = $update ? get_post($template->wp_id) : null;
        $saved = $update ? wp_update_post(wp_slash((array) $changes), true) : wp_insert_post(wp_slash((array) $changes), true);
        if (is_wp_error($saved)) {
            $saved->add_data(['status' => $saved->get_error_code() === 'db_update_error' ? 500 : 400]);
            return $saved;
        }
        $template = get_block_template($request['id'], $this->post_type);
        $request->set_param('context', 'edit');
        $post = get_post($template->wp_id);
        do_action("rest_after_insert_{$this->post_type}", $post, $request, false);
        wp_after_insert_post($post, $update, $before);
        return rest_ensure_response($this->prepare_item_for_response($template, $request));
    }

    public function create_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    public function create_item($request)
    {
        // A new template of the site's own under the slug asked for (made unique); the answer is it, a 201.
        $prepared = $this->prepare_item_for_database($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $prepared->post_name = $request['slug'];
        $post_id = wp_insert_post(wp_slash((array) $prepared), true);
        if (is_wp_error($post_id)) {
            $post_id->add_data(['status' => $post_id->get_error_code() === 'db_insert_error' ? 500 : 400]);
            return $post_id;
        }
        $posts = get_block_templates(['wp_id' => $post_id], $this->post_type);
        if ($posts === []) {
            return new WP_Error('rest_template_insert_error', __('No templates exist with that id.'), ['status' => 400]);
        }
        $template = get_block_template($posts[0]->id, $this->post_type);
        $post = get_post($post_id);
        do_action("rest_after_insert_{$this->post_type}", $post, $request, true);
        wp_after_insert_post($post, false, null);
        $response = rest_ensure_response($this->prepare_item_for_response($template, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(sprintf('%s/%s/%s', $this->namespace, $this->rest_base, $template->id)));
        return $response;
    }

    public function delete_item_permissions_check($request)
    {
        return $this->permissions_check($request);
    }

    public function delete_item($request)
    {
        // Only the site's own copy goes: to the trash (the template in the edit context, trashed), or for good with force (what it was).
        $template = get_block_template($request['id'], $this->post_type);
        if (!$template) {
            return new WP_Error('rest_template_not_found', __('No templates exist with that id.'), ['status' => 404]);
        }
        if ($template->source !== 'custom') {
            return new WP_Error('rest_invalid_template', __("Templates based on theme files can't be removed."), ['status' => 400]);
        }
        $request->set_param('context', 'edit');
        if ((bool) $request['force']) {
            $previous = $this->prepare_item_for_response($template, $request);
            $done = wp_delete_post($template->wp_id, true);
            $response = new WP_REST_Response(['deleted' => true, 'previous' => $previous->get_data()]);
        } else {
            if ($template->status === 'trash') {
                return new WP_Error('rest_template_already_trashed', __('The template has already been deleted.'), ['status' => 410]);
            }
            $done = wp_trash_post($template->wp_id);
            $template->status = 'trash';
            $response = $this->prepare_item_for_response($template, $request);
        }
        return $done ? $response : new WP_Error('rest_cannot_delete', __('The template cannot be deleted.'), ['status' => 500]);
    }

    /**
     * What a request saves (probe rest-templates-controller): a new
     * template takes the type, publish and the theme; a theme's template
     * saved over takes its slug, the theme and its origin, and keeps its
     * content, title and description unless the request changes them; the
     * site's own copy takes its id. Then the request's content, title,
     * description, part area and author; through rest_pre_insert_{type}.
     */
    protected function prepare_item_for_database($request)
    {
        $template = $request['id'] ? get_block_template($request['id'], $this->post_type) : null;
        $changes = new stdClass();
        if ($template === null) {
            $changes->post_type = $this->post_type;
            $changes->post_status = 'publish';
            $changes->tax_input = ['wp_theme' => $request['theme'] ?? get_stylesheet()];
        } elseif ($template->source !== 'custom') {
            $changes->post_name = $template->slug;
            $changes->post_type = $this->post_type;
            $changes->post_status = 'publish';
            $changes->tax_input = ['wp_theme' => $template->theme];
            $changes->meta_input = ['origin' => $template->source];
        } else {
            $changes->post_name = $template->slug;
            $changes->ID = $template->wp_id;
            $changes->post_status = 'publish';
        }
        $keeps = $template !== null && $template->source !== 'custom';
        $this->minn_changed_text($changes, $request, $keeps ? $template : null);
        if ($this->post_type === 'wp_template_part' && isset($request['area'])) {
            $changes->tax_input['wp_template_part_area'] = _filter_block_template_part_area($request['area']);
        } elseif ($this->post_type === 'wp_template_part' && $keeps) {
            $changes->tax_input['wp_template_part_area'] = _filter_block_template_part_area((string) $template->area);
        }
        if (!empty($request['author'])) {
            $author = (int) $request['author'];
            if (get_current_user_id() !== $author && !get_userdata($author)) {
                return new WP_Error('rest_invalid_author', __('Invalid author ID.'), ['status' => 400]);
            }
            $changes->post_author = $author;
        }
        return apply_filters("rest_pre_insert_{$this->post_type}", $changes, $request);
    }

    /** @internal the request's content, title and description on the changes, else the theme template's own */
    private function minn_changed_text(stdClass $changes, $request, ?WP_Block_Template $kept): void
    {
        foreach (['content' => ['post_content', 'content'], 'title' => ['post_title', 'title'], 'description' => ['post_excerpt', 'description']] as $field => [$column, $own]) {
            if (isset($request[$field])) {
                $changes->{$column} = is_array($request[$field]) ? ($request[$field]['raw'] ?? '') : $request[$field];
            } elseif ($kept !== null) {
                $changes->{$column} = $kept->{$own};
            }
        }
    }

    public function prepare_item_for_response($item, $request)
    {
        // The engine's template shape for this route's type, the fields asked for in the context asked for, its links when the fields take them.
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->templateView(self::minn_record($item), $context, $this->post_type);
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $expanded = [];
            foreach (Minn\Rest\RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $items) {
                foreach ($items as $link) {
                    $expanded[$rel][] = ['href' => $link['href']] + array_diff_key((array) $link['attributes'], ['targetHints' => true]);
                }
            }
            $response->add_links($expanded);
        }
        return apply_filters("rest_prepare_{$this->post_type}", $response, $item, $request);
    }

    /** @internal a block template as the engine's template record */
    private static function minn_record(WP_Block_Template $template): TemplateRecord
    {
        $rest = static fn ($date) => $date ? mysql_to_rfc3339((string) $date) : null;
        return new TemplateRecord(theme: (string) $template->theme, slug: (string) $template->slug, type: (string) $template->type, content: (string) $template->content, title: (string) $template->title, description: (string) $template->description, source: (string) $template->source, origin: $template->origin, hasThemeFile: (bool) $template->has_theme_file, author: (int) $template->author, modified: $rest($template->modified), date: $rest($template->date ?? null), wpId: (int) $template->wp_id, status: (string) $template->status, area: $template->area ?? null, plugin: $template->plugin ?? null);
    }

    protected function prepare_links($id)
    {
        $collection = sprintf('%s/%s', $this->namespace, $this->rest_base);
        return ['self' => ['href' => rest_url($collection . '/' . $id)], 'collection' => ['href' => rest_url($collection)], 'about' => ['href' => rest_url('wp/v2/types/' . $this->post_type)]];
    }

    protected function get_available_actions()
    {
        // Publishing for who may publish the type, unfiltered HTML for who may post it.
        $actions = [];
        $object = get_post_type_object($this->post_type);
        if (current_user_can($object->cap->publish_posts)) {
            $actions[] = 'https://api.w.org/action-publish';
        }
        if (current_user_can('unfiltered_html')) {
            $actions[] = 'https://api.w.org/action-unfiltered-html';
        }
        return $actions;
    }

    public function get_collection_params()
    {
        return [
            'context' => $this->get_context_param(['default' => 'view']),
            'wp_id' => ['description' => __('Limit to the specified post id.'), 'type' => 'integer'],
            'area' => ['description' => __('Limit to the specified template part area.'), 'type' => 'string'],
            'post_type' => ['description' => __('Post type to get the templates for.'), 'type' => 'string'],
        ];
    }

    /** The template fields captured from the reference (data/rest-schemas.json), then the type's own: a template's is_custom and plugin, a part's area. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = SchemaData::of('template-properties') + (array) (SchemaData::of('template-type-properties')[$this->post_type] ?? []);
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => $this->post_type, 'type' => 'object', 'properties' => $properties];
        return $this->add_additional_fields_schema($this->schema);
    }
}
