<?php

declare(strict_types=1);

use Minn\Runtime\Placeholders;

/** wpdb::prepare's placeholders, filled as the reference fills them (captured from its query log). */
$fill = static fn (string $query, mixed ...$args): ?string => Placeholders::fill($query, $args, static fn (string $s): string => addslashes($s));
return [
    'a bare %s is escaped and quoted' => static fn () => $fill('%s', "it's") === "'it\\'s'" ?: $fill('%s', "it's"),
    'quotes already around %s are not doubled' => static fn () => $fill("'%s' \"%s\"", 'a', 'b') === "'a' 'b'",
    'a numbered %1$s is never quoted, so it can name a table' => static fn () => $fill('TRUNCATE %1$s;', 'wp_x') === 'TRUNCATE wp_x;',
    'quotes written around a numbered placeholder stay' => static fn () => $fill("'%1\$s'", 'a') === "'a'",
    'numbered placeholders address the list directly' => static fn () => $fill('%2$s-%1$s', 'a', 'b') === 'b-a',
    'unnumbered placeholders count on their own beside numbered ones' => static fn () => $fill('%1$s %s', 'a', 'b') === "a 'a'",
    '%d casts to an integer' => static fn () => $fill('%d %1$d', '12abc') === '12 12',
    '%f keeps its precision' => static fn () => $fill('%.2f', '1.005') === '1.00',
    'a width pads and does not quote' => static fn () => $fill('%5s', 'a') === '    a',
    '%i backticks an identifier, numbered or not' => static fn () => $fill('%i %1$i', 'a`b') === '`a``b` `a``b`',
    '%% is a literal' => static fn () => $fill('100%% of %s', 'x') === "100% of 'x'",
    'a placeholder with no argument empties the query' => static fn () => $fill('%s %s', 'a') === null,
    'an array argument becomes nothing' => static fn () => $fill('%s', ['a']) === "''",
];
