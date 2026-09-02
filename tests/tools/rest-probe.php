<?php
/**
 * Behaviour probe for the REST route API: register_rest_route, the request
 * and response objects, permission and argument handling, the server's
 * route index, all through rest_do_request. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$kind = static fn ($r) => $r instanceof WP_Error ? 'error:' . $r->get_error_code() : (is_object($r) ? get_class($r) : var_export($r, true));
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace($home, '{home}', $v) : $v;
$data = static function ($response) use ($kind) {
    if ($response instanceof WP_REST_Response) {
        return ['class' => 'WP_REST_Response', 'status' => $response->get_status(), 'data' => $response->get_data(), 'headers' => $response->get_headers(), 'links' => $response->get_links()];
    }
    return $kind($response);
};

add_action('rest_api_init', static function () {
    register_rest_route('minn-probe/v1', '/echo', [
        ['methods' => 'GET', 'callback' => static fn (WP_REST_Request $r) => ['got' => $r->get_param('x'), 'n' => $r['n'], 'method' => $r->get_method(), 'route' => $r->get_route(), 'params' => $r->get_params(), 'url' => $r->get_url_params(), 'query' => $r->get_query_params(), 'has' => $r->has_param('nope'), 'json' => $r->get_json_params(), 'header' => $r->get_header('x-probe'), 'attrs' => array_keys($r->get_attributes())], 'permission_callback' => '__return_true', 'args' => ['x' => ['required' => true, 'type' => 'string'], 'n' => ['type' => 'integer', 'default' => 7, 'minimum' => 1, 'maximum' => 10], 'e' => ['enum' => ['a', 'b']], 'b' => ['type' => 'boolean'], 'arr' => ['type' => 'array', 'items' => ['type' => 'integer']]]],
        ['methods' => WP_REST_Server::CREATABLE, 'callback' => static fn (WP_REST_Request $r) => new WP_REST_Response(['created' => $r->get_param('title'), 'body' => $r->get_body_params(), 'json' => $r->get_json_params()], 201, ['X-Probe' => 'yes']), 'permission_callback' => static fn () => current_user_can('edit_posts')],
    ]);
    register_rest_route('minn-probe/v1', '/items/(?P<id>\d+)', ['methods' => 'GET,POST', 'callback' => static fn ($r) => rest_ensure_response(['id' => (int) $r['id'], 'url' => $r->get_url_params()]), 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/error', ['methods' => 'GET', 'callback' => static fn () => new WP_Error('probe_error', 'It broke.', ['status' => 418, 'extra' => 1]), 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/error-bare', ['methods' => 'GET', 'callback' => static fn () => new WP_Error('bare_error', 'No status.'), 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/plain', ['methods' => 'GET', 'callback' => static fn () => 'plain string', 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/null', ['methods' => 'GET', 'callback' => static fn () => null, 'permission_callback' => '__return_true']);
    register_rest_route('minn-probe/v1', '/denied', ['methods' => 'GET', 'callback' => '__return_true', 'permission_callback' => '__return_false']);
    register_rest_route('minn-probe/v1', '/denied-error', ['methods' => 'GET', 'callback' => '__return_true', 'permission_callback' => static fn () => new WP_Error('custom_denied', 'Custom denied.', ['status' => 403])]);
    register_rest_route('minn-probe/v1', '/sanitize', ['methods' => 'GET', 'callback' => static fn ($r) => ['s' => $r['s'], 'v' => $r['v']], 'permission_callback' => '__return_true', 'args' => ['s' => ['sanitize_callback' => static fn ($v) => strtoupper((string) $v)], 'v' => ['validate_callback' => static fn ($v) => $v === 'ok' ? true : new WP_Error('bad_v', 'v must be ok.')]]]);
    register_rest_route('minn-probe/v1', '/override', ['methods' => 'GET', 'callback' => static fn () => 'first', 'permission_callback' => '__return_true']);
    $say = null;
});
$say('register before init', register_rest_route('minn-probe/v1', '/early', ['methods' => 'GET', 'callback' => '__return_true', 'permission_callback' => '__return_true']));
$server = rest_get_server();
$say('rest_get_server', [get_class($server), did_action('rest_api_init')]);
$say('register after init', register_rest_route('minn-probe/v1', '/late', ['methods' => 'GET', 'callback' => static fn () => 'late', 'permission_callback' => '__return_true']));
$say('register override', [register_rest_route('minn-probe/v1', '/override', ['methods' => 'GET', 'callback' => static fn () => 'second', 'permission_callback' => '__return_true']), rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/override'))->get_data(), register_rest_route('minn-probe/v1', '/override', ['methods' => 'GET', 'callback' => static fn () => 'third', 'permission_callback' => '__return_true'], true), rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/override'))->get_data()]);
$say('register bad', [register_rest_route('', '/x', ['callback' => '__return_true']), register_rest_route('minn-probe/v1', '', ['callback' => '__return_true']), register_rest_route('minn-probe/v1', 'no-slash', ['methods' => 'GET', 'callback' => '__return_true', 'permission_callback' => '__return_true'])]);
$say('routes registered', array_values(array_filter(array_keys($server->get_routes()), static fn ($r) => str_starts_with($r, '/minn-probe'))));
$say('routes shape', array_map(static fn ($e) => [array_keys($e), $e['methods'], array_keys($e['args'] ?? [])], $server->get_routes()['/minn-probe/v1/echo']));
$say('get_namespaces has', in_array('minn-probe/v1', $server->get_namespaces(), true));
$say('route_options', $server->get_route_options('/minn-probe/v1/echo'));

$req = new WP_REST_Request('GET', '/minn-probe/v1/echo');
$req->set_query_params(['x' => 'hello', 'n' => '3', 'extra' => 'kept']);
$req->set_header('X-Probe', 'h');
$res = rest_do_request($req);
$say('echo get', $data($res));
$say('echo get matched', [$res->get_matched_route(), is_array($res->get_matched_handler()) ? array_keys($res->get_matched_handler()) : null]);
$say('echo missing required', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/echo'))));
$r2 = new WP_REST_Request('GET', '/minn-probe/v1/echo');
$r2->set_query_params(['x' => 'a', 'n' => '99']);
$say('echo out of range', $data(rest_do_request($r2)));
$r3 = new WP_REST_Request('GET', '/minn-probe/v1/echo');
$r3->set_query_params(['x' => 'a', 'e' => 'z']);
$say('echo bad enum', $data(rest_do_request($r3)));
$r4 = new WP_REST_Request('GET', '/minn-probe/v1/echo');
$r4->set_query_params(['x' => 'a', 'b' => 'false', 'n' => '2', 'arr' => ['1', '2']]);
$say('echo coerced', rest_do_request($r4)->get_data()['params']);
$r5 = new WP_REST_Request('GET', '/minn-probe/v1/echo');
$r5->set_query_params(['x' => 'a', 'b' => 'nope']);
$say('echo bad boolean', $data(rest_do_request($r5)));
$say('echo no perm callback default', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/early'))));
$post = new WP_REST_Request('POST', '/minn-probe/v1/echo');
$post->set_body_params(['title' => 'T']);
$say('post denied anon', $data(rest_do_request($post)));
wp_set_current_user(1);
$say('post as admin', $data(rest_do_request($post)));
$jsonReq = new WP_REST_Request('POST', '/minn-probe/v1/echo');
$jsonReq->set_header('Content-Type', 'application/json');
$jsonReq->set_body(json_encode(['title' => 'J', 'deep' => ['a' => 1]]));
$say('post json', $data(rest_do_request($jsonReq)));
$badJson = new WP_REST_Request('POST', '/minn-probe/v1/echo');
$badJson->set_header('Content-Type', 'application/json');
$badJson->set_body('{bad');
$say('post bad json', $data(rest_do_request($badJson)));
wp_set_current_user(0);
$say('items url param', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/items/42'))));
$say('items post', $data(rest_do_request(new WP_REST_Request('POST', '/minn-probe/v1/items/42'))));
$say('items delete', $data(rest_do_request(new WP_REST_Request('DELETE', '/minn-probe/v1/items/42'))));
$say('items no match', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/items/abc'))));
$say('no route', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/nope'))));
$say('error with status', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/error'))));
$say('error bare', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/error-bare'))));
$say('plain', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/plain'))));
$say('null', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/null'))));
$say('denied', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/denied'))));
wp_set_current_user(2);
$say('denied logged in', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/denied'))));
wp_set_current_user(0);
$say('denied error', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/denied-error'))));
$san = new WP_REST_Request('GET', '/minn-probe/v1/sanitize');
$san->set_query_params(['s' => 'low', 'v' => 'ok']);
$say('sanitize', $data(rest_do_request($san)));
$san2 = new WP_REST_Request('GET', '/minn-probe/v1/sanitize');
$san2->set_query_params(['v' => 'bad']);
$say('validate', $data(rest_do_request($san2)));
$say('late route', $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1/late'))));
$say('namespace index', (static function () use ($data) { $d = $data(rest_do_request(new WP_REST_Request('GET', '/minn-probe/v1'))); $d['data']['routes'] = array_map(static fn ($r) => [$r['namespace'], $r['methods'], array_map(static fn ($e) => [$e['methods'], array_keys($e['args'] ?? [])], $r['endpoints'])], $d['data']['routes']); $d['data']['_links'] = array_keys($d['data']['_links'] ?? []); return $d; })());
$say('root index namespaces', in_array('minn-probe/v1', rest_do_request(new WP_REST_Request('GET', '/'))->get_data()['namespaces'], true));
$say('root index route entry', (static function () { $r = rest_do_request(new WP_REST_Request('GET', '/'))->get_data()['routes']['/minn-probe/v1/echo']; return [$r['namespace'], $r['methods'], array_map(static fn ($e) => [$e['methods'], array_keys($e['args'] ?? []), $e['args']['n'] ?? null], $r['endpoints']), array_keys($r['_links'] ?? [])]; })());
$say('rest_ensure_response', [get_class(rest_ensure_response('x')), rest_ensure_response('x')->get_data(), rest_ensure_response('x')->get_status(), $kind(rest_ensure_response(new WP_Error('e', 'm'))), get_class(rest_ensure_response(new WP_REST_Response('y')))]);
$say('WP_REST_Request shapes', (static function () { $r = new WP_REST_Request('get', '/x/y', ['a' => ['default' => 1]]); $r->set_param('p', 'v'); $r['q'] = 'w'; return [$r->get_method(), $r->get_route(), $r->get_attributes(), $r->get_params(), isset($r['q']), $r['nope'], $r->get_param('nope'), $r->has_param('p'), $r->get_content_type(), $r->is_json_content_type(), $r->get_headers(), $r->get_body(), $r->get_default_params(), $r->get_file_params(), $r->get_body_params()]; })());
$say('WP_REST_Request from_url', (static function () use ($home) { $r = WP_REST_Request::from_url($home . '/wp-json/minn-probe/v1/echo?x=1'); return [$r ? get_class($r) : false, $r ? $r->get_route() : null, $r ? $r->get_query_params() : null, WP_REST_Request::from_url('https://elsewhere.test/x')]; })());
$say('WP_REST_Request headers', (static function () { $r = new WP_REST_Request(); $r->set_headers(['Content-Type' => 'text/plain', 'X-Multi' => ['a', 'b']]); $r->add_header('x-multi', 'c'); return [$r->get_header('content-type'), $r->get_header('x-multi'), $r->get_header_as_array('x-multi'), $r->get_headers(), WP_REST_Request::canonicalize_header_name('Content-Type')]; })());
$say('WP_REST_Request param order', (static function () { $r = new WP_REST_Request('POST', '/x'); $r->set_url_params(['k' => 'url']); $r->set_query_params(['k' => 'query']); $r->set_body_params(['k' => 'body']); $r->set_default_params(['k' => 'default', 'd' => 'def']); return [$r->get_param('k'), $r->get_param('d')]; })());
$say('WP_REST_Request json', (static function () { $r = new WP_REST_Request('POST', '/x'); $r->set_header('Content-Type', 'application/json; charset=utf-8'); $r->set_body('{"a":1}'); return [$r->get_json_params(), $r->get_param('a'), $r->get_content_type(), $r->is_json_content_type()]; })());
$say('WP_REST_Response', (static function () { $r = new WP_REST_Response(['a' => 1], 201, ['X-A' => '1']); $r->header('X-B', '2'); $r->add_link('self', 'https://x.test/s', ['embeddable' => true]); $r->add_link('collection', 'https://x.test/c'); $r->add_links(['up' => [['href' => 'https://x.test/u']]]); return [$r->get_status(), $r->get_data(), $r->get_headers(), $r->get_links(), $r->is_error(), $r->as_error(), $r->link_header('alternate', 'https://x.test/alt', ['type' => 'text/html']), $r->get_headers()['Link'] ?? null, $r->get_curies()]; })());
$say('WP_REST_Response error', (static function () { $r = new WP_REST_Response(['code' => 'x', 'message' => 'm', 'data' => ['status' => 400]], 400); return [$r->is_error(), get_class($r->as_error()), $r->as_error()->get_error_code(), $r->as_error()->get_error_data()]; })());
$say('response_to_data', (static function () use ($server) { $r = new WP_REST_Response(['a' => 1]); $r->add_link('self', 'https://x.test/s'); $r->add_link('https://api.w.org/term', 'https://x.test/t', ['taxonomy' => 'category']); return [$server->response_to_data($r, false), $server->response_to_data($r, true)]; })());
$say('error_to_response', (static function () { $e = new WP_Error('c1', 'm1', ['status' => 402, 'x' => 1]); $e->add('c2', 'm2'); $r = rest_convert_error_to_response($e); return [$r->get_status(), $r->get_data()]; })());
$say('rest_convert_error_to_response', (static function () { $r = rest_convert_error_to_response(new WP_Error('c', 'm', 'string-data')); return [$r->get_status(), $r->get_data()]; })());
$say('rest_validate_value_from_schema', [rest_validate_value_from_schema('5', ['type' => 'integer'], 'p'), $kind(rest_validate_value_from_schema('x', ['type' => 'integer'], 'p')), rest_validate_value_from_schema('x', ['type' => 'integer'], 'p') instanceof WP_Error ? rest_validate_value_from_schema('x', ['type' => 'integer'], 'p')->get_error_message() : null, rest_validate_value_from_schema('true', ['type' => 'boolean'], 'p'), $kind(rest_validate_value_from_schema('maybe', ['type' => 'boolean'], 'p')), rest_validate_value_from_schema('b', ['enum' => ['a', 'b']], 'p'), rest_validate_value_from_schema('c', ['enum' => ['a', 'b']], 'p')->get_error_message(), rest_validate_value_from_schema('1,2', ['type' => 'array', 'items' => ['type' => 'integer']], 'p'), rest_validate_value_from_schema(['a' => 'b'], ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]], 'p'), rest_validate_value_from_schema('x', ['type' => 'string', 'minLength' => 2], 'p')->get_error_message(), rest_validate_value_from_schema('2020-01-01T00:00:00', ['type' => 'string', 'format' => 'date-time'], 'p'), rest_validate_value_from_schema('nope', ['type' => 'string', 'format' => 'email'], 'p')->get_error_message(), rest_validate_value_from_schema(11, ['type' => 'integer', 'maximum' => 10], 'p')->get_error_message(), rest_validate_value_from_schema('x', ['type' => ['string', 'null']], 'p'), rest_validate_value_from_schema(null, ['type' => ['string', 'null']], 'p')]);
$say('rest_get_allowed_schema_keywords', rest_get_allowed_schema_keywords());
$say('rest_sanitize_value_from_schema', [rest_sanitize_value_from_schema('5', ['type' => 'integer']), rest_sanitize_value_from_schema('5.5', ['type' => 'number']), rest_sanitize_value_from_schema('false', ['type' => 'boolean']), rest_sanitize_value_from_schema('1,2', ['type' => 'array', 'items' => ['type' => 'integer']]), rest_sanitize_value_from_schema(['b' => '1', 'z' => 'x'], ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']]]), rest_sanitize_value_from_schema(['b' => '1', 'z' => 'x'], ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']], 'additionalProperties' => false]), rest_sanitize_value_from_schema(' <b>x</b> ', ['type' => 'string']), rest_sanitize_value_from_schema('x', ['type' => 'string', 'format' => 'uri']), rest_sanitize_value_from_schema('A@B.co', ['type' => 'string', 'format' => 'email']), rest_sanitize_value_from_schema(5, ['type' => 'string']), rest_sanitize_value_from_schema('x', ['type' => 'nope'])]);
$say('rest helpers', [rest_is_boolean('false'), rest_is_boolean('yes'), rest_is_integer('5'), rest_is_integer('5.5'), rest_is_array('a,b'), rest_is_array(['a']), rest_is_object(['a' => 1]), rest_is_object('x'), rest_stabilize_value(['b' => 1, 'a' => 2]), rest_parse_date('2020-01-02T03:04:05'), rest_parse_date('2020-01-02T03:04:05Z'), rest_parse_date('nope'), rest_get_date_with_gmt('2020-01-02 03:04:05'), rest_get_date_with_gmt('2020-01-02T03:04:05Z', true)]);
$say('rest_get_route_for', [rest_get_route_for_post(1), rest_get_route_for_post(2), rest_get_route_for_post(999999), rest_get_route_for_term(get_term(1, 'category')), rest_get_route_for_term(get_term(2, 'post_tag')), rest_get_route_for_post_type_items('post'), rest_get_route_for_taxonomy_items('category')]);
$say('register_rest_field', (static function () { register_rest_field('post', 'probe_field', ['get_callback' => static fn ($o) => 'pf:' . $o['id'], 'schema' => ['type' => 'string']]); $r = rest_do_request(new WP_REST_Request('GET', '/wp/v2/posts/1')); return [$r->get_data()['probe_field'] ?? null, isset($GLOBALS['wp_rest_additional_fields']['post']['probe_field']), array_keys($GLOBALS['wp_rest_additional_fields']['post']['probe_field'])]; })());
$say('rest_url', [$rel(rest_url('minn-probe/v1/echo')), $rel(get_rest_url(null, '/minn-probe/v1/echo'))]);
$say('WP_REST_Controller', (static function () { $c = new class extends WP_REST_Controller { public function __construct() { $this->namespace = 'minn-probe/v1'; $this->rest_base = 'ctl'; } public function get_items($r) { return rest_ensure_response(['ok']); } public function get_items_permissions_check($r) { return true; } public function get_item_schema() { return ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'probe', 'type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'context' => ['view', 'edit']], 'secret' => ['type' => 'string', 'context' => ['edit']]]]; } }; $c->register_routes(); return [$c->get_collection_params(), $c->get_context_param(['default' => 'view']), array_keys($c->get_public_item_schema()), $c->filter_response_by_context(['id' => 1, 'secret' => 's'], 'view'), $c->get_fields_for_response(new WP_REST_Request('GET', '/x')), $c->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE), $c->prepare_response_for_collection(new WP_REST_Response(['a' => 1]))]; })());
$say('server constants', [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE, WP_REST_Server::EDITABLE, WP_REST_Server::DELETABLE, WP_REST_Server::ALLMETHODS]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
