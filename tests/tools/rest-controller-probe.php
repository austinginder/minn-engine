<?php
/**
 * The REST controller base class (probe rest-controller): WP_REST_Controller
 * through a small subclass with a schema. The default answers of each route
 * method, a response for a collection, the response cut to a context, the
 * public schema, the collection parameters and context parameter,
 * additional fields registered with register_rest_field (added, updated,
 * in the schema), the fields a request asks for, the endpoint arguments
 * read from the schema for each method, and slugs sanitized. Same protocol
 * as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$result = static function ($value) {
    if ($value instanceof WP_Error) {
        return ['error', $value->get_error_code(), $value->get_error_message(), $value->get_error_data()];
    }
    if ($value instanceof WP_REST_Response) {
        return ['response', $value->get_status(), $value->get_data(), array_keys($value->get_links())];
    }
    return $value;
};

class Zz_Rest_Probe_Controller extends WP_REST_Controller
{
    /** The protected base methods, called from inside the class. */
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }

    public function __construct()
    {
        $this->namespace = 'zz-probe/v1';
        $this->rest_base = 'things';
    }

    public function get_item_schema()
    {
        if ($this->schema) {
            return $this->add_additional_fields_schema($this->schema);
        }
        $this->schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'zz-thing',
            'type' => 'object',
            'properties' => [
                'id' => ['description' => 'The id.', 'type' => 'integer', 'context' => ['view', 'edit', 'embed'], 'readonly' => true],
                'title' => ['description' => 'The title.', 'type' => 'string', 'context' => ['view', 'edit'], 'required' => true, 'arg_options' => ['sanitize_callback' => 'sanitize_text_field']],
                'secret' => ['description' => 'Edit only.', 'type' => 'string', 'context' => ['edit'], 'default' => 'shh'],
                'count' => ['description' => 'A count.', 'type' => 'integer', 'context' => ['view', 'edit'], 'minimum' => 1, 'default' => 1],
                'meta' => ['description' => 'Nested.', 'type' => 'object', 'context' => ['view', 'edit'], 'properties' => [
                    'inner' => ['type' => 'string', 'context' => ['edit']],
                    'shown' => ['type' => 'string', 'context' => ['view', 'edit']],
                ]],
                'tags' => ['description' => 'A list.', 'type' => 'array', 'items' => ['type' => 'string'], 'context' => ['view']],
            ],
        ];
        return $this->add_additional_fields_schema($this->schema);
    }

    public function prepare_item_for_response($item, $request)
    {
        $data = $this->add_additional_fields_to_object($this->filter_response_by_context($item, $request['context'] ?? 'view'), $request);
        $response = rest_ensure_response($data);
        $response->add_link('self', rest_url("zz-probe/v1/things/{$item['id']}"));
        return $response;
    }
}

$controller = new Zz_Rest_Probe_Controller();
$base = new class () extends WP_REST_Controller {
    /** The protected base methods, called from inside the class. */
    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
};
$request = new WP_REST_Request('GET', '/zz-probe/v1/things');
$say('the class', [get_class($base) !== WP_REST_Controller::class, $base instanceof WP_REST_Controller, $controller->call('get_object_type')]);

$defaults = [];
foreach (['get_items_permissions_check', 'get_items', 'get_item_permissions_check', 'get_item', 'create_item_permissions_check', 'create_item', 'update_item_permissions_check', 'update_item', 'delete_item_permissions_check', 'delete_item', 'prepare_item_for_database', 'prepare_item_for_response'] as $method) {
    $defaults[$method] = $result($method === 'prepare_item_for_response' ? $base->call($method, [], $request) : $base->call($method, $request));
}
$defaults['register_routes'] = (static function () use ($base) {
    $wrong = [];
    $listen = static function ($function, $message) use (&$wrong): void {
        $wrong[] = [$function, strip_tags((string) $message)];
    };
    add_action('doing_it_wrong_run', $listen, 10, 2);
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    $base->register_routes();
    remove_action('doing_it_wrong_run', $listen, 10);
    remove_filter('doing_it_wrong_trigger_error', '__return_false');
    return $wrong;
})();
$defaults['base schema'] = $base->get_item_schema();
$defaults['base public schema'] = $base->get_public_item_schema();
$say('defaults', $defaults);

$item = ['id' => 7, 'title' => 'Zz', 'secret' => 'x', 'count' => 3, 'meta' => ['inner' => 'i', 'shown' => 's'], 'tags' => ['a'], 'extra' => 'not in schema'];
$say('filtered by context', [
    $controller->filter_response_by_context($item, 'view'),
    $controller->filter_response_by_context($item, 'edit'),
    $controller->filter_response_by_context($item, 'embed'),
    $controller->filter_response_by_context($item, 'nonsense'),
]);
$response = $controller->prepare_item_for_response($item, $request);
$say('for a collection', [
    $controller->prepare_response_for_collection($response),
    $controller->prepare_response_for_collection(['not' => 'a response']),
]);
$say('public schema', [$controller->get_public_item_schema()]);
$say('collection and context params', [
    $controller->get_collection_params(),
    $controller->get_context_param(),
    $controller->get_context_param(['default' => 'view']),
    $controller->get_context_param(['default' => 'edit', 'description' => 'Overridden.']),
    $base->get_context_param(['default' => 'view']),
]);

$updated = [];
register_rest_field('zz-thing', 'zz_extra', [
    'get_callback' => static fn ($object, $field, $req, $type) => 'extra for ' . $object['id'] . ' as ' . $type,
    'update_callback' => static function ($value, $object, $field, $req, $type) use (&$updated) {
        $updated[] = [$value, $object['id'], $field, $type];
        return $value === 'refuse' ? new WP_Error('zz_refused', 'Refused.') : true;
    },
    'schema' => ['description' => 'An extra.', 'type' => 'string', 'context' => ['view']],
]);
register_rest_field('zz-thing', 'zz_readonly', ['get_callback' => static fn () => 'ro', 'schema' => null]);
register_rest_field(['zz-thing', 'zz-other'], 'zz_shared', ['get_callback' => null, 'update_callback' => null]);
$say('additional fields', [
    array_keys($controller->call('get_additional_fields')),
    $controller->call('get_additional_fields', 'zz-other'),
    array_keys($controller->call('get_additional_fields', 'zz-none')),
    $controller->call('add_additional_fields_to_object', ['id' => 7], $request),
    $result($controller->call('update_additional_fields_for_object', ['id' => 7], (static function () {
        $r = new WP_REST_Request('POST', '/zz-probe/v1/things/7');
        $r->set_param('zz_extra', 'value');
        return $r;
    })())),
    $result($controller->call('update_additional_fields_for_object', ['id' => 7], (static function () {
        $r = new WP_REST_Request('POST', '/zz-probe/v1/things/7');
        $r->set_param('zz_extra', 'refuse');
        return $r;
    })())),
    $updated,
    $controller->get_item_schema()['properties']['zz_extra'] ?? null,
    array_key_exists('zz_readonly', $controller->get_item_schema()['properties']),
    $result($controller->prepare_item_for_response($item, $request)),
]);

$fieldsRequest = static function (?string $fields, string $context = 'view') {
    $r = new WP_REST_Request('GET', '/zz-probe/v1/things');
    if ($fields !== null) {
        $r->set_param('_fields', $fields);
    }
    $r->set_param('context', $context);
    return $r;
};
$say('fields for a response', [
    $controller->get_fields_for_response($fieldsRequest(null)),
    $controller->get_fields_for_response($fieldsRequest(null, 'edit')),
    $controller->get_fields_for_response($fieldsRequest('id,title')),
    $controller->get_fields_for_response($fieldsRequest('meta.shown,zz_extra,nope')),
    $controller->get_fields_for_response($fieldsRequest('_links,_embedded,id')),
]);
$say('endpoint arguments', [
    $controller->get_endpoint_args_for_item_schema(),
    $controller->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
    $controller->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
    $controller->get_endpoint_args_for_item_schema('PATCH'),
]);
$say('slugs sanitized', array_map([$controller, 'sanitize_slug'], ['Hello World', 'already-a-slug', 'Ünïcödé', '  spaced  ', '', 'a/b']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
