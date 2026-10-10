<?php
/**
 * Core block helpers (probe core-block-helpers): the calendar's
 * published-posts flag (read, recomputed, kept current on delete and on
 * status changes), the sidebar a widget group renders in, footnotes added to
 * the revisioned fields, whether a post template uses featured images, and
 * the RSS block with its fetch answered locally. The render callbacks the
 * engine does not render yet (site logo, footnotes, tabs, icon, playlist,
 * query pagination, legacy widget, term template) wait for their batches.
 * The probe's posts and options are removed or put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
$saved = [];
foreach (['wp_calendar_block_has_published_posts'] as $name) {
    $saved[$name] = get_option($name, null);
}
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $saved, $pings): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($saved as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$try = static function (Closure $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    if (!is_int($id) || $id < 2) {
        fwrite(STDERR, "core-block-helpers: {$title} could not be made\n");
        exit(1);
    }
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};

// The calendar's published-posts flag.
$calendar = [];
delete_option('wp_calendar_block_has_published_posts');
$calendar[] = [block_core_calendar_has_published_posts(), get_option('wp_calendar_block_has_published_posts', 'unset')];
update_option('wp_calendar_block_has_published_posts', '0');
$calendar[] = [block_core_calendar_has_published_posts(), block_core_calendar_update_has_published_posts(), get_option('wp_calendar_block_has_published_posts')];
$draft = $post('zz cbh draft', ['post_status' => 'draft']);
update_option('wp_calendar_block_has_published_posts', '0');
block_core_calendar_update_has_published_post_on_transition_post_status('draft', 'draft', get_post($draft));
$calendar[] = get_option('wp_calendar_block_has_published_posts');
block_core_calendar_update_has_published_post_on_transition_post_status('publish', 'draft', get_post($draft));
$calendar[] = get_option('wp_calendar_block_has_published_posts');
update_option('wp_calendar_block_has_published_posts', '0');
block_core_calendar_update_has_published_post_on_transition_post_status('publish', 'draft', (object) ['post_type' => 'page', 'ID' => 0]);
$calendar[] = get_option('wp_calendar_block_has_published_posts');
$published = $post('zz cbh published');
update_option('wp_calendar_block_has_published_posts', '0');
block_core_calendar_update_has_published_post_on_delete($draft);
$calendar[] = get_option('wp_calendar_block_has_published_posts');
block_core_calendar_update_has_published_post_on_delete($published);
$calendar[] = get_option('wp_calendar_block_has_published_posts');
$say('the calendar flag', $calendar);

note_sidebar_being_rendered('zz-sidebar');
$sidebar = [$GLOBALS['_sidebar_being_rendered'] ?? null];
note_sidebar_being_rendered('zz-other');
$sidebar[] = $GLOBALS['_sidebar_being_rendered'] ?? null;
discard_sidebar_being_rendered();
$sidebar[] = array_key_exists('_sidebar_being_rendered', $GLOBALS) ? $GLOBALS['_sidebar_being_rendered'] : 'unset';
$say('the sidebar a widget group renders in', $sidebar);

$say('footnotes in the revisioned fields', [wp_add_footnotes_to_revision(['post_title' => 'Title', 'post_content' => 'Content']), wp_add_footnotes_to_revision([]), wp_add_footnotes_to_revision(['footnotes' => 'Given'])]);

// The post template hands its inner blocks over as WP_Block objects.
$inner = static fn (array $blocks) => (new WP_Block(['blockName' => 'core/group', 'attrs' => [], 'innerBlocks' => $blocks, 'innerHTML' => '', 'innerContent' => array_fill(0, count($blocks), null)], []))->inner_blocks;
$parsed = static fn (string $name, array $attrs = [], array $children = []) => ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $children, 'innerHTML' => '', 'innerContent' => array_fill(0, count($children), null)];
$say('a post template using featured images', [
    block_core_post_template_uses_featured_image($inner([$parsed('core/post-title'), $parsed('core/post-featured-image')])), block_core_post_template_uses_featured_image($inner([$parsed('core/post-title')])),
    block_core_post_template_uses_featured_image($inner([$parsed('core/cover', ['useFeaturedImage' => true])])), block_core_post_template_uses_featured_image($inner([$parsed('core/cover')])),
    block_core_post_template_uses_featured_image($inner([$parsed('core/group', [], [$parsed('core/post-featured-image')])])),
    block_core_post_template_uses_featured_image($inner([])),
]);

add_filter('pre_http_request', $blocked = static fn () => new WP_Error('zz_blocked', 'No request leaves the probe.'), 1);
$say('rss', [$try(static fn () => render_block_core_rss(['feedURL' => 'https://zz.example/feed/'])), $try(static fn () => render_block_core_rss(['feedURL' => '']))]);
remove_filter('pre_http_request', $blocked, 1);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
