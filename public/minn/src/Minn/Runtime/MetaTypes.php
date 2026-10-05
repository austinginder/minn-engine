<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Meta types a plugin brought, by the table it named on $wpdb as
 * "{$type}meta" (WooCommerce's order items keep theirs in
 * woocommerce_order_itemmeta). The reference reads any such table with
 * "{$type}_id" for the object and meta_id for the row; Meta does the same.
 */
final class MetaTypes
{
    /** @var array<string, string> type => full table name */
    private static array $tables = [];

    /** Registers a meta type by its full table name; refused unless both are plain identifiers. */
    public static function register(string $type, string $table): bool
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $type . $table) !== 1) {
            return false;
        }
        self::$tables[$type] = $table;
        return true;
    }

    /** The full table name of a registered meta type, or null. */
    public static function table(string $type): ?string
    {
        return self::$tables[$type] ?? null;
    }
}
