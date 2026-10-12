<?php

declare(strict_types=1);

namespace Minn\Content;

use Closure;
use Minn\Db;

/**
 * Rows read once a request: posts, their terms, users, and every object's
 * meta, as the reference keeps them in its object cache, so a loop or a
 * REST list does not read the same row once per use. A write to a table
 * drops everything kept from it (Db::wrote, through the engine's own door
 * or the facade's wpdb), as does a rolled-back transaction, so a read after
 * a write finds the row as it now is. Nothing outlives the request.
 */
final class RowCache
{
    /** The tables each kind of entry is read from. */
    private const SOURCES = [
        'posts' => ['posts'],
        'post_terms' => ['term_relationships', 'term_taxonomy', 'terms'],
        'revisions' => ['posts'],
        'theme_templates' => ['posts', 'term_relationships', 'term_taxonomy', 'terms'],
        'users' => ['users'],
        'comments' => ['comments'],
        'comment_queries' => ['comments', 'commentmeta'],
        'term_rows' => ['terms', 'term_taxonomy'],
        'post_meta' => ['postmeta'],
        'user_meta' => ['usermeta'],
        'term_meta' => ['termmeta'],
        'comment_meta' => ['commentmeta'],
    ];

    /** @var array<string, array<int|string, mixed>> */
    private static array $entries = [];
    private static bool $listening = false;
    /** @var array<string, true> the kind and table pairs watch() set up, "kind|table" */
    private static array $watched = [];

    /** The kept value of a kind for a key, read through $load the first time. */
    public static function remember(string $kind, int|string $key, Closure $load): mixed
    {
        self::listen();
        if (isset(self::$entries[$kind]) && array_key_exists($key, self::$entries[$kind])) {
            return self::$entries[$kind][$key];
        }
        return self::$entries[$kind][$key] = $load();
    }

    /** Whether a value is kept. */
    public static function has(string $kind, int|string $key): bool
    {
        return isset(self::$entries[$kind]) && array_key_exists($key, self::$entries[$kind]);
    }

    /** A kept value, or null when there is none. */
    public static function peek(string $kind, int|string $key): mixed
    {
        return self::$entries[$kind][$key] ?? null;
    }

    /** Keeps a value read elsewhere (a batch read for a whole list). */
    public static function put(string $kind, int|string $key, mixed $value): void
    {
        self::listen();
        self::$entries[$kind][$key] = $value;
    }

    /**
     * Has a kind named at run time (a plugin's meta type, whose table only
     * the registration knows) dropped whenever that table is written, as
     * the kinds above are by theirs.
     */
    public static function watch(string $kind, string $table): void
    {
        if (isset(self::SOURCES[$kind]) || isset(self::$watched["{$kind}|{$table}"])) {
            return;
        }
        self::$watched["{$kind}|{$table}"] = true;
        Db::onWrite($table, static function () use ($kind): void {
            self::forget($kind);
        });
    }

    /** Drops one kept value, or every one of a kind. */
    public static function forget(string $kind, int|string|null $key = null): void
    {
        if ($key === null) {
            unset(self::$entries[$kind]);
            return;
        }
        unset(self::$entries[$kind][$key]);
    }

    /** Drops everything kept. */
    public static function flush(): void
    {
        self::$entries = [];
    }

    private static function listen(): void
    {
        if (self::$listening) {
            return;
        }
        self::$listening = true;
        foreach (self::SOURCES as $kind => $tables) {
            foreach ($tables as $table) {
                Db::onWrite($table, static function () use ($kind): void {
                    self::forget($kind);
                });
            }
        }
    }
}
