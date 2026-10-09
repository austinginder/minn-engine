<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Json;

/**
 * REST schemas captured from the reference for the facade's controller
 * classes (data/rest-schemas.json): an item schema, a collection's
 * parameters or a route argument, by name. Callbacks are not data, so a
 * controller adds its own.
 */
final class SchemaData
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $all = null;

    /** One captured schema by name; an empty array when there is none. @return array<string, mixed> */
    public static function of(string $name): array
    {
        self::$all ??= (array) Json::file(MINN_ENGINE_DIR . '/data/rest-schemas.json');
        return (array) (self::$all[$name] ?? []);
    }
}
