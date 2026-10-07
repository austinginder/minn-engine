<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
        $type = \get_post_type_object($postType);
        if (!$type || ($type->name !== 'post' && !$type->has_archive)) {
            return false;
        }
        if ($type->name === 'post') {
            $postsPage = Runtime::options()->filtered('show_on_front') === 'page' ? (int) Runtime::options()->filtered('page_for_posts') : 0;
            return \apply_filters('post_type_archive_link', $postsPage > 0 ? \get_permalink($postsPage) : \get_home_url(), $postType);
        }
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        if (Runtime::options()->filtered('permalink_structure') && is_array($type->rewrite) && $rewrite instanceof \WP_Rewrite) {
            $slug = is_string($type->has_archive) ? $type->has_archive : (string) ($type->rewrite['slug'] ?? $postType);
            $link = \home_url(\user_trailingslashit((empty($type->rewrite['with_front']) ? $rewrite->root : $rewrite->front) . $slug, 'post_type_archive'));
        } else {
            $link = \home_url('?post_type=' . $postType);
        }
        return \apply_filters('post_type_archive_link', $link, $postType);
    }

    /** get_post_type_archive_feed_link. */
    public static function feed(string $postType, string $feed): string|false
    {
        $default = (string) \get_default_feed();
        $feed = $feed === '' ? $default : $feed;
        $link = \get_post_type_archive_link($postType);
        if (!$link) {
            return false;
        }
        $type = \get_post_type_object($postType);
        if (Runtime::options()->filtered('permalink_structure') && $type && is_array($type->rewrite) && !empty($type->rewrite['feeds'])) {
            $link = \trailingslashit((string) $link) . \user_trailingslashit('feed' . ($feed === $default ? '' : '/' . $feed), 'feed');
        } else {
            $link = \add_query_arg('feed', $feed, (string) $link);
        }
        return \apply_filters('post_type_archive_feed_link', $link, $feed);
    }
}
