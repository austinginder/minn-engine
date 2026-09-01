<?php

declare(strict_types=1);

namespace Minn\Content;

use ArrayAccess;
use LogicException;

/**
 * One term with its taxonomy row, read by name: $term->name, ->slug,
 * ->taxonomy, ->parentId, ->count. The taxonomy and description are empty
 * when the query that built the record did not select them. Array access
 * is the migration bridge, read-only.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class TermRecord implements ArrayAccess
{
    /** @param array<string, mixed> $row */
    private function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $taxonomyId,
        public string $taxonomy,
        public string $description,
        public int $parentId,
        public int $count,
        private array $row,
    ) {
    }

    /**
     * A record from a joined terms row; a missing column reads as empty.
     *
     * @param array<string, mixed> $row a terms row joined with term_taxonomy, whatever columns it carries
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['term_id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            slug: (string) ($row['slug'] ?? ''),
            taxonomyId: (int) ($row['term_taxonomy_id'] ?? 0),
            taxonomy: (string) ($row['taxonomy'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            parentId: (int) ($row['parent'] ?? 0),
            count: (int) ($row['count'] ?? 0),
            row: $row,
        );
    }

    /**
     * A record for every row, in order.
     *
     * @param list<array<string, mixed>> $rows @return list<self>
     */
    public static function fromRows(array $rows): array
    {
        return array_map(self::fromRow(...), $rows);
    }

    /** The stored row, for the writers and shapers that still spell columns. */
    public function row(): array
    {
        return $this->row;
    }

    /** Whether the term sits under another. */
    public function hasParent(): bool
    {
        return $this->parentId > 0;
    }

    /** One stored column by its database name, or null. */
    public function column(string $name): mixed
    {
        return $this->row[$name] ?? null;
    }

    /** The migration bridge: the record answers to its column names the way the row did. */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->row[$offset]);
    }

    /** The migration bridge: one column by its stored name, or null. */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->row[$offset] ?? null;
    }

    /** Records are read-only; writes go through the repository. */
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('A TermRecord is read-only; write through Terms.');
    }

    /** Records are read-only; writes go through the repository. */
    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A TermRecord is read-only; write through Terms.');
    }
}
