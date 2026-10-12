<?php
/**
 * The audit's Lows (2026-10-12) as the reference answers them (probe
 * audit-lows): an attachment of a draft, private or published post read
 * alone and in a list, signed out; a role editable_roles leaves out,
 * assigned over REST; a locale that is not installed, or not a locale; a
 * category assign_term refuses; esc_sql and prepare with a percent; kses
 * protocol entities without their semicolon; the playlist's JSON; and a
 * response cut to limit_response_size. What it makes it deletes.
 */
$out = [];
$say = static function (string $label, $value) use (&$out): void { $out[] = [$label, $value]; };
$home = (string) parse_url(home_url(), PHP_URL_HOST);
$ask = static function (string $method, string $route, array $params = [], int $user = 1) {
    wp_set_current_user($user);
    $r = new WP_REST_Request($method, $route);
    foreach ($params as $k => $v) { $r->set_param($k, $v); }
    $res = rest_do_request($r);
    $d = $res->get_data();
    return [$res->get_status(), is_array($d) ? ($d['code'] ?? (isset($d['id']) ? 'an id' : null)) : null, is_array($d) ? ($d['message'] ?? null) : null];
};
$made = [];
register_shutdown_function(static function () use (&$made) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made as [$kind, $id]) {
        if (!is_int($id) || $id <= 1) { continue; }
        if ($kind === 'post') { wp_delete_post($id, true); }
        if ($kind === 'attachment') { wp_delete_attachment($id, true); }
        if ($kind === 'user') { wp_delete_user($id); }
    }
});

// media single: an attachment of a draft, a private and a trashed post, read anonymously and as an author
foreach (['draft', 'private', 'publish', 'trash'] as $status) {
    $parent = wp_insert_post(['post_title' => 'zz-lows-' . $status, 'post_status' => $status === 'trash' ? 'publish' : $status]);
    if (!is_int($parent) || $parent <= 1) { throw new RuntimeException('no parent'); }
    $made[] = ['post', $parent];
    $att = wp_insert_attachment(['post_title' => 'zz-lows-att-' . $status, 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], false, $parent);
    $made[] = ['attachment', $att];
    if ($status === 'trash') { wp_trash_post($parent); }
    $say("media of a {$status} parent, anonymous", $ask('GET', "/wp/v2/media/{$att}", [], 0));
    $say("media of a {$status} parent, in a list", (static function () use ($att) { wp_set_current_user(0); $r = new WP_REST_Request('GET', '/wp/v2/media'); $r->set_param('include', [$att]); return count((array) rest_do_request($r)->get_data()); })());
}

// editable_roles in REST role assignment
$filter = static fn ($roles) => array_diff_key($roles, ['editor' => true]);
add_filter('editable_roles', $filter);
$user = wp_insert_user(['user_login' => 'zz-lows-user', 'user_email' => 'zz-lows-user@example.org', 'user_pass' => 'zz' . wp_generate_password(16)]);
if (!is_int($user) || $user <= 1) { throw new RuntimeException('no user'); }
$made[] = ['user', $user];
$say('a role editable_roles leaves out, on update', $ask('POST', "/wp/v2/users/{$user}", ['roles' => ['editor']]));
$say('a role editable_roles leaves out, on create', $ask('POST', '/wp/v2/users', ['username' => 'zz-lows-user2', 'email' => 'zz-lows-user2@example.org', 'password' => 'zz' . wp_generate_password(16), 'roles' => ['editor']]));
remove_filter('editable_roles', $filter);
if ($u2 = get_user_by('login', 'zz-lows-user2')) { $made[] = ['user', $u2->ID]; }

// a locale that is not installed, over REST
$say('an uninstalled locale for a user', $ask('POST', "/wp/v2/users/{$user}", ['locale' => 'zz_ZZ']));
$say('a malformed locale for a user', $ask('POST', "/wp/v2/users/{$user}", ['locale' => 'en_US"><script>']));

// assign_term: a contributor naming a category it cannot assign
add_filter('map_meta_cap', $deny = static fn ($caps, $cap) => $cap === 'assign_term' ? ['do_not_allow'] : $caps, 10, 2);
$say('a category assign_term refuses', $ask('POST', '/wp/v2/posts', ['title' => 'zz-lows-assign', 'categories' => [1], 'status' => 'draft']));
remove_filter('map_meta_cap', $deny, 10);
foreach (get_posts(['title' => 'zz-lows-assign', 'post_status' => 'any', 'numberposts' => 5]) as $p) { $made[] = ['post', $p->ID]; }

// wp_rand edges, esc_sql placeholders, kses protocol entities
// The commenter's cookies are cleaned as a request begins, before a theme reads them.
$say('sanitize_comment_cookies is hooked', has_action('sanitize_comment_cookies', 'sanitize_comment_cookies'));

// wp_rand: no maximum reaches past zero (it once always answered 0), bounds the wrong way round are swapped,
// and the answer is never negative; shown as ranges, the draws being random.
$draws = static fn (callable $draw): array => array_map(static fn () => $draw(), range(1, 40));
$say('wp_rand with no bounds', (static function () use ($draws) { $all = $draws(static fn () => wp_rand()); return [min($all) >= 0, max($all) <= 4294967295, max($all) > 0]; })());
$say('wp_rand with its bounds swapped', (static function () use ($draws) { $all = $draws(static fn () => wp_rand(9, 3)); return [min($all) >= 3, max($all) <= 9]; })());
$say('wp_rand below zero', (static function () use ($draws) { $all = $draws(static fn () => wp_rand(-9, -3)); return [min($all) >= 3, max($all) <= 9]; })());
$say('wp_rand with equal bounds', [wp_rand(7, 7), wp_rand(0, 0)]);

// The placeholder escape is the stack's own secret: shown as {placeholder}.
global $wpdb;
$placeholder = static fn ($value) => str_replace($wpdb->placeholder_escape(), '{placeholder}', $value);
$say('esc_sql with a percent', array_map($placeholder, [esc_sql('50% off'), ...esc_sql(['a%b', "c'd"])]));
$say('prepare after esc_sql', $placeholder($wpdb->prepare('SELECT %s', esc_sql('50%'))));
$say('the placeholder escape gone from the query run', $wpdb->get_var($wpdb->prepare('SELECT %s', '50% off')));
foreach (['javascript&#58alert(1)', 'javascript&#58;alert(1)', 'javascript&#x3a;alert(1)', 'javascript&#x3aalert(1)', 'java&#09;script:alert(1)', 'http&#58//example.org', 'javascript&#581alert(1)', 'javascript&#0058alert(1)', 'javascript&#X3a;alert(1)', 'javascript&#5800;x', 'a&#58b&#58c'] as $url) {
    $say('kses protocol: ' . $url, wp_kses_bad_protocol($url, ['http', 'https']));
}

// the playlist's JSON
$pa = wp_insert_post(['post_title' => 'zz-lows-playlist', 'post_status' => 'publish']);
$made[] = ['post', $pa];
$track = wp_insert_attachment(['post_title' => 'zz </script><script>alert(1)</script> & \'q\' "d"', 'post_mime_type' => 'audio/mpeg', 'post_status' => 'inherit', 'guid' => 'https://example.org/a.mp3'], false, $pa);
$made[] = ['attachment', $track];
update_post_meta($track, '_wp_attached_file', '2026/10/zz-lows.mp3');
$html = wp_playlist_shortcode(['ids' => (string) $track, 'type' => 'audio']);
preg_match('#<script type="application/json" class="wp-playlist-script">(.*?)</script>#s', $html, $m);
// The icon's address takes the scheme of the command line it runs from; the host and the escaping are what count.
$say('playlist json', isset($m[1]) ? str_replace(['https://' . $home, 'http://' . $home], '{home}', $m[1]) : 'none');

// a response size limit
$say('limit_response_size', (static function () {
    $r = wp_remote_get(home_url('/wp-json/'), ['limit_response_size' => 100, 'sslverify' => false, 'timeout' => 10]);
    return is_wp_error($r) ? $r->get_error_code() : [wp_remote_retrieve_response_code($r), strlen(wp_remote_retrieve_body($r))];
})());

echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
