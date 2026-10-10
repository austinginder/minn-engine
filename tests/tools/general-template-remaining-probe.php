<?php
/**
 * The rest of general-template.php (probe general-template-remaining): the
 * checked/selected helper and readonly, the calendar's week arithmetic, the
 * generator in every form, titles for the views a theme renders (wp_title,
 * the document title and its tag, the single post title, archive titles and
 * descriptions, with their filters; the title tag for a theme without
 * title-tag support), modified dates and times, the search
 * query, the rich editor checks, the lost password and registration links,
 * the referrer meta, resource hints, the site icon (none, then one), the
 * extra feed links, header/footer/sidebar templates loaded from files the
 * probe writes into the theme, and the head, body and footer hooks. The
 * probe's posts, terms, files and site icon option are removed or put back
 * at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'files' => []];
$icon = get_option('site_icon', null);
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $icon, $pings): void {
    $icon === null ? delete_option('site_icon') : update_option('site_icon', $icon);
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_reverse($made['terms']) as [$id, $taxonomy]) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, $taxonomy);
        }
    }
    foreach ($made['files'] as $file) {
        if (str_contains($file, 'zzgt') && is_file($file)) {
            unlink($file);
        }
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
    $value = (string) preg_replace('#(https?:)?//' . preg_quote($host, '#') . '#', '{site}', $value);
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
$term = static function (string $name, string $taxonomy, string $description = '') use (&$made, &$ids): int {
    $made['terms'][] = [$id = (int) wp_insert_term($name, $taxonomy, ['description' => $description])['term_id'], $taxonomy];
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

$say('checked and readonly helpers', [
    __checked_selected_helper('a', 'a', false, 'checked'), __checked_selected_helper('1', 1, false, 'selected'), __checked_selected_helper(true, '1', false, 'disabled'), __checked_selected_helper('a', 'b', false, 'checked'),
    __checked_selected_helper(0, '', false, 'checked'), $echoed(static fn () => __checked_selected_helper('x', 'x', true, 'zz-type')),
    wp_readonly(1, 1, false), wp_readonly('x', 'y', false), $echoed(static fn () => wp_readonly(true)), $echoed(static fn () => wp_readonly('1')), wp_readonly(false, false, false),
]);
$say('calendar arithmetic', [array_map(static fn ($n) => calendar_week_mod($n), [0, 1, 6, 7, 8, 13, 14, 15, -1, -7, 3.5]), delete_get_calendar_cache()]);
$say('the generator', [
    array_map(static fn ($type) => preg_replace('/created="[^"]+"/', 'created="{now}"', (string) get_the_generator($type)), ['', 'html', 'xhtml', 'atom', 'rss2', 'rdf', 'comment', 'export']),
    $echoed(static fn () => the_generator('html')), $echoed(static fn () => the_generator('comment')), $echoed(static fn () => wp_generator()),
]);

register_post_type('zz_gt_book', ['public' => true, 'has_archive' => true, 'label' => 'ZZ Books', 'labels' => ['archives' => 'ZZ Book Archives']]);
register_taxonomy('zz_gt_genre', 'zz_gt_book', ['public' => true, 'label' => 'ZZ Genres', 'labels' => ['singular_name' => 'ZZ Genre']]);
$category = $term('zz gt cat', 'category', 'Cat <b>description</b> & more');
$tag = $term('zz gt tag', 'post_tag');
$genre = $term('zz gt genre', 'zz_gt_genre', 'Genre words');
$single = $post('zz gt single "q" & co', ['post_category' => [$category], 'tags_input' => ['zz gt tag'], 'post_date' => '1986-05-06 07:08:09']);
$post('zz gt second', ['post_category' => [$category], 'post_date' => '1986-04-01 07:08:09']);
$page = $post('zz gt page', ['post_type' => 'page']);
$book = $post('zz gt book', ['post_type' => 'zz_gt_book', 'tax_input' => ['zz_gt_genre' => [$genre]]]);
wp_set_object_terms($book, [$genre], 'zz_gt_genre');
global $wpdb;
$wpdb->update($wpdb->posts, ['post_modified' => '1987-06-07 08:09:10', 'post_modified_gmt' => '1987-06-07 12:09:10'], ['ID' => $single]);
clean_post_cache($single);

$views = [
    'a post' => [['p' => $single]], 'a page' => [['page_id' => $page]], 'a book' => [['p' => $book, 'post_type' => 'zz_gt_book']],
    'a category' => [['cat' => $category]], 'a tag' => [['tag_id' => $tag]], 'a genre' => [['zz_gt_genre' => 'zz-gt-genre']], 'the books' => [['post_type' => 'zz_gt_book']],
    'an author' => [['author' => 1]], 'a year' => [['year' => 1986]], 'a month' => [['year' => 1986, 'monthnum' => 5]], 'a day' => [['year' => 1986, 'monthnum' => 5, 'day' => 6]],
    'search' => [['s' => 'zz gt single']], 'nothing found' => [['p' => 999999], true], 'the home page' => [[]], 'a paged category' => [['cat' => $category, 'paged' => 2, 'posts_per_page' => 1]],
    'm as a month' => [['m' => '198605']], 'm as a year' => [['m' => '1986']],
];
$titles = [];
foreach ($views as $label => $spec) {
    [$args, $notFound] = $spec + [1 => false];
    $query($args, $notFound);
    $titles[$label] = [
        wp_title('&raquo;', false), wp_title('|', false, 'right'), $echoed(static fn () => wp_title()), wp_get_document_title(),
        single_post_title('Prefix: ', false), get_the_archive_title(), get_the_archive_description(), $echoed(static fn () => the_archive_title('<h1>', '</h1>')), $echoed(static fn () => the_archive_description('<div>', '</div>')),
    ];
}
$say('titles', $mask($titles));

$heard = [];
$query(['cat' => $category]);
add_filter('get_the_archive_title_prefix', $prefix = static fn ($prefix) => '', 10, 1);
add_filter('get_the_archive_title', $archive = static function ($title, $original, $prefix) use (&$heard) {
    $heard[] = ['get_the_archive_title', $title, $original, $prefix];
    return $title;
}, 10, 3);
add_filter('document_title_parts', $parts = static function ($parts) use (&$heard) {
    $heard[] = ['document_title_parts', $parts];
    return ['title' => 'Zz Title'] + $parts;
});
add_filter('document_title_separator', $separator = static fn () => '~');
add_filter('wp_title_parts', $wpParts = static function ($parts) use (&$heard) {
    $heard[] = ['wp_title_parts', $parts];
    return $parts;
});
add_filter('wp_title', $wpTitle = static function ($title, $sep, $location) use (&$heard) {
    $heard[] = ['wp_title', $title, $sep, $location];
    return $title;
}, 10, 3);
$filtered = [get_the_archive_title(), wp_get_document_title(), wp_title('-', false, 'right')];
$query(['year' => 1986, 'monthnum' => 5]);
$filtered[] = [wp_title('-', false), wp_title('-', false, 'right')];
$query([]);
$filtered[] = [wp_title('-', false), wp_title('-', false, 'right')];
remove_filter('get_the_archive_title_prefix', $prefix, 10);
remove_filter('get_the_archive_title', $archive, 10);
remove_filter('document_title_parts', $parts, 10);
remove_filter('document_title_separator', $separator, 10);
remove_filter('wp_title_parts', $wpParts, 10);
remove_filter('wp_title', $wpTitle, 10);
add_filter('pre_get_document_title', $pre = static fn () => 'Zz Short Circuit');
$filtered[] = wp_get_document_title();
remove_filter('pre_get_document_title', $pre, 10);
$say('title filters', $mask([$filtered, $heard]));

$query(['p' => $single]);
$say('dates and times', $mask([
    $echoed(static fn () => the_time()), $echoed(static fn () => the_time('Y-m-d H:i')), $echoed(static fn () => the_modified_date()), the_modified_date('Y', '<b>', '</b>', false), $echoed(static fn () => the_modified_date('', '[', ']')),
    $echoed(static fn () => the_modified_time()), $echoed(static fn () => the_modified_time('H:i')), get_post_modified_time(), get_post_modified_time('Y-m-d H:i:s', true, $single), get_post_modified_time('G', false, get_post($single), true),
    get_post_modified_time('U', false, 999999), get_post_modified_time('Y', false, $page) === get_post_field('post_modified', $page) ? 'same' : 'year',
]));

$query(['s' => 'zz "quoted" <b> & co']);
$say('the search query', [$echoed(static fn () => the_search_query()), (static function () use ($query, $echoed) {
    $query(['p' => 999999], true);
    return $echoed(static fn () => the_search_query());
})()]);

wp_set_current_user(1);
$say('the rich editor', [user_can_richedit(), wp_default_editor()]);

$say('links', $mask([wp_lostpassword_url(), wp_lostpassword_url('https://zz.example/after?a=1&b=2'), wp_lostpassword_url(home_url('/zz-back/')), wp_registration_url()]));
$say('referrer and hints', $mask([$echoed(static fn () => wp_strict_cross_origin_referrer()), $echoed(static fn () => wp_resource_hints())]));

$upload = wp_get_upload_dir();
$made['posts'][] = $image = (int) wp_insert_attachment(['post_title' => 'zz gt icon', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], $upload['basedir'] . '/2026/10/zzgt-icon.png');
$ids[$image] = 'icon';
$sizes = [];
foreach ([270, 192, 180, 32] as $size) {
    $sizes["site_icon-{$size}"] = ['file' => "zzgt-icon-{$size}x{$size}.png", 'width' => $size, 'height' => $size, 'mime-type' => 'image/png'];
}
wp_update_attachment_metadata($image, ['width' => 512, 'height' => 512, 'file' => '2026/10/zzgt-icon.png', 'sizes' => $sizes]);
update_option('site_icon', 0);
$icons = [$echoed(static fn () => wp_site_icon()), $echoed(static fn () => site_icon_url()), $echoed(static fn () => site_icon_url(512, 'https://zz.example/fallback.png?a=1&b=2'))];
update_option('site_icon', $image);
$icons[] = $echoed(static fn () => wp_site_icon());
$icons[] = $echoed(static fn () => site_icon_url());
$icons[] = $echoed(static fn () => site_icon_url(32));
add_filter('site_icon_meta_tags', $meta = static fn ($tags) => array_merge(array_slice($tags, 0, 1), ['<meta name="zz-icon" content="1" />']));
$icons[] = $echoed(static fn () => wp_site_icon());
remove_filter('site_icon_meta_tags', $meta, 10);
update_option('site_icon', 0);
$say('the site icon', $mask($icons));

$feeds = [];
foreach (['a post' => ['p' => $single], 'a page' => ['page_id' => $page], 'a category' => ['cat' => $category], 'a tag' => ['tag_id' => $tag], 'a genre' => ['zz_gt_genre' => 'zz-gt-genre'], 'the books' => ['post_type' => 'zz_gt_book'], 'an author' => ['author' => 1], 'search' => ['s' => 'zz gt single'], 'the home page' => []] as $label => $args) {
    $query($args);
    $feeds[$label] = $echoed(static fn () => feed_links_extra());
}
$query(['cat' => $category]);
$feeds['a category, given words'] = $echoed(static fn () => feed_links_extra(['separator' => '|', 'cattitle' => '%1$s %2$s [%3$s]']));
$query(['p' => $single]);
add_filter('feed_links_show_comments_feed', '__return_false');
$feeds['a post, comments feed hidden'] = $echoed(static fn () => feed_links_extra());
remove_filter('feed_links_show_comments_feed', '__return_false');
add_filter('feed_links_extra_show_post_comments_feed', '__return_false');
$feeds['a post, its comments feed hidden'] = $echoed(static fn () => feed_links_extra());
remove_filter('feed_links_extra_show_post_comments_feed', '__return_false');
foreach (['category' => ['cat' => $category], 'tag' => ['tag_id' => $tag], 'tax' => ['zz_gt_genre' => 'zz-gt-genre'], 'post_type_archive' => ['post_type' => 'zz_gt_book'], 'author' => ['author' => 1], 'search' => ['s' => 'zz gt single']] as $kind => $args) {
    $query($args);
    add_filter("feed_links_extra_show_{$kind}_feed", '__return_false');
    $feeds["{$kind}, hidden"] = $echoed(static fn () => feed_links_extra());
    remove_filter("feed_links_extra_show_{$kind}_feed", '__return_false');
}
$say('extra feed links', $mask($feeds));

$dir = get_stylesheet_directory();
foreach (['header-zzgt.php', 'footer-zzgt.php', 'sidebar-zzgt.php'] as $file) {
    $made['files'][] = "{$dir}/{$file}";
    file_put_contents("{$dir}/{$file}", "<?php echo '[{$file} ', json_encode(\$args), ']';\n");
}
$heard = [];
foreach (['get_header', 'get_footer', 'get_sidebar'] as $hook) {
    add_action($hook, static function ($name, $args) use ($hook, &$heard) {
        $heard[] = [$hook, $name, $args];
    }, 10, 2);
}
$templates = [];
foreach (['header' => static fn ($name, $args) => get_header($name, $args), 'footer' => static fn ($name, $args) => get_footer($name, $args), 'sidebar' => static fn ($name, $args) => get_sidebar($name, $args)] as $part => $load) {
    ob_start();
    $returned = $load('zzgt', ['zz' => $part]);
    $templates[$part] = [$returned, (string) ob_get_clean()];
}
$templates[] = $heard;
$say('header, footer and sidebar', $templates);

$hooks = [$echoed(static fn () => _wp_render_title_tag())];
$query(['p' => $single]);
foreach (['wp_head', 'wp_footer', 'wp_body_open'] as $hook) {
    remove_all_actions($hook);
    add_action($hook, static function (...$args) use ($hook) {
        echo "[{$hook} " . json_encode($args) . ']';
    });
}
$before = [did_action('wp_head'), did_action('wp_footer'), did_action('wp_body_open')];
$hooks[] = [$echoed(static fn () => wp_head()), $echoed(static fn () => wp_body_open()), $echoed(static fn () => wp_footer())];
$hooks[] = [did_action('wp_head') - $before[0], did_action('wp_footer') - $before[1], did_action('wp_body_open') - $before[2]];
$say('the head, body and footer hooks', $hooks);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
