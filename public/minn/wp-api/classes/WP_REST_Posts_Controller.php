<?php

use Minn\Content\PostRecord;
use Minn\Rest\PostCollectionParams;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * A post type's wp/v2 endpoints as a class (probe rest-posts-controller).
 * The engine answers the core types' routes itself; plugins construct this
 * class for a type, extend it as a type's rest_controller_class, or call
 * its methods directly. Reading goes through the engine: a post's shape
 * is the engine's PostObject, a list is the engine's own query.
 */
class WP_REST_Posts_Controller extends WP_REST_Controller
{
    protected $post_type = NULL;
    protected $meta = NULL;
    protected $password_check_passed = [];
    protected $allow_batch = ['v1' => true];

    public function __construct($post_type)
    {
        $this->post_type = $post_type;
        $object = get_post_type_object($post_type);
        $this->rest_base = !empty($object->rest_base) ? $object->rest_base : $object->name;
        $this->namespace = !empty($object->rest_namespace) ? $object->rest_namespace : 'wp/v2';
        $this->meta = new WP_REST_Post_Meta_Fields($this->post_type);
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        // Reading one post takes an excerpt length and a password when the type has those fields.
        $schema = $this->get_item_schema();
        $read = ['context' => $this->get_context_param(['default' => 'view'])];
        if (isset($schema['properties']['excerpt'])) {
            $read['excerpt_length'] = ['description' => __('Override the default excerpt length.'), 'type' => 'integer'];
        }
        if (isset($schema['properties']['password'])) {
            $read['password'] = ['description' => __('The password for the post if it is password protected.'), 'type' => 'string'];
        }
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['id' => ['description' => __('Unique identifier for the post.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => $read],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Whether to bypass Trash and force deletion.')]]],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        // Anyone may list; the edit context only for someone who edits posts of the type.
        $object = get_post_type_object($this->post_type);
        if ($request['context'] === 'edit' && !current_user_can($object->cap->edit_posts)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit posts in this post type.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function check_password_required($required, $post)
    {
        // A post whose password the request already passed, or that the user may edit, needs no password.
        if (!$required) {
            return $required;
        }
        if (!empty($this->password_check_passed[$post->ID])) {
            return false;
        }
        return !current_user_can('edit_post', $post->ID);
    }

    /**
     * The engine's own list for the type (the query, the totals, the
     * pages), each post shaped through this controller, so a subclass's
     * prepare_item_for_response shapes them.
     */
    public function get_items($request)
    {
        $listing = clone $request;
        $listing->set_route('/' . $this->namespace . '/' . $this->rest_base);
        $api = WP_REST_Server::minn_engine_api($listing);
        // Only the ids come from the engine's list, so each post renders once, here.
        $engine = Minn\Rest\RuntimePrepare::during($listing, fn () => $api->listPosts($listing->get_route(), $this->post_type, static fn (PostRecord $post): array => ['id' => $post->id], $listing));
        $body = json_decode($engine->body, true);
        if ($engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_error'), (string) ($body['message'] ?? ''), (array) ($body['data'] ?? ['status' => $engine->status]));
        }
        $posts = [];
        foreach ((array) $body as $item) {
            $post = isset($item['id']) ? get_post((int) $item['id']) : null;
            if ($post instanceof WP_Post) {
                $posts[] = $this->prepare_response_for_collection($this->prepare_item_for_response($post, $request));
            }
        }
        $response = rest_ensure_response($posts);
        foreach (['X-WP-Total', 'X-WP-TotalPages'] as $header) {
            if (isset($engine->headers[$header])) {
                $response->header($header, (int) $engine->headers[$header]);
            }
        }
        if (isset($engine->headers['Link'])) {
            $response->header('Link', $engine->headers['Link']);
        }
        return $response;
    }

    protected function get_post($id)
    {
        $error = new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
        if ((int) $id <= 0) {
            return $error;
        }
        $post = get_post((int) $id);
        if (empty($post) || empty($post->ID) || $this->post_type !== $post->post_type) {
            return $error;
        }
        return $post;
    }

    public function get_item_permissions_check($request)
    {
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        if ($request['context'] === 'edit' && !$this->check_update_permission($post)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit this post.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request->get_query_params()['password'])) {
            if (!hash_equals((string) $post->post_password, (string) $request['password'])) {
                return new WP_Error('rest_post_incorrect_password', __('Incorrect post password.'), ['status' => 403]);
            }
            $this->password_check_passed[$post->ID] = true;
        }
        // The edit context reads every password protected post the user may edit.
        if ($request['context'] === 'edit') {
            add_filter('post_password_required', [$this, 'check_password_required'], 10, 2);
        }
        return $this->check_read_permission($post);
    }

    public function can_access_password_content($post, $request)
    {
        // Nothing to unlock without a password; the edit context unlocks what the user may edit; otherwise the password must match.
        if (empty($post->post_password)) {
            return false;
        }
        if ($request['context'] === 'edit' && current_user_can('edit_post', $post->ID)) {
            return true;
        }
        if (empty($request['password'])) {
            return false;
        }
        return hash_equals($post->post_password, (string) $request['password']);
    }

    public function get_item($request)
    {
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        $response = rest_ensure_response($this->prepare_item_for_response($post, $request));
        if (is_post_type_viewable(get_post_type_object($post->post_type))) {
            $response->link_header('alternate', get_permalink($post->ID), ['type' => 'text/html']);
        }
        return $response;
    }

    public function create_item_permissions_check($request)
    {
        // No id; another author only for someone who edits others' posts; sticky only for someone who publishes and edits others'; then creating, then the terms.
        if (!empty($request['id'])) {
            return new WP_Error('rest_post_exists', __('Cannot create existing post.'), ['status' => 400]);
        }
        $object = get_post_type_object($this->post_type);
        if (!empty($request['author']) && get_current_user_id() !== $request['author'] && !current_user_can($object->cap->edit_others_posts)) {
            return new WP_Error('rest_cannot_edit_others', __('Sorry, you are not allowed to create posts as this user.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request['sticky']) && !current_user_can($object->cap->edit_others_posts) && !current_user_can($object->cap->publish_posts)) {
            return new WP_Error('rest_cannot_assign_sticky', __('Sorry, you are not allowed to make posts sticky.'), ['status' => rest_authorization_required_code()]);
        }
        if (!current_user_can($object->cap->create_posts)) {
            return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to create posts as this user.'), ['status' => rest_authorization_required_code()]);
        }
        if (!$this->check_assign_terms_permission($request)) {
            return new WP_Error('rest_cannot_assign_term', __('Sorry, you are not allowed to assign the provided terms.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item($request)
    {
        // The engine writes the post (its save, terms, meta and actions); the answer is the edit view through this controller.
        if (!empty($request['id'])) {
            return new WP_Error('rest_post_exists', __('Cannot create existing post.'), ['status' => 400]);
        }
        $created = $this->minn_engine_write('POST', '/' . $this->namespace . '/' . $this->rest_base, $request);
        if (is_wp_error($created)) {
            return $created;
        }
        $post = get_post((int) ($created['id'] ?? 0));
        $request->set_param('context', 'edit');
        $response = rest_ensure_response($this->prepare_item_for_response($post, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(rest_get_route_for_post($post)));
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        $object = get_post_type_object($this->post_type);
        if ($post && !$this->check_update_permission($post)) {
            return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this post.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request['author']) && get_current_user_id() !== $request['author'] && !current_user_can($object->cap->edit_others_posts)) {
            return new WP_Error('rest_cannot_edit_others', __('Sorry, you are not allowed to update posts as this user.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request['sticky']) && !current_user_can($object->cap->edit_others_posts) && !current_user_can($object->cap->publish_posts)) {
            return new WP_Error('rest_cannot_assign_sticky', __('Sorry, you are not allowed to make posts sticky.'), ['status' => rest_authorization_required_code()]);
        }
        if (!$this->check_assign_terms_permission($request)) {
            return new WP_Error('rest_cannot_assign_term', __('Sorry, you are not allowed to assign the provided terms.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function update_item($request)
    {
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        $updated = $this->minn_engine_write($request->get_method() === 'PATCH' ? 'PATCH' : 'PUT', '/' . $this->namespace . '/' . $this->rest_base . '/' . $post->ID, $request);
        if (is_wp_error($updated)) {
            return $updated;
        }
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_post($post->ID), $request));
    }

    public function delete_item_permissions_check($request)
    {
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        if ($post && !$this->check_delete_permission($post)) {
            return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete this post.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function delete_item($request)
    {
        // Trashed, the post in its edit view; deleted for good, the view it had before as previous.
        $post = $this->get_post($request['id']);
        if (is_wp_error($post)) {
            return $post;
        }
        $force = (bool) $request['force'];
        $request->set_param('context', 'edit');
        $previous = $force ? $this->prepare_item_for_response($post, $request) : null;
        $deleted = $this->minn_engine_write('DELETE', '/' . $this->namespace . '/' . $this->rest_base . '/' . $post->ID, $request);
        if (is_wp_error($deleted)) {
            return $deleted;
        }
        if ($previous !== null) {
            $response = new WP_REST_Response();
            $response->set_data(['deleted' => true, 'previous' => $previous->get_data()]);
            return $response;
        }
        return rest_ensure_response($this->prepare_item_for_response(get_post($post->ID), $request));
    }

    protected function prepare_items_query($prepared_args = [], $request = NULL)
    {
        // Each argument through rest_query_var-{key}; stickies ignored unless a post list says otherwise; REST orderby names mapped to WP_Query's.
        $query_args = [];
        foreach ((array) $prepared_args as $key => $value) {
            $query_args[$key] = apply_filters("rest_query_var-{$key}", $value);
        }
        if ($this->post_type !== 'post' || !isset($query_args['ignore_sticky_posts'])) {
            $query_args['ignore_sticky_posts'] = true;
        }
        $mapped = ['id' => 'ID', 'include' => 'post__in', 'slug' => 'post_name', 'include_slugs' => 'post_name__in'];
        if (isset($query_args['orderby'], $request['orderby'], $mapped[$request['orderby']])) {
            $query_args['orderby'] = $mapped[$request['orderby']];
        }
        return $query_args;
    }

    protected function prepare_date_response($date_gmt, $date = NULL)
    {
        // The local date when one is given; an unset GMT date is no date.
        if (isset($date)) {
            return mysql_to_rfc3339($date);
        }
        if ($date_gmt === '0000-00-00 00:00:00') {
            return null;
        }
        return mysql_to_rfc3339($date_gmt);
    }

    protected function prepare_item_for_database($request)
    {
        // The fields the request sets and the type has, in the reference's order, then rest_pre_insert_{type}.
        $prepared = new stdClass();
        if (isset($request['id'])) {
            $existing = $this->get_post($request['id']);
            if (is_wp_error($existing)) {
                return $existing;
            }
            $prepared->ID = $existing->ID;
        }
        $schema = $this->get_item_schema();
        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $field => $column) {
            if (!empty($schema['properties'][$field]) && isset($request[$field])) {
                $prepared->{$column} = is_string($request[$field]) ? $request[$field] : ($request[$field]['raw'] ?? null);
            }
        }
        $prepared->post_type = $this->post_type;
        $prepared = $this->minn_prepared_settings($prepared, $request, $schema);
        return is_wp_error($prepared) ? $prepared : apply_filters("rest_pre_insert_{$this->post_type}", $prepared, $request);
    }

    public function check_status($status, $request, $param)
    {
        // The status must be one the route takes.
        return rest_validate_request_arg($status, $request, $param);
    }

    protected function handle_status_param($post_status, $post_type)
    {
        // Drafts and pending posts for anyone; private, published and scheduled ones need publishing; an unknown status is a draft.
        switch ($post_status) {
            case 'draft':
            case 'pending':
                break;
            case 'private':
                if (!current_user_can($post_type->cap->publish_posts)) {
                    return new WP_Error('rest_cannot_publish', __('Sorry, you are not allowed to create private posts in this post type.'), ['status' => rest_authorization_required_code()]);
                }
                break;
            case 'publish':
            case 'future':
                if (!current_user_can($post_type->cap->publish_posts)) {
                    return new WP_Error('rest_cannot_publish', __('Sorry, you are not allowed to publish posts in this post type.'), ['status' => rest_authorization_required_code()]);
                }
                break;
            default:
                if (!get_post_status_object($post_status)) {
                    $post_status = 'draft';
                }
        }
        return $post_status;
    }

    protected function handle_featured_media($featured_media, $post_id)
    {
        // A media id becomes the thumbnail when it can be one; none removes it.
        $featured_media = (int) $featured_media;
        if (!$featured_media) {
            return delete_post_thumbnail($post_id);
        }
        if (set_post_thumbnail($post_id, $featured_media)) {
            return true;
        }
        return new WP_Error('rest_invalid_featured_media', __('Invalid featured media ID.'), ['status' => 400]);
    }

    public function check_template($template, $request)
    {
        // No template, the post's own, or one the theme offers for the type.
        if (!$template) {
            return true;
        }
        $post = !empty($request['id']) ? get_post((int) $request['id']) : null;
        if ($post && get_page_template_slug($post) === $template) {
            return true;
        }
        $allowed = wp_get_theme()->get_page_templates($post, $this->post_type);
        if (isset($allowed[$template])) {
            return true;
        }
        /* translators: 1: Parameter, 2: List of valid values. */
        return new WP_Error('rest_invalid_param', sprintf(__('%1$s is not one of %2$s.'), 'template', implode(', ', array_keys($allowed))));
    }

    public function handle_template($template, $post_id, $validate = false)
    {
        // Checked against the theme's templates for the post when asked; an unknown one is cleared.
        if ($validate && !array_key_exists($template, wp_get_theme()->get_page_templates(get_post($post_id)))) {
            $template = '';
        }
        update_post_meta($post_id, '_wp_page_template', $template);
    }

    protected function handle_terms($post_id, $request)
    {
        // Each REST taxonomy the request names, set to the terms it lists.
        foreach ($this->minn_rest_taxonomies() as $base => $taxonomy) {
            if (!isset($request[$base])) {
                continue;
            }
            $result = wp_set_object_terms($post_id, $request[$base], $taxonomy->name);
            if (is_wp_error($result)) {
                return $result;
            }
        }
    }

    protected function check_assign_terms_permission($request)
    {
        // Every term the request lists, in each REST taxonomy, must be one the user may assign (a term that does not exist is refused later).
        foreach ($this->minn_rest_taxonomies() as $base => $taxonomy) {
            foreach (isset($request[$base]) ? (array) $request[$base] : [] as $term_id) {
                if (get_term($term_id, $taxonomy->name) && !current_user_can('assign_term', (int) $term_id)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @internal the status, dates, slug, author, password, parent, order and discussion the request sets, as the reference orders them; an error when one is not allowed */
    private function minn_prepared_settings(stdClass $prepared, $request, array $schema)
    {
        if (!empty($schema['properties']['status']) && isset($request['status'])) {
            $status = $this->handle_status_param($request['status'], get_post_type_object($this->post_type));
            if (is_wp_error($status)) {
                return $status;
            }
            $prepared->post_status = $status;
        }
        $dates = !empty($schema['properties']['date']) && !empty($request['date']) ? rest_get_date_with_gmt($request['date']) : (!empty($schema['properties']['date_gmt']) && !empty($request['date_gmt']) ? rest_get_date_with_gmt($request['date_gmt'], true) : null);
        if (!empty($dates)) {
            [$prepared->post_date, $prepared->post_date_gmt] = $dates;
            $prepared->edit_date = true;
        }
        if (!empty($schema['properties']['slug']) && isset($request['slug'])) {
            $prepared->post_name = $request['slug'];
        }
        if (!empty($schema['properties']['author']) && !empty($request['author'])) {
            if (get_current_user_id() !== (int) $request['author'] && !get_userdata((int) $request['author'])) {
                return new WP_Error('rest_invalid_author', __('Invalid author ID.'), ['status' => 400]);
            }
            $prepared->post_author = $request['author'];
        }
        return $this->minn_prepared_access($prepared, $request, $schema);
    }

    /** @internal the password (never with sticky), the parent (which must exist), the order and the discussion settings */
    private function minn_prepared_access(stdClass $prepared, $request, array $schema)
    {
        if (!empty($schema['properties']['password']) && isset($request['password'])) {
            $prepared->post_password = $request['password'];
            if ($request['password'] !== '' && (!empty($request['sticky']) || (!empty($request['id']) && is_sticky((int) $request['id'])))) {
                return new WP_Error('rest_invalid_field', __('A post can not be sticky and have a password.'), ['status' => 400]);
            }
        }
        if (!empty($schema['properties']['parent']) && isset($request['parent'])) {
            if ((int) $request['parent'] !== 0 && !get_post((int) $request['parent'])) {
                return new WP_Error('rest_post_invalid_id', __('Invalid post parent ID.'), ['status' => 400]);
            }
            $prepared->post_parent = (int) $request['parent'];
        }
        foreach (['menu_order' => 'menu_order', 'comment_status' => 'comment_status', 'ping_status' => 'ping_status'] as $field => $column) {
            if (!empty($schema['properties'][$field]) && isset($request[$field])) {
                $prepared->{$column} = $field === 'menu_order' ? (int) $request[$field] : $request[$field];
            }
        }
        // The template is set after the save, not through it.
        $prepared->page_template = null;
        return $prepared;
    }

    /** @internal one of the engine's own writes for this type, answering the request in process: the written object, or the error it refused with */
    private function minn_engine_write(string $method, string $route, $request)
    {
        $write = clone $request;
        $write->set_method($method);
        $write->set_route($route);
        $api = WP_REST_Server::minn_engine_api($write);
        $engine = Minn\Rest\RuntimePrepare::during($write, static fn () => $api->handleEngineOnly($route, $write));
        if ($engine === null) {
            return new WP_Error('rest_no_route', __('No route was found matching the URL and request method.'), ['status' => 404]);
        }
        $body = json_decode($engine->body, true);
        if ($engine->status >= 400) {
            return new WP_Error((string) ($body['code'] ?? 'rest_error'), (string) ($body['message'] ?? ''), $body['data'] ?? ['status' => $engine->status]);
        }
        return (array) $body;
    }

    protected function check_is_post_type_allowed($post_type)
    {
        if (!is_object($post_type)) {
            $post_type = get_post_type_object((string) $post_type);
        }
        return !empty($post_type) && !empty($post_type->show_in_rest);
    }

    public function check_read_permission($post)
    {
        // Published, or readable by the user, or of a public status; an attachment inherits its parent's.
        if (!$this->check_is_post_type_allowed(get_post_type_object($post->post_type))) {
            return false;
        }
        if ($post->post_status === 'publish' || current_user_can('read_post', $post->ID)) {
            return true;
        }
        $status = get_post_status_object($post->post_status);
        if ($status && $status->public) {
            return true;
        }
        if ($post->post_status === 'inherit' && $post->post_parent > 0) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                return $this->check_read_permission($parent);
            }
        }
        return $post->post_status === 'inherit';
    }

    protected function check_update_permission($post)
    {
        return $this->check_is_post_type_allowed(get_post_type_object($post->post_type)) && current_user_can('edit_post', $post->ID);
    }

    protected function check_create_permission($post)
    {
        $object = get_post_type_object($post->post_type);
        return $this->check_is_post_type_allowed($object) && current_user_can($object->cap->create_posts);
    }

    protected function check_delete_permission($post)
    {
        return $this->check_is_post_type_allowed(get_post_type_object($post->post_type)) && current_user_can('delete_post', $post->ID);
    }

    /**
     * A post as the engine shapes it (view or edit; embed narrows the view)
     * for the fields asked for, its links on the response when asked for.
     */
    public function prepare_item_for_response($item, $request)
    {
        $GLOBALS['post'] = $item;
        setup_postdata($item);
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $object = WP_REST_Server::minn_engine_api($request)->postObject();
        $record = PostRecord::fromRow((array) get_post($item->ID, ARRAY_A));
        $data = $context === 'edit' ? $object->edit($record, get_current_user_id()) : $object->view($record);
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links(self::minn_links_for_response($links));
        }
        return $response;
    }

    /** @internal the engine's links as add_links takes them: rels expanded, the transport's target hints left to the server */
    private static function minn_links_for_response(array $links): array
    {
        $expanded = [];
        foreach (RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $items) {
            foreach ($items as $item) {
                $expanded[$rel][] = ['href' => $item['href']] + array_diff_key((array) $item['attributes'], ['targetHints' => true]);
            }
        }
        return $expanded;
    }

    public function protected_title_format()
    {
        return '%s';
    }

    protected function prepare_links($post)
    {
        // The engine's links for the post: one link a rel, the term links a list.
        $object = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $post->ID))->postObject();
        $links = [];
        foreach (self::minn_links_for_response($object->links(PostRecord::fromRow((array) get_post($post->ID, ARRAY_A)))) as $rel => $items) {
            $links[$rel] = $rel === 'https://api.w.org/term' ? $items : $items[0];
        }
        return $links;
    }

    protected function get_available_actions($post, $request)
    {
        // In the edit context, the actions the user may take on the post: publish, unfiltered HTML, sticky, author, then each taxonomy's create and assign.
        if ($request['context'] !== 'edit') {
            return [];
        }
        $rels = [];
        $object = get_post_type_object($post->post_type);
        if ($this->post_type !== 'attachment' && current_user_can($object->cap->publish_posts)) {
            $rels[] = 'https://api.w.org/action-publish';
        }
        if (current_user_can('unfiltered_html')) {
            $rels[] = 'https://api.w.org/action-unfiltered-html';
        }
        if ($object->name === 'post' && current_user_can($object->cap->edit_others_posts) && current_user_can($object->cap->publish_posts)) {
            $rels[] = 'https://api.w.org/action-sticky';
        }
        if (post_type_supports($object->name, 'author') && current_user_can($object->cap->edit_others_posts)) {
            $rels[] = 'https://api.w.org/action-assign-author';
        }
        foreach ($this->minn_rest_taxonomies() as $base => $taxonomy) {
            if (current_user_can(is_taxonomy_hierarchical($taxonomy->name) ? $taxonomy->cap->edit_terms : $taxonomy->cap->assign_terms)) {
                $rels[] = 'https://api.w.org/action-create-' . $base;
            }
            if (current_user_can($taxonomy->cap->assign_terms)) {
                $rels[] = 'https://api.w.org/action-assign-' . $base;
            }
        }
        return $rels;
    }

    /**
     * The type's item schema: the fields every post has, then those its
     * visibility, hierarchy and supports bring (definitions captured from
     * the reference, data/rest-schemas.json), its meta, sticky for posts,
     * the template, each REST taxonomy's terms; the schema links; through
     * rest_{type}_item_schema; then the fields plugins register.
     */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $object = get_post_type_object($this->post_type);
        $schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => $this->post_type, 'type' => 'object', 'properties' => []];
        foreach ($this->minn_schema_fields($object) as $field) {
            $schema['properties'][$field] = $field === 'meta' ? $this->meta->get_field_schema() : $this->minn_bound(SchemaData::of('post-properties')[$field]);
        }
        $schema['properties']['status']['enum'] = array_keys(get_post_stati(['internal' => false]));
        if (isset($schema['properties']['format'])) {
            $schema['properties']['format']['enum'] = array_values(get_post_format_slugs());
        }
        foreach ($this->minn_rest_taxonomies() as $base => $taxonomy) {
            /* translators: %s: Taxonomy name. */
            $schema['properties'][$base] = ['description' => sprintf(__('The terms assigned to the post in the %s taxonomy.'), $taxonomy->name), 'type' => 'array', 'items' => ['type' => 'integer'], 'context' => ['view', 'edit']];
        }
        $schema['links'] = $this->get_schema_links();
        $this->schema = apply_filters("rest_{$this->post_type}_item_schema", $schema);
        return $this->add_additional_fields_schema($this->schema);
    }

    /** @internal the fields a type's schema carries, in order @return list<string> */
    private function minn_schema_fields(WP_Post_Type $object): array
    {
        $fields = ['date', 'date_gmt', 'guid', 'id', 'link', 'modified', 'modified_gmt', 'slug', 'status', 'type', 'password'];
        $by = [
            'permalink_template' => $object->public, 'generated_slug' => $object->public, 'class_list' => $object->public, 'parent' => $object->hierarchical,
            // A page carries an excerpt whatever its supports say (observed).
            'title' => 'title', 'content' => 'editor', 'author' => 'author', 'excerpt' => $this->post_type === 'page' || post_type_supports($this->post_type, 'excerpt'), 'featured_media' => 'thumbnail', 'comment_status' => 'comments', 'ping_status' => 'comments',
            'menu_order' => 'page-attributes', 'format' => 'post-formats', 'meta' => $this->post_type === 'attachment' || post_type_supports($this->post_type, 'custom-fields'), 'sticky' => $this->post_type === 'post',
        ];
        foreach ($by as $field => $when) {
            if (is_string($when) ? post_type_supports($this->post_type, $when) : (bool) $when) {
                $fields[] = $field;
            }
        }
        $fields[] = 'template';
        return $fields;
    }

    /** @internal a captured definition with its @this:method callbacks bound to this controller */
    private function minn_bound($definition)
    {
        if (is_string($definition) && str_starts_with($definition, '@this:')) {
            return [$this, substr($definition, 6)];
        }
        return is_array($definition) ? array_map(fn ($value) => $this->minn_bound($value), $definition) : $definition;
    }

    /** @internal the type's taxonomies shown in REST, by REST base @return array<string, WP_Taxonomy> */
    private function minn_rest_taxonomies(): array
    {
        $taxonomies = [];
        foreach (wp_list_filter(get_object_taxonomies($this->post_type, 'objects'), ['show_in_rest' => true]) as $taxonomy) {
            $taxonomies[!empty($taxonomy->rest_base) ? $taxonomy->rest_base : $taxonomy->name] = $taxonomy;
        }
        return $taxonomies;
    }

    protected function get_schema_links()
    {
        // The actions a client may be offered: publish, unfiltered HTML, sticky (posts), author (with author support), then each taxonomy's assign and create.
        $href = rest_url("{$this->namespace}/{$this->rest_base}/{id}");
        $links = [];
        if ($this->post_type !== 'attachment') {
            $links[] = ['rel' => 'https://api.w.org/action-publish', 'title' => __('The current user can publish this post.'), 'href' => $href, 'targetSchema' => ['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'enum' => ['publish', 'future']]]]];
        }
        $links[] = ['rel' => 'https://api.w.org/action-unfiltered-html', 'title' => __('The current user can post unfiltered HTML markup and JavaScript.'), 'href' => $href, 'targetSchema' => ['type' => 'object', 'properties' => ['content' => ['raw' => ['type' => 'string']]]]];
        if ($this->post_type === 'post') {
            $links[] = ['rel' => 'https://api.w.org/action-sticky', 'title' => __('The current user can sticky this post.'), 'href' => $href, 'targetSchema' => ['type' => 'object', 'properties' => ['sticky' => ['type' => 'boolean']]]];
        }
        if (post_type_supports($this->post_type, 'author')) {
            $links[] = ['rel' => 'https://api.w.org/action-assign-author', 'title' => __('The current user can change the author on this post.'), 'href' => $href, 'targetSchema' => ['type' => 'object', 'properties' => ['author' => ['type' => 'integer']]]];
        }
        foreach ($this->minn_rest_taxonomies() as $base => $taxonomy) {
            $terms = ['type' => 'object', 'properties' => [$base => ['type' => 'array', 'items' => ['type' => 'integer']]]];
            /* translators: %s: Taxonomy name. */
            $links[] = ['rel' => 'https://api.w.org/action-assign-' . $base, 'title' => sprintf(__('The current user can assign terms in the %s taxonomy.'), $taxonomy->name), 'href' => $href, 'targetSchema' => $terms];
            /* translators: %s: Taxonomy name. */
            $links[] = ['rel' => 'https://api.w.org/action-create-' . $base, 'title' => sprintf(__('The current user can create terms in the %s taxonomy.'), $taxonomy->name), 'href' => $href, 'targetSchema' => $terms];
        }
        return $links;
    }

    /** The engine's parameters for the type's list, with the callbacks the reference names, through rest_{type}_collection_params. */
    public function get_collection_params()
    {
        $object = get_post_type_object($this->post_type);
        $params = array_replace($object instanceof WP_Post_Type ? PostCollectionParams::built($object) : [], parent::get_collection_params());
        $params['context']['default'] = 'view';
        if (isset($params['status'])) {
            $params['status']['sanitize_callback'] = [$this, 'sanitize_post_statuses'];
        }
        return apply_filters("rest_{$this->post_type}_collection_params", $params, $object);
    }

    public function sanitize_post_statuses($statuses, $request, $parameter)
    {
        // The default status needs no right; any other needs editing the type (or reading its private posts, for private), and must be valid.
        $statuses = wp_parse_slug_list($statuses);
        $default = $request->get_attributes()['args']['status']['default'] ?? null;
        $object = get_post_type_object($this->post_type);
        foreach ($statuses as $status) {
            if ($status === $default) {
                continue;
            }
            if (!current_user_can($object->cap->edit_posts) && !($status === 'private' && current_user_can($object->cap->read_private_posts))) {
                return new WP_Error('rest_forbidden_status', __('Status is forbidden.'), ['status' => rest_authorization_required_code()]);
            }
            $valid = rest_validate_request_arg($status, $request, $parameter);
            if (is_wp_error($valid)) {
                return $valid;
            }
        }
        return $statuses;
    }

    private function prepare_tax_query(array $args, WP_REST_Request $request)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Posts_Controller::prepare_tax_query');
        return null;
    }

    private function prepare_taxonomy_limit_schema(array $query_params)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Posts_Controller::prepare_taxonomy_limit_schema');
        return null;
    }
}
