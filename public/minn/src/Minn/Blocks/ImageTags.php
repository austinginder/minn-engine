<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Closure;
use Minn\Runtime\AttachmentImages;
use Minn\Runtime\Runtime;

use Minn\Content\Posts;
use Minn\Media\Metadata;
use Minn\Media\Uploads;
use Minn\Html;

/**
 * The attributes the reference adds to an <img> that carries a
 * wp-image-{id} class: loading, decoding, width and height (prepended, in
 * that order), and srcset plus sizes (appended). The srcset lists the
 * displayed size first, then every same-ratio size in stored order, then
 * the original.
 */
final readonly class ImageTags
{
    /** @param Closure(): bool $front whether the images are on a front-end page, where the loading budget applies */
    public function __construct(
        private Posts $posts,
        private Uploads $uploads,
        private Closure $front,
    ) {
    }

    /** Rewrites every wp-image-* <img> in a fragment; other images pass through. */
    public function enrich(string $html): string
    {
        return $this->rewrite($html, false, true);
    }

    /** The same for a gallery: every image also carries its data-id. */
    public function enrichGallery(string $html): string
    {
        return $this->rewrite($html, true, true);
    }

    /** The same for a plugin block's output, where the reference adds no sizes="auto". */
    public function enrichPlugin(string $html): string
    {
        return $this->rewrite($html, false, false);
    }

    /**
     * Every <img> and <iframe> of a content fragment as the reference's
     * wp_filter_content_tags leaves it for the context named (probe
     * content-tags): whether iframes may load lazily asked first; each tag
     * fitted out once, however often it appears. An attachment's image
     * (a wp-image class) is given the width and height it lacks and its
     * srcset and sizes; every image the loading, decoding and priority its
     * context earns (wp_get_loading_optimization_attributes), "auto" sizes
     * when lazy, then wp_content_img_tag. An iframe that names no loading of
     * its own is given its loading, while iframes may load lazily.
     */
    public function content(string $html, mixed $context = null): string
    {
        $frames = (bool) AttachmentImages::lazyLoading('iframe', $context);
        if (preg_match_all('/<(img|iframe)\s[^>]+>/i', $html, $found) === 0) {
            return $html;
        }
        $fitted = [];
        foreach ($found[0] as $i => $tag) {
            if (isset($fitted[$tag])) {
                continue;
            }
            if (strtolower($found[1][$i]) === 'img') {
                $fitted[$tag] = $this->contentImage($tag, $context);
            } elseif ($frames && !str_contains($tag, ' loading=')) {
                $fitted[$tag] = self::frame($tag, $context);
            }
        }
        return strtr($html, $fitted);
    }

    /**
     * An <img> given what it lacks of decoding, loading and fetchpriority, as
     * the context earns them (wp_img_tag_add_loading_optimization_attrs):
     * an image with no source given none of them; decoding through
     * wp_img_tag_add_decoding_attr; for an image that writes a width and a
     * height, lazy loading asked, then a loading through
     * wp_img_tag_add_loading_attr (lazy or eager as given, any other
     * answer keeping the loading the context earned, a false one none); then
     * a high priority. Each is placed
     * straight after "<img", so the last added reads first.
     */
    public static function optimized(string $image, mixed $context): string
    {
        $attr = ['src' => self::attributeOf($image, 'src')] + self::sizeOf($image) + ['loading' => self::attributeOf($image, 'loading'), 'fetchpriority' => self::attributeOf($image, 'fetchpriority'), 'decoding' => self::attributeOf($image, 'decoding')];
        $optimization = (array) AttachmentImages::loadingAttributes('img', $attr, $context);
        // An image whose source is not written src="..." is given nothing (probe content-tags).
        if (!str_contains($image, ' src="')) {
            return $image;
        }
        $hooks = Runtime::hooks();
        if ($attr['decoding'] === null) {
            $decoding = $hooks->filter('wp_img_tag_add_decoding_attr', [$optimization['decoding'] ?? false, $image, $context]);
            if (in_array($decoding, ['async', 'sync', 'auto'], true)) {
                $image = (string) preg_replace('/<img\b/i', '<img decoding="' . $decoding . '"', $image, 1);
            }
        }
        // Only a width and a height written width="..." height="..." ask for a loading, whatever they hold.
        if (str_contains($image, ' width="') && str_contains($image, ' height="')) {
            AttachmentImages::lazyLoading('img', $context);
            if ($attr['loading'] === null) {
                // lazy or eager is taken as given and nothing drops it; any other answer keeps the loading the context earned.
                $loading = $hooks->filter('wp_img_tag_add_loading_attr', [$optimization['loading'] ?? false, $image, $context]);
                $loading = in_array($loading, ['lazy', 'eager'], true) ? $loading : ($loading ? ($optimization['loading'] ?? null) : null);
                if ($loading !== null) {
                    $image = (string) preg_replace('/<img\b/i', '<img loading="' . $loading . '"', $image, 1);
                }
            }
        }
        if ($attr['fetchpriority'] === null && ($optimization['fetchpriority'] ?? null) === 'high') {
            $image = (string) preg_replace('/<img\b/i', '<img fetchpriority="high"', $image, 1);
        }
        return $image;
    }

    /** One content image fitted out as wp_filter_content_tags fits it, then through wp_content_img_tag. */
    private function contentImage(string $tag, mixed $context): string
    {
        $id = self::attachmentOf($tag);
        if ($id > 0 && preg_match('/\sclass="[^"]*\bwp-image-\d+\b/', $tag) === 1) {
            $hooks = Runtime::hooks();
            $size = self::sizeOf($tag);
            if (($size['width'] === null || $size['height'] === null) && $hooks->filter('wp_img_tag_add_width_and_height_attr', [true, $tag, $context, $id])) {
                $tag = $this->dimensioned($tag, $id);
            }
            if (!str_contains($tag, ' srcset=') && $hooks->filter('wp_img_tag_add_srcset_and_sizes_attr', [true, $tag, $context, $id]) === true) {
                $tag = $this->sized($tag);
            }
        }
        $tag = self::optimized($tag, $context);
        if (Runtime::hooks()->filter('wp_img_tag_add_auto_sizes', [true])) {
            $tag = self::lazySizes($tag);
        }
        return (string) Runtime::hooks()->filter('wp_content_img_tag', [$tag, $context, $id]);
    }

    /** An iframe given the loading its context earns (wp_iframe_tag_add_loading_attr), when it has a width and a height. */
    private static function frame(string $iframe, mixed $context): string
    {
        $has = static fn (string $name): ?int => str_contains($iframe, ' ' . $name . '="') ? 100 : null;
        $attr = ['width' => $has('width'), 'height' => $has('height'), 'loading' => null];
        $optimization = (array) AttachmentImages::loadingAttributes('iframe', $attr, $context);
        if ($attr['width'] === null || $attr['height'] === null) {
            return $iframe;
        }
        $loading = Runtime::hooks()->filter('wp_iframe_tag_add_loading_attr', [$optimization['loading'] ?? false, $iframe, $context]);
        return $loading ? (string) preg_replace('/<iframe\b/i', '<iframe loading="' . (in_array($loading, ['lazy', 'eager'], true) ? $loading : 'lazy') . '"', $iframe, 1) : $iframe;
    }

    /** An attachment's image given the width and height its source names, when it is one of the attachment's files. */
    private function dimensioned(string $tag, int $id): string
    {
        $found = $this->candidates($tag, $id);
        return $found === null ? $tag : (string) preg_replace('/^<img\s/i', '<img width="' . $found[1] . '" height="' . $found[2] . '" ', $tag, 1);
    }

    /** A lazy image's sizes led by "auto", unless they already are. */
    private static function lazySizes(string $tag): string
    {
        if (preg_match('/\sloading=["\']?lazy\b/i', $tag) !== 1 || preg_match('/\ssizes="([^"]*)"/', $tag, $m) !== 1 || preg_match('/^\s*auto\s*(,|$)/i', $m[1]) === 1) {
            return $tag;
        }
        return str_replace($m[0], ' sizes="auto, ' . $m[1] . '"', $tag);
    }

    /**
     * An attribute's value as written after a space, or null: its name in
     * lower case (any case for src), as the reference reads it.
     */
    private static function attributeOf(string $tag, string $name): ?string
    {
        return preg_match('/ ' . $name . '=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/' . ($name === 'src' ? 'i' : ''), $tag, $m) === 1 ? (string) ($m[3] ?? $m[2] ?? $m[1] ?? '') : null;
    }

    /** An image's width and height, each as a whole number when it is written as one. @return array{width: ?int, height: ?int} */
    private static function sizeOf(string $tag): array
    {
        $size = [];
        foreach (['width', 'height'] as $name) {
            $size[$name] = preg_match('/ ' . $name . '=["\']([0-9]+)["\']/', $tag, $m) === 1 ? (int) $m[1] : null;
        }
        return $size;
    }

    /** A template's image: an attachment's gets its srcset and sizes in place, nothing more; any other is left as it is. */
    private function sized(string $tag): string
    {
        $id = self::attachmentOf($tag);
        if ($id < 1 || str_contains($tag, ' srcset=') || preg_match('/\sclass="[^"]*\bwp-image-\d+\b/', $tag) !== 1) {
            return $tag;
        }
        $found = $this->candidates($tag, $id);
        if ($found === null) {
            return $tag;
        }
        [$candidates, $width, $height, $meta, $src] = $found;
        $srcset = self::srcsetAttributes($candidates, $width, false, $id, $meta, $src, $height);
        return $srcset === '' ? $tag : self::closeWith($tag, $srcset);
    }

    /**
     * Content the engine fitted out itself, with what the_content's other
     * callbacks added on the way (an attachment's own link, a plugin's
     * image): an image still without its loading attributes is fitted as
     * the rest were, then each is offered to wp_content_img_tag, as
     * wp_filter_content_tags would.
     */
    public function finished(string $html, string $context): string
    {
        return (string) preg_replace_callback('/<img\s[^>]*>/i', function (array $m) use ($context): string {
            $done = RenderState::current()->fittedImage($m[0]) || str_contains($m[0], ' decoding=') || str_contains($m[0], ' loading=');
            $tag = $done ? self::autoSizedWhenLazy($m[0]) : $this->fit($m[0]);
            return Runtime::hooks()->has('wp_content_img_tag') ? (string) Runtime::hooks()->filter('wp_content_img_tag', [$tag, $context, self::attachmentOf($tag)]) : $tag;
        }, $html);
    }

    /** One content image fitted out: an attachment's (by its wp-image class) in full, any other with its loading attributes. */
    private function fit(string $tag): string
    {
        $id = self::attachmentOf($tag);
        $enriched = $id > 0 && preg_match('/\sclass="[^"]*\bwp-image-\d+\b/', $tag) === 1 ? $this->enrichTag($tag, $id, false, true) : $tag;
        return $enriched !== $tag ? $enriched : $this->plainImage($tag);
    }

    /** The attachment an image names in its wp-image-{id} class, or 0. */
    /** The tag with its attributes added before the close; a callback, so a $0 in an address stays text. */
    private static function closeWith(string $tag, string $attributes): string
    {
        return (string) preg_replace_callback('/\s*\/?>$/', static fn (): string => $attributes . ' />', $tag, 1);
    }

    private static function attachmentOf(string $tag): int
    {
        return preg_match('/wp-image-(\d+)/i', $tag, $id) === 1 ? (int) $id[1] : 0;
    }

    /** An image that is not an attachment's: decoding always, loading when its size is known and it has none. */
    private function plainImage(string $tag): string
    {
        $width = preg_match('/\swidth="?(\d+)/', $tag, $w) === 1 ? (int) $w[1] : 0;
        $height = preg_match('/\sheight="?(\d+)/', $tag, $h) === 1 ? (int) $h[1] : 0;
        if ($width > 0 && $height > 0 && !str_contains($tag, ' loading=')) {
            [$prefix, $lazy] = $this->loadingPrefix($width, $height);
            $tag = (string) preg_replace('/^<img\s/i', '<img ' . $prefix . ' ', $tag, 1);
            return $lazy ? self::autoSized($tag) : $tag;
        }
        return str_contains($tag, ' decoding=') ? $tag : (string) preg_replace('/^<img\s/i', '<img decoding="async" ', $tag, 1);
    }

    /** A lazy image's sizes led by "auto" (wp_img_tag_add_auto_sizes), unless they already are or a plugin says not to (probe rest-attachments-controller). */
    private static function autoSized(string $tag): string
    {
        if (preg_match('/\ssizes="([^"]*)"/', $tag, $m) !== 1 || preg_match('/^\s*auto\s*(,|$)/i', $m[1]) === 1) {
            return $tag;
        }
        if (Runtime::hooks()->has('wp_img_tag_add_auto_sizes') && !Runtime::hooks()->filter('wp_img_tag_add_auto_sizes', [true])) {
            return $tag;
        }
        return str_replace($m[0], ' sizes="auto, ' . $m[1] . '"', $tag);
    }

    /** An image a plugin made lazy after it was fitted (Elementor's image loading does) gets "auto" sizes on the next pass, as on the reference. */
    private static function autoSizedWhenLazy(string $tag): string
    {
        return preg_match('/\sloading=["\']?lazy\b/i', $tag) === 1 ? self::autoSized($tag) : $tag;
    }

    private function rewrite(string $html, bool $withDataId, bool $autoSizes): string
    {
        return preg_replace_callback(
            '/<img\s[^>]*?class="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*\/?>/',
            fn (array $m) => $this->enrichTag($m[0], (int) $m[1], $withDataId, $autoSizes),
            $html,
        );
    }

    /**
     * On a page the first big-enough content image is fetched eagerly with
     * high priority, the next few are eager, and the rest lazy; only lazy
     * images carry the "auto" sizes hint. In a REST response every image is
     * lazy. A plugin that turns lazy loading off (wp_lazy_loading_enabled;
     * Elementor's image loading does) leaves every image without it.
     */
    private function loadingPrefix(int $width = 0, int $height = 0): array
    {
        $lazy = self::lazyAllowed('img') ? ['loading="lazy" decoding="async"', true] : ['decoding="async"', false];
        if (!($this->front)()) {
            return $lazy;
        }
        // Three eager images per page, every <img> on the page counting toward
        // the budget; the first eager image large enough to be worth the
        // network's attention is fetched with high priority, and a thumbnail
        // or an avatar is not, so the flag can fall to a later image.
        $seen = RenderState::current()->nextImage();
        if ($seen > AttachmentImages::omitThreshold()) {
            return $lazy;
        }
        $large = $width * $height >= self::minimumPriorityPixels();
        return $large && RenderState::current()->claimPriority() ? ['fetchpriority="high" decoding="async"', false] : ['decoding="async"', false];
    }

    /** An image that names its own loading keeps it: an eager one large enough is fetched first, every one is decoded asynchronously. @return array{0: string, 1: bool} */
    private function givenLoading(string $loading, int $width, int $height): array
    {
        if ($loading === 'eager' && $width * $height >= self::minimumPriorityPixels() && RenderState::current()->claimPriority()) {
            return ['fetchpriority="high" decoding="async"', false];
        }
        return ['decoding="async"', false];
    }

    /** Whether content images or iframes may load lazily (wp_lazy_loading_enabled, asked for the_content). */
    private static function lazyAllowed(string $tagName): bool
    {
        return (bool) AttachmentImages::lazyLoading($tagName, 'the_content');
    }

    /** The area an image must cover before it is worth fetching first. */
    private static function minimumPriorityPixels(): int
    {
        $minimum = 50000;
        return (int) Runtime::hooks()->filter('wp_min_priority_img_pixels', [$minimum]);
    }

    private function enrichTag(string $tag, int $attachmentId, bool $withDataId, bool $autoSizes): string
    {
        if (str_contains($tag, ' srcset=')) {
            // Already enriched by an inner image block; a gallery still adds its data-id.
            if ($withDataId && !str_contains($tag, ' data-id=')) {
                return preg_replace('/(\sheight="\d+")/', '$1 data-id="' . $attachmentId . '"', $tag, 1);
            }
            return $tag;
        }
        $found = $this->candidates($tag, $attachmentId);
        if ($found === null) {
            return $tag;
        }
        [$candidates, $width, $height, $meta, $src] = $found;
        // In a template part the part's own content pass gives its images their loading, for the part's context, as the reference does.
        [$loading, $auto] = match (true) {
            str_starts_with((string) RenderState::current()->contentContext(), 'template_part_') => ['', false],
            preg_match('/\sloading="(\w+)"/', $tag, $given) === 1 => $this->givenLoading($given[1], $width, $height),
            default => $this->loadingPrefix($width, $height),
        };
        $prefix = ltrim($loading . ' width="' . $width . '" height="' . $height . '"' . ($withDataId ? ' data-id="' . $attachmentId . '"' : ''));
        $tag = preg_replace('/^<img\s/', '<img ' . $prefix . ' ', $tag, 1);
        $srcset = self::srcsetAttributes($candidates, $width, $auto && $autoSizes, $attachmentId, $meta, $src, $height);
        return $srcset === '' ? $tag : self::closeWith($tag, $srcset);
    }

    /**
     * The srcset candidates for an attachment's image, keyed by width: the
     * shown size first, then same-ratio sizes in stored order, then the
     * original; with the shown width and height, the metadata and the src.
     * Null when the image is not one of the attachment's files.
     *
     * @return array{0: array<int, string>, 1: int, 2: int, 3: array<string, mixed>, 4: string}|null
     */
    private function candidates(string $tag, int $attachmentId): ?array
    {
        $meta = Metadata::parse($this->posts->meta($attachmentId, '_wp_attachment_metadata'));
        if ($meta['file'] === '' || !preg_match('/\ssrc="([^"]+)"/', $tag, $srcMatch)) {
            return null;
        }
        $src = $srcMatch[1];
        $baseUrl = rtrim($this->uploads->baseUrl() . '/' . dirname($meta['file']), '/.');
        $fullUrl = $this->uploads->urlFor($meta['file']);
        $ratio = $meta['width'] > 0 ? $meta['height'] / $meta['width'] : 0.0;

        // Candidates keyed by width: the shown size first, then same-ratio
        // sizes in stored order, then the original.
        $candidates = [];
        $shown = null;
        if ($src === $fullUrl) {
            $shown = [$meta['width'], $meta['height']];
            $candidates[$meta['width']] = $fullUrl;
        }
        foreach ($meta['sizes'] as $size) {
            if ($size['width'] < 1 || abs($size['height'] - $size['width'] * $ratio) > 1) {
                continue;
            }
            $url = $baseUrl . '/' . $size['file'];
            if ($url === $src) {
                $shown = [$size['width'], $size['height']];
                $candidates = [$size['width'] => $url] + $candidates;
                continue;
            }
            $candidates[$size['width']] = $url;
        }
        if ($shown === null) {
            return null;
        }
        if ($src !== $fullUrl) {
            $candidates[$meta['width']] = $fullUrl;
        }
        return [$candidates, $shown[0], $shown[1], $meta, $src];
    }

    /**
     * A srcset needs a choice: one candidate yields no srcset and no sizes.
     * With the runtime up, plugin code filters the candidates and the sizes
     * hint the way the reference lets it (wp_calculate_image_srcset,
     * wp_calculate_image_sizes).
     */
    private static function srcsetAttributes(array $candidates, int $width, bool $auto, int $attachmentId = 0, array $meta = [], string $src = '', int $height = 0): string
    {
        if (Runtime::booted() && $attachmentId > 0) {
            $sources = [];
            foreach ($candidates as $w => $url) {
                $sources[$w] = ['url' => $url, 'descriptor' => 'w', 'value' => $w];
            }
            $filtered = Runtime::hooks()->filter('wp_calculate_image_srcset', [$sources, [$width, $height], $src, $meta, $attachmentId]);
            $candidates = [];
            foreach (is_array($filtered) ? $filtered : [] as $source) {
                if (is_array($source) && isset($source['url'], $source['value'])) {
                    $candidates[(int) $source['value']] = (string) $source['url'];
                }
            }
        }
        if (count($candidates) < 2) {
            return '';
        }
        $srcset = [];
        foreach ($candidates as $w => $url) {
            $srcset[] = $url . ' ' . $w . 'w';
        }
        $sizes = '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px';
        if (Runtime::booted() && $attachmentId > 0) {
            $sizes = (string) Runtime::hooks()->filter('wp_calculate_image_sizes', [$sizes, [$width, $height], $src, $meta, $attachmentId]);
        }
        return ' srcset="' . implode(', ', $srcset) . '" sizes="' . ($auto ? 'auto, ' : '') . $sizes . '"';
    }

    /**
     * A post's featured image at full size, in the reference's attribute
     * order (dimensions, source, class, alt, style, then the loading
     * attributes and the srcset). Empty when the attachment has no file.
     */
    public function featured(int $attachmentId, string $alt, string $style): string
    {
        $meta = Metadata::parse($this->posts->meta($attachmentId, '_wp_attachment_metadata'));
        $file = $meta['file'] !== '' ? $meta['file'] : (string) ($this->posts->meta($attachmentId, '_wp_attached_file') ?? '');
        if ($file === '') {
            return '';
        }
        $fullUrl = $this->uploads->urlFor($file);
        $baseUrl = rtrim($this->uploads->baseUrl() . '/' . dirname($file), '/.');
        $ratio = $meta['width'] > 0 ? $meta['height'] / $meta['width'] : 0.0;
        $candidates = [$meta['width'] => $fullUrl];
        foreach ($meta['sizes'] as $size) {
            if ($size['width'] >= 1 && abs($size['height'] - $size['width'] * $ratio) <= 1) {
                $candidates[$size['width']] = $baseUrl . '/' . $size['file'];
            }
        }
        [$loading, $auto] = $this->loadingPrefix((int) $meta['width'], (int) $meta['height']);
        $loading = match ($loading) {
            'fetchpriority="high" decoding="async"' => 'decoding="async" fetchpriority="high"',
            'loading="lazy" decoding="async"' => 'decoding="async" loading="lazy"',
            default => $loading,
        };
        $dimensions = $meta['width'] > 0 ? 'width="' . $meta['width'] . '" height="' . $meta['height'] . '" ' : '';
        return '<img ' . $dimensions . 'src="' . Html::attr($fullUrl) . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" alt="' . Html::attr($alt) . '" style="' . Html::attr($style) . '" ' . $loading
            . self::srcsetAttributes($candidates, $meta['width'], $auto, $attachmentId, $meta, $fullUrl, (int) $meta['height']) . ' />';
    }
}
