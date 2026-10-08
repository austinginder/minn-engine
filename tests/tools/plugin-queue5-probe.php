<?php
/**
 * The fifth wave of the plugin catalogue's queue (probe plugin-queue5), as
 * the reference answers it: the playlist shortcode over audio and video
 * attachments (by ids, by parent, styled and trimmed, filtered short) with
 * its scripts and templates; the targeted-link rel filters switched on and
 * off, nofollow, the text before the last bar, a string split on
 * whitespace, the old specialchars; a comment author's address and its
 * link, the author's address, a user by address; the posts navigation on a
 * paged query; meta SQL; the cache primers; blocks flattened; an image tag;
 * the default extension for a type; the PHP 8.5 array functions; an id from
 * values; theme folders ignored; block assets on demand; and the
 * placeholders themes call (the cancel-reply link, the term edit link, a
 * post's parent, the login screen test, a deep replace, every page id,
 * magic quotes, the script suffix, old theme data, a post's meta rows).
 * Everything made is removed at the end. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach (array_reverse($made['posts']) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);
if (!function_exists('has_meta')) {
    require_once ABSPATH . 'wp-admin/includes/post.php';
}
if (!did_action('init')) {
    do_action('init');
}

// Which functions the reference reports as deprecated, in the order they are first reported.
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    if (!in_array([$function, $replacement, $version], $deprecated, true)) {
        $deprecated[] = [$function, $replacement, $version];
    }
}, 10, 3);
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (is_int($value) && isset($ids[$value])) {
        return '{' . $ids[$value] . '}';
    }
    if (!is_string($value)) {
        return $value;
    }
    // The site's address is one token: the CLI runtimes differ only in whether they count as https.
    $value = str_replace([home_url(), site_url(), str_replace('/', '\\/', home_url()), str_replace('/', '\\/', site_url())], '{home}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (array $fields, string $label) use (&$made, &$ids): int {
    $id = wp_insert_post($fields + ['post_status' => 'publish']);
    $id = is_int($id) ? $id : 0;
    if ($id > 0) {
        $made['posts'][] = $id;
        $ids[$id] = $label;
    }
    return $id;
};

// The playlist.
$parent = $post(['post_type' => 'post', 'post_title' => 'ZZ Playlist Parent', 'post_content' => 'Songs.'], 'parent');
$media = static function (string $title, string $file, string $mime, array $meta, int $order) use ($post, $parent): int {
    $id = $post(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => $title, 'post_mime_type' => $mime, 'post_parent' => $parent, 'menu_order' => $order, 'post_excerpt' => $title . ' caption', 'post_content' => $title . ' description'], sanitize_title($title));
    if ($id > 0) {
        update_post_meta($id, '_wp_attached_file', 'zz-queue/' . $file);
        update_post_meta($id, '_wp_attachment_metadata', $meta);
    }
    return $id;
};
$one = $media('ZZ Song One', 'one.mp3', 'audio/mpeg', ['length_formatted' => '3:05', 'length' => 185, 'artist' => 'ZZ Artist', 'album' => 'ZZ Album', 'fileformat' => 'mp3', 'filesize' => 1000, 'mime_type' => 'audio/mpeg'], 2);
$two = $media('ZZ Song "Two"', 'two.ogg', 'audio/ogg', ['length_formatted' => '0:42', 'length' => 42, 'fileformat' => 'ogg'], 1);
$clip = $media('ZZ Clip', 'clip.mp4', 'video/mp4', ['length_formatted' => '1:00', 'width' => 640, 'height' => 360, 'fileformat' => 'mp4'], 3);
$playlist = static fn (array $atts) => $mask(wp_playlist_shortcode($atts));
$say('the playlist', $playlist(['ids' => "{$one},{$two}"]));
$say('the playlist, a second on the page', $playlist(['ids' => (string) $two]));
$say('the playlist, dark and trimmed', $playlist(['ids' => "{$two},{$one}", 'style' => 'dark', 'tracklist' => false, 'tracknumbers' => false, 'images' => false, 'artists' => false]));
$say('the playlist of videos', $playlist(['ids' => (string) $clip, 'type' => 'video']));
$say('the playlist of a post\'s audio', $playlist(['id' => $parent]));
$say('the playlist of a post\'s audio, by title, descending', $playlist(['id' => $parent, 'orderby' => 'title', 'order' => 'DESC']));
$say('the playlist, included and excluded', [$playlist(['include' => (string) $one]), $playlist(['id' => $parent, 'exclude' => (string) $one])]);
// A post's own attachments are listed only for someone who may read the post (an anonymous request may not).
wp_set_current_user(1);
$say('the playlist of a post\'s audio, signed in', $playlist(['id' => $parent]));
$say('the playlist of a post\'s audio, signed in, by title, descending', $playlist(['id' => $parent, 'orderby' => 'title', 'order' => 'DESC']));
$say('the playlist of a post\'s audio, signed in, one excluded', $playlist(['id' => $parent, 'exclude' => (string) $one]));
$say('the playlist of a post\'s videos, signed in', $playlist(['id' => $parent, 'type' => 'video']));
wp_set_current_user(0);
$say('the playlist of nothing', [$playlist(['ids' => '999999999']), $playlist(['id' => 999999999])]);
$say('the playlist, a mixed list as audio', $playlist(['ids' => "{$clip},{$one}"]));
$short = static fn ($output, $attr, $instance) => '<zz-playlist data-n="' . $instance . '"/>';
add_filter('post_playlist', $short, 10, 3);
$say('the playlist, filtered short', $playlist(['ids' => (string) $one]));
remove_filter('post_playlist', $short, 10);
$styles = static fn ($style) => 'zz-' . $style;
add_filter('playlist_styles', $styles);
$say('the playlist, its styles filtered', $playlist(['ids' => (string) $one, 'style' => 'dark']));
remove_filter('playlist_styles', $styles);
$say('the playlist scripts', [wp_script_is('wp-playlist', 'enqueued'), wp_style_is('wp-mediaelement', 'enqueued'), has_action('wp_footer', 'wp_underscore_playlist_templates'), has_action('admin_footer', 'wp_underscore_playlist_templates')]);
ob_start();
wp_underscore_playlist_templates();
$say('wp_underscore_playlist_templates', ob_get_clean());
$say('the playlist shortcode', [shortcode_exists('playlist'), $mask(do_shortcode('[playlist ids="' . $two . '" tracklist="0"]'))]);

$square = $media('ZZ Square', 'square.mp4', 'video/mp4', ['length_formatted' => '0:10', 'width' => 400, 'height' => 400], 4);
$wide = $media('ZZ Wide', 'wide.webm', 'video/webm', ['width' => 1280, 'height' => 720], 5);
$bare = $media('ZZ Bare', 'bare.mp4', 'video/mp4', [], 6);
$say('the playlist of videos, square then wide', $playlist(['ids' => "{$square},{$wide}", 'type' => 'video']));
$say('the playlist of videos, wide then square', $playlist(['ids' => "{$wide},{$square}", 'type' => 'video', 'images' => 'false']));
$say('the playlist of videos, square then bare', $playlist(['ids' => "{$square},{$bare}", 'type' => 'video', 'images' => 0]));
$say('the playlist of an unknown type', $playlist(['ids' => "{$one},{$clip}", 'type' => 'nope']));
$GLOBALS['content_width'] = 500;
$say('the playlist with a content width', [$playlist(['ids' => (string) $square, 'type' => 'video', 'images' => false]), $playlist(['ids' => (string) $one, 'images' => false])]);
unset($GLOBALS['content_width']);
$feedMain = $GLOBALS['wp_query'] ?? null;
$feedMainThe = $GLOBALS['wp_the_query'] ?? null;
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['feed' => 'rss2']);
$say('the playlist in a feed', [is_feed(), $playlist(['ids' => "{$one},{$two}"])]);
$GLOBALS['wp_query'] = $feedMain;
$GLOBALS['wp_the_query'] = $feedMainThe;

// Link rel filters, and small formatting.
$relHooks = ['title_save_pre', 'content_save_pre', 'excerpt_save_pre', 'content_filtered_save_pre', 'pre_comment_content', 'pre_term_description', 'pre_link_description', 'pre_link_notes', 'pre_user_description'];
$rels = static fn () => array_map(static fn ($hook) => has_filter($hook, 'wp_targeted_link_rel'), $relHooks);
$say('the targeted link rel filters at first', $rels());
wp_init_targeted_link_rel_filters();
$say('wp_init_targeted_link_rel_filters', $rels());
wp_remove_targeted_link_rel_filters();
$say('wp_remove_targeted_link_rel_filters', $rels());
$say('wp_rel_nofollow', [
    wp_rel_nofollow('<a href="https://example.com/">x</a>'),
    wp_rel_nofollow('<a href=\\"https://example.com/\\" rel=\\"friend\\">x</a>'),
    wp_rel_nofollow('<a href="' . home_url('/inside/') . '">in</a> <a href="#top">top</a> <a href="https://example.com/" rel="nofollow external">out</a>'),
    wp_rel_nofollow('<A HREF="https://example.com/" REL="me">x</A> plain'),
]);
$say('wp_rel_callback', [wp_rel_callback(['<a href="https://example.com/" rel="me">', ' href="https://example.com/" rel="me"'], 'nofollow'), wp_rel_callback(['<a href="https://example.com/">', ' href="https://example.com/"'], 'ugc noopener')]);
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$say('wp_is_internal_link', array_map('wp_is_internal_link', [home_url('/x/'), 'http://' . $host . '/x', strtoupper(home_url('/X')), 'https://elsewhere.example/', '//' . $host . '/x', '/x', 'mailto:me@' . $host, 'ftp://' . $host . '/f', '']));
$say('wp_internal_hosts', str_replace($host, '{host}', wp_internal_hosts()));
$say('wp_rel_nofollow_callback', wp_rel_nofollow_callback(['<a href="https://example.com/">', 'href="https://example.com/"']));
$say('wp_rel_nofollow, single quotes and bare names', [wp_rel_nofollow("<a href='https://example.com/' download>x</a>"), wp_rel_nofollow("<a href='https://example.com/' rel='me' download>x</a>"), wp_rel_nofollow('<a href="' . home_url('/') . '" rel="nofollow me">home</a>')]);
$say('before_last_bar', [before_last_bar('a|b|c'), before_last_bar('none'), before_last_bar('|lead'), before_last_bar('trail|'), before_last_bar('')]);
$say('_split_str_by_whitespace', [_split_str_by_whitespace('one two three four five six', 8), _split_str_by_whitespace('averyveryverylongword short', 5), _split_str_by_whitespace("tab\tand\nnewline words", 6), _split_str_by_whitespace('', 5)]);
$say('wp_specialchars', [wp_specialchars('<a> & "q" \'s\''), wp_specialchars('<a> & "q" \'s\'', ENT_QUOTES), wp_specialchars('&amp; &lt;', 'double'), wp_specialchars('x', 1)]);

// Addresses.
$comment = wp_insert_comment(['comment_post_ID' => $parent, 'comment_author' => 'ZZ Commenter', 'comment_author_email' => 'zz-commenter@example.com', 'comment_author_url' => 'https://commenter.example.com/', 'comment_content' => 'Hi.', 'comment_approved' => 1]);
$comment = is_int($comment) ? $comment : 0;
$made['comments'][] = $comment;
$ids[$comment] = 'comment';
$noMail = wp_insert_comment(['comment_post_ID' => $parent, 'comment_author' => 'ZZ Quiet', 'comment_author_email' => '', 'comment_content' => 'Hush.', 'comment_approved' => 1]);
$noMail = is_int($noMail) ? $noMail : 0;
$made['comments'][] = $noMail;
$ids[$noMail] = 'quiet';
$say('get_comment_author_email', [get_comment_author_email($comment), get_comment_author_email(get_comment($noMail)), get_comment_author_email(999999999)]);
$heard = [];
$spy = static function ($value, ...$rest) use (&$heard) {
    $heard[] = [current_filter(), count($rest)];
    return $value;
};
add_filter('get_comment_author_email', $spy, 10, 3);
add_filter('author_email', $spy, 10, 2);
ob_start();
comment_author_email($comment);
$say('comment_author_email', [ob_get_clean(), $mask($heard)]);
remove_filter('get_comment_author_email', $spy, 10);
remove_filter('author_email', $spy, 10);
$heard = [];
add_filter('comment_email', $spy, 10, 2);
// The address is scrambled into random entities on every call; compared decoded.
$decoded = static fn ($html) => html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5);
$say('get_comment_author_email_link', $mask([$decoded(get_comment_author_email_link('', '', '', $comment)), $decoded(get_comment_author_email_link('Mail', '<b>', '</b>', get_comment($comment))), get_comment_author_email_link('Mail', '<b>', '</b>', get_comment($noMail)), $heard]));
remove_filter('comment_email', $spy, 10);
$GLOBALS['comment'] = get_comment($comment);
ob_start();
comment_author_email_link('Write', '[', ']');
$say('comment_author_email_link', [$mask($decoded(ob_get_clean()))]);
unset($GLOBALS['comment']);
$say('get_the_author_email', [$try(static fn () => get_the_author_email()), $try(static function () {
    $GLOBALS['authordata'] = get_userdata(1);
    $out = [get_the_author_email()];
    ob_start();
    the_author_email();
    $out[] = ob_get_clean();
    unset($GLOBALS['authordata']);
    return $out;
})]);
$byEmail = get_user_by_email(get_userdata(1)->user_email);
$say('get_user_by_email', [$byEmail instanceof WP_User ? (int) $byEmail->ID : $byEmail, get_user_by_email('zz-nobody@example.com')]);

// The posts navigation on a paged query.
$navMain = $GLOBALS['wp_query'] ?? null;
$navMainThe = $GLOBALS['wp_the_query'] ?? null;
foreach ([1, 2] as $paged) {
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['posts_per_page' => 1, 'paged' => $paged]);
    set_query_var('paged', $paged);
    $GLOBALS['paged'] = $paged;
    ob_start();
    posts_nav_link(' | ', '&laquo; Newer', 'Older &raquo;');
    $say("posts_nav_link on page {$paged}", [(string) preg_replace('#/page/\d+/#', '/page/{n}/', $mask(ob_get_clean())), (string) preg_replace('#/page/\d+/#', '/page/{n}/', $mask(get_posts_nav_link(['sep' => ' — ', 'prelabel' => 'Back', 'nxtlabel' => 'On'])))]);
}
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['p' => $parent]);
$say('get_posts_nav_link on a single post', get_posts_nav_link());
$GLOBALS['wp_query'] = $navMain;
$GLOBALS['wp_the_query'] = $navMainThe;
unset($GLOBALS['paged']);
set_query_var('paged', 0);

// Meta SQL, caches and blocks.
global $wpdb;
$say('get_meta_sql', array_map(static fn ($sql) => is_array($sql) ? array_map(static fn ($part) => str_replace($wpdb->prefix, '{prefix}', (string) $part), $sql) : $sql, [
    get_meta_sql([['key' => 'zz_key', 'value' => 'zz', 'compare' => '=']], 'post', $wpdb->posts, 'ID'),
    get_meta_sql(['relation' => 'OR', ['key' => 'a', 'compare' => 'EXISTS'], ['key' => 'b', 'value' => [1, 2], 'compare' => 'IN', 'type' => 'NUMERIC']], 'user', $wpdb->users, 'ID'),
    get_meta_sql([], 'post', $wpdb->posts, 'ID'),
    get_meta_sql([['key' => 'c']], 'nope', $wpdb->posts, 'ID'),
]));
$say('the cache primers', [
    update_post_author_caches([get_post($parent)]),
    update_comment_cache([get_comment($comment)]),
    update_comment_cache([get_comment($comment)], false),
    update_term_cache([get_term(1)], 'category'),
    global_terms_enabled(),
    wp_prime_option_caches_by_group('discussion'),
    wp_prime_option_caches_by_group('zz-nope'),
]);
$before = wp_cache_get_last_changed('posts');
wp_cache_set_posts_last_changed();
$say('wp_cache_set_posts_last_changed', [wp_cache_set_posts_last_changed(), wp_cache_get_last_changed('posts') !== $before]);
$blocks = parse_blocks('<!-- wp:group --><div><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- wp:columns --><div><!-- wp:column --><div><!-- wp:heading --><h2>h</h2><!-- /wp:heading --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group --><!-- wp:image /-->');
$flat = _flatten_blocks($blocks);
$none = [];
$say('_flatten_blocks', [array_map(static fn ($block) => $block['blockName'], $flat), count(_flatten_blocks($none))]);

// Images and types.
$image = $post(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'ZZ Picture', 'post_mime_type' => 'image/jpeg'], 'picture');
update_post_meta($image, '_wp_attached_file', 'zz-queue/picture.jpg');
update_post_meta($image, '_wp_attachment_metadata', ['width' => 800, 'height' => 600, 'file' => 'zz-queue/picture.jpg', 'sizes' => ['medium' => ['file' => 'picture-300x225.jpg', 'width' => 300, 'height' => 225, 'mime-type' => 'image/jpeg']]]);
$imgHeard = [];
$imgSpy = static function ($value, ...$rest) use (&$imgHeard) {
    $imgHeard[] = [current_filter(), count($rest)];
    return $value;
};
add_filter('get_image_tag_class', $imgSpy, 10, 4);
add_filter('get_image_tag', $imgSpy, 10, 6);
$say('get_image_tag', $mask([get_image_tag($image, 'An "alt"', 'A title', 'left'), get_image_tag($image, '', '', 'none', 'full'), get_image_tag($image, 'x', '', 'center', [100, 50]), get_image_tag(999999999, 'x', '', 'left'), $imgHeard]));
remove_filter('get_image_tag_class', $imgSpy, 10);
remove_filter('get_image_tag', $imgSpy, 10);
$say('wp_make_content_images_responsive', $mask(wp_make_content_images_responsive('<p><img src="' . wp_get_attachment_url($image) . '" class="wp-image-' . $image . '" /></p>')));
$say('wp_get_default_extension_for_mime_type', array_map('wp_get_default_extension_for_mime_type', ['image/jpeg', 'image/png', 'audio/mpeg', 'text/plain', 'application/x-nope', '', 'IMAGE/JPEG']));
$say('_device_can_upload', _device_can_upload());

// Arrays, ids, theme folders, block assets.
$say('array_first and array_last', [array_first([3 => 'a', 1 => 'b']), array_last([3 => 'a', 1 => 'b']), array_first([]), array_last([]), array_first(['k' => null, 'j' => 2])]);
$say('wp_unique_id_from_values', [wp_unique_id_from_values(['a' => 1, 'b' => [2, 3]]), wp_unique_id_from_values(['a' => 1, 'b' => [2, 3]], 'zz-'), wp_unique_id_from_values(['b' => [2, 3], 'a' => 1]), $try(static fn () => wp_unique_id_from_values([]))]);
$say('wp_is_theme_directory_ignored', array_map('wp_is_theme_directory_ignored', ['node_modules', 'vendor', '.git', 'src/node_modules', 'templates', 'node_modules/x', '.github', 'bower_components', '.svn', 'tests', 'dist', 'vendorx', 'Vendor', '', '/node_modules', 'parts/vendor']));
$onDemand = [wp_should_load_block_assets_on_demand()];
add_filter('should_load_block_assets_on_demand', '__return_true');
$onDemand[] = wp_should_load_block_assets_on_demand();
remove_filter('should_load_block_assets_on_demand', '__return_true');
add_filter('should_load_block_assets_on_demand', '__return_false');
$onDemand[] = wp_should_load_block_assets_on_demand();
remove_filter('should_load_block_assets_on_demand', '__return_false');
$onDemand[] = wp_should_load_separate_core_block_assets();
$say('wp_should_load_block_assets_on_demand', $onDemand);

// Placeholders themes call.
$page = $post(['post_type' => 'page', 'post_title' => 'ZZ Queue Page'], 'page');
$child = $post(['post_type' => 'page', 'post_title' => 'ZZ Queue Child', 'post_parent' => $page], 'child');
$say('get_post_parent', $mask([get_post_parent($child)->ID ?? null, get_post_parent($page), get_post_parent(999999999), get_post_parent(get_post($child))->post_title ?? null]));
$say('get_all_page_ids', $mask(array_values(array_intersect(array_map('intval', get_all_page_ids()), [$page, $child, 2, 6]))));
$say('cancel_comment_reply_link', (function () use ($mask) {
    $out = [];
    foreach (['', 'Never mind'] as $text) {
        ob_start();
        $returned = cancel_comment_reply_link($text);
        $out[] = [$mask(ob_get_clean()), $returned];
    }
    return $out;
})());
wp_set_current_user(1);
$say('edit_term_link', (function () use ($mask) {
    $out = [];
    foreach ([['', '', '', 1], ['Edit me', '<p>', '</p>', 1], ['Edit', '', '', get_term(1)], ['Edit', '', '', 999999999]] as [$link, $before, $after, $term]) {
        ob_start();
        $returned = edit_term_link($link, $before, $after, $term, true);
        $printed = ob_get_clean();
        $out[] = [$mask((string) preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce={nonce}', $printed)), $mask((string) preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce={nonce}', (string) edit_term_link($link, $before, $after, $term, false))), $returned];
    }
    return $out;
})());
wp_set_current_user(0);
ob_start();
$anonymous = edit_term_link('Edit', '', '', 1, false);
$say('edit_term_link, signed out', [ob_get_clean(), $anonymous]);
$say('is_login', [is_login(), $try(static function () {
    $script = $_SERVER['SCRIPT_NAME'] ?? null;
    $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
    $login = is_login();
    $_SERVER['SCRIPT_NAME'] = $script;
    return $login;
})]);
$say('_deep_replace', [_deep_replace('%0d', 'a%0%0d0dd'), _deep_replace(['a', 'b'], 'aabbab-c'), _deep_replace('x', ''), _deep_replace('', 'abc')]);
$say('add_magic_quotes', [add_magic_quotes(['a' => "it's", 'b' => ['c' => 'say "hi"', 'd' => 3], 'e' => null, 'f' => true]), addslashes_gpc("it's"), addslashes_gpc(['x' => "y'z"])]);
$say('wp_scripts_get_suffix', [wp_scripts_get_suffix(), $try(static fn () => wp_scripts_get_suffix('typescript'))]);
$themeData = get_theme_data(get_stylesheet_directory() . '/style.css');
$say('get_theme_data', [array_keys((array) $themeData), $themeData['Name'] ?? null, $themeData['Template'] ?? null, isset($themeData['Version'])]);
add_post_meta($parent, 'zz_visible', 'one');
add_post_meta($parent, 'zz_visible', 'two');
add_post_meta($parent, '_zz_hidden', 'three');
$say('has_meta', $mask(array_map(static fn ($row) => array_diff_key((array) $row, ['meta_id' => 1]) + ['meta_id' => is_numeric($row['meta_id'] ?? null)], array_values(array_filter(has_meta($parent), static fn ($row) => str_contains((string) $row['meta_key'], 'zz'))))));
$say('clean_attachment_cache', [clean_attachment_cache($one), clean_attachment_cache($one, true)]);

$say('deprecated, as reported', $deprecated);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
