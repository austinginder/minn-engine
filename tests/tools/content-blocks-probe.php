<?php
/**
 * The content blocks' helpers (probe content-blocks): the search block's
 * classes and styles, social link icons and colours, the gallery's data,
 * context, gaps and dynamic images, the image lightbox, details and
 * paragraph filters, the avatar's border and the featured image's overlay,
 * latest posts and comments, the post excerpt's length, post terms
 * variations, the query's pagination, the archives dropdown script, comment
 * templates and the comment forms. Attachment 607 ("Battery image") and
 * post 1 are the fixtures' own. Same protocol as api-probe.php.
 */

$log = [];
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    // The archives dropdown and comment forms number their elements and nonces per request.
    // A gallery's id is a uniqid.
    return is_string($value) ? (string) preg_replace(['/(wp-block-archives-)\d+/', '/(_wpnonce[^"]*" value=")[0-9a-f]{10}/', '/^[0-9a-f]{13}$/', '/(&quot;imageId&quot;:&quot;|data-wp-key=")[0-9a-f]{13}/'], ['$1{n}', '$1{nonce}', '{uniqid}', '$1{uniqid}'], $value) : $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
$try = static function (callable $run) {
    try {
        return $run();
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!did_action('init')) {
    do_action('init');
}
$block = static fn (string $name, array $attrs = [], array $inner = [], string $html = '') => ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => $inner, 'innerHTML' => $html, 'innerContent' => $html === '' ? array_fill(0, count($inner), null) : [$html]];

// Search.
$searches = [
    [],
    ['buttonPosition' => 'button-inside', 'buttonUseIcon' => true, 'showLabel' => false],
    ['buttonPosition' => 'no-button'],
    ['buttonPosition' => 'button-only', 'isSearchFieldHidden' => true],
    ['buttonPosition' => 'button-outside', 'width' => 50, 'widthUnit' => '%', 'textColor' => 'accent', 'backgroundColor' => 'base', 'gradient' => 'sunset', 'borderColor' => 'contrast', 'fontSize' => 'large', 'fontFamily' => 'mono'],
    ['buttonPosition' => 'button-inside', 'width' => 300, 'widthUnit' => 'px', 'style' => ['border' => ['radius' => '8px', 'width' => '2px', 'color' => '#f00', 'style' => 'dashed'], 'color' => ['text' => '#111', 'background' => '#eee', 'gradient' => 'linear-gradient(red,blue)'], 'typography' => ['fontSize' => '20px', 'lineHeight' => '1.5', 'fontWeight' => '700', 'fontStyle' => 'italic', 'textTransform' => 'uppercase', 'textDecoration' => 'underline', 'letterSpacing' => '1px', 'fontFamily' => 'serif']]],
    ['buttonPosition' => 'button-outside', 'style' => ['border' => ['radius' => ['topLeft' => '1px', 'topRight' => '2px', 'bottomLeft' => '3px', 'bottomRight' => '4px'], 'top' => ['width' => '1px', 'color' => '#000', 'style' => 'solid'], 'left' => ['color' => 'var:preset|color|accent']]]],
    ['buttonPosition' => 'button-inside', 'style' => ['border' => ['radius' => ['topLeft' => '1px', 'bottomRight' => '4px'], 'right' => ['width' => '3px']]]],
];
$say('classnames_for_block_core_search', array_map(static fn ($a) => $try(static fn () => classnames_for_block_core_search($a)), $searches));
$say('styles_for_block_core_search', array_map(static fn ($a) => $try(static fn () => styles_for_block_core_search($a)), $searches));
$say('search color, border and typography', array_map(static fn ($a) => [
    $try(static fn () => get_color_classes_for_block_core_search($a)),
    $try(static fn () => get_border_color_classes_for_block_core_search($a)),
    $try(static fn () => get_typography_classes_for_block_core_search($a)),
    $try(static fn () => get_typography_styles_for_block_core_search($a)),
], $searches));
$say('apply_block_core_search_border_styles', array_map(static function (array $a) use ($try) {
    return $try(static function () use ($a) {
        $out = [];
        foreach (['width', 'color', 'style', 'radius'] as $property) {
            $wrapper = $button = $input = [];
            apply_block_core_search_border_styles($a, $property, $wrapper, $button, $input);
            $out[$property] = [$wrapper, $button, $input];
        }
        $wrapper = $button = $input = [];
        apply_block_core_search_border_style($a, 'width', 'top', $wrapper, $button, $input);
        $out['top width'] = [$wrapper, $button, $input];
        return $out;
    });
}, array_slice($searches, 4)));

// Social links.
$say('block_core_social_link_get_icon', array_map(static fn ($s) => $try(static fn () => md5((string) block_core_social_link_get_icon($s)) . ' ' . strlen((string) block_core_social_link_get_icon($s))), ['wordpress', 'github', 'mastodon', 'nonexistent', '']));
$say('block_core_social_link_get_icon whole', $try(static fn () => block_core_social_link_get_icon('chain')));
$say('social link colours', array_map(static fn ($c) => [$try(static fn () => block_core_social_link_get_color_classes($c)), $try(static fn () => block_core_social_link_get_color_styles($c))], [[], ['iconColor' => 'accent', 'iconBackgroundColor' => 'base'], ['iconColorValue' => '#111', 'iconBackgroundColorValue' => '#eee'], ['iconColor' => 'accent', 'iconColorValue' => '#111', 'iconBackgroundColor' => 'base', 'iconBackgroundColorValue' => '#eee']]));

// Gallery.
$say('block_core_gallery_data_id_backcompatibility', array_map(static fn ($b) => $try(static fn () => block_core_gallery_data_id_backcompatibility($b)), [
    $block('core/gallery', ['ids' => [607]], [$block('core/image', ['id' => 607], [], '<figure class="wp-block-image"><img src="x.png" class="wp-image-607"/></figure>')]),
    $block('core/gallery', [], [$block('core/image', ['id' => 607], [], '<figure class="wp-block-image"><img src="x.png" class="wp-image-607"/></figure>')]),
    $block('core/gallery', ['ids' => [607]], []),
    $block('core/image', ['id' => 607]),
]));
$say('block_core_gallery_get_column_gap_value', array_map(static fn ($c) => $try(static fn () => block_core_gallery_get_column_gap_value(...$c)), [['1rem', '0.5em'], [['top' => '1rem', 'left' => '2rem'], '0.5em'], [['top' => '1rem'], '0.5em'], ['var:preset|spacing|30', '0.5em'], [null, '0.5em'], ['', '1em'], [['left' => 'var:preset|spacing|20'], '0.5em'], ['1rem;color:red', '0.5em']]));
$say('block_core_gallery_render_context', array_map(static fn ($c) => $try(static fn () => block_core_gallery_render_context(...$c)), [
    [['postId' => 1], $block('core/gallery', ['linkTo' => 'media', 'sizeSlug' => 'medium'])],
    [['postId' => 1], $block('core/image', ['linkTo' => 'media'])],
    [[], $block('core/gallery', [])],
]));
$say('block_core_gallery_dynamic_image_link_attributes', array_map(static fn ($a) => $try(static fn () => block_core_gallery_dynamic_image_link_attributes(607, $a)), [[], ['linkTo' => 'media'], ['linkTo' => 'attachment'], ['linkTo' => 'none'], ['linkTo' => 'media', 'linkTarget' => '_blank'], ['linkTo' => 'custom']]));
$say('block_core_gallery_render_dynamic_image', array_map(static fn ($c) => $try(static fn () => block_core_gallery_render_dynamic_image(...$c)), [
    [607, [], []],
    [607, ['linkTo' => 'media', 'sizeSlug' => 'thumbnail'], ['postId' => 1]],
    [607, ['sizeSlug' => 'large', 'linkTo' => 'attachment'], []],
    [999999999, [], []],
]));
$say('block_core_gallery_resolve_dynamic_source', array_map(static fn ($c) => $try(static fn () => block_core_gallery_resolve_dynamic_source($c[0], new WP_Block($block('core/gallery', $c[1]), $c[2]))), [
    [[], [], []],
    [['type' => 'attached'], [], ['postId' => 1]],
    [['type' => 'ids', 'ids' => [607]], [], []],
    ['nonsense', [], []],
]));

// Image lightbox.
$say('block_core_image_get_lightbox_settings', array_map(static fn ($a) => $try(static fn () => block_core_image_get_lightbox_settings($block('core/image', $a))), [[], ['lightbox' => ['enabled' => true]], ['lightbox' => ['enabled' => false]], ['linkDestination' => 'media', 'lightbox' => ['enabled' => true]]]));
$say('block_core_image_render_lightbox', array_map(static fn ($a) => $try(static fn () => block_core_image_render_lightbox('<figure class="wp-block-image size-large"><img src="https://x.example/a.jpg" alt="An image" class="wp-image-607" width="200" height="100"/><figcaption class="wp-element-caption">Cap</figcaption></figure>', $block('core/image', $a), new WP_Block($block('core/image', $a)))), [['id' => 607], ['id' => 607, 'lightbox' => ['enabled' => true]], ['id' => 607, 'lightbox' => ['enabled' => true], 'alt' => 'Alt text'], ['lightbox' => ['enabled' => true]]]));
$say('the lightbox state and overlay hook', $try(static fn () => [array_values((array) (wp_interactivity_state('core/image')['metadata'] ?? [])), has_action('wp_footer', 'block_core_image_print_lightbox_overlay')]));
$say('block_core_image_print_lightbox_overlay', $try(static function () {
    ob_start();
    block_core_image_print_lightbox_overlay();
    return ob_get_clean();
}));

// Details, paragraph, avatar, featured image.
$say('block_core_details_set_img_fetchpriority_low', array_map(static fn ($h) => $try(static fn () => block_core_details_set_img_fetchpriority_low($h, $block('core/details'))), ['<details><summary>S</summary><img src="a.jpg"><figure><img src="b.jpg" fetchpriority="high"></figure></details>', '<details><summary>S</summary><p>x</p></details>', '<details open><summary>S</summary><img src="c.jpg"></details>']));
$say('block_core_paragraph_add_class', array_map(static fn ($h) => $try(static fn () => block_core_paragraph_add_class($h)), ['<p>x</p>', '<p class="has-text-color">x</p>', '<p class="wp-block-paragraph">x</p>', '<div><p>x</p></div>', '']));
$say('get_block_core_avatar_border_attributes', array_map(static fn ($a) => $try(static fn () => get_block_core_avatar_border_attributes($a)), [[], ['borderColor' => 'accent'], ['style' => ['border' => ['radius' => '50%', 'width' => '2px', 'color' => '#f00', 'style' => 'solid']]], ['style' => ['border' => ['top' => ['color' => '#000', 'width' => '1px'], 'radius' => ['topLeft' => '3px']]]]]));
$say('get_block_core_post_featured_image_overlay_element_markup', array_map(static fn ($a) => $try(static fn () => get_block_core_post_featured_image_overlay_element_markup($a)), [[], ['dimRatio' => 50], ['dimRatio' => 50, 'overlayColor' => 'contrast'], ['dimRatio' => 30, 'customOverlayColor' => '#123456'], ['dimRatio' => 100, 'gradient' => 'sunset'], ['dimRatio' => 70, 'customGradient' => 'linear-gradient(red,blue)'], ['dimRatio' => 0, 'overlayColor' => 'contrast'], ['style' => ['border' => ['radius' => '4px', 'width' => '1px']], 'dimRatio' => 40, 'borderColor' => 'accent']]));

// Latest posts and comments, post excerpt, post terms.
$say('block_core_latest_posts_get_excerpt_length', $try(static function () {
    global $block_core_latest_posts_excerpt_length;
    $out = [block_core_latest_posts_get_excerpt_length()];
    $block_core_latest_posts_excerpt_length = 7;
    $out[] = block_core_latest_posts_get_excerpt_length();
    $block_core_latest_posts_excerpt_length = 0;
    return $out;
}));
$say('block_core_latest_posts_migrate_categories', array_map(static fn ($b) => $try(static fn () => block_core_latest_posts_migrate_categories($b)), [$block('core/latest-posts', ['categories' => '1']), $block('core/latest-posts', ['categories' => [['id' => 1]]]), $block('core/latest-posts', ['categories' => '']), $block('core/latest-posts'), $block('core/paragraph', ['categories' => '1'])]));
$say('wp_latest_comments_draft_or_post_title', [$try(static fn () => wp_latest_comments_draft_or_post_title(1)), $try(static fn () => wp_latest_comments_draft_or_post_title(get_post(7))), $try(static fn () => wp_latest_comments_draft_or_post_title(999999999))]);
$say('block_core_post_excerpt_excerpt_length', $try(static function () {
    global $block_core_post_excerpt_excerpt_length;
    $out = [block_core_post_excerpt_excerpt_length()];
    $block_core_post_excerpt_excerpt_length = 12;
    $out[] = block_core_post_excerpt_excerpt_length();
    $block_core_post_excerpt_excerpt_length = null;
    return $out;
}));
$say('block_core_post_terms_build_variations', $try(static fn () => block_core_post_terms_build_variations()));
$say('block_core_query_disable_enhanced_pagination', array_map(static fn ($b) => $try(static fn () => block_core_query_disable_enhanced_pagination($b)), [
    $block('core/query', ['enhancedPagination' => true, 'queryId' => 3], [$block('core/post-template'), $block('core/html')]),
    $block('core/query', ['enhancedPagination' => true, 'queryId' => 4], [$block('core/post-template', [], [$block('core/post-title')])]),
    $block('core/query', ['enhancedPagination' => false], [$block('core/html')]),
    $block('core/paragraph'),
]));
$say('block_core_archives_build_dropdown_script', $try(static fn () => block_core_archives_build_dropdown_script('wp-block-archives-7')));

// Comment templates and forms.
$say('block_core_comment_template_render_comments', $try(static fn () => block_core_comment_template_render_comments(get_comments(['post_id' => 1, 'status' => 'approve']), new WP_Block($block('core/comment-template', [], [$block('core/comment-author-name'), $block('core/comment-content')]), ['postId' => 1]))));
$say('comments_block_form_defaults', [$try(static fn () => comments_block_form_defaults(['title_reply' => 'Leave a Reply', 'title_reply_before' => '<h3>', 'title_reply_after' => '</h3>'])), $try(static fn () => comments_block_form_defaults([]))]);
$say('post_comments_form_block_form_defaults', [$try(static fn () => post_comments_form_block_form_defaults(['title_reply' => 'Leave a Reply', 'title_reply_before' => '<h3>', 'title_reply_after' => '</h3>'])), $try(static fn () => post_comments_form_block_form_defaults([]))]);
$say('enqueue_legacy_post_comments_block_styles', $try(static function () {
    enqueue_legacy_post_comments_block_styles('core/post-comments');
    enqueue_legacy_post_comments_block_styles('core/paragraph');
    return [wp_style_is('wp-block-post-comments', 'enqueued'), wp_style_is('wp-block-buttons', 'enqueued'), wp_style_is('wp-block-button', 'enqueued'), wp_style_is('wp-block-comments', 'enqueued')];
}));
$say('register_legacy_post_comments_block', $try(static function () {
    $registry = WP_Block_Type_Registry::get_instance();
    $before = $registry->is_registered('core/post-comments');
    register_legacy_post_comments_block();
    $type = $registry->get_registered('core/post-comments');
    return [$before, $type ? [$type->name, $type->category, array_keys((array) $type->attributes), $type->render_callback, $type->supports['inserter'] ?? null] : null];
}));

// Auto sizes and block styles at render.
$say('wp_img_tag_add_auto_sizes', array_map(static fn ($h) => $try(static fn () => wp_img_tag_add_auto_sizes($h)), ['<img width="10" loading="lazy" sizes="100vw">', '<img width="10" loading="LAZY" sizes="100vw">', '<img width="10" loading=" lazy " sizes="100vw">', '<img width="10" loading="lazy" sizes="">', '<img width="10" loading="lazy" sizes="Auto,100vw">', '<img width="10" loading="lazy" sizes="auto">', '<img width="10" loading="lazy" sizes="  auto, 100vw">', '<img width="10" loading="lazy" sizes="autox, 100vw">', '<img width="10" loading="lazy">', '<img width="" loading="lazy" sizes="100vw">', '<img width loading="lazy" sizes="100vw">', '<img width="0" loading="lazy" sizes="100vw">', '<img width="10" loading="eager" sizes="100vw">', '<img src="a.jpg" loading="lazy" sizes="100vw" srcset="a.jpg 100w">', '<p>x</p><img width="10" loading="lazy" sizes="100vw">', '<img width="10" loading="lazy" sizes="100vw"><img width="10" loading="lazy" sizes="50vw">', '<p>x</p>', '']));
$say('wp_get_attachment_image auto sizes', array_map(static fn ($a) => $try(static fn () => preg_match('/sizes="([^"]*)"/', wp_get_attachment_image(607, 'large', false, $a), $m) === 1 ? $m[1] : '-'), [[], ['loading' => false], ['loading' => 'eager'], ['sizes' => '50vw'], ['srcset' => 'x 1w', 'sizes' => '10vw'], ['srcset' => 'x 1w'], ['sizes' => 'auto, 5vw']]));
$say('auto sizes turned off', $try(static function () {
    add_filter('wp_img_tag_add_auto_sizes', '__return_false');
    $out = [wp_img_tag_add_auto_sizes('<img width="10" loading="lazy" sizes="100vw">'), preg_match('/sizes="([^"]*)"/', wp_get_attachment_image(607, 'large'), $m) === 1 ? $m[1] : '-'];
    wp_enqueue_img_auto_sizes_contain_css_fix();
    $out[] = wp_style_is('wp-img-auto-sizes-contain', 'enqueued');
    remove_filter('wp_img_tag_add_auto_sizes', '__return_false');
    wp_enqueue_img_auto_sizes_contain_css_fix();
    $style = wp_styles()->registered['wp-img-auto-sizes-contain'] ?? null;
    $out[] = [wp_style_is('wp-img-auto-sizes-contain', 'enqueued'), $style ? [$style->src, $style->deps, $style->ver, $style->extra['after'] ?? null] : null];
    return $out;
}));
$say('wp_enqueue_block_style at render', $try(static function () {
    global $wp_filter;
    $count = static fn () => array_sum(array_map('count', $wp_filter['render_block']->callbacks ?? []));
    $before = $count();
    wp_enqueue_block_style('core/zz-minn-probe', ['handle' => 'zz-minn-probe-style', 'src' => 'https://x.example/zz.css']);
    $out = [$count() - $before, has_filter('render_block_core/zz-minn-probe'), wp_style_is('zz-minn-probe-style', 'registered'), wp_style_is('zz-minn-probe-style', 'enqueued')];
    render_block(['blockName' => 'core/zz-minn-other', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]);
    $out[] = wp_style_is('zz-minn-probe-style', 'enqueued');
    render_block(['blockName' => 'core/zz-minn-probe', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '<p>x</p>', 'innerContent' => ['<p>x</p>']]);
    $out[] = [wp_style_is('zz-minn-probe-style', 'registered'), wp_style_is('zz-minn-probe-style', 'enqueued')];
    wp_dequeue_style('zz-minn-probe-style');
    wp_deregister_style('zz-minn-probe-style');
    return $out;
}));

restore_error_handler();
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
