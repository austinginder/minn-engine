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

    /** @param array<string, mixed> $row a terms row joined with term_taxonomy, whatever columns it carries */
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

    /** @param list<array<string, mixed>> $rows @return list<self> */
    public static function fromRows(array $rows): array
    {
        return array_map(self::fromRow(...), $rows);
    }

    public function row(): array
    {
        return $this->row;
    }

    public function hasParent(): bool
    {
        return $this->parentId > 0;
    }

    public function column(string $name): mixed
    {
        return $this->row[$name] ?? null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->row[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->row[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('A TermRecord is read-only; write through Terms.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A TermRecord is read-only; write through Terms.');
    }
}
