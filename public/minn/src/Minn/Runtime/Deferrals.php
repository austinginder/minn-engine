<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The switches an importer flips for the length of a request (probe
 * plugin-helpers): term and comment counts deferred until the switch goes
 * off again, when what was put off is counted at once, and cache additions
 * suspended so a bulk read does not fill the cache. Each answers what it
 * is now; a value that is not a boolean asks without changing it. Kept in
 * the request's state, so a worker's next request starts with them off.
 */
final class Deferrals
{
    /** wp_defer_term_counting: on, off, or asked; off counts what was put off. */
    public static function terms(mixed $defer): bool
    {
        $now = self::flip('defer_term_counting', $defer);
        if ($defer === false) {
            foreach ((array) Runtime::current()->get('deferred_term_counts', []) as $taxonomy => $terms) {
                \wp_update_term_count_now(array_keys($terms), (string) $taxonomy);
            }
            Runtime::current()->set('deferred_term_counts', []);
        }
        return $now;
    }

    /** wp_defer_comment_counting: on, off, or asked; off counts what was put off. */
    public static function comments(mixed $defer): bool
    {
        $now = self::flip('defer_comment_counting', $defer);
        if ($defer === false) {
            foreach (array_keys((array) Runtime::current()->get('deferred_comment_counts', [])) as $post) {
                \wp_update_comment_count_now((int) $post);
            }
            Runtime::current()->set('deferred_comment_counts', []);
        }
        return $now;
    }

    /** wp_suspend_cache_addition: on, off, or asked. */
    public static function cacheAddition(mixed $suspend): bool
    {
        return self::flip('suspend_cache_addition', $suspend);
    }

    /** Puts terms' recount off while counting is deferred; false when it is not (count now). @param list<int> $terms */
    public static function putOffTerms(array $terms, string $taxonomy): bool
    {
        if (!Runtime::current()->get('defer_term_counting', false)) {
            return false;
        }
        $queued = (array) Runtime::current()->get('deferred_term_counts', []);
        $queued[$taxonomy] = ($queued[$taxonomy] ?? []) + array_fill_keys($terms, true);
        Runtime::current()->set('deferred_term_counts', $queued);
        return true;
    }

    /** Puts a post's comment recount off while counting is deferred; false when it is not (count now). */
    public static function putOffComments(int $post): bool
    {
        if (!Runtime::current()->get('defer_comment_counting', false)) {
            return false;
        }
        $queued = (array) Runtime::current()->get('deferred_comment_counts', []);
        $queued[$post] = true;
        Runtime::current()->set('deferred_comment_counts', $queued);
        return true;
    }

    private static function flip(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            Runtime::current()->set($key, $value);
        }
        return (bool) Runtime::current()->get($key, false);
    }
}
