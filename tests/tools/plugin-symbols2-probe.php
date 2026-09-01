<?php
/**
 * Second behaviour probe down the catalogue queue: the gallery shortcode,
 * the page walker, the object-term cache, the attachment neighbours, and the
 * small helpers plugins reach for. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (defined('ABSPATH')) {
    foreach (['file', 'plugin', 'theme', 'post', 'image', 'media'] as $inc) {
        if (is_file(ABSPATH . 'wp-admin/includes/' . $inc . '.php')) {
            require_once ABSPATH . 'wp-admin/includes/' . $inc . '.php';
        }
    }
}
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$rel = static fn ($v) => is_string($v)
    ? str_replace(['https://' . $bare, 'http://' . $bare, rtrim(ABSPATH, '/')], ['{home}', '{home}', '{abspath}'], $v)
    : $v;
$deep = static function ($v) use (&$deep, $rel) {
    return is_array($v) ? array_map($deep, $v) : $rel($v);
};
wp_set_current_user(1);

// The battery's attachment is the one image every stack shares.
$attachments = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC']);
$image = $attachments[0] ?? null;
$imageId = $image ? (int) $image->ID : 0;
$say('probe image', $imageId > 0);

// --- The gallery shortcode. Ids are given explicitly so the row never depends
// on which attachments a stack happens to hold.
$GLOBALS['wp_query'] = new WP_Query(['p' => 1]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
$say('gallery_shortcode', $deep(gallery_shortcode(['ids' => (string) $imageId, 'columns' => '2', 'size' => 'thumbnail', 'link' => 'none'])));
$say('gallery_shortcode empty', $deep(gallery_shortcode(['ids' => '999999'])));
$say('gallery_shortcode captions', $deep(gallery_shortcode(['ids' => (string) $imageId, 'itemtag' => 'div', 'icontag' => 'span', 'captiontag' => 'p', 'columns' => '1', 'size' => 'medium'])));
$say('get_post_gallery', $deep(get_post_gallery(1, false)));
$say('get_post_gallery html', $deep(get_post_gallery(1, true)));

// --- The page walker. wp_list_pages already walks; the walker class is what
// a theme subclasses, so the probe drives it directly.
$walker = new Walker_Page();
$output = '';
$walker->start_lvl($output, 0, ['item_spacing' => 'preserve']);
$say('Walker_Page start_lvl', $deep($output));
$output = '';
$walker->end_lvl($output, 0, ['item_spacing' => 'preserve']);
$say('Walker_Page end_lvl', $deep($output));
$output = '';
$walker->start_el($output, get_post(2), 0, ['item_spacing' => 'preserve', 'link_before' => '', 'link_after' => ''], 0);
$say('Walker_Page start_el', $deep($output));
$output = '';
$walker->end_el($output, get_post(2), 0, ['item_spacing' => 'preserve']);
$say('Walker_Page end_el', $deep($output));
$say('Walker_Page fields', [$walker->tree_type, $walker->db_fields]);
$say('walk_page_tree', $deep(walk_page_tree(get_pages(['parent' => 0, 'number' => 2]), 1, 0, ['link_before' => '', 'link_after' => '', 'item_spacing' => 'preserve'])));

// --- The object term cache. Reading it before priming and after says what the
// two functions are for.
clean_object_term_cache([1], 'post');
$say('get_object_term_cache cold', get_object_term_cache(1, 'category'));
update_object_term_cache([1], 'post');
$warm = get_object_term_cache(1, 'category');
$say('get_object_term_cache warm', [
    is_array($warm),
    is_array($warm) ? count($warm) > 0 : false,
    is_array($warm) && isset($warm[0]) ? $warm[0]->taxonomy : null,
]);
$say('get_object_term_cache unknown taxonomy', get_object_term_cache(1, 'minn_probe_nope'));

// --- Post type moves and the small helpers.
$moved = wp_insert_post(['post_title' => 'Minn Probe Type Move', 'post_status' => 'draft', 'post_type' => 'post']);
$say('set_post_type', [set_post_type($moved, 'page'), get_post($moved)->post_type]);
wp_delete_post($moved, true);
$say('format_for_editor', $deep([
    format_for_editor('a & b <strong>c</strong>'),
    format_for_editor('plain', 'tinymce'),
    format_for_editor(''),
]));
$say('wp_privacy_anonymize_data', [
    wp_privacy_anonymize_data('email', 'someone@example.test'),
    wp_privacy_anonymize_data('ip', '192.168.10.44'),
    wp_privacy_anonymize_data('text', 'anything'),
    wp_privacy_anonymize_data('longtext', 'anything'),
    wp_privacy_anonymize_data('url', 'https://example.test/x'),
    wp_privacy_anonymize_data('unknown-kind', 'value'),
]);
$say('wp_opcache_invalidate', [
    wp_opcache_invalidate(__FILE__),
    wp_opcache_invalidate('/definitely/not/here.php'),
]);
$say('delete_expired_transients', delete_expired_transients(true));
$say('get_bookmark', [get_bookmark(999999), get_bookmark(999999, ARRAY_A)]);
$say('get_the_taxonomies', $deep(get_the_taxonomies(1)));
$say('the_modified_date', $deep([get_the_modified_date('Y-m-d', 1), get_the_modified_time('H:i', 1)]));

// --- Attachment neighbours inside a parent.
if ($imageId > 0) {
    $GLOBALS['post'] = get_post($imageId);
    $say('get_adjacent_image_link', $deep([
        get_adjacent_image_link(true, 'thumbnail', 'Prev'),
        get_adjacent_image_link(false, 'thumbnail', 'Next'),
    ]));
}

// --- Which image wins fetchpriority. The flag goes to one image per page,
// and the sizes below say whether a small one can take it.
$priority = [];
foreach (['thumbnail', 'medium', 'large', 'full', [64, 64], [200, 200], [1, 1]] as $case) {
    Minn_Probe_Reset_Priority: // label only; each stack resets through the filter below
    $key = is_array($case) ? implode('x', $case) : $case;
    remove_all_filters('wp_get_attachment_image_attributes');
    $markup = wp_get_attachment_image($imageId, $case);
    $priority[$key] = [
        str_contains((string) $markup, 'fetchpriority="high"'),
        str_contains((string) $markup, 'loading="lazy"'),
    ];
}
$say('fetchpriority by size', $priority);

// --- Small classes.
$proxy = new WP_HTTP_Proxy();
$say('WP_HTTP_Proxy', [
    $proxy->is_enabled(),
    $proxy->use_authentication(),
    $proxy->host(),
    $proxy->port(),
    $proxy->username(),
    $proxy->password(),
    $proxy->authentication(),
]);
$say('WP_HTTP_Proxy send_through_proxy', [
    $proxy->send_through_proxy('https://example.test/x'),
    $proxy->send_through_proxy('http://127.0.0.1/x'),
    $proxy->send_through_proxy('http://localhost/x'),
]);
$exception = new WP_Exception('probe message', 7);
$say('WP_Exception', [$exception instanceof Exception, $exception->getMessage(), $exception->getCode()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
