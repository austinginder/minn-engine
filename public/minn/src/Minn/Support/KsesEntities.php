<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The named references kses keeps as written: the list captured from the
 * reference (data/kses.json) plus the five XML ones. Any other name is
 * stored as "&amp;name;".
 */
final class KsesEntities
{
    /** @var array<string, mixed>|null */
    private static ?array $table = null;
    /** @var array<string, true>|null */
    private static ?array $known = null;

    /** Whether "&name;" is a reference kses leaves alone. */
    public static function known(string $name): bool
    {
        self::$known ??= array_fill_keys([...(array) (self::table()['entities'] ?? []), 'quot', 'amp', 'lt', 'gt', 'apos'], true);
        return isset(self::$known[$name]);
    }

    /**
     * An allowlist the reference ships, by context ("post" or "data").
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allowlist(string $context): array
    {
        return (array) (self::table()[$context] ?? []);
    }

    /** @return array<string, mixed> */
    private static function table(): array
    {
        return self::$table ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/kses.json'), true);
    }
}
