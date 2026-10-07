<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * The parts of a post's embed card the reference's embed template prints,
 * as markup: the featured image and its shape (the widest of the image's
 * sizes; wide enough and it sits above the title, else beside it, both by
 * filter), the site's name and icon, the comments and sharing buttons, and
 * the sharing dialog (its ids the post's and a random number, as on the
 * reference). The facade's template tags print them.
 */
final class EmbedCard
{
    /**
     * The featured image the card shows: [attachment id, size, shape], or
     * null. A post's thumbnail, or an image attachment itself, through
     * embed_thumbnail_id, embed_thumbnail_image_size and
     * embed_thumbnail_image_shape.
     *
     * @return array{0: int, 1: string, 2: string}|null
     */
    public static function thumbnail(): ?array
    {
        $id = \has_post_thumbnail() ? (int) \get_post_thumbnail_id() : 0;
        if (\get_post_type() === 'attachment' && \wp_attachment_is_image()) {
            $id = (int) \get_the_ID();
        }
        $id = (int) \apply_filters('embed_thumbnail_id', $id);
        if ($id === 0) {
            return null;
        }
        [$ratio, $measurements, $size] = [1, [1, 1], 'full'];
        $meta = (array) \wp_get_attachment_metadata($id);
        foreach ((array) ($meta['sizes'] ?? []) as $name => $data) {
            $data = (array) $data;
            if ((int) ($data['height'] ?? 0) > 0 && (int) $data['width'] / (int) $data['height'] > $ratio) {
                $ratio = (int) $data['width'] / (int) $data['height'];
                $measurements = [(int) $data['width'], (int) $data['height']];
                $size = (string) $name;
            }
        }
        $size = \apply_filters('embed_thumbnail_image_size', $size, $id);
        $shape = (string) \apply_filters('embed_thumbnail_image_shape', $measurements[0] / $measurements[1] >= 1.75 ? 'rectangular' : 'square', $id);
        return [$id, is_string($size) || is_array($size) ? $size : 'full', $shape];
    }

    /** The featured image's block, when the card shows one of this shape. */
    public static function featuredImage(?array $thumbnail, string $shape): string
    {
        if ($thumbnail === null || $thumbnail[2] !== $shape) {
            return '';
        }
        return "\t\t\t<div class=\"wp-embed-featured-image {$shape}\">"
            . "\n\t\t\t\t<a href=\"" . \esc_url((string) \get_permalink()) . "\" target=\"_top\">\n\t\t\t\t\t"
            . \wp_get_attachment_image($thumbnail[0], $thumbnail[1]) . "\t\t\t\t</a>\n\t\t\t</div>\n\t\t";
    }

    /** The site's name and icon, linking home, the whole block through embed_site_title_html. */
    public static function siteTitle(): string
    {
        $title = sprintf(
            '<div class="wp-embed-site-title"><a href="%s" target="_top"><img src="%s" width="32" height="32" alt="" class="wp-embed-site-icon" /><span>%s</span></a></div>',
            \esc_url(\home_url()),
            \esc_url((string) \get_site_icon_url(32, \includes_url('images/w-logo-gray-white-bg.svg'))),
            \esc_html((string) \get_bloginfo('name')),
        );
        return (string) \apply_filters('embed_site_title_html', $title);
    }

    /** The comments button: the count, linking to the comments; none on a 404, or with neither comments nor comments open. */
    public static function commentsButton(): string
    {
        if (\is_404() || !((int) \get_comments_number() || \comments_open())) {
            return '';
        }
        $count = (int) \get_comments_number();
        $words = sprintf(\_n('%s <span class="screen-reader-text">Comment</span>', '%s <span class="screen-reader-text">Comments</span>', $count), \number_format_i18n($count));
        return "\t<div class=\"wp-embed-comments\">\n\t\t<a href=\"" . \esc_url((string) \get_comments_link()) . "\" target=\"_top\">\n\t\t\t<span class=\"dashicons dashicons-admin-comments\"></span>\n\t\t\t"
            . $words . "\t\t</a>\n\t</div>\n\t";
    }

    /** The button that opens the sharing dialog; none on a 404. */
    public static function sharingButton(): string
    {
        if (\is_404()) {
            return '';
        }
        return "\t<div class=\"wp-embed-share\">\n\t\t<button type=\"button\" class=\"wp-embed-share-dialog-open\" aria-label=\"Open sharing dialog\">\n\t\t\t<span class=\"dashicons dashicons-share\"></span>\n\t\t</button>\n\t</div>\n\t";
    }

    /** The sharing dialog: the post's address and its embed code, each a tab; none on a 404. */
    public static function sharingDialog(): string
    {
        if (\is_404()) {
            return '';
        }
        $key = \get_the_ID() . '-' . mt_rand();
        $tab = static fn (string $kind, string $label, bool $selected): string => "\t\t\t\t\t<li class=\"wp-embed-share-tab-button wp-embed-share-tab-button-{$kind}\" role=\"presentation\">\n\t\t\t\t\t\t<button type=\"button\" role=\"tab\" aria-controls=\"wp-embed-share-tab-{$kind}-{$key}\" aria-selected=\"" . ($selected ? 'true' : 'false') . '" tabindex="' . ($selected ? '0' : '-1') . "\">{$label}</button>\n\t\t\t\t\t</li>\n";
        $panel = static fn (string $kind, bool $shown, string $input, string $words): string => "\t\t\t\t<div id=\"wp-embed-share-tab-{$kind}-{$key}\" class=\"wp-embed-share-tab\" role=\"tabpanel\" aria-hidden=\"" . ($shown ? 'false' : 'true') . "\">\n\t\t\t\t\t{$input}\n\n\t\t\t\t\t<p class=\"wp-embed-share-description\" id=\"wp-embed-share-description-{$kind}-{$key}\">\n\t\t\t\t\t\t{$words}\t\t\t\t\t</p>\n\t\t\t\t</div>\n";
        return "\t<div class=\"wp-embed-share-dialog hidden\" role=\"dialog\" aria-label=\"Sharing options\">\n\t\t<div class=\"wp-embed-share-dialog-content\">\n\t\t\t<div class=\"wp-embed-share-dialog-text\">\n\t\t\t\t<ul class=\"wp-embed-share-tabs\" role=\"tablist\">\n"
            . $tab('wordpress', 'WordPress Embed', true) . $tab('html', 'HTML Embed', false) . "\t\t\t\t</ul>\n"
            . $panel('wordpress', true, '<input type="text" value="' . \esc_url((string) \get_permalink()) . "\" class=\"wp-embed-share-input\" aria-label=\"URL\" aria-describedby=\"wp-embed-share-description-wordpress-{$key}\" tabindex=\"0\" readonly/>", 'Copy and paste this URL into your WordPress site to embed')
            . $panel('html', false, "<textarea class=\"wp-embed-share-input\" aria-label=\"HTML\" aria-describedby=\"wp-embed-share-description-html-{$key}\" tabindex=\"0\" readonly>" . \esc_textarea((string) \get_post_embed_html(600, 400)) . '</textarea>', 'Copy and paste this code into your site to embed')
            . "\t\t\t</div>\n\n\t\t\t<button type=\"button\" class=\"wp-embed-share-dialog-close\" aria-label=\"Close sharing dialog\">\n\t\t\t\t<span class=\"dashicons dashicons-no\"></span>\n\t\t\t</button>\n\t\t</div>\n\t</div>\n\t";
    }
}
