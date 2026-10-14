<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Front\FeedTags;
use Minn\Support\Url;

/**
 * A post type's archive address and its feed, as the reference gives them
 * (probe archive-links). The posts' archive is the posts page when the
 * front is a page with one, else the bare home; a type with an archive
 * lives under its named archive or its rewrite slug (after the
 * structure's front unless it declines it), or at ?post_type= without
 * rewrites or pretty permalinks; any other type has none (false, no
 * filter). Its feed is the archive's /feed/ (/feed/{type}/ for one not the
 * default) when the type's rewrite takes feeds, else ?feed= with the type
 * spelled out.
 */
final class ArchiveLinks
{
    /** get_post_type_archive_link. */
    public static function archive(string $postType): string|false
    {
        $type = Registered::postType($postType);
        if (!$type || ($type->name !== 'post' && !$type->has_archive)) {
            return false;
        }
        if ($type->name === 'post') {
            $postsPage = Runtime::options()->filtered('show_on_front') === 'page' ? (int) Runtime::options()->filtered('page_for_posts') : 0;
            return Runtime::hooks()->filter('post_type_archive_link', [$postsPage > 0 ? PostLinks::permalink($postsPage) : SiteUrls::home(), $postType]);
        }
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        if (Runtime::options()->filtered('permalink_structure') && is_array($type->rewrite) && $rewrite instanceof \WP_Rewrite) {
            $slug = is_string($type->has_archive) ? $type->has_archive : (string) ($type->rewrite['slug'] ?? $postType);
            $link = SiteUrls::home(SiteUrls::trailing((empty($type->rewrite['with_front']) ? $rewrite->root : $rewrite->front) . $slug, 'post_type_archive'));
        } else {
            $link = SiteUrls::home('?post_type=' . $postType);
        }
        return Runtime::hooks()->filter('post_type_archive_link', [$link, $postType]);
    }

    /** get_post_type_archive_feed_link. */
    public static function feed(string $postType, string $feed): string|false
    {
        $default = (string) FeedTags::defaultFeed();
        $feed = $feed === '' ? $default : $feed;
        $link = self::archive($postType);
        if (!$link) {
            return false;
        }
        $type = Registered::postType($postType);
        if (Runtime::options()->filtered('permalink_structure') && $type && is_array($type->rewrite) && !empty($type->rewrite['feeds'])) {
            $link = Url::withTrailingSlash((string) $link) . SiteUrls::trailing('feed' . ($feed === $default ? '' : '/' . $feed), 'feed');
        } else {
            $link = QueryArgs::add('feed', $feed, (string) $link);
        }
        return Runtime::hooks()->filter('post_type_archive_feed_link', [$link, $feed]);
    }
}
