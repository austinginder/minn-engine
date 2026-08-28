<?php

declare(strict_types=1);

namespace Minn\Blocks;

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
    public function __construct(
        private Posts $posts,
        private Uploads $uploads,
    ) {
    }

    /** Rewrites every wp-image-* <img> in a fragment; other images pass through. */
    public function enrich(string $html, bool $withDataId = false, bool $front = false): string
    {
        return preg_replace_callback(
            '/<img\s[^>]*?class="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*\/?>/',
            fn (array $m) => $this->enrichTag($m[0], (int) $m[1], $withDataId, $front),
            $html,
        );
    }

    /**
     * On a page the first content image is fetched eagerly with high
     * priority, the next is eager, and the rest lazy; only lazy images
     * carry the "auto" sizes hint. In a REST response every image is lazy.
     */
    private static function loadingPrefix(bool $front): array
    {
        if (!$front) {
            return ['loading="lazy" decoding="async"', true];
        }
        // Three eager images per page, every <img> on the page counting toward
        // the budget; the first eager content image is fetched with high priority.
        $seen = RenderState::nextImage();
        if ($seen > 3) {
            return ['loading="lazy" decoding="async"', true];
        }
        return RenderState::claimPriority() ? ['fetchpriority="high" decoding="async"', false] : ['decoding="async"', false];
    }

    private function enrichTag(string $tag, int $attachmentId, bool $withDataId, bool $front): string
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
        [$loading, $auto] = self::loadingPrefix($front);
        $prefix = $loading . ' width="' . $width . '" height="' . $height . '"' . ($withDataId ? ' data-id="' . $attachmentId . '"' : '');
        $tag = preg_replace('/^<img\s/', '<img ' . $prefix . ' ', $tag, 1);
        $srcset = self::srcsetAttributes($candidates, $width, $auto);
        return $srcset === '' ? $tag : preg_replace('/\s*\/?>$/', $srcset . ' />', $tag, 1);
    }

    /** A srcset needs a choice: one candidate yields no srcset and no sizes. */
    private static function srcsetAttributes(array $candidates, int $width, bool $auto): string
    {
        if (count($candidates) < 2) {
            return '';
        }
        $srcset = [];
        foreach ($candidates as $w => $url) {
            $srcset[] = $url . ' ' . $w . 'w';
        }
        return ' srcset="' . implode(', ', $srcset) . '" sizes="' . ($auto ? 'auto, ' : '') . '(max-width: ' . $width . 'px) 100vw, ' . $width . 'px"';
    }

    /**
     * A post's featured image at full size, in the reference's attribute
     * order (dimensions, source, class, alt, style, then the loading
     * attributes and the srcset). Empty when the attachment has no file.
     */
    public function featured(int $attachmentId, string $alt, string $style, bool $front): string
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
        [$loading, $auto] = self::loadingPrefix($front);
        $loading = match ($loading) {
            'fetchpriority="high" decoding="async"' => 'decoding="async" fetchpriority="high"',
            'loading="lazy" decoding="async"' => 'decoding="async" loading="lazy"',
            default => $loading,
        };
        $dimensions = $meta['width'] > 0 ? 'width="' . $meta['width'] . '" height="' . $meta['height'] . '" ' : '';
        return '<img ' . $dimensions . 'src="' . Html::attr($fullUrl) . '" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" alt="' . Html::attr($alt) . '" style="' . Html::attr($style) . '" ' . $loading
            . self::srcsetAttributes($candidates, $meta['width'], $auto) . ' />';
    }
}
