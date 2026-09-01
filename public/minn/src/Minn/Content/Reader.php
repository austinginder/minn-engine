<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;

/**
 * Who is reading this request: their user id, whether they may read
 * private content, whether they may edit a given post, and the
 * post-password cookie they carry. Set once per request by the engine
 * and consulted by the resolver, the queries, and the renderers.
 */
final class Reader
{
    private static ?self $current = null;

    /** @param Closure(int): bool $canEditPost */
    public function __construct(
        public readonly int $userId,
        public readonly bool $readsPrivatePosts,
        public readonly bool $readsPrivatePages,
        private readonly Closure $canEditPost,
        public readonly string $postPassword,
        public readonly string $sessionToken = '',
        /** @var list<string> */
        public readonly array $roles = [],
    ) {
    }

    /** The reader who is nobody: no session, no private posts, only the post password they typed. */
    public static function anonymous(string $postPassword = ''): self
    {
        return new self(0, false, false, static fn (int $id): bool => false, $postPassword);
    }

    /** Makes this reader the current one for the request. */
    public static function set(self $reader): void
    {
        self::$current = $reader;
    }

    /** The request's reader, anonymous until one is set. */
    public static function current(): self
    {
        return self::$current ??= self::anonymous();
    }

    /** Whether the reader has a session. */
    public function loggedIn(): bool
    {
        return $this->userId > 0;
    }

    /** Whether this reader may edit a given post. */
    public function canEdit(int $postId): bool
    {
        return ($this->canEditPost)($postId);
    }

    /** The statuses a listing may show this reader: published, plus private when they may read it. @return list<string> */
    public function listableStatuses(string $type = 'post'): array
    {
        $private = $type === 'page' ? $this->readsPrivatePages : $this->readsPrivatePosts;
        return PostStatus::values($private ? [PostStatus::Publish, PostStatus::Private] : [PostStatus::Publish]);
    }
}
