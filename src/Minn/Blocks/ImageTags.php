<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Content\Posts;
use Minn\Media\Metadata;
use Minn\Media\Uploads;

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
    public function enrich(string $html, bool $withDataId = false): string
    {
        return preg_replace_callback(
            '/<img\s[^>]*?class="[^"]*\bwp-image-(\d+)\b[^"]*"[^>]*\/?>/',
            fn (array $m) => $this->enrichTag($m[0], (int) $m[1], $withDataId),
            $html,
        );
    }

    private function enrichTag(string $tag, int $attachmentId, bool $withDataId): string
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
        $baseUrl = $this->uploads->baseUrl() . '/' . dirname($meta['file']);
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
        $srcset = [];
        foreach ($candidates as $width => $url) {
            $srcset[] = $url . ' ' . $width . 'w';
        }
        [$width, $height] = $shown;
        $prefix = 'loading="lazy" decoding="async" width="' . $width . '" height="' . $height . '"' . ($withDataId ? ' data-id="' . $attachmentId . '"' : '');
        $tag = preg_replace('/^<img\s/', '<img ' . $prefix . ' ', $tag, 1);
        $suffix = ' srcset="' . implode(', ', $srcset) . '" sizes="auto, (max-width: ' . $width . 'px) 100vw, ' . $width . 'px"';
        return preg_replace('/\s*\/?>$/', $suffix . ' />', $tag, 1);
    }
}
