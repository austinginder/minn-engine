<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\RestError;
use Minn\Runtime\MetaKeys;
use Minn\Runtime\Runtime;
use Minn\I18n\Gettext;
use Minn\Support\Slashes;

/**
 * An object's meta field over REST, from the keys registered to show
 * there (probe rest-meta). Each key answers under its REST name (its own,
 * or show_in_rest's name): those for every subtype, then the object's
 * subtype's, left out of a context their schema does not name. A single
 * key reads its first stored value (its default, or its type's empty value,
 * when none), a multiple key each one; each value is checked against the
 * key's schema (null when it does not fit, the empty value of a scalar
 * type for '') and cast, or handed to the key's prepare callback. A write
 * goes key by key in registration order: null (or an empty list) deletes,
 * a value its schema refuses is a 400, and a key the user may not edit is
 * a 403 (an unchanged single value is left alone first). The errors are
 * gathered; the first is the answer, with the others beside it.
 */
final class RestMeta
{
    private const SCALARS = ['string', 'boolean', 'integer', 'number'];

    /** The meta field of an object in a context (the one the request answers in, when none is named), keyed by REST name. @return array<string, mixed> */
    public static function read(string $objectType, int $objectId, string $subtype, ?string $context = null): array
    {
        $request = self::request();
        $context ??= RegisteredFields::context($request, $objectType === 'term' ? $subtype : $objectType);
        $out = [];
        foreach (self::fields($objectType, $subtype) as $key => $field) {
            $contexts = $field['schema']['context'] ?? null;
            if (is_array($contexts) && $contexts !== [] && !in_array($context, $contexts, true)) {
                continue;
            }
            $stored = \get_metadata($objectType, $objectId, $key, false);
            $stored = is_array($stored) ? array_values($stored) : [];
            if ($field['single']) {
                $out[$field['name']] = self::prepare($stored === [] ? $field['schema']['default'] : $stored[0], $field, $request);
                continue;
            }
            $out[$field['name']] = array_map(static fn ($value) => self::prepare($value, $field, $request), $stored);
        }
        return $out;
    }

    /** Applies the meta a write request names, when it names any. */
    public static function writeFrom(\WP_REST_Request $request, string $objectType, int $objectId, string $subtype): void
    {
        $meta = $request['meta'];
        if (is_array($meta) && $meta !== []) {
            self::write($objectType, $objectId, $subtype, $meta);
        }
    }

    /** Applies a request's meta to an object; the gathered refusals are thrown. */
    public static function write(string $objectType, int $objectId, string $subtype, array $meta): void
    {
        $errors = [];
        foreach (self::fields($objectType, $subtype) as $key => $field) {
            if (!array_key_exists($field['name'], $meta)) {
                continue;
            }
            $value = $meta[$field['name']];
            $error = match (true) {
                $value === null || ($value === [] && !$field['single']) => self::delete($objectType, $objectId, (string) $key, $field),
                !$field['single'] && is_array($value) && in_array(null, $value, true) => self::nullStored($field['name']),
                default => self::update($objectType, $objectId, $subtype, (string) $key, $field, $value),
            };
            if ($error !== null) {
                $errors[] = $error;
            }
        }
        if ($errors !== []) {
            throw self::gathered($errors);
        }
    }

    /**
     * The keys registered to show in REST for an object type and subtype,
     * each with its REST name, whether it is single, its schema (a multiple
     * key's an array of its own) and its prepare callback; keys of a type
     * REST does not know are left out.
     *
     * @return array<string, array{name: string, single: bool, schema: array<string, mixed>, prepare_callback: mixed}>
     */
    public static function fields(string $objectType, string $subtype): array
    {
        $fields = [];
        foreach (MetaKeys::forObject($objectType, $subtype) as $key => $args) {
            if (empty($args['show_in_rest'])) {
                continue;
            }
            $rest = is_array($args['show_in_rest']) ? $args['show_in_rest'] : [];
            $field = array_merge(['name' => (string) $key, 'single' => !empty($args['single']), 'type' => $args['type'] ?: null, 'schema' => [], 'prepare_callback' => null], $rest);
            $schema = array_merge(['type' => $field['type'], 'title' => (string) ($args['label'] ?? ''), 'description' => (string) ($args['description'] ?? ''), 'default' => $args['default'] ?? null], (array) $field['schema']);
            $type = !empty($schema['type']) ? $schema['type'] : $field['type'];
            if (!in_array($type, [...self::SCALARS, 'array', 'object'], true)) {
                continue;
            }
            $schema['default'] ??= self::emptyValue((string) $type);
            $schema = \rest_default_additional_properties_to_false($schema);
            $field['schema'] = $field['single'] ? $schema : ['type' => 'array', 'items' => $schema];
            $field['single'] = (bool) $field['single'];
            $fields[(string) $key] = $field;
        }
        return $fields;
    }

    /** @param array<string, mixed> $field */
    private static function prepare(mixed $value, array $field, \WP_REST_Request $request): mixed
    {
        if (is_callable($field['prepare_callback'])) {
            return call_user_func($field['prepare_callback'], $value, $request, $field);
        }
        $schema = $field['single'] ? $field['schema'] : $field['schema']['items'];
        if ($value === '' && in_array($schema['type'] ?? null, ['boolean', 'integer', 'number'], true)) {
            $value = self::emptyValue((string) $schema['type']);
        }
        return \is_wp_error(\rest_validate_value_from_schema($value, $schema)) ? null : \rest_sanitize_value_from_schema($value, $schema);
    }

    /** @param array<string, mixed> $field */
    private static function update(string $objectType, int $objectId, string $subtype, string $key, array $field, mixed $value): ?\WP_Error
    {
        $valid = \rest_validate_value_from_schema($value, $field['schema'], 'meta.' . $field['name']);
        if (\is_wp_error($valid)) {
            $valid->add_data(['status' => 400]);
            return $valid;
        }
        $value = \rest_sanitize_value_from_schema($value, $field['schema']);
        if ($field['single']) {
            $old = \get_metadata($objectType, $objectId, $key);
            if (is_array($old) && count($old) === 1 && self::same($objectType, $subtype, $key, $field, $old[0], $value)) {
                return null;
            }
            if (!\current_user_can("edit_{$objectType}_meta", $objectId, $key)) {
                return self::refused('rest_cannot_update', $field['name']);
            }
            return \update_metadata($objectType, $objectId, Slashes::add($key), Slashes::add($value)) ? null : self::failed($key, $field['name']);
        }
        if (!\current_user_can("edit_{$objectType}_meta", $objectId, $key)) {
            return self::refused('rest_cannot_update', $field['name']);
        }
        return self::replaceAll($objectType, $objectId, $subtype, $key, $field, (array) $value);
    }

    /**
     * A multiple key set to a list: values already stored once are kept,
     * the rest of the stored ones removed and the new ones added.
     *
     * @param array<string, mixed> $field
     * @param array<mixed> $values
     */
    private static function replaceAll(string $objectType, int $objectId, string $subtype, string $key, array $field, array $values): ?\WP_Error
    {
        $current = \get_metadata($objectType, $objectId, $key, false);
        $current = is_array($current) ? $current : [];
        [$toRemove, $toAdd] = [$current, $values];
        foreach ($values as $addKey => $value) {
            $matches = array_keys(array_filter($current, static fn ($stored) => self::same($objectType, $subtype, $key, $field, $stored, $value)));
            if (count($matches) === 1) {
                unset($toRemove[$matches[0]], $toAdd[$addKey]);
            }
        }
        foreach (array_unique($toRemove, SORT_REGULAR) as $value) {
            if (!\delete_metadata($objectType, $objectId, Slashes::add($key), Slashes::add($value))) {
                return self::failed($key, $field['name']);
            }
        }
        foreach ($toAdd as $value) {
            if (!\add_metadata($objectType, $objectId, Slashes::add($key), Slashes::add($value))) {
                return self::failed($key, $field['name']);
            }
        }
        return null;
    }

    /** @param array<string, mixed> $field */
    private static function delete(string $objectType, int $objectId, string $key, array $field): ?\WP_Error
    {
        if ($field['single'] && \is_wp_error(\rest_validate_value_from_schema(\get_metadata($objectType, $objectId, $key, true), $field['schema']))) {
            return self::nullStored($field['name']);
        }
        if (!\current_user_can("delete_{$objectType}_meta", $objectId, $key)) {
            return self::refused('rest_cannot_delete', $field['name']);
        }
        if (\get_metadata_raw($objectType, $objectId, Slashes::add($key)) === null) {
            return null;
        }
        return \delete_metadata($objectType, $objectId, Slashes::add($key)) ? null : new \WP_Error('rest_meta_database_error', Gettext::text('Could not delete meta value from database.'), ['key' => $field['name'], 'status' => 500]);
    }

    /** Whether a value sanitizes to what is stored (scalar types compared as the text the store keeps). @param array<string, mixed> $field */
    private static function same(string $objectType, string $subtype, string $key, array $field, mixed $stored, mixed $value): bool
    {
        $sanitized = \sanitize_meta($key, $value, $objectType, $subtype);
        $type = $field['single'] ? ($field['schema']['type'] ?? null) : ($field['schema']['items']['type'] ?? null);
        if (in_array($type, self::SCALARS, true)) {
            $sanitized = (string) $sanitized;
        }
        return $sanitized === $stored;
    }

    private static function refused(string $code, string $name): \WP_Error
    {
        /* translators: %s: Custom field key. */
        return new \WP_Error($code, sprintf(Gettext::text('Sorry, you are not allowed to edit the %s custom field.'), $name), ['key' => $name, 'status' => \rest_authorization_required_code()]);
    }

    private static function failed(string $key, string $name): \WP_Error
    {
        /* translators: %s: Custom field key. */
        return new \WP_Error('rest_meta_database_error', sprintf(Gettext::text('Could not update the meta value of %s in database.'), $key), ['key' => $name, 'status' => 500]);
    }

    private static function nullStored(string $name): \WP_Error
    {
        /* translators: %s: Custom field key. */
        return new \WP_Error('rest_invalid_stored_value', sprintf(Gettext::text('The %s property has an invalid stored value, and cannot be updated to null.'), $name), ['status' => 500]);
    }

    /** The gathered errors as one refusal: the first answers, with its earlier data beside it, and the rest as additional errors. @param list<\WP_Error> $errors */
    private static function gathered(array $errors): RestError
    {
        $formatted = [];
        foreach ($errors as $error) {
            foreach ($error->errors as $code => $messages) {
                $data = $error->get_all_error_data($code);
                $last = array_pop($data);
                foreach ((array) $messages as $message) {
                    $formatted[] = ['code' => (string) $code, 'message' => (string) $message, 'data' => $last] + ($data === [] ? [] : ['additional_data' => $data]);
                }
            }
        }
        $first = array_shift($formatted);
        $data = is_array($first['data']) ? $first['data'] : [];
        $topLevel = array_diff_key($first, ['code' => true, 'message' => true, 'data' => true]) + ($formatted === [] ? [] : ['additional_errors' => $formatted]);
        return new RestError($first['code'], $first['message'], (int) ($data['status'] ?? 500), $data, $topLevel);
    }

    private static function emptyValue(string $type): mixed
    {
        return match ($type) {
            'string' => '',
            'boolean' => false,
            'integer', 'number' => 0,
            'array', 'object' => [],
            default => null,
        };
    }

    private static function request(): \WP_REST_Request
    {
        $request = Runtime::current()->get('rest_prepare_request');
        return $request instanceof \WP_REST_Request ? $request : new \WP_REST_Request('GET', '/');
    }
}
