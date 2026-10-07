<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What WP_Query does with its posts once it has them, as the reference does
 * it (probe wp-query-sql): a single post whose status is not public is
 * dropped unless the reader may see it (a draft its editor may preview, with
 * the_preview); sticky posts move to the front of the home listing's first
 * page, the ones it did not find fetched by a nested query; the_posts; and
 * the post objects, count and current post set.
 */
final class PostQueryResults
{
    public function __construct(private readonly \WP_Query $query, private readonly PostQueryParts $parts)
    {
    }

    /** Settles the posts a query found: status check, stickies, the_posts, then the count and the current post. @param array<string, mixed> $q */
    public function settle(array $q): void
    {
        $query = $this->query;
        $this->singleStatus();
        $this->stickies($q);
        if (!$q['suppress_filters']) {
            $query->posts = \apply_filters_ref_array('the_posts', [$query->posts, &$query]);
        }
        if ($query->posts) {
            $query->post_count = count($query->posts);
            $query->posts = array_map('get_post', $query->posts);
            if ($q['cache_results']) {
                \update_post_caches($query->posts, $this->parts->postType, $q['update_post_term_cache'], $q['update_post_meta_cache']);
            }
            $query->post = reset($query->posts);
        } else {
            $query->post_count = 0;
            $query->posts = [];
        }
    }

    /** A single post the reader may not see is dropped; a draft its editor may edit is a preview. */
    private function singleStatus(): void
    {
        $query = $this->query;
        if (empty($query->posts) || !($query->is_single || $query->is_page)) {
            return;
        }
        $first = $query->posts[0];
        if ($first->post_type === 'attachment' && (int) $first->post_parent === 0) {
            $query->is_page = false;
            $query->is_single = true;
            $query->is_attachment = true;
        }
        $status = \get_post_status($first);
        if (!in_array($status, $this->parts->statuses, true)) {
            $query->posts = $this->visible($status) ? $query->posts : [];
        }
        if ($query->is_preview && $query->posts && \current_user_can('edit_post', $query->posts[0]->ID)) {
            $query->posts[0] = \get_post(\apply_filters_ref_array('the_preview', [$query->posts[0], &$query]));
        }
    }

    /** Whether the reader may see the first post in its status (a draft they may edit becomes a preview, dated now). */
    private function visible(string|false $status): bool
    {
        $query = $this->query;
        $first = $query->posts[0];
        $object = \get_post_status_object((string) $status);
        if (!$object) {
            return \current_user_can('edit_post', $first->ID);
        }
        if ($object->public) {
            return true;
        }
        if (!\is_user_logged_in()) {
            return false;
        }
        if ($object->protected) {
            if (!\current_user_can('edit_post', $first->ID)) {
                return false;
            }
            $query->is_preview = true;
            if ($status !== 'future') {
                $first->post_date = \current_time('mysql');
            }
            return true;
        }
        return $object->private && \current_user_can('read_post', $first->ID);
    }

    /** Sticky posts to the front of the home listing's first page; those it did not find, fetched. @param array<string, mixed> $q */
    private function stickies(array $q): void
    {
        $query = $this->query;
        $sticky = Runtime::options()->filtered('sticky_posts');
        if (!$query->is_home || $this->parts->page > 1 || !is_array($sticky) || $sticky === [] || $q['ignore_sticky_posts'] || !is_array($query->posts)) {
            return;
        }
        $offset = 0;
        $count = count($query->posts);
        for ($i = 0; $i < $count; $i++) {
            if (in_array($query->posts[$i]->ID, $sticky, true)) {
                $post = $query->posts[$i];
                array_splice($query->posts, $i, 1);
                array_splice($query->posts, $offset, 0, [$post]);
                $offset++;
                unset($sticky[array_search($post->ID, $sticky, true)]);
            }
        }
        ['post__not_in' => $excluded] = $q + ['post__not_in' => []];
        if ($sticky !== [] && !empty($excluded)) {
            $sticky = array_diff($sticky, (array) $excluded);
        }
        if ($sticky === []) {
            return;
        }
        $found = \get_posts(['post__in' => $sticky, 'post_type' => $this->parts->postType, 'post_status' => 'publish', 'posts_per_page' => count($sticky), 'suppress_filters' => $q['suppress_filters'], 'cache_results' => $q['cache_results'], 'update_post_meta_cache' => $q['update_post_meta_cache'], 'update_post_term_cache' => $q['update_post_term_cache'], 'lazy_load_term_meta' => $q['lazy_load_term_meta']]);
        foreach ($found as $post) {
            array_splice($query->posts, $offset, 0, [$post]);
            $offset++;
        }
    }
}
