<?php
/**
 * The core blocks' named functions (wp-includes/blocks). Each render
 * callback, which the core block types carry by name as the reference's
 * do, renders through the engine's own renderer for the block
 * (_minn_render_core_callback); one the reference declares with fewer
 * parameters still passes on the content and instance WP_Block::render
 * gives it. Each register_block_core_* registers its block type again,
 * which the registry refuses while it is registered. Behaviour from
 * contracts/fixtures/api/core-blocks.json.
 */

use Minn\Blocks\Block as MinnBlock;
use Minn\Blocks\Renderer as MinnRenderer;
use Minn\Blocks\Wrapper;

// Render callbacks.

function block_core_accordion_item_render($attributes, $content)
{
    return _minn_render_core_callback('core/accordion-item', ...func_get_args());
}

function block_core_gallery_render($attributes, $content, $block)
{
    return _minn_render_core_callback('core/gallery', $attributes, $content, $block);
}

function block_core_heading_render($attributes, $content)
{
    return _minn_render_core_callback('core/heading', ...func_get_args());
}

function block_core_list_render($attributes, $content)
{
    return _minn_render_core_callback('core/list', ...func_get_args());
}

function block_core_tab_list_render_callback($attributes, $content, $block)
{
    return _minn_render_core_callback('core/tab-list', $attributes, $content, $block);
}

function block_core_tab_panel_render($attributes, $content, $block)
{
    return _minn_render_core_callback('core/tab-panel', $attributes, $content, $block);
}

function block_core_tabs_render_block_callback($attributes, $content, $block)
{
    return _minn_render_core_callback('core/tabs', $attributes, $content, $block);
}

function render_block_core_accordion($attributes, $content)
{
    return _minn_render_core_callback('core/accordion', ...func_get_args());
}

function render_block_core_archives($attributes)
{
    return _minn_render_core_callback('core/archives', ...func_get_args());
}

function render_block_core_avatar($attributes, $content, $block)
{
    return _minn_render_core_callback('core/avatar', $attributes, $content, $block);
}

function render_block_core_block($attributes, $content, $block_instance)
{
    return _minn_render_core_callback('core/block', $attributes, $content, $block_instance);
}

function render_block_core_breadcrumbs($attributes, $content, $block)
{
    return _minn_render_core_callback('core/breadcrumbs', $attributes, $content, $block);
}

function render_block_core_button($attributes, $content)
{
    return _minn_render_core_callback('core/button', ...func_get_args());
}

function render_block_core_calendar($attributes)
{
    return _minn_render_core_callback('core/calendar', ...func_get_args());
}

function render_block_core_categories($attributes, $content, $block)
{
    return _minn_render_core_callback('core/categories', $attributes, $content, $block);
}

function render_block_core_comment_author_name($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-author-name', $attributes, $content, $block);
}

function render_block_core_comment_content($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-content', $attributes, $content, $block);
}

function render_block_core_comment_date($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-date', $attributes, $content, $block);
}

function render_block_core_comment_edit_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-edit-link', $attributes, $content, $block);
}

function render_block_core_comment_reply_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-reply-link', $attributes, $content, $block);
}

function render_block_core_comment_template($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comment-template', $attributes, $content, $block);
}

/** For core/comments and core/post-comments (the instance names which). */
function render_block_core_comments($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comments', $attributes, $content, $block);
}

function render_block_core_comments_pagination($attributes, $content)
{
    return _minn_render_core_callback('core/comments-pagination', ...func_get_args());
}

function render_block_core_comments_pagination_next($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comments-pagination-next', $attributes, $content, $block);
}

function render_block_core_comments_pagination_numbers($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comments-pagination-numbers', $attributes, $content, $block);
}

function render_block_core_comments_pagination_previous($attributes, $content, $block)
{
    return _minn_render_core_callback('core/comments-pagination-previous', $attributes, $content, $block);
}

function render_block_core_comments_title($attributes)
{
    return _minn_render_core_callback('core/comments-title', ...func_get_args());
}

function render_block_core_cover($attributes, $content)
{
    return _minn_render_core_callback('core/cover', ...func_get_args());
}

function render_block_core_file($attributes, $content)
{
    return _minn_render_core_callback('core/file', ...func_get_args());
}

function render_block_core_footnotes($attributes, $content, $block)
{
    return _minn_render_core_callback('core/footnotes', $attributes, $content, $block);
}

function render_block_core_home_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/home-link', $attributes, $content, $block);
}

function render_block_core_icon($attributes)
{
    return _minn_render_core_callback('core/icon', ...func_get_args());
}

function render_block_core_image($attributes, $content, $block)
{
    return _minn_render_core_callback('core/image', $attributes, $content, $block);
}

function render_block_core_latest_comments($attributes)
{
    return _minn_render_core_callback('core/latest-comments', ...func_get_args());
}

function render_block_core_latest_posts($attributes)
{
    return _minn_render_core_callback('core/latest-posts', ...func_get_args());
}

function render_block_core_legacy_widget($attributes)
{
    return _minn_render_core_callback('core/legacy-widget', ...func_get_args());
}

function render_block_core_loginout($attributes)
{
    return _minn_render_core_callback('core/loginout', ...func_get_args());
}

function render_block_core_media_text($attributes, $content)
{
    return _minn_render_core_callback('core/media-text', ...func_get_args());
}

function render_block_core_navigation($attributes, $content, $block)
{
    return _minn_render_core_callback('core/navigation', $attributes, $content, $block);
}

function render_block_core_navigation_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/navigation-link', $attributes, $content, $block);
}

function render_block_core_navigation_overlay_close($attributes)
{
    return _minn_render_core_callback('core/navigation-overlay-close', ...func_get_args());
}

function render_block_core_navigation_submenu($attributes, $content, $block)
{
    return _minn_render_core_callback('core/navigation-submenu', $attributes, $content, $block);
}

function render_block_core_page_list($attributes, $content, $block)
{
    return _minn_render_core_callback('core/page-list', $attributes, $content, $block);
}

function render_block_core_pattern($attributes)
{
    return _minn_render_core_callback('core/pattern', ...func_get_args());
}

function render_block_core_playlist($attributes, $content, $block)
{
    return _minn_render_core_callback('core/playlist', $attributes, $content, $block);
}

function render_block_core_playlist_track($attributes, $content = '', $block = null)
{
    return _minn_render_core_callback('core/playlist-track', $attributes, $content, $block);
}

function render_block_core_post_author($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-author', $attributes, $content, $block);
}

function render_block_core_post_author_biography($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-author-biography', $attributes, $content, $block);
}

function render_block_core_post_author_name($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-author-name', $attributes, $content, $block);
}

function render_block_core_post_comments_count($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-comments-count', $attributes, $content, $block);
}

function render_block_core_post_comments_form($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-comments-form', $attributes, $content, $block);
}

function render_block_core_post_comments_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-comments-link', $attributes, $content, $block);
}

function render_block_core_post_content($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-content', $attributes, $content, $block);
}

function render_block_core_post_date($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-date', $attributes, $content, $block);
}

function render_block_core_post_excerpt($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-excerpt', $attributes, $content, $block);
}

function render_block_core_post_featured_image($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-featured-image', $attributes, $content, $block);
}

function render_block_core_post_navigation_link($attributes, $content)
{
    return _minn_render_core_callback('core/post-navigation-link', ...func_get_args());
}

function render_block_core_post_template($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-template', $attributes, $content, $block);
}

function render_block_core_post_terms($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-terms', $attributes, $content, $block);
}

function render_block_core_post_time_to_read($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-time-to-read', $attributes, $content, $block);
}

function render_block_core_post_title($attributes, $content, $block)
{
    return _minn_render_core_callback('core/post-title', $attributes, $content, $block);
}

function render_block_core_query($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query', $attributes, $content, $block);
}

function render_block_core_query_no_results($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-no-results', $attributes, $content, $block);
}

function render_block_core_query_pagination($attributes, $content)
{
    return _minn_render_core_callback('core/query-pagination', ...func_get_args());
}

function render_block_core_query_pagination_next($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-pagination-next', $attributes, $content, $block);
}

function render_block_core_query_pagination_numbers($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-pagination-numbers', $attributes, $content, $block);
}

function render_block_core_query_pagination_previous($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-pagination-previous', $attributes, $content, $block);
}

function render_block_core_query_title($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-title', $attributes, $content, $block);
}

function render_block_core_query_total($attributes, $content, $block)
{
    return _minn_render_core_callback('core/query-total', $attributes, $content, $block);
}

function render_block_core_read_more($attributes, $content, $block)
{
    return _minn_render_core_callback('core/read-more', $attributes, $content, $block);
}

function render_block_core_search($attributes)
{
    return _minn_render_core_callback('core/search', ...func_get_args());
}

function render_block_core_shortcode($attributes, $content)
{
    return _minn_render_core_callback('core/shortcode', ...func_get_args());
}

function render_block_core_site_logo($attributes)
{
    return _minn_render_core_callback('core/site-logo', ...func_get_args());
}

function render_block_core_site_tagline($attributes)
{
    return _minn_render_core_callback('core/site-tagline', ...func_get_args());
}

function render_block_core_site_title($attributes)
{
    return _minn_render_core_callback('core/site-title', ...func_get_args());
}

function render_block_core_social_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/social-link', $attributes, $content, $block);
}

function render_block_core_tag_cloud($attributes)
{
    return _minn_render_core_callback('core/tag-cloud', ...func_get_args());
}

function render_block_core_term_count($attributes, $content, $block)
{
    return _minn_render_core_callback('core/term-count', $attributes, $content, $block);
}

function render_block_core_term_description($attributes, $content, $block)
{
    return _minn_render_core_callback('core/term-description', $attributes, $content, $block);
}

function render_block_core_term_name($attributes, $content, $block)
{
    return _minn_render_core_callback('core/term-name', $attributes, $content, $block);
}

function render_block_core_term_template($attributes, $content, $block)
{
    return _minn_render_core_callback('core/term-template', $attributes, $content, $block);
}

function render_block_core_video($attributes, $content)
{
    return _minn_render_core_callback('core/video', ...func_get_args());
}

function render_block_core_widget_group($attributes, $content, $block)
{
    return _minn_render_core_callback('core/widget-group', $attributes, $content, $block);
}

// Registrations: every core block type is registered when the registry is first read.

function register_block_core_accordion()
{
    _minn_register_core_block('core/accordion');
}

function register_block_core_accordion_item()
{
    _minn_register_core_block('core/accordion-item');
}

function register_block_core_archives()
{
    _minn_register_core_block('core/archives');
}

function register_block_core_avatar()
{
    _minn_register_core_block('core/avatar');
}

function register_block_core_block()
{
    _minn_register_core_block('core/block');
}

function register_block_core_breadcrumbs()
{
    _minn_register_core_block('core/breadcrumbs');
}

function register_block_core_button()
{
    _minn_register_core_block('core/button');
}

function register_block_core_calendar()
{
    _minn_register_core_block('core/calendar');
}

function register_block_core_categories()
{
    _minn_register_core_block('core/categories');
}

function register_block_core_comment_author_name()
{
    _minn_register_core_block('core/comment-author-name');
}

function register_block_core_comment_content()
{
    _minn_register_core_block('core/comment-content');
}

function register_block_core_comment_date()
{
    _minn_register_core_block('core/comment-date');
}

function register_block_core_comment_edit_link()
{
    _minn_register_core_block('core/comment-edit-link');
}

function register_block_core_comment_reply_link()
{
    _minn_register_core_block('core/comment-reply-link');
}

function register_block_core_comment_template()
{
    _minn_register_core_block('core/comment-template');
}

function register_block_core_comments()
{
    _minn_register_core_block('core/comments');
}

function register_block_core_comments_pagination()
{
    _minn_register_core_block('core/comments-pagination');
}

function register_block_core_comments_pagination_next()
{
    _minn_register_core_block('core/comments-pagination-next');
}

function register_block_core_comments_pagination_numbers()
{
    _minn_register_core_block('core/comments-pagination-numbers');
}

function register_block_core_comments_pagination_previous()
{
    _minn_register_core_block('core/comments-pagination-previous');
}

function register_block_core_comments_title()
{
    _minn_register_core_block('core/comments-title');
}

function register_block_core_cover()
{
    _minn_register_core_block('core/cover');
}

function register_block_core_details()
{
    _minn_register_core_block('core/details');
}

function register_block_core_file()
{
    _minn_register_core_block('core/file');
}

function register_block_core_footnotes()
{
    _minn_register_core_block('core/footnotes');
}

function register_block_core_gallery()
{
    _minn_register_core_block('core/gallery');
}

function register_block_core_heading()
{
    _minn_register_core_block('core/heading');
}

function register_block_core_home_link()
{
    _minn_register_core_block('core/home-link');
}

function register_block_core_icon()
{
    _minn_register_core_block('core/icon');
}

function register_block_core_image()
{
    _minn_register_core_block('core/image');
}

function register_block_core_latest_comments()
{
    _minn_register_core_block('core/latest-comments');
}

function register_block_core_latest_posts()
{
    _minn_register_core_block('core/latest-posts');
}

function register_block_core_legacy_widget()
{
    _minn_register_core_block('core/legacy-widget');
}

function register_block_core_list()
{
    _minn_register_core_block('core/list');
}

function register_block_core_loginout()
{
    _minn_register_core_block('core/loginout');
}

function register_block_core_media_text()
{
    _minn_register_core_block('core/media-text');
}

function register_block_core_navigation()
{
    _minn_register_core_block('core/navigation');
}

function register_block_core_navigation_link()
{
    _minn_register_core_block('core/navigation-link');
}

function register_block_core_navigation_overlay_close()
{
    _minn_register_core_block('core/navigation-overlay-close');
}

function register_block_core_navigation_submenu()
{
    _minn_register_core_block('core/navigation-submenu');
}

function register_block_core_page_list()
{
    _minn_register_core_block('core/page-list');
}

function register_block_core_page_list_item()
{
    _minn_register_core_block('core/page-list-item');
}

function register_block_core_paragraph()
{
    _minn_register_core_block('core/paragraph');
}

function register_block_core_pattern()
{
    _minn_register_core_block('core/pattern');
}

function register_block_core_playlist()
{
    _minn_register_core_block('core/playlist');
}

function register_block_core_playlist_track()
{
    _minn_register_core_block('core/playlist-track');
}

function register_block_core_post_author()
{
    _minn_register_core_block('core/post-author');
}

function register_block_core_post_author_biography()
{
    _minn_register_core_block('core/post-author-biography');
}

function register_block_core_post_author_name()
{
    _minn_register_core_block('core/post-author-name');
}

function register_block_core_post_comments_count()
{
    _minn_register_core_block('core/post-comments-count');
}

function register_block_core_post_comments_form()
{
    _minn_register_core_block('core/post-comments-form');
}

function register_block_core_post_comments_link()
{
    _minn_register_core_block('core/post-comments-link');
}

function register_block_core_post_content()
{
    _minn_register_core_block('core/post-content');
}

function register_block_core_post_date()
{
    _minn_register_core_block('core/post-date');
}

function register_block_core_post_excerpt()
{
    _minn_register_core_block('core/post-excerpt');
}

function register_block_core_post_featured_image()
{
    _minn_register_core_block('core/post-featured-image');
}

function register_block_core_post_navigation_link()
{
    _minn_register_core_block('core/post-navigation-link');
}

function register_block_core_post_template()
{
    _minn_register_core_block('core/post-template');
}

function register_block_core_post_terms()
{
    _minn_register_core_block('core/post-terms');
}

function register_block_core_post_time_to_read()
{
    _minn_register_core_block('core/post-time-to-read');
}

function register_block_core_post_title()
{
    _minn_register_core_block('core/post-title');
}

function register_block_core_query()
{
    _minn_register_core_block('core/query');
}

function register_block_core_query_no_results()
{
    _minn_register_core_block('core/query-no-results');
}

function register_block_core_query_pagination()
{
    _minn_register_core_block('core/query-pagination');
}

function register_block_core_query_pagination_next()
{
    _minn_register_core_block('core/query-pagination-next');
}

function register_block_core_query_pagination_numbers()
{
    _minn_register_core_block('core/query-pagination-numbers');
}

function register_block_core_query_pagination_previous()
{
    _minn_register_core_block('core/query-pagination-previous');
}

function register_block_core_query_title()
{
    _minn_register_core_block('core/query-title');
}

function register_block_core_query_total()
{
    _minn_register_core_block('core/query-total');
}

function register_block_core_read_more()
{
    _minn_register_core_block('core/read-more');
}

function register_block_core_rss()
{
    _minn_register_core_block('core/rss');
}

function register_block_core_search()
{
    _minn_register_core_block('core/search');
}

function register_block_core_shortcode()
{
    _minn_register_core_block('core/shortcode');
}

function register_block_core_site_logo()
{
    _minn_register_core_block('core/site-logo');
}

function register_block_core_site_tagline()
{
    _minn_register_core_block('core/site-tagline');
}

function register_block_core_site_title()
{
    _minn_register_core_block('core/site-title');
}

function register_block_core_social_link()
{
    _minn_register_core_block('core/social-link');
}

function register_block_core_tab_list()
{
    _minn_register_core_block('core/tab-list');
}

function register_block_core_tab_panel()
{
    _minn_register_core_block('core/tab-panel');
}

function register_block_core_tabs()
{
    _minn_register_core_block('core/tabs');
}

function register_block_core_tag_cloud()
{
    _minn_register_core_block('core/tag-cloud');
}

function register_block_core_template_part()
{
    _minn_register_core_block('core/template-part');
}

function register_block_core_term_count()
{
    _minn_register_core_block('core/term-count');
}

function register_block_core_term_description()
{
    _minn_register_core_block('core/term-description');
}

function register_block_core_term_name()
{
    _minn_register_core_block('core/term-name');
}

function register_block_core_term_template()
{
    _minn_register_core_block('core/term-template');
}

function register_block_core_video()
{
    _minn_register_core_block('core/video');
}

function register_block_core_widget_group()
{
    _minn_register_core_block('core/widget-group');
}

/** The site logo setting, shown in REST under its own name. */
function register_block_core_site_logo_setting()
{
    register_setting('general', 'site_logo', ['show_in_rest' => ['name' => 'site_logo'], 'type' => 'integer', 'label' => __('Logo'), 'description' => __('Site logo.')]);
}

/** The site icon setting, shown in REST. */
function register_block_core_site_icon_setting()
{
    register_setting('general', 'site_icon', ['show_in_rest' => true, 'type' => 'integer', 'label' => __('Icon'), 'description' => __('Site icon.')]);
}

// The core blocks the facade renders, from the WordPress functions they are
// made of, registered with the engine's renderer when it is built
// (minn_block_renderers). Behaviour from contracts/fixtures/api/core-blocks.json.

/** @internal registers the facade's block renderers with the engine's renderer */
function _minn_register_block_renderers(MinnRenderer $renderer): void
{
    $renderers = [
        'core/categories' => '_minn_block_categories',
        'core/post-author' => '_minn_block_post_author',
        'core/post-author-biography' => '_minn_block_post_author_biography',
        'core/post-comments-count' => '_minn_block_post_comments_count',
        'core/post-comments-link' => '_minn_block_post_comments_link',
        'core/post-time-to-read' => '_minn_block_post_time_to_read',
        'core/read-more' => '_minn_block_read_more',
        'core/loginout' => '_minn_block_loginout',
        'core/shortcode' => static fn (MinnBlock $block): string => wpautop($block->innerHtml),
        'core/widget-group' => '_minn_block_widget_group',
        'core/calendar' => '_minn_block_calendar',
        'core/home-link' => '_minn_block_home_link',
    ];
    foreach ($renderers as $name => $render) {
        $renderer->registerDynamic($name, $render);
    }
}

/** @internal the categories as wp_list_categories lists them, or as a dropdown that goes to the one chosen; each block numbered */
function _minn_block_categories(MinnBlock $block, MinnRenderer $renderer): string
{
    static $instance = 0;
    $id = 'wp-block-categories-' . ++$instance;
    $taxonomy = get_taxonomy((string) $block->attr('taxonomy', 'category'));
    if (!$taxonomy) {
        return '';
    }
    $args = ['echo' => false, 'hierarchical' => (bool) $block->attr('showHierarchy', false), 'orderby' => 'name', 'show_count' => (bool) $block->attr('showPostCounts', false), 'taxonomy' => $taxonomy->name, 'title_li' => '', 'hide_empty' => !$block->attr('showEmpty', false)];
    if ($block->attr('showOnlyTopLevel', false)) {
        $args['parent'] = 0;
    }
    $classes = ['wp-block-categories-taxonomy-' . $taxonomy->name];
    if (!$block->attr('displayAsDropdown', false)) {
        return Wrapper::open('ul', 'wp-block-categories', $block, extraClasses: ['wp-block-categories-list', ...$classes]) . wp_list_categories($args) . '</ul>';
    }
    $label = (string) $block->attr('label', '') ?: $taxonomy->labels->name;
    $select = wp_dropdown_categories($args + ['id' => $id, 'name' => $taxonomy->query_var, 'value_field' => 'slug', 'show_option_none' => sprintf(__('Select %s'), $taxonomy->labels->singular_name)]);
    return Wrapper::open('div', 'wp-block-categories', $block, extraClasses: ['wp-block-categories-dropdown', ...$classes])
        . '<label class="wp-block-categories__label' . ($block->attr('showLabel', true) ? '' : ' screen-reader-text') . '" for="' . esc_attr($id) . '">' . $label . '</label>'
        . str_replace('</select>', '</select>' . build_dropdown_script_block_core_categories($id), $select) . '</div>';
}

/** The script that sends a categories dropdown to the archive of the one chosen (Escape leaves it). */
function build_dropdown_script_block_core_categories($dropdown_id)
{
    $script = <<<'JS'
( ( [ dropdownId, homeUrl ] ) => {
		const dropdown = document.getElementById( dropdownId );
		function onSelectChange() {
			setTimeout( () => {
				if ( 'escape' === dropdown.dataset.lastkey ) {
					return;
				}
				// Only navigate if a valid term is selected (not the default "Select [taxonomy]" option)
				if ( dropdown.value && dropdown.value !== '-1' && dropdown instanceof HTMLSelectElement ) {
					const url = new URL( homeUrl );
					url.searchParams.set( dropdown.name, dropdown.value );
					location.href = url.href;
				}
			}, 250 );
		}
		function onKeyUp( event ) {
			if ( 'Escape' === event.key ) {
				dropdown.dataset.lastkey = 'escape';
			} else {
				delete dropdown.dataset.lastkey;
			}
		}
		function onClick() {
			delete dropdown.dataset.lastkey;
		}
		dropdown.addEventListener( 'keyup', onKeyUp );
		dropdown.addEventListener( 'click', onClick );
		dropdown.addEventListener( 'change', onSelectChange );
	} )( 
JS;
    return wp_get_inline_script_tag($script . wp_json_encode([$dropdown_id, home_url()], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . " );\n//# sourceURL=" . __FUNCTION__);
}

/** @internal the post's author: their avatar, the byline, their name (linked to their posts when asked) and their bio when asked */
function _minn_block_post_author(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    $user = $post === null ? false : get_userdata($post->authorId);
    if (!$user) {
        return '';
    }
    $avatar = $block->attr('showAvatar', true) ? get_avatar($user->ID, (int) $block->attr('avatarSize', 48)) : '';
    $name = get_the_author_meta('display_name', $user->ID);
    if ($block->attr('isLink', false)) {
        $name = sprintf('<a href="%1$s" target="%2$s">%3$s</a>', get_author_posts_url($user->ID, $user->user_nicename), esc_attr((string) $block->attr('linkTarget', '_self')), $name);
    }
    $byline = (string) $block->attr('byline', '');
    $bio = $block->attr('showBio', false) ? (string) get_the_author_meta('user_description', $user->ID) : '';
    return Wrapper::open('div', 'wp-block-post-author', $block)
        . ($avatar ? '<div class="wp-block-post-author__avatar">' . $avatar . '</div>' : '')
        . '<div class="wp-block-post-author__content">'
        . ($byline !== '' ? '<p class="wp-block-post-author__byline">' . wp_kses_post($byline) . '</p>' : '')
        . '<p class="wp-block-post-author__name">' . $name . '</p>'
        . ($bio !== '' ? '<p class="wp-block-post-author__bio">' . $bio . '</p>' : '')
        . '</div></div>';
}

/** @internal the post author's bio; nothing when they have none */
function _minn_block_post_author_biography(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    $bio = $post === null ? '' : (string) get_the_author_meta('description', $post->authorId);
    return $bio === '' ? '' : Wrapper::open('div', 'wp-block-post-author-biography', $block) . $bio . '</div>';
}

/** @internal how many comments the post has */
function _minn_block_post_comments_count(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    return $post === null ? '' : Wrapper::open('div', 'wp-block-post-comments-count', $block) . get_comments_number($post->id) . '</div>';
}

/** @internal a link to the post's comments ("No comments" to its form while it takes some); nothing for a post with none that takes none */
function _minn_block_post_comments_link(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    $count = $post === null ? 0 : (int) get_comments_number($post->id);
    if ($post === null || ($count === 0 && !comments_open($post->id))) {
        return '';
    }
    $text = $count === 0 ? __('No comments') : sprintf(_n('%s comment', '%s comments', $count), number_format_i18n($count));
    $href = $count === 0 ? get_permalink($post->id) . '#respond' : get_comments_link($post->id);
    return Wrapper::open('div', 'wp-block-post-comments-link', $block) . '<a href=' . esc_url($href) . '>' . $text . '<span class="screen-reader-text"> on ' . get_the_title($post->id) . '</span></a></div>';
}

/** @internal how long the post takes to read (a range of minutes by default) at the block's reading speed, or how many words it has */
function _minn_block_post_time_to_read(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    if ($post === null) {
        return '';
    }
    $words = block_core_post_time_to_read_word_count((string) get_post_field('post_content', $post->id), 'words');
    $text = match ((string) $block->attr('displayMode', 'time')) {
        'time' => _minn_minutes_to_read($words / max(1, (int) $block->attr('averageReadingSpeed', 189)), (bool) $block->attr('displayAsRange', true)),
        'words' => sprintf(_n('%s word', '%s words', $words), number_format_i18n($words)),
        default => '',
    };
    return Wrapper::open('div', 'wp-block-post-time-to-read', $block) . $text . '</div>';
}

/**
 * How many words a text holds (or characters, without or with spaces) once
 * its HTML and comments are out: entities, connecting dashes and
 * punctuation (digits among it) do not count, a word being what stands
 * before whitespace (probe core-blocks).
 */
function block_core_post_time_to_read_word_count($text, $type)
{
    $text = (string) preg_replace(['/<\/?[a-z][^>]*?>/i', '/<!--[\s\S]*?-->/'], "\n", $text . "\n");
    $text = (string) preg_replace('/&nbsp;|&#160;/i', ' ', $text);
    if ($type === 'words') {
        $text = (string) preg_replace(['/&\S+?;/', '/--|\x{2014}/u', '/[\x{0021}-\x{0040}\x{005B}-\x{0060}\x{007B}-\x{007E}\x{0080}-\x{00BF}\x{00D7}\x{00F7}\x{2000}-\x{2BFF}\x{2E00}-\x{2E7F}]/u'], ['', ' ', ''], $text);
        return preg_match_all('/\S\s+/u', $text);
    }
    $text = (string) preg_replace('/&\S+?;/', 'a', $text);
    return preg_match_all($type === 'characters_excluding_spaces' ? '/\S/u' : '/[^\f\n\r\t\v\x{00AD}\x{2028}\x{2029}]/u', $text);
}

/** @internal minutes to read: at least one, or a range from four fifths to six fifths of it, the high end above the low */
function _minn_minutes_to_read(float $minutes, bool $range): string
{
    if (!$range) {
        $minutes = max(1, (int) round($minutes));
        return sprintf(_n('%s minute', '%s minutes', $minutes), $minutes);
    }
    $low = max(1, (int) round($minutes * 0.8));
    $high = max($low + 1, (int) round($minutes * 1.2));
    return sprintf(_x('%1$s–%2$s minutes', 'Range of minutes to read'), $low, $high);
}

/** @internal a link to the post, its title for screen readers */
function _minn_block_read_more(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    if ($post === null) {
        return '';
    }
    $content = (string) $block->attr('content', '');
    $open = Wrapper::open('a', 'wp-block-read-more', $block);
    return substr($open, 0, -1) . ' href="' . esc_url(get_permalink($post->id)) . '" target="' . esc_attr((string) $block->attr('linkTarget', '_self')) . '">'
        . ($content !== '' ? $content : __('Read more')) . '<span class="screen-reader-text">: ' . get_the_title($post->id) . '</span></a>';
}

/** @internal the log in (or log out) link, returning to this page unless told not to; the login form instead when asked */
function _minn_block_loginout(MinnBlock $block, MinnRenderer $renderer): string
{
    $current = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    $redirect = $block->attr('redirectToCurrent', true) ? $current : '';
    $in = is_user_logged_in();
    $html = $in || !$block->attr('displayLoginAsForm', false) ? wp_loginout($redirect, false) : wp_login_form(['echo' => false, 'redirect' => $redirect]);
    return Wrapper::open('div', 'wp-block-loginout', $block, extraClasses: [$in ? 'logged-in' : 'logged-out']) . $html . '</div>';
}

/** @internal the link to the front page, marked current there */
function _minn_block_home_link(MinnBlock $block, MinnRenderer $renderer): string
{
    $label = (string) $block->attr('label', '');
    return '<li class="wp-block-navigation-item wp-block-home-link"><a class="wp-block-home-link__content wp-block-navigation-item__content" href="' . esc_url(home_url()) . '" rel="home"' . (is_front_page() ? ' aria-current="page"' : '') . ' >' . ($label !== '' ? wp_kses_post($label) : __('Home')) . '</a></li>';
}

/** @internal a widget group: its title in the sidebar's heading markup (an h2 elsewhere), then its blocks */
function _minn_block_widget_group(MinnBlock $block, MinnRenderer $renderer): string
{
    global $wp_registered_sidebars, $_sidebar_being_rendered;
    $sidebar = $wp_registered_sidebars[$_sidebar_being_rendered ?? ''] ?? [];
    $title = (string) $block->attr('title', '');
    $inner = '';
    $index = 0;
    foreach ($block->innerContent as $chunk) {
        $inner .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
    }
    $heading = $title === '' ? '' : ($sidebar['before_title'] ?? '<h2 class="widget-title">') . esc_html($title) . ($sidebar['after_title'] ?? '</h2>');
    return $heading . '<div class="wp-widget-group__inner-blocks">' . $inner . '</div>';
}

/** Notes the sidebar being rendered, for the widget groups inside it (dynamic_sidebar_before). */
function note_sidebar_being_rendered($index)
{
    $GLOBALS['_sidebar_being_rendered'] = $index;
}

/** Forgets the sidebar once it is rendered (dynamic_sidebar_after). */
function discard_sidebar_being_rendered()
{
    unset($GLOBALS['_sidebar_being_rendered']);
}

/** @internal the calendar of the month being viewed (the block's month where the permalinks can link one) */
function _minn_block_calendar(MinnBlock $block, MinnRenderer $renderer): string
{
    global $monthnum, $year;
    $viewing = [$monthnum, $year];
    $structure = (string) get_option('permalink_structure');
    if (isset($block->attrs['month'], $block->attrs['year']) && str_contains($structure, '%monthnum%') && str_contains($structure, '%year%')) {
        [$monthnum, $year] = [$block->attrs['month'], $block->attrs['year']];
    }
    $calendar = get_calendar(['display' => false]);
    [$monthnum, $year] = $viewing;
    return Wrapper::open('div', 'wp-block-calendar', $block) . $calendar . '</div>';
}
