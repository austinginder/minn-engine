<?php

use Minn\Rest\SchemaData;

require_once __DIR__ . '/WP_REST_Controller.php';

/**
 * wp/v2/search as a class (probe rest-registry-controllers): one search
 * handler per object type (post, term, post-format), the request's type
 * choosing one. Results are the handler's ids, each prepared by it and
 * linked back to the search, with the total and pages as headers and Link
 * rel prev and next built from the request's own query. The schema's type
 * and subtype choices come from the handlers. The engine answers the route
 * itself; plugins call this class directly.
 */
class WP_REST_Search_Controller extends WP_REST_Controller
{
    const PROP_ID = 'id';
    const PROP_TITLE = 'title';
    const PROP_URL = 'url';
    const PROP_TYPE = 'type';
    const PROP_SUBTYPE = 'subtype';
    const TYPE_ANY = 'any';

    protected $search_handlers = [];

    public function __construct(array $search_handlers)
    {
        $this->namespace = 'wp/v2';
        $this->rest_base = 'search';
        foreach ($search_handlers as $search_handler) {
            if (!$search_handler instanceof WP_REST_Search_Handler) {
                _doing_it_wrong(__METHOD__, sprintf(__('REST search handlers must extend the %s class.'), 'WP_REST_Search_Handler'), '5.0.0');
                continue;
            }
            $this->search_handlers[$search_handler->get_type()] = $search_handler;
        }
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_items'], 'permission_callback' => [$this, 'get_items_permission_check'], 'args' => $this->get_collection_params()],
            'schema' => [$this, 'get_public_item_schema'],
        ]);
    }

    public function get_items_permission_check($request)
    {
        return true;
    }

    public function get_items($request)
    {
        $handler = $this->get_search_handler($request);
        if (is_wp_error($handler)) {
            return $handler;
        }
        $result = $handler->search_items($request);
        if (!isset($result[WP_REST_Search_Handler::RESULT_IDS], $result[WP_REST_Search_Handler::RESULT_TOTAL]) || !is_array($result[WP_REST_Search_Handler::RESULT_IDS])) {
            return new WP_Error('rest_search_handler_error', __('Internal search handler error.'), ['status' => 500]);
        }
        $total = (int) $result[WP_REST_Search_Handler::RESULT_TOTAL];
        $page = (int) $request['page'];
        $pages = (int) ceil($total / max(1, (int) $request['per_page']));
        if ($page > $pages && $total > 0) {
            return new WP_Error('rest_search_invalid_page_number', __('The page number requested is larger than the number of pages available.'), ['status' => 400]);
        }
        $results = [];
        foreach ($result[WP_REST_Search_Handler::RESULT_IDS] as $id) {
            $results[] = $this->prepare_response_for_collection($this->prepare_item_for_response($id, $request));
        }
        $response = rest_ensure_response($results);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', $pages);
        $base = add_query_arg(urlencode_deep($request->get_query_params()), rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base)));
        if ($page > 1) {
            $response->link_header('prev', add_query_arg('page', $page - 1, $base));
        }
        if ($page < $pages) {
            $response->link_header('next', add_query_arg('page', $page + 1, $base));
        }
        return $response;
    }

    /** One result as its handler prepares it, filtered by context, linked to the search when links are asked for; an empty response when no handler takes the request. */
    public function prepare_item_for_response($item, $request)
    {
        $handler = $this->get_search_handler($request);
        if (is_wp_error($handler)) {
            return new WP_REST_Response();
        }
        $fields = $this->get_fields_for_response($request);
        $data = $this->filter_response_by_context($this->add_additional_fields_to_object($handler->prepare_item($item, $fields), $request), $request['context'] ?? 'view');
        $response = rest_ensure_response($data);
        if (rest_is_field_included('_links', $fields) || rest_is_field_included('_embedded', $fields)) {
            $links = $handler->prepare_item_links($item);
            $links['collection'] = ['href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base))];
            $response->add_links($links);
        }
        return $response;
    }

    /** The search-result schema, its type and subtype choices the handlers' own. */
    public function get_item_schema()
    {
        if ($this->schema === null) {
            $schema = SchemaData::of('search-result');
            $schema['properties'][self::PROP_TYPE]['enum'] = array_keys($this->search_handlers);
            $schema['properties'][self::PROP_SUBTYPE]['enum'] = $this->minn_subtypes();
            $this->schema = $schema;
        }
        return $this->add_additional_fields_schema($this->schema);
    }

    public function get_collection_params()
    {
        $types = array_keys($this->search_handlers);
        $params = parent::get_collection_params();
        $params['context']['default'] = 'view';
        $params[self::PROP_TYPE] = ['default' => $types[0] ?? null, 'description' => __('Limit results to items of an object type.'), 'type' => 'string', 'enum' => $types];
        $params[self::PROP_SUBTYPE] = ['default' => self::TYPE_ANY, 'description' => __('Limit results to items of one or more object subtypes.'), 'type' => 'array', 'items' => ['enum' => array_merge($this->minn_subtypes(), [self::TYPE_ANY]), 'type' => 'string'], 'sanitize_callback' => [$this, 'sanitize_subtypes']];
        $params['exclude'] = ['description' => __('Ensure result set excludes specific IDs.'), 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => []];
        $params['include'] = ['description' => __('Limit result set to specific IDs.'), 'type' => 'array', 'items' => ['type' => 'integer'], 'default' => []];
        return $params;
    }

    /** The subtypes a request names, as a list: "any" alone when it is among them, otherwise those its handler knows. */
    public function sanitize_subtypes($subtypes, $request, $parameter)
    {
        $subtypes = rest_parse_request_arg(wp_parse_slug_list($subtypes), $request, $parameter);
        if (is_wp_error($subtypes)) {
            return $subtypes;
        }
        if (in_array(self::TYPE_ANY, $subtypes, true)) {
            return [self::TYPE_ANY];
        }
        $handler = $this->get_search_handler($request);
        return is_wp_error($handler) ? $handler : array_intersect($subtypes, $handler->get_subtypes());
    }

    protected function get_search_handler($request)
    {
        $type = $request->get_param(self::PROP_TYPE);
        if (!$type || !is_string($type) || !isset($this->search_handlers[$type])) {
            return new WP_Error('rest_search_invalid_type', __('Invalid type parameter.'), ['status' => 400]);
        }
        return $this->search_handlers[$type];
    }

    /** Every handler's subtypes, once each, in handler order. @return list<string> */
    private function minn_subtypes(): array
    {
        $subtypes = [];
        foreach ($this->search_handlers as $handler) {
            $subtypes = array_merge($subtypes, $handler->get_subtypes());
        }
        return array_values(array_unique($subtypes));
    }
}
