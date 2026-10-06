<?php
/**
 * Behaviour probe for media, cron, HTTP, comments, widgets, and the small
 * template helpers. Same protocol as api-probe.php; cleans up on shutdown.
 */

// Admin-side files the reference loads on demand; the engine's facade has these at boot.
if (defined('ABSPATH') && !function_exists('dbDelta') && is_file(ABSPATH . 'wp-admin/includes/upgrade.php')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}
if (defined('ABSPATH') && !function_exists('wp_generate_attachment_metadata') && is_file(ABSPATH . 'wp-admin/includes/image.php')) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
}
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$kind = static fn ($r) => $r instanceof WP_Error ? 'error:' . $r->get_error_code() : (is_object($r) ? get_class($r) : var_export($r, true));
$out = static function (callable $fn): string { ob_start(); $fn(); return (string) ob_get_clean(); };
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
// The upload month is the day's, not the fixture's: masked like the host.
$month = ltrim((string) wp_get_upload_dir()['subdir'], '/');
$rel = static fn ($v) => is_string($v) ? str_replace([$home, str_replace('https://', 'http://', $home), $month . '/'], ['{home}', '{home}', '{month}/'], $v) : $v;
$cleanup = static function (): void {
    foreach (get_posts(['post_type' => 'attachment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 's' => 'Probe image']) as $id) {
        wp_delete_attachment($id, true);
    }
    wp_clear_scheduled_hook('minn_probe_hook', ['a']);
    wp_clear_scheduled_hook('minn_probe_hook');
    wp_unschedule_hook('minn_probe_single');
    wp_unschedule_hook('minn_probe_hook');
    wp_unschedule_hook('minn_probe_multi');
    delete_option('minn_probe_widget');
    $GLOBALS['wpdb']->query('DROP TABLE IF EXISTS ' . $GLOBALS['wpdb']->prefix . 'minn_probe');
    foreach (get_comments(['author_email' => 'probe@example.test', 'status' => 'all']) as $c) {
        wp_delete_comment($c->comment_ID, true);
    }
};
$cleanup();
register_shutdown_function($cleanup);

// The battery attachment: the oldest attachment in the shared database.
$existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$aid = $existing ? $existing[0]->ID : 0;
$meta = wp_get_attachment_metadata($aid);
$say('attachment meta keys', array_keys($meta));
$say('attachment meta sizes', array_map(static fn ($s) => [$s['width'], $s['height'], $s['mime-type'], isset($s['filesize'])], $meta['sizes']));
$say('attachment dims', [$meta['width'], $meta['height'], basename($meta['file'])]);
$say('image_src thumbnail', [$rel(wp_get_attachment_image_src($aid, 'thumbnail')[0] ?? null), array_slice(wp_get_attachment_image_src($aid, 'thumbnail'), 1)]);
$say('image_src medium', array_slice(wp_get_attachment_image_src($aid, 'medium'), 1));
$say('image_src full', [array_slice(wp_get_attachment_image_src($aid, 'full'), 1), basename(wp_get_attachment_image_src($aid, 'full')[0])]);
$say('image_src array', array_slice(wp_get_attachment_image_src($aid, [100, 100]), 1));
$say('image_src array big', [basename(wp_get_attachment_image_src($aid, [5000, 5000])[0]), array_slice(wp_get_attachment_image_src($aid, [5000, 5000]), 1)]);
$say('image_src unknown size', [basename(wp_get_attachment_image_src($aid, 'nope')[0]), array_slice(wp_get_attachment_image_src($aid, 'nope'), 1)]);
$say('image_src missing', wp_get_attachment_image_src(999999));
$say('image_url', [$rel(wp_get_attachment_image_url($aid, 'medium')), wp_get_attachment_image_url(999999)]);
$say('attachment_image', $rel(wp_get_attachment_image($aid, 'thumbnail')));
$say('attachment_image attr', $rel(wp_get_attachment_image($aid, 'medium', false, ['class' => 'x', 'alt' => 'Alt text', 'loading' => false])));
$say('attachment_image missing', wp_get_attachment_image(999999));
$say('wp_attachment_is', [wp_attachment_is_image($aid), wp_attachment_is('image', $aid), wp_attachment_is('audio', $aid), wp_attachment_is_image(1), wp_attachment_is_image(999999)]);
$say('get_intermediate_image_sizes', get_intermediate_image_sizes());
$say('wp_get_registered_image_subsizes', wp_get_registered_image_subsizes());
$say('add_image_size', (static function () { add_image_size('minn-probe', 200, 100, true); $a = [has_image_size('minn-probe'), wp_get_registered_image_subsizes()['minn-probe'] ?? null, in_array('minn-probe', get_intermediate_image_sizes(), true)]; remove_image_size('minn-probe'); $a[] = has_image_size('minn-probe'); return $a; })());
$say('image_resize_dimensions', [image_resize_dimensions(1200, 800, 300, 300), image_resize_dimensions(1200, 800, 150, 150, true), image_resize_dimensions(1200, 800, 0, 400), image_resize_dimensions(100, 100, 300, 300), image_resize_dimensions(1200, 800, 300, 0, true), image_resize_dimensions(1200, 800, 1200, 800), image_resize_dimensions(1200, 800, 1200, 800, true)]);
$say('wp_constrain_dimensions', [wp_constrain_dimensions(1200, 800, 300, 300), wp_constrain_dimensions(1200, 800, 0, 100), wp_constrain_dimensions(100, 50, 300, 300), wp_constrain_dimensions(1200, 800, 1024, 1024)]);
$say('image_constrain_size_for_editor', [image_constrain_size_for_editor(1200, 800, 'medium'), image_constrain_size_for_editor(1200, 800, 'thumb'), image_constrain_size_for_editor(1200, 800, 'full'), image_constrain_size_for_editor(1200, 800, [100, 100]), image_constrain_size_for_editor(1200, 800, 'large')]);
$say('image_downsize', [array_slice((array) image_downsize($aid, 'medium'), 1), image_downsize(999999)]);
$say('image_get_intermediate_size', array_keys((array) image_get_intermediate_size($aid, 'medium')));
$say('image_get_intermediate_size values', (static function () use ($aid, $rel) { $s = image_get_intermediate_size($aid, 'medium'); return [$s['width'], $s['height'], $s['mime-type'], basename($s['path']), $rel($s['url'])]; })());
$say('wp_get_attachment_caption', [wp_get_attachment_caption($aid), wp_get_attachment_caption(999999)]);
$say('attachment_url_to_postid', [attachment_url_to_postid(wp_get_attachment_url($aid)), attachment_url_to_postid($home . '/wp-content/uploads/nope.png'), attachment_url_to_postid(wp_get_attachment_image_url($aid, 'medium'))]);
$say('wp_get_attachment_thumb_url', $rel(wp_get_attachment_thumb_url($aid)));
$say('wp_get_attachment_link', [$rel(wp_get_attachment_link($aid)), $rel(wp_get_attachment_link($aid, 'medium', true, false, 'Text')), wp_get_attachment_link(999999)]);
$say('srcset', $rel(wp_get_attachment_image_srcset($aid, 'medium')));
$say('sizes attr', wp_get_attachment_image_sizes($aid, 'medium'));
$say('original path', [basename(wp_get_original_image_path($aid)), basename(get_attached_file($aid)), str_starts_with(get_attached_file($aid), wp_get_upload_dir()['basedir'])]);
$say('wp_get_image_mime', [wp_get_image_mime(get_attached_file($aid)), wp_get_image_mime('/nope.png')]);
$say('file_is_valid_image', [file_is_valid_image(get_attached_file($aid)), file_is_valid_image('/nope'), file_is_displayable_image(get_attached_file($aid))]);
$say('site icon', [get_site_icon_url(), has_site_icon(), get_site_icon_url(32, 'fallback')]);
$say('wp_get_image_editor', [$kind(wp_get_image_editor(get_attached_file($aid))), $kind(wp_get_image_editor('/nope.png'))]);
$say('get_the_post_thumbnail', [$rel(get_the_post_thumbnail(5)), $rel(get_the_post_thumbnail(1)), $rel(get_the_post_thumbnail_url(5, 'thumbnail')), get_the_post_thumbnail_url(1)]);
$say('prepare_attachment_for_js keys', array_keys(wp_prepare_attachment_for_js($aid)));
$say('prepare_attachment_for_js sizes', array_keys(wp_prepare_attachment_for_js($aid)['sizes']));

// A new image through the whole pipeline.
$img = imagecreatetruecolor(1200, 800);
imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
ob_start();
imagepng($img);
$bits = ob_get_clean();
$upload = wp_upload_bits('probe image.png', null, $bits);
$say('wp_upload_bits', [$upload['error'], basename($upload['file']), $rel($upload['url']), $upload['type'], str_starts_with($upload['file'], wp_get_upload_dir()['basedir'])]);
$newId = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => 'Probe image', 'post_content' => '', 'post_status' => 'inherit'], $upload['file'], 5);
$post = get_post($newId);
$say('wp_insert_attachment', [is_int($newId) && $newId > 0, $post->post_type, $post->post_status, $post->post_parent, $post->post_mime_type, $post->post_name, get_post_meta($newId, '_wp_attached_file', true) === ltrim(str_replace(wp_get_upload_dir()['basedir'], '', $upload['file']), '/'), $rel($post->guid) === $rel($upload['url'])]);
$generated = wp_generate_attachment_metadata($newId, $upload['file']);
$say('wp_generate_attachment_metadata', [array_keys($generated), $generated['width'], $generated['height'], isset($generated['filesize']), array_map(static fn ($s) => [$s['width'], $s['height'], $s['mime-type'], isset($s['filesize'])], $generated['sizes']), array_keys($generated['image_meta'] ?? []), basename($generated['file']) === basename($upload['file'])]);
$say('generated files exist', array_map(static fn ($s) => file_exists(dirname($upload['file']) . '/' . $s['file']), $generated['sizes']));
$say('wp_update_attachment_metadata', [wp_update_attachment_metadata($newId, $generated), wp_get_attachment_metadata($newId) == $generated, wp_update_attachment_metadata($newId, $generated)]);
$say('stored metadata blob', $rel(preg_replace('/probe-image(-\d+)?/', 'probe-image', (string) get_post_meta($newId, '_wp_attachment_metadata', true) === '' ? 'empty' : substr((string) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT meta_value FROM {$GLOBALS['wpdb']->postmeta} WHERE post_id = %d AND meta_key = '_wp_attachment_metadata'", $newId)), 0, 120))));
$say('update_attached_file', [update_attached_file($newId, $upload['file']), update_attached_file(999999, '/x.png')]);
$say('get_attached_file new', basename(get_attached_file($newId)) === basename($upload['file']));
$say('image_src new medium', array_slice(wp_get_attachment_image_src($newId, 'medium'), 1));
$say('image_src new large', array_slice(wp_get_attachment_image_src($newId, 'large'), 1));
$say('attachment_url_to_postid new', attachment_url_to_postid($upload['url']) === $newId);
$say('wp_count_attachments', get_object_vars(wp_count_attachments()) !== []);
$say('wp_delete_attachment', (static function () use ($newId, $upload, $kind) { $r = wp_delete_attachment($newId, true); return [$kind($r), get_post($newId), file_exists($upload['file']), glob(dirname($upload['file']) . '/' . pathinfo($upload['file'], PATHINFO_FILENAME) . '-*') === []]; })());
$say('wp_delete_attachment missing', wp_delete_attachment(999999));
$tmp = wp_upload_bits('probe-delete.txt', null, 'x')['file'];
$say('wp_delete_file', [file_exists($tmp), wp_delete_file($tmp), file_exists($tmp), wp_delete_file('/nope/nope.txt')]);

// Cron.
$say('cron array shape', (static function () { $c = _get_cron_array(); foreach ($c as $hooks) { foreach ($hooks as $hook => $entries) { foreach ($entries as $key => $entry) { if (!empty($entry['schedule'])) { return [is_array($c), is_int(array_key_first($c)), isset($c['version']), array_keys($entry), strlen((string) $key)]; } } } } return 'no recurring event'; })());
$ts = time() + 3600;
$say('wp_schedule_event', [wp_schedule_event($ts, 'hourly', 'minn_probe_hook', ['a']), wp_next_scheduled('minn_probe_hook', ['a']) === $ts, wp_next_scheduled('minn_probe_hook'), wp_get_schedule('minn_probe_hook', ['a']), wp_get_schedule('minn_probe_hook')]);
$say('wp_schedule_event duplicate', [wp_schedule_event($ts + 60, 'hourly', 'minn_probe_hook', ['a']), $kind(wp_schedule_event($ts + 60, 'hourly', 'minn_probe_hook', ['a'], true))]);
$say('wp_schedule_event bad schedule', [wp_schedule_event($ts, 'nope', 'minn_probe_hook'), $kind(wp_schedule_event($ts, 'nope', 'minn_probe_hook', [], true))]);
$say('wp_get_scheduled_event', (static function () use ($ts) { $e = wp_get_scheduled_event('minn_probe_hook', ['a']); return [get_class($e), get_object_vars($e) == ['hook' => 'minn_probe_hook', 'timestamp' => $ts, 'schedule' => 'hourly', 'args' => ['a'], 'interval' => 3600], array_keys(get_object_vars($e)), wp_get_scheduled_event('minn_probe_hook')]; })());
$say('cron option entry', (static function () use ($ts) { $c = get_option('cron'); return [$c[$ts]['minn_probe_hook'][md5(serialize(['a']))] ?? null, array_keys($c[$ts]['minn_probe_hook'])]; })());
$say('wp_schedule_single_event', [wp_schedule_single_event($ts + 120, 'minn_probe_single', ['b']), wp_next_scheduled('minn_probe_single', ['b']) === $ts + 120, wp_get_schedule('minn_probe_single', ['b']), wp_schedule_single_event($ts + 130, 'minn_probe_single', ['b']), wp_schedule_single_event($ts + 20000, 'minn_probe_single', ['b']), (static function () use ($ts) { $e = get_object_vars(wp_get_scheduled_event('minn_probe_single', ['b'])); $e['timestamp'] = $e['timestamp'] === $ts + 120; return $e; })()]);
$say('wp_reschedule_event', [wp_reschedule_event($ts, 'hourly', 'minn_probe_hook', ['a']), wp_next_scheduled('minn_probe_hook', ['a']) > $ts]);
$say('wp_unschedule_event', [wp_unschedule_event(wp_next_scheduled('minn_probe_hook', ['a']), 'minn_probe_hook', ['a']), wp_next_scheduled('minn_probe_hook', ['a']) === $ts + 60, wp_unschedule_event($ts, 'minn_probe_hook', ['a']), $kind(wp_unschedule_event($ts, 'minn_probe_hook', ['a'], true))]);
wp_schedule_event($ts, 'daily', 'minn_probe_multi', [1]);
wp_schedule_event($ts + 5, 'daily', 'minn_probe_multi', [2]);
wp_schedule_single_event($ts + 9, 'minn_probe_multi', [3]);
$say('wp_clear_scheduled_hook', [wp_clear_scheduled_hook('minn_probe_multi', [1]), wp_next_scheduled('minn_probe_multi', [1]), wp_next_scheduled('minn_probe_multi', [2]) === $ts + 5, wp_clear_scheduled_hook('minn_probe_multi', [9])]);
$say('wp_unschedule_hook', [wp_unschedule_hook('minn_probe_multi'), wp_next_scheduled('minn_probe_multi', [2]), wp_unschedule_hook('minn_probe_multi'), $kind(wp_unschedule_hook('minn_probe_multi', true))]);
$say('wp_get_schedules', array_map(static fn ($s) => [$s['interval'], $s['display']], wp_get_schedules()));
$say('wp_get_ready_cron_jobs', is_array(wp_get_ready_cron_jobs()));
$say('_set_cron_array', [_set_cron_array(_get_cron_array()), $kind(_set_cron_array(['version' => 2] + _get_cron_array(), true))]);

// HTTP against the site itself.
$url = $home . '/robots.txt';
$r = wp_remote_get($url, ['sslverify' => false, 'timeout' => 10]);
$say('wp_remote_get shape', [is_array($r) ? array_keys($r) : $kind($r), wp_remote_retrieve_response_code($r), wp_remote_retrieve_response_message($r), str_contains((string) wp_remote_retrieve_body($r), 'User-agent'), is_object($r['headers'] ?? null) ? get_class($r['headers']) : gettype($r['headers'] ?? null), wp_remote_retrieve_header($r, 'content-type'), is_array(wp_remote_retrieve_headers($r)) || is_object(wp_remote_retrieve_headers($r)), wp_remote_retrieve_cookies($r), wp_remote_retrieve_header($r, 'nope')]);
$say('wp_remote_head', [wp_remote_retrieve_response_code(wp_remote_head($url, ['sslverify' => false])), wp_remote_retrieve_body(wp_remote_head($url, ['sslverify' => false]))]);
$say('wp_remote_request method', wp_remote_retrieve_response_code(wp_remote_request($url, ['method' => 'HEAD', 'sslverify' => false])));
$say('wp_remote_get 404', [wp_remote_retrieve_response_code(wp_remote_get($home . '/no-such-page-probe/', ['sslverify' => false])), wp_remote_retrieve_response_message(wp_remote_get($home . '/no-such-page-probe/', ['sslverify' => false]))]);
$say('wp_remote_get bad host', [$kind($e = wp_remote_get('http://nonexistent.invalid/', ['timeout' => 3])), wp_remote_retrieve_response_code($e), wp_remote_retrieve_body($e), wp_remote_retrieve_headers($e)]);
$say('wp_remote_get bad url', $kind(wp_remote_get('not a url')));
$say('wp_safe_remote_get', wp_remote_retrieve_response_code(wp_safe_remote_get($url, ['sslverify' => false])));
$say('wp_safe_remote_get local', $kind(wp_safe_remote_get('http://127.0.0.1:1/x')));
$say('wp_remote_post', [wp_remote_retrieve_response_code(wp_remote_post($home . '/wp-json/', ['sslverify' => false, 'body' => ['a' => 1]])), wp_remote_retrieve_response_code(wp_remote_post($home . '/wp-json/wp/v2/posts', ['sslverify' => false, 'body' => ['title' => 'x']]))]);
$say('wp_http_validate_url', [wp_http_validate_url('https://example.com/x'), wp_http_validate_url('ftp://example.com/'), wp_http_validate_url('http://127.0.0.1/'), wp_http_validate_url('http://user:pass@example.com/'), wp_http_validate_url('nope')]);
$say('wp_http_supports', [wp_http_supports(['ssl']), wp_http_supports(['nope'])]);
$say('download_url', (static function () use ($url, $kind) { add_filter('https_ssl_verify', '__return_false'); $f = download_url($url); $ok = is_string($f) && file_exists($f); if ($ok) { unlink($f); } $bad = download_url('http://nonexistent.invalid/x'); return [$ok, $kind($bad), $kind(download_url(''))]; })());
$say('get_http_origin', get_http_origin());
$say('is_allowed_http_origin', [is_allowed_http_origin($home), is_allowed_http_origin('https://evil.test')]);

// Comments.
$comments = get_comments(['post_id' => 1]);
$say('get_comments', [count($comments), $comments === [] ? null : get_class($comments[0]), $comments === [] ? null : array_keys(get_object_vars($comments[0]))]);
$say('get_comments args', [count(get_comments(['post_id' => 1, 'status' => 'approve', 'number' => 1])), get_comments(['post_id' => 999999]), get_comments(['post_id' => 1, 'count' => true]), get_comments(['post_id' => 1, 'fields' => 'ids', 'number' => 1])]);
$say('get_comments_number', [get_comments_number(1), get_comments_number(5), get_comments_number(999999)]);
$say('wp_count_comments', get_object_vars(wp_count_comments(1)));
$say('wp_count_comments all keys', array_keys(get_object_vars(wp_count_comments())));
$cid = wp_insert_comment(['comment_post_ID' => 1, 'comment_author' => 'Probe', 'comment_author_email' => 'probe@example.test', 'comment_content' => 'Probe comment', 'comment_approved' => 1]);
$c = get_comment($cid);
$say('wp_insert_comment', [is_int($cid) && $cid > 0, get_class($c), $c->comment_post_ID, $c->comment_approved, $c->comment_author, $c->comment_type, $c->comment_parent, $c->user_id, $c->comment_date !== '', $c->comment_author_IP, get_post(1)->comment_count]);
$say('get_comment ARRAY_A keys', array_keys(get_comment($cid, ARRAY_A)));
$say('get_comment missing', get_comment(999999));
$say('wp_update_comment', [wp_update_comment(['comment_ID' => $cid, 'comment_content' => 'Edited']), get_comment($cid)->comment_content, wp_update_comment(['comment_ID' => 999999, 'comment_content' => 'x']), $kind(wp_update_comment(['comment_ID' => 999999], true))]);
$say('wp_delete_comment trash', [wp_delete_comment($cid), get_comment($cid)->comment_approved, wp_delete_comment($cid, true), get_comment($cid)]);
$say('wp_count_terms', [wp_count_terms(['taxonomy' => 'post_tag']), wp_count_terms('post_tag'), wp_count_terms(['taxonomy' => 'category', 'hide_empty' => false])]);

// Head, footer, and the small template helpers.
$say('wp_head action', (static function () use ($out) { $before = did_action('wp_head'); $out('wp_head'); return [did_action('wp_head') - $before, did_action('wp_enqueue_scripts') > 0]; })());
$say('wp_footer action', (static function () use ($out) { $before = did_action('wp_footer'); $out('wp_footer'); return did_action('wp_footer') - $before; })());
$say('wp_body_open', (static function () use ($out) { $out('wp_body_open'); return did_action('wp_body_open'); })());
$say('language_attributes', [$out(static fn () => language_attributes()), get_language_attributes('xhtml'), get_language_attributes()]);
$say('user_trailingslashit', [user_trailingslashit('a/b'), user_trailingslashit('a/b/'), user_trailingslashit('a/b', 'single'), user_trailingslashit('a/b?x=1')]);
$say('date links', [$rel(get_year_link(2026)), $rel(get_month_link(2026, 8)), $rel(get_day_link(2026, 8, 9)), $rel(get_month_link(false, false)) !== '']);
$say('esc_xml', esc_xml('<a> & &amp; &copy; "q" \'s &#8217; &bogus;'));
$say('sanitize_hex_color', [sanitize_hex_color('#abc'), sanitize_hex_color('#AABBCC'), sanitize_hex_color('abc'), sanitize_hex_color('#abcd'), sanitize_hex_color(''), sanitize_hex_color_no_hash('#abc'), sanitize_hex_color_no_hash('abc'), maybe_hash_hex_color('abc'), maybe_hash_hex_color('#abc'), maybe_hash_hex_color('zzz')]);
$say('translate_user_role', [translate_user_role('Administrator'), translate_user_role('Nope')]);
$say('get_super_admins', get_super_admins());
$say('get_blogs_of_user', array_map(static fn ($b) => array_keys(get_object_vars($b)), array_values(get_blogs_of_user(1))));
$say('get_blogs_of_user values', array_map(static fn ($b) => [$b->userblog_id, $b->blogname, $b->path, $rel($b->siteurl)], array_values(get_blogs_of_user(1))));
$say('sidebars', (static function () use ($out) { $id = register_sidebar(['id' => 'minn-probe-sidebar', 'name' => 'Probe']); $a = [$id, is_active_sidebar('minn-probe-sidebar'), array_keys($GLOBALS['wp_registered_sidebars']['minn-probe-sidebar']), $GLOBALS['wp_registered_sidebars']['minn-probe-sidebar']['before_widget'], $out(static fn () => dynamic_sidebar('minn-probe-sidebar'))]; unregister_sidebar('minn-probe-sidebar'); $a[] = isset($GLOBALS['wp_registered_sidebars']['minn-probe-sidebar']); return $a; })());
class Minn_Probe_Widget extends WP_Widget { public function __construct() { parent::__construct('minn_probe_widget', 'Probe Widget', ['description' => 'd']); } public function widget($args, $instance) { echo $args['before_widget'] . 'W:' . ($instance['t'] ?? '') . $args['after_widget']; } public function form($instance) { echo '<input name="' . $this->get_field_name('t') . '" id="' . $this->get_field_id('t') . '">'; } public function update($new, $old) { return ['t' => sanitize_text_field($new['t'] ?? '')]; } }
$say('register_widget', (static function () use ($out) { register_widget('Minn_Probe_Widget'); $w = $GLOBALS['wp_widget_factory']->widgets['Minn_Probe_Widget'] ?? null; $a = [get_class($GLOBALS['wp_widget_factory']), $w ? get_class($w) : null, $w->id_base ?? null, $w->name ?? null, $w->widget_options ?? null, $w->control_options ?? null, $w->option_name ?? null, $w->number ?? null]; $a[] = $out(static fn () => the_widget('Minn_Probe_Widget', ['t' => 'hi'], ['before_widget' => '<div>', 'after_widget' => '</div>'])); $w->_set(2); $a[] = [$w->get_field_name('t'), $w->get_field_id('t'), $w->id]; unregister_widget('Minn_Probe_Widget'); $a[] = isset($GLOBALS['wp_widget_factory']->widgets['Minn_Probe_Widget']); return $a; })());
$say('wp_make_link_relative', [wp_make_link_relative($home . '/a/b?c=1'), wp_make_link_relative('//x.test/a'), wp_make_link_relative('/already'), wp_make_link_relative('http://x.test')]);
$say('inline script tag', [wp_get_inline_script_tag('var a = 1;'), wp_get_inline_script_tag('x', ['id' => 'i', 'type' => 'module', 'async' => true]), $out(static fn () => wp_print_inline_script_tag('y', ['type' => 'text/template']))]);
$say('apply_filters_deprecated', (static function () { add_filter('minn_probe_old', static fn ($v) => $v . '+f'); return [apply_filters_deprecated('minn_probe_old', ['v'], '1.0', 'minn_probe_new'), apply_filters_deprecated('minn_probe_none', ['v'], '1.0')]; })());
$say('do_action_deprecated', (static function () { $seen = 0; add_action('minn_probe_old_action', static function () use (&$seen) { $seen++; }); do_action_deprecated('minn_probe_old_action', [1], '1.0'); do_action_deprecated('minn_probe_none_action', [1], '1.0'); return $seen; })());
$say('_wp_to_kebab_case', [_wp_to_kebab_case('fontSize'), _wp_to_kebab_case('Font Size'), _wp_to_kebab_case('font_size_x'), _wp_to_kebab_case('FontSize2Value')]);
$say('wp_star_rating', [wp_star_rating(['rating' => 3.5, 'echo' => false]), wp_star_rating(['rating' => 80, 'type' => 'percent', 'number' => 12, 'echo' => false])]);
$say('get_avatar_url', [$rel(get_avatar_url(1)), preg_replace('/[a-f0-9]{64}/', 'H', get_avatar_url('someone@example.test', ['size' => 40])), get_avatar_url('nope')]);
$say('get_avatar', preg_replace('/[a-f0-9]{64}/', 'H', get_avatar(1, 48, '', 'Alt', ['class' => 'x'])));
$say('get_avatar_data keys', array_keys(get_avatar_data(1)));
$say('rest_sanitize_boolean', [rest_sanitize_boolean('false'), rest_sanitize_boolean('0'), rest_sanitize_boolean('1'), rest_sanitize_boolean('yes'), rest_sanitize_boolean(''), rest_sanitize_boolean(true)]);
$say('rest_authorization_required_code', rest_authorization_required_code());
$say('dbDelta', (static function () { $t = $GLOBALS['wpdb']->prefix . 'minn_probe'; $r = dbDelta("CREATE TABLE {$t} (\n  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  name varchar(100) NOT NULL DEFAULT '',\n  PRIMARY KEY  (id),\n  KEY name (name)\n) DEFAULT CHARSET=utf8mb4;"); $exists = $GLOBALS['wpdb']->get_var("SHOW TABLES LIKE '{$t}'") === $t; $again = dbDelta("CREATE TABLE {$t} (\n  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  name varchar(100) NOT NULL DEFAULT '',\n  extra int(11) NOT NULL DEFAULT 0,\n  PRIMARY KEY  (id),\n  KEY name (name)\n) DEFAULT CHARSET=utf8mb4;"); $cols = array_column($GLOBALS['wpdb']->get_results("SHOW COLUMNS FROM {$t}", ARRAY_A), 'Field'); return [$r, $exists, $again, $cols]; })());
$say('template helpers', [locate_template(['nope.php']), locate_template('nope.php', false), get_template_part('nope'), $out(static fn () => get_template_part('nope', 'x')), basename((string) locate_template('style.css'))]);
$tmpl = get_temp_dir() . 'minn-probe-template.php';
file_put_contents($tmpl, '<?php echo "T:" . ($args["v"] ?? "") . ":" . (isset($post) ? "p" : "") . ":" . (isset($wp_query) ? "q" : "");');
$say('load_template', [$out(static fn () => load_template($tmpl, false, ['v' => 1])), $out(static fn () => load_template($tmpl, true, ['v' => 2])), $out(static fn () => load_template($tmpl, true, ['v' => 3]))]);
wp_delete_file($tmpl);
$say('get_upload_iframe_src', $rel(get_upload_iframe_src('image', 1)));
$say('add_thickbox', (static function () { add_thickbox(); return [wp_script_is('thickbox'), wp_style_is('thickbox')]; })());
$say('wp_get_environment_type', wp_get_environment_type());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE), "\n";
