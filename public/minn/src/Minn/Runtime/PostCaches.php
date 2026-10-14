<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\RowCache;

/**
 * A post's cached rows let go as WordPress lets them go: its row, its meta
 * and the terms read with it, then plugins told (a page told twice); held
 * back while cache invalidation is suspended.
 */
final class PostCaches
{
    /** A post's caches let go (clean_post_cache). */
    public static function clean(mixed $post): void
    {
        if (!empty($GLOBALS['_wp_suspend_cache_invalidation'])) {
            return;
        }
        $cleaned = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
        if ($cleaned > 0) {
            RowCache::forget('posts', $cleaned);
            RowCache::forget('post_meta', $cleaned);
            RowCache::forget('post_terms');
        }
        $post = Records::post($post);
        if ($post === null) {
            return;
        }
        Handoff::cacheDelete($post->ID, 'posts');
        Handoff::cacheDelete($post->ID, 'post_meta');
        $hooks = Runtime::hooks();
        $hooks->action('clean_post_cache', [$post->ID, $post]);
        if ($post->post_type === 'page') {
            $hooks->action('clean_page_cache', [$post->ID]);
        }
    }
}
