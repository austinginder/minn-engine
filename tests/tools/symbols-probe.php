<?php
/**
 * Behaviour probe for the small symbols the dogfood plugins were one step
 * short of: block editor per post type, entities, registered post meta,
 * sitemap URLs and limits, edit term links, feed helpers, meta deletion by
 * key, paths, kses filters, screen options, WP_Term_Query, WP_Site, the
 * direct filesystem, update helpers. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (defined('ABSPATH')) {
    foreach (['screen', 'misc', 'file', 'template', 'media', 'dashboard', 'plugin', 'theme', 'update', 'ms'] as $inc) {
        if (is_file(ABSPATH . 'wp-admin/includes/' . $inc . '.php')) {
            require_once ABSPATH . 'wp-admin/includes/' . $inc . '.php';
        }
    }
}
$home = home_url();
$rel = static fn ($v) => is_string($v) ? str_replace([$home, rtrim(ABSPATH, '/')], ['{home}', '{abspath}'], $v) : $v;
$capture = static function (callable $fn) use ($rel): string {
    ob_start();
    $fn();
    return $rel((string) ob_get_clean());
};
wp_set_current_user(1);

// Block editor per post type.
register_post_type('minn_probe_norest', ['public' => true]);
register_post_type('minn_probe_rest', ['public' => true, 'show_in_rest' => true]);
$say('use_block_editor_for_post_type', [use_block_editor_for_post_type('post'), use_block_editor_for_post_type('page'), use_block_editor_for_post_type('attachment'), use_block_editor_for_post_type('nope'), use_block_editor_for_post_type('minn_probe_norest'), use_block_editor_for_post_type('minn_probe_rest'), use_block_editor_for_post_type('wp_block'), use_block_editor_for_post_type('revision')]);
add_filter('use_block_editor_for_post_type', static fn ($use, $type) => $type === 'page' ? false : $use, 10, 2);
$say('use_block_editor_for_post_type filtered', [use_block_editor_for_post_type('page'), use_block_editor_for_post_type('post')]);
unregister_post_type('minn_probe_norest');
unregister_post_type('minn_probe_rest');

$say('htmlentities2', [htmlentities2('a & b < c > "d" \'e\' &amp; &lt; &#39; &#x41; &nbsp; é'), htmlentities2(''), htmlentities2('&notanentity; &copy;')]);

// Registered post meta.
$say('register_post_meta', [register_post_meta('post', 'minn_probe_meta', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'default' => 'dflt']), register_post_meta('post', 'minn_probe_meta', ['type' => 'string']), register_post_meta('minn_probe_type', 'minn_probe_meta2', ['type' => 'integer'])]);
$keys = get_registered_meta_keys('post', 'post');
$say('registered post meta', isset($keys['minn_probe_meta']) ? array_intersect_key($keys['minn_probe_meta'], array_flip(['type', 'single', 'default', 'object_subtype', 'show_in_rest', 'description'])) : null);
$say('registered meta all', array_keys(get_registered_meta_keys('post')));
$say('registered meta subtype', array_keys(get_registered_meta_keys('post', 'minn_probe_type')));
$say('get_post_meta default', get_post_meta(1, 'minn_probe_meta', true));
unregister_post_meta('post', 'minn_probe_meta');
unregister_meta_key('post', 'minn_probe_meta2', 'minn_probe_type');
$say('registered after unregister', [array_keys(get_registered_meta_keys('post', 'post')), get_post_meta(1, 'minn_probe_meta', true)]);

// Sitemaps.
$say('get_sitemap_url', array_map($rel, [get_sitemap_url('posts', 'post', 1), get_sitemap_url('posts', 'post', 2), get_sitemap_url('posts', 'page'), get_sitemap_url('taxonomies', 'category', 3), get_sitemap_url('users'), get_sitemap_url('users', '', 2), get_sitemap_url('index'), get_sitemap_url('nope'), get_sitemap_url('posts', 'nope'), get_sitemap_url('posts', 'post', 0)]));
$say('wp_sitemaps_get_max_urls', [wp_sitemaps_get_max_urls('post'), wp_sitemaps_get_max_urls('term'), wp_sitemaps_get_max_urls('user'), wp_sitemaps_get_max_urls('nope')]);
add_filter('wp_sitemaps_max_urls', static fn ($n, $type) => $type === 'post' ? 7 : $n, 10, 2);
$say('wp_sitemaps_get_max_urls filtered', [wp_sitemaps_get_max_urls('post'), wp_sitemaps_get_max_urls('term')]);
$server = wp_sitemaps_get_server();
$say('wp_sitemaps_get_server', [get_class($server), get_class($server->registry), get_class($server->renderer), get_class($server->index), array_keys($server->registry->get_providers()), $server->sitemaps_enabled(), $rel($server->index->get_index_url()), $rel($server->registry->get_provider('posts')->get_sitemap_url('post', 1))]);

// Edit term link, feed helpers.
$say('get_edit_term_link', array_map($rel, [get_edit_term_link(1), get_edit_term_link(1, 'category'), get_edit_term_link(1, 'category', 'post'), get_edit_term_link(1, 'nope'), get_edit_term_link(999999), get_edit_term_link(get_term(1)), get_edit_term_link(1, 'category', 'page')]));
wp_set_current_user(0);
$say('get_edit_term_link anonymous', get_edit_term_link(1));
wp_set_current_user(1);
$say('feed_content_type', [feed_content_type(), feed_content_type('rss2'), feed_content_type('rss'), feed_content_type('rss-http'), feed_content_type('atom'), feed_content_type('rdf'), feed_content_type('nope')]);
$say('get_bloginfo_rss', array_map($rel, [get_bloginfo_rss('name'), get_bloginfo_rss('description'), get_bloginfo_rss('url'), get_bloginfo_rss('language'), get_bloginfo_rss('charset'), get_bloginfo_rss('nope'), get_bloginfo_rss()]));
$say('bloginfo_rss', $capture(static fn () => bloginfo_rss('name')));
update_option('blogname', 'Probe & <b>Site</b> “q”');
$say('get_bloginfo_rss escaped', get_bloginfo_rss('name'));
update_option('blogname', 'Minn Engine');

// Meta deletion by key.
add_post_meta(1, 'minn_probe_bykey', 'a');
add_post_meta(1, 'minn_probe_bykey', 'b');
add_post_meta(2, 'minn_probe_bykey', 'c');
$say('delete_post_meta_by_key', [delete_post_meta_by_key('minn_probe_bykey'), get_post_meta(1, 'minn_probe_bykey'), get_post_meta(2, 'minn_probe_bykey'), delete_post_meta_by_key('minn_probe_bykey'), delete_post_meta_by_key('')]);

// Paths.
$say('_wp_get_attachment_relative_path', [_wp_get_attachment_relative_path('2024/01/photo.jpg'), _wp_get_attachment_relative_path('photo.jpg'), _wp_get_attachment_relative_path('/abs/2024/01/photo.jpg'), _wp_get_attachment_relative_path(''), _wp_get_attachment_relative_path('a/b/c/d.png')]);
$say('get_home_path', $rel(get_home_path()));

// Kses filters.
$state = static fn () => [has_filter('content_save_pre', 'wp_filter_post_kses'), has_filter('excerpt_save_pre', 'wp_filter_post_kses'), has_filter('content_filtered_save_pre', 'wp_filter_post_kses'), has_filter('pre_comment_content', 'wp_filter_kses'), has_filter('title_save_pre', 'wp_filter_kses'), has_filter('pre_comment_author_name', 'wp_filter_kses'), has_filter('pre_term_description', 'wp_filter_kses'), has_filter('pre_link_description', 'wp_filter_kses')];
$say('kses filters initial', $state());
kses_remove_filters();
$say('kses filters removed', $state());
kses_init_filters();
$say('kses filters init', $state());
kses_init_filters();
$say('kses filters init twice', $state());

// Screen options.
set_current_screen('edit-post');
$screen = get_current_screen();
add_screen_option('per_page', ['label' => 'Items', 'default' => 20, 'option' => 'minn_probe_per_page']);
add_screen_option('layout_columns', ['max' => 4, 'default' => 2]);
$say('add_screen_option', [$screen->get_option('per_page'), $screen->get_option('per_page', 'default'), $screen->get_option('per_page', 'option'), $screen->get_option('per_page', 'nope'), $screen->get_option('layout_columns'), $screen->get_option('nope'), array_keys($screen->get_options())]);
$say('get_hidden_columns', [get_hidden_columns($screen), get_hidden_columns('edit-post'), get_hidden_columns('nope')]);
update_user_option(1, 'manageedit-postcolumnshidden', ['tags', 'comments'], true);
$say('get_hidden_columns set', [get_hidden_columns($screen), get_hidden_columns('edit-post')]);
add_filter('default_hidden_columns', static fn ($hidden, $s) => array_merge($hidden, ['probe']), 10, 2);
delete_user_option(1, 'manageedit-postcolumnshidden', true);
$say('get_hidden_columns default filter', get_hidden_columns($screen));
$say('set_screen_options no post', $capture(static fn () => set_screen_options()));

// Dashboard setup and the iframe shell.
$say('wp_dashboard_setup', (static function () {
    try {
        ob_start();
        wp_dashboard_setup();
        ob_end_clean();
        return [did_action('wp_dashboard_setup') > 0, array_keys($GLOBALS['wp_meta_boxes']['dashboard'] ?? [])];
    } catch (Throwable $e) {
        ob_end_clean();
        return ['threw', get_class($e)];
    }
})());
$say('wp_iframe', (static function () use ($capture) {
    if (!function_exists('minn_probe_iframe_content')) {
        function minn_probe_iframe_content($a, $b) {
            echo "CONTENT[$a|$b]";
        }
    }
    $out = $capture(static fn () => wp_iframe('minn_probe_iframe_content', 'x', 'y'));
    return [str_starts_with($out, '<!DOCTYPE html>'), str_contains($out, 'CONTENT[x|y]'), str_contains($out, '<body'), str_contains($out, '</html>'), (bool) preg_match('/<html[^>]*class="[^"]*wp-toolbar[^"]*"/', $out), (bool) preg_match('/<body[^>]*class="[^"]*wp-core-ui[^"]*"/', $out), str_contains($out, 'id="wpadminbar"')];
})());
$say('iframe_header', (static function () use ($capture) {
    $out = $capture(static fn () => iframe_header('Probe Title'));
    return [str_starts_with($out, '<!DOCTYPE html>'), str_contains($out, '<title>Probe Title'), str_contains($out, '<body'), (bool) preg_match('/<body[^>]*class="[^"]*wp-core-ui[^"]*"/', $out), str_contains($out, 'iframe')];
})());
$say('iframe_footer', (static function () use ($capture) {
    $out = $capture(static fn () => iframe_footer());
    return [str_contains($out, 'wp-auth-check-wrap'), str_contains($out, '</body>'), str_contains($out, '</html>'), str_starts_with($out, "\t<div class=\"hidden\">")];
})());

// WP_Term_Query.
$q = new WP_Term_Query(['taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
$say('WP_Term_Query terms', array_map(static fn ($t) => [get_class($t), $t->term_id, $t->name, $t->slug, $t->taxonomy], (array) $q->terms));
$say('WP_Term_Query query_vars', array_intersect_key($q->query_vars, array_flip(['taxonomy', 'hide_empty', 'orderby', 'order', 'fields', 'number', 'offset', 'include', 'exclude', 'parent', 'child_of', 'search', 'name', 'slug', 'get', 'update_term_meta_cache', 'meta_query', 'pad_counts', 'hierarchical', 'childless', 'cache_results'])));
$say('WP_Term_Query defaults', array_keys($q->query_var_defaults));
$say('WP_Term_Query request', is_string($q->request) && str_contains($q->request, 'term_taxonomy'));
$q2 = new WP_Term_Query();
$say('WP_Term_Query lazy', [$q2->terms, $q2->query_vars, array_map(static fn ($t) => $t->name, (array) $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'all'])), $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids']), $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'names']), $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'count']), $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'id=>name']), $q2->query(['taxonomy' => 'category', 'hide_empty' => false, 'number' => 1, 'fields' => 'ids']), $q2->query(['taxonomy' => 'nope']), $q2->query(['taxonomy' => ['category', 'post_tag'], 'hide_empty' => false, 'fields' => 'names'])]);
$q3 = new WP_Term_Query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids']);
$say('WP_Term_Query get_terms', [$q3->get_terms(), $q3->terms]);
$say('WP_Term_Query slug and search', [(new WP_Term_Query(['taxonomy' => 'category', 'slug' => 'uncategorized', 'hide_empty' => false, 'fields' => 'names']))->terms, (new WP_Term_Query(['taxonomy' => 'category', 'search' => 'uncat', 'hide_empty' => false, 'fields' => 'names']))->terms, (new WP_Term_Query(['taxonomy' => 'category', 'include' => [1], 'hide_empty' => false, 'fields' => 'ids']))->terms, (new WP_Term_Query(['taxonomy' => 'category', 'exclude' => [1], 'hide_empty' => false, 'fields' => 'ids']))->terms]);
$say('WP_Term_Query vs get_terms', get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids']) === (new WP_Term_Query(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids']))->terms);

// WP_Site: a single-site reference never loads the class; the multisite helpers still answer.
$say('WP_Site', [class_exists('WP_Site'), class_exists('WP_Site_Query'), class_exists('WP_Network'), class_exists('WP_Network_Query'), function_exists('get_site'), function_exists('get_sites'), function_exists('get_current_site'), function_exists('get_main_site_id')]);
$say('site helpers', [get_current_blog_id(), is_main_site(), get_current_network_id(), is_multisite(), get_main_site_id(), get_main_site_id(1), get_main_site_id(99), function_exists('get_site') ? (get_site() === null ? null : get_class(get_site())) : 'no-fn']);

// Filesystem.
$say('get_filesystem_method', [get_filesystem_method(), get_filesystem_method([], '', false)]);
$say('request_filesystem_credentials', [request_filesystem_credentials(false), request_filesystem_credentials('http://x/', '', false, '', null, false)]);
$say('WP_Filesystem', [WP_Filesystem(), get_class($GLOBALS['wp_filesystem']), get_parent_class($GLOBALS['wp_filesystem']), $GLOBALS['wp_filesystem']->method, $rel($GLOBALS['wp_filesystem']->abspath()), $rel($GLOBALS['wp_filesystem']->wp_content_dir()), $rel($GLOBALS['wp_filesystem']->wp_plugins_dir()), $rel($GLOBALS['wp_filesystem']->wp_themes_dir()), $rel($GLOBALS['wp_filesystem']->wp_lang_dir()), $GLOBALS['wp_filesystem']->errors instanceof WP_Error, $GLOBALS['wp_filesystem']->verbose]);
$fs = $GLOBALS['wp_filesystem'];
$dir = sys_get_temp_dir() . '/minn-probe-fs-' . getmypid();
$say('WP_Filesystem ops', (static function () use ($fs, $dir) {
    $r = [];
    $r[] = $fs->exists($dir);
    $r[] = $fs->mkdir($dir);
    $r[] = $fs->mkdir($dir);
    $r[] = $fs->is_dir($dir);
    $r[] = $fs->is_file($dir);
    $r[] = $fs->put_contents($dir . '/a.txt', "hello\nworld\n");
    $r[] = $fs->get_contents($dir . '/a.txt');
    $r[] = $fs->get_contents_array($dir . '/a.txt');
    $r[] = $fs->get_contents($dir . '/missing.txt');
    $r[] = $fs->exists($dir . '/a.txt');
    $r[] = $fs->is_file($dir . '/a.txt');
    $r[] = $fs->is_readable($dir . '/a.txt');
    $r[] = $fs->is_writable($dir);
    $r[] = $fs->size($dir . '/a.txt');
    $r[] = $fs->copy($dir . '/a.txt', $dir . '/b.txt');
    $r[] = $fs->copy($dir . '/a.txt', $dir . '/b.txt');
    $r[] = $fs->copy($dir . '/a.txt', $dir . '/b.txt', true);
    $r[] = $fs->move($dir . '/b.txt', $dir . '/c.txt');
    $r[] = $fs->move($dir . '/missing.txt', $dir . '/d.txt');
    $r[] = $fs->touch($dir . '/t.txt');
    $r[] = $fs->getchmod($dir . '/a.txt');
    $r[] = $fs->chmod($dir . '/a.txt', 0600);
    $r[] = $fs->getchmod($dir . '/a.txt');
    $r[] = $fs->gethchmod($dir . '/a.txt');
    $r[] = $fs->mkdir($dir . '/sub');
    $r[] = $fs->put_contents($dir . '/sub/n.txt', 'n');
    $list = $fs->dirlist($dir);
    ksort($list);
    $r[] = array_map(static fn ($e) => [$e['name'], $e['type'], isset($e['size']), isset($e['perms']), isset($e['files'])], $list);
    $listRecursive = $fs->dirlist($dir, true, true);
    $r[] = isset($listRecursive['sub']['files']) ? array_keys($listRecursive['sub']['files']) : null;
    $r[] = $fs->dirlist($dir . '/missing');
    $single = $fs->dirlist($dir . '/a.txt');
    $r[] = is_array($single) ? array_map(static fn ($e) => [$e['name'], $e['type'], $e['size'], $e['permsn'], $e['perms'], array_keys($e)], $single) : $single;
    $r[] = $fs->delete($dir . '/a.txt');
    $r[] = $fs->delete($dir . '/a.txt');
    $r[] = $fs->delete($dir . '/sub');
    $r[] = $fs->delete($dir . '/sub', true);
    $r[] = $fs->rmdir($dir);
    $r[] = $fs->delete($dir, true);
    $r[] = $fs->exists($dir);
    $r[] = $fs->cwd() !== false;
    $r[] = $fs->chdir($dir);
    $r[] = $fs->find_folder(ABSPATH . 'wp-content') === $fs->wp_content_dir();
    $r[] = $fs->is_binary("abc\0def");
    $r[] = $fs->is_binary('abc');
    $r[] = $fs->getnumchmodfromh('-rw-r--r--');
    $r[] = $fs->connect();
    return $r;
})());

// Update and admin helpers.
$say('wp_get_translation_updates', wp_get_translation_updates());
$say('get_core_updates', [is_array(get_core_updates()) || get_core_updates() === false, is_array(get_core_updates(['dismissed' => true])) || get_core_updates(['dismissed' => true]) === false]);
$say('wp_clean_plugins_cache', [wp_clean_plugins_cache(), wp_clean_plugins_cache(false), wp_clean_themes_cache(), wp_clean_themes_cache(false)]);
$say('print_admin_styles', [is_array(print_admin_styles()), $capture(static fn () => print_admin_styles()) === '']);
$say('upload_is_user_over_quota', [function_exists('upload_is_user_over_quota'), function_exists('get_space_allowed')]);
$say('delete_theme', (static function () {
    $r = delete_theme('minn-probe-nope');
    return $r instanceof WP_Error ? ['error', $r->get_error_code()] : $r;
})());
$say('delete_theme empty', (static function () {
    $r = delete_theme('');
    return $r instanceof WP_Error ? ['error', $r->get_error_code()] : $r;
})());
$say('_wp_oembed_get_object', [get_class(_wp_oembed_get_object()), _wp_oembed_get_object() === _wp_oembed_get_object(), is_array(_wp_oembed_get_object()->providers), count(_wp_oembed_get_object()->providers) > 10]);

// The functions Modula, FooGallery, and Stream were short of.
$post1 = get_post(1);
$say('the_guid', [$capture(static fn () => the_guid(1)), get_the_guid(1) === $post1->guid, $rel(get_the_guid($post1)), get_the_guid(999999), $capture(static fn () => the_guid(999999))]);
$GLOBALS['post'] = $post1;
setup_postdata($post1);
$say('the_permalink_rss', $rel($capture(static fn () => the_permalink_rss())));
add_filter('the_permalink_rss', static fn ($p) => $p . '?rss=1');
$say('the_permalink_rss filtered', $rel($capture(static fn () => the_permalink_rss())));
wp_reset_postdata();
$say('wp_validate_boolean', array_map('wp_validate_boolean', [true, false, 'true', 'false', 'FALSE', 'False', '1', '0', 1, 0, '', null, 'yes', 'no', 'off', [], [0], 'null']));
$say('_wp_image_editor_choose', [_wp_image_editor_choose(), _wp_image_editor_choose(['mime_type' => 'image/jpeg']), _wp_image_editor_choose(['mime_type' => 'image/nope']), _wp_image_editor_choose(['methods' => ['nope']])]);
$attachment = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC', 'post_mime_type' => 'image']);
$say('wp_image_src_get_dimensions', (static function () use ($attachment) {
    if ($attachment === []) {
        return 'no attachment';
    }
    $id = $attachment[0]->ID;
    $meta = wp_get_attachment_metadata($id);
    $full = wp_get_attachment_url($id);
    $base = dirname($full) . '/';
    $sizes = [];
    foreach (($meta['sizes'] ?? []) as $name => $size) {
        $sizes[$name] = wp_image_src_get_dimensions($base . $size['file'], $meta, $id);
    }
    return [wp_image_src_get_dimensions($full, $meta, $id), $sizes, wp_image_src_get_dimensions($base . 'nope.jpg', $meta, $id), wp_image_src_get_dimensions($full, [], $id), wp_image_src_get_dimensions($full, ['width' => 5, 'height' => 6, 'file' => basename($full)], 0), wp_image_src_get_dimensions('https://elsewhere.invalid/' . basename($full), $meta, $id)];
})());
$say('get_edit_user_link', array_map($rel, [get_edit_user_link(1), get_edit_user_link(), get_edit_user_link(999999), get_edit_user_link(0)]));
wp_set_current_user(0);
$say('get_edit_user_link anonymous', [get_edit_user_link(1), get_edit_user_link()]);
wp_set_current_user(1);
$say('self_link', is_string($capture(static fn () => self_link())));
$revisions = wp_get_post_revisions(1, ['numberposts' => 1]);
$say('wp_get_post_revision', (static function () use ($revisions) {
    $missing = 999999;
    $one = 1;
    $r = [wp_get_post_revision($missing), wp_get_post_revision($one)];
    if ($revisions !== []) {
        $rev = reset($revisions);
        $revId = $rev->ID;
        $got = wp_get_post_revision($revId);
        $asArray = wp_get_post_revision($revId, ARRAY_A);
        $fromObject = wp_get_post_revision($rev);
        $r[] = [get_class($got), $got->post_type, (int) $got->post_parent, $asArray['post_type'], $fromObject->ID === $rev->ID];
    }
    return $r;
})());

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
