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
        if (!$open || !$this->enabled || $this->days <= 0 || $post === null) {
            return $open;
        }
        if (($post['post_type'] ?? '') !== 'post') {
            return $open;
        }
        $published = strtotime((string) ($post['post_date_gmt'] ?? '') . ' UTC');
        if ($published === false) {
            return $open;
        }
        return intdiv(max(0, $now - $published), 86400) <= $this->days;
    }
}
