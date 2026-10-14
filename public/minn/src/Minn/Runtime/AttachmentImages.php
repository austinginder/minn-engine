<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\RenderState;
use Minn\I18n\Gettext;
use Minn\Media\Icons;
use Minn\Media\Sizing;
use Minn\Support\Escape;
use Minn\Support\Lists;
use Minn\Support\Text;

/**
 * An attachment's image as WordPress's media functions make it: the
 * registered sizes, a size fitted to a box, the file for a size (or an
 * icon), the srcset and sizes pair, the loading attributes an image earns
 * where it sits, the img element itself, and the site icon.
 */
final class AttachmentImages
{
    /**
     * The registered sizes (_minn_image_sizes): the four from the options,
     * then the ones add_image_size added.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function sizes(): array
    {
        $options = Runtime::options();
        $sizes = [
            'thumbnail' => ['width' => (int) $options->filtered('thumbnail_size_w'), 'height' => (int) $options->filtered('thumbnail_size_h'), 'crop' => (bool) $options->filtered('thumbnail_crop')],
            'medium' => ['width' => (int) $options->filtered('medium_size_w'), 'height' => (int) $options->filtered('medium_size_h'), 'crop' => false],
            'medium_large' => ['width' => (int) $options->filtered('medium_large_size_w'), 'height' => (int) $options->filtered('medium_large_size_h'), 'crop' => false],
            'large' => ['width' => (int) $options->filtered('large_size_w'), 'height' => (int) $options->filtered('large_size_h'), 'crop' => false],
        ];
        foreach (self::additionalSizes() as $name => $size) {
            $sizes[$name] = $size;
        }
        return $sizes;
    }

    /**
     * The sizes plugins and themes added (wp_get_additional_image_sizes),
     * kept where they read them: $_wp_additional_image_sizes.
     *
     * @return array<string, mixed>
     */
    public static function additionalSizes(): array
    {
        if (!isset($GLOBALS['_wp_additional_image_sizes']) || !is_array($GLOBALS['_wp_additional_image_sizes'])) {
            $GLOBALS['_wp_additional_image_sizes'] = [];
        }
        return $GLOBALS['_wp_additional_image_sizes'];
    }

    /**
     * Dimensions fitted inside a box (wp_constrain_dimensions), through
     * wp_constrain_dimensions when there is a box.
     *
     * @return array{0: int, 1: int}
     */
    public static function constrain(mixed $width, mixed $height, mixed $maxWidth = 0, mixed $maxHeight = 0): mixed
    {
        $constrained = Sizing::constrain((int) $width, (int) $height, (int) $maxWidth, (int) $maxHeight);
        if (!(int) $maxWidth && !(int) $maxHeight) {
            return $constrained;
        }
        return Runtime::hooks()->filter('wp_constrain_dimensions', [$constrained, (int) $width, (int) $height, (int) $maxWidth, (int) $maxHeight]);
    }

    /** Whether two sizes share a ratio (wp_image_matches_ratio): the wider one, fitted into the other's box, comes within a pixel of it. */
    public static function matchesRatio(mixed $sourceWidth, mixed $sourceHeight, mixed $targetWidth, mixed $targetHeight): bool
    {
        if (!$sourceWidth || !$sourceHeight || !$targetWidth || !$targetHeight) {
            return false;
        }
        [$wide, $other] = $sourceWidth > $targetWidth ? [[$sourceWidth, $sourceHeight], [$targetWidth, $targetHeight]] : [[$targetWidth, $targetHeight], [$sourceWidth, $sourceHeight]];
        $constrained = self::constrain($wide[0], $wide[1], $other[0], $other[1]);
        return abs($constrained[0] - $other[0]) <= 1 && abs($constrained[1] - $other[1]) <= 1;
    }

    /**
     * Dimensions fitted for the editor to a named or given size
     * (image_constrain_size_for_editor), within editor_max_image_size.
     *
     * @return array{0: int, 1: int}
     */
    public static function constrainForEditor(mixed $width, mixed $height, mixed $size = 'medium', mixed $context = null): mixed
    {
        $width = (int) $width;
        $height = (int) $height;
        if (is_array($size)) {
            $maxWidth = (int) $size[0];
            $maxHeight = (int) $size[1];
        } elseif ($size === 'thumb' || $size === 'thumbnail') {
            $maxWidth = (int) Runtime::options()->filtered('thumbnail_size_w') ?: 128;
            $maxHeight = (int) Runtime::options()->filtered('thumbnail_size_h') ?: 96;
        } elseif ($size === 'full') {
            return [$width, $height];
        } else {
            $sizes = self::sizes();
            if (!isset($sizes[$size])) {
                return [$width, $height];
            }
            $maxWidth = (int) $sizes[$size]['width'];
            $maxHeight = (int) $sizes[$size]['height'];
        }
        [$maxWidth, $maxHeight] = (array) Runtime::hooks()->filter('editor_max_image_size', [[$maxWidth, $maxHeight], $size, $context]) + [0, 0];
        return self::constrain($width, $height, (int) $maxWidth, (int) $maxHeight);
    }

    /** An attachment's file for a size (image_get_intermediate_size), as image_get_intermediate_size leaves it; false for none. */
    public static function intermediate(mixed $postId, mixed $size = 'thumbnail'): mixed
    {
        $postId = (int) $postId;
        $meta = Attachments::metadata($postId);
        if (!is_array($meta) || !$size) {
            return false;
        }
        $data = Sizing::intermediate($meta, is_array($size) ? $size : (string) $size, Attachments::url($postId) ?: null, static fn (int $w, int $h, array $box): array => self::constrainForEditor($w, $h, $box), static fn (int $w, int $h, int $tw, int $th): bool => self::matchesRatio($w, $h, $tw, $th));
        return $data === null ? false : Runtime::hooks()->filter('image_get_intermediate_size', [$data, $postId, $size]);
    }

    /**
     * An image attachment at a size (image_downsize): its address, width,
     * height and whether that is a resized copy, unless image_downsize
     * answers first; false for no image.
     */
    public static function downsize(mixed $id, mixed $size = 'medium'): mixed
    {
        $post = Records::post((int) $id);
        if ($post === null || !Attachments::isImage($post->ID)) {
            return false;
        }
        $out = Runtime::hooks()->filter('image_downsize', [false, $post->ID, $size]);
        if ($out) {
            return $out;
        }
        $url = Attachments::url($post->ID);
        $meta = Attachments::metadata($post->ID);
        $width = 0;
        $height = 0;
        $isIntermediate = false;
        $basename = Text::basename((string) $url);
        $intermediate = self::intermediate($post->ID, $size);
        if ($intermediate) {
            $url = str_replace($basename, $intermediate['file'], (string) $url);
            $width = (int) $intermediate['width'];
            $height = (int) $intermediate['height'];
            $isIntermediate = true;
        } elseif ($size === 'thumbnail' && ($old = Sizing::oldThumbnail((string) Attachments::file($post->ID), is_array($meta) && is_string($meta['thumb'] ?? null) ? $meta['thumb'] : '')) !== null) {
            [$name, $width, $height] = $old;
            $url = str_replace($basename, $name, (string) $url);
            $isIntermediate = true;
        }
        if (!$width && !$height && is_array($meta) && isset($meta['width'], $meta['height'])) {
            $width = (int) $meta['width'];
            $height = (int) $meta['height'];
        }
        if ($url) {
            [$width, $height] = self::constrainForEditor($width, $height, $size);
            return [$url, $width, $height, $isIntermediate];
        }
        return false;
    }

    /** An attachment's image at a size, or its icon when asked (wp_get_attachment_image_src), as wp_get_attachment_image_src leaves it. */
    public static function src(mixed $attachmentId, mixed $size = 'thumbnail', mixed $icon = false): mixed
    {
        $image = self::downsize((int) $attachmentId, $size);
        if (!$image && $icon) {
            $src = Icons::forType((int) $attachmentId, '.png');
            $image = $src ? [$src, 64, 64, false] : false;
        }
        return Runtime::hooks()->filter('wp_get_attachment_image_src', [$image, $attachmentId, $size, $icon]);
    }

    /**
     * A named size's dimensions from an image's metadata
     * (_wp_get_image_size_from_meta): full for the original; false for one
     * it lacks.
     */
    public static function sizeFromMeta(mixed $sizeName, mixed $imageMeta): mixed
    {
        if ($sizeName === 'full') {
            return [abs((int) $imageMeta['width']), abs((int) $imageMeta['height'])];
        }
        if (!empty($imageMeta['sizes'][$sizeName])) {
            return [abs((int) $imageMeta['sizes'][$sizeName]['width']), abs((int) $imageMeta['sizes'][$sizeName]['height'])];
        }
        return false;
    }

    /** The srcset of an image's sizes with its ratio (wp_calculate_image_srcset), as wp_calculate_image_srcset leaves the sources; false for none. */
    public static function srcset(mixed $sizeArray, mixed $imageSrc, mixed $imageMeta, mixed $attachmentId = 0): mixed
    {
        $hooks = Runtime::hooks();
        $imageMeta = $hooks->filter('wp_calculate_image_srcset_meta', [$imageMeta, $sizeArray, $imageSrc, $attachmentId]);
        if (!is_array($imageMeta)) {
            return false;
        }
        $sources = Sizing::sources([(int) ($sizeArray[0] ?? 0), (int) ($sizeArray[1] ?? 0)], (string) $imageSrc, $imageMeta, static fn (int $sw, int $sh, int $tw, int $th): bool => self::matchesRatio($sw, $sh, $tw, $th), (string) Attachments::uploadDir()['baseurl'], static fn (array $size): int => (int) $hooks->filter('max_srcset_image_width', [2048, $size]));
        if ($sources === []) {
            return false;
        }
        $sources = $hooks->filter('wp_calculate_image_srcset', [$sources, $sizeArray, $imageSrc, $imageMeta, $attachmentId]);
        return Sizing::srcset(is_array($sources) ? $sources : []) ?? false;
    }

    /** The sizes attribute for an image's width (wp_calculate_image_sizes), as wp_calculate_image_sizes leaves it; false without a width. */
    public static function sizesAttribute(mixed $size, mixed $imageSrc = null, mixed $imageMeta = null, mixed $attachmentId = 0): mixed
    {
        $width = 0;
        if (is_array($size)) {
            $width = abs((int) $size[0]);
        } elseif (is_string($size)) {
            if (!$imageMeta && $attachmentId) {
                $imageMeta = Attachments::metadata($attachmentId);
            }
            if (is_array($imageMeta)) {
                $dimensions = self::sizeFromMeta($size, $imageMeta);
                if ($dimensions) {
                    $width = abs((int) $dimensions[0]);
                }
            }
        }
        if (!$width) {
            return false;
        }
        $sizes = sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $width);
        return Runtime::hooks()->filter('wp_calculate_image_sizes', [$sizes, $size, $imageSrc, $imageMeta, $attachmentId]);
    }

    /** Whether a sizes list starts with auto, alone or before a comma, in any case (wp_sizes_attribute_includes_valid_auto). */
    public static function startsWithAuto(mixed $sizes): bool
    {
        return preg_match('/^auto(\s*,|$)/i', trim((string) $sizes)) === 1;
    }

    /** The width and height attributes for an image (image_hwstring), each when set. */
    public static function dimensionAttributes(mixed $width, mixed $height): string
    {
        $out = '';
        if ($width) {
            $out .= 'width="' . (int) $width . '" ';
        }
        if ($height) {
            $out .= 'height="' . (int) $height . '" ';
        }
        return $out;
    }

    /** Whether lazy loading is on for a tag in a context (wp_lazy_loading_enabled): images and iframes, as wp_lazy_loading_enabled leaves it. */
    public static function lazyLoading(mixed $tagName, mixed $context): mixed
    {
        return Runtime::hooks()->filter('wp_lazy_loading_enabled', [$tagName === 'img' || $tagName === 'iframe', $tagName, $context]);
    }

    /**
     * The loading attributes an image or iframe earns
     * (wp_get_loading_optimization_attributes), unless a plugin answers
     * first: decoding async for an image; with both dimensions set, the
     * caller's own priority and loading, else where it sits.
     */
    public static function loadingAttributes(mixed $tagName, mixed $attr, mixed $context): mixed
    {
        $hooks = Runtime::hooks();
        $answer = $hooks->filter('pre_wp_get_loading_optimization_attributes', [false, $tagName, $attr, $context]);
        if (is_array($answer)) {
            return $answer;
        }
        $optimization = [];
        if ($tagName !== 'img' && $tagName !== 'iframe' || $context === 'template' || ($context !== 'the_content' && $hooks->doing('the_content'))) {
            return $hooks->filter('wp_get_loading_optimization_attributes', [$optimization, $tagName, $attr, $context]);
        }
        if ($tagName === 'img') {
            $optimization['decoding'] = $attr['decoding'] ?? 'async';
        }
        if (isset($attr['width'], $attr['height'])) {
            $priority = in_array($attr['fetchpriority'] ?? null, ['high', 'low', 'auto'], true) ? $attr['fetchpriority'] : null;
            if ($priority === 'low' || $priority === 'auto') {
                $optimization['fetchpriority'] = $priority;
            }
            $optimization = $priority === 'low' ? $optimization : (self::asked((string) $tagName, (array) $attr, $priority, $optimization) ?? self::byPlace((string) $tagName, (array) $attr, (string) $context, $optimization));
        }
        return $hooks->filter('wp_get_loading_optimization_attributes', [$optimization, $tagName, $attr, $context]);
    }

    /**
     * Fetch priority high for an image large enough to deserve it, while no
     * other image has taken it.
     *
     * @param array<string, mixed> $attr
     * @return array<string, string>
     */
    public static function earnedPriority(string $tagName, array $attr): array
    {
        if ($tagName !== 'img') {
            return [];
        }
        $pixels = (int) ($attr['width'] ?? 0) * (int) ($attr['height'] ?? 0);
        return $pixels >= (int) Runtime::hooks()->filter('wp_min_priority_img_pixels', [50000]) && RenderState::current()->claimPriority() ? ['fetchpriority' => 'high'] : [];
    }

    /**
     * The loading attributes and the srcset and sizes pair an attachment's
     * image carries unless the caller set them.
     *
     * @param array<string, mixed> $attr
     * @return array<string, mixed>
     */
    public static function imageAttributes(int $attachmentId, array $attr, string $src, int $width, int $height, string $context = 'wp_get_attachment_image'): array
    {
        $loading = self::loadingAttributes('img', $attr + ['width' => $width, 'height' => $height], $context);
        foreach (['loading', 'fetchpriority'] as $key) {
            if (array_key_exists($key, $attr) && !$attr[$key]) {
                unset($loading[$key]);
            }
        }
        $attr = array_merge($attr, $loading);
        $meta = empty($attr['srcset']) ? Attachments::metadata($attachmentId) : null;
        if (is_array($meta)) {
            $srcset = self::srcset([$width, $height], $src, $meta, $attachmentId);
            $sizes = self::sizesAttribute([$width, $height], $src, $meta, $attachmentId);
            if ($srcset && ($sizes || !empty($attr['sizes']))) {
                $attr['srcset'] = $srcset;
                $attr['sizes'] = empty($attr['sizes']) ? $sizes : $attr['sizes'];
            }
        }
        if (($attr['loading'] ?? '') === 'lazy' && !empty($attr['sizes']) && is_string($attr['sizes']) && !self::startsWithAuto($attr['sizes']) && Runtime::hooks()->filter('wp_img_tag_add_auto_sizes', [true])) {
            $attr['sizes'] = 'auto, ' . $attr['sizes'];
        }
        return $attr;
    }

    /**
     * An attachment's img element at a size (wp_get_attachment_image): its
     * class, alt text, loading attributes and srcset, through
     * wp_get_attachment_image_attributes and wp_get_attachment_image; empty
     * for none.
     */
    public static function image(mixed $attachmentId, mixed $size = 'thumbnail', mixed $icon = false, mixed $attr = ''): mixed
    {
        $attachmentId = (int) $attachmentId;
        $image = self::src($attachmentId, $size, $icon);
        if (!$image) {
            return '';
        }
        [$src, $width, $height] = $image;
        $sizeClass = is_array($size) ? implode('x', $size) : $size;
        $attr = Lists::args($attr, ['src' => $src, 'class' => "attachment-{$sizeClass} size-{$sizeClass}", 'alt' => trim(strip_tags((string) MetaData::get('post', $attachmentId, '_wp_attachment_image_alt', true)))]);
        $hooks = Runtime::hooks();
        $context = $hooks->filter('wp_get_attachment_image_context', ['wp_get_attachment_image']);
        $attr = self::imageAttributes($attachmentId, $attr, (string) $src, (int) $width, (int) $height, (string) $context);
        $attr = $hooks->filter('wp_get_attachment_image_attributes', [$attr, Records::post($attachmentId), $size]);
        $attr = array_map(static fn (mixed $value): string => Escape::attr($value), array_filter($attr, static fn (mixed $value): bool => $value !== false && $value !== null));
        $html = rtrim('<img ' . self::dimensionAttributes($width, $height));
        foreach ($attr as $name => $value) {
            $html .= " {$name}=\"{$value}\"";
        }
        return $hooks->filter('wp_get_attachment_image', [$html . ' />', $attachmentId, $size, $icon, $attr]);
    }

    /** The site icon's address at a size (get_site_icon_url), the address given when the site has none; as get_site_icon_url leaves it. */
    public static function siteIconUrl(mixed $size = 512, mixed $url = '', mixed $blogId = 0): mixed
    {
        $iconId = (int) Runtime::options()->filtered('site_icon');
        if ($iconId > 0) {
            $data = self::src($iconId, [$size, $size]);
            if ($data) {
                $url = $data[0];
            }
        }
        return Runtime::hooks()->filter('get_site_icon_url', [$url, $size, $blogId]);
    }

    /** Whether the site has an icon (has_site_icon). */
    public static function hasSiteIcon(mixed $blogId = 0): bool
    {
        return (bool) self::siteIconUrl(512, '', $blogId);
    }

    /**
     * What a caller's own fetchpriority high or loading settles; null when it
     * named neither. Only an image takes high priority, and an element that
     * names a loading other than lazy is never made lazy.
     *
     * @param array<string, mixed> $attr
     * @param array<string, mixed> $optimization
     * @return array<string, mixed>|null
     */
    private static function asked(string $tagName, array $attr, ?string $priority, array $optimization): ?array
    {
        $loading = $attr['loading'] ?? null;
        if ($priority === 'high') {
            if ($tagName === 'img') {
                $optimization['fetchpriority'] = 'high';
            }
            if ($loading === 'lazy') {
                if ($tagName === 'img') {
                    Notices::doingItWrong('wp_get_loading_optimization_attributes', Gettext::text('An image should not be lazy-loaded and marked as high priority at the same time.'), '6.3.0');
                }
                return $optimization + ['loading' => 'lazy'];
            }
            if ($tagName === 'img') {
                RenderState::current()->closePriority();
            }
            return $optimization;
        }
        if ($loading === null) {
            return null;
        }
        return $loading === 'lazy' ? $optimization + ['loading' => 'lazy'] : $optimization + self::earnedPriority($tagName, $attr);
    }

    /**
     * Where an element sits settles the rest. One in a header context is in
     * view: never lazy, one of the eager images. Inside a page's template, or
     * its main loop, an image shares the engine's budget: three eager images,
     * the first one large enough to deserve it high priority. Anything else
     * waits, where lazy loading is on.
     *
     * @param array<string, mixed> $attr
     * @param array<string, mixed> $optimization
     * @return array<string, mixed>
     */
    private static function byPlace(string $tagName, array $attr, string $context, array $optimization): array
    {
        $header = Runtime::hooks()->filter('wp_loading_optimization_force_header_contexts', [['template_part_header' => true, 'get_header_image_tag' => true]]);
        if (is_array($header) && isset($header[$context])) {
            RenderState::current()->nextImage();
            return $optimization + self::earnedPriority($tagName, $attr);
        }
        $query = MainQuery::current();
        if ($tagName === 'img' && ((RenderState::current()->inside('template') && RenderState::current()->depth() > 0) || ((bool) $query->in_the_loop && $query->is_main_query())) && !(defined('REST_REQUEST') && REST_REQUEST) && RenderState::current()->nextImage() <= 3) {
            return $optimization + self::earnedPriority($tagName, $attr);
        }
        return self::lazyLoading($tagName, $context) ? $optimization + ['loading' => 'lazy'] : $optimization;
    }
}
