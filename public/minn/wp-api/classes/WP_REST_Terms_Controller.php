<?php

use Minn\Content\TermRecord;
use Minn\Http\Args;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * A taxonomy's wp/v2 endpoints as a class (probe rest-terms-controller).
 * The engine answers the core taxonomies' routes itself; plugins construct
 * this class for a taxonomy, extend it as a rest_controller_class, or call
 * it directly. A term's shape is the engine's TermObject; lists and writes
 * run the engine's own routes, each term shaped here.
 */
class WP_REST_Terms_Controller extends WP_REST_Controller
{
    protected $taxonomy = NULL;
    protected $meta = NULL;
    protected $sort_column = NULL;
    protected $total_terms = NULL;
    protected $allow_batch = ['v1' => true];

    public function __construct($taxonomy)
    {
        $this->taxonomy = $taxonomy;
        $object = get_taxonomy($taxonomy);
        $this->rest_base = !empty($object->rest_base) ? $object->rest_base : $object->name;
        $this->namespace = !empty($object->rest_namespace) ? $object->rest_namespace : 'wp/v2';
        $this->meta = new WP_REST_Term_Meta_Fields($taxonomy);
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permissions_check'], 'args' => $this->get_collection_params()],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'create_item'], 'permission_callback' => [$this, 'create_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE)],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'args' => ['id' => ['description' => __('Unique identifier for the term.'), 'type' => 'integer']],
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_item'], 'permission_callback' => [$this, 'get_item_permissions_check'], 'args' => ['context' => $this->get_context_param(['default' => 'view'])]],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_item'], 'permission_callback' => [$this, 'update_item_permissions_check'], 'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE)],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_item'], 'permission_callback' => [$this, 'delete_item_permissions_check'], 'args' => ['force' => ['type' => 'boolean', 'default' => false, 'description' => __('Required to be true, as terms do not support trashing.')]]],
            'allow_batch' => $this->allow_batch,
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function check_read_terms_permission_for_post($post, $request)
    {
        // A post's terms are readable when the post is, by its own type's controller.
        $type = get_post_type_object($post->post_type);
        if (!$type) {
            return false;
        }
        $controller = $type->get_rest_controller();
        if (!$controller instanceof WP_REST_Posts_Controller) {
            $controller = new WP_REST_Posts_Controller($post->post_type);
        }
        return $controller->check_read_permission($post);
    }

    public function get_items_permissions_check($request)
    {
        if (!$this->check_is_taxonomy_allowed($this->taxonomy)) {
            return false;
        }
        if (!empty($request['post'])) {
            $post = get_post((int) $request['post']);
            if (!$post) {
                return new WP_Error('rest_post_invalid_id', __('Invalid post ID.'), ['status' => 400]);
            }
            if (!$this->check_read_terms_permission_for_post($post, $request)) {
                return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to view terms for this post.'), ['status' => rest_authorization_required_code()]);
            }
        }
        if ($request['context'] === 'edit' && !current_user_can(get_taxonomy($this->taxonomy)->cap->edit_terms)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit terms in this taxonomy.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** The engine's own list for the taxonomy (the query, the totals, the pages), each term shaped here. */
    public function get_items($request)
    {
        $listing = clone $request;
        $listing->set_route('/' . $this->namespace . '/' . $this->rest_base);
        $body = $this->minn_engine('GET', $listing->get_route(), $listing, $engine);
        if (is_wp_error($body)) {
            return $body;
        }
        $terms = [];
        foreach ($body as $item) {
            $term = isset($item['id']) ? get_term((int) $item['id'], $this->taxonomy) : null;
            if ($term instanceof WP_Term) {
                $terms[] = $this->prepare_response_for_collection($this->prepare_item_for_response($term, $request));
            }
        }
        $response = rest_ensure_response($terms);
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

    protected function get_term($id)
    {
        $error = new WP_Error('rest_term_invalid', __('Term does not exist.'), ['status' => 404]);
        if (!$this->check_is_taxonomy_allowed($this->taxonomy) || (int) $id <= 0) {
            return $error;
        }
        $term = get_term((int) $id, $this->taxonomy);
        if (empty($term) || $term->taxonomy !== $this->taxonomy) {
            return $error;
        }
        return $term;
    }

    public function get_item_permissions_check($request)
    {
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        if ($request['context'] === 'edit' && !current_user_can('edit_term', $term->term_id)) {
            return new WP_Error('rest_forbidden_context', __('Sorry, you are not allowed to edit this term.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function get_item($request)
    {
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        return rest_ensure_response($this->prepare_item_for_response($term, $request));
    }

    public function create_item_permissions_check($request)
    {
        // A hierarchical taxonomy needs editing its terms, a flat one assigning them.
        if (!$this->check_is_taxonomy_allowed($this->taxonomy)) {
            return false;
        }
        $object = get_taxonomy($this->taxonomy);
        if (!current_user_can(is_taxonomy_hierarchical($this->taxonomy) ? $object->cap->edit_terms : $object->cap->assign_terms)) {
            return new WP_Error('rest_cannot_create', __('Sorry, you are not allowed to create terms in this taxonomy.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function create_item($request)
    {
        // The engine's create route makes the term (its checks, meta and actions); the answer is the term here, a 201 with its location.
        $created = $this->minn_engine('POST', '/' . $this->namespace . '/' . $this->rest_base, $request);
        if (is_wp_error($created)) {
            return $created;
        }
        $term = get_term((int) ($created['id'] ?? 0), $this->taxonomy);
        $request->set_param('context', 'edit');
        $response = rest_ensure_response($this->prepare_item_for_response($term, $request));
        $response->set_status(201);
        $response->header('Location', rest_url($this->namespace . '/' . $this->rest_base . '/' . $term->term_id));
        return $response;
    }

    public function update_item_permissions_check($request)
    {
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        if (!current_user_can('edit_term', $term->term_id)) {
            return new WP_Error('rest_cannot_update', __('Sorry, you are not allowed to edit this term.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function update_item($request)
    {
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        $updated = $this->minn_engine($request->get_method() === 'PATCH' ? 'PATCH' : 'PUT', '/' . $this->namespace . '/' . $this->rest_base . '/' . $term->term_id, $request);
        if (is_wp_error($updated)) {
            return $updated;
        }
        $request->set_param('context', 'edit');
        return rest_ensure_response($this->prepare_item_for_response(get_term($term->term_id, $this->taxonomy), $request));
    }

    public function delete_item_permissions_check($request)
    {
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        if (!current_user_can('delete_term', $term->term_id)) {
            return new WP_Error('rest_cannot_delete', __('Sorry, you are not allowed to delete this term.'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    public function delete_item($request)
    {
        // Terms are never trashed: a forced delete answers the term as it was, shaped before it goes.
        $term = $this->get_term($request['id']);
        if (is_wp_error($term)) {
            return $term;
        }
        $request->set_param('context', 'view');
        $previous = !empty($request['force']) ? $this->prepare_item_for_response($term, $request) : null;
        $deleted = $this->minn_engine('DELETE', '/' . $this->namespace . '/' . $this->rest_base . '/' . $term->term_id, $request);
        if (is_wp_error($deleted)) {
            return $deleted;
        }
        $response = new WP_REST_Response();
        $response->set_data(['deleted' => true, 'previous' => $previous ? $previous->get_data() : $deleted['previous'] ?? null]);
        return $response;
    }

    public function prepare_item_for_database($request)
    {
        // The name, slug, description and (in a hierarchical taxonomy) the parent the request sets, a missing parent as none; then rest_pre_insert_{taxonomy}.
        $prepared = new stdClass();
        $schema = $this->get_item_schema();
        foreach (['name', 'slug', 'description'] as $field) {
            if (isset($request[$field]) && !empty($schema['properties'][$field])) {
                $prepared->{$field} = $request[$field];
            }
        }
        if (isset($request['parent']) && !empty($schema['properties']['parent'])) {
            $parent = (int) $request['parent'] ? get_term((int) $request['parent'], $this->taxonomy) : null;
            $prepared->parent = $parent instanceof WP_Term ? $parent->term_id : 0;
        }
        return apply_filters("rest_pre_insert_{$this->taxonomy}", $prepared, $request);
    }

    /** A term as the engine shapes it, cut to the fields and context asked for, its links on the response when asked for. */
    public function prepare_item_for_response($item, $request)
    {
        $context = !empty($request['context']) ? $request['context'] : 'view';
        $data = WP_REST_Server::minn_engine_api($request)->termObject()->view(TermRecord::fromRow((array) get_term($item->term_id, $this->taxonomy, ARRAY_A)), $this->rest_base);
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

    protected function prepare_links($term)
    {
        // The engine's links for the term: one link a rel, the post type links a list.
        $object = WP_REST_Server::minn_engine_api(new WP_REST_Request('GET', '/' . $this->namespace . '/' . $this->rest_base . '/' . $term->term_id))->termObject();
        $view = $object->view(TermRecord::fromRow((array) get_term($term->term_id, $this->taxonomy, ARRAY_A)), $this->rest_base);
        $links = [];
        foreach (self::minn_links_for_response((array) ($view['_links'] ?? [])) as $rel => $items) {
            $links[$rel] = $rel === 'https://api.w.org/post_type' ? $items : $items[0];
        }
        return $links;
    }

    /** The taxonomy's schema: the term fields (captured from the reference), the parent in a hierarchical taxonomy, the meta; then the fields plugins register. */
    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $properties = [];
        foreach (SchemaData::of('term-properties') as $field => $definition) {
            if ($field !== 'parent' || is_taxonomy_hierarchical($this->taxonomy)) {
                $properties[$field] = $this->minn_bound($definition);
            }
        }
        $properties['taxonomy']['enum'] = [$this->taxonomy];
        $properties['meta'] = $this->meta->get_field_schema();
        $this->schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'post_tag' === $this->taxonomy ? 'tag' : $this->taxonomy, 'type' => 'object', 'properties' => $properties];
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

    /** The engine's term list parameters (a parent for a hierarchical taxonomy, an offset for a flat one) with the base controller's four, through rest_{taxonomy}_collection_params. */
    public function get_collection_params()
    {
        $terms = array_diff_key(Args::TERMS, [is_taxonomy_hierarchical($this->taxonomy) ? 'offset' : 'parent' => true]);
        $params = array_replace(['context' => null] + array_map(static fn (array $arg): array => array_diff_key($arg, ['required' => true]), $terms), parent::get_collection_params());
        $params['context']['default'] = 'view';
        return apply_filters("rest_{$this->taxonomy}_collection_params", $params, get_taxonomy($this->taxonomy));
    }

    protected function check_is_taxonomy_allowed($taxonomy)
    {
        $object = get_taxonomy($taxonomy);
        return $object && !empty($object->show_in_rest);
    }

    /** @internal one of the engine's own routes for this taxonomy, answering the request in process: its decoded body, or the error it refused with */
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
