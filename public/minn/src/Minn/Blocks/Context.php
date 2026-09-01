<?php

declare(strict_types=1);

namespace Minn\Blocks;

use Minn\Content\PostRecord;
use Minn\Front\Kind;
use Minn\Front\Resolution;

/**
 * What the template blocks render against: the resolution, the main
 * query's posts, and a stack of "current post" frames pushed by post
 * templates and comment templates as they loop.
 */
final class Context
{
    /** @var list<array|PostRecord> */
    private array $postStack = [];
    private ?array $comment = null;

    /**
     * @param list<PostRecord> $posts the main query's page of posts
     */
    public function __construct(
        public readonly Resolution $resolution,
        public readonly array $posts,
        public readonly int $total,
        public readonly int $perPage,
        public readonly bool $front,
    ) {
    }

    public static function forRest(): self
    {
        return new self(Resolution::home(), [], 0, 10, false);
    }

    public function post(): array|PostRecord|null
    {
        if ($this->postStack !== []) {
            return $this->postStack[count($this->postStack) - 1];
        }
        return in_array($this->resolution->kind, [Kind::Single, Kind::Page], true) ? $this->resolution->record : null;
    }

    public function pushPost(array|PostRecord $post): void
    {
        $this->postStack[] = $post;
    }

    public function popPost(): void
    {
        array_pop($this->postStack);
    }

    /** Inside a post template loop (as opposed to the singular view). */
    public function inLoop(): bool
    {
        return $this->postStack !== [];
    }

    public function comment(): ?array
    {
        return $this->comment;
    }

    public function withComment(?array $comment): void
    {
        $this->comment = $comment;
    }

    public function paged(): int
    {
        return $this->resolution->paged;
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
