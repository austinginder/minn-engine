<?php
/**
 * Small core classes no probe named yet (probe small-classes): the user-meta
 * session store's own methods; the rewrite matches map; a layer of
 * theme.json data; a personal data request from its post; the HTML API's
 * unsupported exception; and the feed cache's transients. The user and the
 * transients the probe makes are removed again; dropping every user's
 * sessions is intercepted before it deletes anything.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
require_once ABSPATH . 'wp-admin/includes/user.php';
$user = wp_insert_user(['user_login' => 'zz_smc_user', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-smc@example.com', 'role' => 'subscriber']);
register_shutdown_function(static function () use ($user): void {
    if (is_int($user) && $user > 1) {
        wp_delete_user($user);
    }
    delete_site_transient('feed_zz-smc');
    delete_site_transient('feed_mod_zz-smc');
});
if (!is_int($user) || $user <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}

// The store's protected methods, called on the manager get_instance hands out.
$tokens = WP_Session_Tokens::get_instance($user);
$call = static fn (string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $tokens, WP_User_Meta_Session_Tokens::class)(...$args);
$future = time() + 3600;
$meta = static fn () => get_user_meta($GLOBALS['zz_smc_user'], 'session_tokens', true);
$GLOBALS['zz_smc_user'] = $user;
update_user_meta($user, 'session_tokens', ['aaa' => ['expiration' => $future, 'login' => 1], 'bbb' => ['expiration' => 1000, 'login' => 2], 'ccc' => $future]);
$sessions = [
    'the manager' => get_class($tokens) === WP_User_Meta_Session_Tokens::class,
    'read' => $call('get_sessions'),
    'one' => $call('get_session', 'aaa'),
    'an expired one' => $call('get_session', 'bbb'),
    'a bare expiration' => $call('get_session', 'ccc'),
    'none' => $call('get_session', 'zzz'),
    'a session prepared' => $call('prepare_session', ['expiration' => 5, 'ip' => '1.2.3.4']),
    'an expiration prepared' => $call('prepare_session', 77),
];
$call('update_session', 'ddd', ['expiration' => $future, 'login' => 4]);
$sessions['one added'] = $meta();
$call('update_session', 'aaa', null);
$sessions['one ended'] = $meta();
$call('update_sessions', ['eee' => ['expiration' => $future, 'login' => 5], 'fff' => ['expiration' => $future, 'login' => 6]]);
$sessions['replaced'] = $meta();
$call('destroy_other_sessions', 'eee');
$sessions['all but one ended'] = $meta();
$call('destroy_all_sessions');
$sessions['all ended'] = $meta();
$call('update_sessions', []);
$sessions['written empty'] = $meta();
// Dropping every user's sessions, stopped at the delete with what it asked for.
$sessions['every user\'s dropped'] = (static function () {
    $asked = [];
    $stop = static function ($check, $object_id, $meta_key, $meta_value, $delete_all) use (&$asked) {
        $asked[] = [$object_id, $meta_key, $meta_value, $delete_all];
        return false;
    };
    add_filter('delete_user_metadata', $stop, 10, 5);
    WP_User_Meta_Session_Tokens::drop_sessions();
    remove_filter('delete_user_metadata', $stop, 10);
    return $asked;
})();
$say('session store', str_replace((string) $future, '{future}', (string) json_encode($sessions)));

$say('matches map', [
    'applied' => WP_MatchesMapRegex::apply('index.php?p=$matches[1]&name=$matches[2]&zz=$matches[9]', ['whole', '12', 'a b&c']),
    'made' => (static function () {
        $map = new WP_MatchesMapRegex('page=$matches[1]', [0, 'x/y']);
        return [$map->output, $map->_pattern, $map->callback(['$matches[1]'])];
    })(),
    'no matches' => WP_MatchesMapRegex::apply('p=$matches[1]', []),
    'a two-digit index' => WP_MatchesMapRegex::apply('$matches[10]-$matches[1]', array_merge(['w'], range(1, 10))),
    'not an index' => WP_MatchesMapRegex::apply('$matches[0] $matches[x]', ['zero']),
]);

$data = new WP_Theme_JSON_Data(['version' => 3, 'settings' => ['color' => ['custom' => false, 'palette' => [['slug' => 'zz', 'color' => '#111', 'name' => 'ZZ']]]]], 'theme');
$before = $data->get_data();
$returned = $data->update_with(['version' => 3, 'settings' => ['color' => ['custom' => true], 'typography' => ['dropCap' => false]]]);
$say('theme.json data', [
    'read' => $before,
    'updated returns itself' => $returned === $data,
    'after the update' => $data->get_data(),
    'its object' => [get_class($data->get_theme_json()), $data->get_theme_json()->get_raw_data()],
    'default origin' => (new WP_Theme_JSON_Data(['version' => 3, 'styles' => ['color' => ['text' => 'red']]]))->get_data(),
]);

$request = new WP_User_Request((object) ['ID' => 999998, 'post_author' => '7', 'post_title' => 'zz@example.com', 'post_name' => 'export_personal_data', 'post_status' => 'request-pending', 'post_date_gmt' => '2026-01-02 03:04:05', 'post_modified_gmt' => '2026-01-03 04:05:06', 'post_content' => '{"a":1,"b":[2]}', 'post_password' => 'zz-key']);
$say('a personal data request', get_object_vars($request));

$exception = new WP_HTML_Unsupported_Exception('Cannot process.', 'TABLE', 12, '<table>', ['HTML', 'BODY'], ['B']);
$say('unsupported exception', [$exception->getMessage(), $exception->token_name, $exception->token_at, $exception->token, $exception->stack_of_open_elements, $exception->active_formatting_elements, $exception instanceof Exception]);

// The feed classes load with SimplePie on the reference, only when asked.
if (!class_exists('SimplePie\\Cache')) {
    require_once ABSPATH . WPINC . '/class-simplepie.php';
}
if (!class_exists('WP_Feed_Cache_Transient')) {
    require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
}
$lifetime = static fn ($seconds, $name) => $name === 'zz-smc' ? 600 : $seconds;
add_filter('wp_feed_cache_transient_lifetime', $lifetime, 10, 2);
$cache = new WP_Feed_Cache_Transient('ignored', 'zz-smc', 'spc');
$other = new WP_Feed_Cache_Transient('ignored', 'zz-smc-other', 'spc');
remove_filter('wp_feed_cache_transient_lifetime', $lifetime, 10);
$feed = [
    'made' => [$cache->name, $cache->mod_name, $cache->lifetime, $other->lifetime],
    'nothing yet' => [$cache->load(), $cache->mtime()],
    'saved' => $cache->save(['zz' => ['feed', 1]]),
    'loaded' => $cache->load(),
    'its time' => abs((int) $cache->mtime() - time()) < 5,
    'touched' => $cache->touch(),
    'unlinked' => $cache->unlink(),
    'after' => [$cache->load(), $cache->mtime()],
];
$say('feed cache', $feed);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
