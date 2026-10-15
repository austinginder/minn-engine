<?php
/**
 * Plugin Name: Minn test ajax
 * Description: Fixture for the ajax suite: admin-ajax.php handlers in the shapes plugins register them, and a sign-in a plugin refuses through authenticate.
 * Version: 1.0.0
 * License: MIT
 */

// Where the request has been by the time a handler runs.
foreach (['plugins_loaded', 'init', 'wp_loaded', 'admin_menu', 'admin_init', 'current_screen', 'admin_enqueue_scripts', 'wp', 'template_redirect', 'send_headers', 'parse_request'] as $minnTestAjaxHook) {
    add_action($minnTestAjaxHook, static function () use ($minnTestAjaxHook): void {
        $GLOBALS['minn_test_ajax_trail'][] = $minnTestAjaxHook;
    }, 1);
}

function minn_test_ajax_echo(): void
{
    echo 'echo:' . sanitize_text_field(wp_unslash($_REQUEST['word'] ?? '')) . ':user=' . get_current_user_id();
}
add_action('wp_ajax_nopriv_minn_echo', 'minn_test_ajax_echo');
add_action('wp_ajax_minn_echo', 'minn_test_ajax_echo');

add_action('wp_ajax_minn_private', static function (): void {
    echo 'private:' . get_current_user_id();
});

add_action('wp_ajax_nopriv_minn_json', static function (): void {
    wp_send_json_success(['word' => $_POST['word'] ?? null, 'method' => $_SERVER['REQUEST_METHOD'] ?? '']);
});
add_action('wp_ajax_nopriv_minn_json_error', static function (): void {
    wp_send_json_error(['why' => 'no'], 422);
});
add_action('wp_ajax_nopriv_minn_json_plain', static function (): void {
    wp_send_json(['a' => 1, 'b' => [true, null]]);
});

add_action('wp_ajax_nopriv_minn_die', static function (): void {
    wp_die('stopped here');
});
add_action('wp_ajax_nopriv_minn_die_status', static function (): void {
    wp_die('forbidden here', '', ['response' => 403]);
});
add_action('wp_ajax_nopriv_minn_die_empty', static function (): void {
    wp_die();
});
add_action('wp_ajax_nopriv_minn_die_error', static function (): void {
    wp_die(new WP_Error('minn_bad', 'A WP_Error message'));
});
add_action('wp_ajax_nopriv_minn_die_int', static function (): void {
    wp_die(-1);
});

add_action('wp_ajax_nopriv_minn_mint', static function (): void {
    echo wp_create_nonce('minn-test-ajax');
});
add_action('wp_ajax_minn_mint', static function (): void {
    echo wp_create_nonce('minn-test-ajax');
});
add_action('wp_ajax_nopriv_minn_nonce', static function (): void {
    check_ajax_referer('minn-test-ajax', 'nonce');
    echo 'nonce ok';
});
add_action('wp_ajax_minn_nonce', static function (): void {
    check_ajax_referer('minn-test-ajax', 'nonce');
    echo 'nonce ok:' . get_current_user_id();
});
add_action('wp_ajax_nopriv_minn_nonce_soft', static function (): void {
    $verdict = check_ajax_referer('minn-test-ajax', 'nonce', false);
    echo 'soft:' . var_export($verdict, true);
});

add_action('wp_ajax_nopriv_minn_state', static function (): void {
    echo wp_json_encode([
        'is_admin' => is_admin(),
        'doing_ajax' => wp_doing_ajax(),
        'wp_admin' => defined('WP_ADMIN') && WP_ADMIN,
        'admin_init' => did_action('admin_init'),
        'trail' => $GLOBALS['minn_test_ajax_trail'] ?? [],
        'action' => $_REQUEST['action'] ?? null,
        'get_action' => $_GET['action'] ?? null,
        'logged_in' => is_user_logged_in(),
        'pagenow' => $GLOBALS['pagenow'] ?? null,
        'is_network_admin' => is_network_admin(),
        'is_blog_admin' => is_blog_admin(),
        'core_actions' => $GLOBALS['minn_test_ajax_core'] ?? [],
        'heartbeat_now' => has_action('wp_ajax_nopriv_heartbeat'),
        'rest_nonce_now' => has_action('wp_ajax_rest-nonce'),
    ]);
});
add_action('wp_ajax_minn_state', static function (): void {
    echo wp_json_encode(['logged_in' => is_user_logged_in(), 'user' => get_current_user_id(), 'can_edit' => current_user_can('edit_posts')]);
});

add_action('wp_ajax_nopriv_minn_headers', static function (): void {
    // The status wp_die() leaves depends on whether the echo below has gone out:
    // a host that buffers output (php.ini-production's 4096, most hosting) answers
    // 200, one that does not (Cove's FrankenPHP has no php.ini) keeps the 201.
    // The engine always buffers; buffer here so both stacks are a buffered host.
    ob_start();
    header('X-Minn-Test: 1');
    status_header(201);
    echo 'made';
    wp_die();
});
add_action('wp_ajax_nopriv_minn_exit', static function (): void {
    echo 'bye';
    exit;
});

// When the core ajax actions become visible to a plugin.
foreach (['init', 'wp_loaded', 'admin_init'] as $minnTestAjaxHook) {
    add_action($minnTestAjaxHook, static function () use ($minnTestAjaxHook): void {
        $GLOBALS['minn_test_ajax_core'][$minnTestAjaxHook] = has_action('wp_ajax_nopriv_heartbeat');
    }, 1);
}

// Handlers that only exist on an admin request.
if (is_admin()) {
    add_action('wp_ajax_nopriv_minn_admin_only', static function (): void {
        echo 'admin-only handler';
    });
}
add_action('admin_init', static function (): void {
    add_action('wp_ajax_nopriv_minn_from_admin_init', static function (): void {
        echo 'from admin_init';
    });
});

// A plugin refusing a sign-in: the editor, when the form asks for it.
add_filter('authenticate', static function ($user, $username) {
    if ($username === 'editor' && !empty($_POST['minn_refuse'])) {
        return new WP_Error('minn_refused', '<strong>Error:</strong> A plugin refused this sign-in.');
    }
    return $user;
}, 30, 2);
add_filter('wp_authenticate_user', static function ($user) {
    if ($user instanceof WP_User && $user->user_login === 'editor' && !empty($_POST['minn_refuse_user'])) {
        return new WP_Error('minn_refused_user', '<strong>Error:</strong> This account is paused.');
    }
    return $user;
});
add_action('wp_login_failed', static function ($login, $error = null): void {
    update_option('minn_test_ajax_failed', [$login, is_wp_error($error) ? $error->get_error_code() : null], false);
}, 10, 2);
add_action('wp_authenticate', static function ($login): void {
    update_option('minn_test_ajax_authenticate', (string) $login, false);
});

// A plugin on the signed-in heartbeat: a nonce for it, what it is handed and
// what it adds (only for the suite's own data key), and the tick it hears.
add_action('wp_ajax_minn_mint_heartbeat', static function (): void {
    echo wp_create_nonce('heartbeat-nonce');
});
add_filter('heartbeat_received', static function ($response, $data, $screen) {
    if (isset($data['minn_test'])) {
        $response['minn_received'] = [$data['minn_test'], $screen, array_keys($response)];
    }
    return $response;
}, 10, 3);
add_filter('heartbeat_send', static function ($response, $screen) {
    $response['minn_send'] = [$screen, array_keys($response)];
    return $response;
}, 10, 2);
add_action('heartbeat_tick', static function ($response, $screen): void {
    header('X-Minn-Test: tick ' . $screen . ' ' . implode(',', array_keys($response)));
}, 10, 2);
add_filter('wp_refresh_nonces', static function ($response, $data, $screen) {
    $response['minn_refresh'] = [$screen, array_keys($response), isset($data['minn_test'])];
    return $response;
}, 10, 3);

// A wp-admin page a plugin answers itself, as a page builder opens its editor
// (post.php?post=N&action=elementor): the handler prints a page and ends the request.
add_action('admin_action_minn_door', static function (): void {
    header('X-Minn-Test: door');
    echo 'door ' . (is_admin() ? 'admin' : 'front') . ' ' . ($GLOBALS['pagenow'] ?? '') . ' user ' . get_current_user_id() . ' admin_init ' . did_action('admin_init');
    exit;
});

// The media modal's upload (async-upload.php, action upload-attachment): the page's action fires
// first, saying how the request was booted; WordPress's own upload, or the pack's, answers after it.
add_action('admin_action_upload-attachment', static function (): void {
    header('X-Minn-Test: upload ' . (wp_doing_ajax() ? 'ajax' : 'page') . ' ' . (is_admin() ? 'admin' : 'front') . ' ' . ($GLOBALS['pagenow'] ?? '') . ' user ' . get_current_user_id() . ' admin_init ' . did_action('admin_init'));
});
add_action('wp_ajax_minn_mint_upload', static function (): void {
    echo wp_create_nonce('media-form');
});

// Editors that need the Minn Legacy Pack, as page builders declare them (minn_legacy_pack_editors): one
// opened by a wp-admin action, and one a theme opens by a query on the page it edits (as Bricks does). While
// the pack is not active the engine sends either to Minn Admin's offer; once it is, the editor opens.
add_filter('minn_legacy_pack_editors', static function ($editors) {
    $editors['minn-test-ajax/minn-test-ajax.php'] = ['name' => 'Test Door', 'action' => 'minn_door_pack'];
    $editors['twentytwentyfive'] = ['name' => 'Test Front', 'query' => 'minn_front_pack=on', 'theme' => true];
    return $editors;
});
// A plugin script that needs one of WordPress's own scripts the engine carries no file for (wp-util), on a page
// that asks for it: while the Minn Legacy Pack is not active, the engine notes the plugin for Minn Admin's offer.
add_action('wp_enqueue_scripts', static function (): void {
    if (isset($_GET['minn-test-wants-pack'])) {
        wp_enqueue_script('minn-test-wants-pack', content_url('/plugins/minn-test-ajax/wants-pack.js'), ['wp-util'], '1.0.0');
    }
});
add_action('admin_action_minn_door_pack', static function (): void {
    header('X-Minn-Test: door-pack');
    echo 'door-pack opened';
    exit;
});
