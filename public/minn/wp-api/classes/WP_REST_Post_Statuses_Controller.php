<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/statuses as a class (probe rest-registry-controllers): the statuses
 * that are not internal, trash last, keyed by name, and one by name. A
 * public status is anyone's to read; the others (trash among them, other
 * internal ones never) only for someone who may edit posts of a type shown
 * in REST. Each links to the posts in it. The engine answers the route
 * itself; plugins call this class directly.
 */
class WP_REST_Post_Statuses_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'statuses';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<status>[\w-]+)', [
            'args' => ['status' => ['description' => __('An alphanumeric identifier for the status.'), 'type' => 'string']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        if ($request['context'] !== 'edit' || $this->minn_edits_a_type()) {
            return true;
        }
        return new WP_Error('rest_cannot_view', __('Sorry, you are not allowed to manage post statuses.'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items($request)
    {
        $statuses = get_post_stati(['internal' => false], 'object');
        $statuses['trash'] = get_post_status_object('trash');
        $data = [];
        foreach ($statuses as $status) {
            if ($this->check_read_permission($status)) {
                $data[$status->name] = $this->prepare_response_for_collection($this->prepare_item_for_response($status, $request));
            }
        }
        return rest_ensure_response($data);
    }

    public function get_item_permissions_check($request)
    {
        $status = get_post_status_object($request['status']);
        if (empty($status)) {
            return new WP_Error('rest_status_invalid', __('Invalid status.'), ['status' => 404]);
        }
        if (!$this->check_read_permission($status)) {
            return new WP_Error('rest_cannot_read_status', __('Cannot view status.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** Whether the caller may read a status: a public one, or one not internal (or trash) for someone who edits posts of a REST type. */
    protected function check_read_permission($status)
    {
        if ($status->public === true) {
            return true;
        }
        return ($status->internal === false || $status->name === 'trash') && $this->minn_edits_a_type();
    }

    public function get_item($request)
    {
        $status = get_post_status_object($request['status']);
        if (empty($status)) {
            return new WP_Error('rest_status_invalid', __('Invalid status.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response($status, $request);
    }

    /** A status as REST shows it, filtered by the fields asked for and the context, linked to the posts in it whatever the fields. */
    public function prepare_item_for_response($item, $request)
    {
        $status = $item;
        $fields = $this->get_fields_for_response($request);
        $values = [
            'name' => static fn () => $status->label,
            'private' => static fn () => (bool) $status->private,
            'protected' => static fn () => (bool) $status->protected,
            'public' => static fn () => (bool) $status->public,
            'queryable' => static fn () => (bool) $status->publicly_queryable,
            'show_in_list' => static fn () => (bool) $status->show_in_admin_all_list,
            'slug' => static fn () => $status->name,
            'date_floating' => static fn () => (bool) ($status->date_floating ?? false),
        ];
        $data = [];
        foreach ($values as $field => $value) {
            if (rest_is_field_included($field, $fields)) {
                $data[$field] = $value();
            }
        }
        $data = $this->filter_response_by_context($this->add_additional_fields_to_object($data, $request), $request['context'] ?? 'view');
        $response = rest_ensure_response($data);
        $posts = rest_url(rest_get_route_for_post_type_items('post'));
        $response->add_link('archives', $status->name === 'publish' ? $posts : add_query_arg('status', $status->name, $posts));
        return apply_filters('rest_prepare_status', $response, $status, $request);
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('status');
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }

    /** Whether the caller may edit posts of any type shown in REST. */
    private function minn_edits_a_type(): bool
    {
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $post_type) {
            if (current_user_can($post_type->cap->edit_posts)) {
                return true;
            }
        }
        return false;
    }
}
