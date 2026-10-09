<?php
/**
 * The rest of post.php (probe post-remaining): page statuses and which
 * statuses and posts are publicly viewable, post type capabilities and
 * labels, custom fields, post thumbnails, the author SQL, page children and
 * hierarchy loops, MIME type matching and its SQL, relative upload paths,
 * the original image address, post tags, and the status transition
 * callbacks (scheduling, publishing what is due, front page and privacy
 * page resets, term counts, old slugs and dates, trashing a post's comments,
 * attachment files). Every option it can change is put back, and its posts,
 * comments, terms, cron events and files are removed at the end. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'comments' => [], 'files' => []];
$options = [];
foreach (['show_on_front', 'page_on_front', 'page_for_posts', 'sticky_posts', 'wp_page_for_privacy_policy'] as $name) {
    $options[$name] = get_option($name, null);
}
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $options, $pings): void {
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_clear_scheduled_hook('publish_future_post', [$id]);
        }
    }
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach ($made['files'] as $file) {
        if (is_string($file) && str_contains($file, 'zz-pr-') && is_file($file)) {
            unlink($file);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$uploads = wp_get_upload_dir();
$mask = static function ($value) use (&$mask, &$ids, $host, $uploads) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = str_replace([$uploads['baseurl'], $uploads['basedir']], ['{uploads}', '{updir}'], $value);
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$kind = static fn ($value) => is_int($value) ? 'int' : $value;
wp_set_current_user(1);

$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x']);
    $made['posts'][] = $id;
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
register_post_type('zz_pr_hidden', ['public' => false]);
register_post_type('zz_pr_shown', ['public' => true, 'publicly_queryable' => true]);
register_post_status('zz_pr_open', ['public' => true, 'label' => 'Open']);
register_post_status('zz_pr_shut', ['public' => false, 'protected' => true, 'label' => 'Shut']);
register_post_status('zz_pr_internal', ['internal' => true]);

$published = $post('zz pr published', ['post_date' => '1988-01-01 10:00:00']);
$draft = $post('zz pr draft', ['post_status' => 'draft']);
$private = $post('zz pr private', ['post_status' => 'private']);
$page = $post('zz pr page', ['post_type' => 'page']);
$hidden = $post('zz pr hidden', ['post_type' => 'zz_pr_hidden']);
$shown = $post('zz pr shown', ['post_type' => 'zz_pr_shown', 'post_status' => 'zz_pr_open']);
$shut = $post('zz pr shut', ['post_type' => 'zz_pr_shown', 'post_status' => 'zz_pr_shut']);
$made['posts'][] = $inherit = (int) wp_insert_attachment(['post_title' => 'zz pr inherit', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], 'zz/zz-pr-inherit.png', $published);
$ids[$inherit] = 'inherit';
$made['posts'][] = $orphan = (int) wp_insert_attachment(['post_title' => 'zz pr orphan', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], false, $draft);
$ids[$orphan] = 'orphan';

$say('page statuses and viewable statuses', [
    get_page_statuses(),
    array_map(static fn ($status) => is_post_status_viewable($status), ['publish', 'private', 'draft', 'future', 'pending', 'trash', 'inherit', 'auto-draft', 'zz_pr_open', 'zz_pr_shut', 'zz_pr_internal', 'zz_missing', '']),
    is_post_status_viewable(get_post_status_object('publish')), is_post_status_viewable(get_post_status_object('zz_pr_shut')),
]);
$GLOBALS['post'] = get_post($published);
$say('publicly viewable posts', $mask([
    array_map(static fn ($id) => [$id, is_post_publicly_viewable($id)], [$published, $draft, $private, $page, $hidden, $shown, $shut, $inherit, $orphan, 999999]),
    is_post_publicly_viewable(), is_post_publicly_viewable(get_post($page)),
]));
unset($GLOBALS['post']);
$say('publicly viewable with no post', is_post_publicly_viewable());

$caps = static fn (array $args) => (array) get_post_type_capabilities((object) $args);
$say('post type capabilities', [
    $caps(['capability_type' => 'post', 'map_meta_cap' => true, 'capabilities' => []]),
    $caps(['capability_type' => 'post', 'map_meta_cap' => false, 'capabilities' => []]),
    $caps(['capability_type' => ['zz_book', 'zz_books'], 'map_meta_cap' => true, 'capabilities' => ['edit_posts' => 'zz_write', 'create_posts' => 'zz_create', 'zz_extra' => 'zz_extra_cap']]),
    $caps(['capability_type' => 'zz_page', 'map_meta_cap' => null, 'capabilities' => ['read' => 'zz_read']]),
    $caps(['capability_type' => 'zz_pr_cap', 'map_meta_cap' => true, 'capabilities' => ['edit_posts' => 'zz_write']]),
    $caps(['capability_type' => ['zz_solo'], 'map_meta_cap' => false, 'capabilities' => []]),
    array_intersect_key((array) ($GLOBALS['post_type_meta_caps'] ?? []), array_flip(['edit_zz_book', 'read_zz_book', 'delete_zz_book', 'edit_zz_page', 'edit_zz_pr_cap', 'read_zz_pr_cap', 'delete_zz_pr_cap', 'edit_zz_solo'])),
]);
$labels = static fn ($type) => (array) get_post_type_labels($type);
$say('post type labels', [
    $labels(get_post_type_object('post')), $labels(get_post_type_object('page')),
    $labels((object) ['name' => 'zz_pr_book', 'labels' => ['name' => 'Books', 'singular_name' => 'Book', 'add_new_item' => 'New book'], 'hierarchical' => false, 'label' => 'Books']),
    $labels((object) ['name' => 'zz_pr_tree', 'labels' => ['menu_name' => 'Trees'], 'hierarchical' => true, 'label' => 'Trees']),
    $labels((object) ['name' => 'zz_pr_bare', 'labels' => [], 'hierarchical' => false]),
    $labels((object) ['name' => 'zz_pr_two', 'labels' => ['zz_custom' => 'C', 'name' => 'Twos', 'singular_name' => 'Two', 'template_name' => 'T'], 'hierarchical' => false]),
    $labels((object) ['name' => 'zz_pr_three', 'labels' => ['name' => 'Threes', 'all_items' => 'Every three', 'name_admin_bar' => 'Bar'], 'label' => 'Ignored', 'hierarchical' => true]),
    $labels((object) ['name' => 'zz_pr_four', 'labels' => ['singular_name' => 'Four'], 'hierarchical' => false]),
]);
$heard = [];
add_filter('post_type_labels_zz_pr_five', $listener = static function ($labels) use (&$heard) {
    $heard[] = [get_class($labels), $labels->template_name ?? null, $labels->name_admin_bar ?? null];
    $labels->zz_added = 'A';
    return $labels;
});
$filtered = get_post_type_labels((object) ['name' => 'zz_pr_five', 'labels' => ['singular_name' => 'Five'], 'hierarchical' => false]);
remove_filter('post_type_labels_zz_pr_five', $listener);
$say('post type labels filter', [$heard, get_class($filtered), $filtered->zz_added ?? null]);
register_post_type('zz_pr_registered', ['label' => 'Ones', 'labels' => ['zz_custom' => 'C', 'singular_name' => 'One']]);
$say('labels of a registered type', array_slice((array) get_post_type_object('zz_pr_registered')->labels, -6));
unregister_post_type('zz_pr_registered');

add_post_meta($published, 'zz_one', 'a');
add_post_meta($published, 'zz_many', 'b');
add_post_meta($published, 'zz_many', 'c');
add_post_meta($published, 'zz_list', ['x' => 1, 'y' => [2, 3]]);
add_post_meta($published, '_zz_hidden', 'h');
add_post_meta($published, 'zz_empty', '');
$custom = static fn ($fields) => is_array($fields) ? array_filter($fields, static fn ($key) => str_contains((string) $key, 'zz_'), ARRAY_FILTER_USE_KEY) : $fields;
$GLOBALS['post'] = get_post($published);
$say('custom fields', $mask([
    $custom(get_post_custom($published)), $custom(get_post_custom()), get_post_custom($draft), get_post_custom(999999),
    get_post_custom_values('zz_many', $published), get_post_custom_values('zz_list', $published), get_post_custom_values('zz_one'), get_post_custom_values('zz_missing', $published), get_post_custom_values('', $published), get_post_custom_values('zz_empty', $published),
]));
unset($GLOBALS['post']);

$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz pr image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], 'zz/zz-pr-image.png', 0);
$ids[$image] = 'image';
$made['posts'][] = $fileless = (int) wp_insert_attachment(['post_title' => 'zz pr fileless', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], false, 0);
$ids[$fileless] = 'fileless';
$made['posts'][] = $document = (int) wp_insert_attachment(['post_title' => 'zz pr document', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'], 'zz/zz-pr-document.pdf', 0);
$ids[$document] = 'document';
$thumb = static fn () => get_post_meta($published, '_thumbnail_id', true);
$say('post thumbnails', $mask([
    $kind(set_post_thumbnail($published, $image)), $thumb(), set_post_thumbnail($published, $image), set_post_thumbnail(get_post($published), (string) $image),
    set_post_thumbnail($published, $fileless), $thumb(), $kind(set_post_thumbnail($published, $image)), set_post_thumbnail($published, $document), $thumb(),
    $kind(set_post_thumbnail($published, $image)), set_post_thumbnail($published, 999999), $thumb(), set_post_thumbnail($published, 0), $thumb(), set_post_thumbnail(999999, $image),
    $kind(set_post_thumbnail($published, $image)), delete_post_thumbnail($published), $thumb(), delete_post_thumbnail($published), delete_post_thumbnail(999999), delete_post_thumbnail(get_post($draft)),
]));

$sql = static fn (...$args) => $mask(str_replace($GLOBALS['wpdb']->posts, '{posts}', get_posts_by_author_sql(...$args)));
$authorSql = static fn () => [
    $sql('post'), $sql('post', false), $sql('post', true, 1), $sql('post', true, 1, true), $sql('post', true, null, true), $sql(['post', 'page'], true, null, true), $sql(['post', 'page'], true, 1),
    $sql('page', true, 2), $sql('zz_missing'), $sql('attachment'), $sql('zz_pr_shown', true, 1), $sql([], true), $sql('post', true, 0),
];
$say('author SQL, signed in', $authorSql());
wp_set_current_user(0);
$say('author SQL, signed out', $authorSql());
wp_set_current_user(1);

$node = static fn (int $id, int $parent) => new WP_Post((object) ['ID' => $id, 'post_parent' => $parent, 'post_type' => 'page']);
$pages = [$node(101, 0), $node(102, 101), $node(103, 102), $node(104, 101), $node(105, 0), $node(106, 105), $node(107, 103), $node(108, 999)];
$say('page children', array_map(static fn ($id) => array_map(static fn ($child) => $child->ID, get_page_children($id, $pages)), [101, 102, 103, 105, 107, 0, 999, 555]));

$a = $post('zz pr a', ['post_type' => 'page']);
$b = $post('zz pr b', ['post_type' => 'page', 'post_parent' => $a]);
$c = $post('zz pr c', ['post_type' => 'page', 'post_parent' => $b]);
$parents = static fn () => array_map(static fn ($id) => (int) get_post($id)->post_parent, [$a, $b, $c]);
$say('hierarchy loops', $mask([
    wp_check_post_hierarchy_for_loops(0, $a), wp_check_post_hierarchy_for_loops($a, 0), wp_check_post_hierarchy_for_loops($a, $a), wp_check_post_hierarchy_for_loops($b, $c),
    wp_check_post_hierarchy_for_loops(999999, $a), wp_check_post_hierarchy_for_loops((string) $b, $c), $parents(),
    wp_check_post_hierarchy_for_loops($c, $a), $parents(), wp_check_post_hierarchy_for_loops($b, $a), $parents(),
]));

$real = ['image/png', 'image/jpeg', 'video/mp4', 'text/plain', 'application/pdf', 'image/svg+xml', 'audio/mpeg'];
$say('MIME type matching', [
    wp_match_mime_types('image', $real), wp_match_mime_types('image/*, video', $real), wp_match_mime_types(['image/png', 'audio'], $real), wp_match_mime_types('', $real),
    wp_match_mime_types('*/png', $real), wp_match_mime_types('image/svg+xml', $real), wp_match_mime_types('zz', $real), wp_match_mime_types('application,text', $real), wp_match_mime_types('image', 'image/png,image/gif'),
    wp_match_mime_types('audio,image,video', $real), wp_match_mime_types('*', $real), wp_match_mime_types('imag', $real), wp_match_mime_types('image/svg*', $real), wp_match_mime_types('IMAGE', $real), wp_match_mime_types('image/', $real),
    wp_match_mime_types(['image', 'image'], $real), wp_match_mime_types('image', ['image/png', ' image/gif ', 'IMAGE/JPEG', 'image/vnd.zz_x']), wp_match_mime_types('application/*', ['application/vnd.ms-excel', 'application/x-zz+json']), wp_match_mime_types('image, ,video', $real),
    wp_match_mime_types('image/png', [' image/png ', 'image/png', 'x image/png']), wp_match_mime_types('image/*', ['image/vnd.zz_x', ' image/gif', 'image/gif x', 'image/GIF']), wp_match_mime_types('*', [' text/plain', 'text/plain']), wp_match_mime_types('image', ['x image/png', 'image/png x', "image/png\n"]),
]);
$say('MIME type SQL', array_map(static fn ($args) => str_replace($GLOBALS['wpdb']->posts, '{posts}', wp_post_mime_type_where(...$args)), [
    ['image'], ['image/png'], [['image', 'video/mp4']], ['image/*', 'p'], ['', ''], ['bad mime!'], ['image/%'], ['image,video'], [' image , audio/* '], ['*/png'], [['']], ['application/vnd.ms-excel', 'zz'],
]));

$say('relative upload paths', $mask(array_map(static fn ($path) => _wp_relative_upload_path($path), [$uploads['basedir'] . '/2026/10/a.png', $uploads['basedir'] . '/a.png', $uploads['basedir'], '/elsewhere/a.png', '2026/a.png', '', $uploads['basedir'] . '//double.png'])));

$made['posts'][] = $scaled = (int) wp_insert_attachment(['post_title' => 'zz pr scaled', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], '2026/10/zz-pr-big-scaled.jpg', 0);
$ids[$scaled] = 'scaled';
wp_update_attachment_metadata($scaled, ['width' => 2560, 'height' => 1440, 'file' => '2026/10/zz-pr-big-scaled.jpg', 'original_image' => 'zz-pr-big.jpg', 'sizes' => []]);
$made['posts'][] = $plain = (int) wp_insert_attachment(['post_title' => 'zz pr plain', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit'], '2026/10/zz-pr-plain.jpg', 0);
$ids[$plain] = 'plain';
wp_update_attachment_metadata($plain, ['width' => 10, 'height' => 10, 'file' => '2026/10/zz-pr-plain.jpg', 'sizes' => []]);
$say('original image addresses', $mask([wp_get_original_image_url($scaled), wp_get_original_image_url($plain), wp_get_original_image_url($image), wp_get_original_image_url($document), wp_get_original_image_url($published), wp_get_original_image_url(999999), wp_get_original_image_url($fileless)]));

$term('zz pr tag b', 'post_tag');
$term('zz pr tag a', 'post_tag');
wp_set_post_tags($published, ['zz pr tag b', 'zz pr tag a']);
$GLOBALS['post'] = get_post($published);
$say('post tags', $mask([
    array_map(static fn ($tag) => [$tag->name, $tag->term_id, $tag->taxonomy, $tag->count], wp_get_post_tags($published)), wp_get_post_tags($published, ['fields' => 'names']), wp_get_post_tags($published, ['fields' => 'ids', 'orderby' => 'term_id']),
    wp_get_post_tags(0, ['fields' => 'names']), wp_get_post_tags($draft), wp_get_post_tags(999999),
]));
unset($GLOBALS['post']);

// Scheduling and publishing what is due.
$future = $post('zz pr future', ['post_status' => 'future', 'post_date' => '2031-05-05 10:00:00']);
$due = $post('zz pr due', ['post_status' => 'future', 'post_date' => '2031-05-06 10:00:00']);
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_date' => '1988-02-02 10:00:00', 'post_date_gmt' => '1988-02-02 10:00:00'], ['ID' => $due]);
clean_post_cache($due);
$scheduled = static fn ($id) => ($when = wp_next_scheduled('publish_future_post', [$id])) ? $when - strtotime(get_post($id)->post_date_gmt . ' GMT') : $when;
wp_clear_scheduled_hook('publish_future_post', [$future]);
$before = $scheduled($future);
_future_post_hook(null, get_post($future));
$heard = [];
add_action('transition_post_status', $listener = static function ($new, $old, $post) use (&$heard) {
    $heard[] = [$new, $old, $post->post_title];
}, 10, 3);
$say('scheduling and publishing', $mask([
    $before, $scheduled($future),
    check_and_publish_future_post($future), get_post_status($future), check_and_publish_future_post(get_post($due)), get_post_status($due), check_and_publish_future_post($published), check_and_publish_future_post(999999), $heard,
]));
remove_action('transition_post_status', $listener, 10);

// The transition default: a guid for what becomes published, the deprecated hook, and the schedule cleared.
$deprecated = [];
add_action('deprecated_hook_run', $deprecatedListener = static function ($hook, $replacement, $version) use (&$deprecated) {
    $deprecated[] = [$hook, $replacement, $version];
}, 10, 3);
add_action('private_to_published', $privateListener = static function (...$args) use (&$deprecated, &$ids) {
    $deprecated[] = ['heard', array_map(static fn ($id) => isset($ids[$id]) ? '{' . $ids[$id] . '}' : $id, $args)];
}, 10, 9);
$transitions = [];
foreach ([['publish', 'draft', 'publish', 'post'], ['publish', 'private', 'draft', 'post'], ['publish', 'future', 'draft', 'page'], ['draft', 'future', 'draft', 'post'], ['private', 'draft', 'draft', 'post'], ['publish', 'publish', 'publish', 'post'], ['future', 'draft', 'draft', 'post'], ['publish', 'new', 'draft', 'post']] as $i => [$new, $old, $stored, $type]) {
    $id = $post("zz pr moving {$i}", ['post_status' => $stored, 'post_type' => $type, 'post_name' => "zz-pr-moving-{$i}"]);
    $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['guid' => ''], ['ID' => $id]);
    clean_post_cache($id);
    wp_schedule_single_event(time() + 9999, 'publish_future_post', [$id]);
    $deprecated = [];
    _transition_post_status($new, $old, get_post($id));
    $cached = get_post($id)->guid;
    clean_post_cache($id);
    $transitions[] = ["{$new} from {$old}", $cached, get_post($id)->guid, (bool) wp_next_scheduled('publish_future_post', [$id]), $deprecated];
}
$kept = $post('zz pr kept guid', ['post_status' => 'draft', 'guid' => 'https://zz.example/kept']);
_transition_post_status('publish', 'draft', get_post($kept));
clean_post_cache($kept);
$transitions[] = get_post($kept)->guid;
remove_action('deprecated_hook_run', $deprecatedListener, 10);
remove_action('private_to_published', $privateListener, 10);
$say('the transition default', $mask($transitions));

// The initial post meta.
$blockKeys = static fn () => array_keys(get_registered_meta_keys('post', 'wp_block'));
$initial = [$blockKeys()];
unregister_meta_key('post', 'wp_pattern_sync_status', 'wp_block');
$initial[] = $blockKeys();
wp_create_initial_post_meta();
$initial[] = $blockKeys();
$initial[] = get_registered_meta_keys('post', 'wp_block')['wp_pattern_sync_status'] ?? null;
$say('the initial post meta', $initial);

// Front page, posts page, sticky and privacy page resets.
update_option('show_on_front', 'page');
update_option('page_on_front', $page);
update_option('page_for_posts', $a);
update_option('wp_page_for_privacy_policy', $b);
stick_post($published);
$state = static fn () => [get_option('show_on_front'), (int) get_option('page_on_front'), (int) get_option('page_for_posts'), (int) get_option('wp_page_for_privacy_policy'), in_array($published, array_map('intval', (array) get_option('sticky_posts')), true)];
$resets = [$state()];
_reset_front_page_settings_for_post($c);
$resets[] = $state();
_reset_front_page_settings_for_post($a);
$resets[] = $state();
_reset_front_page_settings_for_post($page);
$resets[] = $state();
_reset_front_page_settings_for_post($published);
$resets[] = $state();
_reset_privacy_policy_page_for_post($a);
$resets[] = $state();
_reset_privacy_policy_page_for_post($b);
$resets[] = $state();
$say('front page and privacy page resets', $mask($resets));
foreach ($options as $name => $value) {
    $value === null ? delete_option($name) : update_option($name, $value);
}

// Term counts after a transition.
$counted = $term('zz pr counted', 'category');
$countedPost = $post('zz pr counted post', ['post_category' => [$counted]]);
$count = static fn () => (int) get_term($counted, 'category')->count;
$counts = [$count()];
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['count' => 7], ['term_id' => $counted, 'taxonomy' => 'category']);
clean_term_cache($counted, 'category');
$counts[] = $count();
_update_term_count_on_transition_post_status('publish', 'publish', get_post($countedPost));
$counts[] = $count();
$GLOBALS['wpdb']->update($GLOBALS['wpdb']->term_taxonomy, ['count' => 7], ['term_id' => $counted, 'taxonomy' => 'category']);
clean_term_cache($counted, 'category');
_update_term_count_on_transition_post_status('draft', 'publish', get_post($countedPost));
$counts[] = $count();
$say('term counts on a transition', $counts);

// Old slugs and dates.
$moved = $post('zz pr moved', ['post_name' => 'zz-pr-moved', 'post_date' => '1988-03-03 10:00:00']);
$before = get_post($moved);
$after = clone $before;
$after->post_name = 'zz-pr-moved-on';
$after->post_date = '1988-03-04 10:00:00';
$oldMeta = static fn () => [get_post_meta($moved, '_wp_old_slug'), get_post_meta($moved, '_wp_old_date')];
wp_check_for_changed_slugs($moved, $after, $before);
wp_check_for_changed_dates($moved, $after, $before);
$changes = [$oldMeta()];
wp_check_for_changed_slugs($moved, $after, $before);
wp_check_for_changed_dates($moved, $after, $before);
$changes[] = $oldMeta();
$back = clone $after;
$back->post_name = 'zz-pr-moved';
$back->post_date = '1988-03-03 10:00:00';
wp_check_for_changed_slugs($moved, $back, $after);
wp_check_for_changed_dates($moved, $back, $after);
$changes[] = $oldMeta();
$draftBefore = get_post($draft);
$draftAfter = clone $draftBefore;
$draftAfter->post_name = 'zz-pr-draft-moved';
$draftAfter->post_date = '1988-05-05 10:00:00';
wp_check_for_changed_slugs($draft, $draftAfter, $draftBefore);
wp_check_for_changed_dates($draft, $draftAfter, $draftBefore);
$changes[] = [get_post_meta($draft, '_wp_old_slug'), get_post_meta($draft, '_wp_old_date')];
$pageBefore = get_post($page);
$pageAfter = clone $pageBefore;
$pageAfter->post_name = 'zz-pr-page-moved';
wp_check_for_changed_slugs($page, $pageAfter, $pageBefore);
$changes[] = get_post_meta($page, '_wp_old_slug');
$say('old slugs and dates', $changes);

// The after-insert action.
$heard = [];
add_action('wp_after_insert_post', $listener = static function ($id, $post, $update, $before) use (&$heard, &$ids) {
    $heard[] = [isset($ids[$id]) ? $id : $post->post_type . ' of ' . ($ids[$post->post_parent] ?? $post->post_parent), $post->post_title, $update, $before === null ? null : $before->post_title];
}, 10, 4);
wp_after_insert_post($published, true, get_post($draft));
wp_after_insert_post(get_post($page), false, null);
wp_after_insert_post(999999, true, null);
remove_action('wp_after_insert_post', $listener, 10);
$say('the after-insert action', $mask($heard));

// A post's comments trashed and brought back.
$discussed = $post('zz pr discussed');
foreach ([['1', 'zz pr approved'], ['0', 'zz pr held'], ['spam', 'zz pr spam'], ['trash', 'zz pr trashed']] as [$approved, $content]) {
    $made['comments'][] = $id = (int) wp_insert_comment(['comment_post_ID' => $discussed, 'comment_content' => $content, 'comment_approved' => $approved]);
    $ids[$id] = str_replace(' ', '-', $content);
}
$all = static fn () => array_map(static fn ($comment) => [$comment->comment_content, $comment->comment_approved], $GLOBALS['wpdb']->get_results($GLOBALS['wpdb']->prepare("SELECT comment_content, comment_approved FROM {$GLOBALS['wpdb']->comments} WHERE comment_post_ID = %d ORDER BY comment_ID", $discussed)));
$trashMeta = static function () use ($discussed, &$ids) {
    $meta = get_post_meta($discussed, '_wp_trash_meta_comments_status', true);
    return is_array($meta) ? array_map(static fn ($id, $status) => ['{' . ($ids[$id] ?? $id) . '}', $status], array_keys($meta), $meta) : $meta;
};
$say('trashing a post\'s comments', $mask([
    $all(), wp_trash_post_comments($discussed), $all(), $trashMeta(), wp_trash_post_comments($discussed), wp_untrash_post_comments($discussed), $all(), $trashMeta(), wp_untrash_post_comments($discussed),
    wp_trash_post_comments(999999), wp_untrash_post_comments(999999), wp_trash_post_comments($draft), wp_untrash_post_comments(get_post($draft)),
]));

// Attachment files.
$dir = $uploads['basedir'] . '/zz-pr-files';
wp_mkdir_p($dir);
$files = ['main' => 'zz-pr-photo.jpg', 'small' => 'zz-pr-photo-150x150.jpg', 'backup' => 'zz-pr-photo-e1.jpg', 'original' => 'zz-pr-photo-original.jpg', 'other' => 'zz-pr-other.jpg'];
foreach ($files as $name) {
    file_put_contents($made['files'][] = "{$dir}/{$name}", 'x');
}
$exists = static fn () => array_map(static fn ($name) => is_file("{$dir}/{$name}"), $files);
$meta = ['file' => 'zz-pr-files/zz-pr-photo.jpg', 'sizes' => ['thumbnail' => ['file' => 'zz-pr-photo-150x150.jpg']], 'original_image' => 'zz-pr-photo-original.jpg'];
$backups = ['full-orig' => ['file' => 'zz-pr-photo-e1.jpg']];
$fileResults = [$exists()];
$fileResults[] = wp_delete_attachment_files(0, $meta, $backups, "{$dir}/zz-pr-photo.jpg");
$fileResults[] = $exists();
$fileResults[] = wp_delete_attachment_files(0, ['file' => 'zz-pr-files/zz-pr-gone.jpg'], [], "{$dir}/zz-pr-gone.jpg");
$fileResults[] = wp_delete_attachment_files(0, ['file' => 'zz-pr-files/zz-pr-other.jpg'], [], "{$dir}/zz-pr-other.jpg");
$fileResults[] = $exists();
file_put_contents($made['files'][] = "{$dir}/zz-pr-kept.jpg", 'x');
$fileResults[] = wp_delete_attachment_files(0, ['file' => 'zz-pr-files/zz-pr-kept.jpg', 'sizes' => ['thumbnail' => ['file' => 'zz-pr-kept-150x150.jpg']], 'original_image' => 'zz-pr-kept-original.jpg'], ['full-orig' => ['file' => 'zz-pr-kept-e1.jpg']], "{$dir}/zz-pr-kept.jpg");
$fileResults[] = is_file("{$dir}/zz-pr-kept.jpg");
$fileResults[] = wp_delete_attachment_files(0, ['file' => 'zz-pr-files/zz-pr-kept.jpg'], [], "{$uploads['basedir']}/../zz-pr-outside.jpg");
$say('attachment files', $fileResults);
@rmdir($dir);

unregister_post_type('zz_pr_hidden');
unregister_post_type('zz_pr_shown');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
