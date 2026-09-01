<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * What a listing is narrowed to. Every field is optional and the object is
 * immutable, so a filter reads as a sentence: types('post')->inTerm(12).
 * Dates are site-local "Y-m-d H:i:s" bounds, from inclusive, to exclusive.
 */
final readonly class PostFilter
{
    /** @param list<string> $types */
    public function __construct(
        public array $types = ['post'],
        public ?int $term = null,
        public ?int $author = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?string $search = null,
    ) {
    }

    public static function all(): self
    {
        return new self();
    }

    public static function types(string ...$types): self
    {
        return new self(types: $types === [] ? ['post'] : array_values($types));
    }

    /** Posts linked to a term, by its term_taxonomy_id. */
    public function inTerm(int $termTaxonomyId): self
    {
        return new self($this->types, $termTaxonomyId, $this->author, $this->from, $this->to, $this->search);
    }

    public function byAuthor(int $userId): self
    {
        return new self($this->types, $this->term, $userId, $this->from, $this->to, $this->search);
    }

    public function between(string $from, string $to): self
    {
        return new self($this->types, $this->term, $this->author, $from, $to, $this->search);
    }

    public function matching(string $search): self
    {
        return new self($this->types, $this->term, $this->author, $this->from, $this->to, $search);
    }

    public function hasDates(): bool
    {
        return $this->from !== null && $this->to !== null;
    }
}
