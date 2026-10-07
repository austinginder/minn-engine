<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Closure;
use Minn\Runtime\Runtime;

use Minn\Content\Posts;
use Minn\Media\Metadata;
use Minn\Media\Uploads;
use Minn\Support\Html;

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
     * wp_filter_content_tags leaves it: an attachment's image is enriched as
     * above (a loading it already has is kept, and an eager large one is
     * fetched first); any other image is decoded asynchronously and, when its
     * size is known, loaded lazily or by the page's budget; an iframe with a
     * size is loaded lazily.
     */
    public function content(string $html, mixed $context = null): string
    {
        return (string) preg_replace_callback('/<(img|iframe)\s[^>]*>/i', function (array $m) use ($context): string {
            if (strtolower($m[1]) === 'iframe') {
                return self::lazyFrame($m[0]);
            }
            $id = self::attachmentOf($m[0]);
            $enriched = $id > 0 && preg_match('/\sclass="[^"]*\bwp-image-\d+\b/', $m[0]) === 1 ? $this->enrichTag($m[0], $id, false, true) : $m[0];
            $fitted = $enriched !== $m[0] ? $enriched : $this->plainImage($m[0]);
            return (string) \apply_filters('wp_content_img_tag', $fitted, $context, $id);
        }, $html);
    }

    /**
     * Content whose images are fitted out already, each image offered to
     * wp_content_img_tag in a context, as wp_filter_content_tags offers them.
     */
    public function offered(string $html, string $context): string
    {
        if (!\has_filter('wp_content_img_tag')) {
            return $html;
        }
        return (string) preg_replace_callback('/<img\s[^>]*>/i', static fn (array $m): string => (string) \apply_filters('wp_content_img_tag', $m[0], $context, self::attachmentOf($m[0])), $html);
    }

    /** The attachment an image names in its wp-image-{id} class, or 0. */
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
            return (string) preg_replace('/^<img\s/i', '<img ' . $this->loadingPrefix($width, $height)[0] . ' ', $tag, 1);
        }
        return str_contains($tag, ' decoding=') ? $tag : (string) preg_replace('/^<img\s/i', '<img decoding="async" ', $tag, 1);
    }

    /** An iframe with a width and a height, and no loading of its own, loads lazily. */
    private static function lazyFrame(string $tag): string
    {
        if (str_contains($tag, ' loading=') || preg_match('/\swidth=/', $tag) !== 1 || preg_match('/\sheight=/', $tag) !== 1) {
            return $tag;
        }
        return (string) preg_replace('/^<iframe\s/i', '<iframe loading="lazy" ', $tag, 1);
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
     * lazy.
     */
    private function loadingPrefix(int $width = 0, int $height = 0): array
    {
        if (!($this->front)()) {
            return ['loading="lazy" decoding="async"', true];
        }
        // Three eager images per page, every <img> on the page counting toward
        // the budget; the first eager image large enough to be worth the
        // network's attention is fetched with high priority, and a thumbnail
        // or an avatar is not, so the flag can fall to a later image.
        $seen = RenderState::current()->nextImage();
        if ($seen > 3) {
            return ['loading="lazy" decoding="async"', true];
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

    /** The area an image must cover before it is worth fetching first. */
    private static function minimumPriorityPixels(): int
    {
        $minimum = 50000;
        return Runtime::booted() ? (int) Runtime::hooks()->filter('wp_min_priority_img_pixels', [$minimum]) : $minimum;
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
        $meta = Metadata::parse($this->posts->meta($attachmentId, '_wp_attachment_metadata'));
        if ($meta['file'] === '' || !preg_match('/\ssrc="([^"]+)"/', $tag, $srcMatch)) {
            return $tag;
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
            return $tag;
        }
        if ($src !== $fullUrl) {
            $candidates[$meta['width']] = $fullUrl;
        }
        [$width, $height] = $shown;
        [$loading, $auto] = preg_match('/\sloading="(\w+)"/', $tag, $given) === 1 ? $this->givenLoading($given[1], $width, $height) : $this->loadingPrefix($width, $height);
        $prefix = $loading . ' width="' . $width . '" height="' . $height . '"' . ($withDataId ? ' data-id="' . $attachmentId . '"' : '');
        $tag = preg_replace('/^<img\s/', '<img ' . $prefix . ' ', $tag, 1);
        $srcset = self::srcsetAttributes($candidates, $width, $auto && $autoSizes, $attachmentId, $meta, $src, $height);
        return $srcset === '' ? $tag : preg_replace('/\s*\/?>$/', $srcset . ' />', $tag, 1);
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
