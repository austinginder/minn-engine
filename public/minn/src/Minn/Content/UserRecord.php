<?php

declare(strict_types=1);

namespace Minn\Content;

use ArrayAccess;
use LogicException;

/**
 * One row of the users table, read by name. Columns keep their WordPress
 * spelling in row(); here they are $user->login, ->email, ->displayName.
 * Array access is the migration bridge, read-only; new code reads the
 * properties.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class UserRecord implements ArrayAccess
{
    /** @param array<string, mixed> $row */
    private function __construct(
        public int $id,
        public string $login,
        public string $passwordHash,
        public string $nicename,
        public string $email,
        public string $url,
        public string $registered,
        public string $activationKey,
        public int $status,
        public string $displayName,
        private array $row,
    ) {
    }

    /**
     * A record from a users row; a missing column reads as empty.
     *
     * @param array<string, mixed> $row a users-table row, joined columns welcome
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['ID'] ?? 0),
            login: (string) ($row['user_login'] ?? ''),
            passwordHash: (string) ($row['user_pass'] ?? ''),
            nicename: (string) ($row['user_nicename'] ?? ''),
            email: (string) ($row['user_email'] ?? ''),
            url: (string) ($row['user_url'] ?? ''),
            registered: (string) ($row['user_registered'] ?? ''),
            activationKey: (string) ($row['user_activation_key'] ?? ''),
            status: (int) ($row['user_status'] ?? 0),
            displayName: (string) ($row['display_name'] ?? ''),
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

    /**
     * The stored row, for the writers and shapers that still spell columns.
     *
     * The row as the table holds it.
     */
    public function row(): array
    {
        return $this->row;
    }

    /** The name shown for this person: the display name, or the login when none was set. */
    public function name(): string
    {
        return $this->displayName !== '' ? $this->displayName : $this->login;
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
        throw new LogicException('A UserRecord is read-only; write through Users.');
    }

    /** Records are read-only; writes go through the repository. */
    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A UserRecord is read-only; write through Users.');
    }
}
