<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;

/**
 * Whether a comment may be stored and in what state: the duplicate and
 * flood refusals first, then the site's moderation settings, with a
 * moderator's own comment always approved.
 */
final readonly class CommentModeration
{
    public const FLOOD_SECONDS = 15;

    public function __construct(private Comments $comments)
    {
    }

    /** "comment_duplicate", "comment_flood", or null when the comment may proceed. */
    public function refusal(array $data, bool $moderator): ?string
    {
        $postId = (int) ($data['comment_post_ID'] ?? 0);
        $author = (string) ($data['comment_author'] ?? '');
        $email = (string) ($data['comment_author_email'] ?? '');
        $content = (string) ($data['comment_content'] ?? '');
        if ($this->comments->duplicate($postId, $author, $email, $content, (int) ($data['user_id'] ?? 0))) {
            return 'comment_duplicate';
        }
        if (!$moderator && $this->comments->flooding($email, (string) ($data['comment_author_IP'] ?? ''), self::FLOOD_SECONDS)) {
            return 'comment_flood';
        }
        return null;
    }

    /**
     * "1" approved or "0" held, from the settings the reader supplies.
     *
     * @param Closure(string): ?string $option
     */
    public function approval(bool $moderator, string $author, string $email, Closure $option): string
    {
        if ($moderator) {
            return '1';
        }
        if (($option('comment_moderation') ?? '0') === '1') {
            return '0';
        }
        if (($option('comment_previously_approved') ?? '1') === '1') {
            return $this->comments->previouslyApproved($author, $email) ? '1' : '0';
        }
        return '1';
    }
}
