<?php

use Minn\Content\UserRecord;
use Minn\Http\Args;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2 users as a class (probe rest-users-controller). The engine answers
 * the routes itself; plugins construct this class or call it directly. A
 * user's shape is the engine's UserObject; lists and writes run the
 * engine's own routes, each user shaped here.
 */
class WP_REST_Users_Controller extends WP_REST_Controller
{
    protected $meta = NULL;
    protected $allow_batch = ['v1' => true];

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'users';
        $this->meta = new WP_REST_User_Meta_Fields();
    }

    public function register_routes()
    {
        $delete = ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Required to be true, as users do not support trashing.')], 'reassign' => ['type' => 'integer', 'description' => __('Reassign the deleted user\'s posts and links to this user ID.'), 'required' => true, 'sanitize_callback' => [$this, 'check_reassign']]];
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['id' => ['description' => __('Unique identifier for the user.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => $delete],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/me', [
            ['methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true', 'callback' => [$this, 'get_current_item'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_current_item'], 'permission_callback' => [$this, 'update_current_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_current_item'], 'permission_callback' => [$this, 'delete_current_item_permissions_check'], 'args' => $delete],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function check_reassign($value, $request, $param)
    {
        // Nothing to reassign to, or a user id; anything else is not a user.
        if (is_numeric($value)) {
            return $value;
        }
        if (empty($value) || $value === false || $value === 'false') {
            return false;
        }
        return new WP_Error('rest_invalid_param', __('Invalid user parameter(s).'), ['status' => 400]);
    }

    public function get_items_permissions_check($request)
    {
        // Listing is open; editing, private orderings, authors and role or capability filters need the right caps.
        if (!empty($request['roles']) && !current_user_can('list_users')) {
            return new WP_Error('rest_user_cannot_view', __('Sorry, you are not allowed to filter users by role.'), ['status' => rest_authorization_required_code()]);
        }
        if (!empty($request['capabilities']) && !current_user_can('list_users')) {
            return new WP_Error('rest_user_cannot_view', __('Sorry, you are not allowed to filter users by capability.'), ['status' => rest_authorization_required_code()]);
        }
        if ($request['context'] === 'edit' && !current_user_can('list_users')) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit users.'), ['status' => rest_authorization_required_code()]);
        }
        if (in_array($request['orderby'], ['email', 'registered_date'], true) && !current_user_can('list_users')) {
            return new WP_Error('rest_forbidden_orderby', __('Sorry, you are not allowed to order users by this parameter.'), ['status' => rest_authorization_required_code()]);
        }
        if ($request['who'] === 'authors' && !$this->minn_edits_a_type()) {
            return new WP_Error('rest_forbidden_who', __('Sorry, you are not allowed to query users by this parameter.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** @internal whether the user may edit posts of some type shown in REST */
    private function minn_edits_a_type(): bool
    {
        foreach (get_post_types(['show_in_rest' => true], 'objects') as $type) {
            if (post_type_supports($type->name, 'author') && current_user_can($type->cap->edit_posts)) {
                return true;
            }
        }
        return false;
    }

    /** The engine's own user list (the query, the totals, the pages), each user shaped here. */
    public function get_items($request)
    {
        $listing = clone $request;
        $listing->set_route('/' . $this->namespace . '/' . $this->rest_base);
        $body = $this->minn_engine('GET', $listing->get_route(), $listing, $engine);
        if (is_wp_error($body)) {
            return $body;
        }
        $users = [];
        foreach ($body as $item) {
            $user = isset($item['id']) ? get_userdata((int) $item['id']) : false;
            if ($user instanceof WP_User) {
                $users[] = $this->prepare_response_for_collection($this->prepare_item_for_response($user, $request));
            }
        }
        $response = rest_ensure_response($users);
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

    protected function get_user($id)
    {
        $error = new WP_Error('rest_user_invalid_id', __('Invalid user ID.'), ['status' => 404]);
        if ((int) $id <= 0) {
            return $error;
        }
        $user = get_userdata((int) $id);
        if (empty($user) || !$user->exists()) {
            return $error;
        }
        return $user;
    }

    public function get_item_permissions_check($request)
    {
        // Oneself, someone who may edit or list users, or a user with published posts.
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        if (get_current_user_id() === $user->ID) {
            return true;
        }
        if ($request['context'] === 'edit' && !current_user_can('edit_user', $user->ID)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit this user.'), ['status' => rest_authorization_required_code()]);
        }
        if (!current_user_can('edit_user', $user->ID) && !current_user_can('list_users') && !count_user_posts($user->ID, get_post_types(['show_in_rest' => true]))) {
            return new WP_Error('rest_user_cannot_view', __('Sorry, you are not allowed to list users.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_item($request)
    {
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        return rest_ensure_response($this->prepare_item_for_response($user, $request));
    }

    public function get_current_item($request)
    {
        $current = get_current_user_id();
        if (empty($current)) {
            return new WP_Error('rest_not_logged_in', __('You are not currently logged in.'), ['status' => 401]);
        }
        return rest_ensure_response($this->prepare_item_for_response(wp_get_current_user(), $request));
    }

    public function create_item_permissions_check($request)
    {
        if (!current_user_can('create_users')) {
            return new WP_Error('rest_cannot_create_user', __('Sorry, you are not allowed to create new users.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item($request)
    {
        // The engine's create route makes the user (its checks, roles, meta and actions); the answer is the user here, a 201 with its location.
        if (!empty($request['id'])) {
            return new WP_Error('rest_user_exists', __('Cannot create existing user.'), ['status' => 400]);
        }
        $created = $this->minn_engine('POST', '/' . $this->namespace . '/' . $this->rest_base, $request);
        if (is_wp_error($created)) {
            return $created;
        }
        $user = get_userdata((int) ($created['id'] ?? 0));
        $request->set_param('context', 'edit');
        $response = rest_ensure_response($this->prepare_item_for_response($user, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $user->ID)));
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        // Roles need promoting users (and are checked before editing); then editing the user.
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        if (!empty($request['roles']) && !current_user_can('promote_user', $user->ID)) {
            return new WP_Error('rest_cannot_edit_roles', __('Sorry, you are not allowed to edit roles of this user.'), ['status' => rest_authorization_required_code()]);
        }
        if (!current_user_can('edit_user', $user->ID)) {
            return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this user.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function update_item($request)
    {
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        $updated = $this->minn_engine($request->get_method() === 'PATCH' ? 'PATCH' : 'PUT', '/' . $this->namespace . '/' . $this->rest_base . '/' . $user->ID, $request);
        if (is_wp_error($updated)) {
            return $updated;
        }
        clean_user_cache($user->ID);
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_userdata($user->ID), $request));
    }

    public function update_current_item_permissions_check($request)
    {
        $request['id'] = get_current_user_id();
        return $this->update_item_permissions_check($request);
    }

    public function update_current_item($request)
    {
        $request['id'] = get_current_user_id();
        return $this->update_item($request);
    }

    public function delete_item_permissions_check($request)
    {
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        if (!current_user_can('delete_user', $user->ID)) {
            return new WP_Error('rest_user_cannot_delete', __('Sorry, you are not allowed to delete this user.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function delete_item($request)
    {
        // Users are never trashed; the reassignment must be another user. The previous view is shaped before the user goes.
        $user = $this->get_user($request['id']);
        if (is_wp_error($user)) {
            return $user;
        }
        if (empty($request['force'])) {
            /* translators: %s: force=true */
            return new WP_Error('rest_trash_not_supported', sprintf(__("Users do not support trashing. Set '%s' to delete."), 'force=true'), ['status' => 501]);
        }
        $reassign = $request['reassign'] === false ? null : (int) $request['reassign'];
        if ($reassign !== null && ($reassign === $user->ID || !get_userdata($reassign))) {
            return new WP_Error('rest_user_invalid_reassign', __('Invalid user ID for reassignment.'), ['status' => 400]);
        }
        $request->set_param('context', 'edit');
        $previous = $this->prepare_item_for_response($user, $request);
        $deleted = $this->minn_engine('DELETE', '/' . $this->namespace . '/' . $this->rest_base . '/' . $user->ID, $request);
        if (is_wp_error($deleted)) {
            return $deleted;
        }
        $response = new WP_REST_Response();
        $response->set_data(['deleted' => true, 'previous' => $previous->get_data()]);
        return $response;
    }

    public function delete_current_item_permissions_check($request)
    {
        $request['id'] = get_current_user_id();
        return $this->delete_item_permissions_check($request);
    }

    public function delete_current_item($request)
    {
        $request['id'] = get_current_user_id();
        return $this->delete_item($request);
    }

    /** A user as the engine shapes it (view or edit; embed narrows the view), cut to the fields asked for, its links when asked for. */
    public function prepare_item_for_response($item, $request)
    {
        $context = !empty($request['context']) ? $request['context'] : 'embed';
        $object = WP_REST_Server::minn_engine_api($request)->userObject();
        $record = UserRecord::fromRow((array) get_userdata($item->ID)->data);
        $data = $context === 'edit' ? $object->edit($record) : $object->view($record);
        $links = (array) ($data['_links'] ?? []);
        unset($data['_links']);
        $fields = $this->get_fields_for_response($request);
        $data = array_filter($data, static fn ($field) => rest_is_field_included((string) $field, $fields), ARRAY_FILTER_USE_KEY);
        $response = rest_ensure_response($this->filter_response_by_context($data, $context));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $expanded = [];
            foreach (RuntimeRoutes::itemResponse(['_links' => $links])->get_links() as $rel => $items) {
                foreach ($items as $link) {
                    $expanded[$rel][] = ['href' => $link['href']] + array_diff_key((array) $link['attributes'], ['targetHints' => true]);
                }
            }
            $response->add_links($expanded);
        }
        return $response;
    }

    protected function prepare_links($user)
    {
        return ['self' => ['href' => rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $user->ID))], 'collection' => ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))]];
    }

    protected function prepare_item_for_database($request)
    {
        // The fields the request sets, in the reference's order; a role list leaves the role to be set after the save.
        $prepared = new stdClass();
        $schema = $this->get_item_schema();
        $map = ['email' => 'user_email', 'username' => 'user_login', 'password' => 'user_pass', 'name' => 'display_name', 'first_name' => 'first_name', 'last_name' => 'last_name', 'nickname' => 'nickname', 'slug' => 'user_nicename', 'description' => 'description', 'url' => 'user_url', 'locale' => 'locale'];
        foreach ($map as $field => $column) {
            if ($field === 'password' && isset($request['id'])) {
                $prepared->ID = (int) $request['id'];
            }
            if (isset($request[$field]) && !empty($schema['properties'][$field])) {
                $prepared->{$column} = $request[$field];
            }
        }
        if (isset($request['roles'])) {
            $prepared->role = false;
        }
        return apply_filters('rest_pre_insert_user', $prepared, $request);
    }

    protected function check_role_update($user_id, $roles)
    {
        // Each role must exist; one's own may not lose editing users; the role must be one the user may give.
        foreach ($roles as $role) {
            $object = wp_roles()->role_objects[$role] ?? null;
            if (!$object) {
                /* translators: %s: Role key. */
                return new WP_Error('rest_user_invalid_role', sprintf(__('The role %s does not exist.'), $role), ['status' => 400]);
            }
            if (get_current_user_id() === (int) $user_id && !$object->has_cap('edit_users')) {
                return new WP_Error('rest_user_invalid_role', __('Sorry, you are not allowed to give users that role.'), ['status' => rest_authorization_required_code()]);
            }
            if (empty(get_editable_roles()[$role])) {
                return new WP_Error('rest_user_invalid_role', __('Sorry, you are not allowed to give users that role.'), ['status' => 403]);
            }
        }
        return true;
    }

    public function check_username($value, $request, $param)
    {
        $username = (string) $value;
        if (!validate_username($username)) {
            return new WP_Error('rest_user_invalid_username', __('This username is invalid because it uses illegal characters. Please enter a valid username.'), ['status' => 400]);
        }
        if (in_array(strtolower($username), array_map('strtolower', (array) apply_filters('illegal_user_logins', [])), true)) {
            return new WP_Error('rest_user_invalid_username', __('Sorry, that username is not allowed.'), ['status' => 400]);
        }
        return $username;
    }

    public function check_user_password($value, $request, $param)
    {
        $password = (string) $value;
        if (empty($password)) {
            return new WP_Error('rest_user_invalid_password', __('Passwords cannot be empty.'), ['status' => 400]);
        }
        if (str_contains($password, '\\')) {
            /* translators: %s: The '\' character. */
            return new WP_Error('rest_user_invalid_password', sprintf(__('Passwords cannot contain the "%s" character.'), '\\'), ['status' => 400]);
        }
        return $password;
    }

    /** The user schema: the fields captured from the reference, the installed locales, the avatar sizes, the meta; then the fields plugins register. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = $this->minn_bound(SchemaData::of('user-properties'));
        $properties['locale']['enum'] = array_merge(['', 'en_US'], get_available_languages());
        $sizes = [];
        foreach (rest_get_avatar_sizes() as $size) {
            /* translators: %d: Avatar image size in pixels. */
            $sizes[$size] = ['description' => sprintf(__('Avatar URL with image size of %d pixels.'), $size), 'type' => 'string', 'format' => 'uri', 'context' => ['embed', 'view', 'edit']];
        }
        $properties['avatar_urls']['properties'] = $sizes;
        $properties['meta'] = $this->meta->get_field_schema();
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'user', 'type' => 'object', 'properties' => $properties];
        return $this->add_additional_fields_schema($this->schema);
    }

    /** @internal a captured definition with its @this:method callbacks bound to this controller */
    private function minn_bound($definition)
    {
        if (is_string($definition) && str_starts_with($definition, '@this:')) {
            return [$this, substr($definition, 6)];
        }
        return is_array($definition) ? array_map(fn ($value) => $this->minn_bound($value), $definition) : $definition;
    }

    /** The engine's user list parameters with the base controller's four, through rest_user_collection_params. */
    public function get_collection_params()
    {
        $params = array_replace(array_map(static fn (array $arg): array => array_diff_key($arg, ['required' => true]), Args::CONTEXT + Minn\Rest\UserCollectionParams::for([])), parent::get_collection_params());
        $params['context']['default'] = 'view';
        return apply_filters('rest_user_collection_params', $params);
    }

    /** @internal one of the engine's own user routes, answering the request in process: its decoded body, or the error it refused with */
    private function minn_engine(string $method, string $route, $request, &$engine = null)
    {
        $call = clone $request;
        $call->set_method($method);
        $call->set_route($route);
        $api = WP_REST_Server::minn_engine_api($call);
        $engine = Minn\Rest\RuntimePrepare::during($call, static fn () => $api->handleEngineOnly($route, $call));
        if ($engine === null) {
            return new WP_Error('rest_no_route', __('No route was found matching the URL and request method.'), ['status' => 404]);
        }
        $body = json_decode($engine->body, true);
        if ($engine->status >= 400) {
            // An error the save itself raised carries no data; over HTTP it reads as a bare 500.
            $data = $body['data'] ?? ['status' => $engine->status];
            return new WP_Error((string) ($body['code'] ?? 'rest_error'), (string) ($body['message'] ?? ''), $data === ['status' => 500] ? null : $data);
        }
        return (array) $body;
    }
}
