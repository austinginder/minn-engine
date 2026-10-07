<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\Users;
use Minn\Support\Serialized;

/**
 * Application passwords as the reference stores them: a serialized list in
 * the user's _application_passwords meta, each entry uuid, app_id, name,
 * password (a hash), created, last_used, last_ip. New passwords are 24
 * characters shown in groups of four; the stored hash is the reference's
 * own ($generic$, FastHash), so a password made on either stack works on
 * the other. Older hashes still verify, as the reference verifies them:
 * phpass (WordPress before 6.8, and the engine before this) and $wp$.
 */
final readonly class ApplicationPasswords
{
    public const META = '_application_passwords';

    public function __construct(private Users $users)
    {
    }

    /**
     * Every application password of a user, as stored records without the hash's plain text.
     *
     * @return list<array<string, mixed>>
     */
    public function all(int $userId): array
    {
        $raw = $this->users->meta($userId, self::META);
        if ($raw === null || $raw === '') {
            return [];
        }
        $list = Serialized::decode($raw);
        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * One record by uuid, or null.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $userId, string $uuid): ?array
    {
        foreach ($this->all($userId) as $item) {
            if (($item['uuid'] ?? '') === $uuid) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Mints a password, stores its hash, and returns the record with the plain text once.
     *
     * @return array{0: array<string, mixed>, 1: string} the stored record and the one-time plaintext
     */
    public function create(int $userId, string $name, string $appId): array
    {
        $plain = self::generate();
        $record = ['uuid' => self::uuid(), 'app_id' => $appId, 'name' => $name, 'password' => FastHash::available() ? FastHash::hash($plain) : PortableHash::hash($plain, 11), 'created' => time(), 'last_used' => null, 'last_ip' => null];
        $list = $this->all($userId);
        $list[] = $record;
        $this->save($userId, $list);
        return [$record, self::chunked($plain)];
    }

    /** Renames one password; null when the uuid is unknown. */
    public function rename(int $userId, string $uuid, string $name): ?array
    {
        return $this->change($userId, $uuid, ['name' => $name]);
    }

    /** Records a use: the time and the address it came from. */
    public function touch(int $userId, string $uuid, string $ip): ?array
    {
        return $this->change($userId, $uuid, ['last_used' => time(), 'last_ip' => $ip]);
    }

    /** Removes one password and returns its record; null when unknown. */
    public function delete(int $userId, string $uuid): ?array
    {
        $kept = [];
        $removed = null;
        foreach ($this->all($userId) as $item) {
            if (($item['uuid'] ?? '') === $uuid) {
                $removed = $item;
            } else {
                $kept[] = $item;
            }
        }
        if ($removed !== null) {
            $this->save($userId, $kept);
        }
        return $removed;
    }

    /**
     * Removes every application password of a user and returns how many.
     *
     * @return int how many went
     */
    public function deleteAll(int $userId): int
    {
        $count = count($this->all($userId));
        $this->users->deleteMeta($userId, self::META);
        return $count;
    }

    /** The record a plaintext (spaces ignored) unlocks for the user, or null. */
    public function verify(int $userId, string $password): ?array
    {
        $password = str_replace(' ', '', $password);
        foreach ($this->all($userId) as $item) {
            $hash = (string) ($item['password'] ?? '');
            if (FastHash::verify($password, $hash) || PortableHash::verify($password, $hash) || (str_starts_with($hash, '$wp$') && Password::verify($password, $hash))) {
                return $item;
            }
        }
        return null;
    }

    /** Twenty-four letters and digits. */
    public static function generate(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $out = '';
        for ($i = 0; $i < 24; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    /** The plaintext as shown once: groups of four, space separated. */
    public static function chunked(string $plain): string
    {
        return implode(' ', str_split($plain, 4));
    }

    private function change(int $userId, string $uuid, array $fields): ?array
    {
        $list = $this->all($userId);
        $changed = null;
        foreach ($list as $i => $item) {
            if (($item['uuid'] ?? '') === $uuid) {
                $list[$i] = $changed = array_merge($item, $fields);
            }
        }
        if ($changed !== null) {
            $this->save($userId, $list);
        }
        return $changed;
    }

    private function save(int $userId, array $list): void
    {
        $this->users->setMeta($userId, self::META, Serialized::encode(array_values($list)));
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
