<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;
use Minn\Auth\Capabilities;
use Minn\Runtime\Runtime;

/**
 * Who is reading this request: their user id, whether they may read
 * private content, whether they may edit a given post, and the
 * post-password cookie they carry. Built once per surface by the engine,
 * carried by the request's context, and consulted by the resolver, the
 * queries, and the renderers.
 */
final class Reader
{
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

    /**
     * The reader a signed-in user is, asked of the capability engine: what
     * they may read privately, what they may edit, and the roles they hold.
     * User 0 is nobody, whatever the capabilities say.
     */
    public static function forUser(int $userId, Capabilities $capabilities, string $postPassword = '', string $sessionToken = ''): self
    {
        if ($userId === 0) {
            return new self(0, false, false, static fn (int $id): bool => false, $postPassword, $sessionToken);
        }
        return new self(
            $userId,
            $capabilities->can($userId, 'read_private_posts'),
            $capabilities->can($userId, 'read_private_pages'),
            static fn (int $postId): bool => $capabilities->can($userId, 'edit_post', $postId),
            $postPassword,
            $sessionToken,
            $capabilities->rolesOf($userId),
        );
    }

    /**
     * The reader of the request being answered. The runtime holds it (the
     * context it was built with carries it), so there is one holder and a
     * request cannot see the reader of the one before it. Without a
     * runtime, on the command line and in unit tests, nobody is reading.
     */
    public static function current(): self
    {
        return Runtime::booted() ? Runtime::current()->reader : self::anonymous();
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
