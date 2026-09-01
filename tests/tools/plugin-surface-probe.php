<?php
/**
 * Behaviour probe for the second plugin surface: the functions a large
 * plugin (WooCommerce was the yardstick) calls that the first batteries did
 * not cover. Runs unchanged on the reference (wp eval-file) and on the
 * engine's facade (tests/api.test.php); prints one JSON transcript.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$out = static function (callable $fn): string { ob_start(); $fn(); return (string) ob_get_clean(); };
$err = static fn ($v) => is_wp_error($v) ? ['error' => $v->get_error_code()] : $v;
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}

// Small formatting and predicate helpers.
$say('iso8601_timezone_to_offset', [iso8601_timezone_to_offset('+02:00'), iso8601_timezone_to_offset('Z'), iso8601_timezone_to_offset('-0530'), iso8601_timezone_to_offset('+0100'), iso8601_timezone_to_offset('garbage')]);
$say('rest_is_ip_address', [rest_is_ip_address('1.2.3.4'), rest_is_ip_address('::1'), rest_is_ip_address('nope'), rest_is_ip_address('1.2.3'), rest_is_ip_address('')]);
add_shortcode('minnprobe', static fn () => 'x');
$say('shortcode_unautop', [shortcode_unautop('<p>[minnprobe]</p>'), shortcode_unautop("<p>[a]\n[/a]</p>"), shortcode_unautop('<p>text [minnprobe] text</p>'), shortcode_unautop('<p>[minnprobe]<br />\n[x]</p>'), shortcode_unautop("<p>[minnprobe]\ntext[/minnprobe]</p>"), shortcode_unautop('<p> [minnprobe id="1" /] </p>')]);
remove_shortcode('minnprobe');
$say('is_object_in_taxonomy', [is_object_in_taxonomy('post', 'category'), is_object_in_taxonomy('page', 'category'), is_object_in_taxonomy('post', 'post_tag'), is_object_in_taxonomy('attachment', 'category'), is_object_in_taxonomy('post', 'nope')]);
$say('sanitize_post_field', array_map(static fn ($c) => sanitize_post_field('post_title', 'x <b>"y"</b> & z', 1, $c), ['raw', 'edit', 'db', 'display', 'attribute', 'js']));
$say('sanitize_post_field content', array_map(static fn ($c) => sanitize_post_field('post_content', "<p>a</p>\n<script>x</script>", 1, $c), ['raw', 'edit', 'display']));
$say('sanitize_post_field int', [sanitize_post_field('post_author', '3', 1, 'display'), sanitize_post_field('ID', '3', 1, 'display'), sanitize_post_field('post_parent', 'x', 1, 'db')]);
$say('sanitize_title_for_query', [sanitize_title_for_query('Hello World!'), sanitize_title_for_query('Ünïcode Ok'), sanitize_title_for_query('a%20b')]);
$say('_cleanup_header_comment', [_cleanup_header_comment(' Foo */ '), _cleanup_header_comment("Bar ?>\n"), _cleanup_header_comment('Plain')]);
$say('_wp_filter_build_unique_id', [_wp_filter_build_unique_id('h', 'strlen', 10), _wp_filter_build_unique_id('h', 'Foo::bar', 10), _wp_filter_build_unique_id('h', ['Foo', 'bar'], 10), _wp_filter_build_unique_id('h', ['Foo', 'bar'], 20)]);
$say('wp_omit_loading_attr_threshold', wp_omit_loading_attr_threshold());
$say('wp_should_load_separate_core_block_assets', wp_should_load_separate_core_block_assets());
$say('wp_get_password_hint', wp_get_password_hint());
$say('validate_username', [validate_username('admin'), validate_username('bad name!'), validate_username('ok_user-1'), validate_username(''), validate_username('Ünï'), validate_username('a.b@c.d'), validate_username(' spaced ')]);
$say('get_the_category_by_ID', [get_the_category_by_ID(1), $err(get_the_category_by_ID(999999))]);
$say('get_dropins', array_keys(get_dropins()));
$say('wp_get_installed_translations core', array_keys(wp_get_installed_translations('core')));
$say('wp_get_installed_translations plugins', is_array(wp_get_installed_translations('plugins')));

// Options.
delete_option('minn_probe_al');
add_option('minn_probe_al', 'v', '', false);
$say('wp_set_option_autoload_values', wp_set_option_autoload_values(['minn_probe_al' => true, 'minn_probe_missing_al' => false, 'minn_probe_al2' => 'yes']));
$say('wp_set_option_autoload_values again', wp_set_option_autoload_values(['minn_probe_al' => true]));
$say('autoload after', $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", 'minn_probe_al')));
delete_option('minn_probe_al');
add_option('minn_probe_pr', 'primed');
$say('wp_prime_option_caches', wp_prime_option_caches(['minn_probe_pr', 'minn_probe_missing']));
$say('primed get', get_option('minn_probe_pr'));
delete_option('minn_probe_pr');

// Roles.
remove_role('minn_probe_role');
$role = add_role('minn_probe_role', 'Probe Role', ['read' => true, 'minn_cap' => false]);
$say('add_role', [get_class($role), $role->name, $role->capabilities]);
$say('add_role existing', add_role('minn_probe_role', 'Again', []));
$say('add_role empty name', add_role('', 'X', []));
$say('get_role added', get_role('minn_probe_role')->capabilities);
$say('roles option has it', isset(get_option($GLOBALS['wpdb']->prefix . 'user_roles')['minn_probe_role']));
$say('remove_role', remove_role('minn_probe_role'));
$say('get_role removed', get_role('minn_probe_role'));
$say('remove_role missing', remove_role('minn_probe_role'));

// Terms and taxonomy helpers.
$say('wp_unique_term_slug', [wp_unique_term_slug('uncategorized', get_term(1)), wp_unique_term_slug('brand-new-slug', (object) ['taxonomy' => 'category', 'term_id' => 0, 'parent' => 0]), wp_unique_term_slug('uncategorized', (object) ['taxonomy' => 'category', 'term_id' => 0, 'parent' => 0]), wp_unique_term_slug('uncategorized', (object) ['taxonomy' => 'post_tag', 'term_id' => 0, 'parent' => 0])]);
$before = (int) get_term(1)->count;
$say('_update_post_term_count', [_update_post_term_count([1], get_taxonomy('category')), (int) get_term(1)->count === $before]);
$say('update_termmeta_cache', [update_termmeta_cache([1]), update_termmeta_cache([]), update_termmeta_cache([999999])]);
$postsForCache = [get_post(1)];
$say('update_post_caches', update_post_caches($postsForCache, 'post', true, true));
$say('update_post_thumbnail_cache', update_post_thumbnail_cache(new WP_Query(['p' => 1])));
$say('wp_list_categories', wp_list_categories(['echo' => false]));
$say('wp_list_categories args', wp_list_categories(['echo' => false, 'title_li' => '', 'show_count' => true, 'hide_empty' => false, 'orderby' => 'name', 'style' => 'list', 'current_category' => 1]));
$say('wp_list_categories none', wp_list_categories(['echo' => false, 'title_li' => '', 'taxonomy' => 'post_tag', 'include' => [999999]]));
$say('wp_list_categories flat', wp_list_categories(['echo' => false, 'style' => 'none', 'title_li' => '', 'hide_empty' => false]));
$say('wp_dropdown_categories', [wp_dropdown_categories(['echo' => 0]), wp_dropdown_categories(['echo' => 0, 'show_option_none' => 'Pick', 'hide_empty' => 0, 'show_count' => 1, 'name' => 'product_cat', 'id' => 'pc', 'class' => 'x', 'selected' => 1, 'value_field' => 'slug', 'option_none_value' => '', 'orderby' => 'name']), wp_dropdown_categories(['echo' => 0, 'show_option_all' => 'All', 'hide_if_empty' => 1, 'taxonomy' => 'post_tag', 'include' => [999999]]), wp_dropdown_categories(['echo' => 0, 'show_option_all' => 'All', 'hide_if_empty' => 0, 'taxonomy' => 'post_tag', 'include' => [999999], 'required' => true, 'aria_describedby' => 'help', 'tab_index' => 3]), wp_dropdown_categories(['echo' => 0, 'show_option_all' => 'All', 'selected' => 0, 'hierarchical' => 1, 'hide_empty' => 0, 'taxonomy' => 'post_tag'])]);
$say('wp_tag_cloud', wp_tag_cloud(['echo' => false]));
$say('wp_tag_cloud args', wp_tag_cloud(['echo' => false, 'taxonomy' => 'category', 'hide_empty' => false, 'format' => 'list', 'number' => 3, 'orderby' => 'count', 'order' => 'DESC']));
$say('wp_tag_cloud none', wp_tag_cloud(['echo' => false, 'include' => '999999']));

// Template tags a classic commerce theme needs (Storefront's functions.php
// is gated on all of these existing).
$say('get_the_category_list', [get_the_category_list('', '', 1), get_the_category_list(' | ', '', 1), get_the_category_list(', ', '', 999999)]);
$say('get_the_tag_list', [get_the_tag_list('<span>', ', ', '</span>', 5), get_the_tag_list('', ', ', '', 2)]);
$say('adjacent post links', (static function (): array {
    $GLOBALS['post'] = get_post(5);
    setup_postdata($GLOBALS['post']);
    $out = [
        get_previous_post_link(),
        get_next_post_link(),
        get_the_post_navigation(),
        get_the_post_navigation(['prev_text' => 'P: %title', 'next_text' => 'N: %title', 'screen_reader_text' => 'More', 'class' => 'nav-x']),
    ];
    wp_reset_postdata();
    return $out;
})());
// A theme reads its own headers as array offsets (Storefront does), so
// WP_Theme is ArrayAccess as well as an object.
$say('WP_Theme array access', (static function (): array {
    $theme = wp_get_theme();
    return [
        $theme instanceof ArrayAccess,
        (string) $theme['Name'],
        (string) $theme['Version'],
        (string) $theme['Stylesheet'],
        (string) $theme['Template'],
        isset($theme['Nope']),
        $theme['Nope'] ?? null,
    ];
})());

// Post helpers.
$say('get_post_class post', get_post_class('', 1));
$say('get_post_class page', get_post_class(['extra', 'two'], 2));
$say('get_post_class string', get_post_class('a b', 1));
$say('get_post_class missing', get_post_class('', 999999));
$GLOBALS['post'] = get_post(1);
setup_postdata($GLOBALS['post']);
$say('the_ID', $out(static fn () => the_ID()));
$say('get_post_class global', get_post_class());
$say('get_page_template_slug', [get_page_template_slug(2), get_page_template_slug(1), get_page_template_slug(999999)]);
$say('is_page_template', [is_page_template(), is_page_template('nope.php')]);
$say('get_query_template', array_map('basename', [get_query_template('single'), get_query_template('single', ['single-post.php', 'single.php']), get_query_template('nope', ['nope.php']), get_query_template('index')]));
$say('get_query_template filter', (static function () { add_filter('single_template', static fn ($t, $type, $names) => "filtered:{$type}:" . implode(',', $names), 10, 3); $r = get_query_template('single', ['single-post.php']); remove_all_filters('single_template'); return $r; })());
wp_reset_postdata();

// Comments.
$comment = get_comment(1);
$GLOBALS['comment'] = $comment;
$say('comment_ID', $out(static fn () => comment_ID()));
$say('comment_author', $out(static fn () => comment_author()));
$say('comment_author id', $out(static fn () => comment_author(1)));
$say('get_comment_author_url', [get_comment_author_url(1), get_comment_author_url(999999)]);
$say('get_comment_author_IP', get_comment_author_IP(1));
$say('comment_author_IP', $out(static fn () => comment_author_IP(1)));
$say('get_comment_class', [get_comment_class('', 1, 1), get_comment_class(['x'], $comment, 1)]);
$say('comment_class', $out(static fn () => comment_class('extra', 1, 1)));
$say('comment_class no echo', comment_class('', 1, 1, false));
$say('get_pending_comments_num', [get_pending_comments_num(1), get_pending_comments_num([1, 2]), get_pending_comments_num(999999)]);
$say('get_approved_comments', array_map(static fn ($c) => [(int) $c->comment_ID, get_class($c)], get_approved_comments(1)));
$say('get_approved_comments args', array_map(static fn ($c) => (int) $c->comment_ID, get_approved_comments(1, ['number' => 1, 'orderby' => 'comment_ID', 'order' => 'DESC'])));
$say('get_approved_comments none', get_approved_comments(999999));
$long = ['comment_author' => str_repeat('a', 246), 'comment_author_email' => 'a@b.c', 'comment_author_url' => 'http://x', 'comment_content' => 'ok'];
$say('wp_check_comment_data_max_lengths', [$err(wp_check_comment_data_max_lengths($long)), wp_check_comment_data_max_lengths(['comment_author' => 'a', 'comment_author_email' => 'a@b.c', 'comment_author_url' => '', 'comment_content' => 'ok']), $err(wp_check_comment_data_max_lengths(['comment_author' => 'a', 'comment_author_email' => str_repeat('e', 101), 'comment_author_url' => '', 'comment_content' => 'ok'])), $err(wp_check_comment_data_max_lengths(['comment_author' => 'a', 'comment_author_email' => 'a@b.c', 'comment_author_url' => str_repeat('u', 201), 'comment_content' => 'ok'])), $err(wp_check_comment_data_max_lengths(['comment_author' => 'a', 'comment_author_email' => 'a@b.c', 'comment_author_url' => '', 'comment_content' => str_repeat('c', 65526)]))]);
$data = ['comment_post_ID' => 1, 'comment_author' => ' Probe <b>Name</b> ', 'comment_author_email' => 'PROBE@Example.com ', 'comment_author_url' => 'example.com', 'comment_content' => 'Hello <script>x</script> there', 'comment_type' => '', 'user_ID' => 0, 'user_id' => 0, 'comment_author_IP' => '127.0.0.1', 'comment_agent' => 'probe'];
$say('wp_filter_comment', wp_filter_comment($data));
$fresh = ['comment_post_ID' => 1, 'comment_author' => 'Probe Allow ' . mt_rand(), 'comment_author_email' => 'allow' . mt_rand() . '@example.com', 'comment_author_url' => '', 'comment_content' => 'Fresh content ' . mt_rand(), 'comment_type' => 'comment', 'user_id' => 0, 'comment_author_IP' => '127.0.0.1', 'comment_agent' => 'probe', 'comment_parent' => 0, 'comment_date_gmt' => gmdate('Y-m-d H:i:s'), 'comment_date' => gmdate('Y-m-d H:i:s')];
$say('wp_allow_comment fresh', wp_allow_comment($fresh, true));
$say('wp_allow_comment duplicate', $err(wp_allow_comment(['comment_post_ID' => 1, 'comment_author' => $comment->comment_author, 'comment_author_email' => $comment->comment_author_email, 'comment_content' => $comment->comment_content, 'comment_type' => 'comment', 'user_id' => 0, 'comment_author_IP' => '127.0.0.1', 'comment_agent' => 'probe', 'comment_parent' => 0, 'comment_date_gmt' => gmdate('Y-m-d H:i:s'), 'comment_date' => gmdate('Y-m-d H:i:s')], true)));
$say('wp_allow_comment admin user', wp_allow_comment($fresh + ['x' => 1] + ['user_id' => 1], true));

// Auth.
$say('wp_signon empty', $err(wp_signon(['user_login' => '', 'user_password' => ''])));
$say('wp_signon wrong', $err(wp_signon(['user_login' => 'admin', 'user_password' => 'definitely-wrong'])));
$say('wp_signon unknown', $err(wp_signon(['user_login' => 'no-such-user-xyz', 'user_password' => 'x'])));
$user = get_user_by('id', 1);
$key = get_password_reset_key($user);
$stored = $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT user_activation_key FROM {$GLOBALS['wpdb']->users} WHERE ID = %d", 1));
$say('get_password_reset_key', [strlen($key), preg_match('/^[A-Za-z0-9]+$/', $key) === 1, preg_match('/^\d{10}:\$P\$[.\/0-9A-Za-z]{31}$/', (string) $stored) === 1, preg_match('/^\d{10}:\$[a-z]+\$[^:]+$/', (string) $stored) === 1]);
$checked = check_password_reset_key($key, 'admin');
$say('check_password_reset_key', [is_wp_error($checked) ? $checked->get_error_code() : get_class($checked), $err(check_password_reset_key('wrong', 'admin')), $err(check_password_reset_key($key, 'nobody'))]);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->users, ['user_activation_key' => ''], ['ID' => 1]);
clean_user_cache(1);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
