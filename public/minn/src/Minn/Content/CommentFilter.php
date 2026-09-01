<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * What a comment listing is narrowed to. Every field is optional; the id
 * lists keep zero, because post=0 means "comments without a post". Dates
 * are site-local "Y-m-d H:i:s", after and before both exclusive.
 */
final readonly class CommentFilter
{
    /**
     * @param list<int> $post
     * @param list<int> $include
     * @param list<int> $exclude
     * @param list<int> $parent
     * @param list<int> $parentExclude
     * @param list<int> $author
     * @param list<int> $authorExclude
     */
    public function __construct(
        public array $post = [],
        public array $include = [],
        public array $exclude = [],
        public array $parent = [],
        public array $parentExclude = [],
        public array $author = [],
        public array $authorExclude = [],
        public string $authorEmail = '',
        public string $type = 'comment',
        public string $search = '',
        public string $after = '',
        public string $before = '',
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    /** The plain kind: an empty type or "comment". */
    public function isPlainType(): bool
    {
        return $this->type === '' || $this->type === 'comment';
    }
}
