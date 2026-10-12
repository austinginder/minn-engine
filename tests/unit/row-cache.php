<?php

declare(strict_types=1);

use Minn\Content\RowCache;
use Minn\Db;

/**
 * The request-long row cache drops what a write makes stale: a kind it
 * knows by its tables, and a kind named at run time (a plugin's meta type)
 * once watch() names its table. WooCommerce's order items read their meta
 * before it is written; without the watch the empty read stood for the rest
 * of the request (the woo suite's checkout).
 */
return [
    'a write to a known kind\'s table drops what was kept from it, and only that' => static function (): bool {
        RowCache::put('post_meta', 9001, ['a' => ['1']]);
        RowCache::put('user_meta', 9001, ['b' => ['2']]);
        Db::wrote("UPDATE wp_postmeta SET meta_value = '3' WHERE post_id = 9001");
        $dropped = !RowCache::has('post_meta', 9001) && RowCache::has('user_meta', 9001);
        RowCache::flush();
        return $dropped;
    },
    'a kind named at run time is dropped by a write to the table watch() names, not by another' => static function (): bool {
        RowCache::watch('zz_item_meta', 'wp_zz_itemmeta');
        RowCache::put('zz_item_meta', 7, []);
        Db::wrote("INSERT INTO wp_zz_items (name) VALUES ('x')");
        $kept = RowCache::has('zz_item_meta', 7);
        Db::wrote("INSERT INTO wp_zz_itemmeta (zz_item_id, meta_key, meta_value) VALUES (7, '_product_id', '12')");
        $dropped = !RowCache::has('zz_item_meta', 7);
        RowCache::flush();
        return $kept && $dropped;
    },
    'a read is not a write' => static function (): bool {
        RowCache::put('posts', 5, null);
        Db::wrote('SELECT * FROM wp_posts WHERE ID = 5');
        $kept = RowCache::has('posts', 5);
        RowCache::flush();
        return $kept;
    },
];
