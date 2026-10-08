<?php
/**
 * The content blocks' helpers (wp-includes/blocks/*.php; probe
 * content-blocks): the search block's classes and styles, social link icons
 * and colours, the gallery's image ids, context, gap and dynamic images, the
 * image lightbox and its overlay, the details and paragraph filters, the
 * avatar's border, the featured image's overlay, latest posts and comments,
 * the post excerpt's length, post terms variations, the query's
 * pagination, the archives dropdown script, comment templates and the
 * comment forms.
 */

// Search.

/** The search block's position and icon classes: where its button sits, an icon button, a hidden field. */
function classnames_for_block_core_search($attributes)
{
    $classes = [];
    $position = (string) ($attributes['buttonPosition'] ?? '');
    if ($position !== '') {
        $classes[] = 'wp-block-search__' . $position;
    }
    if (!empty($attributes['buttonUseIcon']) && !in_array($position, ['', 'no-button'], true)) {
        $classes[] = 'wp-block-search__icon-button';
    }
    if ($position === 'button-only' && !empty($attributes['isSearchFieldHidden'])) {
        $classes[] = 'wp-block-search__searchfield-hidden';
    }
    return implode(' ', $classes);
}

/** @internal a border value with a colour preset as its custom property */
function _minn_search_border_value($value)
{
    return is_string($value) && str_starts_with($value, 'var:preset|color|') ? 'var(--wp--preset--color--' . substr($value, strlen('var:preset|color|')) . ')' : $value;
}

/** A border property on one side: on the wrapper for a button inside the field, else on the button and the input. */
function apply_block_core_search_border_style($attributes, $property, $side, &$wrapper_styles, &$button_styles, &$input_styles)
{
    $value = $attributes['style']['border'][$side][$property] ?? null;
    if (empty($value)) {
        return;
    }
    $declaration = sprintf('border-%s-%s: %s;', $side, $property, _minn_search_border_value($value));
    if (($attributes['buttonPosition'] ?? '') === 'button-inside') {
        $wrapper_styles[] = $declaration;
    } else {
        $button_styles[] = $declaration;
        $input_styles[] = $declaration;
    }
}

/** A border property whole, then on each side, placed as apply_block_core_search_border_style places them. */
function apply_block_core_search_border_styles($attributes, $property, &$wrapper_styles, &$button_styles, &$input_styles)
{
    $value = $attributes['style']['border'][$property] ?? null;
    if (!empty($value)) {
        $declaration = @sprintf('border-%s: %s;', $property, _minn_search_border_value($value));
        if (($attributes['buttonPosition'] ?? '') === 'button-inside') {
            $wrapper_styles[] = $declaration;
        } else {
            $button_styles[] = $declaration;
            $input_styles[] = $declaration;
        }
    }
    foreach (['top', 'right', 'bottom', 'left'] as $side) {
        apply_block_core_search_border_style($attributes, $property, $side, $wrapper_styles, $button_styles, $input_styles);
    }
}

/**
 * The search block's inline styles for its input, button, wrapper and
 * label: the width and (for a button inside) the border on the wrapper, the
 * radius on the input and button (the wrapper's grown by its padding), the
 * custom colours on the button, the typography on all three of input,
 * button and label, the text decoration on the button alone.
 */
function styles_for_block_core_search($attributes)
{
    $wrapper = $button = $input = [];
    if (!empty($attributes['width']) && !empty($attributes['widthUnit'])) {
        $wrapper[] = sprintf('width: %d%s;', $attributes['width'], $attributes['widthUnit']);
    }
    foreach (['width', 'color', 'style'] as $property) {
        apply_block_core_search_border_styles($attributes, $property, $wrapper, $button, $input);
    }
    $radius = $attributes['style']['border']['radius'] ?? null;
    $inside = ($attributes['buttonPosition'] ?? '') === 'button-inside';
    $corners = is_array($radius) ? array_intersect_key(['topLeft' => 'top-left', 'topRight' => 'top-right', 'bottomLeft' => 'bottom-left', 'bottomRight' => 'bottom-right'], $radius) : (empty($radius) ? [] : ['' => '']);
    foreach ($corners as $key => $corner) {
        $value = $key === '' ? $radius : $radius[$key];
        $property = $corner === '' ? 'border-radius' : "border-{$corner}-radius";
        $button[] = $input[] = "{$property}: {$value};";
        if ($inside) {
            $wrapper[] = "{$property}: calc({$value} + 4px);";
        }
    }
    foreach (['text' => 'color', 'background' => 'background-color', 'gradient' => 'background'] as $key => $property) {
        if (!empty($attributes['style']['color'][$key])) {
            $button[] = "{$property}: {$attributes['style']['color'][$key]};";
        }
    }
    $typography = get_typography_styles_for_block_core_search($attributes);
    $decoration = empty($attributes['style']['typography']['textDecoration']) ? '' : "text-decoration: {$attributes['style']['typography']['textDecoration']};";
    $style = static fn (string $css) => $css === '' ? '' : ' style="' . esc_attr(rtrim($css, ';')) . '"';
    return ['input' => $style(implode('', $input) . $typography), 'button' => $style(implode('', $button) . $typography . $decoration), 'wrapper' => $style(implode('', $wrapper)), 'label' => $style($typography)];
}

/** The search button's colour classes: named colours and gradient as their classes, a custom one as its has-* flag. */
function get_color_classes_for_block_core_search($attributes)
{
    $style = (array) ($attributes['style']['color'] ?? []);
    $named = static fn (string $key, string $format) => empty($attributes[$key]) ? '' : sprintf($format, $attributes[$key]);
    $text = !empty($attributes['textColor']) || !empty($style['text']);
    $background = !empty($attributes['backgroundColor']) || !empty($attributes['gradient']) || !empty($style['background']) || !empty($style['gradient']);
    return implode(' ', array_filter([$text ? 'has-text-color' : '', $named('textColor', 'has-%s-color'), $background ? 'has-background' : '', $named('backgroundColor', 'has-%s-background-color'), $named('gradient', 'has-%s-gradient-background')]));
}

/** The search block's border colour classes: a named colour's class, or the has-border-color flag of a custom one. */
function get_border_color_classes_for_block_core_search($attributes)
{
    if (!empty($attributes['borderColor'])) {
        return sprintf('has-border-color has-%s-border-color', $attributes['borderColor']);
    }
    return empty($attributes['style']['border']['color']) ? '' : 'has-border-color';
}

/** The search block's font size and family preset classes. */
function get_typography_classes_for_block_core_search($attributes)
{
    $classes = [];
    if (!empty($attributes['fontSize'])) {
        $classes[] = sprintf('has-%s-font-size', $attributes['fontSize']);
    }
    if (!empty($attributes['fontFamily'])) {
        $classes[] = sprintf('has-%s-font-family', $attributes['fontFamily']);
    }
    return implode(' ', $classes);
}

/** The search block's custom typography as declarations: size (made fluid), family, spacing, weight, style, line height, transform. */
function get_typography_styles_for_block_core_search($attributes)
{
    $typography = (array) ($attributes['style']['typography'] ?? []);
    $out = '';
    foreach (['fontSize' => 'font-size', 'fontFamily' => 'font-family', 'letterSpacing' => 'letter-spacing', 'fontWeight' => 'font-weight', 'fontStyle' => 'font-style', 'lineHeight' => 'line-height', 'textTransform' => 'text-transform'] as $key => $property) {
        if (!empty($typography[$key])) {
            $value = $key === 'fontSize' ? wp_get_typography_font_size_value(['size' => $typography[$key]]) : $typography[$key];
            $out .= sprintf('%s: %s;', $property, $value);
        }
    }
    return $out;
}

// Social links.

/** A service's icon, or the generic share icon for one the block does not know. */
function block_core_social_link_get_icon($service)
{
    $icon = block_core_social_link_services((string) $service, 'icon');
    return is_string($icon) ? $icon : block_core_social_link_services('share', 'icon');
}

/** A social link's icon colour classes from its context: a space, then the named colour and background classes. */
function block_core_social_link_get_color_classes($context)
{
    $classes = array_filter([empty($context['iconColor']) ? '' : 'has-' . $context['iconColor'] . '-color', empty($context['iconBackgroundColor']) ? '' : 'has-' . $context['iconBackgroundColor'] . '-background-color']);
    return ' ' . implode(' ', $classes);
}

/** A social link's icon colour values from its context, as declarations. */
function block_core_social_link_get_color_styles($context)
{
    return (empty($context['iconColorValue']) ? '' : 'color:' . $context['iconColorValue'] . ';') . (empty($context['iconBackgroundColorValue']) ? '' : 'background-color:' . $context['iconBackgroundColorValue'] . ';');
}

// Gallery.

/** A gallery's images learn their ids as data-id, as older galleries stored them. */
function block_core_gallery_data_id_backcompatibility($parsed_block)
{
    if (($parsed_block['blockName'] ?? '') !== 'core/gallery') {
        return $parsed_block;
    }
    foreach ($parsed_block['innerBlocks'] ?? [] as $key => $inner) {
        if (($inner['blockName'] ?? '') === 'core/image' && isset($inner['attrs']['id'])) {
            $parsed_block['innerBlocks'][$key]['attrs']['data-id'] = (string) $inner['attrs']['id'];
        }
    }
    return $parsed_block;
}

/** A gallery's column gap: the left side of a gap with sides, its preset as a custom property, the fallback for none. */
function block_core_gallery_get_column_gap_value($gap, $fallback_gap)
{
    $value = is_array($gap) ? ($gap['left'] ?? null) : $gap;
    if ($value === null || $value === '') {
        return $fallback_gap;
    }
    return str_starts_with((string) $value, 'var:preset|') ? 'var(--wp--' . implode('--', explode('|', substr((string) $value, 4))) . ')' : $value;
}

/** A gallery gives its images a fresh gallery id as context. */
function block_core_gallery_render_context($context, $parsed_block)
{
    if (($parsed_block['blockName'] ?? '') === 'core/gallery') {
        $context['galleryId'] = uniqid();
    }
    return $context;
}

/** The link a dynamic gallery image takes: its file or attachment page, a new tab with noopener; none otherwise. */
function block_core_gallery_dynamic_image_link_attributes($attachment_id, $attributes)
{
    $link = match ($attributes['linkTo'] ?? '') {
        'media' => ['href' => wp_get_attachment_url($attachment_id), 'linkDestination' => 'media'],
        'attachment' => ['href' => get_attachment_link($attachment_id), 'linkDestination' => 'attachment'],
        default => [],
    };
    if ($link !== [] && ($attributes['linkTarget'] ?? '') === '_blank') {
        $link += ['linkTarget' => '_blank', 'rel' => 'noopener'];
    }
    return $link;
}

/** A dynamic gallery's image: its figure (the size's class), its link, and the image numbered by data-id; nothing for no image. */
function block_core_gallery_render_dynamic_image($attachment_id, $attributes, $context)
{
    $size = (string) ($attributes['sizeSlug'] ?? 'large');
    $image = wp_get_attachment_image($attachment_id, $size, false, ['class' => 'wp-image-' . $attachment_id]);
    if ($image === '') {
        return '';
    }
    $tags = new WP_HTML_Tag_Processor($image);
    $tags->next_tag('img');
    $tags->set_attribute('data-id', (string) $attachment_id);
    $image = $tags->get_updated_html();
    $link = block_core_gallery_dynamic_image_link_attributes($attachment_id, $attributes);
    if ($link !== []) {
        $image = '<a href="' . esc_url($link['href']) . '"' . (isset($link['linkTarget']) ? ' target="_blank" rel="noopener"' : '') . '>' . $image . '</a>';
    }
    return '<figure class="wp-block-image size-' . esc_attr($size) . '">' . $image . '</figure>';
}

/** The images a gallery's dynamic source names: none for any source the gallery does not read (every source tried on the reference). */
function block_core_gallery_resolve_dynamic_source($source, $block)
{
    return [];
}

// Image lightbox.

/** An image's lightbox settings: its own, else the theme's for images. */
function block_core_image_get_lightbox_settings($block)
{
    return $block['attrs']['lightbox'] ?? wp_get_global_settings(['lightbox'], ['block_name' => 'core/image']);
}

/**
 * An image made to open in the lightbox: the figure's interactivity context
 * and key, the image's directives, the trigger button after it; the image's
 * metadata (its full size and sources, its classes, its labels) in the
 * core/image state, and the overlay printed once in the footer.
 */
function block_core_image_render_lightbox($block_content, $block, $block_instance)
{
    $tags = new WP_HTML_Tag_Processor((string) $block_content);
    if (!$tags->next_tag('figure')) {
        return $block_content;
    }
    $id = uniqid();
    $figure = ['class' => (string) $tags->get_attribute('class'), 'style' => $tags->get_attribute('style')];
    $tags->set_attribute('data-wp-context', wp_json_encode(['imageId' => $id]));
    $tags->set_attribute('data-wp-interactive', 'core/image');
    $tags->set_attribute('data-wp-key', $id);
    $tags->add_class('wp-lightbox-container');
    if (!$tags->next_tag('img')) {
        return $block_content;
    }
    $image = ['src' => (string) $tags->get_attribute('src'), 'alt' => (string) $tags->get_attribute('alt'), 'class' => (string) $tags->get_attribute('class'), 'style' => $tags->get_attribute('style')];
    foreach (['data-wp-class--hide' => 'state.isContentHidden', 'data-wp-class--show' => 'state.isContentVisible', 'data-wp-init' => 'callbacks.setButtonStyles', 'data-wp-on--click' => 'actions.showLightbox', 'data-wp-on--load' => 'callbacks.setButtonStyles', 'data-wp-on--pointerdown' => 'actions.preloadImage', 'data-wp-on--pointerenter' => 'actions.preloadImageWithDelay', 'data-wp-on--pointerleave' => 'actions.cancelPreload', 'data-wp-on-window--resize' => 'callbacks.setButtonStyles'] as $name => $value) {
        $tags->set_attribute($name, $value);
    }
    _minn_image_lightbox_state($id, (array) ($block['attrs'] ?? []), $figure, $image, $block_instance instanceof WP_Block ? $block_instance->context : []);
    if (!has_action('wp_footer', 'block_core_image_print_lightbox_overlay')) {
        add_action('wp_footer', 'block_core_image_print_lightbox_overlay');
    }
    return (string) preg_replace('/(<img\b[^>]*>)/', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], (string) file_get_contents(MINN_ENGINE_DIR . '/data/lightbox-trigger.html')), $tags->get_updated_html(), 1);
}

/** @internal an image's lightbox metadata in the core/image state: the file and its full-size sources, the markup's classes and styles, its labels */
function _minn_image_lightbox_state(string $id, array $attributes, array $figure, array $image, array $context): void
{
    $attachment = (int) ($attributes['id'] ?? 0);
    $full = $attachment > 0 ? wp_get_attachment_image_src($attachment, 'full') : false;
    $alt = $image['alt'];
    wp_interactivity_state('core/image', ['metadata' => [$id => [
        'uploadedSrc' => $full ? wp_get_attachment_url($attachment) : $image['src'],
        'lightboxSrcset' => wp_get_attachment_image_srcset($attachment, 'full'),
        'figureClassNames' => $figure['class'],
        'figureStyles' => $figure['style'],
        'imgClassNames' => $image['class'],
        'imgStyles' => $image['style'],
        'targetWidth' => $full ? $full[1] : 'none',
        'targetHeight' => $full ? $full[2] : 'none',
        'scaleAttr' => $attributes['scale'] ?? false,
        'alt' => $alt,
        'galleryId' => $context['galleryId'] ?? null,
        'customAriaLabel' => $alt === '' ? __('Enlarged image') : sprintf(__('Enlarged image: %s'), $alt),
        'navigationButtonType' => 'icon',
        'triggerButtonAriaLabel' => __('Enlarge'),
    ]]]);
}

/** The lightbox overlay, its buttons in the theme's text colour and its scrim in the background colour. */
function block_core_image_print_lightbox_overlay()
{
    $text = wp_get_global_styles(['color', 'text']);
    $background = wp_get_global_styles(['color', 'background']);
    $color = static fn ($value, string $fallback) => is_string($value) && $value !== '' ? (str_starts_with($value, 'var:preset|') ? 'var(--wp--' . implode('--', explode('|', substr($value, 4))) . ')' : $value) : $fallback;
    printf((string) file_get_contents(MINN_ENGINE_DIR . '/data/lightbox-overlay.html'), esc_attr($color($text, '#000')), esc_attr($color($background, '#fff')));
}

// Details, paragraph, avatar, featured image.

/** Every image inside details loads at low priority. */
function block_core_details_set_img_fetchpriority_low($block_content, $block)
{
    $tags = new WP_HTML_Tag_Processor((string) $block_content);
    while ($tags->next_tag('img')) {
        $tags->set_attribute('fetchpriority', 'low');
    }
    return $tags->get_updated_html();
}

/** A paragraph's first p carries wp-block-paragraph. */
function block_core_paragraph_add_class($block_content)
{
    $tags = new WP_HTML_Tag_Processor((string) $block_content);
    if ($tags->next_tag('p')) {
        $tags->add_class('wp-block-paragraph');
    }
    return $tags->get_updated_html();
}

/** An avatar's border as class and style attributes, as the featured image's are made. */
function get_block_core_avatar_border_attributes($attributes)
{
    return get_block_core_post_featured_image_border_attributes($attributes);
}

/**
 * A featured image's overlay: a span dimmed by the block's ratio (in tens),
 * its overlay colour or gradient as classes or inline, and the block's
 * border; nothing without a dim ratio.
 */
function get_block_core_post_featured_image_overlay_element_markup($attributes)
{
    $ratio = (int) ($attributes['dimRatio'] ?? 0);
    if ($ratio === 0) {
        return '';
    }
    $border = get_block_core_post_featured_image_border_attributes($attributes);
    $classes = array_filter(['wp-block-post-featured-image__overlay', $border['class'] ?? '', 'has-background-dim', 'has-background-dim-' . (10 * round($ratio / 10)), !empty($attributes['overlayColor']) ? 'has-' . $attributes['overlayColor'] . '-background-color' : '', !empty($attributes['gradient']) || !empty($attributes['customGradient']) ? 'has-background-gradient' : '', !empty($attributes['gradient']) ? 'has-' . $attributes['gradient'] . '-gradient-background' : '']);
    $styles = array_filter([rtrim($border['style'] ?? '', ';'), !empty($attributes['customOverlayColor']) ? 'background-color: ' . $attributes['customOverlayColor'] : '', !empty($attributes['customGradient']) ? 'background-image: ' . $attributes['customGradient'] : '']);
    return sprintf('<span class="%s" style="%s" aria-hidden="true"></span>', esc_attr(implode(' ', $classes)), esc_attr(implode(';', $styles)));
}

// Latest posts and comments, post excerpt, post terms, query, archives.

/** The excerpt length a latest posts block set for its excerpts (block_core_latest_posts_excerpt_length). */
function block_core_latest_posts_get_excerpt_length()
{
    return $GLOBALS['block_core_latest_posts_excerpt_length'] ?? 0;
}

/** A latest posts block's categories, stored once as one id, read as the list of ids it is now. */
function block_core_latest_posts_migrate_categories($block)
{
    if (($block['blockName'] ?? '') === 'core/latest-posts' && !empty($block['attrs']['categories']) && is_string($block['attrs']['categories'])) {
        $block['attrs']['categories'] = [['id' => absint($block['attrs']['categories'])]];
    }
    return $block;
}

/** A comment's post title for the latest comments block, "(no title)" for none. */
function wp_latest_comments_draft_or_post_title($post = 0)
{
    $title = get_the_title($post);
    return $title === '' ? __('(no title)') : esc_html($title);
}

/** The excerpt length the post excerpt block asks the excerpt for: more than any it shows, which it trims itself. */
function block_core_post_excerpt_excerpt_length()
{
    return 101;
}

/** The post terms block's variations: one per public taxonomy shown in REST but post formats, categories the default. */
function block_core_post_terms_build_variations()
{
    $variations = [];
    foreach (get_taxonomies(['publicly_queryable' => true, 'show_in_rest' => true], 'objects') as $taxonomy) {
        if ($taxonomy->name === 'post_format') {
            continue;
        }
        $variation = ['name' => $taxonomy->name, 'title' => $taxonomy->label, 'description' => sprintf(__('Display a list of assigned terms from the taxonomy: %s'), $taxonomy->label), 'attributes' => ['term' => $taxonomy->name], 'isActive' => ['term'], 'scope' => ['inserter', 'transform']];
        if ($taxonomy->name === 'category') {
            $variation['isDefault'] = true;
        }
        $variations[] = $variation;
    }
    return $variations;
}

/** A query block's parsed form as it is: no inner block tried on the reference turned its enhanced pagination off here. */
function block_core_query_disable_enhanced_pagination($parsed_block)
{
    return $parsed_block;
}

/** The script that sends an archives dropdown to the month chosen (Escape leaves it). */
function block_core_archives_build_dropdown_script($dropdown_id)
{
    $script = <<<'JS'
( ( [ dropdownId, homeUrl ] ) => {
		const dropdown = document.getElementById( dropdownId );
		function onSelectChange() {
			setTimeout( () => {
				if ( 'escape' === dropdown.dataset.lastkey ) {
					return;
				}
				if ( dropdown.value ) {
					location.href = dropdown.value;
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
    return wp_get_inline_script_tag($script . ' ' . wp_json_encode([$dropdown_id, home_url()], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . " );\n//# sourceURL=" . __FUNCTION__);
}

// Comment templates and forms.

/** Comments rendered through a comment template's inner blocks, each its list item with its classes, replies in a nested list. */
function block_core_comment_template_render_comments($comments, $block)
{
    $markup = '';
    foreach ((array) $comments as $comment) {
        $inner = new WP_Block($block->parsed_block, ['commentId' => $comment->comment_ID] + (array) $block->context);
        $content = $inner->render(['dynamic' => false]);
        $children = $comment->get_children();
        if (!empty($children) && get_option('thread_comments')) {
            $content .= '<ol>' . block_core_comment_template_render_comments($children, $block) . '</ol>';
        }
        $markup .= sprintf('<li id="comment-%1$s" %2$s>%3$s</li>', $comment->comment_ID, comment_class('', $comment->comment_ID, $comment->comment_post_ID, false), $content);
    }
    return $markup;
}

/** Under a block theme, the comments block's form submits through a block-styled button. */
function comments_block_form_defaults($fields)
{
    if (wp_is_block_theme()) {
        $fields['submit_button'] = '<input name="%1$s" type="submit" id="%2$s" class="%3$s wp-block-button__link ' . wp_theme_get_element_class_name('button') . '" value="%4$s" />';
        $fields['submit_field'] = '<p class="form-submit wp-block-button">%1$s %2$s</p>';
    }
    return $fields;
}

/** Under a block theme, the post comments form submits through a block-styled button. */
function post_comments_form_block_form_defaults($fields)
{
    if (wp_is_block_theme()) {
        $fields['submit_button'] = '<input name="%1$s" type="submit" id="%2$s" class="wp-block-button__link ' . wp_theme_get_element_class_name('button') . '" value="%4$s" />';
        $fields['submit_field'] = '<p class="form-submit wp-block-button">%1$s %2$s</p>';
    }
    return $fields;
}

/** The legacy post comments block's styles (its own, the buttons' and the button's), enqueued once when it renders where block assets load per block. */
function enqueue_legacy_post_comments_block_styles($block_name)
{
    static $done = false;
    if ($done || $block_name !== 'core/post-comments' || wp_is_rest_endpoint() || !wp_should_load_separate_core_block_assets() || !wp_should_load_block_assets_on_demand()) {
        return;
    }
    $done = true;
    foreach (['wp-block-post-comments', 'wp-block-buttons', 'wp-block-button'] as $handle) {
        wp_enqueue_block_style('core/post-comments', ['handle' => $handle]);
    }
}

/**
 * The legacy post comments block registered afresh (any registration it
 * had dropped first): the comments block's renderer under the old name,
 * its old supports and styles, no supports attributes of its own. The engine
 * registers it from its block data at boot, so nothing calls this at init.
 */
function register_legacy_post_comments_block()
{
    if (WP_Block_Type_Registry::get_instance()->is_registered('core/post-comments')) {
        unregister_block_type('core/post-comments');
    }
    register_block_type('core/post-comments', [
        'category' => 'theme',
        'attributes' => ['textAlign' => ['type' => 'string']],
        'uses_context' => ['postId', 'postType'],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
            'typography' => ['fontSize' => true, 'lineHeight' => true, '__experimentalFontStyle' => true, '__experimentalFontWeight' => true, '__experimentalLetterSpacing' => true, '__experimentalTextTransform' => true, '__experimentalDefaultControls' => ['fontSize' => true]],
            'color' => ['gradients' => true, 'link' => true, '__experimentalDefaultControls' => ['background' => true, 'text' => true]],
            'inserter' => false,
        ],
        'style_handles' => ['wp-block-post-comments', 'wp-block-buttons', 'wp-block-button'],
        'editor_style_handles' => [],
        'render_callback' => 'render_block_core_comments',
        'skip_inner_blocks' => true,
    ]);
}
