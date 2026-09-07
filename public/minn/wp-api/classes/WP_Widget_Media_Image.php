<?php
/** The image widget: an attachment at a size, or an image by URL, optionally linked and captioned. */
class WP_Widget_Media_Image extends WP_Widget_Media
{
    public function __construct()
    {
        parent::__construct('media_image', 'Image', ['description' => 'Displays an image.', 'mime_type' => 'image']);
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
            $dimensions = image_hwstring($instance['width'], $instance['height']);
            $lazy = $instance['width'] && $instance['height'] ? ' loading="lazy"' : '';
            $image = sprintf('<img class="%1$s" src="%2$s" alt="%3$s" %4$sdecoding="async"%5$s />', esc_attr($classes), esc_url($instance['url']), esc_attr($instance['alt']), $dimensions, $lazy);
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
