<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use WeakMap;

/**
 * Answers for queries the engine writes itself and already knows the result
 * of, from what it has read once this request: a template part's lookup
 * from the theme's saved templates, the theme's term from the same read. The
 * query still runs every filter the reference runs; only the database is
 * spared, and only while no plugin has changed the query (its variables, a
 * clause, the request), so a lookup a plugin steers runs as written.
 */
final class QueryAnswers
{
    /** @var WeakMap<object, Closure>|null */
    private static ?WeakMap $answers = null;

    /** Marks a query object as one the engine can answer, before it runs. */
    public static function expect(object $query, Closure $answer): void
    {
        self::$answers ??= new WeakMap();
        self::$answers[$query] = $answer;
    }

    /** The answer a query object was given, taken so it answers once; null for any other query. */
    public static function take(object $query): ?Closure
    {
        if (self::$answers === null || !isset(self::$answers[$query])) {
            return null;
        }
        $answer = self::$answers[$query];
        unset(self::$answers[$query]);
        return $answer;
    }
}
