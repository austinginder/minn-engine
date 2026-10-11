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

/**
 * An accordion item (probe core-block-callbacks): a fresh accordion-item-{n}
 * id, the item wired to the accordion's state and its own context, the
 * heading's toggle controlling the panel, the panel labelled by the toggle.
 */
function block_core_accordion_item_render($attributes, $content)
{
    $id = wp_unique_id('accordion-item-');
    $open = empty($attributes['openByDefault']) ? 'false' : 'true';
    $tags = new WP_HTML_Tag_Processor((string) $content);
    _minn_set_attributes($tags, [], ['data-wp-class--is-open' => 'state.isOpen', 'data-wp-context' => '{ "id": "' . $id . '", "openByDefault": ' . $open . ' }', 'data-wp-init' => 'callbacks.initAccordionItems', 'data-wp-on-window--hashchange' => 'callbacks.hashChange']);
    _minn_set_attributes($tags, ['class_name' => 'wp-block-accordion-heading__toggle'], ['aria-controls' => $id . '-panel', 'data-wp-bind--aria-expanded' => 'state.isOpen', 'data-wp-on--click' => 'actions.toggle', 'id' => $id]);
    _minn_set_attributes($tags, ['class_name' => 'wp-block-accordion-panel'], ['aria-labelledby' => $id, 'data-wp-bind--hidden' => 'state.isHidden', 'data-wp-on--beforematch' => 'actions.handleBeforeMatch', 'id' => $id . '-panel']);
    return $tags->get_updated_html();
}

/** @internal the next tag matching a query given the attributes, in order; false when there is none */
function _minn_set_attributes(WP_HTML_Tag_Processor $tags, array $query, array $attributes): bool
{
    if (!$tags->next_tag($query === [] ? null : $query)) {
        return false;
    }
    foreach ($attributes as $name => $value) {
        $tags->set_attribute($name, $value);
    }
    return true;
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

/** A tab list (probe core-block-callbacks): its label, and each button wired to the panel it opens, from the tabs' list of panel ids. */
function block_core_tab_list_render_callback($attributes, $content, $block)
{
    return _minn_tab_list((array) $attributes, (string) $content, (array) ($block->context ?? []));
}

/** @internal a tab list's markup, from its tabs' list of panel ids */
function _minn_tab_list(array $attributes, string $content, array $context): string
{
    $tabs = (array) ($context['core/tabs-list'] ?? []);
    $tags = new WP_HTML_Tag_Processor($content);
    _minn_set_attributes($tags, [], ['aria-label' => (string) ($attributes['ariaLabel'] ?? '') ?: __('Tabbed content')]);
    for ($index = 0; $tags->next_tag('button'); $index++) {
        $panel = (string) ($tabs[$index] ?? 'tab-' . $index);
        foreach (['aria-controls' => $panel, 'data-wp-bind--aria-selected' => 'state.isActiveTab', 'data-wp-bind--tabindex' => 'state.tabIndexAttribute', 'data-wp-context' => wp_json_encode(['tabIndex' => $index]), 'data-wp-on--click' => 'actions.handleTabClick', 'data-wp-on--keydown' => 'actions.handleTabKeyDown', 'id' => 'tab__' . $panel] as $name => $value) {
            $tags->set_attribute($name, $value);
        }
    }
    return $tags->get_updated_html();
}

/** A tab panel (probe core-block-callbacks): {tabs id}-tab-{n} (tab-{n} outside tabs, its anchor when it has one), labelled by its tab, hidden unless active. */
function block_core_tab_panel_render($attributes, $content, $block)
{
    return _minn_tab_panel((array) $attributes, (string) $content, (array) ($block->context ?? []));
}

/** @internal a tab panel's markup: the panels of one tabs block are counted in order */
function _minn_tab_panel(array $attributes, string $content, array $context): string
{
    static $counted = [];
    $tabs = (string) ($context['core/tabs-id'] ?? '');
    $counted[$tabs] = isset($counted[$tabs]) ? $counted[$tabs] + 1 : 0;
    $id = (string) ($attributes['anchor'] ?? '') ?: ($tabs !== '' ? $tabs . '-' : '') . 'tab-' . $counted[$tabs];
    $tags = new WP_HTML_Tag_Processor($content);
    _minn_set_attributes($tags, [], ['aria-labelledby' => 'tab__' . $id, 'data-wp-bind--hidden' => '!state.isActiveTab', 'id' => $id]);
    return $tags->get_updated_html();
}

/**
 * The ids of a tabs block's tabs: one per panel in its first tab-panels
 * block, the panel's anchor when it has one, else "{tabs id}-tab-{n}" (just
 * "tab-{n}" without an id), counting the panels.
 */
function block_core_tabs_generate_tabs_list($innerblocks = [], $tabs_id = '')
{
    $named = static fn (string $name) => static fn ($block) => ($block['blockName'] ?? null) === $name;
    $container = current(array_filter((array) $innerblocks, $named('core/tab-panels')));
    $panels = array_values(array_filter($container['innerBlocks'] ?? [], $named('core/tab-panel')));
    $prefix = $tabs_id !== '' ? $tabs_id . '-' : '';
    return array_map(static fn ($panel, $index) => ($panel['attrs']['anchor'] ?? '') !== '' ? $panel['attrs']['anchor'] : $prefix . 'tab-' . $index, $panels, array_keys($panels));
}

/** A tabs block hands its inner blocks its id (its anchor as given, else a fresh tabs_{n}) and its tabs' ids (on render_block_context); other blocks' context passes. */
function block_core_tabs_provide_context($context, $parsed_block)
{
    if (($parsed_block['blockName'] ?? null) !== 'core/tabs') {
        return $context;
    }
    $id = $parsed_block['attrs']['anchor'] ?? wp_unique_id('tabs_');
    $context['core/tabs-list'] = block_core_tabs_generate_tabs_list($parsed_block['innerBlocks'] ?? [], $id);
    $context['core/tabs-id'] = $id;
    return $context;
}

/** A tabs block (probe core-block-callbacks): the interactive region with its id and active tab; the state holds its panels' ids. */
function block_core_tabs_render_block_callback($attributes, $content, $block)
{
    return _minn_tabs((array) $attributes, (string) $content, (array) ($block->context ?? []));
}

/** @internal a tabs block's markup, from the id and panel list its context carries (block_core_tabs_provide_context) */
function _minn_tabs(array $attributes, string $content, array $context): string
{
    $id = (string) ($context['core/tabs-id'] ?? '');
    $tags = new WP_HTML_Tag_Processor($content);
    _minn_set_attributes($tags, [], ['data-wp-context' => wp_json_encode(['tabsId' => $id, 'activeTabIndex' => (int) ($attributes['activeTabIndex'] ?? 0)]), 'data-wp-init' => 'callbacks.onTabsInit', 'data-wp-interactive' => 'core/tabs', 'data-wp-on--keydown' => 'actions.handleTabKeyDown']);
    wp_interactivity_state('core/tabs', [$id => array_values((array) ($context['core/tabs-list'] ?? []))]);
    wp_enqueue_script_module('@wordpress/block-library/tabs/view');
    return $tags->get_updated_html();
}

/** An accordion (probe core-block-callbacks): the interactive region with its autoclose setting; an item is open as its context says. */
function render_block_core_accordion($attributes, $content)
{
    $tags = new WP_HTML_Tag_Processor((string) $content);
    _minn_set_attributes($tags, [], ['data-wp-context' => '{ "autoclose": ' . (empty($attributes['autoclose']) ? 'false' : 'true') . ', "accordionItems": [] }', 'data-wp-interactive' => 'core/accordion']);
    wp_interactivity_state('core/accordion', ['isOpen' => static fn () => !empty(wp_interactivity_get_context()['openByDefault'])]);
    wp_enqueue_script_module('@wordpress/block-library/accordion/view');
    return $tags->get_updated_html();
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

/** A comment's edit link, for a reader who may edit it (probe core-block-callbacks): in the tab the block names, aligned as it says. */
function render_block_core_comment_edit_link($attributes, $content, $block)
{
    $comment = $block->context['commentId'] ?? null;
    $link = $comment === null ? '' : _minn_comment_edit_anchor((array) $attributes, (int) $comment);
    if ($link === '') {
        return '';
    }
    return '<div ' . get_block_wrapper_attributes(['class' => empty($attributes['textAlign']) ? '' : 'has-text-align-' . $attributes['textAlign']]) . '>' . $link . '</div>';
}

/** @internal a comment's edit link, or nothing for a reader who may not edit it */
function _minn_comment_edit_anchor(array $attributes, int $comment): string
{
    $url = current_user_can('edit_comment', $comment) ? get_edit_comment_link($comment) : null;
    return $url ? '<a href="' . esc_url($url) . '" target="' . esc_attr((string) ($attributes['linkTarget'] ?? '_self')) . '">' . esc_html__('Edit') . '</a>' : '';
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

/** The comment pagination (probe comments-pagination): its links in a labelled nav, nothing when none of them rendered. */
function render_block_core_comments_pagination($attributes, $content)
{
    return trim((string) $content) === '' ? '' : '<nav ' . get_block_wrapper_attributes() . ' aria-label="' . esc_attr__('Comments pagination') . '">' . $content . '</nav>';
}

/** The link to the newer page of comments, up to the post's last (probe comments-pagination). */
function render_block_core_comments_pagination_next($attributes, $content, $block)
{
    return _minn_comments_pagination_link('next', (array) $attributes, (array) $block->context, get_block_wrapper_attributes());
}

/** The numbered links over the post's comment pages (probe comments-pagination). */
function render_block_core_comments_pagination_numbers($attributes, $content, $block)
{
    return _minn_comments_pagination_numbers((array) $block->context, get_block_wrapper_attributes());
}

/** The link to the older page of comments (probe comments-pagination). */
function render_block_core_comments_pagination_previous($attributes, $content, $block)
{
    return _minn_comments_pagination_link('previous', (array) $attributes, (array) $block->context, get_block_wrapper_attributes());
}

/**
 * @internal An older or newer comments link from the comment query's page:
 * the block's label (Older or Newer Comments without one), the
 * pagination's arrow beside it, the block's wrapper attributes on the link;
 * the newer one stops at the post's last page of comments.
 */
function _minn_comments_pagination_link(string $which, array $attributes, array $context, string $wrapper): string
{
    $previous = $which === 'previous';
    $label = (string) ($attributes['label'] ?? '') ?: ($previous ? __('Older Comments') : __('Newer Comments'));
    $arrows = ['arrow' => ['←', '→'], 'chevron' => ['«', '»']];
    $kind = (string) ($context['comments/paginationArrow'] ?? 'none');
    if (isset($arrows[$kind])) {
        $span = "<span class='wp-block-comments-pagination-{$which}-arrow is-arrow-{$kind}' aria-hidden='true'>" . $arrows[$kind][$previous ? 0 : 1] . '</span>';
        $label = $previous ? $span . $label : $label . $span;
    }
    $hook = "{$which}_comments_link_attributes";
    $attributes_filter = static fn () => $wrapper;
    add_filter($hook, $attributes_filter);
    // The page is the comment query's own (the newest page when none is asked for and the newest comes first).
    $vars = build_comment_query_vars_from_block((object) ['context' => $context]);
    $page = $vars['paged'] ?? null;
    $link = $previous ? get_previous_comments_link($label, $page) : get_next_comments_link($label, (new WP_Comment_Query($vars))->max_num_pages, $page);
    remove_filter($hook, $attributes_filter);
    return (string) $link;
}

/** @internal the numbered comment page links in the block's wrapper, nothing without them */
function _minn_comments_pagination_numbers(array $context, string $wrapper): string
{
    $vars = build_comment_query_vars_from_block((object) ['context' => $context]);
    $links = paginate_comments_links(['total' => (new WP_Comment_Query($vars))->max_num_pages, 'current' => !empty($vars['paged']) ? $vars['paged'] : null, 'prev_next' => false, 'echo' => false]);
    return empty($links) ? '' : '<div ' . $wrapper . '>' . $links . '</div>';
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

/**
 * A post's footnotes (probe block-renderers): its footnotes meta as a list,
 * each note with a link back to where it is cited. Nothing for a post behind
 * a password, a post without notes, or no post.
 */
function render_block_core_footnotes($attributes, $content, $block)
{
    $items = _minn_footnote_items($block instanceof WP_Block ? (int) ($block->context['postId'] ?? 0) : 0);
    return $items === '' ? '' : '<ol ' . get_block_wrapper_attributes() . '>' . $items . '</ol>';
}

/** @internal a post's footnotes as list items, each linking back to its citation; '' for no post, a password, or no notes */
function _minn_footnote_items(int $post_id): string
{
    if ($post_id < 1 || get_post($post_id) === null || post_password_required($post_id)) {
        return '';
    }
    $notes = json_decode((string) get_post_meta($post_id, 'footnotes', true), true);
    $items = '';
    foreach (is_array($notes) ? array_values($notes) : [] as $i => $note) {
        $id = esc_attr((string) ($note['id'] ?? ''));
        $items .= '<li id="' . $id . '">' . ($note['content'] ?? '') . ' <a href="#' . $id . '-link" aria-label="' . esc_attr(sprintf(__('Jump to footnote reference %1$d'), $i + 1)) . '">↩︎</a></li>';
    }
    return $items;
}

function render_block_core_home_link($attributes, $content, $block)
{
    return _minn_render_core_callback('core/home-link', $attributes, $content, $block);
}

/** An icon from the registry in its wrapper; an unknown or missing icon gives nothing (probe icon-term-template). */
function render_block_core_icon($attributes)
{
    $svg = _minn_icon_svg((array) $attributes);
    return $svg === '' ? '' : '<div ' . get_block_wrapper_attributes() . '>' . $svg . '</div>';
}

/**
 * @internal An icon block's drawing. A first pass names it (its label, or
 * hidden from assistive technology) and gives it the colour and border
 * classes; a second flips it and styles it: the colours, border, width and
 * padding the block skips on its wrapper, then its rotation.
 */
function _minn_icon_svg(array $attributes): string
{
    $icon = WP_Icons_Registry::get_instance()->get_registered_icon((string) ($attributes['icon'] ?? ''));
    if (!is_array($icon) || (string) ($icon['content'] ?? '') === '') {
        return '';
    }
    $styles = wp_style_engine_get_styles(_minn_icon_style($attributes));
    $label = (string) ($attributes['ariaLabel'] ?? '');
    $tags = new WP_HTML_Tag_Processor((string) $icon['content']);
    _minn_set_attributes($tags, ['tag_name' => 'svg'], array_filter(['aria-label' => $label ?: null, 'role' => $label !== '' ? 'img' : null, 'aria-hidden' => $label === '' ? 'true' : null, 'focusable' => $label === '' ? 'false' : null, 'class' => ($styles['classnames'] ?? '') ?: null]));
    $tags = new WP_HTML_Tag_Processor($tags->get_updated_html());
    $tags->next_tag('svg');
    foreach (['flipHorizontal' => 'is-flip-horizontal', 'flipVertical' => 'is-flip-vertical'] as $flag => $class) {
        empty($attributes[$flag]) || $tags->add_class($class);
    }
    $style = implode(' ', array_filter([(string) ($styles['css'] ?? ''), empty($attributes['rotation']) ? '' : 'rotate: ' . $attributes['rotation'] . 'deg;']));
    $style === '' || $tags->set_attribute('style', $style);
    return $tags->get_updated_html();
}

/** @internal the styles an icon block puts on its drawing: preset colours as references, then its own colours, border, width and padding */
function _minn_icon_style(array $attributes): array
{
    $own = (array) ($attributes['style'] ?? []);
    $color = array_filter(['text' => empty($attributes['textColor']) ? ($own['color']['text'] ?? null) : 'var:preset|color|' . $attributes['textColor'], 'background' => empty($attributes['backgroundColor']) ? ($own['color']['background'] ?? null) : 'var:preset|color|' . $attributes['backgroundColor']]);
    $border = (array) ($own['border'] ?? []);
    if (!empty($attributes['borderColor'])) {
        $border['color'] = 'var:preset|color|' . $attributes['borderColor'];
    }
    return array_filter(['color' => $color, 'border' => $border, 'dimensions' => array_filter(['width' => $own['dimensions']['width'] ?? null]), 'spacing' => array_filter(['padding' => $own['spacing']['padding'] ?? null])]);
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

/**
 * A classic widget shown as a block (probe block-renderers): a registered
 * widget by id, or a widget type by its base with no settings or settings
 * encoded as the widget-types route hands them out (base64, signed with
 * wp_hash). A raw instance is not trusted; an unknown widget, a bad
 * signature, or neither gives nothing.
 */
function render_block_core_legacy_widget($attributes)
{
    if (isset($attributes['id'])) {
        return (string) wp_render_widget((string) $attributes['id'], (string) wp_find_widgets_sidebar((string) $attributes['id']));
    }
    $class = null;
    foreach (_minn_widget_factory()->widgets as $key => $widget) {
        if ($widget instanceof WP_Widget && $widget->id_base === (string) ($attributes['idBase'] ?? '')) {
            $class = $key;
            break;
        }
    }
    if ($class === null) {
        return '';
    }
    $instance = [];
    if (isset($attributes['instance']['encoded'], $attributes['instance']['hash'])) {
        $serialized = (string) base64_decode((string) $attributes['instance']['encoded'], true);
        if (!hash_equals(wp_hash($serialized), (string) $attributes['instance']['hash'])) {
            return '';
        }
        $instance = (array) unserialize($serialized, ['allowed_classes' => false]);
    }
    ob_start();
    the_widget($class, $instance);
    return (string) ob_get_clean();
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

/**
 * The next page's link (probe query-pagination): shown before the last page.
 * Following the main query, it reads the global page and gives null when not
 * shown; a block's own query reads query-{id}-page and gives ''.
 */
function render_block_core_query_pagination_next($attributes, $content, $block)
{
    if (!$block instanceof WP_Block) {
        return '';
    }
    $state = _minn_query_pagination_state($block);
    if ($state['page'] >= $state['pages']) {
        return $state['inherit'] ? null : '';
    }
    $url = $state['inherit'] ? get_next_posts_page_link($state['pages']) : add_query_arg($state['param'], $state['page'] + 1);
    return _minn_query_pagination_link($block, (string) $url, __('Next Page'), 'next');
}

/**
 * The page numbers (probe query-pagination): nothing for a single page or a
 * page past the end. A block's own query numbers its links ?query-{id}-page=N,
 * with the reference's cst argument once past the first page.
 */
function render_block_core_query_pagination_numbers($attributes, $content, $block)
{
    if (!$block instanceof WP_Block) {
        return '';
    }
    $state = _minn_query_pagination_state($block);
    if ($state['pages'] < 2 || $state['page'] > $state['pages']) {
        return '';
    }
    $args = ['current' => $state['page'], 'total' => $state['pages'], 'prev_next' => false];
    if (isset($attributes['midSize'])) {
        $args['mid_size'] = (int) $attributes['midSize'];
    }
    if (!$state['inherit']) {
        $args += ['base' => '%_%', 'format' => '?' . $state['param'] . '=%#%'];
        if ($state['page'] > 1) {
            $args['add_args'] = ['cst' => ''];
        }
    }
    $links = paginate_links($args);
    return $links ? '<div ' . get_block_wrapper_attributes() . '>' . $links . '</div>' : '';
}

/**
 * The previous page's link (probe query-pagination): following the main
 * query, shown past the first page (even past the last); a block's own query
 * shows it only between the second page and the last.
 */
function render_block_core_query_pagination_previous($attributes, $content, $block)
{
    if (!$block instanceof WP_Block) {
        return '';
    }
    $state = _minn_query_pagination_state($block);
    $shown = $state['page'] > 1 && ($state['inherit'] || $state['page'] <= $state['pages']);
    if (!$shown) {
        return $state['inherit'] ? null : '';
    }
    $url = $state['inherit'] ? get_previous_posts_page_link() : add_query_arg($state['param'], $state['page'] - 1);
    return _minn_query_pagination_link($block, (string) $url, __('Previous Page'), 'previous');
}

/**
 * @internal Where a pagination block's query stands: the page it is on, the
 * pages it has, whether it follows the main query, and the request parameter
 * a block's own query pages by (capped by its pages setting).
 */
function _minn_query_pagination_state(WP_Block $block): array
{
    $context = (array) $block->context;
    if (!empty($context['query']['inherit'])) {
        return ['inherit' => true, 'page' => max(1, (int) ($GLOBALS['paged'] ?? 0)), 'pages' => (int) ($GLOBALS['wp_query']->max_num_pages ?? 0), 'param' => ''];
    }
    $param = isset($context['queryId']) ? 'query-' . $context['queryId'] . '-page' : 'query-page';
    $page = empty($_GET[$param]) ? 1 : (int) $_GET[$param];
    $pages = (int) (new WP_Query(build_query_vars_from_query_block($block, $page)))->max_num_pages;
    $cap = (int) ($context['query']['pages'] ?? 0);
    return ['inherit' => false, 'page' => $page, 'pages' => $cap > 0 ? min($cap, $pages) : $pages, 'param' => $param];
}

/**
 * @internal A previous or next link: its label (escaped, or hidden behind an
 * aria-label when showLabel is off) and the arrow the pagination block chose,
 * before the label going back and after it going on.
 */
function _minn_query_pagination_link(WP_Block $block, string $url, string $default, string $direction): string
{
    $context = (array) $block->context;
    $label = esc_html((string) ($block->attributes['label'] ?? '') !== '' ? (string) $block->attributes['label'] : $default);
    $arrow = (string) ($context['paginationArrow'] ?? '');
    $glyphs = ['arrow' => ['next' => '→', 'previous' => '←'], 'chevron' => ['next' => '»', 'previous' => '«']];
    $span = isset($glyphs[$arrow]) ? "<span class='wp-block-query-pagination-{$direction}-arrow is-arrow-{$arrow}' aria-hidden='true'>{$glyphs[$arrow][$direction]}</span>" : '';
    $shown = ($context['showLabel'] ?? true) !== false;
    $text = $shown ? $label : '';
    return '<a href="' . esc_url($url) . '" ' . get_block_wrapper_attributes() . ($shown ? '' : ' aria-label="' . $label . '"') . '>' . ($direction === 'next' ? $text . $span : $span . $text) . '</a>';
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

/**
 * The site logo (probe block-renderers): get_custom_logo with the image set
 * to the block's width and its height in proportion; with no width, the
 * image keeps its size and the wrapper says is-default-size. Not linked, the
 * image stands alone; opening in a new tab, the link says so. Nothing
 * without a logo.
 */
function render_block_core_site_logo($attributes)
{
    $logo = _minn_site_logo_markup((array) $attributes);
    return $logo === '' ? '' : '<div ' . get_block_wrapper_attributes(empty($attributes['width']) ? ['class' => 'is-default-size'] : []) . '>' . $logo . '</div>';
}

/** @internal the site logo's image (linked or not) at the block's width, without the block's wrapper; '' without a logo */
function _minn_site_logo_markup(array $attributes): string
{
    $width = (int) ($attributes['width'] ?? 0);
    $resize = static function ($image) use ($width) {
        if (is_array($image) && $width > 0 && !empty($image[1]) && !empty($image[2])) {
            $image[2] = (int) round($image[2] * $width / $image[1]);
            $image[1] = $width;
        }
        return $image;
    };
    add_filter('wp_get_attachment_image_src', $resize);
    $logo = (string) get_custom_logo();
    remove_filter('wp_get_attachment_image_src', $resize);
    if ($logo === '') {
        return '';
    }
    if (empty($attributes['isLink'])) {
        $logo = (string) preg_replace('#<a\s[^>]*>|</a>#', '', $logo);
    } elseif (($attributes['linkTarget'] ?? '') === '_blank') {
        $logo = str_replace('<a href=', '<a aria-label="' . esc_attr__('(Home link, opens in a new tab)') . '" target="_blank" href=', $logo);
    }
    return $logo;
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

/**
 * The terms a terms query asks for, each a list item (term-{id}, its
 * taxonomy twice) holding the template's blocks with that term as their
 * context; nothing without the query or with no terms (probe
 * icon-term-template).
 */
function render_block_core_term_template($attributes, $content, $block)
{
    $query = $block instanceof WP_Block ? ($block->context['termQuery'] ?? null) : null;
    $items = '';
    foreach (is_array($query) ? _minn_term_template_terms($query) : [] as $term) {
        $inner = '';
        foreach ((array) $block->parsed_block['innerBlocks'] as $inner_block) {
            $inner .= (new WP_Block($inner_block, ['termId' => $term->term_id, 'taxonomy' => $term->taxonomy]))->render();
        }
        $items .= _minn_term_item($term, $inner);
    }
    return $items === '' ? '' : '<ul ' . get_block_wrapper_attributes() . '>' . $items . '</ul>';
}

/** @internal the terms for a terms query: top-level only unless nested, empty ones only when asked, an include list in its own order */
function _minn_term_template_terms(array $query): array
{
    $taxonomy = (string) ($query['taxonomy'] ?? 'category');
    $include = array_values(array_filter(array_map('intval', (array) ($query['include'] ?? []))));
    $args = ['taxonomy' => $taxonomy, 'number' => (int) ($query['perPage'] ?? 10), 'order' => (string) ($query['order'] ?? 'asc'), 'orderby' => $include === [] ? (string) ($query['orderBy'] ?? 'name') : 'include', 'hide_empty' => !empty($query['hideEmpty']), 'include' => $include];
    if (empty($query['showNested']) && is_taxonomy_hierarchical($taxonomy)) {
        $args['parent'] = 0;
    }
    $terms = get_terms($args);
    return is_array($terms) ? array_values(array_filter($terms, static fn ($term) => $term instanceof WP_Term)) : [];
}

/** @internal one term's list item */
function _minn_term_item(WP_Term $term, string $inner): string
{
    return '<li class="' . esc_attr("wp-block-term term-{$term->term_id} {$term->taxonomy} taxonomy-{$term->taxonomy}") . '">' . $inner . '</li>';
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
// The site logo and the custom logo kept in step (probe site-logo).

/** The custom logo theme mod read as the site_logo option has it, when that option is set. */
function _override_custom_logo_theme_mod($custom_logo)
{
    $site_logo = get_option('site_logo');
    return $site_logo === false ? $custom_logo : $site_logo;
}

/** A custom logo set as a theme mod is kept in the site_logo option too; an empty one deletes the option. */
function _sync_custom_logo_to_site_logo($value)
{
    if (empty($value)) {
        delete_option('site_logo');
    } else {
        update_option('site_logo', $value);
    }
    return $value;
}

/** When the theme mods are updated without a custom logo they had, the site_logo option goes too. */
function _delete_site_logo_on_remove_custom_logo($old_value, $value)
{
    if (isset($old_value['custom_logo']) && !isset($value['custom_logo'])) {
        delete_option('site_logo');
    }
}

/** When the theme mods are deleted, the site_logo option goes too, where the theme supports custom logos. */
function _delete_site_logo_on_remove_theme_mods()
{
    if (get_theme_support('custom-logo') !== false) {
        delete_option('site_logo');
    }
}

/** The two hooks above, on the active theme's theme mods option (at setup_theme). */
function _delete_site_logo_on_remove_custom_logo_on_setup_theme()
{
    $theme = get_option('stylesheet');
    add_action("update_option_theme_mods_{$theme}", '_delete_site_logo_on_remove_custom_logo', 10, 2);
    add_action("delete_option_theme_mods_{$theme}", '_delete_site_logo_on_remove_theme_mods');
}

/** Deleting the site_logo option removes the custom logo theme mod, holding back the hook that would delete the option in turn. */
function _delete_custom_logo_on_remove_site_logo()
{
    $hook = 'update_option_theme_mods_' . get_option('stylesheet');
    $priority = has_action($hook, '_delete_site_logo_on_remove_custom_logo');
    remove_action($hook, '_delete_site_logo_on_remove_custom_logo', $priority === false ? 10 : $priority);
    remove_theme_mod('custom_logo');
    if ($priority !== false) {
        add_action($hook, '_delete_site_logo_on_remove_custom_logo', $priority, 2);
    }
}

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
        'core/breadcrumbs' => '_minn_block_breadcrumbs',
        'core/site-logo' => '_minn_block_site_logo',
        'core/footnotes' => '_minn_block_footnotes',
        'core/legacy-widget' => static fn (MinnBlock $block): string => (string) render_block_core_legacy_widget($block->attrs),
        'core/accordion' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_laid_out($block, render_block_core_accordion($block->attrs, _minn_inner_html($block, $renderer))),
        'core/accordion-item' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_laid_out($block, block_core_accordion_item_render($block->attrs, _minn_inner_html($block, $renderer))),
        'core/tabs' => '_minn_block_tabs',
        'core/comments-pagination' => '_minn_block_comments_pagination',
        'core/comments-pagination-previous' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_comments_pagination_link('previous', $block->attrs, _minn_comment_page_context($renderer), _minn_wrapper_attributes('a', 'wp-block-comments-pagination-previous', $block)),
        'core/comments-pagination-next' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_comments_pagination_link('next', $block->attrs, _minn_comment_page_context($renderer), _minn_wrapper_attributes('a', 'wp-block-comments-pagination-next', $block)),
        'core/comments-pagination-numbers' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_comments_pagination_numbers(_minn_comment_page_context($renderer), _minn_wrapper_attributes('div', 'wp-block-comments-pagination-numbers', $block)),
        'core/comment-edit-link' => static function (MinnBlock $block, MinnRenderer $renderer): string {
            $comment = $renderer->context()->comment();
            $link = $comment === null ? '' : _minn_comment_edit_anchor($block->attrs, (int) $comment->id);
            $align = (string) $block->attr('textAlign', '');
            return $link === '' ? '' : Wrapper::open('div', 'wp-block-comment-edit-link', $block, extraClasses: $align === '' ? [] : ['has-text-align-' . $align]) . $link . '</div>';
        },
        'core/tab-list' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_laid_out($block, _minn_tab_list($block->attrs, _minn_inner_html($block, $renderer), $renderer->blockContext())),
        'core/tab-panel' => static fn (MinnBlock $block, MinnRenderer $renderer): string => _minn_laid_out($block, _minn_tab_panel($block->attrs, _minn_inner_html($block, $renderer), $renderer->blockContext())),
        'core/icon' => '_minn_block_icon',
        'core/terms-query' => '_minn_block_terms_query',
        'core/term-template' => '_minn_block_term_template',
    ];
    foreach ($renderers as $name => $render) {
        $renderer->registerDynamic($name, $render);
    }
}

/** @internal a block's inner blocks rendered into its saved markup */
function _minn_inner_html(MinnBlock $block, MinnRenderer $renderer): string
{
    $html = '';
    $index = 0;
    foreach ($block->innerContent as $chunk) {
        $html .= $chunk ?? $renderer->renderBlock($block->innerBlocks[$index++]);
    }
    return $html;
}

/** @internal a natively rendered block's layout classes on its wrapper */
function _minn_laid_out(MinnBlock $block, string $html): string
{
    return Minn\Html::addClasses($html, Minn\Blocks\Layout::classes((string) $block->name, $block->attrs));
}

/** @internal the comment pagination in a page: its arrow provided to its links, a labelled nav around what they render */
function _minn_block_comments_pagination(MinnBlock $block, MinnRenderer $renderer): string
{
    $inner = $renderer->providing(['comments/paginationArrow' => (string) $block->attr('paginationArrow', 'none')], static fn (): string => _minn_inner_html($block, $renderer));
    if (trim($inner) === '') {
        return '';
    }
    return _minn_laid_out($block, substr(Wrapper::open('nav', 'wp-block-comments-pagination', $block), 0, -1) . ' aria-label="' . esc_attr__('Comments pagination') . '">' . $inner . '</nav>');
}

/** @internal what a comment pagination link reads in a page: the post shown, and the arrow its pagination provides */
function _minn_comment_page_context(MinnRenderer $renderer): array
{
    $post = $renderer->context()->post();
    return ($post === null ? [] : ['postId' => $post->id]) + $renderer->blockContext();
}

/** @internal a block's wrapper attributes as markup, from the engine's wrapper (Wrapper::open without its tag) */
function _minn_wrapper_attributes(string $tag, string $blockClass, MinnBlock $block): string
{
    return trim(substr(Wrapper::open($tag, $blockClass, $block), strlen($tag) + 1, -1));
}

/** @internal an icon in a page: rendered as the reference renders it, its wrapper from the block supports (the ones it skips stay off) */
function _minn_block_icon(MinnBlock $block): string
{
    $parent = WP_Block_Supports::$block_to_render;
    WP_Block_Supports::$block_to_render = ['blockName' => 'core/icon', 'attrs' => $block->attrs];
    try {
        return render_block_core_icon($block->attrs);
    } finally {
        WP_Block_Supports::$block_to_render = $parent;
    }
}

/** @internal a terms query in a page: its query, with the block type's defaults, provided to the blocks inside */
function _minn_block_terms_query(MinnBlock $block, MinnRenderer $renderer): string
{
    $type = WP_Block_Type_Registry::get_instance()->get_registered('core/terms-query');
    $query = (array) $block->attr('termQuery', $type->attributes['termQuery']['default'] ?? []);
    return _minn_laid_out($block, $renderer->providing(['termQuery' => $query], static fn (): string => _minn_inner_html($block, $renderer)));
}

/** @internal a term template in a page: each term's items with the term provided to the blocks inside */
function _minn_block_term_template(MinnBlock $block, MinnRenderer $renderer): string
{
    $query = $renderer->blockContext()['termQuery'] ?? null;
    $items = '';
    foreach (is_array($query) ? _minn_term_template_terms($query) : [] as $term) {
        $items .= _minn_term_item($term, $renderer->providing(['termId' => $term->term_id, 'taxonomy' => $term->taxonomy], static fn (): string => _minn_inner_html($block, $renderer)));
    }
    return $items === '' ? '' : _minn_laid_out($block, Wrapper::open('ul', 'wp-block-term-template', $block) . $items . '</ul>');
}

/** @internal a tabs block in a page: its id and panel list provided to the blocks inside, as render_block_context gives them on the reference */
function _minn_block_tabs(MinnBlock $block, MinnRenderer $renderer): string
{
    $context = block_core_tabs_provide_context([], ['blockName' => 'core/tabs', 'attrs' => $block->attrs, 'innerBlocks' => array_map('_minn_block_to_array', $block->innerBlocks)]);
    $inner = $renderer->providing($context, static fn (): string => _minn_inner_html($block, $renderer));
    return _minn_laid_out($block, _minn_tabs($block->attrs, $inner, $context));
}

/**
 * @internal The site logo in a page (probe block-renderers-rendered): linked
 * unless the block says not; its wrapper carries is-default-size without a
 * width, the alignment and the custom class, then the block's class, and no
 * style.
 */
function _minn_block_site_logo(MinnBlock $block, MinnRenderer $renderer): string
{
    // A page renders the block with its type's defaults: linked, in the same tab.
    $logo = _minn_site_logo_markup($block->attrs + ['isLink' => true, 'linkTarget' => '_self']);
    $classes = array_filter([empty($block->attrs['width']) ? 'is-default-size' : '', empty($block->attrs['align']) ? '' : 'align' . $block->attrs['align'], $block->className(), 'wp-block-site-logo']);
    return $logo === '' ? '' : '<div class="' . esc_attr(implode(' ', $classes)) . '">' . $logo . '</div>';
}

/** @internal the footnotes of the post a page is showing, its style before its class (probe block-renderers-rendered) */
function _minn_block_footnotes(MinnBlock $block, MinnRenderer $renderer): string
{
    $post = $renderer->context()->post();
    $items = $post === null ? '' : _minn_footnote_items((int) $post->id);
    return $items === '' ? '' : Wrapper::open('ol', 'wp-block-footnotes', $block, styleFirst: true) . $items . '</ol>';
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
/** The reference leaves the sidebar being rendered in place: discarding it changes nothing a caller can see (probe core-block-helpers). */
function discard_sidebar_being_rendered()
{
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

// The breadcrumbs block's helpers (probe breadcrumbs).

/** A crumb: its label, and on a paged listing the address of its first page. */
function block_core_breadcrumbs_create_item($text, $is_paged = false)
{
    $item = ['label' => $text];
    if ($is_paged) {
        $item['url'] = get_pagenum_link(1);
    }
    return $item;
}

/** The crumb naming the page a listing is on. */
function block_core_breadcrumbs_create_page_number_item($query_var = 'paged')
{
    return ['label' => sprintf(__('Page %s'), (int) get_query_var($query_var))];
}

/** Whether the listing is past its first page. */
function block_core_breadcrumbs_is_paged()
{
    return (int) get_query_var('paged') > 1;
}

/** A post's title for a crumb, (no title) when it has none. */
function block_core_breadcrumbs_get_post_title($post_id_or_object)
{
    $title = get_the_title($post_id_or_object);
    return $title !== '' ? $title : __('(no title)');
}

/** The crumbs for a hierarchical post's ancestors, the root first, their titles kept as markup. */
function block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($post_id)
{
    return array_map(static fn ($ancestor) => ['label' => block_core_breadcrumbs_get_post_title($ancestor), 'url' => get_permalink($ancestor), 'allow_html' => true], array_reverse(get_post_ancestors($post_id)));
}

/** The crumbs for a term's ancestors, the root first. */
function block_core_breadcrumbs_get_term_ancestors_items($term_id, $taxonomy)
{
    $items = [];
    foreach (array_reverse(get_ancestors($term_id, $taxonomy, 'taxonomy')) as $ancestor) {
        $term = get_term($ancestor, $taxonomy);
        if ($term instanceof WP_Term) {
            $items[] = ['label' => $term->name, 'url' => get_term_link($term)];
        }
    }
    return $items;
}

/**
 * A post's trail through one of its terms: the taxonomy
 * block_core_breadcrumbs_post_type_settings names when it is usable
 * (viewable, shown in REST, the type's own) and holds the post's terms,
 * else the first usable one that does; the term it names there, else the
 * first by name; its ancestors, then itself.
 */
function block_core_breadcrumbs_get_terms_breadcrumbs($post_id, $post_type)
{
    $settings = apply_filters('block_core_breadcrumbs_post_type_settings', [], $post_type, $post_id);
    $settings = is_array($settings) ? $settings : [];
    $usable = static fn ($taxonomy) => is_string($taxonomy) && is_object_in_taxonomy($post_type, $taxonomy) && is_taxonomy_viewable($taxonomy) && !empty(get_taxonomy($taxonomy)->show_in_rest);
    $terms = [];
    foreach (array_unique([...(isset($settings['taxonomy']) ? [$settings['taxonomy']] : []), ...get_object_taxonomies($post_type)]) as $taxonomy) {
        $found = $usable($taxonomy) ? get_the_terms($post_id, $taxonomy) : false;
        if (is_array($found) && $found !== []) {
            $terms = $found;
            break;
        }
    }
    if ($terms === []) {
        return [];
    }
    $chosen = array_values(array_filter($terms, static fn ($term) => isset($settings['term']) && $term->slug === $settings['term']))[0] ?? $terms[0];
    return [...block_core_breadcrumbs_get_term_ancestors_items($chosen->term_id, $chosen->taxonomy), ['label' => $chosen->name, 'url' => get_term_link($chosen)]];
}

/** The crumbs an archive's main query gives: its date parts, its author, its term with the term's ancestors, or its post type; the last one linked to its first page when paged, and the page after it. */
function block_core_breadcrumbs_get_archive_breadcrumbs()
{
    $paged = block_core_breadcrumbs_is_paged();
    $items = match (true) {
        is_date() => _minn_breadcrumbs_date_items($paged),
        is_author() => [block_core_breadcrumbs_create_item(get_queried_object()?->display_name ?? '', $paged)],
        is_category() || is_tag() || is_tax() => _minn_breadcrumbs_term_items($paged),
        is_post_type_archive() => [block_core_breadcrumbs_create_item(post_type_archive_title('', false), $paged)],
        default => [],
    };
    if ($items !== [] && $paged) {
        $items[] = block_core_breadcrumbs_create_page_number_item();
    }
    return $items;
}

/** @internal a date archive's crumbs: the year, the month's name, the day, each linked but the last */
function _minn_breadcrumbs_date_items(bool $paged): array
{
    $m = (string) get_query_var('m');
    [$year, $month, $day] = $m !== '' ? [substr($m, 0, 4), (int) substr($m, 4, 2), (int) substr($m, 6, 2)] : [get_query_var('year'), (int) get_query_var('monthnum'), (int) get_query_var('day')];
    $parts = [[$year, get_year_link($year)]];
    if ($month > 0) {
        $parts[] = [$GLOBALS['wp_locale']->get_month($month), get_month_link($year, $month)];
        if ($day > 0) {
            $parts[] = [$day, get_day_link($year, $month, $day)];
        }
    }
    $last = array_pop($parts);
    return [...array_map(static fn ($part) => ['label' => $part[0], 'url' => $part[1]], $parts), block_core_breadcrumbs_create_item($last[0], $paged)];
}

/** @internal a term archive's crumbs: the term's ancestors, then the term */
function _minn_breadcrumbs_term_items(bool $paged): array
{
    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return [];
    }
    return [...block_core_breadcrumbs_get_term_ancestors_items($term->term_id, $term->taxonomy), block_core_breadcrumbs_create_item($term->name, $paged)];
}

/**
 * The breadcrumbs block (probe breadcrumbs): nothing on the home page or a
 * 404; otherwise a nav whose list runs from Home (unless left out) through
 * the trail (a search's query, a singular post's ancestors or terms then
 * its title, or an archive's crumbs) to the current item, which is
 * dropped when it is not to show. The separator rides on a custom property.
 */
function _minn_block_breadcrumbs(MinnBlock $block, MinnRenderer $renderer): string
{
    $items = is_front_page() || is_home() || is_404() ? [] : _minn_breadcrumbs_trail($block);
    if ($items === []) {
        return '';
    }
    if ($block->attr('showHomeItem', true)) {
        array_unshift($items, ['label' => __('Home'), 'url' => home_url('/')]);
    }
    $current = array_pop($items);
    $html = '';
    foreach ($items as $item) {
        $label = !empty($item['allow_html']) ? (string) $item['label'] : esc_html((string) $item['label']);
        $html .= isset($item['url']) ? '<li><a href="' . esc_url($item['url']) . '">' . $label . '</a></li>' : '<li><span>' . $label . '</span></li>';
    }
    if ($block->attr('showCurrentItem', true)) {
        $html .= '<li><span aria-current="page">' . (!empty($current['allow_html']) ? (string) $current['label'] : esc_html((string) $current['label'])) . '</span></li>';
    }
    if ($html === '') {
        return '';
    }
    $saved = WP_Block_Supports::$block_to_render;
    WP_Block_Supports::$block_to_render = ['blockName' => 'core/breadcrumbs', 'attrs' => $block->attrs];
    // Quotes and backslashes in the separator are escaped, so safecss refuses a separator that carries them.
    $wrapper = get_block_wrapper_attributes(['style' => '--separator: "' . addcslashes((string) $block->attr('separator', '/'), '\\"') . '"', 'aria-label' => __('Breadcrumbs')]);
    WP_Block_Supports::$block_to_render = $saved;
    return '<nav ' . $wrapper . '><ol>' . $html . '</ol></nav>';
}

/** @internal the trail after Home: a search's query, a singular post's ancestors or terms and title, or an archive's crumbs */
function _minn_breadcrumbs_trail(MinnBlock $block): array
{
    if (is_search()) {
        return [['label' => sprintf(__('Search results for: "%s"'), get_search_query(false))]];
    }
    if (!is_singular()) {
        return block_core_breadcrumbs_get_archive_breadcrumbs();
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post) {
        return [];
    }
    // A hierarchical post follows its ancestors, unless it prefers its terms and has some to follow.
    $terms = !is_post_type_hierarchical($post->post_type) || $block->attr('prefersTaxonomy', false) ? block_core_breadcrumbs_get_terms_breadcrumbs($post->ID, $post->post_type) : [];
    $trail = $terms === [] && is_post_type_hierarchical($post->post_type) ? block_core_breadcrumbs_get_hierarchical_post_type_breadcrumbs($post->ID) : $terms;
    return [...$trail, ['label' => block_core_breadcrumbs_get_post_title($post), 'allow_html' => true]];
}
