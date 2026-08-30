<?php
/**
 * Behaviour probe for the connectors registry (the site's AI providers and
 * external services) and the old-post comment closer. Same protocol as
 * api-probe.php: rows of [label, value], run on the reference with
 * `wp eval-file` and on the engine through run-api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$plain = static function ($value) use (&$plain) {
    if (is_array($value)) {
        return array_map($plain, $value);
    }
    if ($value instanceof Closure) {
        return '{closure}';
    }
    return $value;
};
$warnings = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$warnings): void {
    $warnings[] = sprintf('Function %s was called incorrectly. %s', $function, trim(strip_tags((string) $message)));
}, 10, 2);
$wrong = static function () use (&$warnings): array {
    $out = array_values(array_unique($warnings));
    $warnings = [];
    return $out;
};

if (!did_action('init') && class_exists(Minn\Runtime\Plugins::class)) {
    // A CLI boot of the engine has not run the lifecycle yet; the reference has by the time eval-file runs.
    Minn\Runtime\Plugins::load(Minn\Runtime\Runtime::current());
}
$registry = WP_Connector_Registry::get_instance();
$say('registry', [get_class($registry), $registry === WP_Connector_Registry::get_instance(), did_action('wp_connectors_init')]);
$say('default ids', array_keys(wp_get_connectors()));
$say('default rows', $plain(wp_get_connectors()));
$say('get_connector', [wp_get_connector('anthropic') === wp_get_connectors()['anthropic'], wp_get_connector('nope'), $wrong()]);
$say('is_registered', [wp_is_connector_registered('anthropic'), wp_is_connector_registered('akismet'), wp_is_connector_registered('nope'), wp_is_connector_registered('')]);
$say('get_registered', [$registry->get_registered('openai') === wp_get_connector('openai'), $registry->get_registered('nope'), $wrong()]);
$say('is_active default', array_map(static fn (array $c) => isset($c['plugin']['is_active']) ? (bool) call_user_func($c['plugin']['is_active']) : null, wp_get_connectors()));
$say('registered settings', array_values(array_filter(array_keys(get_registered_settings()), static fn (string $k): bool => str_starts_with($k, 'connectors_') || $k === 'wordpress_api_key')));

// Registration rules.
foreach (['minn_probe_a', 'minn_probe_b', 'minn_probe_c', 'minn-probe-d', 'minn_probe_e'] as $id) {
    if ($registry->is_registered($id)) {
        $registry->unregister($id);
    }
}
$say('register no type', [$registry->register('minn_probe_a', ['name' => 'A']), $wrong()]);
$say('register empty type', [$registry->register('minn_probe_a', ['name' => 'A', 'type' => '']), $wrong()]);
$say('register no name', [$plain($registry->register('minn_probe_a', ['type' => 'custom'])), $wrong()]);
$say('register first', [$plain($registry->register('minn_probe_a', ['name' => 'A', 'type' => 'custom', 'authentication' => ['method' => 'none']])), $wrong()]);
$say('register dup', [$registry->register('minn_probe_a', ['name' => 'A2', 'type' => 'custom', 'authentication' => ['method' => 'none']]), $wrong()]);
$say('register dup before type', [$registry->register('minn_probe_a', []), $wrong()]);
$say('register bad id before type', [$registry->register('Bad!', []), $wrong()]);
$say('register bad ids', [$registry->register('', ['name' => 'X', 'type' => 'custom']), $registry->register('Bad Id!', ['name' => 'X', 'type' => 'custom']), $registry->register('UPPER', ['name' => 'X', 'type' => 'custom']), $registry->register('with.dot', ['name' => 'X', 'type' => 'custom']), $wrong()]);
$say('register hyphen id', [$plain($registry->register('minn-probe-d', ['name' => 'D', 'type' => 'custom'])), $wrong()]);
$say('register full', [$plain($registry->register('minn_probe_b', ['name' => 'B', 'description' => 'desc', 'type' => 'cloud_service', 'logo_url' => 'https://example.invalid/logo.svg', 'authentication' => ['method' => 'api_key', 'credentials_url' => 'https://example.invalid/keys', 'setting_name' => 'minn_probe_b_key', 'constant_name' => 'MINN_PROBE_B', 'env_var_name' => 'MINN_PROBE_B', 'extra' => 'x'], 'plugin' => ['file' => 'minn-probe/plugin.php', 'slug' => 'minn-probe'], 'extra' => 'dropped?'])), $wrong()]);
$say('register auth none', [$plain($registry->register('minn_probe_c', ['name' => 'C', 'type' => 'cloud_service', 'authentication' => ['method' => 'none']])), $wrong()]);
$say('register is_active callable', [$plain($registry->register('minn_probe_e', ['name' => 'E', 'type' => 'custom', 'authentication' => ['method' => 'none'], 'plugin' => ['file' => 'e/e.php', 'is_active' => '__return_false']])), $wrong()]);
$say('register is_active not callable', [$registry->register('minn_probe_g', ['name' => 'G', 'type' => 'custom', 'authentication' => ['method' => 'none'], 'plugin' => ['file' => 'g/g.php', 'is_active' => 'not_a_function']]), $wrong()]);
$say('register auth empty', [$registry->register('minn_probe_g', ['name' => 'G', 'type' => 'custom', 'authentication' => []]), $wrong()]);
$say('register auth unknown method', [$registry->register('minn_probe_g', ['name' => 'G', 'type' => 'custom', 'authentication' => ['method' => 'weird']]), $wrong()]);
$say('register api_key no setting', [$plain($registry->register('minn_probe_g', ['name' => 'G', 'type' => 'kind', 'authentication' => ['method' => 'api_key']])), $wrong()]);
$say('register application_password', [$plain($registry->register('minn_probe_h', ['name' => 'H', 'type' => 'kind', 'authentication' => ['method' => 'application_password', 'setting_name' => 'minn_probe_h_creds']])), $wrong()]);
$say('register plugin without file', [$plain($registry->register('minn_probe_i', ['name' => 'I', 'type' => 'kind', 'authentication' => ['method' => 'none'], 'plugin' => ['slug' => 'i']])), $wrong()]);
$say('register whitespace name', [$plain($registry->register('minn_probe_j', ['name' => '  ', 'type' => 'kind', 'authentication' => ['method' => 'none'], 'description' => 5, 'logo_url' => 'rel/logo.svg'])), $wrong()]);
$say('register non-string values', [$plain($registry->register('minn_probe_f', ['name' => 5, 'type' => 'custom', 'description' => ['x'], 'authentication' => 'api_key', 'plugin' => 'file.php'])), $wrong()]);
$say('ids after register', array_keys(wp_get_connectors()));
$say('unregister', [$plain($registry->unregister('minn_probe_a')), $registry->unregister('minn_probe_a'), $registry->unregister('nope'), $wrong(), wp_is_connector_registered('minn_probe_a')]);
foreach (['minn_probe_b', 'minn_probe_c', 'minn-probe-d', 'minn_probe_e', 'minn_probe_f', 'minn_probe_g', 'minn_probe_h', 'minn_probe_i', 'minn_probe_j'] as $id) {
    if ($registry->is_registered($id)) {
        $registry->unregister($id);
    }
}
$say('ids after cleanup', array_keys(wp_get_connectors()));

// The init hook hands the registry to third parties.
$seen = [];
add_action('wp_connectors_init', static function ($arg) use (&$seen): void {
    $seen[] = [get_class($arg), $arg === WP_Connector_Registry::get_instance()];
    $arg->register('minn_probe_hooked', ['name' => 'Hooked', 'type' => 'custom', 'authentication' => ['method' => 'none']]);
}, 20);
do_action('wp_connectors_init', $registry);
$say('hook arg', [$seen, $wrong()]);
$say('hooked registered', [wp_is_connector_registered('minn_probe_hooked'), $plain(wp_get_connector('minn_probe_hooked'))]);
$registry->unregister('minn_probe_hooked');
$say('lifecycle hooks', [has_action('init', '_wp_connectors_init'), has_action('init', '_wp_register_default_connector_settings'), has_action('init', '_wp_connectors_pass_default_keys_to_ai_client')]);

// A new registry instance can be swapped in.
$fresh = new WP_Connector_Registry();
$say('fresh registry', [count($fresh->get_all_registered()), $fresh->is_registered('anthropic')]);
WP_Connector_Registry::set_instance($fresh);
$say('swapped registry', [count(wp_get_connectors()), WP_Connector_Registry::get_instance() === $fresh]);
WP_Connector_Registry::set_instance($registry);
$say('restored registry', count(wp_get_connectors()));

// Key masking and key sources.
$say('mask', array_map('_wp_connectors_mask_api_key', ['', 'a', 'abc', 'abcd', 'abcde', 'abcdef', 'abcdefg', 'abcdefgh', 'abcdefghi', 'sk-ant-1234567890abcdef', 'ünïcödé-key-value', str_repeat('x', 20), str_repeat('x', 21), str_repeat('x', 40)]));
$say('logo and validity', [_wp_connectors_resolve_ai_provider_logo_url('rel.svg'), _wp_connectors_resolve_ai_provider_logo_url(''), _wp_connectors_is_ai_api_key_valid('', 'anthropic'), _wp_connectors_is_ai_api_key_valid('x', 'nope'), $wrong()]);
delete_option('minn_probe_key');
$say('source none', _wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_ENV_UNSET', 'MINN_PROBE_CONST_UNSET'));
update_option('minn_probe_key', 'stored-value');
$say('source database', [_wp_connectors_get_api_key_source('minn_probe_key'), _wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_ENV_UNSET', 'MINN_PROBE_CONST_UNSET')]);
putenv('MINN_PROBE_ENV=from-env');
$say('source env', [_wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_ENV'), _wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_ENV', 'MINN_PROBE_CONST_UNSET')]);
putenv('MINN_PROBE_ENV');
putenv('MINN_PROBE_EMPTY=');
$say('source empty env', _wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_EMPTY'));
putenv('MINN_PROBE_EMPTY');
define('MINN_PROBE_CONST', 'from-const');
define('MINN_PROBE_CONST_EMPTY', '');
define('MINN_PROBE_CONST_INT', 5);
$say('source constant', [_wp_connectors_get_api_key_source('minn_probe_key', '', 'MINN_PROBE_CONST'), _wp_connectors_get_api_key_source('minn_probe_key', 'MINN_PROBE_ENV_UNSET', 'MINN_PROBE_CONST'), _wp_connectors_get_api_key_source('minn_probe_key', '', 'MINN_PROBE_CONST_EMPTY'), _wp_connectors_get_api_key_source('minn_probe_key', '', 'MINN_PROBE_CONST_INT')]);
update_option('minn_probe_key', '');
$say('source empty option', _wp_connectors_get_api_key_source('minn_probe_key'));
delete_option('minn_probe_key');

// Application-password credentials.
$say('parse credentials', array_map('wp_connectors_parse_application_password_credentials', ['', 'user:pass word', 'u:p:x', 'nocolon', ':', 'u:', ':p', ' user : pass ']));
$say('sanitize credentials', [wp_connectors_sanitize_application_password_credentials('user:pass'), wp_connectors_sanitize_application_password_credentials(['username' => 'u', 'password' => 'p']), wp_connectors_sanitize_application_password_credentials(''), wp_connectors_sanitize_application_password_credentials(['username' => 'u', 'password' => 'p', 'extra' => 1]), wp_connectors_sanitize_application_password_credentials(['username' => ' u ', 'password' => ' p ']), wp_connectors_sanitize_application_password_credentials(['username' => 'u']), wp_connectors_sanitize_application_password_credentials(['username' => '<b>u</b>', 'password' => 'a<b>b']), wp_connectors_sanitize_application_password_credentials(['username' => 5, 'password' => ['x']]), wp_connectors_sanitize_application_password_credentials(null)]);
$say('credentials from auth', [wp_connectors_get_application_password_credentials([]), wp_connectors_get_application_password_credentials(['method' => 'application_password']), wp_connectors_get_application_password_credentials(['method' => 'application_password', 'setting_name' => 'minn_probe_creds'])]);
update_option('minn_probe_creds', ['username' => 'u', 'password' => 'p']);
$say('credentials stored', wp_connectors_get_application_password_credentials(['method' => 'application_password', 'setting_name' => 'minn_probe_creds']));
update_option('minn_probe_creds', 'u2:p2');
$say('credentials stored string', wp_connectors_get_application_password_credentials(['method' => 'application_password', 'setting_name' => 'minn_probe_creds']));
putenv('MINN_PROBE_CREDS=envu:envp');
$say('credentials env', wp_connectors_get_application_password_credentials(['method' => 'application_password', 'setting_name' => 'minn_probe_creds', 'env_var_name' => 'MINN_PROBE_CREDS']));
putenv('MINN_PROBE_CREDS');
define('MINN_PROBE_CREDS_CONST', 'cu:cp');
$say('credentials constant', wp_connectors_get_application_password_credentials(['method' => 'application_password', 'setting_name' => 'minn_probe_creds', 'constant_name' => 'MINN_PROBE_CREDS_CONST']));
delete_option('minn_probe_creds');

// The old-post comment closer.
$say('comments_open hooks', [has_filter('comments_open', '_close_comments_for_old_post'), has_filter('comments_open', '_close_comments_for_old_post') === 10]);
$optOn = get_option('close_comments_for_old_posts');
$optDays = get_option('close_comments_days_old');
foreach (get_posts(['post_status' => 'any', 'numberposts' => -1, 'post_type' => 'any', 's' => 'zz-probe-old']) as $stale) {
    wp_delete_post($stale->ID, true);
}
update_option('close_comments_for_old_posts', '');
$oldPost = wp_insert_post(['post_title' => 'zz-probe-old-post', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => gmdate('Y-m-d H:i:s', time() - 15 * 86400), 'post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 15 * 86400)]);
$oldPage = wp_insert_post(['post_title' => 'zz-probe-old-page', 'post_status' => 'publish', 'post_type' => 'page', 'post_date' => gmdate('Y-m-d H:i:s', time() - 15 * 86400), 'post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 15 * 86400)]);
$newPost = wp_insert_post(['post_title' => 'zz-probe-old-new', 'post_status' => 'publish', 'post_type' => 'post', 'post_date' => gmdate('Y-m-d H:i:s', time() - 13 * 86400), 'post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 13 * 86400)]);
$say('closer off', [_close_comments_for_old_post(true, $oldPost), _close_comments_for_old_post(false, $oldPost), _close_comments_for_old_post(true, 0)]);
update_option('close_comments_for_old_posts', '1');
update_option('close_comments_days_old', '14');
$say('closer on', [_close_comments_for_old_post(true, $oldPost), _close_comments_for_old_post(true, $newPost), _close_comments_for_old_post(true, $oldPage), _close_comments_for_old_post(false, $oldPost), _close_comments_for_old_post(false, $newPost)]);
$say('closer bad ids', [_close_comments_for_old_post(true, 999999999)]);
$GLOBALS['post'] = get_post($oldPost);
$say('closer global old', [_close_comments_for_old_post(true, 0), _close_comments_for_old_post(true, null), _close_comments_for_old_post(true, ''), comments_open(), comments_open(0), comments_open($newPost)]);
$GLOBALS['post'] = get_post($newPost);
$say('closer global new', [_close_comments_for_old_post(true, 0), comments_open()]);
unset($GLOBALS['post']);
update_option('close_comments_days_old', '0');
$say('closer days zero', _close_comments_for_old_post(true, $oldPost));
update_option('close_comments_days_old', '15');
$say('closer days fifteen', [_close_comments_for_old_post(true, $oldPost), _close_comments_for_old_post(true, $newPost)]);
update_option('close_comments_days_old', '14');
add_filter('close_comments_posts_types', static fn ($t) => ['page'], 99);
$say('closer types page', [_close_comments_for_old_post(true, $oldPost), _close_comments_for_old_post(true, $oldPage)]);
remove_all_filters('close_comments_posts_types', 99);
register_post_type('minn_probe_cpt', ['public' => true, 'supports' => ['title', 'comments']]);
$oldCpt = wp_insert_post(['post_title' => 'zz-probe-old-cpt', 'post_status' => 'publish', 'post_type' => 'minn_probe_cpt', 'post_date' => gmdate('Y-m-d H:i:s', time() - 15 * 86400), 'post_date_gmt' => gmdate('Y-m-d H:i:s', time() - 15 * 86400)]);
$say('closer cpt', [get_post($oldCpt)->post_type, _close_comments_for_old_post(true, $oldCpt)]);
wp_delete_post($oldCpt, true);
unregister_post_type('minn_probe_cpt');
wp_update_post(['ID' => $oldPost, 'post_date' => gmdate('Y-m-d H:i:s', time() - 13 * 86400)]);
$say('closer gmt date rules', [substr(get_post($oldPost)->post_date, 0, 10) !== substr(get_post($oldPost)->post_date_gmt, 0, 10), _close_comments_for_old_post(true, $oldPost)]);
$say('closer types default', apply_filters('close_comments_posts_types', ['post']));
wp_update_post(['ID' => $oldPost, 'post_status' => 'draft']);
$say('closer draft', _close_comments_for_old_post(true, $oldPost));
wp_update_post(['ID' => $oldPost, 'post_status' => 'publish', 'comment_status' => 'closed']);
$say('closer closed status', [_close_comments_for_old_post(true, $oldPost), comments_open($oldPost)]);
wp_update_post(['ID' => $newPost, 'comment_status' => 'open']);
$say('comments_open new open', comments_open($newPost));
foreach ([$oldPost, $oldPage, $newPost] as $id) {
    wp_delete_post($id, true);
}
update_option('close_comments_for_old_posts', $optOn);
update_option('close_comments_days_old', $optDays);
$say('options restored', [(string) get_option('close_comments_for_old_posts'), (string) get_option('close_comments_days_old')]);

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
