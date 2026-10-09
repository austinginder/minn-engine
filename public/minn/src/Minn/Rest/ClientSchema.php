<?php

declare(strict_types=1);

namespace Minn\Rest;

/**
 * A JSON schema trimmed for a client (probe json-schema): only the keywords
 * a profile allows, in the order given, at every level a sub-schema sits.
 *
 * A property's boolean required becomes its object's required list (names
 * whose required is true), kept where the object's own required is not a
 * list, appended last otherwise, even where the profile drops required
 * itself; an object's own list wins. A boolean required is always dropped.
 * Sub-schemas are trimmed where they are objects: a keyword's value, a
 * list's members, a map's entries; anything else stays as given.
 */
final class ClientSchema
{
    private const DRAFT_04_FIRST = ['$schema', 'id', '$ref'];
    private const DRAFT_04_LAST = ['required', 'allOf', 'not', 'definitions', 'dependencies', 'additionalItems'];
    /** Keywords whose value maps names to sub-schemas. */
    private const MAPS = ['properties', 'patternProperties', 'definitions', 'dependencies'];
    /** Keywords whose value is a sub-schema or a list of them. */
    private const SCHEMAS = ['items', 'additionalProperties', 'additionalItems', 'not', 'anyOf', 'oneOf', 'allOf'];

    /**
     * The keywords a profile keeps: draft-04's whole set, the REST API's for
     * any other profile.
     *
     * @return list<string>
     */
    public static function keywords(string $profile): array
    {
        return $profile === 'draft-04' ? [...self::DRAFT_04_FIRST, ...Schema::KEYWORDS, ...self::DRAFT_04_LAST] : Schema::KEYWORDS;
    }

    /**
     * A schema with only the allowed keywords, keyed by keyword (the values
     * do not matter), trimmed all the way down.
     *
     * @param array<array-key, mixed> $schema
     * @param array<array-key, mixed> $allowed
     * @return array<array-key, mixed>
     */
    public static function trim(array $schema, array $allowed): array
    {
        $names = array_key_exists('properties', $allowed) ? self::requiredNames($schema) : [];
        $out = [];
        foreach ($schema as $key => $value) {
            if (!array_key_exists($key, $allowed)) {
                continue;
            }
            if ($key === 'required') {
                if (is_array($value) || $names !== [] || !is_bool($value)) {
                    $out[$key] = is_array($value) || $names === [] ? $value : $names;
                }
                continue;
            }
            $out[$key] = match (true) {
                in_array($key, self::MAPS, true) => is_array($value) ? array_map(static fn ($entry) => self::member($entry, $allowed), $value) : $value,
                in_array($key, self::SCHEMAS, true) => self::position($value, $allowed),
                default => $value,
            };
        }
        if ($names !== [] && !array_key_exists('required', $out)) {
            $out['required'] = $names;
        }
        return $out;
    }

    /** A sub-schema's place: an object trimmed, a list's object members trimmed, anything else as given. */
    private static function position(mixed $value, array $allowed): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        return array_is_list($value) ? array_map(static fn ($member) => self::member($member, $allowed), $value) : self::trim($value, $allowed);
    }

    /** A list member or map entry: trimmed when it is an object schema. */
    private static function member(mixed $value, array $allowed): mixed
    {
        return is_array($value) && !array_is_list($value) ? self::trim($value, $allowed) : $value;
    }

    /** @return list<string> the properties whose required is true */
    private static function requiredNames(array $schema): array
    {
        $names = [];
        foreach (is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            if (is_array($property) && ($property['required'] ?? null) === true) {
                $names[] = (string) $name;
            }
        }
        return $names;
    }
}
