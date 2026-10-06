<?php
/**
 * Functions the engine answered with a constant, as the reference answers
 * them (probe editor-styles): the privacy policy page's address and link,
 * get_post_timestamp, is_rtl, the theme's editor stylesheets,
 * search_theme_directories, wp_get_global_stylesheet by type,
 * wp_enqueue_block_style, and rest_preload_api_request. Same protocol as
 * api-probe.php; what it makes and changes is put back.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$mask = static fn ($value) => json_decode(str_replace([$home, addcslashes($home, '/')], '{home}', (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
$made = [];

// The privacy policy page.
$keptPolicy = get_option('wp_page_for_privacy_policy');
$published = (int) wp_insert_post(['post_title' => 'zz probe privacy <b>policy</b> & "terms"', 'post_name' => 'zz-probe-privacy', 'post_type' => 'page', 'post_status' => 'publish']);
$draft = (int) wp_insert_post(['post_title' => 'zz probe draft policy', 'post_type' => 'page', 'post_status' => 'draft']);
$untitled = (int) wp_insert_post(['post_title' => '', 'post_content' => 'zz probe untitled policy', 'post_type' => 'page', 'post_status' => 'publish']);
array_push($made, $published, $draft, $untitled);
$policy = [];
foreach (['none' => 0, 'published' => $published, 'draft' => $draft, 'untitled' => $untitled, 'missing' => 999999] as $kind => $id) {
    update_option('wp_page_for_privacy_policy', $id);
    ob_start();
    the_privacy_policy_link('<p>', '</p>');
    $echoed = ob_get_clean();
    $policy[$kind] = [get_privacy_policy_url(), get_the_privacy_policy_link(), get_the_privacy_policy_link('<span>', '</span>'), $echoed];
}
update_option('wp_page_for_privacy_policy', $published);
add_filter('privacy_policy_url', static fn ($url, $id) => $url . '#zz-' . ($id === $published ? 'page' : 'other'), 10, 2);
add_filter('the_privacy_policy_link', static fn ($link, $url) => '[' . $link . '|' . $url . ']', 10, 2);
$policy['filtered'] = [get_privacy_policy_url(), get_the_privacy_policy_link()];
remove_all_filters('privacy_policy_url');
remove_all_filters('the_privacy_policy_link');
update_option('wp_page_for_privacy_policy', $keptPolicy);
$say('privacy policy', $mask(json_decode(str_replace([(string) $published, (string) $draft, (string) $untitled], ['{published}', '{draft}', '{untitled}'], (string) json_encode($policy, JSON_UNESCAPED_SLASHES)), true)));

// Timestamps.
$dated = (int) wp_insert_post(['post_title' => 'zz probe dated', 'post_status' => 'publish', 'post_date' => '2026-02-03 04:05:06', 'post_date_gmt' => '2026-02-03 09:05:06']);
$floating = (int) wp_insert_post(['post_title' => 'zz probe floating', 'post_status' => 'draft']);
array_push($made, $dated, $floating);
$say('get_post_timestamp', [
    'published date' => get_post_timestamp($dated),
    'published modified is a number' => is_int(get_post_timestamp($dated, 'modified')),
    'draft date is a number' => is_int(get_post_timestamp($floating)),
    'unknown field' => get_post_timestamp($dated, 'zz'),
    'no such post' => get_post_timestamp(999999),
    'datetime local' => get_post_datetime($dated)?->format('c'),
    'datetime gmt' => get_post_datetime($dated, 'date', 'gmt')?->format('c'),
]);

$say('is_rtl', [is_rtl(), $GLOBALS['wp_locale']->text_direction ?? null]);

// Editor stylesheets.
$keptEditorStyles = $GLOBALS['editor_styles'] ?? null;
unset($GLOBALS['editor_styles']);
$editor = ['none' => get_editor_stylesheets(), 'theme supports' => current_theme_supports('editor-style')];
add_editor_style('style.css');
add_editor_style(['zz-probe-missing.css', 'https://example.com/zz.css']);
$editor['added'] = [$GLOBALS['editor_styles'] ?? null, current_theme_supports('editor-style')];
$editor['sheets'] = get_editor_stylesheets();
add_filter('editor_stylesheets', static fn ($sheets) => array_merge($sheets, ['zz-filtered.css']));
$editor['filtered'] = get_editor_stylesheets();
remove_all_filters('editor_stylesheets');
$editor['removed'] = [remove_editor_styles(), $GLOBALS['editor_styles'] ?? null, get_editor_stylesheets(), remove_editor_styles()];
$GLOBALS['editor_styles'] = $keptEditorStyles;
if ($keptEditorStyles === null) {
    unset($GLOBALS['editor_styles']);
}
remove_theme_support('editor-style');
$say('editor stylesheets', $mask(json_decode(str_replace([get_stylesheet_directory_uri(), addcslashes(get_stylesheet_directory_uri(), '/')], '{theme}', (string) json_encode($editor, JSON_UNESCAPED_SLASHES)), true)));

// Theme folders.
$found = search_theme_directories();
$roots = array_values(array_unique(array_map(static fn ($t) => $t['theme_root'], (array) $found)));
$say('search_theme_directories', [
    'themes' => array_keys((array) $found),
    'one' => isset($found['twentytwentyfive']) ? ['theme_file' => $found['twentytwentyfive']['theme_file'], 'root is a theme root' => in_array($found['twentytwentyfive']['theme_root'], (array) $GLOBALS['wp_theme_directories'], true)] : null,
    'roots' => count($roots),
]);

// The global stylesheet, by type.
$sheets = [];
foreach (['all' => [], 'variables' => ['variables'], 'presets' => ['presets'], 'styles' => ['styles'], 'base layout' => ['base-layout-styles'], 'custom' => ['custom-css'], 'variables and presets' => ['variables', 'presets']] as $kind => $types) {
    $css = wp_get_global_stylesheet($types);
    $sheets[$kind] = [strlen($css), md5($css), substr($css, 0, 120)];
}
$say('wp_get_global_stylesheet', $sheets);

// A block's own stylesheet.
wp_enqueue_block_style('core/paragraph', ['handle' => 'zz-probe-paragraph', 'src' => 'https://example.com/zz-paragraph.css', 'ver' => '1']);
$block = ['registered' => wp_style_is('zz-probe-paragraph', 'registered'), 'enqueued before' => wp_style_is('zz-probe-paragraph', 'enqueued')];
do_blocks('<!-- wp:heading --><h2>zz</h2><!-- /wp:heading -->');
$block['after another block'] = wp_style_is('zz-probe-paragraph', 'enqueued');
do_blocks('<!-- wp:paragraph --><p>zz</p><!-- /wp:paragraph -->');
$block['after a paragraph'] = wp_style_is('zz-probe-paragraph', 'enqueued');
$block['src'] = $mask(wp_styles()->registered['zz-probe-paragraph']->src ?? null);
wp_dequeue_style('zz-probe-paragraph');
wp_deregister_style('zz-probe-paragraph');
$say('wp_enqueue_block_style', $block);

// Preloading REST answers.
wp_set_current_user(1);
$preloaded = array_reduce(['/wp/v2/types/post?context=edit', ['/wp/v2/media', 'OPTIONS'], '/wp/v2/zz-none', '/wp/v2/settings?_fields=title'], 'rest_preload_api_request', []);
$shape = [];
foreach ($preloaded as $path => $entry) {
    if ($path === 'OPTIONS') {
        foreach ($entry as $route => $answer) {
            // The route's description, not its schema (which the engine does not serve yet).
            $shape["OPTIONS {$route}"] = [array_keys($answer), $answer['headers'] ?? null, is_array($answer['body'] ?? null) ? array_values(array_intersect(array_keys($answer['body']), ['namespace', 'methods', 'endpoints'])) : null];
        }
        continue;
    }
    $shape[$path] = [array_keys($entry), $entry['headers'] ?? null, is_array($entry['body'] ?? null) ? array_keys($entry['body']) : null];
}
wp_set_current_user(0);
$say('rest_preload_api_request', $mask([array_keys($preloaded), $shape]));

$say('rest_parse_embed_param', [rest_parse_embed_param(''), rest_parse_embed_param('true'), rest_parse_embed_param('1'), rest_parse_embed_param('0'), rest_parse_embed_param('author,wp:term'), rest_parse_embed_param(['a', 'b']), rest_parse_embed_param(' , ')]);

// An untitled post that goes live takes its id for a slug (made free in its type's scope).
$untitledPost = (int) wp_insert_post(['post_title' => '', 'post_content' => 'zz probe untitled post', 'post_status' => 'publish']);
$untitledDraft = (int) wp_insert_post(['post_title' => '', 'post_content' => 'zz probe untitled draft', 'post_status' => 'draft']);
$symbols = (int) wp_insert_post(['post_title' => '!!!', 'post_content' => 'zz probe symbols', 'post_status' => 'publish']);
array_push($made, $untitledPost, $untitledDraft, $symbols);
$say('untitled slugs', [str_replace((string) $untitledPost, '{id}', get_post($untitledPost)->post_name), get_post($untitledDraft)->post_name, str_replace((string) $symbols, '{id}', get_post($symbols)->post_name), str_replace((string) $untitled, '{id}', get_post($untitled)->post_name)]);

foreach (array_reverse($made) as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
