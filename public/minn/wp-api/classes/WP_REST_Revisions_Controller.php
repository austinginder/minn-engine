<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * A post type's revisions as a class (probe rest-revisions). The engine
 * answers the core types' revision routes itself; plugins construct this
 * class or call it directly. A revision's shape is the engine's
 * (Rest\RevisionsController::object); the list is a revision query with
 * the reference's pages, offsets and order.
 */
class WP_REST_Revisions_Controller extends WP_REST_Controller
{
    private $parent_post_type = NULL;
    protected $meta = NULL;
    private $parent_controller = NULL;
    private $parent_base = NULL;

    public function __construct($parent_post_type)
    {
        $this->parent_post_type = $parent_post_type;
        $object = get_post_type_object($parent_post_type);
        $this->parent_controller = $object->get_rest_controller() ?: new WP_REST_Posts_Controller($parent_post_type);
        $this->rest_base = 'revisions';
        $this->parent_base = !empty($object->rest_base) ? $object->rest_base : $object->name;
        $this->namespace = !empty($object->rest_namespace) ? $object->rest_namespace : 'wp/v2';
        $this->meta = new WP_REST_Post_Meta_Fields($parent_post_type);
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->parent_base . '/(?P<parent>[\d]+)/' . $this->rest_base, [
            'args' => ['parent' => ['description' => __('The ID for the parent of the revision.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->parent_base . '/(?P<parent>[\d]+)/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['parent' => ['description' => __('The ID for the parent of the revision.'), 'type' => 'integer'], 'id' => ['description' => __('Unique identifier for the revision.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Required to be true, as revisions do not support trashing.')]]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    protected function get_parent($parent_post_id)
    {
        // A post of the parent type, or rest_post_invalid_parent.
        $error = new WP_Error('rest_post_invalid_parent', __('Invalid post parent ID.'), ['status' => 404]);
        $parent = (int) $parent_post_id > 0 ? get_post((int) $parent_post_id) : null;
        if (empty($parent) || empty($parent->ID) || $this->parent_post_type !== $parent->post_type) {
            return $error;
        }
        return $parent;
    }

    public function get_items_permissions_check($request)
    {
        $parent = $this->get_parent($request['parent']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        if (!current_user_can('edit_post', $parent->ID)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to view revisions of this post.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    protected function get_revision($id)
    {
        $revision = (int) $id > 0 ? get_post((int) $id) : null;
        if (empty($revision) || empty($revision->ID) || $revision->post_type !== 'revision') {
            return new WP_Error('rest_post_invalid_id', __('Invalid revision ID.'), ['status' => 404]);
        }
        return $revision;
    }

    /**
     * The parent's revisions a page at a time (all of them without a page
     * size), with the totals and the previous and next page links; ordering
     * by relevance needs a search, by include needs an include.
     */
    public function get_items($request)
    {
        $parent = $this->get_parent($request['parent']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        if ($request['orderby'] === 'relevance' && empty($request['search'])) {
            return new WP_Error('rest_no_search_term_defined', __('You need to define a search term to order by relevance.'), ['status' => 400]);
        }
        if ($request['orderby'] === 'include' && empty($request['include'])) {
            return new WP_Error('rest_orderby_include_missing_include', __('You need to define an include parameter to order by include.'), ['status' => 400]);
        }
        $found = wp_revisions_enabled($parent) ? $this->minn_revision_page($parent, $request) : [[], 0, 0, (int) $request['page']];
        if (is_wp_error($found)) {
            return $found;
        }
        [$revisions, $total, $pages, $page] = $found;
        $items = [];
        foreach ($revisions as $revision) {
            $items[] = $this->prepare_response_for_collection($this->prepare_item_for_response($revision, $request));
        }
        $response = rest_ensure_response($items);
        $response->header('X-WP-Total', (int) $total);
        $response->header('X-WP-TotalPages', (int) $pages);
        $base = add_query_arg(urlencode_deep($request->get_query_params()), rest_url(sprintf('%s/%s/%d/%s', $this->namespace, $this->parent_base, $request['parent'], $this->rest_base)));
        if ($page > 1) {
            $response->link_header('prev', add_query_arg('page', min($page - 1, $pages), $base));
        }
        if ($pages > $page) {
            $response->link_header('next', add_query_arg('page', $page + 1, $base));
        }
        return $response;
    }

    /** @internal one page of the parent's revisions: [revisions, total, pages, page], or the error for a page or offset past the end */
    private function minn_revision_page(WP_Post $parent, $request)
    {
        $args = ['post_parent' => $parent->ID, 'post_type' => 'revision', 'post_status' => 'inherit', 'posts_per_page' => -1, 'orderby' => 'date ID', 'order' => 'DESC', 'suppress_filters' => true];
        $registered = $this->get_collection_params();
        foreach (['exclude' => 'post__not_in', 'include' => 'post__in', 'offset' => 'offset', 'order' => 'order', 'orderby' => 'orderby', 'page' => 'paged', 'per_page' => 'posts_per_page', 'search' => 's'] as $param => $var) {
            if (isset($registered[$param], $request[$param])) {
                $args[$var] = $request[$param];
            }
        }
        // Ordering by date breaks ties by id.
        if ($args['orderby'] === 'date') {
            $args['orderby'] = 'date ID';
        }
        $query_args = $this->prepare_items_query(apply_filters('rest_revision_query', $args, $request), $request);
        $query = new WP_Query();
        $revisions = $query->query($query_args);
        $offset = (int) ($query_args['offset'] ?? 0);
        $page = (int) ($query_args['paged'] ?? 0);
        $total = $query->found_posts;
        if ($total < 1) {
            unset($query_args['paged'], $query_args['offset']);
            $total = (new WP_Query($query_args))->found_posts;
        }
        $per_page = (int) $query->query_vars['posts_per_page'];
        $pages = $per_page > 0 ? (int) ceil($total / $per_page) : ($total > 0 ? 1 : 0);
        if ($total > 0 && $offset >= $total) {
            return new WP_Error('rest_revision_invalid_offset_number', __('The offset number requested is larger than or equal to the number of available revisions.'), ['status' => 400]);
        }
        if ($total > 0 && !$offset && $page > $pages) {
            return new WP_Error('rest_revision_invalid_page_number', __('The page number requested is larger than the number of pages available.'), ['status' => 400]);
        }
        return [$revisions, $total, $pages, $page];
    }

    public function get_item_permissions_check($request)
    {
        return $this->get_items_permissions_check($request);
    }

    public function get_item($request)
    {
        $parent = $this->get_parent($request['parent']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        $revision = $this->get_revision($request['id']);
        if (is_wp_error($revision)) {
            return $revision;
        }
        if ((int) $parent->ID !== (int) $revision->post_parent) {
            /* translators: %d: A post id. */
            return new WP_Error('rest_revision_parent_id_mismatch', sprintf(__('The revision does not belong to the specified parent with id of "%d"'), $parent->ID), ['status' => 404]);
        }
        return $this->prepare_item_for_response($revision, $request);
    }

    public function delete_item_permissions_check($request)
    {
        $parent = $this->get_parent($request['parent']);
        if (is_wp_error($parent)) {
            return $parent;
        }
        if (!current_user_can('delete_post', $parent->ID)) {
            return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete revisions of this post.'), ['status' => rest_authorization_required_code()]);
        }
        $revision = $this->get_revision($request['id']);
        if (is_wp_error($revision)) {
            return $revision;
        }
        if (!current_user_can('delete_post', $revision->ID)) {
            return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete this revision.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function delete_item($request)
    {
        // Revisions are never trashed: only a forced delete, answering the revision as it was.
        $revision = $this->get_revision($request['id']);
        if (is_wp_error($revision)) {
            return $revision;
        }
        if (empty($request['force'])) {
            /* translators: %s: force=true */
            return new WP_Error('rest_trash_not_supported', sprintf(__("Revisions do not support trashing. Set '%s' to delete."), 'force=true'), ['status' => 501]);
        }
        $previous = $this->prepare_item_for_response($revision, $request);
        $result = wp_delete_post($request['id'], true);
        do_action('rest_delete_revision', $result, $request);
        if (!$result) {
            return new WP_Error('rest_cannot_delete', __('The post cannot be deleted.'), ['status' => 500]);
        }
        $response = new WP_REST_Response();
        $response->set_data(['deleted' => true, 'previous' => $previous->get_data()]);
        return $response;
    }

    protected function prepare_items_query($prepared_args = [], $request = NULL)
    {
        // Each argument through rest_query_var-{key}; REST orderby names mapped to WP_Query's.
        $query_args = [];
        foreach ((array) $prepared_args as $key => $value) {
            $query_args[$key] = apply_filters("rest_query_var-{$key}", $value);
        }
        $mapped = ['id' => 'ID', 'include' => 'post__in', 'slug' => 'post_name', 'include_slugs' => 'post_name__in'];
        if (isset($query_args['orderby'], $request['orderby'], $mapped[$request['orderby']])) {
            $query_args['orderby'] = $mapped[$request['orderby']];
        }
        return $query_args;
    }

    /** A revision as the engine shapes it, cut to the fields and context asked for; its parent link when the parent is shown. */
    public function prepare_item_for_response($item, $request)
    {
        $GLOBALS['post'] = $item;
        setup_postdata($item);
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->revisionObject((array) get_post($item->ID, ARRAY_A), $context === 'edit' ? Minn\Rest\Context::Edit : Minn\Rest\Context::View);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($this->add_additional_fields_to_object($data, $request), static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (!empty($data['parent'])) {
            $response->add_link('parent', rest_url(rest_get_route_for_post($data['parent'])));
        }
        return apply_filters('rest_prepare_revision', $response, $item, $request);
    }

    protected function prepare_date_response($date_gmt, $date = NULL)
    {
        if (isset($date)) {
            return mysql_to_rfc3339($date);
        }
        if ($date_gmt === '0000-00-00 00:00:00') {
            return null;
        }
        return mysql_to_rfc3339($date_gmt);
    }

    /**
     * A revision's schema: its own fields (captured from the reference,
     * data/rest-schemas.json), the guid, title, content and excerpt as the
     * parent type's schema has them, and the parent type's meta; then the
     * fields plugins register.
     */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $own = SchemaData::of('revision-properties');
        $parent = $this->parent_controller->get_item_schema();
        $properties = array_slice($own, 0, 3, true);
        if (!empty($parent['properties']['guid'])) {
            $properties['guid'] = $parent['properties']['guid'];
        }
        $properties += array_slice($own, 3, null, true);
        foreach (['title', 'content', 'excerpt'] as $field) {
            if (!empty($parent['properties'][$field])) {
                $properties[$field] = $parent['properties'][$field];
            }
        }
        $properties['meta'] = $this->meta->get_field_schema();
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => "{$this->parent_post_type}-revision", 'type' => 'object', 'properties' => $properties];
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        // The base controller's, no default page size, then ids, offset and ordering.
        $params = parent::get_collection_params();
        $params['context']['default'] = 'view';
        unset($params['per_page']['default']);
        $params['exclude'] = ['description' => __('Ensure result set excludes specific IDs.'), 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => []];
        $params['include'] = ['description' => __('Limit result set to specific IDs.'), 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => []];
        $params['offset'] = ['description' => __('Offset the result set by a specific number of items.'), 'type' => 'integer'];
        $params['order'] = ['description' => __('Order sort attribute ascending or descending.'), 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc']];
        $params['orderby'] = ['description' => __('Sort collection by object attribute.'), 'type' => 'string', 'default' => 'date', 'enum' => ['date', 'id', 'include', 'relevance', 'slug', 'include_slugs', 'title']];
        return $params;
    }

    protected function prepare_excerpt_response($excerpt, $post)
    {
        // The excerpt through the_excerpt; none stays none.
        $excerpt = apply_filters('the_excerpt', $excerpt, $post);
        return empty($excerpt) ? '' : $excerpt;
    }

    private function restore_post_data($previous_post)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Revisions_Controller::restore_post_data');
        return null;
    }
}
