<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * One page of a listing: the rows on it and how many rows the whole
 * listing has, which is what pagination is counted from.
 */
final readonly class Page
{
    /** @param list<PostRecord> $posts */
    public function __construct(
        public array $posts,
        public int $total,
    ) {
    }

    public static function empty(): self
    {
        return new self([], 0);
    }

    public function isEmpty(): bool
    {
        return $this->posts === [];
    }

    public function count(): int
    {
        return count($this->posts);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map(static fn (PostRecord $post) => $post->id, $this->posts);
    }

    public function totalPages(int $perPage): int
    {
        return $perPage > 0 ? (int) ceil($this->total / $perPage) : 0;
    }

    /** The same page with other rows on it and the same total behind it. */
    public function withPosts(array $posts): self
    {
        return new self(array_values($posts), $this->total);
    }
}
