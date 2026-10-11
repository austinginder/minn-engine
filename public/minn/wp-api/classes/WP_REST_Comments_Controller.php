<?php

use Minn\Content\CommentRecord;
use Minn\Http\Args;
use Minn\Rest\Context;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2 comments as a class (probe rest-comments-controller). The engine
 * answers the routes itself; plugins construct this class or call it
 * directly. A comment's shape is the engine's CommentObject; lists and
 * writes run the engine's own routes, each comment shaped here.
 */
class WP_REST_Comments_Controller extends WP_REST_Controller
{
    protected $meta = NULL;

    public function __construct()
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'comments';
        $this->meta = new WP_REST_Comment_Meta_Fields();
    }

    public function register_routes()
    {
        $password = ['description' => __('The password for the parent post of the comment (if the post is password protected).'), 'type' => 'string'];
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['id' => ['description' => __('Unique identifier for the comment.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view']), 'password' => $password]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Whether to bypass Trash and force deletion.')], 'password' => $password]],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permissions_check($request)
    {
        // The posts asked about must be readable; the edit context needs moderating; without editing posts, the private parameters are refused by name.
        foreach (!empty($request['post']) ? (array) $request['post'] : [] as $post_id) {
            $post = get_post($post_id);
            if (!empty($post_id) && $post && !$this->check_read_post_permission($post, $request)) {
                return new WP_Error('rest_cannot_read_post', __('Sorry, you are not allowed to read the post for this comment.'), ['status' => rest_authorization_required_code()]);
            }
            if ((int) $post_id === 0 && !current_user_can('moderate_comments')) {
                return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to read comments without a post.'), ['status' => rest_authorization_required_code()]);
            }
        }
        if ($request['context'] === 'edit' && !current_user_can('moderate_comments')) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit comments.'), ['status' => rest_authorization_required_code()]);
        }
        if (!current_user_can('edit_posts')) {
            $forbidden = [];
            foreach (['author', 'author_exclude', 'author_email', 'type', 'status'] as $param) {
                $set = match ($param) {
                    'status' => $request[$param] !== 'approve',
                    'type' => $request[$param] !== 'comment',
                    default => !empty($request[$param]),
                };
                if ($set) {
                    $forbidden[] = $param;
                }
            }
            if ($forbidden !== []) {
                /* translators: %s: List of forbidden parameters. */
                return new WP_Error('rest_forbidden_param', sprintf(__('Query parameter not permitted: %s'), implode(', ', $forbidden)), ['status' => rest_authorization_required_code()]);
            }
        }
        return true;
    }

    /** The engine's own comment list (the query, the totals, the pages), each comment shaped here. */
    public function get_items($request)
    {
        $listing = clone $request;
        $listing->set_route('/' . $this->namespace . '/' . $this->rest_base);
        $body = $this->minn_engine('GET', $listing->get_route(), $listing, $engine);
        if (is_wp_error($body)) {
            return $body;
        }
        $comments = [];
        foreach ($body as $item) {
            $comment = isset($item['id']) ? get_comment((int) $item['id']) : null;
            if ($comment instanceof WP_Comment) {
                $comments[] = $this->prepare_response_for_collection($this->prepare_item_for_response($comment, $request));
            }
        }
        $response = rest_ensure_response($comments);
        // The comment list sends its totals as strings, as the reference's does.
        foreach (['X-WP-Total', 'X-WP-TotalPages', 'Link'] as $header) {
            if (isset($engine->headers[$header])) {
                $response->header($header, $engine->headers[$header]);
            }
        }
        return $response;
    }

    protected function get_comment($id)
    {
        $error = new WP_Error('rest_comment_invalid_id', __('Invalid comment ID.'), ['status' => 404]);
        $comment = (int) $id > 0 ? get_comment((int) $id) : null;
        if (empty($comment)) {
            return $error;
        }
        if (!empty($comment->comment_post_ID) && empty(get_post((int) $comment->comment_post_ID))) {
            return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 404]);
        }
        return $comment;
    }

    public function get_item_permissions_check($request)
    {
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        if ($request['context'] === 'edit' && !current_user_can('moderate_comments')) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit comments.'), ['status' => rest_authorization_required_code()]);
        }
        if (!$this->check_read_permission($comment, $request)) {
            return new WP_Error('rest_cannot_read', __('Sorry, you are not allowed to read this comment.'), ['status' => rest_authorization_required_code()]);
        }
        $post = get_post($comment->comment_post_ID);
        if ($post && !$this->check_read_post_permission($post, $request)) {
            return new WP_Error('rest_cannot_read_post', __('Sorry, you are not allowed to read the post for this comment.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_item($request)
    {
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        return rest_ensure_response($this->prepare_item_for_response($comment, $request));
    }

    public function create_item_permissions_check($request)
    {
        // Signed in (or anonymous comments allowed); only moderators set another author, the IP or the status; a readable, open, live post.
        if (!is_user_logged_in() && (get_option('comment_registration') || !apply_filters('rest_allow_anonymous_comments', false, $request))) {
            return new WP_Error('rest_comment_login_required', __('Sorry, you must be logged in to comment.'), ['status' => 401]);
        }
        $moderates = current_user_can('moderate_comments');
        if (isset($request['author']) && get_current_user_id() !== $request['author'] && !$moderates) {
            return $this->minn_not_allowed('rest_comment_invalid_author', 'author');
        }
        if (isset($request['author_ip']) && !$moderates && (empty($_SERVER['REMOTE_ADDR']) || $request['author_ip'] !== $_SERVER['REMOTE_ADDR'])) {
            return $this->minn_not_allowed('rest_comment_invalid_author_ip', 'author_ip');
        }
        if (isset($request['status']) && !$moderates) {
            return $this->minn_not_allowed('rest_comment_invalid_status', 'status');
        }
        $post = empty($request['post']) ? null : get_post((int) $request['post']);
        if (!$post) {
            return new WP_Error('rest_comment_invalid_post_id', __('Sorry, you are not allowed to create this comment without a post.'), ['status' => 403]);
        }
        if (in_array($post->post_status, ['draft', 'trash'], true)) {
            return new WP_Error('rest_comment_' . $post->post_status . '_post', __('Sorry, you are not allowed to create a comment on this post.'), ['status' => 403]);
        }
        if (!$this->check_read_post_permission($post, $request)) {
            return new WP_Error('rest_cannot_read_post', __('Sorry, you are not allowed to read the post for this comment.'), ['status' => rest_authorization_required_code()]);
        }
        if (!comments_open($post->ID)) {
            return new WP_Error('rest_comment_closed', __('Sorry, comments are closed for this item.'), ['status' => 403]);
        }
        return true;
    }

    /** @internal the refusal for a field only moderators may set */
    private function minn_not_allowed(string $code, string $field): WP_Error
    {
        /* translators: %s: Request parameter. */
        return new WP_Error($code, sprintf(__("Sorry, you are not allowed to edit '%s' for comments."), $field), ['status' => rest_authorization_required_code()]);
    }

    public function create_item($request)
    {
        // The engine's create route makes the comment (its checks, flood and duplicate tests, meta and actions); the answer is the comment here, a 201 with its location.
        if (!empty($request['id'])) {
            return new WP_Error('rest_comment_exists', __('Cannot create existing comment.'), ['status' => 400]);
        }
        $created = $this->minn_engine('POST', '/' . $this->namespace . '/' . $this->rest_base, $request);
        if (is_wp_error($created)) {
            return $created;
        }
        $comment = get_comment((int) ($created['id'] ?? 0));
        $request->set_param('context', 'edit');
        $response = rest_ensure_response($this->prepare_item_for_response($comment, $request));
        $response->set_status(201);
        $response->header('Location', rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $comment->comment_ID)));
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        if (!$this->check_edit_permission($comment)) {
            return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this comment.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function update_item($request)
    {
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        $updated = $this->minn_engine($request->get_method() === 'PATCH' ? 'PATCH' : 'PUT', '/' . $this->namespace . '/' . $this->rest_base . '/' . $comment->comment_ID, $request);
        if (is_wp_error($updated)) {
            return $updated;
        }
        clean_comment_cache($comment->comment_ID);
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_comment($comment->comment_ID), $request));
    }

    public function delete_item_permissions_check($request)
    {
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        if (!$this->check_edit_permission($comment)) {
            return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete this comment.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function delete_item($request)
    {
        // Trashed, the comment in its edit view; deleted for good, the view it had before as previous.
        $comment = $this->get_comment($request['id']);
        if (is_wp_error($comment)) {
            return $comment;
        }
        $request->set_param('context', 'edit');
        $previous = !empty($request['force']) ? $this->prepare_item_for_response($comment, $request) : null;
        $deleted = $this->minn_engine('DELETE', '/' . $this->namespace . '/' . $this->rest_base . '/' . $comment->comment_ID, $request);
        if (is_wp_error($deleted)) {
            return $deleted;
        }
        if ($previous !== null) {
            $response = new WP_REST_Response();
            $response->set_data(['deleted' => true, 'previous' => $previous->get_data()]);
            return $response;
        }
        clean_comment_cache($comment->comment_ID);
        return rest_ensure_response($this->prepare_item_for_response(get_comment($comment->comment_ID), $request));
    }

    /** A comment as the engine shapes it, cut to the fields and context asked for, its links on the response when asked for. */
    public function prepare_item_for_response($item, $request)
    {
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $object = WP_REST_Server::minn_engine_api($request)->commentObject();
        $data = $object->build(CommentRecord::fromRow((array) get_comment($item->comment_ID, ARRAY_A)), $context === 'edit' ? Context::Edit : Context::View);
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

    protected function prepare_links($comment)
    {
        // The engine's links for the comment, one link a rel.
        $object = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $comment->comment_ID))->commentObject();
        $built = $object->build(CommentRecord::fromRow((array) get_comment($comment->comment_ID, ARRAY_A)), Context::View);
        return array_map(static fn (array $items) => $items[0], self::minn_links_for_response((array) ($built['_links'] ?? [])));
    }

    protected function normalize_query_param($query_param)
    {
        return match ($query_param) {
            'id' => 'comment_ID',
            'post' => 'comment_post_ID',
            'include' => 'comment__in',
            default => 'comment_' . $query_param,
        };
    }

    protected function prepare_status_response($comment_approved)
    {
        return match ((string) $comment_approved) {
            'hold', '0' => 'hold',
            'approve', '1' => 'approved',
            default => $comment_approved,
        };
    }

    protected function prepare_item_for_database($request)
    {
        // The fields the request sets, in the reference's order; an author's own name, email and URL unless the request names them; the IP a moderator gives, else the caller's.
        $prepared = [];
        if (isset($request['content'])) {
            $prepared['comment_content'] = is_string($request['content']) ? $request['content'] : ($request['content']['raw'] ?? '');
        }
        foreach (['post' => 'comment_post_ID', 'parent' => 'comment_parent'] as $field => $column) {
            if (isset($request[$field])) {
                $prepared[$column] = (int) $request[$field];
            }
        }
        if (isset($request['author'])) {
            $user = new WP_User($request['author']);
            if (!$user->exists()) {
                return new WP_Error('rest_comment_author_invalid', __('Invalid comment author ID.'), ['status' => 400]);
            }
            $prepared += ['user_id' => $user->ID, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email, 'comment_author_url' => $user->user_url];
        }
        foreach (['author_name' => 'comment_author', 'author_email' => 'comment_author_email', 'author_url' => 'comment_author_url'] as $field => $column) {
            if (isset($request[$field])) {
                $prepared[$column] = $request[$field];
            }
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $prepared['comment_author_IP'] = isset($request['author_ip']) && current_user_can('moderate_comments') ? $request['author_ip'] : ($remote !== '' && rest_is_ip_address($remote) ? $remote : '127.0.0.1');
        if (!empty($request['author_user_agent'])) {
            $prepared['comment_agent'] = $request['author_user_agent'];
        }
        $dates = !empty($request['date']) ? rest_get_date_with_gmt($request['date']) : (!empty($request['date_gmt']) ? rest_get_date_with_gmt($request['date_gmt'], true) : null);
        if (!empty($dates)) {
            [$prepared['comment_date'], $prepared['comment_date_gmt']] = $dates;
        }
        return apply_filters('rest_preprocess_comment', $prepared, $request);
    }

    /** The comment schema: the fields captured from the reference, the author avatars in the sizes REST serves, the meta; then the fields plugins register. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = array_map(fn ($definition) => $this->minn_bound($definition), SchemaData::of('comment-properties'));
        $sizes = [];
        foreach (rest_get_avatar_sizes() as $size) {
            /* translators: %d: Avatar image size in pixels. */
            $sizes[$size] = ['description' => sprintf(__('Avatar URL with image size of %d pixels.'), $size), 'type' => 'string', 'format' => 'uri', 'context' => ['embed', 'view', 'edit']];
        }
        $properties['author_avatar_urls'] = ['description' => __('Avatar URLs for the comment author.'), 'type' => 'object', 'context' => ['view', 'edit', 'embed'], 'readonly' => true, 'properties' => $sizes];
        $properties['meta'] = $this->meta->get_field_schema();
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'comment', 'type' => 'object', 'properties' => $properties];
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

    /** The engine's comment list parameters with the base controller's four, the status and type sanitized as keys, through rest_comment_collection_params. */
    public function get_collection_params()
    {
        $params = array_replace(['context' => null] + array_map(static fn (array $arg): array => array_diff_key($arg, ['required' => true]), Args::COMMENTS), parent::get_collection_params());
        $params['context']['default'] = 'view';
        foreach (['status', 'type'] as $key) {
            $params[$key] = ['default' => $params[$key]['default'], 'description' => $params[$key]['description'], 'sanitize_callback' => 'sanitize_key', 'type' => $params[$key]['type'], 'validate_callback' => 'rest_validate_request_arg'];
        }
        return apply_filters('rest_comment_collection_params', $params);
    }

    protected function handle_status_param($new_status, $comment_id)
    {
        // A change of status, through the comment functions; the same status, or an unknown one, changes nothing.
        if ($new_status === wp_get_comment_status($comment_id)) {
            return false;
        }
        return match ((string) $new_status) {
            'approved', 'approve', '1' => wp_set_comment_status($comment_id, 'approve'),
            'hold', '0' => wp_set_comment_status($comment_id, 'hold'),
            'spam' => wp_spam_comment($comment_id),
            'unspam' => wp_unspam_comment($comment_id),
            'trash' => wp_trash_comment($comment_id),
            'untrash' => wp_untrash_comment($comment_id),
            default => false,
        };
    }

    protected function check_read_post_permission($post, $request)
    {
        // The post is readable by its own type's controller.
        $type = get_post_type_object($post->post_type);
        $controller = $type ? $type->get_rest_controller() : null;
        if (!$controller instanceof WP_REST_Posts_Controller) {
            $controller = new WP_REST_Posts_Controller($post->post_type);
        }
        return $controller->check_read_permission($post);
    }

    protected function check_read_permission($comment, $request)
    {
        // An approved comment on a readable post is anyone's; otherwise one's own, or one the user may edit.
        $post = !empty($comment->comment_post_ID) ? get_post($comment->comment_post_ID) : null;
        if ($post && (int) $comment->comment_approved === 1 && $this->check_read_post_permission($post, $request)) {
            return true;
        }
        if (get_current_user_id() === 0) {
            return false;
        }
        if (empty($comment->comment_post_ID) && !current_user_can('moderate_comments')) {
            return false;
        }
        if (!empty($comment->user_id) && get_current_user_id() === (int) $comment->user_id) {
            return true;
        }
        return current_user_can('edit_comment', $comment->comment_ID);
    }

    protected function check_edit_permission($comment)
    {
        if (get_current_user_id() === 0) {
            return false;
        }
        return current_user_can('moderate_comments') || current_user_can('edit_comment', $comment->comment_ID);
    }

    public function check_comment_author_email($value, $request, $param)
    {
        // An empty email passes; any other is checked against the route's arguments.
        $email = (string) $value;
        if ($email === '') {
            return $email;
        }
        $valid = rest_validate_request_arg($email, $request, $param);
        return is_wp_error($valid) ? $valid : $email;
    }

    protected function check_is_comment_content_allowed($prepared_comment)
    {
        $check = wp_parse_args($prepared_comment, ['comment_content' => '']);
        if (apply_filters('allow_empty_comment', false, $check)) {
            return true;
        }
        return $check['comment_content'] !== '';
    }

    protected function check_target_post_permission($post_id, $request, $is_note = false)
    {
        // Moving a comment to a post is a moderator's, and the post must exist.
        if (!current_user_can('moderate_comments')) {
            return new WP_Error('rest_cannot_edit', __('Sorry, you are not allowed to edit this comment.'), ['status' => rest_authorization_required_code()]);
        }
        if (!get_post((int) $post_id)) {
            return new WP_Error('rest_comment_invalid_post_id', __('Sorry, you are not allowed to create this comment without a post.'), ['status' => 403]);
        }
        return true;
    }

    private function check_post_type_supports_notes($post_type)
    {
        \Minn\Runtime\PlaceholderTrace::hit('WP_REST_Comments_Controller::check_post_type_supports_notes');
        return null;
    }

    /** @internal one of the engine's own comment routes, answering the request in process: its decoded body, or the error it refused with */
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
            return new WP_Error((string) ($body['code'] ?? 'rest_error'), (string) ($body['message'] ?? ''), $body['data'] ?? ['status' => $engine->status]);
        }
        return (array) $body;
    }
}
