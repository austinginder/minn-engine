<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Front\Permalinks;
use Minn\Media\Metadata;

/**
 * The site icon: the attachment the site_icon option names, as the file
 * under uploads it points at. Read by the favicon route, the head links,
 * and the admin app's sidebar.
 */
final readonly class SiteIcon
{
    public function __construct(
        private Site $site,
        private Posts $posts,
        private Permalinks $permalinks,
    ) {
    }

    /** The icon's path under uploads, or null when the site has none. */
    public function file(): ?string
    {
        $icon = (int) ($this->site->option('site_icon') ?? 0);
        $file = $icon > 0 ? $this->posts->meta($icon, '_wp_attached_file') : null;
        return $file === null || $file === '' ? null : $file;
    }

    /**
     * The URL of the icon's smallest generated size that covers a square of
     * $size pixels, or of the original when no size does; empty without an icon.
     */
    public function urlAt(int $size): string
    {
        $file = $this->file();
        if ($file === null) {
            return '';
        }
        $meta = Metadata::parse($this->posts->meta((int) ($this->site->option('site_icon') ?? 0), '_wp_attachment_metadata'));
        $best = null;
        foreach ((array) ($meta['sizes'] ?? []) as $candidate) {
            $w = (int) ($candidate['width'] ?? 0);
            $h = (int) ($candidate['height'] ?? 0);
            if ($w >= $size && $h >= $size && ($best === null || $w < (int) $best['width'])) {
                $best = $candidate;
            }
        }
        if ($best === null || !is_string($best['file'] ?? null)) {
            return $this->permalinks->url('/wp-content/uploads/' . $file);
        }
        $dir = dirname($file);
        return $this->permalinks->url('/wp-content/uploads/' . ($dir === '.' ? '' : $dir . '/') . $best['file']);
    }

    /** The icon's URL, empty when the site has none. */
    public function url(): string
    {
        $file = $this->file();
        return $file === null ? '' : $this->permalinks->url('/wp-content/uploads/' . $file);
    }
}
