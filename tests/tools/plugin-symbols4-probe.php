<?php
/**
 * Fourth behaviour probe from the top of the catalogue: the feed links
 * All in One SEO prints, Yoast's robots.txt and gallery images, the privacy
 * link a login screen shows, the legacy theme lists ManageWP and the
 * UpdraftPlus family read, the numeric-slug resolver Site Kit runs, the
 * menu-item decorator, and the block parser class. Same protocol as
 * api-probe.php; the posts and the menu it needs are its own, swept by name.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// The sitemap's robots line is added on init, which WP-CLI has run on the reference; WP-CLI
// then takes kses off content saves, so the probe's posts are saved as written on both stacks.
if (!did_action('init')) {
    do_action('init');
}
kses_remove_filters();
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
add_filter('deprecated_function_trigger_error', '__return_false');
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
$rel = static fn ($v) => is_string($v) ? str_replace(['https://' . $bare, 'http://' . $bare], ['{home}', '{home}'], $v) : $v;
$deep = static function ($v) use (&$deep, $rel) {
    return is_array($v) ? array_map($deep, $v) : $rel($v);
};
$notices = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$notices): void {
    $notices[] = [(string) $function, (string) $replacement, (string) $version];
}, 10, 3);
$heard = static function () use (&$notices): array {
    $out = $notices;
    $notices = [];
    return $out;
};
// Records what a documented filter is handed, passing its value through.
$spied = [];
$spy = static function (string $hook) use (&$spied, $deep): void {
    add_filter($hook, static function () use ($hook, &$spied, $deep) {
        $args = func_get_args();
        $spied[] = [$hook, $deep(array_map(static fn ($a) => is_object($a) ? get_class($a) : $a, $args))];
        return $args[0] ?? null;
    }, 10, 9);
};
$caught = static function () use (&$spied): array {
    $out = $spied;
    $spied = [];
    return $out;
};
foreach (['author_feed_link', 'category_feed_link', 'tag_feed_link', 'taxonomy_feed_link', 'post_comments_feed_link', 'post_type_archive_feed_link', 'search_feed_link', 'privacy_policy_url', 'the_privacy_policy_link', 'get_lastpostdate', 'robots_txt', 'post_gallery_images'] as $hook) {
    $spy($hook);
}

$sweep = static function (): void {
    foreach (get_posts(['post_type' => ['post', 'page', 'nav_menu_item'], 'post_status' => 'any', 'numberposts' => -1, 'name__in' => [], 's' => '', 'meta_key' => '_minn_probe4']) as $leftover) {
        if (is_int($leftover->ID) && $leftover->ID > 1) {
            wp_delete_post($leftover->ID, true);
        }
    }
    $menu = get_term_by('name', 'minn-probe4-menu', 'nav_menu');
    if ($menu) {
        wp_delete_term($menu->term_id, 'nav_menu');
    }
};
$sweep();

// --- Feed links.
$tag = get_term_by('slug', 'engine', 'post_tag');
$tagId = $tag ? (int) $tag->term_id : 0;
$say('probe tag', $tagId > 0);
$say('get_author_feed_link', [$rel(get_author_feed_link(1)), $rel(get_author_feed_link(1, 'atom')), $rel(get_author_feed_link(1, 'rss2')), $rel(get_author_feed_link(999999)), $caught()]);
$say('get_category_feed_link', [$rel(get_category_feed_link(1)), $rel(get_category_feed_link(1, 'atom')), get_category_feed_link(999999), $caught()]);
$say('get_tag_feed_link', [$rel(get_tag_feed_link($tagId)), $rel(get_tag_feed_link($tagId, 'rdf')), get_tag_feed_link(999999), $caught()]);
$say('get_term_feed_link', [$rel(get_term_feed_link(1, 'category')), $rel(get_term_feed_link(get_term(1, 'category'))), $rel(get_term_feed_link($tagId, 'post_tag', 'atom')), get_term_feed_link(1, 'no_such_taxonomy'), $caught()]);
$say('get_post_comments_feed_link', [$rel(get_post_comments_feed_link(1)), $rel(get_post_comments_feed_link(1, 'atom')), $rel(get_post_comments_feed_link(2)), $rel(get_post_comments_feed_link(999999)), $caught()]);
$say('get_post_type_archive_feed_link', [$rel(get_post_type_archive_feed_link('post')), $rel(get_post_type_archive_feed_link('post', 'atom')), get_post_type_archive_feed_link('page'), get_post_type_archive_feed_link('no_such_type'), $caught()]);
$say('get_search_feed_link', [$rel(get_search_feed_link('hello')), $rel(get_search_feed_link('hello world', 'atom')), $rel(get_search_feed_link('a&b <c>')), $rel(get_search_feed_link('')), $caught()]);

// --- The privacy policy link, unset, on an unpublished page, then on a published one.
$privacy = get_option('wp_page_for_privacy_policy');
$privacyRow = static function () use ($rel, $caught): array {
    ob_start();
    the_privacy_policy_link('<div class="privacy-policy-page-link">', '</div>');
    $printed = (string) ob_get_clean();
    return [$rel(get_privacy_policy_url()), $rel(get_the_privacy_policy_link()), $rel(get_the_privacy_policy_link('<p>', '</p>')), $rel($printed), $caught()];
};
update_option('wp_page_for_privacy_policy', 0);
$say('privacy unset', $privacyRow());
update_option('wp_page_for_privacy_policy', 3);
$say('privacy draft page', $privacyRow());
update_option('wp_page_for_privacy_policy', 2);
$say('privacy published page', $privacyRow());
update_option('wp_page_for_privacy_policy', 999999);
$say('privacy missing page', $privacyRow());
update_option('wp_page_for_privacy_policy', $privacy);

// --- Galleries: a block gallery, a shortcode gallery, none.
$image = (int) (get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'ASC'])[0]->ID ?? 0);
$say('probe image', $image > 0);
$galleryPost = static function (string $slug, string $content): int {
    $id = (int) wp_insert_post(['post_title' => $slug, 'post_name' => $slug, 'post_status' => 'publish', 'post_content' => $content, 'post_author' => 1]);
    update_post_meta($id, '_minn_probe4', '1');
    return $id;
};
$imageUrl = (string) wp_get_attachment_url($image);
$withBlock = $galleryPost('minn-probe4-block-gallery', '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"id":' . $image . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . $imageUrl . '" alt="" class="wp-image-' . $image . '"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->');
$withCode = $galleryPost('minn-probe4-shortcode-gallery', "Intro\n\n[gallery ids=\"{$image}\" size=\"thumbnail\"]\n\nOutro");
$withTwo = $galleryPost('minn-probe4-two-galleries', "[gallery ids=\"{$image}\"]\n\n[gallery ids=\"{$image}\" size=\"medium\"]");
$plain = $galleryPost('minn-probe4-no-gallery', 'No gallery here.');
foreach (['block' => $withBlock, 'shortcode' => $withCode, 'two' => $withTwo, 'none' => $plain] as $name => $id) {
    $ownId = static fn ($v) => is_string($v) ? str_replace("galleryid-{$id}", 'galleryid-{post}', $v) : $v;
    $say("get_post_galleries {$name}", [$deep(get_post_galleries($id, false)), array_map($ownId, $deep(get_post_galleries($id)))]);
    $say("get_post_gallery_images {$name}", [$deep(get_post_gallery_images($id)), $deep(get_post_gallery_images(get_post($id))), $caught()]);
}
$say('get_post_gallery_images missing post', get_post_gallery_images(999999));

// --- The latest post date in each zone and type.
$dates = [];
foreach (['server', 'blog', 'gmt'] as $zone) {
    foreach (['any', 'post', 'page', 'attachment', 'no_such_type'] as $type) {
        $value = get_lastpostdate($zone, $type);
        $dates["{$zone} {$type}"] = is_string($value) ? (bool) preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d(\.\d+)?$/', $value) . ':' . strlen($value) : $value;
    }
}
$say('get_lastpostdate shapes', $dates);
$say('get_lastpostdate values agree', [get_lastpostdate('gmt', 'post') === get_lastpostdate('gmt', 'any') || get_lastpostdate('gmt', 'page') === get_lastpostdate('gmt', 'any'), substr((string) get_lastpostdate('server'), 0, 19) === (string) get_lastpostdate('blog'), array_map(
    // The dates are the probe's own posts, made a moment ago: their shape is kept, not the time.
    static fn (array $call) => [$call[0], array_map(static fn ($v) => is_string($v) && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d(\.\d+)?$/', $v) ? 'date:' . strlen($v) : $v, $call[1])],
    $caught()
)]);

// --- The legacy theme lists.
$themes = get_themes();
$rows = [];
foreach ($themes as $name => $theme) {
    $rows[$name] = [get_class($theme), $theme['Name'], $theme['Stylesheet'], $theme['Template'], $theme['Version'], $theme['Status'], $theme['Parent Theme'], $theme['Template Files'] === null ? null : 'files'];
}
ksort($rows);
$say('get_themes', [$rows, $heard()]);
$say('get_current_theme', [get_current_theme(), $heard()]);

// --- create_initial_post_types re-registers core's types without disturbing them.
$before = array_keys(get_post_types());
$postLabels = (array) get_post_type_object('post')->labels;
$result = create_initial_post_types();
$say('create_initial_post_types', [$result, array_keys(get_post_types()) === $before, (array) get_post_type_object('post')->labels === $postLabels, get_post_type_object('page')->hierarchical, get_post_type_object('wp_block')->public]);

// --- do_robots, public and private.
$public = get_option('blog_public');
$robotsActions = 0;
$countRobots = static function () use (&$robotsActions): void {
    $robotsActions++;
};
add_action('do_robotstxt', $countRobots);
update_option('blog_public', '1');
ob_start();
do_robots();
$say('do_robots public', [$rel((string) ob_get_clean()), $robotsActions, $caught()]);
update_option('blog_public', '0');
ob_start();
do_robots();
$say('do_robots private', [$rel((string) ob_get_clean()), $robotsActions, $caught()]);
update_option('blog_public', $public);
remove_action('do_robotstxt', $countRobots);

// --- wp_resolve_numeric_slug_conflicts against a post whose slug is a number.
$numeric = $galleryPost('2019', 'A post named 2019.');
$numericMonth = $galleryPost('11', 'A post named 11.');
$cases = [
    'empty' => [],
    'year only' => ['year' => '2019'],
    'year missing post' => ['year' => '2018'],
    'year and month' => ['year' => '2019', 'monthnum' => '11'],
    'month as name' => ['year' => '2026', 'monthnum' => '11'],
    'day' => ['year' => '2026', 'monthnum' => '08', 'day' => '28'],
    'name' => ['name' => 'hello-world'],
    'page and year' => ['year' => '2019', 'paged' => '2'],
];
$resolved = [];
foreach ($cases as $name => $vars) {
    $resolved[$name] = wp_resolve_numeric_slug_conflicts($vars);
}
$say('wp_resolve_numeric_slug_conflicts', $resolved);

// --- wp_setup_nav_menu_item over menu items, a page, and a term.
$menuId = (int) wp_create_nav_menu('minn-probe4-menu');
$item = static function (array $meta, string $title = '') use ($menuId): int {
    $id = (int) wp_insert_post(['post_type' => 'nav_menu_item', 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => 1]);
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }
    update_post_meta($id, '_minn_probe4', '1');
    wp_set_object_terms($id, [$menuId], 'nav_menu');
    return $id;
};
$custom = $item(['_menu_item_type' => 'custom', '_menu_item_object' => 'custom', '_menu_item_object_id' => '0', '_menu_item_url' => 'https://example.com/x', '_menu_item_target' => '_blank', '_menu_item_classes' => ['one', 'two'], '_menu_item_xfn' => 'friend', '_menu_item_menu_item_parent' => '0'], 'Custom link');
$pageItem = $item(['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => '2', '_menu_item_url' => '', '_menu_item_target' => '', '_menu_item_classes' => [''], '_menu_item_xfn' => '', '_menu_item_menu_item_parent' => (string) $custom]);
$termItem = $item(['_menu_item_type' => 'taxonomy', '_menu_item_object' => 'category', '_menu_item_object_id' => '1', '_menu_item_url' => '', '_menu_item_target' => '', '_menu_item_classes' => [''], '_menu_item_xfn' => '', '_menu_item_menu_item_parent' => '0'], 'Renamed category');
$goneItem = $item(['_menu_item_type' => 'post_type', '_menu_item_object' => 'page', '_menu_item_object_id' => '999999', '_menu_item_url' => '', '_menu_item_target' => '', '_menu_item_classes' => [''], '_menu_item_xfn' => '', '_menu_item_menu_item_parent' => '0']);
$shape = static function ($decorated) use ($rel, $custom, $pageItem, $termItem, $goneItem): array {
    $out = [];
    foreach (['db_id', 'menu_item_parent', 'object_id', 'object', 'type', 'type_label', 'url', 'title', 'target', 'attr_title', 'description', 'classes', 'xfn', '_invalid', 'post_content', 'post_excerpt'] as $key) {
        $value = $decorated->$key ?? 'UNSET';
        if (in_array($key, ['db_id', 'menu_item_parent'], true) && is_numeric($value) && (int) $value > 0) {
            $value = array_search((int) $value, [$custom => $custom, $pageItem => $pageItem, $termItem => $termItem, $goneItem => $goneItem], true) === false ? 'other' : 'item';
        }
        $out[$key] = is_array($value) ? $value : $rel(is_scalar($value) || $value === null ? $value : gettype($value));
    }
    return $out;
};
foreach (['custom' => $custom, 'page' => $pageItem, 'term' => $termItem, 'gone' => $goneItem] as $name => $id) {
    $say("wp_setup_nav_menu_item {$name}", $shape(wp_setup_nav_menu_item(get_post($id))));
}
$say('wp_setup_nav_menu_item page post', $shape(wp_setup_nav_menu_item(get_post(2))));
$say('wp_setup_nav_menu_item a term object', $shape(wp_setup_nav_menu_item(get_term(1, 'category'))));
$say('wp_setup_nav_menu_item non-object', wp_setup_nav_menu_item('nope'));

// --- WP_Block_Parser.
$documents = [
    'empty' => '',
    'freeform' => 'Just text',
    'paragraph' => "<!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph -->",
    'nested' => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:heading {"level":3} --><h3>T</h3><!-- /wp:heading --></div><!-- /wp:group -->',
    'void' => 'a<!-- wp:latest-posts {"postsToShow":2} /-->b',
    'namespaced' => '<!-- wp:my/block {"x":[1,2]} --><b>x</b><!-- /wp:my/block -->',
];
$parsed = [];
foreach ($documents as $name => $document) {
    $parser = new WP_Block_Parser();
    $blocks = $parser->parse($document);
    $parsed[$name] = [$blocks, $blocks === parse_blocks($document)];
}
$say('WP_Block_Parser', $parsed);

$sweep();
foreach ([$numeric, $numericMonth] as $id) {
    if ($id > 1) {
        wp_delete_post($id, true);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
