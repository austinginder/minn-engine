<?php

declare(strict_types=1);

namespace Minn\Content;

use ArrayAccess;
use LogicException;

/**
 * One row of the posts table, read by name. The columns keep their
 * WordPress spelling on the way in (row()) and get plain names here:
 * $post->title, $post->slug, $post->status. A record is built from a row
 * and can hand the row back, so a writer or a REST shaper that still
 * works in columns is not disturbed.
 *
 * Array access is the migration bridge: code that still reads
 * $post['post_title'] keeps working while it is moved over. New code
 * reads the properties. The style suite counts the bracket reads down.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class PostRecord implements ArrayAccess
{
    /** @param array<string, mixed> $row */
    private function __construct(
        public int $id,
        public int $authorId,
        public string $date,
        public string $dateGmt,
        public string $content,
        public string $title,
        public string $excerpt,
        public string $status,
        public string $commentStatus,
        public string $pingStatus,
        public string $password,
        public string $slug,
        public string $modified,
        public string $modifiedGmt,
        public int $parentId,
        public string $guid,
        public int $menuOrder,
        public string $type,
        public string $mimeType,
        public int $commentCount,
        private array $row,
    ) {
    }

    /** @param array<string, mixed> $row a posts-table row, joined columns welcome */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['ID'] ?? 0),
            authorId: (int) ($row['post_author'] ?? 0),
            date: (string) ($row['post_date'] ?? ''),
            dateGmt: (string) ($row['post_date_gmt'] ?? ''),
            content: (string) ($row['post_content'] ?? ''),
            title: (string) ($row['post_title'] ?? ''),
            excerpt: (string) ($row['post_excerpt'] ?? ''),
            status: (string) ($row['post_status'] ?? ''),
            commentStatus: (string) ($row['comment_status'] ?? ''),
            pingStatus: (string) ($row['ping_status'] ?? ''),
            password: (string) ($row['post_password'] ?? ''),
            slug: (string) ($row['post_name'] ?? ''),
            modified: (string) ($row['post_modified'] ?? ''),
            modifiedGmt: (string) ($row['post_modified_gmt'] ?? ''),
            parentId: (int) ($row['post_parent'] ?? 0),
            guid: (string) ($row['guid'] ?? ''),
            menuOrder: (int) ($row['menu_order'] ?? 0),
            type: (string) ($row['post_type'] ?? ''),
            mimeType: (string) ($row['post_mime_type'] ?? ''),
            commentCount: (int) ($row['comment_count'] ?? 0),
            row: $row,
        );
    }

    /** @param list<array<string, mixed>> $rows @return list<self> */
    public static function fromRows(array $rows): array
    {
        return array_map(self::fromRow(...), $rows);
    }

    /** The row as the table holds it, joined columns and all. */
    public function row(): array
    {
        return $this->row;
    }

    /** The status as an enum case, or null for a status only a plugin knows. */
    public function status(): ?PostStatus
    {
        return PostStatus::tryFrom($this->status);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Publish->value;
    }

    /** Publish, future or private: it counts, it links, it is not a draft. */
    public function isLive(): bool
    {
        return $this->status()?->isLive() ?? false;
    }

    public function isTrashed(): bool
    {
        return $this->status === PostStatus::Trash->value;
    }

    public function isProtected(): bool
    {
        return $this->password !== '';
    }

    public function isPage(): bool
    {
        return $this->type === 'page';
    }

    public function isAttachment(): bool
    {
        return $this->type === 'attachment';
    }

    /** A joined column, or any column by its table name. */
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
        throw new LogicException('A PostRecord is read-only; write through PostWriter.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A PostRecord is read-only; write through PostWriter.');
    }
}
