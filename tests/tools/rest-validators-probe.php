<?php
/**
 * The REST API's schema helpers one by one (rest-api.php): the per-type
 * validators, the enum and pattern checks, unique items, the recursive key
 * intersection, the combining-operation error, and the authentication
 * status the cookie and application-password checks leave for the server.
 * Pure functions, so no rows are written. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// A WP_Error reads as its codes, messages and data, in order.
$out = static function ($result) {
    // A user reads as its id: the object carries the password hash.
    if ($result instanceof WP_User) {
        return 'WP_User #' . $result->ID;
    }
    if (!$result instanceof WP_Error) {
        return $result;
    }
    $codes = $result->get_error_codes();
    return ['error' => $codes, 'messages' => array_map(static fn ($c) => $result->get_error_messages($c), $codes), 'data' => array_map(static fn ($c) => $result->get_error_data($c), $codes)];
};
$each = static function (array $values, callable $fn) use ($out): array {
    $rows = [];
    foreach ($values as $value) {
        $rows[] = [$value, $out($fn($value))];
    }
    return $rows;
};

$say('null', $each([null, '', 0, false, 'null', []], static fn ($v) => rest_validate_null_value_from_schema($v, 'p')));
$say('rest_is_boolean', $each(['', ' ', 'true', 'TRUE', 'on', 'yes', '0', '1', 0, 1, 2, false, true, null], static fn ($v) => rest_is_boolean($v)));
$say('boolean', $each([true, false, 'true', 'false', '1', '0', 1, 0, 2, 'yes', null, [], ''], static fn ($v) => rest_validate_boolean_value_from_schema($v, 'p')));

$integers = [5, '5', 5.0, 5.5, '5.5', 'abc', null, true, -3, '', '0x1A', ' 5'];
$numbers = [5.5, '5.5', 5, 'abc', '1e3', -0.25, null, false, '', '.5'];
foreach ([[], ['minimum' => 1], ['maximum' => 3], ['minimum' => 1, 'maximum' => 10, 'exclusiveMinimum' => true], ['minimum' => 1, 'maximum' => 5, 'exclusiveMaximum' => true], ['multipleOf' => 2], ['minimum' => 2.5], ['maximum' => -1, 'exclusiveMaximum' => true]] as $args) {
    $say('integer ' . json_encode($args), $each($integers, static fn ($v) => rest_validate_integer_value_from_schema($v, $args, 'p')));
}
foreach ([[], ['minimum' => 0.5], ['maximum' => 5], ['multipleOf' => 0.5], ['multipleOf' => 0.1], ['minimum' => 1, 'maximum' => 2]] as $args) {
    $say('number ' . json_encode($args), $each($numbers, static fn ($v) => rest_validate_number_value_from_schema($v, $args, 'p')));
}
$say('number multipleOf float', $each([0.3, 0.7, 1.1, 10], static fn ($v) => rest_validate_number_value_from_schema($v, ['multipleOf' => 0.1], 'p')));

$strings = ['abc', '', 5, null, true, ['a'], 'é', 'a#b', 'ab', 'a/b'];
foreach ([[], ['minLength' => 2], ['maxLength' => 2], ['minLength' => 1], ['maxLength' => 1], ['pattern' => '^a'], ['pattern' => 'a#b'], ['pattern' => '[0-9]+'], ['pattern' => 'a/b'], ['pattern' => '^.$'], ['format' => 'email'], ['minLength' => 2, 'pattern' => '^z']] as $args) {
    $say('string ' . json_encode($args), $each($strings, static fn ($v) => rest_validate_string_value_from_schema($v, $args, 'p')));
}

$arrays = [[1, 2], [], 'a,b', 'a', null, ['x' => 1], [1, 1], ['a', 'b', 'c'], [[1], [1]], 5];
foreach ([[], ['items' => ['type' => 'integer']], ['minItems' => 1], ['minItems' => 2], ['maxItems' => 1], ['maxItems' => 2], ['uniqueItems' => true], ['items' => ['type' => 'string', 'minLength' => 2]], ['items' => ['type' => 'array', 'items' => ['type' => 'integer']]]] as $args) {
    $say('array ' . json_encode($args), $each($arrays, static fn ($v) => rest_validate_array_value_from_schema($v, $args, 'p')));
}

$enums = ['a', 'd', 1, '1', true, null, 1.0, ['x' => 1]];
foreach ([['enum' => ['a']], ['enum' => ['a', 'b']], ['enum' => ['a', 'b', 'c']], ['enum' => [1, 2]], ['enum' => [1, 2], 'type' => 'integer'], ['enum' => ['1', '2'], 'type' => 'string'], ['enum' => [true]], ['enum' => [true], 'type' => 'boolean'], ['enum' => [['x' => 1]]], ['enum' => [null, 'a']]] as $args) {
    $say('enum ' . json_encode($args), $each($enums, static fn ($v) => rest_validate_enum($v, $args, 'p')));
}

// The whole validator with the type named: does it run the same checks in the same order?
$whole = [
    ['integer', ['maximum' => 3], [5.5, '5.5', 'abc']],
    ['integer', ['multipleOf' => 2], [5.5, 3, '4']],
    ['integer', ['minimum' => 1, 'enum' => [1, 2]], ['1', 2, 3, '0']],
    ['number', ['minimum' => 1, 'enum' => [1.5, 2]], ['1.5', 2, 3]],
    ['boolean', ['enum' => [true]], ['a', 'true', false, 0]],
    ['string', ['enum' => ['1', '2'], 'minLength' => 2], [1, '1', 'abc']],
    ['array', ['uniqueItems' => true], [[['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]], [1, '1'], [1.0, 1]]],
    ['array', ['minItems' => 2, 'items' => ['type' => 'integer']], ['a', [1, 'x'], 5]],
    ['string', ['format' => 'email'], ['abc', 'a@b.co']],
    ['string', ['format' => 'email', 'enum' => ['abc', 'a@b.co']], ['x', 'abc', 'a@b.co']],
    ['null', ['enum' => [null]], [null, 'a']],
    ['object', ['enum' => [['x' => 1]], 'required' => ['y']], [['x' => 1], ['y' => 1]]],
];
$say('whole validator', array_map(static fn ($case) => [$case[0], $case[1], $each($case[2], static fn ($v) => rest_validate_value_from_schema($v, $case[1] + ['type' => $case[0]], 'p'))], $whole));

$say('pattern', array_map(static fn ($pair) => [$pair, rest_validate_json_schema_pattern($pair[0], $pair[1])], [['^a', 'abc'], ['^a', 'bac'], ['a#b', 'a#b'], ['#', '#'], ['é', 'café'], ['\\d+', 'x12'], ['a/b', 'a/b'], ['^abc$', 'ABC'], ['^[a-z]+$', 'abc'], ['b', ''], ['', 'x'], ['^\\p{L}+$', 'héllo']]));

$say('unique items', array_map(static fn ($a) => [$a, rest_validate_array_contains_unique_items($a)], [[1, 2, 3], [1, 1], [1, '1'], [true, 1], [null, 0], [[1, 2], [1, 2]], [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]], [1.0, 1], ['a', 'A'], [], [['a' => 1], ['a' => '1']], [false, 0, '']]));

$say('intersect keys', array_map(static fn ($pair) => [$pair, _rest_array_intersect_key_recursive($pair[0], $pair[1])], [
    [['a' => 1, 'b' => ['c' => 2, 'd' => 3]], ['a' => true, 'b' => ['c' => true]]],
    [['a' => ['x' => 1]], ['a' => true]],
    [[1, 2, 3], [0 => 1, 2 => 1]],
    [['a' => 1], ['a' => ['b' => 1]]],
    [['a' => ['b' => ['c' => 1, 'd' => 2]], 'e' => 3], ['a' => ['b' => ['d' => true]]]],
    [[], ['a' => 1]],
]));

$say('combining error', array_map(static fn ($error) => $out(rest_format_combining_operation_error('p', $error)), [
    ['schema' => ['title' => 'Thing'], 'error_object' => new WP_Error('rest_invalid_type', 'p is not of type string.', ['param' => 'p']), 'index' => 0],
    ['schema' => ['title' => ''], 'error_object' => new WP_Error('rest_invalid_type', 'p is not of type string.'), 'index' => 2],
    ['schema' => [], 'error_object' => new WP_Error('rest_too_long', 'p must be at most 2 characters long.'), 'index' => 1],
    ['schema' => ['title' => 'Other', 'type' => 'object'], 'error_object' => new WP_Error('rest_property_required', 'x is a required property of p.', ['a' => 1]), 'index' => 3],
]));

// What the application-password check leaves for the server, and what the server then reads.
$appStatus = static fn (): array => [$GLOBALS['wp_rest_application_password_status'] ?? 'unset', $GLOBALS['wp_rest_application_password_uuid'] ?? 'unset', rest_get_authenticated_app_password()];
unset($GLOBALS['wp_rest_application_password_status'], $GLOBALS['wp_rest_application_password_uuid']);
$say('app password unset', $appStatus());
rest_application_password_collect_status(new WP_Error('incorrect_password', 'The provided password is an invalid application password.'));
$say('app password refused', [$out($GLOBALS['wp_rest_application_password_status'] ?? 'unset'), $GLOBALS['wp_rest_application_password_uuid'] ?? 'unset', rest_get_authenticated_app_password()]);
$say('app password check after refusal', [$out(rest_application_password_check_errors(null)), $out(rest_application_password_check_errors(true)), $out(rest_application_password_check_errors(new WP_Error('other', 'Other.'))), $out(rest_application_password_check_errors(false))]);
rest_application_password_collect_status(get_user_by('id', 1), ['uuid' => 'zz-probe-uuid', 'name' => 'Probe']);
$say('app password accepted', [$out($GLOBALS['wp_rest_application_password_status'] ?? 'unset'), $GLOBALS['wp_rest_application_password_uuid'] ?? 'unset', rest_get_authenticated_app_password()]);
$say('app password check after success', [$out(rest_application_password_check_errors(null)), $out(rest_application_password_check_errors(true)), $out(rest_application_password_check_errors(new WP_Error('other', 'Other.')))]);
rest_application_password_collect_status(new WP_Error('incorrect_password', 'Refused after a success.'));
$say('app password refused after success', [$out($GLOBALS['wp_rest_application_password_status'] ?? 'unset'), $GLOBALS['wp_rest_application_password_uuid'] ?? 'unset', rest_get_authenticated_app_password()]);
rest_application_password_collect_status(get_user_by('id', 1));
$say('app password accepted without uuid', [$out($GLOBALS['wp_rest_application_password_status'] ?? 'unset'), $GLOBALS['wp_rest_application_password_uuid'] ?? 'unset', rest_get_authenticated_app_password()]);
unset($GLOBALS['wp_rest_application_password_status'], $GLOBALS['wp_rest_application_password_uuid']);
$say('app password check unset', [$out(rest_application_password_check_errors(null))]);

// The cookie check's verdict, left by its default hooks on the auth_cookie_* actions as they fire.
$cookie = [];
foreach (['auth_cookie_malformed' => ['zz', 'logged_in'], 'auth_cookie_expired' => [['username' => 'zz']], 'auth_cookie_bad_username' => [['username' => 'zz']], 'auth_cookie_bad_hash' => [['username' => 'zz']], 'auth_cookie_bad_session_token' => [['username' => 'zz']], 'auth_cookie_valid' => [['username' => 'admin'], get_user_by('id', 1)]] as $hook => $args) {
    unset($GLOBALS['wp_rest_auth_cookie']);
    do_action($hook, ...$args);
    $cookie[$hook] = $GLOBALS['wp_rest_auth_cookie'] ?? 'unset';
}
// Hooked anywhere else, the verdict is the hook name after the twelve characters auth_cookie_ takes.
add_action('zz_probe_cookie_hook', 'rest_cookie_collect_status');
unset($GLOBALS['wp_rest_auth_cookie']);
do_action('zz_probe_cookie_hook');
$cookie['zz_probe_cookie_hook'] = $GLOBALS['wp_rest_auth_cookie'] ?? 'unset';
remove_action('zz_probe_cookie_hook', 'rest_cookie_collect_status');
unset($GLOBALS['wp_rest_auth_cookie']);
$say('cookie status', $cookie);

// Where each of these is hooked by default.
$hooked = [];
foreach (['rest_cookie_collect_status', 'rest_application_password_collect_status', 'rest_application_password_check_errors', 'rest_output_rsd', 'create_initial_rest_routes', 'rest_handle_deprecated_function', 'rest_handle_deprecated_argument', 'rest_handle_doing_it_wrong'] as $fn) {
    $hooked[$fn] = [];
    foreach ($GLOBALS['wp_filter'] as $hook => $wpHook) {
        $priority = has_filter((string) $hook, $fn);
        if ($priority !== false) {
            $hooked[$fn][] = $hook . ':' . $priority;
        }
    }
    sort($hooked[$fn]);
}
$say('default hooks', $hooked);

ob_start();
rest_output_rsd();
$say('rsd', ob_get_clean());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
