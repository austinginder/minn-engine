<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/users/{id}/application-passwords as a class (probe
 * rest-app-passwords-class): a user's application passwords listed, read,
 * created (the new password shown once, chunked), renamed and deleted one
 * by one or all at once, and the one the request signed in with. Each
 * action asks its own meta capability of the user named (a number, or
 * "me" for the caller); the user is looked up first, so a missing one is a
 * 404 for anyone. Answers show the fields of the request's context, none
 * when it has none. The engine answers the route itself; plugins call this
 * class directly.
 */
class WP_REST_Application_Passwords_Controller extends WP_REST_Controller
{
    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'users/(?P<user_id>(?:[\d]+|me))/application-passwords';
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema()],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_items'], 'permission_callback' => [$this, 'delete_items_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/introspect', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_current_item'], 'permission_callback' => [$this, 'get_current_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<uuid>[\w\-]+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check']],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        return $this->minn_may($request, 'list_app_passwords', null, 'rest_cannot_list_application_passwords', __('Sorry, you are not allowed to list application passwords for this user.'));
    }

    public function get_items($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $response = [];
        foreach (WP_Application_Passwords::get_user_application_passwords($user->ID) as $password) {
            $response[] = $this->prepare_response_for_collection($this->prepare_item_for_response($password, $request));
        }
        return new WP_REST_Response($response);
    }

    public function get_item_permissions_check($request)
    {
        return $this->minn_may($request, 'read_app_password', $request['uuid'], 'rest_cannot_read_application_password', __('Sorry, you are not allowed to read this application password.'));
    }

    public function get_item($request)
    {
        $password = $this->get_application_password($request);
        return is_wp_error($password) ? $password : $this->prepare_item_for_response($password, $request);
    }

    public function create_item_permissions_check($request)
    {
        return $this->minn_may($request, 'create_app_password', null, 'rest_cannot_create_application_passwords', __('Sorry, you are not allowed to create application passwords for this user.'));
    }

    /** A new password, shown once (chunked) in an edit-context answer with 201. */
    public function create_item($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $prepared = $this->prepare_item_for_database($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $created = WP_Application_Passwords::create_new_application_password($user->ID, wp_slash((array) $prepared));
        if (is_wp_error($created)) {
            return $created;
        }
        $item = WP_Application_Passwords::get_user_application_password($user->ID, $created[1]['uuid']);
        $item['new_password'] = WP_Application_Passwords::chunk_password($created[0]);
        $fields = $this->update_additional_fields_for_object($item, $request);
        if (is_wp_error($fields)) {
            return $fields;
        }
        do_action('rest_after_insert_application_password', $item, $request, true);
        $request->set_param('context', 'edit');
        $response = $this->prepare_item_for_response($item, $request);
        $response->set_status(201);
        $response->header('Location', $response->get_links()['self'][0]['href']);
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        return $this->minn_may($request, 'edit_app_password', $request['uuid'], 'rest_cannot_edit_application_password', __('Sorry, you are not allowed to edit this application password.'));
    }

    public function update_item($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $item = $this->get_application_password($request);
        if (is_wp_error($item)) {
            return $item;
        }
        $prepared = $this->prepare_item_for_database($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        $saved = WP_Application_Passwords::update_application_password($user->ID, $item['uuid'], wp_slash((array) $prepared));
        if (is_wp_error($saved)) {
            return $saved;
        }
        $fields = $this->update_additional_fields_for_object($item, $request);
        if (is_wp_error($fields)) {
            return $fields;
        }
        $item = WP_Application_Passwords::get_user_application_password($user->ID, $item['uuid']);
        do_action('rest_after_insert_application_password', $item, $request, false);
        $request->set_param('context', 'edit');
        return $this->prepare_item_for_response($item, $request);
    }

    public function delete_items_permissions_check($request)
    {
        return $this->minn_may($request, 'delete_app_passwords', null, 'rest_cannot_delete_application_passwords', __('Sorry, you are not allowed to delete application passwords for this user.'));
    }

    public function delete_items($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $deleted = WP_Application_Passwords::delete_all_application_passwords($user->ID);
        return is_wp_error($deleted) ? $deleted : new WP_REST_Response(['deleted' => true, 'count' => $deleted]);
    }

    public function delete_item_permissions_check($request)
    {
        return $this->minn_may($request, 'delete_app_password', $request['uuid'], 'rest_cannot_delete_application_password', __('Sorry, you are not allowed to delete this application password.'));
    }

    /** The password deleted, answered with its edit-context form as it was. */
    public function delete_item($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $password = $this->get_application_password($request);
        if (is_wp_error($password)) {
            return $password;
        }
        $request->set_param('context', 'edit');
        $previous = $this->prepare_item_for_response($password, $request);
        $deleted = WP_Application_Passwords::delete_application_password($user->ID, $password['uuid']);
        return is_wp_error($deleted) ? $deleted : new WP_REST_Response(['deleted' => true, 'previous' => $previous->get_data()]);
    }

    /** Only the signed-in user may introspect the password they signed in with. */
    public function get_current_item_permissions_check($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        if (get_current_user_id() !== $user->ID) {
            return new WP_Error('rest_cannot_introspect_app_password_for_non_authenticated_user', __('The authenticated application password can only be introspected for the current user.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_current_item($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $uuid = rest_get_authenticated_app_password();
        if (!$uuid) {
            return new WP_Error('rest_no_authenticated_app_password', __('Cannot introspect application password.'), ['status' => 404]);
        }
        $password = WP_Application_Passwords::get_user_application_password($user->ID, $uuid);
        if (!$password) {
            return new WP_Error('rest_application_password_not_found', __('Application password not found.'), ['status' => 404]);
        }
        return $this->prepare_item_for_response($password, $request);
    }

    /** Deprecated since 5.7.0: whether the caller may edit the user named. */
    protected function do_permissions_check($request)
    {
        _deprecated_function(__METHOD__, '5.7.0');
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        if (!current_user_can('edit_user', $user->ID)) {
            return new WP_Error('rest_cannot_manage_application_passwords', __('Sorry, you are not allowed to manage application passwords for this user.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** The name, and the app id for a new password, through rest_pre_insert_application_password. */
    protected function prepare_item_for_database($request)
    {
        $prepared = (object) ['name' => $request['name']];
        if ($request['app_id'] && !$request['uuid']) {
            $prepared->app_id = $request['app_id'];
        }
        return apply_filters('rest_pre_insert_application_password', $prepared, $request);
    }

    /** A password as REST shows it, its times site-local, the new password when there is one, filtered by the request's context (none: no fields). */
    public function prepare_item_for_response($item, $request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $fields = $this->get_fields_for_response($request);
        $when = static fn ($unix) => $unix ? get_date_from_gmt(gmdate('Y-m-d H:i:s', (int) $unix), 'Y-m-d\TH:i:s') : null;
        $prepared = ['uuid' => $item['uuid'], 'app_id' => empty($item['app_id']) ? '' : $item['app_id'], 'name' => $item['name'], 'created' => $when($item['created']), 'last_used' => $when($item['last_used'] ?? null), 'last_ip' => !empty($item['last_ip']) ? $item['last_ip'] : null];
        if (isset($item['new_password'])) {
            $prepared['password'] = $item['new_password'];
        }
        $response = new WP_REST_Response($this->filter_response_by_context($this->add_additional_fields_to_object($prepared, $request), $request['context']));
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $response->add_links($this->prepare_links($user, $item));
        }
        return apply_filters('rest_prepare_application_password', $response, $item, $request);
    }

    protected function prepare_links($user, $item)
    {
        return ['self' => ['href' => rest_url(sprintf('%s/users/%d/application-passwords/%s', $this->namespace, $user->ID, $item['uuid']))]];
    }

    /** The user a request names, "me" being the caller; refusals for passwords off, nobody signed in, or no such user. */
    protected function get_user($request)
    {
        if (!wp_is_application_passwords_available()) {
            return new WP_Error('application_passwords_disabled', __('Application passwords are not available.'), ['status' => 501]);
        }
        $invalid = new WP_Error('rest_user_invalid_id', __('Invalid user ID.'), ['status' => 404]);
        if ($request['user_id'] === 'me') {
            if (!is_user_logged_in()) {
                return new WP_Error('rest_not_logged_in', __('You are not currently logged in.'), ['status' => 401]);
            }
            $user = wp_get_current_user();
        } else {
            $id = (int) $request['user_id'];
            $user = $id > 0 ? get_userdata($id) : false;
        }
        if (empty($user) || !$user->exists()) {
            return $invalid;
        }
        if (!wp_is_application_passwords_available_for_user($user)) {
            return new WP_Error('application_passwords_disabled_for_user', __('The provided password is an invalid application password.'), ['status' => 501]);
        }
        return $user;
    }

    protected function get_application_password($request)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        $password = WP_Application_Passwords::get_user_application_password($user->ID, $request['uuid']);
        return $password ?: new WP_Error('rest_application_password_not_found', __('Application password not found.'), ['status' => 404]);
    }

    public function get_collection_params()
    {
        return ['context' => $this->get_context_param(['default' => 'view'])];
    }

    public function get_item_schema()
    {
        $this->schema ??= SchemaData::of('application-password');
        return $this->add_additional_fields_schema($this->schema);
    }

    /** The user named, then a meta capability on them (with the password, when there is one); refused with the authorization code. */
    private function minn_may($request, string $capability, $uuid, string $code, string $message)
    {
        $user = $this->get_user($request);
        if (is_wp_error($user)) {
            return $user;
        }
        if (!($uuid === null ? current_user_can($capability, $user->ID) : current_user_can($capability, $user->ID, $uuid))) {
            return new WP_Error($code, $message, ['status' => rest_authorization_required_code()]);
        }
        return true;
    }
}
