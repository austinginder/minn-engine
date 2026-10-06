<?php
/**
 * Plugin Name: Minn test REST envelope
 * Description: Fixture for the rest-envelope suite: hooks the REST server's filters the way plugins do (refuse before the callbacks, answer at dispatch, edit after, add headers after dispatch, serve the body itself, rewrite what is echoed, narrow capabilities), chosen per request by the X-Minn-Envelope header, and records what each filter was handed.
 * Version: 1.0.0
 * License: MIT
 */

$minnEnvelopeModes = array_filter(explode(',', (string) ($_SERVER['HTTP_X_MINN_ENVELOPE'] ?? '')));

add_action('rest_api_init', static function (): void {
    register_rest_route('minn-test/v1', '/echo', [
        'methods' => 'GET',
        'callback' => static fn (WP_REST_Request $request) => ['echo' => (string) $request->get_param('word')],
        'permission_callback' => '__return_true',
        'args' => ['word' => ['type' => 'string', 'default' => 'none']],
    ]);
});

if ($minnEnvelopeModes === []) {
    return;
}

/** What a filter was handed, in words that mean the same on both stacks. */
function minn_test_envelope_describe($value): string
{
    if ($value === null) {
        return 'null';
    }
    if ($value instanceof WP_Error) {
        return 'error:' . $value->get_error_code() . ':' . (int) ($value->get_error_data()['status'] ?? 0);
    }
    if ($value instanceof WP_REST_Response) {
        return 'response:' . $value->get_status();
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return gettype($value);
}

/** The handler as plugins read it: its keys, and whether each callable is callable. */
function minn_test_envelope_handler($handler): array
{
    if (!is_array($handler)) {
        return [gettype($handler)];
    }
    $keys = array_keys($handler);
    sort($keys);
    return [
        'keys' => $keys,
        'methods' => is_array($handler['methods'] ?? null) ? array_keys($handler['methods']) : ($handler['methods'] ?? null),
        'callback' => is_callable($handler['callback'] ?? null),
        'permission' => is_callable($handler['permission_callback'] ?? null),
        'args' => is_array($handler['args'] ?? null) ? count($handler['args']) > 0 : null,
    ];
}

function minn_test_envelope_request($request): array
{
    if (!$request instanceof WP_REST_Request) {
        return [gettype($request)];
    }
    return [$request->get_method(), $request->get_route(), $request->get_url_params(), $request->get_param('id'), $request->get_param('context')];
}

$GLOBALS['minn_test_envelope_trail'] = [];
$minnEnvelopeNote = static function (string $filter, array $what): void {
    $GLOBALS['minn_test_envelope_trail'][] = [$filter, $what];
};

if (in_array('record', $minnEnvelopeModes, true)) {
    add_filter('rest_request_before_callbacks', static function ($response, $handler, $request) use ($minnEnvelopeNote) {
        $minnEnvelopeNote('rest_request_before_callbacks', [minn_test_envelope_describe($response), minn_test_envelope_handler($handler), minn_test_envelope_request($request)]);
        return $response;
    }, 10, 3);
    add_filter('rest_dispatch_request', static function ($result, $request, $route, $handler) use ($minnEnvelopeNote) {
        $minnEnvelopeNote('rest_dispatch_request', [minn_test_envelope_describe($result), $route, minn_test_envelope_request($request)]);
        return $result;
    }, 10, 4);
    add_filter('rest_request_after_callbacks', static function ($response, $handler, $request) use ($minnEnvelopeNote) {
        $minnEnvelopeNote('rest_request_after_callbacks', [minn_test_envelope_describe($response), minn_test_envelope_handler($handler)]);
        return $response;
    }, 10, 3);
    add_filter('rest_post_dispatch', static function ($response, $server, $request) use ($minnEnvelopeNote) {
        $minnEnvelopeNote('rest_post_dispatch', [minn_test_envelope_describe($response), $server instanceof WP_REST_Server, $response instanceof WP_REST_Response ? $response->get_matched_route() : null, minn_test_envelope_request($request)]);
        return $response;
    }, 10, 3);
    add_filter('rest_pre_serve_request', static function ($served, $result, $request, $server) use ($minnEnvelopeNote) {
        $minnEnvelopeNote('rest_pre_serve_request', [minn_test_envelope_describe($served), minn_test_envelope_describe($result)]);
        return $served;
    }, 10, 4);
    add_filter('rest_pre_echo_response', static function ($result, $server, $request) {
        if (is_array($result)) {
            $result['minn_trail'] = $GLOBALS['minn_test_envelope_trail'];
        }
        return $result;
    }, 10, 3);
}

if (in_array('block-before', $minnEnvelopeModes, true)) {
    add_filter('rest_request_before_callbacks', static fn ($response) => new WP_Error('minn_blocked', 'Blocked by a plugin.', ['status' => 403]));
}
if (in_array('replace-before', $minnEnvelopeModes, true)) {
    add_filter('rest_request_before_callbacks', static fn ($response) => new WP_REST_Response(['replaced' => 'before'], 200));
}
if (in_array('clear-before', $minnEnvelopeModes, true)) {
    add_filter('rest_request_before_callbacks', static fn ($response) => null);
}
if (in_array('answer-dispatch', $minnEnvelopeModes, true)) {
    add_filter('rest_dispatch_request', static fn ($result) => ['answered' => 'dispatch']);
}
if (in_array('answer-dispatch-error', $minnEnvelopeModes, true)) {
    add_filter('rest_dispatch_request', static fn ($result) => new WP_Error('minn_dispatch', 'Refused at dispatch.', ['status' => 409]));
}
if (in_array('edit-after', $minnEnvelopeModes, true)) {
    add_filter('rest_request_after_callbacks', static function ($response) {
        if ($response instanceof WP_Error) {
            return new WP_REST_Response(['recovered' => $response->get_error_code()], 200);
        }
        if ($response instanceof WP_REST_Response) {
            $data = $response->get_data();
            $response->set_data(is_array($data) ? ['minn_after' => true, 'id' => $data['id'] ?? null] : $data);
        }
        return $response;
    });
}
if (in_array('post-dispatch', $minnEnvelopeModes, true)) {
    add_filter('rest_post_dispatch', static function ($response) {
        if ($response instanceof WP_REST_Response) {
            $response->header('X-Minn-Post-Dispatch', 'yes');
            $response->set_status(203);
        }
        return $response;
    });
}
if (in_array('serve', $minnEnvelopeModes, true)) {
    add_filter('rest_pre_serve_request', static function ($served, $result) {
        echo '{"served":"plugin","status":' . (int) $result->get_status() . '}';
        return true;
    }, 10, 2);
}
if (in_array('echo', $minnEnvelopeModes, true)) {
    add_filter('rest_pre_echo_response', static fn ($result) => ['wrapped' => is_array($result) && array_is_list($result) ? count($result) : 'item']);
}
if (in_array('deny-cap', $minnEnvelopeModes, true)) {
    // A role editor plugin taking a capability away for this request.
    add_filter('user_has_cap', static function (array $allcaps): array {
        unset($allcaps['edit_posts'], $allcaps['publish_posts']);
        return $allcaps;
    });
}
if (in_array('map-meta', $minnEnvelopeModes, true)) {
    // A lock plugin refusing deletion of the post it guards.
    add_filter('map_meta_cap', static function (array $caps, string $cap, int $userId, array $args): array {
        $guarded = (int) get_option('minn_test_envelope_guarded', 0);
        return $cap === 'delete_post' && (int) ($args[0] ?? 0) === $guarded && $guarded > 0 ? ['do_not_allow'] : $caps;
    }, 10, 4);
}

// A save a plugin changes on the way in.
if (in_array('save-data', $minnEnvelopeModes, true)) {
    add_filter('wp_insert_post_data', static function (array $data): array {
        $data['post_excerpt'] = 'set by wp_insert_post_data';
        return $data;
    });
}
if (in_array('save-title', $minnEnvelopeModes, true)) {
    add_filter('title_save_pre', static fn ($title) => $title . ' (saved)');
}
if (in_array('save-slug', $minnEnvelopeModes, true)) {
    add_filter('wp_unique_post_slug', static fn ($slug) => $slug . '-zz');
}
if (in_array('save-empty', $minnEnvelopeModes, true)) {
    add_filter('wp_insert_post_empty_content', '__return_true');
}
if (in_array('pre-insert', $minnEnvelopeModes, true)) {
    add_filter('rest_pre_insert_post', static function ($prepared) {
        $prepared->post_title = 'zz envelope save set by rest_pre_insert_post';
        return $prepared;
    });
}
if (in_array('pre-insert-error', $minnEnvelopeModes, true)) {
    add_filter('rest_pre_insert_post', static fn () => new WP_Error('minn_pre_insert', 'Refused before the insert.', ['status' => 422]));
}

// A response a plugin adds to as each item is prepared.
if (in_array('prepare', $minnEnvelopeModes, true)) {
    foreach (['rest_prepare_post', 'rest_prepare_page', 'rest_prepare_attachment', 'rest_prepare_user', 'rest_prepare_comment', 'rest_prepare_category'] as $minnPrepare) {
        add_filter($minnPrepare, static function ($response, $object, $request) use ($minnPrepare) {
            $data = $response->get_data();
            $data['minn_prepared'] = [$minnPrepare, is_object($object) ? get_class($object) : gettype($object), $request instanceof WP_REST_Request ? $request->get_route() : gettype($request), array_keys($response->get_links()) !== []];
            $response->set_data($data);
            $response->add_link('https://api.w.org/minn-prepared', home_url('/prepared/'));
            return $response;
        }, 10, 3);
    }
}

// An upload a plugin allows, checks or refuses, as SVG and security plugins do.
if (in_array('svg', $minnEnvelopeModes, true)) {
    add_filter('upload_mimes', static fn (array $mimes): array => $mimes + ['svg' => 'image/svg+xml']);
    add_filter('wp_check_filetype_and_ext', static fn ($data, $file, $filename) => str_ends_with((string) $filename, '.svg') ? ['ext' => 'svg', 'type' => 'image/svg+xml', 'proper_filename' => false] : $data, 10, 3);
    add_filter('wp_handle_sideload_prefilter', static function (array $file): array {
        if (str_contains((string) file_get_contents($file['tmp_name']), '<script')) {
            $file['error'] = 'The SVG carried a script.';
        }
        return $file;
    });
}
if (in_array('refuse-upload', $minnEnvelopeModes, true)) {
    add_filter('wp_handle_sideload_prefilter', static function (array $file): array {
        $file['error'] = 'Uploads are closed.';
        return $file;
    });
}
