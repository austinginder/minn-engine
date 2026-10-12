<?php
/**
 * anyOf and oneOf in a schema (probe rest-schema-choices), through
 * rest_validate_value_from_schema(), rest_find_one_matching_schema() and
 * rest_find_any_matching_schema(): a choice's schemas without a type take
 * the choice's own; the schema's own keywords still apply after a match,
 * under the matched type when it names none; several matches named by their
 * titles; and the refusal for matching none, named from the closest schema
 * (one left once type refusals are set aside, or the object schema sharing
 * the most keys with the value), the titles listed, or plain.
 */

add_filter('doing_it_wrong_trigger_error', '__return_false');
$shown = static fn ($result) => is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message(), $result->get_error_data()] : $result;
$valid = static fn ($value, array $schema) => $shown(rest_validate_value_from_schema($value, $schema, 'p'));
$typeless = ['type' => 'object', 'oneOf' => [['title' => 'A', 'properties' => ['k' => ['type' => 'string', 'enum' => ['a']]]], ['title' => 'B', 'properties' => ['k' => ['type' => 'string', 'enum' => ['b']]]]]];
$object = static fn (string $title, array $properties, array $required = []) => ['title' => $title, 'type' => 'object', 'properties' => $properties] + ($required === [] ? [] : ['required' => $required]);

$groups = [
    'schemas without a type' => [
        'oneOf, matching A' => $valid(['k' => 'a'], $typeless),
        'oneOf, matching none' => $valid(['k' => 'z'], $typeless),
        'anyOf, matching none' => $valid(['k' => 'z'], ['type' => 'object', 'anyOf' => $typeless['oneOf']]),
        'the one found' => $shown(rest_find_one_matching_schema(['k' => 'b'], $typeless, 'p')),
        'none found' => $shown(rest_find_one_matching_schema(['k' => 'z'], $typeless, 'p')),
        'the first found, of two' => $shown(rest_find_one_matching_schema(5, ['oneOf' => [['type' => 'integer'], ['type' => 'number']]], 'p', true)),
        'any found' => $shown(rest_find_any_matching_schema(['k' => 'b'], ['type' => 'object', 'anyOf' => $typeless['oneOf']], 'p')),
    ],
    'after a match' => [
        'minLength, anyOf' => $valid('ab', ['type' => 'string', 'minLength' => 5, 'anyOf' => [['type' => 'string']]]),
        'minLength, oneOf' => $valid('ab', ['type' => 'string', 'minLength' => 5, 'oneOf' => [['type' => 'string'], ['type' => 'integer']]]),
        'the matched type, anyOf' => $valid(3, ['minimum' => 10, 'anyOf' => [['type' => 'integer']]]),
        'the matched type, oneOf' => $valid(3, ['minimum' => 10, 'oneOf' => [['type' => 'integer'], ['type' => 'string']]]),
        'the enum' => $valid('x', ['type' => 'string', 'enum' => ['y'], 'oneOf' => [['type' => 'string']]]),
        'anyOf, then oneOf' => $valid(5, ['anyOf' => [['type' => 'integer']], 'oneOf' => [['type' => 'integer', 'maximum' => 2], ['type' => 'string']]]),
    ],
    'several matches' => [
        'two titled' => $valid(5, ['oneOf' => [['title' => 'One', 'type' => 'integer'], ['title' => 'Two', 'type' => 'number']]]),
        'three titled' => $valid(5, ['oneOf' => [['title' => 'One', 'type' => 'integer'], ['title' => 'Two', 'type' => 'number'], ['title' => 'Three', 'type' => ['integer', 'string']]]]),
        'two of three' => $valid(5, ['oneOf' => [['title' => 'One', 'type' => 'integer'], ['title' => 'Two', 'type' => 'number'], ['title' => 'Three', 'type' => 'string']]]),
        'one untitled' => $valid(5, ['oneOf' => [['title' => 'One', 'type' => 'integer'], ['type' => 'number']]]),
        'an empty title' => $valid(5, ['oneOf' => [['title' => '', 'type' => 'integer'], ['title' => 'Two', 'type' => 'number']]]),
    ],
    'matching none' => [
        'one beyond its type' => $valid('abc', ['oneOf' => [['type' => 'integer'], ['type' => 'string', 'minLength' => 5]]]),
        'all only their type' => $valid(true, ['oneOf' => [['type' => 'integer'], ['type' => 'string']]]),
        'two beyond their type' => $valid('abc', ['oneOf' => [['type' => 'string', 'maxLength' => 1], ['type' => 'string', 'minLength' => 5]]]),
        'two titled beyond their type' => $valid('abc', ['oneOf' => [['title' => 'Short', 'type' => 'string', 'maxLength' => 1], ['title' => 'Long', 'type' => 'string', 'minLength' => 5]]]),
        'titles counted over all' => $valid('abc', ['oneOf' => [['type' => 'integer'], ['title' => 'Short', 'type' => 'string', 'maxLength' => 1], ['title' => 'Long', 'type' => 'string', 'minLength' => 5]]]),
        'anyOf, one beyond its type' => $valid('abc', ['anyOf' => [['type' => 'integer'], ['title' => 'Long', 'type' => 'string', 'minLength' => 5]]]),
        'a nested wrong type' => $valid(['k' => 5], ['oneOf' => [['type' => 'object', 'properties' => ['k' => ['type' => 'string']]], ['type' => 'integer']]]),
        'objects, the second sharing more keys' => $valid(['a' => 1, 'b' => 'x'], ['oneOf' => [$object('One', ['a' => ['type' => 'string']]), $object('Two', ['a' => ['type' => 'integer'], 'b' => ['type' => 'integer']])]]),
        'objects, a tie' => $valid(['a' => 1, 'b' => 2], ['oneOf' => [$object('One', ['a' => ['type' => 'string']]), $object('Two', ['b' => ['type' => 'string']])]]),
        'objects sharing no key, titled' => $valid(['z' => 1], ['oneOf' => [$object('One', ['a' => ['type' => 'string']], ['a']), $object('Two', ['b' => ['type' => 'string']], ['b'])]]),
        'objects sharing no key, untitled' => $valid(['z' => 1], ['oneOf' => [['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']], ['type' => 'object', 'properties' => ['b' => ['type' => 'string']], 'required' => ['b']]]]),
        'objects without properties' => $valid(['a' => 1], ['oneOf' => [['title' => 'One', 'type' => 'object', 'required' => ['x']], ['title' => 'Two', 'type' => 'object', 'required' => ['y']]]]),
        'the first not only an object' => $valid(['a' => 1], ['oneOf' => [['title' => 'One', 'type' => ['object', 'string'], 'properties' => ['a' => ['type' => 'string']]], $object('Two', ['a' => ['type' => 'string'], 'b' => []], ['b'])]]),
        'the combining error itself' => $shown(rest_get_combining_operation_error(['a' => 1], 'p', [['error_object' => new WP_Error('rest_invalid_type', 'p is not of type string.', ['param' => 'p']), 'schema' => ['type' => 'string', 'title' => 'S'], 'index' => 0], ['error_object' => new WP_Error('rest_too_short', 'p is too short.'), 'schema' => ['type' => 'object', 'title' => 'O', 'properties' => ['a' => []]], 'index' => 1]])),
    ],
];

echo json_encode(array_map(null, array_keys($groups), array_values($groups)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
