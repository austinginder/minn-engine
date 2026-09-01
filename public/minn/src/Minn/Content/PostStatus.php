<?php

declare(strict_types=1);

namespace Minn\Content;

/** The statuses a post row can hold; the value is the column's own spelling. */
enum PostStatus: string
{
    case Publish = 'publish';
    case Draft = 'draft';
    case Pending = 'pending';
    case Private = 'private';
    case Future = 'future';
    case Trash = 'trash';
    case Inherit = 'inherit';
    case AutoDraft = 'auto-draft';

    /** A row's status, or null for a value no enum case spells (a plugin's own status). */
    public static function of(array|PostRecord $row): ?self
    {
        return self::tryFrom((string) ($row['post_status'] ?? ''));
    }

    /** Publish, future and private are live: they count, they link, they are not drafts. */
    public function isLive(): bool
    {
        return match ($this) {
            self::Publish, self::Future, self::Private => true,
            default => false,
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Publish;
    }

    /** @param list<self> $statuses @return list<string> the column values, for a query */
    public static function values(array $statuses): array
    {
        return array_map(static fn (self $status) => $status->value, $statuses);
    }
}
