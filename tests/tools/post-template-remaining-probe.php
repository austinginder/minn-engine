<?php
/**
 * The rest of post-template.php (probe post-template-remaining): body classes
 * for the views a theme renders (a post, a page with a template, an
 * attachment, archives, search, a 404, the front page), post classes (type,
 * status, format, sticky, terms, thumbnail, password), the title tags, the
 * content and excerpt as printed (teasers, more links, pages, passwords), the
 * password form, page links, and attachments prepended to their content. The
 * probe's posts, terms and sticky flag are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => []];
$sticky = get_option('sticky_posts');
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $sticky, $pings): void {
    update_option('sticky_posts', $sticky);
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
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [1 => 'admin'];
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
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made['posts'][] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => 'x', 'post_author' => 1]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$term = static function (string $name, string $taxonomy) use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy)['term_id'], $taxonomy];
    $ids[$id] = str_replace(' ', '-', $name);
    return $id;
};
$query = static function (array $args, bool $notFound = false) {
    global $wp_query, $wp_the_query, $post;
    $wp_the_query = $wp_query = new WP_Query();
    $wp_query->query($args);
    if ($notFound || !$wp_query->have_posts()) {
        $wp_query->set_404();
    }
    $post = $wp_query->post ?? null;
    if ($post) {
        setup_postdata($post);
    }
};

$category = $term('zz pt cat', 'category');
$tag = $term('zz pt tag', 'post_tag');
$single = $post('zz pt single "q" & co', ['post_content' => "Teaser text.<!--more-->After the more.\n\n<!--nextpage-->\n\nSecond page.", 'post_excerpt' => '', 'post_category' => [$category], 'tags_input' => ['zz pt tag'], 'post_date' => '1984-03-03 10:00:00']);
set_post_format($single, 'aside');
stick_post($single);
$excerpted = $post('zz pt excerpted', ['post_content' => 'Body words.', 'post_excerpt' => 'Hand <b>written</b> excerpt.', 'post_date' => '1984-03-04 10:00:00']);
$locked = $post('zz pt locked', ['post_password' => 'zz-secret', 'post_content' => 'Hidden body.', 'post_date' => '1984-03-05 10:00:00']);
$page = $post('zz pt page', ['post_type' => 'page', 'page_template' => (string) array_key_first(wp_get_theme()->get_page_templates(null, 'page')), 'post_content' => 'Page body.']);
$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz pt image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_content' => 'Image description.', 'post_excerpt' => 'Image caption.'], '2026/10/zz-pt-image.jpg', $single);
$ids[$image] = 'image';
wp_update_attachment_metadata($image, ['width' => 1200, 'height' => 800, 'file' => '2026/10/zz-pt-image.jpg', 'sizes' => ['medium' => ['file' => 'zz-pt-image-300x200.jpg', 'width' => 300, 'height' => 200, 'mime-type' => 'image/jpeg']]]);
$made['posts'][] = $audio = (int) wp_insert_attachment(['post_title' => 'zz pt audio', 'post_mime_type' => 'audio/mpeg', 'post_status' => 'inherit'], '2026/10/zz-pt-audio.mp3', $single);
$ids[$audio] = 'audio';
$made['posts'][] = $video = (int) wp_insert_attachment(['post_title' => 'zz pt video', 'post_mime_type' => 'video/mp4', 'post_status' => 'inherit'], '2026/10/zz-pt-video.mp4', $single);
$ids[$video] = 'video';
wp_update_attachment_metadata($video, ['width' => 640, 'height' => 360, 'mime_type' => 'video/mp4']);
set_post_thumbnail($excerpted, $image);

$bodies = [];
foreach ([
    'a post' => [['p' => $single]], 'a page with a template' => [['page_id' => $page]], 'an image' => [['p' => $image, 'post_type' => 'attachment', 'post_status' => 'inherit']],
    'a category' => [['cat' => $category]], 'a tag' => [['tag_id' => $tag]], 'an author' => [['author' => 1]], 'a date' => [['year' => 1984, 'monthnum' => 3]],
    'search' => [['s' => 'zz pt single']], 'nothing found' => [['p' => 999999], true], 'a paged archive' => [['cat' => $category, 'paged' => 2, 'posts_per_page' => 1]], 'a locked post' => [['p' => $locked]],
] as $label => $spec) {
    [$args, $notFound] = $spec + [1 => false];
    $query($args, $notFound);
    $classes = get_body_class(['zz-given', 'zz given two']);
    $bodies[$label] = [$classes, $echoed(static fn () => body_class('zz-echo'))];
}
$query(['p' => $single]);
add_filter('body_class', $bodyFilter = static fn ($classes, $given) => array_merge($classes, ['zz-filtered-' . count($given)]), 10, 2);
$bodies['filtered'] = get_body_class('zz-one zz-two');
remove_filter('body_class', $bodyFilter, 10);
$say('body classes', $mask($bodies));

$query(['p' => $single]);
$postClasses = [];
foreach ([[$single, ''], [$single, 'zz-given extra'], [$excerpted, ['zz-array']], [$locked, ''], [$page, ''], [$image, ''], [999999, '']] as [$id, $given]) {
    $postClasses[] = [$id, get_post_class($given, $id), $echoed(static fn () => post_class($given, $id))];
}
$say('post classes', $mask($postClasses));

$titles = [];
foreach ([$single, $locked, $excerpted] as $id) {
    $query(['p' => $id]);
    $titles[] = [
        $echoed(static fn () => the_title()), $echoed(static fn () => the_title('<h2>', '</h2>')), the_title('[', ']', false), the_title('', '', false),
        $echoed(static fn () => the_title_attribute()), the_title_attribute(['echo' => false]), the_title_attribute(['before' => '<b>', 'after' => '</b>', 'echo' => false]), the_title_attribute(['post' => $excerpted, 'echo' => false]), the_title_attribute('echo=0&before=x'),
    ];
}
$say('titles', $mask($titles));

$contents = [];
foreach ([[$single, 'p'], [$excerpted, 'p'], [$locked, 'p'], [$page, 'page_id']] as [$id, $var]) {
    $query([$var => $id]);
    $contents[] = [$echoed(static fn () => the_content()), $echoed(static fn () => the_content('Read on &rarr;')), $echoed(static fn () => the_content(null, true)), $echoed(static fn () => the_excerpt()), has_excerpt(), has_excerpt($excerpted), has_excerpt($single)];
}
$query(['p' => $single, 'page' => 2]);
$contents[] = ['second page', $echoed(static fn () => the_content())];
$GLOBALS['wp_query']->is_single = false;
$GLOBALS['more'] = 0;
$contents[] = ['in a listing', $echoed(static fn () => the_content()), $echoed(static fn () => the_content('More', true))];
$say('content and excerpt', $mask($contents));

$query(['p' => $single]);
$pages = [];
for ($i = 1; $i <= 3; $i++) {
    $pages[] = _wp_link_page($i);
}
$query(['p' => $single, 'preview' => 'true']);
$pages[] = _wp_link_page(2);
$say('page links', $mask($pages));

$say('the password form', $mask([get_the_password_form($locked), get_the_password_form(get_post($single)), (static function () use ($query, $locked) {
    $query(['p' => $locked]);
    return get_the_password_form();
})()]));

$prepended = [];
foreach ([$image, $audio, $video] as $id) {
    $query(['p' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit']);
    $prepended[] = [prepend_attachment('<p>Given content.</p>'), prepend_attachment('')];
}
$query(['p' => $single]);
$prepended[] = prepend_attachment('not an attachment');
$say('attachments prepended', $mask($prepended));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
