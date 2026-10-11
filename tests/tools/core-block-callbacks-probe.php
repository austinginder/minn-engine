<?php
/**
 * Core blocks' render callbacks called by name (probe core-block-callbacks):
 * each block's registered callback is swapped, for its render only, for one
 * that calls the core function by name, so the block supports see the block
 * being rendered as they do on a page. Accordion and its items, tabs with
 * their list and panels, breadcrumbs, a comment's edit link, and the
 * categories dropdown's script. The probe's post and comments are removed,
 * and the options it may touch put back, at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$made = ['posts' => [], 'comments' => []];
$options = [];
foreach (['page_comments', 'comments_per_page', 'default_comments_page', 'thread_comments'] as $name) {
    $options[$name] = get_option($name, null);
}
register_shutdown_function(static function () use (&$made, $options): void {
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($options as $name => $value) {
        $value === null ? delete_option($name) : update_option($name, $value);
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function (string $html) use (&$ids, $host): string {
    $html = (string) preg_replace(['#https?://' . preg_quote($host, '#') . '#', '/(wp-container-[a-z0-9-]+-is-layout-)[0-9a-f]{8}/'], ['{site}', '$1HASH'], $html);
    foreach ($ids as $id => $label) {
        $html = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $label . '}', $html);
    }
    return $html;
};
// The block's own callback stands aside for one render; this one calls the core function by name.
$through = static function (string $block, Closure $callback, string $markup, array $context = []) use ($mask) {
    $type = WP_Block_Type_Registry::get_instance()->get_registered($block);
    if (!$type) {
        return ['unregistered' => $block];
    }
    $saved = $type->render_callback;
    $type->render_callback = $callback;
    $parsed = parse_blocks($markup)[0];
    $html = $context === [] ? render_block($parsed) : (new WP_Block($parsed, $context))->render();
    $type->render_callback = $saved;
    return $mask(trim($html));
};

$item = '<!-- wp:accordion-item --><div class="wp-block-accordion-item"><!-- wp:accordion-heading {"title":"ZZ question"} --><h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">ZZ question</span></button></h3><!-- /wp:accordion-heading --><!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:paragraph --><p>ZZ answer</p><!-- /wp:paragraph --></div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item -->';
$accordion = static fn (string $attrs = '') => '<!-- wp:accordion ' . $attrs . ' --><div class="wp-block-accordion">' . $item . str_replace('ZZ question', 'ZZ second', $item) . '</div><!-- /wp:accordion -->';
$say('accordion', [
    'two items' => $through('core/accordion', static fn ($a, $c, $b) => render_block_core_accordion($a, $c), $accordion()),
    'one open at a time' => $through('core/accordion', static fn ($a, $c, $b) => render_block_core_accordion($a, $c), $accordion('{"autoclose":true}')),
    // Its items through their own callback, called by name, inside the accordion (alone, the reference reads a directive without a namespace).
    'items through their callback' => (static function () use ($through, $accordion) {
        $type = WP_Block_Type_Registry::get_instance()->get_registered('core/accordion-item');
        $saved = $type->render_callback;
        $type->render_callback = static fn ($a, $c, $b) => block_core_accordion_item_render($a, $c);
        $out = $through('core/accordion', static fn ($a, $c, $b) => render_block_core_accordion($a, $c), str_replace('<!-- wp:accordion-item -->', '<!-- wp:accordion-item {"openByDefault":true} -->', $accordion()));
        $type->render_callback = $saved;
        return $out;
    })(),
]);

$panel = static fn (string $label, string $text) => '<!-- wp:tab-panel {"label":"' . $label . '"} --><div class="wp-block-tab-panel"><!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph --></div><!-- /wp:tab-panel -->';
$tabs = '<!-- wp:tabs --><div class="wp-block-tabs"><!-- wp:tab-list --><div class="wp-block-tab-list"></div><!-- /wp:tab-list --><!-- wp:tab-panels --><div class="wp-block-tab-panels">' . $panel('ZZ one', 'First') . $panel('ZZ two', 'Second') . '</div><!-- /wp:tab-panels --></div><!-- /wp:tabs -->';
$say('tabs', [
    'the block' => $through('core/tabs', static fn ($a, $c, $b) => block_core_tabs_render_block_callback($a, $c, $b), $tabs),
    'a list' => $through('core/tab-list', static fn ($a, $c, $b) => block_core_tab_list_render_callback($a, $c, $b), '<!-- wp:tab-list --><div class="wp-block-tab-list"></div><!-- /wp:tab-list -->'),
    'a panel' => $through('core/tab-panel', static fn ($a, $c, $b) => block_core_tab_panel_render($a, $c, $b), $panel('ZZ alone', 'Alone')),
]);

wp_set_current_user(1);
$post = wp_insert_post(['post_title' => 'ZZ cbc post', 'post_status' => 'publish', 'post_author' => 1, 'post_content' => 'zz']);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$made['posts'][] = $post;
$ids[$post] = 'post';
$comments = [];
foreach (['first', 'second', 'third'] as $i => $word) {
    $comment = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => "ZZ {$word} comment", 'comment_author' => 'ZZ cbc', 'comment_approved' => 1, 'comment_date' => '2026-0' . ($i + 1) . '-01 10:00:00', 'comment_date_gmt' => '2026-0' . ($i + 1) . '-01 10:00:00']);
    if (!is_int($comment) || $comment <= 1) {
        fwrite(STDERR, "no fixture\n");
        exit(1);
    }
    $made['comments'][] = $comment;
    $comments[] = $comment;
    $ids[$comment] = "comment{$i}";
}
$say('comment edit link', [
    'an administrator' => $through('core/comment-edit-link', static fn ($a, $c, $b) => render_block_core_comment_edit_link($a, $c, $b), '<!-- wp:comment-edit-link /-->', ['commentId' => $comments[0]]),
    'in a new tab, aligned' => $through('core/comment-edit-link', static fn ($a, $c, $b) => render_block_core_comment_edit_link($a, $c, $b), '<!-- wp:comment-edit-link {"linkTarget":"_blank","textAlign":"right"} /-->', ['commentId' => $comments[0]]),
    'no comment' => $through('core/comment-edit-link', static fn ($a, $c, $b) => render_block_core_comment_edit_link($a, $c, $b), '<!-- wp:comment-edit-link /-->', ['commentId' => 0]),
    'a visitor' => (static function () use ($through, $comments) {
        wp_set_current_user(0);
        $out = $through('core/comment-edit-link', static fn ($a, $c, $b) => render_block_core_comment_edit_link($a, $c, $b), '<!-- wp:comment-edit-link /-->', ['commentId' => $comments[0]]);
        wp_set_current_user(1);
        return $out;
    })(),
]);

$say('breadcrumbs', [
    'a post' => (static function () use ($through, $post) {
        $GLOBALS['wp_query'] = new WP_Query(['p' => $post]);
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $GLOBALS['wp_query']->the_post();
        $out = $through('core/breadcrumbs', static fn ($a, $c, $b) => render_block_core_breadcrumbs($a, $c, $b), '<!-- wp:breadcrumbs /-->', ['postId' => $post, 'postType' => 'post']);
        wp_reset_postdata();
        return $out;
    })(),
]);
wp_set_current_user(0);

$say('categories dropdown script', [
    'an id' => $mask(build_dropdown_script_block_core_categories('zz-cat-dropdown-1')),
    'another' => $mask(build_dropdown_script_block_core_categories('wp-block-categories-2')),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
