<?php
/**
 * The core blocks' named functions (probe core-blocks): the render callback
 * each core block type carries, what calling a register_block_core_*
 * function again reports, the site logo and icon settings, a plugin's
 * callback wrapping the core one it replaced, and every render callback
 * called by name with a block in a post's (a comment's, a term's, a
 * query's) context; the blocks still to come (accordion, tabs, icon,
 * playlist, breadcrumbs, terms, footnotes, legacy widgets, comment and
 * query pagination, the site logo, the edit link) wait for their batch. A callback's output is recorded as its tags and text:
 * the reference adds the block supports' classes in render_block's filters
 * and, called outside a render, none at all. What it makes is removed at
 * the end. Same protocol as api-probe.php.
 */

$log = [];
$made = ['posts' => [], 'terms' => [], 'comments' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
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
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
});
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!did_action('init')) {
    do_action('init');
}
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = str_replace(['https://' . $host, 'http://' . $host], '{home}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
// A block's output as its tags, in order, and its text.
$shape = static function ($html): array {
    $html = (string) $html;
    preg_match_all('/<([a-z][a-z0-9-]*)/i', $html, $m);
    return ['tags' => $m[1], 'text' => trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES)))];
};

// Something to show: an author with a bio, their post with a category, a tag and a comment.
$author = wp_insert_user(['user_login' => 'zz_blocks_author', 'user_pass' => 'zz-blocks-pass-1', 'user_email' => 'zz-blocks@example.com', 'display_name' => 'ZZ Blocks Author', 'description' => 'Writes about blocks.', 'role' => 'author']);
$author = is_int($author) ? $author : 0;
$made['users'][] = $author;
$ids[$author] = 'author';
$cat = wp_insert_term('ZZ Blocks Cat', 'category', ['description' => 'About the blocks.']);
$cat = is_array($cat) ? (int) $cat['term_id'] : 0;
$made['terms'][] = [$cat, 'category'];
$ids[$cat] = 'cat';
$tag = wp_insert_term('ZZ Blocks Tag', 'post_tag');
$tag = is_array($tag) ? (int) $tag['term_id'] : 0;
$made['terms'][] = [$tag, 'post_tag'];
$ids[$tag] = 'tag';
$post = wp_insert_post(['post_title' => 'ZZ Blocks Post', 'post_name' => 'zz-blocks-post', 'post_status' => 'publish', 'post_author' => $author, 'post_date' => '2025-05-06 07:08:09', 'post_date_gmt' => '2025-05-06 07:08:09', 'post_excerpt' => 'A short excerpt.', 'post_content' => "<!-- wp:paragraph -->\n<p>Words of the blocks post, enough to read.</p>\n<!-- /wp:paragraph -->", 'post_category' => [$cat], 'tags_input' => [$tag], 'comment_status' => 'open']);
$post = is_int($post) ? $post : 0;
$made['posts'][] = $post;
$ids[$post] = 'post';
$comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'ZZ Commenter', 'comment_author_email' => 'zz-c@example.com', 'comment_content' => 'A comment on the blocks.', 'comment_approved' => 1, 'comment_date' => '2025-05-07 08:00:00', 'comment_date_gmt' => '2025-05-07 08:00:00']);
$comment = is_int($comment) ? $comment : 0;
$made['comments'][] = $comment;
$ids[$comment] = 'comment';
$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);

$postContext = ['postId' => $post, 'postType' => 'post'];
$commentContext = $postContext + ['commentId' => $comment];
$termContext = ['termId' => $cat, 'taxonomy' => 'category'];
$queryContext = $postContext + ['queryId' => 7, 'query' => ['perPage' => 1, 'pages' => 0, 'offset' => 0, 'postType' => 'post', 'order' => 'asc', 'orderBy' => 'title', 'author' => '', 'search' => 'ZZ Blocks', 'exclude' => [], 'sticky' => '', 'inherit' => false]];
// A callback called by name with a block parsed from markup: its attributes, its inner blocks rendered, the instance.
$render = static function (string $markup, array $context, callable $call) use ($shape): array {
    $parsed = parse_blocks($markup)[0];
    $block = new WP_Block($parsed, $context);
    $content = '';
    $index = 0;
    foreach ($parsed['innerContent'] as $chunk) {
        $content .= is_string($chunk) ? $chunk : (new WP_Block($parsed['innerBlocks'][$index++], $context))->render();
    }
    try {
        return $shape($call($block->attributes, $content, $block));
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};

$callbacks = [];
foreach (WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
    if (str_starts_with($name, 'core/')) {
        $callbacks[$name] = is_string($type->render_callback) ? $type->render_callback : ($type->render_callback === null ? null : 'not a name');
    }
}
ksort($callbacks);
$say('core render callbacks', $callbacks);

$wrong = [];
add_action('doing_it_wrong_run', static function ($function, $message) use (&$wrong) {
    $wrong[] = [$function, $message];
}, 10, 2);
$returned = [
    register_block_core_accordion(),
    register_block_core_accordion_item(),
    register_block_core_archives(),
    register_block_core_avatar(),
    register_block_core_block(),
    register_block_core_breadcrumbs(),
    register_block_core_button(),
    register_block_core_calendar(),
    register_block_core_categories(),
    register_block_core_comment_author_name(),
    register_block_core_comment_content(),
    register_block_core_comment_date(),
    register_block_core_comment_edit_link(),
    register_block_core_comment_reply_link(),
    register_block_core_comment_template(),
    register_block_core_comments(),
    register_block_core_comments_pagination(),
    register_block_core_comments_pagination_next(),
    register_block_core_comments_pagination_numbers(),
    register_block_core_comments_pagination_previous(),
    register_block_core_comments_title(),
    register_block_core_cover(),
    register_block_core_details(),
    register_block_core_file(),
    register_block_core_footnotes(),
    register_block_core_gallery(),
    register_block_core_heading(),
    register_block_core_home_link(),
    register_block_core_icon(),
    register_block_core_image(),
    register_block_core_latest_comments(),
    register_block_core_latest_posts(),
    register_block_core_legacy_widget(),
    register_block_core_list(),
    register_block_core_loginout(),
    register_block_core_media_text(),
    register_block_core_navigation(),
    register_block_core_navigation_link(),
    register_block_core_navigation_overlay_close(),
    register_block_core_navigation_submenu(),
    register_block_core_page_list(),
    register_block_core_page_list_item(),
    register_block_core_paragraph(),
    register_block_core_pattern(),
    register_block_core_playlist(),
    register_block_core_playlist_track(),
    register_block_core_post_author(),
    register_block_core_post_author_biography(),
    register_block_core_post_author_name(),
    register_block_core_post_comments_count(),
    register_block_core_post_comments_form(),
    register_block_core_post_comments_link(),
    register_block_core_post_content(),
    register_block_core_post_date(),
    register_block_core_post_excerpt(),
    register_block_core_post_featured_image(),
    register_block_core_post_navigation_link(),
    register_block_core_post_template(),
    register_block_core_post_terms(),
    register_block_core_post_time_to_read(),
    register_block_core_post_title(),
    register_block_core_query(),
    register_block_core_query_no_results(),
    register_block_core_query_pagination(),
    register_block_core_query_pagination_next(),
    register_block_core_query_pagination_numbers(),
    register_block_core_query_pagination_previous(),
    register_block_core_query_title(),
    register_block_core_query_total(),
    register_block_core_read_more(),
    register_block_core_rss(),
    register_block_core_search(),
    register_block_core_shortcode(),
    register_block_core_site_logo(),
    register_block_core_site_tagline(),
    register_block_core_site_title(),
    register_block_core_social_link(),
    register_block_core_tab_list(),
    register_block_core_tab_panel(),
    register_block_core_tabs(),
    register_block_core_tag_cloud(),
    register_block_core_template_part(),
    register_block_core_term_count(),
    register_block_core_term_description(),
    register_block_core_term_name(),
    register_block_core_term_template(),
    register_block_core_video(),
    register_block_core_widget_group(),
];
$say('register_block_core_* again', [array_unique(array_map('gettype', $returned)), count($wrong), array_slice($wrong, 0, 3)]);
$say('core/archives still registered', WP_Block_Type_Registry::get_instance()->is_registered('core/archives'));

register_block_core_site_logo_setting();
register_block_core_site_icon_setting();
$settings = get_registered_settings();
$say('site logo and icon settings', [$settings['site_logo'] ?? null, $settings['site_icon'] ?? null]);

// A plugin wraps the core callback it replaces, and calls it.
$type = WP_Block_Type_Registry::get_instance()->get_registered('core/site-title');
$core = $type->render_callback;
$type->render_callback = static fn ($attributes, $content, $block) => '<div class="zz-wrap">' . call_user_func($core, $attributes, $content, $block) . '</div>';
$say('a plugin wraps a core callback', $shape(render_block(parse_blocks('<!-- wp:site-title {"level":0} /-->')[0])));
$type->render_callback = $core;

$say('block_core_gallery_render', $render('<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image --><figure class="wp-block-image"><img src="https://x.example/a.jpg" alt="In a gallery"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->', $postContext, static fn ($a, $c, $b) => block_core_gallery_render($a, $c, $b)));
$say('block_core_heading_render', $render('<!-- wp:heading --><h2 class="wp-block-heading">Head</h2><!-- /wp:heading -->', $postContext, static fn ($a, $c, $b) => block_core_heading_render($a, $c)));
$say('block_core_list_render', $render('<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->', $postContext, static fn ($a, $c, $b) => block_core_list_render($a, $c)));
$say('render_block_core_archives', $render('<!-- wp:archives {"showPostCounts":true} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_archives($a)));
$say('render_block_core_avatar', $render('<!-- wp:avatar {"size":48} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_avatar($a, $c, $b)));
$say('render_block_core_block', $render('<!-- wp:block {"ref":0} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_block($a, $c, $b)));
$say('render_block_core_button', $render('<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://x.example/">Go</a></div><!-- /wp:button -->', $postContext, static fn ($a, $c, $b) => render_block_core_button($a, $c)));
$say('render_block_core_calendar', $render('<!-- wp:calendar /-->', $postContext, static function ($a) {
    // The month shown is the one being viewed; this month would change the fixture every month.
    $GLOBALS['monthnum'] = 5;
    $GLOBALS['year'] = 2025;
    try {
        return render_block_core_calendar($a);
    } finally {
        unset($GLOBALS['monthnum'], $GLOBALS['year']);
    }
}));
$say('render_block_core_categories', $render('<!-- wp:categories {"showPostCounts":true} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_categories($a, $c, $b)));
$say('render_block_core_comment_author_name', $render('<!-- wp:comment-author-name /-->', $commentContext, static fn ($a, $c, $b) => render_block_core_comment_author_name($a, $c, $b)));
$say('render_block_core_comment_content', $render('<!-- wp:comment-content /-->', $commentContext, static fn ($a, $c, $b) => render_block_core_comment_content($a, $c, $b)));
$say('render_block_core_comment_date', $render('<!-- wp:comment-date /-->', $commentContext, static fn ($a, $c, $b) => render_block_core_comment_date($a, $c, $b)));
$say('render_block_core_comment_reply_link', $render('<!-- wp:comment-reply-link /-->', $commentContext, static fn ($a, $c, $b) => render_block_core_comment_reply_link($a, $c, $b)));
$say('render_block_core_comment_template', $render('<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template -->', $commentContext, static fn ($a, $c, $b) => render_block_core_comment_template($a, $c, $b)));
$say('render_block_core_comments', $render('<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comments-title /--><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-content /--><!-- /wp:comment-template --></div><!-- /wp:comments -->', $postContext, static fn ($a, $c, $b) => render_block_core_comments($a, $c, $b)));
$say('render_block_core_comments_title', $render('<!-- wp:comments-title /-->', $postContext, static fn ($a, $c, $b) => render_block_core_comments_title($a)));
$say('render_block_core_cover', $render('<!-- wp:cover {"url":"https://x.example/a.jpg","dimRatio":50} --><div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="https://x.example/a.jpg" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Over</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->', $postContext, static fn ($a, $c, $b) => render_block_core_cover($a, $c)));
$say('render_block_core_file', $render('<!-- wp:file {"href":"https://x.example/a.pdf"} --><div class="wp-block-file"><a href="https://x.example/a.pdf">a.pdf</a><a href="https://x.example/a.pdf" class="wp-block-file__button wp-element-button" download>Download</a></div><!-- /wp:file -->', $postContext, static fn ($a, $c, $b) => render_block_core_file($a, $c)));
$say('render_block_core_home_link', $render('<!-- wp:home-link {"label":"Home"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_home_link($a, $c, $b)));
$say('render_block_core_image', $render('<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="https://x.example/a.jpg" alt="An image"/></figure><!-- /wp:image -->', $postContext, static fn ($a, $c, $b) => render_block_core_image($a, $c, $b)));
$say('render_block_core_latest_comments', $render('<!-- wp:latest-comments {"commentsToShow":1,"displayExcerpt":true} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_latest_comments($a)));
$say('render_block_core_latest_posts', $render('<!-- wp:latest-posts {"postsToShow":1,"displayPostDate":false} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_latest_posts($a)));
$say('render_block_core_loginout', $render('<!-- wp:loginout /-->', $postContext, static fn ($a, $c, $b) => render_block_core_loginout($a)));
$say('render_block_core_media_text', $render('<!-- wp:media-text {"mediaType":"image"} --><div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="https://x.example/a.jpg" alt=""/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Beside</p><!-- /wp:paragraph --></div></div><!-- /wp:media-text -->', $postContext, static fn ($a, $c, $b) => render_block_core_media_text($a, $c)));
$say('render_block_core_navigation', $render('<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:navigation-link {"label":"Ex","url":"https://x.example/"} /--><!-- /wp:navigation -->', $postContext, static fn ($a, $c, $b) => render_block_core_navigation($a, $c, $b)));
$say('render_block_core_navigation_link', $render('<!-- wp:navigation-link {"label":"Ex","url":"https://x.example/"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_navigation_link($a, $c, $b)));
$say('render_block_core_navigation_overlay_close', $render('<!-- wp:navigation-overlay-close /-->', $postContext, static fn ($a, $c, $b) => render_block_core_navigation_overlay_close($a)));
$say('render_block_core_navigation_submenu', $render('<!-- wp:navigation-submenu {"label":"Sub","url":"https://x.example/s"} --><!-- wp:navigation-link {"label":"In","url":"https://x.example/in"} /--><!-- /wp:navigation-submenu -->', $postContext, static fn ($a, $c, $b) => render_block_core_navigation_submenu($a, $c, $b)));
$say('render_block_core_page_list', $render('<!-- wp:page-list /-->', $postContext, static fn ($a, $c, $b) => render_block_core_page_list($a, $c, $b)));
$say('render_block_core_pattern', $render('<!-- wp:pattern {"slug":"zz/none"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_pattern($a)));
$say('render_block_core_post_author', $render('<!-- wp:post-author /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_author($a, $c, $b)));
$say('render_block_core_post_author_biography', $render('<!-- wp:post-author-biography /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_author_biography($a, $c, $b)));
$say('render_block_core_post_author_name', $render('<!-- wp:post-author-name /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_author_name($a, $c, $b)));
$say('render_block_core_post_comments_count', $render('<!-- wp:post-comments-count /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_comments_count($a, $c, $b)));
$say('render_block_core_post_comments_form', $render('<!-- wp:post-comments-form /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_comments_form($a, $c, $b)));
$say('render_block_core_post_comments_link', $render('<!-- wp:post-comments-link /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_comments_link($a, $c, $b)));
$say('render_block_core_post_content', $render('<!-- wp:post-content /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_content($a, $c, $b)));
$say('render_block_core_post_date', $render('<!-- wp:post-date {"format":"Y-m-d"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_date($a, $c, $b)));
$say('render_block_core_post_excerpt', $render('<!-- wp:post-excerpt /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_excerpt($a, $c, $b)));
$say('render_block_core_post_featured_image', $render('<!-- wp:post-featured-image /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_featured_image($a, $c, $b)));
$say('render_block_core_post_navigation_link', $render('<!-- wp:post-navigation-link {"type":"previous","showTitle":true} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_navigation_link($a, $c)));
$say('render_block_core_post_template', $render('<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->', $queryContext, static fn ($a, $c, $b) => render_block_core_post_template($a, $c, $b)));
$say('render_block_core_post_terms', $render('<!-- wp:post-terms {"term":"category"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_terms($a, $c, $b)));
$say('render_block_core_post_time_to_read', $render('<!-- wp:post-time-to-read /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_time_to_read($a, $c, $b)));
$say('render_block_core_post_title', $render('<!-- wp:post-title {"level":2} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_post_title($a, $c, $b)));
$say('render_block_core_query', $render('<!-- wp:query {"queryId":7,"query":{"perPage":1,"pages":0,"offset":0,"postType":"post","order":"asc","orderBy":"title","author":"","search":"ZZ Blocks","exclude":[],"sticky":"","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --><!-- wp:query-pagination --><!-- wp:query-pagination-numbers /--><!-- /wp:query-pagination --></div><!-- /wp:query -->', $queryContext, static fn ($a, $c, $b) => render_block_core_query($a, $c, $b)));
$say('render_block_core_query_no_results', $render('<!-- wp:query-no-results /-->', $queryContext, static fn ($a, $c, $b) => render_block_core_query_no_results($a, $c, $b)));
$say('render_block_core_query_pagination', $render('<!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->', $queryContext, static fn ($a, $c, $b) => render_block_core_query_pagination($a, $c)));
$say('render_block_core_query_title', $render('<!-- wp:query-title {"type":"archive"} /-->', $queryContext, static fn ($a, $c, $b) => render_block_core_query_title($a, $c, $b)));
$say('render_block_core_query_total', $render('<!-- wp:query-total /-->', $queryContext, static fn ($a, $c, $b) => render_block_core_query_total($a, $c, $b)));
$say('render_block_core_read_more', $render('<!-- wp:read-more /-->', $postContext, static fn ($a, $c, $b) => render_block_core_read_more($a, $c, $b)));
$say('render_block_core_search', $render('<!-- wp:search {"label":"Search","buttonText":"Find"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_search($a)));
$say('render_block_core_shortcode', $render('<!-- wp:shortcode -->[zz_no_such_shortcode]<!-- /wp:shortcode -->', $postContext, static fn ($a, $c, $b) => render_block_core_shortcode($a, $c)));
$say('render_block_core_site_tagline', $render('<!-- wp:site-tagline /-->', $postContext, static fn ($a, $c, $b) => render_block_core_site_tagline($a)));
$say('render_block_core_site_title', $render('<!-- wp:site-title {"level":0} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_site_title($a)));
$say('render_block_core_social_link', $render('<!-- wp:social-link {"url":"https://x.example/","service":"wordpress","label":"WP"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_social_link($a, $c, $b)));
$say('render_block_core_tag_cloud', $render('<!-- wp:tag-cloud /-->', $postContext, static fn ($a, $c, $b) => render_block_core_tag_cloud($a)));
$say('render_block_core_term_count', $render('<!-- wp:term-count /-->', $termContext, static fn ($a, $c, $b) => render_block_core_term_count($a, $c, $b)));
$say('render_block_core_term_description', $render('<!-- wp:term-description /-->', $termContext, static fn ($a, $c, $b) => render_block_core_term_description($a, $c, $b)));
$say('render_block_core_term_name', $render('<!-- wp:term-name /-->', $termContext, static fn ($a, $c, $b) => render_block_core_term_name($a, $c, $b)));
$say('render_block_core_video', $render('<!-- wp:video --><figure class="wp-block-video"><video controls src="https://x.example/a.mp4"></video></figure><!-- /wp:video -->', $postContext, static fn ($a, $c, $b) => render_block_core_video($a, $c)));
$say('render_block_core_widget_group', $render('<!-- wp:widget-group --><!-- wp:paragraph --><p>W</p><!-- /wp:paragraph --><!-- /wp:widget-group -->', $postContext, static fn ($a, $c, $b) => render_block_core_widget_group($a, $c, $b)));
$say('render_block_core_template_part', $render('<!-- wp:template-part {"slug":"zz-no-such-part"} /-->', $postContext, static fn ($a, $c, $b) => render_block_core_template_part($a)));

// Whole renders, exactly: a block parsed from markup and rendered in its context, as a template renders it.
$full = static function (string $markup, array $context) use ($mask) {
    try {
        return (new WP_Block(parse_blocks($markup)[0], $context))->render();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$cases = [
    'post-date' => ['<!-- wp:post-date /-->', '<!-- wp:post-date {"format":"Y-m-d"} /-->', '<!-- wp:post-date {"isLink":true,"textAlign":"center"} /-->'],
    'post-author' => ['<!-- wp:post-author {"showAvatar":true,"showBio":true,"byline":"Written by"} /-->', '<!-- wp:post-author {"showAvatar":false,"isLink":true} /-->'],
    'post-author-biography' => ['<!-- wp:post-author-biography /-->'],
    'post-comments-count' => ['<!-- wp:post-comments-count /-->'],
    'post-comments-link' => ['<!-- wp:post-comments-link /-->'],
    'post-time-to-read' => ['<!-- wp:post-time-to-read /-->', '<!-- wp:post-time-to-read {"displayAsRange":false} /-->', '<!-- wp:post-time-to-read {"displayMode":"words"} /-->'],
    'read-more' => ['<!-- wp:read-more /-->', '<!-- wp:read-more {"content":"Keep reading","linkTarget":"_blank"} /-->'],
    'avatar' => ['<!-- wp:avatar {"size":48} /-->', '<!-- wp:avatar {"size":64,"isLink":true} /-->'],
    'categories' => ['<!-- wp:categories {"showPostCounts":true} /-->', '<!-- wp:categories {"displayAsDropdown":true} /-->'],
    'home-link' => ['<!-- wp:home-link {"label":"Home"} /-->'],
    'loginout' => ['<!-- wp:loginout /-->'],
    'shortcode' => ['<!-- wp:shortcode -->[zz_no_such_shortcode]<!-- /wp:shortcode -->'],
    'widget-group' => ['<!-- wp:widget-group {"title":"Group"} --><!-- wp:paragraph --><p>W</p><!-- /wp:paragraph --><!-- /wp:widget-group -->'],
    'navigation-submenu' => ['<!-- wp:navigation-submenu {"label":"Sub","url":"https://x.example/s"} --><!-- wp:navigation-link {"label":"In","url":"https://x.example/in"} /--><!-- /wp:navigation-submenu -->'],
    'navigation-overlay-close' => ['<!-- wp:navigation-overlay-close /-->', '<!-- wp:navigation-overlay-close {"displayMode":"text","text":"Close"} /-->', '<!-- wp:navigation-overlay-close {"displayMode":"both","text":"Close"} /-->'],
    'navigation with a submenu' => [
        '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:home-link {"label":"Home"} /--><!-- wp:navigation-submenu {"label":"Sub","url":"https://x.example/s"} --><!-- wp:navigation-link {"label":"In","url":"https://x.example/in"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->',
        '<!-- wp:navigation {"overlayMenu":"never","openSubmenusOnClick":true} --><!-- wp:navigation-submenu {"label":"Sub","url":"https://x.example/s"} --><!-- wp:navigation-link {"label":"In","url":"https://x.example/in"} /--><!-- /wp:navigation-submenu --><!-- /wp:navigation -->',
    ],
    'social-link' => ['<!-- wp:social-link {"url":"https://x.example/","service":"wordpress","label":"WP"} /-->'],
    'post-navigation-link' => ['<!-- wp:post-navigation-link {"type":"previous"} /-->'],
];
// The login link returns to the page being viewed: a request to name it.
$server = $_SERVER;
$_SERVER = ['HTTP_HOST' => $host, 'REQUEST_URI' => '/zz-here/'] + $_SERVER;
foreach ($cases as $name => $markups) {
    $say("full {$name}", array_map(static fn ($m) => $full($m, $postContext), $markups));
}
$_SERVER = $server;
$say('full comment blocks', array_map(static fn ($m) => $full($m, $commentContext), ['<!-- wp:comment-date /-->', '<!-- wp:comment-date {"format":"Y-m-d","isLink":false} /-->', '<!-- wp:comment-reply-link /-->', '<!-- wp:comment-author-name {"isLink":true} /-->', '<!-- wp:comment-content /-->', '<!-- wp:comments-title /-->', '<!-- wp:comments-title {"showPostTitle":false,"showCommentsCount":false} /-->']));
$say('full term blocks', array_map(static fn ($m) => $full($m, $termContext), ['<!-- wp:term-name /-->', '<!-- wp:term-name {"isLink":true,"level":2} /-->', '<!-- wp:term-count /-->', '<!-- wp:term-description /-->']));
$say('block_core_post_time_to_read_word_count', array_map(static fn ($t) => [block_core_post_time_to_read_word_count($t, 'words'), block_core_post_time_to_read_word_count($t, 'characters_excluding_spaces'), block_core_post_time_to_read_word_count($t, 'characters_including_spaces')], [
    'Plain words in a row',
    '<p>Tagged <strong>words</strong></p><!-- a comment with words --> after',
    'Numbers 1,000 and 2025 and v2 count?',
    "Dashes--join and em\u{2014}dashes too, punctuation: (yes) [no] {maybe}!",
    'Entities &amp; spaces&nbsp;here &#8220;quoted&#8221; caf&eacute;',
    "Lines\nand\ttabs\r\nand soft\u{00AD}hyphens",
    '',
]));
$say('full query total', array_map(static fn ($m) => $full('<!-- wp:query ' . $m . ' --><div class="wp-block-query"><!-- wp:query-total /--><!-- wp:query-total {"displayType":"range-display"} /--></div><!-- /wp:query -->', $postContext), ['{"queryId":8,"query":{"perPage":1,"postType":"post","order":"asc","orderBy":"title","search":"ZZ Blocks","inherit":false}}']));

unset($GLOBALS['post']);
restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
