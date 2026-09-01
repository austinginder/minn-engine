<?php

declare(strict_types=1);

namespace Minn\Content;

use ArrayAccess;
use LogicException;

/**
 * One row of the comments table, read by name: $comment->author, ->content,
 * ->postId, ->parentId, and isApproved() for the status the reference
 * stores as '1'. Array access is the migration bridge, read-only.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class CommentRecord implements ArrayAccess
{
    /** @param array<string, mixed> $row */
    private function __construct(
        public int $id,
        public int $postId,
        public string $author,
        public string $authorEmail,
        public string $authorUrl,
        public string $authorIp,
        public string $date,
        public string $dateGmt,
        public string $content,
        public int $karma,
        public string $approved,
        public string $agent,
        public string $type,
        public int $parentId,
        public int $userId,
        private array $row,
    ) {
    }

    /**
     * A record from a comments row; a missing column reads as empty.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['comment_ID'] ?? 0),
            postId: (int) ($row['comment_post_ID'] ?? 0),
            author: (string) ($row['comment_author'] ?? ''),
            authorEmail: (string) ($row['comment_author_email'] ?? ''),
            authorUrl: (string) ($row['comment_author_url'] ?? ''),
            authorIp: (string) ($row['comment_author_IP'] ?? ''),
            date: (string) ($row['comment_date'] ?? ''),
            dateGmt: (string) ($row['comment_date_gmt'] ?? ''),
            content: (string) ($row['comment_content'] ?? ''),
            karma: (int) ($row['comment_karma'] ?? 0),
            approved: (string) ($row['comment_approved'] ?? ''),
            agent: (string) ($row['comment_agent'] ?? ''),
            type: (string) ($row['comment_type'] ?? ''),
            parentId: (int) ($row['comment_parent'] ?? 0),
            userId: (int) ($row['user_id'] ?? 0),
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

    /** Approved, and so public. */
    public function isApproved(): bool
    {
        return $this->approved === '1';
    }

    /** Held for moderation. */
    public function isPending(): bool
    {
        return $this->approved === '0';
    }

    /** A plain comment: the type column is '' on old rows and 'comment' on new ones. */
    public function isComment(): bool
    {
        return $this->type === '' || $this->type === 'comment';
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
        throw new LogicException('A CommentRecord is read-only; write through Comments.');
    }

    /** Records are read-only; writes go through the repository. */
    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A CommentRecord is read-only; write through Comments.');
    }
}
