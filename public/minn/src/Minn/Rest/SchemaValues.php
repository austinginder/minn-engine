<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use JsonSerializable;
use stdClass;

/**
 * The value side of JSON Schema, as the reference applies it: what counts
 * as a boolean, integer, array, or object, the coercions to each, the
 * comparisons and date, colour, and uuid parsers the formats use, and the
 * combining walk anyOf and oneOf share. Schema holds the rules; this holds
 * what they are applied to.
 */
final class SchemaValues
{
    /** Whether a value reads as a boolean, the strings included. */
    public static function isBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['false', 'true', '0', '1', ''], true);
        }
        return is_int($value) && in_array($value, [0, 1], true);
    }

    /** The boolean a value reads as. */
    public static function toBoolean(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, ['false', '0'], true)) {
                $value = false;
            }
        }
        return (bool) $value;
    }

    /** Whether a value is a whole number. */
    public static function isInteger(mixed $value): bool
    {
        return is_numeric($value) && round((float) $value) === (float) $value;
    }

    /** Whether a value reads as a list, a comma-separated string included. */
    public static function isArray(mixed $value): bool
    {
        if (is_scalar($value)) {
            $value = self::list($value);
        }
        return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
    }

    /** The list a value reads as. */
    public static function toArray(mixed $value): array
    {
        if (is_scalar($value)) {
            return self::list($value);
        }
        return is_array($value) ? array_values($value) : [];
    }

    /** Whether a value reads as an object. */
    public static function isObject(mixed $value): bool
    {
        if ($value === '' || $value instanceof stdClass) {
            return true;
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        return is_array($value);
    }

    /** The object a value reads as, as an array. */
    public static function toObject(mixed $value): array
    {
        if ($value === '') {
            return [];
        }
        if ($value instanceof stdClass) {
            return (array) $value;
        }
        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }
        return is_array($value) ? $value : [];
    }

    /** The first of the schema's types, in the order it lists them, that the value reads as (an empty string is a string when that is allowed); '' when none. */
    public static function bestType(mixed $value, array|string $types): string
    {
        $checks = ['array' => self::isArray(...), 'object' => self::isObject(...), 'null' => static fn ($v) => $v === null, 'boolean' => self::isBoolean(...), 'integer' => self::isInteger(...), 'number' => static fn ($v) => is_numeric($v), 'string' => static fn ($v) => is_string($v)];
        // The schema's own order decides (WooCommerce's "mixed" lists null, object,
        // string, number, ...); an empty string is a string whenever that is allowed.
        $types = array_values(array_filter((array) $types, static fn ($type) => isset($checks[$type])));
        if (count($types) === 1) {
            return $types[0];
        }
        if ($value === '' && in_array('string', $types, true)) {
            return 'string';
        }
        foreach ($types as $type) {
            if ($checks[$type]($value)) {
                return $type;
            }
        }
        return '';
    }

    /** Whether two values are equal by the reference's loose rules. */
    public static function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            if (count($a) !== count($b)) {
                return false;
            }
            foreach ($a as $index => $value) {
                if (!isset($b[$index]) || !self::valuesEqual($value, $b[$index])) {
                    return false;
                }
            }
            return true;
        }
        if ((is_int($a) && is_float($b)) || (is_float($a) && is_int($b))) {
            return (float) $a === (float) $b;
        }
        return $a === $b;
    }

    /** The schema of the patternProperties entry a property name matches, or null. */
    public static function patternProperty(string $property, array $args): ?array
    {
        foreach ($args['patternProperties'] ?? [] as $pattern => $schema) {
            if (preg_match('#' . str_replace('#', '\\#', (string) $pattern) . '#u', $property)) {
                return $schema;
            }
        }
        return null;
    }

    /** Keys sorted at every level, so two equal structures serialise the same. */
    public static function stabilize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        ksort($value);
        foreach ($value as $k => $v) {
            $value[$k] = self::stabilize($v);
        }
        return $value;
    }

    /** A timestamp for an RFC3339-shaped date, false otherwise; $forceUtc reads the offset as Z. */
    public static function parseDate(mixed $date, bool $forceUtc = false): int|false
    {
        $date = (string) $date;
        if ($forceUtc) {
            $date = (string) preg_replace('#^(.*?)[+-]\d{2}:?\d{2}$#', '$1Z', $date);
        }
        if (!preg_match('#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#', $date)) {
            return false;
        }
        return strtotime($date);
    }

    /** A hex colour as given, or false. */
    public static function parseHexColor(mixed $color): mixed
    {
        return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color) ? $color : false;
    }

    /** Whether a value is a version-4 uuid. */
    public static function isUuid(mixed $uuid): bool
    {
        return is_string($uuid) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * A comma- or space-separated string as a list.
     *
     * @return list<string>
     */
    public static function list(mixed $value): array
    {
        return preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Which of a combining schema's branches a value matches: the branch on
     * a single match (or the first, for anyOf or when asked to stop), else
     * every match with its index, else the errors the branches raised.
     *
     * @param Closure(mixed, array): (true|Refusal) $validate a branch validator
     * @return array{schema?: array, matches?: array<int, array>, errors?: list<array{error: Refusal, schema: array, index: int}>}
     */
    public static function combining(mixed $value, array $args, bool $stopAfterFirst, Closure $validate): array
    {
        $matches = [];
        $errors = [];
        foreach ($args['anyOf'] ?? $args['oneOf'] ?? [] as $index => $schema) {
            $result = $validate($value, $schema);
            if ($result === true) {
                if ($stopAfterFirst || isset($args['anyOf'])) {
                    return ['schema' => $schema];
                }
                $matches[$index] = $schema;
                continue;
            }
            $errors[] = ['error' => $result, 'schema' => $schema, 'index' => $index];
        }
        if (count($matches) === 1) {
            return ['schema' => reset($matches)];
        }
        return $matches === [] ? ['errors' => $errors] : ['matches' => $matches];
    }
}
