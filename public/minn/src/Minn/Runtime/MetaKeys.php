<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;

/**
 * The meta keys code registers, kept where the reference keeps them
 * ($wp_meta_keys: object type => subtype => key => arguments), with what a
 * registration wires up (probe meta-api). A key's sanitize and auth
 * callbacks hang on their filters (with _for_{subtype} for a subtype's
 * key); a key with no auth callback gets __return_true, or __return_false
 * when protected. A default is served through filter_default_metadata. A
 * registration is refused (with the reference's notice) for an array shown
 * in REST without its items' schema, revisions where the type or subtype
 * has none, and, once its callbacks are hooked, a default its schema does
 * not accept. The old form (callbacks as plain arguments) hooks them and
 * returns false.
 */
final class MetaKeys
{
    private const DEFAULTS = ['object_subtype' => '', 'type' => 'string', 'label' => '', 'description' => '', 'default' => '', 'single' => false, 'sanitize_callback' => null, 'auth_callback' => null, 'show_in_rest' => false, 'revisions_enabled' => false];

    /** Registers a key; true when it joins the registry. */
    public static function register(string $objectType, string $key, mixed $args, mixed $deprecated): bool
    {
        $legacy = is_callable($args) || is_callable($deprecated);
        $args = is_callable($args) ? ['sanitize_callback' => $args] : (array) $args;
        if (is_callable($deprecated)) {
            $args['auth_callback'] = $deprecated;
        }
        $defaults = self::DEFAULTS;
        // Unknown arguments are dropped by a default filter (_wp_register_meta_args_allowed_list) ahead of the plugins' own.
        $args = (array) \apply_filters('register_meta_args', $args, $defaults, $objectType, $key);
        unset($defaults['default']);
        $args = array_merge($defaults, $args);
        if (empty($args['auth_callback'])) {
            $args['auth_callback'] = \is_protected_meta($key, $objectType) ? '__return_false' : '__return_true';
        }
        $subtype = (string) $args['object_subtype'];
        $refusal = self::refusal($objectType, $subtype, $args);
        if ($refusal !== null) {
            \_doing_it_wrong('register_meta', $refusal, '5.3.0');
            return false;
        }
        $suffix = $subtype === '' ? '' : "_for_{$subtype}";
        if (is_callable($args['sanitize_callback'])) {
            Runtime::hooks()->add("sanitize_{$objectType}_meta_{$key}{$suffix}", $args['sanitize_callback'], 10, 4);
        }
        if (is_callable($args['auth_callback'])) {
            Runtime::hooks()->add("auth_{$objectType}_meta_{$key}{$suffix}", $args['auth_callback'], 10, 6);
        }
        if (array_key_exists('default', $args)) {
            if (!self::defaultFits($args)) {
                \_doing_it_wrong('register_meta', Gettext::text('When registering a default meta value the data must match the type provided.'), '5.5.0');
                return false;
            }
            if (!Runtime::hooks()->has("default_{$objectType}_metadata", 'filter_default_metadata')) {
                Runtime::hooks()->add("default_{$objectType}_metadata", 'filter_default_metadata', 10, 5);
            }
        }
        if ($legacy) {
            return false;
        }
        unset($args['object_subtype']);
        $GLOBALS['wp_meta_keys'][$objectType][$subtype][$key] = $args;
        return true;
    }

    /** Takes a key out of the registry, and its callbacks off their filters. */
    public static function unregister(string $objectType, string $key, string $subtype): bool
    {
        $args = $GLOBALS['wp_meta_keys'][$objectType][$subtype][$key] ?? null;
        if (!is_array($args)) {
            return false;
        }
        $suffix = $subtype === '' ? '' : "_for_{$subtype}";
        foreach (['sanitize' => 'sanitize_callback', 'auth' => 'auth_callback'] as $kind => $callback) {
            if (isset($args[$callback]) && is_callable($args[$callback])) {
                Runtime::hooks()->remove("{$kind}_{$objectType}_meta_{$key}{$suffix}", $args[$callback]);
            }
        }
        unset($GLOBALS['wp_meta_keys'][$objectType][$subtype][$key]);
        if (empty($GLOBALS['wp_meta_keys'][$objectType][$subtype])) {
            unset($GLOBALS['wp_meta_keys'][$objectType][$subtype]);
        }
        if (empty($GLOBALS['wp_meta_keys'][$objectType])) {
            unset($GLOBALS['wp_meta_keys'][$objectType]);
        }
        return true;
    }

    /** The keys registered for an object type and subtype ('' for every subtype), in registration order. @return array<string, array<string, mixed>> */
    public static function of(string $objectType, string $subtype = ''): array
    {
        $keys = $GLOBALS['wp_meta_keys'][$objectType][$subtype] ?? [];
        return is_array($keys) ? $keys : [];
    }

    /** The keys that apply to one object: those for every subtype, then its subtype's. @return array<string, array<string, mixed>> */
    public static function forObject(string $objectType, string $subtype): array
    {
        return $subtype === '' ? self::of($objectType) : array_merge(self::of($objectType), self::of($objectType, $subtype));
    }

    /**
     * The registered default for a key as filter_default_metadata answers:
     * the one for every subtype, else the object's subtype's; the value
     * handed in when neither registered one.
     */
    public static function defaultValue(mixed $value, int $objectId, string $key, bool $single, string $objectType): mixed
    {
        $defaults = [];
        foreach ((array) ($GLOBALS['wp_meta_keys'][$objectType] ?? []) as $subtype => $keys) {
            if (is_array($keys[$key] ?? null) && array_key_exists('default', $keys[$key])) {
                $defaults[(string) $subtype] = $keys[$key]['default'];
            }
        }
        if ($defaults === []) {
            return $value;
        }
        $subtype = array_key_exists('', $defaults) ? '' : self::subtype($objectType, $objectId);
        if (!array_key_exists($subtype, $defaults)) {
            return $value;
        }
        return $single ? $defaults[$subtype] : [$defaults[$subtype]];
    }

    /** The object's subtype: a post's type, a term's taxonomy, comment or user while they exist; '' otherwise. */
    public static function subtype(string $objectType, int $objectId): string
    {
        $term = $objectType === 'term' ? \get_term($objectId) : null;
        $subtype = match ($objectType) {
            'post' => (string) (\get_post($objectId)?->post_type ?? ''),
            'term' => $term instanceof \WP_Term ? $term->taxonomy : '',
            'comment' => \get_comment($objectId) instanceof \WP_Comment ? 'comment' : '',
            'user' => \get_userdata($objectId) instanceof \WP_User ? 'user' : '',
            default => '',
        };
        return (string) \apply_filters("get_object_subtype_{$objectType}", $subtype, $objectId);
    }

    /**
     * A meta capability (edit, add or delete a post's, comment's, term's or
     * user's meta) as the reference maps it: what editing the object needs,
     * then the capability itself when the key is not allowed (protected, or
     * refused by its auth filter); nothing for an object that does not
     * exist. Null for any other capability.
     *
     * @param list<mixed> $args the object id, then the key
     * @return list<string>|null
     */
    public static function capabilities(string $capability, int $userId, array $args): ?array
    {
        if (!preg_match('/^(?:edit|add|delete)_(post|comment|term|user)_meta$/', $capability, $m)) {
            return null;
        }
        $objectType = $m[1];
        $objectId = (int) ($args[0] ?? 0);
        $subtype = self::subtype($objectType, $objectId);
        if ($subtype === '') {
            return ['do_not_allow'];
        }
        $caps = \map_meta_cap("edit_{$objectType}", $userId, $objectId);
        $key = $args[1] ?? false;
        if ($key) {
            $allowed = !\is_protected_meta($key, $objectType);
            $filter = Runtime::hooks()->has("auth_{$objectType}_meta_{$key}_for_{$subtype}") ? "auth_{$objectType}_meta_{$key}_for_{$subtype}" : "auth_{$objectType}_meta_{$key}";
            if (!\apply_filters($filter, $allowed, $key, $objectId, $userId, $capability, $caps)) {
                $caps[] = $capability;
            }
        }
        return $caps;
    }

    /** Why the reference refuses a registration before hooking anything, or null. @param array<string, mixed> $args */
    private static function refusal(string $objectType, string $subtype, array $args): ?string
    {
        $rest = $args['show_in_rest'];
        if ($args['type'] === 'array' && !empty($rest) && (!is_array($rest) || empty($rest['schema']['items']))) {
            return Gettext::text('When registering an "array" meta type to show in the REST API, you must specify the schema for each array item in "show_in_rest.schema.items".');
        }
        if (!empty($args['revisions_enabled'])) {
            if ($objectType !== 'post') {
                return Gettext::text('Meta keys cannot enable revisions support unless the object type supports revisions.');
            }
            if ($subtype !== '' && !\post_type_supports($subtype, 'revisions')) {
                return Gettext::text('Meta keys cannot enable revisions support unless the object subtype supports revisions.');
            }
        }
        return null;
    }

    /** Whether a default fits the key's type, and its REST schema when it has one. @param array<string, mixed> $args */
    private static function defaultFits(array $args): bool
    {
        $schema = $args;
        if (is_array($args['show_in_rest']) && isset($args['show_in_rest']['schema']) && is_array($args['show_in_rest']['schema'])) {
            $schema = array_merge($schema, $args['show_in_rest']['schema']);
        }
        return !\is_wp_error(\rest_validate_value_from_schema($args['default'], $schema));
    }
}
