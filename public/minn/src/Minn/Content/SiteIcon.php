<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Front\Permalinks;

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

    /** The icon's URL, empty when the site has none. */
    public function url(): string
    {
        $file = $this->file();
        return $file === null ? '' : $this->permalinks->url('/wp-content/uploads/' . $file);
    }
}
