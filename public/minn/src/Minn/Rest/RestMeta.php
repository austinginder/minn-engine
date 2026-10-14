<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\Can;
use Minn\Objects\Errors;
use Minn\Objects\RestRequest;
use Minn\RestError;
use Minn\Runtime\Handoff;
use Minn\Runtime\MetaData;
use Minn\Runtime\MetaKeys;
use Minn\Runtime\Records;
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
            $out[$field['name']] = self::value($objectType, $objectId, (string) $key, $field, $request);
        }
        return $out;
    }

    /**
     * One field's value for an object: a single key's first stored value (its
     * default when none), a multiple key's each one, prepared for the response.
     *
     * @param array<string, mixed> $field
     */
    public static function value(string $objectType, int $objectId, string $key, array $field, RestRequest $request): mixed
    {
        $stored = MetaData::get($objectType, $objectId, $key, false);
        $stored = is_array($stored) ? array_values($stored) : [];
        if ($field['single']) {
            return self::prepare($stored === [] ? $field['schema']['default'] : $stored[0], $field, $request);
        }
        return array_map(static fn ($value) => self::prepare($value, $field, $request), $stored);
    }

    /** Applies the meta a write request names, when it names any. */
    public static function writeFrom(RestRequest $request, string $objectType, int $objectId, string $subtype): void
    {
        $meta = $request['meta'];
        if (is_array($meta) && $meta !== []) {
            self::write($objectType, $objectId, $subtype, $meta);
        }
    }

    /** Applies a request's meta to an object; the gathered refusals are thrown. */
    public static function write(string $objectType, int $objectId, string $subtype, array $meta): void
    {
        $errors = self::errors($objectType, $objectId, $subtype, $meta);
        if ($errors !== []) {
            throw self::gathered($errors);
        }
    }

    /** Applies a request's meta to an object, key by key in registration order; the refusals come back. @return list<Errors> */
    public static function errors(string $objectType, int $objectId, string $subtype, array $meta): array
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
        return $errors;
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
            $schema = Schema::closeObjects((array) $schema);
            $field['schema'] = $field['single'] ? $schema : ['type' => 'array', 'items' => $schema];
            $field['single'] = (bool) $field['single'];
            $fields[(string) $key] = $field;
        }
        return $fields;
    }

    /** A value for the response: through the field's prepare callback, or checked against its schema (null when it does not fit) and cast. @param array<string, mixed> $field */
    public static function prepare(mixed $value, array $field, RestRequest $request): mixed
    {
        if (is_callable($field['prepare_callback'] ?? null)) {
            return call_user_func($field['prepare_callback'], $value, $request, $field);
        }
        $schema = $field['single'] ? $field['schema'] : $field['schema']['items'];
        if ($value === '' && in_array($schema['type'] ?? null, ['boolean', 'integer', 'number'], true)) {
            $value = self::emptyValue((string) $schema['type']);
        }
        return Records::isError(SiteSchema::validate($value, $schema)) ? null : SiteSchema::sanitize($value, $schema);
    }

    /** @param array<string, mixed> $field */
    private static function update(string $objectType, int $objectId, string $subtype, string $key, array $field, mixed $value): ?Errors
    {
        $valid = SiteSchema::validate($value, $field['schema'], 'meta.' . $field['name']);
        if (Records::isError($valid)) {
            $valid->add_data(['status' => 400]);
            return $valid;
        }
        $value = SiteSchema::sanitize($value, $field['schema']);
        return $field['single'] ? self::store($objectType, $objectId, $subtype, $key, $field, $value) : self::storeAll($objectType, $objectId, $subtype, $key, $field, (array) $value);
    }

    /** A single key stored: an unchanged value is left alone, then the user must be allowed to edit it. @param array<string, mixed> $field */
    public static function store(string $objectType, int $objectId, string $subtype, string $key, array $field, mixed $value): ?Errors
    {
        $old = MetaData::get($objectType, $objectId, $key);
        if (is_array($old) && count($old) === 1 && self::same($objectType, $subtype, $key, $field, $old[0], $value)) {
            return null;
        }
        if (!Can::current("edit_{$objectType}_meta", $objectId, $key)) {
            return self::refused('rest_cannot_update', $field['name']);
        }
        return MetaData::update($objectType, $objectId, Slashes::add($key), Slashes::add($value)) ? null : self::failed($key, $field['name']);
    }

    /** A multiple key stored as a list, once the user may edit it. @param array<string, mixed> $field @param array<mixed> $values */
    public static function storeAll(string $objectType, int $objectId, string $subtype, string $key, array $field, array $values): ?Errors
    {
        if (!Can::current("edit_{$objectType}_meta", $objectId, $key)) {
            return self::refused('rest_cannot_update', $field['name']);
        }
        return self::replaceAll($objectType, $objectId, $subtype, $key, $field, $values);
    }

    /**
     * A multiple key set to a list: values already stored once are kept,
     * the rest of the stored ones removed and the new ones added.
     *
     * @param array<string, mixed> $field
     * @param array<mixed> $values
     */
    private static function replaceAll(string $objectType, int $objectId, string $subtype, string $key, array $field, array $values): ?Errors
    {
        $current = MetaData::get($objectType, $objectId, $key, false);
        $current = is_array($current) ? $current : [];
        [$toRemove, $toAdd] = [$current, $values];
        foreach ($values as $addKey => $value) {
            $matches = array_keys(array_filter($current, static fn ($stored) => self::same($objectType, $subtype, $key, $field, $stored, $value)));
            if (count($matches) === 1) {
                unset($toRemove[$matches[0]], $toAdd[$addKey]);
            }
        }
        foreach (array_unique($toRemove, SORT_REGULAR) as $value) {
            if (!MetaData::delete($objectType, $objectId, Slashes::add($key), Slashes::add($value))) {
                return self::failed($key, $field['name']);
            }
        }
        foreach ($toAdd as $value) {
            if (!MetaData::add($objectType, $objectId, Slashes::add($key), Slashes::add($value))) {
                return self::failed($key, $field['name']);
            }
        }
        return null;
    }

    /** A key deleted once the user may delete it (a single key's invalid stored value refuses null). @param array<string, mixed> $field */
    public static function delete(string $objectType, int $objectId, string $key, array $field): ?Errors
    {
        if ($field['single'] && Records::isError(SiteSchema::validate(MetaData::get($objectType, $objectId, $key, true), $field['schema']))) {
            return self::nullStored($field['name']);
        }
        if (!Can::current("delete_{$objectType}_meta", $objectId, $key)) {
            return self::refused('rest_cannot_delete', $field['name']);
        }
        if (MetaData::raw($objectType, $objectId, Slashes::add($key)) === null) {
            return null;
        }
        return MetaData::delete($objectType, $objectId, Slashes::add($key)) ? null : Handoff::newError('rest_meta_database_error', Gettext::text('Could not delete meta value from database.'), ['key' => $field['name'], 'status' => 500]);
    }

    /** Whether a value sanitizes to what is stored (scalar types compared as the text the store keeps). @param array<string, mixed> $field */
    public static function same(string $objectType, string $subtype, string $key, array $field, mixed $stored, mixed $value): bool
    {
        $sanitized = MetaData::sanitize($key, $value, $objectType, $subtype);
        $type = $field['single'] ? ($field['schema']['type'] ?? null) : ($field['schema']['items']['type'] ?? null);
        if (in_array($type, self::SCALARS, true)) {
            $sanitized = (string) $sanitized;
        }
        return $sanitized === $stored;
    }

    private static function refused(string $code, string $name): Errors
    {
        /* translators: %s: Custom field key. */
        return Handoff::newError($code, sprintf(Gettext::text('Sorry, you are not allowed to edit the %s custom field.'), $name), ['key' => $name, 'status' => Responses::authorizationRequiredCode()]);
    }

    private static function failed(string $key, string $name): Errors
    {
        /* translators: %s: Custom field key. */
        return Handoff::newError('rest_meta_database_error', sprintf(Gettext::text('Could not update the meta value of %s in database.'), $key), ['key' => $name, 'status' => 500]);
    }

    private static function nullStored(string $name): Errors
    {
        /* translators: %s: Custom field key. */
        return Handoff::newError('rest_invalid_stored_value', sprintf(Gettext::text('The %s property has an invalid stored value, and cannot be updated to null.'), $name), ['status' => 500]);
    }

    /** The gathered errors as one refusal: the first answers, with its earlier data beside it, and the rest as additional errors. @param list<Errors> $errors */
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

    /** A type's empty value: '' for a string, false, 0, or an empty list. */
    public static function emptyValue(string $type): mixed
    {
        return match ($type) {
            'string' => '',
            'boolean' => false,
            'integer', 'number' => 0,
            'array', 'object' => [],
            default => null,
        };
    }

    private static function request(): RestRequest
    {
        $request = Runtime::current()->get('rest_prepare_request');
        return $request instanceof RestRequest ? $request : Handoff::newRestRequest('GET', '/');
    }
}
