<?php
/** The image widget: an attachment at a size, or an image by URL, optionally linked and captioned. */
class WP_Widget_Media_Image extends WP_Widget_Media
{
    public function __construct()
    {
        parent::__construct('media_image', 'Image', ['description' => 'Displays an image.', 'mime_type' => 'image']);
        $this->l10n = array_merge($this->l10n, [
            'no_media_selected' => __('No image selected'),
            'add_media' => _x('Add Image', 'label for button in the image widget'),
            'replace_media' => _x('Replace Image', 'label for button in the image widget; should preferably not be longer than ~13 characters long'),
            'edit_media' => _x('Edit Image', 'label for button in the image widget; should preferably not be longer than ~13 characters long'),
            'missing_attachment' => sprintf(__('That image cannot be found. Check your <a href="%s">media library</a> and make sure it was not deleted.'), esc_url(admin_url('upload.php'))),
            'media_library_state_multi' => _n_noop('Image Widget (%d)', 'Image Widget (%d)'),
            'media_library_state_single' => __('Image Widget'),
        ]);
    }

    public function get_instance_schema()
    {
        return array_merge([
            'size' => ['type' => 'string', 'enum' => array_merge(get_intermediate_image_sizes(), ['full', 'custom']), 'default' => 'medium', 'description' => 'Image size'],
            'width' => ['type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Width'],
            'height' => ['type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Height'],
            'caption' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'wp_kses_post', 'description' => 'Caption', 'should_preview_update' => false],
            'alt' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'description' => 'Image Alt Text'],
            'link_type' => ['type' => 'string', 'enum' => ['none', 'file', 'post', 'custom'], 'default' => 'custom', 'media_prop' => 'link', 'description' => 'Link To', 'should_preview_update' => true],
            'link_url' => ['type' => 'string', 'default' => '', 'format' => 'uri', 'media_prop' => 'linkUrl', 'description' => 'URL', 'should_preview_update' => true],
            'image_classes' => ['type' => 'string', 'default' => '', 'sanitize_callback' => [$this, 'sanitize_token_list'], 'media_prop' => 'extraClasses', 'description' => 'Image CSS Class', 'should_preview_update' => false],
            'link_classes' => ['type' => 'string', 'default' => '', 'sanitize_callback' => [$this, 'sanitize_token_list'], 'media_prop' => 'linkClassName', 'description' => 'Link CSS Class', 'should_preview_update' => false],
            'link_rel' => ['type' => 'string', 'default' => '', 'sanitize_callback' => [$this, 'sanitize_token_list'], 'media_prop' => 'linkRel', 'description' => 'Link Rel', 'should_preview_update' => false],
            'link_target_blank' => ['type' => 'boolean', 'default' => false, 'media_prop' => 'linkTargetBlank', 'description' => 'Open link in a new tab', 'should_preview_update' => false],
            'image_title' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field', 'media_prop' => 'title', 'description' => 'Image Title Attribute', 'should_preview_update' => false],
        ], parent::get_instance_schema());
    }

    public function render_media($instance)
    {
        $instance = array_merge(wp_list_pluck($this->get_instance_schema(), 'default'), $instance);
        $instance = wp_parse_args($instance, ['size' => 'thumbnail']);
        $attachment = $instance['attachment_id'] && $this->is_attachment_with_mime_type($instance['attachment_id'], 'image') ? get_post($instance['attachment_id']) : null;
        if ($attachment) {
            [$image, $caption, $width] = $this->attachmentImage($attachment, $instance);
        } elseif (!empty($instance['url'])) {
            $caption = $instance['caption'];
            $width = (int) $instance['width'];
            $classes = 'image ' . $instance['image_classes'];
            // Width and height are always written, empty when unknown; the image loads lazily (probe widget-classes).
            $attributes = ['class' => $classes, 'src' => esc_url($instance['url']), 'alt' => $instance['alt'], 'width' => $instance['width'] ?: '', 'height' => $instance['height'] ?: '', 'decoding' => 'async', 'loading' => 'lazy'];
            $image = '<img' . implode('', array_map(static fn ($name, $value) => ' ' . $name . '="' . esc_attr((string) $value) . '"', array_keys($attributes), $attributes)) . ' />';
        } else {
            return;
        }
        $url = match ($instance['link_type']) {
            'file' => $attachment ? wp_get_attachment_url($attachment->ID) : $instance['url'],
            'post' => $attachment ? get_attachment_link($attachment->ID) : '',
            'custom' => (string) $instance['link_url'],
            default => '',
        };
        if ($url) {
            $image = $this->linked($image, $url, $instance);
        }
        if ($caption) {
            $image = img_caption_shortcode(['width' => $width, 'caption' => $caption], $image);
        }
        echo $image;
    }

    /** @return array{string, string, int} the image tag, its caption, and the caption's width */
    private function attachmentImage(WP_Post $attachment, array $instance): array
    {
        $caption = '';
        if (!isset($instance['caption'])) {
            $caption = $attachment->post_excerpt;
        } elseif (trim((string) $instance['caption']) !== '') {
            $caption = $instance['caption'];
        }
        $attributes = ['class' => sprintf('image wp-image-%d %s', $attachment->ID, $instance['image_classes']), 'style' => 'max-width: 100%; height: auto;'];
        if (!empty($instance['image_title'])) {
            $attributes['title'] = $instance['image_title'];
        }
        if ($instance['alt']) {
            $attributes['alt'] = $instance['alt'];
        }
        $size = $instance['size'];
        if ($size === 'custom' || !in_array($size, array_merge(get_intermediate_image_sizes(), ['full']), true)) {
            $size = [(int) $instance['width'], (int) $instance['height']];
        }
        $attributes['class'] .= sprintf(' attachment-%1$s size-%1$s', is_array($size) ? implode('x', $size) : $size);
        $source = wp_get_attachment_image_src($attachment->ID, $size);
        return [wp_get_attachment_image($attachment->ID, $size, false, $attributes), (string) $caption, (int) ($source[1] ?? 0)];
    }

    private function linked(string $image, string $url, array $instance): string
    {
        $link = sprintf('<a href="%s"', esc_url($url));
        if (!empty($instance['link_classes'])) {
            $link .= sprintf(' class="%s"', esc_attr($instance['link_classes']));
        }
        if (!empty($instance['link_rel'])) {
            $link .= sprintf(' rel="%s"', esc_attr($instance['link_rel']));
        }
        if (!empty($instance['link_target_blank'])) {
            $link .= ' target="_blank"';
        }
        return $link . '>' . $image . '</a>';
    }
}
