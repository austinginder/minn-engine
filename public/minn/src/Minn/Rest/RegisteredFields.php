<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\RestError;

/**
 * The fields plugin code adds to an object type with register_rest_field,
 * as the reference serves them (probe rest-fields). A get callback's value
 * follows the item's own fields, before its links. It is handed the item
 * so far (without links, cut to the fields asked for, id kept), the
 * field's name, the request and the object type. A field is left out when
 * _fields does not name it or its schema's context does not include the
 * request's. A write runs the update callbacks for the fields its body
 * names, with the saved object, before rest_after_insert; an error one
 * returns is the write's answer.
 */
final class RegisteredFields
{
    /** The item with the type's registered fields added, answering the request. @param array<string, mixed> $item @return array<string, mixed> */
    public static function add(array $item, string $type, \WP_REST_Request $request): array
    {
        $fields = self::of($type);
        if ($fields === []) {
            return $item;
        }
        $context = self::context($request, $type);
        $wanted = self::wanted($request);
        $links = $item['_links'] ?? null;
        unset($item['_links']);
        $handed = $context === 'embed' ? Embed::shape($type, $item) : $item;
        $handed = $wanted === null ? $handed : array_filter($handed, static fn ($key) => $key === 'id' || \rest_is_field_included((string) $key, $wanted), ARRAY_FILTER_USE_KEY);
        foreach ($fields as $name => $options) {
            if (empty($options['get_callback']) || !self::inContext($options, $context) || ($wanted !== null && !\rest_is_field_included((string) $name, $wanted))) {
                continue;
            }
            $value = call_user_func($options['get_callback'], $handed, $name, $request, $type);
            $item[$name] = $value;
            $handed[$name] = $value;
        }
        if ($links !== null) {
            $item['_links'] = $links;
        }
        return $item;
    }

    /** Runs the update callbacks for the fields the request's body names; an error one returns is thrown as the write's answer. */
    public static function update(object $object, string $type, \WP_REST_Request $request): void
    {
        foreach (self::of($type) as $name => $options) {
            if (empty($options['update_callback']) || !isset($request[$name])) {
                continue;
            }
            $result = call_user_func($options['update_callback'], $request[$name], $object, $name, $request, $type);
            if ($result instanceof \WP_Error) {
                throw self::refusal($result);
            }
        }
    }

    /**
     * The context the item is answered in, set on the request as the
     * reference's controllers set it: a write and a delete answer in edit
     * (a term's delete in view); a read in the one asked for, or view.
     */
    public static function context(\WP_REST_Request $request, string $type): string
    {
        $method = $request->get_method();
        if ($method !== 'GET' && $method !== 'HEAD') {
            $context = $method === 'DELETE' && \taxonomy_exists($type) ? 'view' : 'edit';
            if ($request['context'] !== $context) {
                $request->set_param('context', $context);
            }
            return $context;
        }
        $context = $request['context'];
        if (!is_string($context) || $context === '') {
            $request->set_default_params(['context' => 'view'] + (array) $request->get_default_params());
            return 'view';
        }
        return $context;
    }

    /** The names of the fields registered for the type that show in the context. @return list<string> */
    public static function shownIn(string $type, string $context): array
    {
        return array_map('strval', array_keys(array_filter(self::of($type), static fn (array $options) => self::inContext($options, $context))));
    }

    /** @return array<string, array<string, mixed>> */
    private static function of(string $type): array
    {
        $fields = $GLOBALS['wp_rest_additional_fields'][$type] ?? [];
        return is_array($fields) ? $fields : [];
    }

    /** The fields _fields names, or null when it names none. @return list<string>|null */
    private static function wanted(\WP_REST_Request $request): ?array
    {
        if (!isset($request['_fields'])) {
            return null;
        }
        $wanted = array_map('trim', \wp_parse_list($request['_fields']));
        return $wanted === [] ? null : $wanted;
    }

    /** A field without a schema, or one whose schema names no context, shows in every context. @param array<string, mixed> $options */
    private static function inContext(array $options, string $context): bool
    {
        $contexts = is_array($options['schema'] ?? null) ? ($options['schema']['context'] ?? null) : null;
        return empty($contexts) || in_array($context, (array) $contexts, true);
    }

    private static function refusal(\WP_Error $error): RestError
    {
        $data = $error->get_error_data();
        if (is_array($data) && isset($data['status'])) {
            return new RestError((string) $error->get_error_code(), $error->get_error_message(), (int) $data['status'], array_diff_key($data, ['status' => true]));
        }
        return RestError::bare((string) $error->get_error_code(), $error->get_error_message());
    }
}
