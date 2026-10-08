<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PostRecord;

/**
 * The Discussion setting that closes comments on old posts. Observed on the
 * reference: only the "post" type closes (the types filter changes nothing),
 * status is not consulted, the age is whole days on post_date_gmt and must
 * exceed the setting (a fifteen-day-old post stays open at fifteen), and zero
 * days switches the rule off.
 */
final readonly class CommentCloser
{
    public function __construct(private bool $enabled, private int $days)
    {
    }

    /** Whether comments stay open on a post under the close-after-days setting. */
    public function open(bool $open, array|PostRecord|null $post, int $now): bool
    {
        return $open && ($post === null || !$this->closes((string) ($post['post_type'] ?? ''), (string) ($post['post_date_gmt'] ?? ''), ['post'], $now));
    }

    /**
     * Whether the setting closes a post of $postType published at
     * $postDateGmt: the type is one of $types, the setting is on, and the
     * post is older than the days allowed. The posts a single query hands
     * over (_close_comments_for_old_posts) take their types from the
     * close_comments_for_post_types filter, which is honoured there.
     *
     * @param list<string> $types
     */
    public function closes(string $postType, string $postDateGmt, array $types, int $now): bool
    {
        if (!$this->enabled || $this->days <= 0 || !in_array($postType, $types, true)) {
            return false;
        }
        $published = strtotime($postDateGmt . ' UTC');
        return $published !== false && intdiv(max(0, $now - $published), 86400) > $this->days;
    }
}
