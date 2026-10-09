<?php
/**
 * JSON schemas for a client (probe json-schema): the keywords each
 * profile allows, and schemas trimmed to them at every level, a boolean
 * required on a property turned into its object's required list. Same
 * protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};

$say('the keywords each profile allows', [
    wp_get_json_schema_allowed_keywords(),
    wp_get_json_schema_allowed_keywords('rest-api'),
    wp_get_json_schema_allowed_keywords('draft-04'),
    wp_get_json_schema_allowed_keywords('zz'),
    wp_get_json_schema_allowed_keywords(''),
    wp_get_json_schema_allowed_keywords('Draft-04'),
]);

$schema = ['$schema' => 'http://json-schema.org/draft-04/schema#', 'title' => 'zz', 'type' => 'object', 'arg_options' => ['x' => 1], 'context' => ['view'], 'readonly' => true, 'properties' => ['a' => ['type' => 'string', 'context' => ['edit'], 'sanitize_callback' => 'x', 'validate_callback' => 'y', 'enum' => ['p'], 'zz-custom' => 1, 'items' => ['type' => 'integer', 'arg_options' => []]], 'b' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['c' => ['type' => 'string', 'required' => true]]]]], 'additionalProperties' => false, 'patternProperties' => ['^z' => ['type' => 'string', 'zz' => 1]], 'oneOf' => [['type' => 'string', 'zz' => 2]], 'anyOf' => [['type' => 'null']], 'required' => ['a'], 'default' => 'q', 'description' => 'd', 'format' => 'uri', 'minimum' => 1, 'links' => [], 'zz-top' => 'drop?'];
$say('a whole schema', [
    wp_prepare_json_schema_for_client($schema),
    wp_prepare_json_schema_for_client($schema, 'rest-api'),
    wp_prepare_json_schema_for_client($schema, 'zz'),
    wp_prepare_json_schema_for_client([]),
]);

$cases = [
    ['additionalProperties' => ['type' => 'string', 'zz' => 1]],
    ['items' => [['type' => 'string', 'zz' => 1], ['type' => 'integer', 'zz' => 2]]],
    ['allOf' => [['type' => 'string', 'zz' => 1]], 'not' => ['type' => 'null', 'zz' => 1], 'definitions' => ['d' => ['type' => 'string', 'zz' => 1]], 'dependencies' => ['a' => ['b'], 'c' => ['type' => 'object', 'zz' => 1]], 'additionalItems' => ['type' => 'string', 'zz' => 1]],
    ['type' => 'object', 'properties' => ['a' => ['type' => 'string', 'required' => false], 'b' => ['required' => true], 'c' => ['required' => 'yes'], 'd' => ['required' => 1]]],
    ['type' => 'object', 'required' => ['x'], 'properties' => ['a' => ['required' => true]]],
    ['type' => 'object', 'required' => 'notarray', 'properties' => ['a' => ['required' => true]]],
    ['type' => 'object', 'required' => [], 'properties' => ['a' => ['required' => true]]],
    ['type' => 'object', 'required' => true, 'properties' => ['a' => ['required' => false]]],
    ['required' => true],
    ['required' => false, 'type' => 'string'],
    ['required' => 'yes', 'type' => 'string'],
    ['properties' => ['a' => ['required' => true], 'b' => ['required' => true]], 'type' => 'object'],
    ['patternProperties' => ['^a' => ['required' => true, 'type' => 'string']]],
    ['items' => ['type' => 'object', 'required' => true, 'properties' => ['q' => ['required' => true]]]],
    ['type' => 'object', 'properties' => ['a' => ['required' => true]], 'zz' => 1, 'title' => 't'],
    ['type' => 'object', 'properties' => 'notarray'],
    ['type' => 'object', 'properties' => ['a' => 'notarray', 'b' => ['type' => 'string']]],
    ['type' => ['string', 'null'], 'enum' => [['zz' => 1]], 'default' => ['zz' => 1, 'type' => 'q']],
    ['properties' => ['a' => ['type' => 'object', 'properties' => ['b' => ['type' => 'object', 'properties' => ['c' => ['required' => true, 'zz' => 3]]]]]]],
    ['patternProperties' => 'x', 'anyOf' => 'y', 'oneOf' => ['z', ['type' => 'string', 'zz' => 1]], 'items' => 'q'],
    ['type' => 'object', 'properties' => []],
    ['$ref' => '#/definitions/x', 'id' => 'zz', '$schema' => 'x'],
    [0 => ['type' => 'string'], 'type' => 'x'],
    ['items' => [[['type' => 'string', 'zz' => 1]]], 'anyOf' => [[['zz' => 1]], []]],
];
$say('schemas trimmed per profile', array_map(static fn ($case) => [wp_prepare_json_schema_for_client($case), wp_prepare_json_schema_for_client($case, 'rest-api')], $cases));

$small = ['type' => 'object', 'properties' => ['c' => ['type' => 'string', 'required' => true, 'zz' => 1]], 'items' => ['zz' => 1, 'type' => 'x'], 'title' => 'kept?'];
$say('schemas trimmed to given keywords', [
    _wp_prepare_json_schema_for_client_with_allowed_keywords($small, ['type' => true, 'properties' => true, 'items' => true]),
    _wp_prepare_json_schema_for_client_with_allowed_keywords($small, ['type' => 1, 'properties' => 0]),
    _wp_prepare_json_schema_for_client_with_allowed_keywords($small, ['type', 'properties', 'items']),
    _wp_prepare_json_schema_for_client_with_allowed_keywords($small, []),
    _wp_prepare_json_schema_for_client_with_allowed_keywords($small, ['title' => true, 'required' => true]),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
