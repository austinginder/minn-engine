<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The Interactivity API's bindings under kses (probe patch-714). A tag
 * whose allowlist checks some attribute's value cannot let post content
 * bind an attribute kses did not allow plainly: a binding would set at
 * render time the value kses judged, or never saw. Directives other than
 * data-wp-bind-- are left to the allowlist.
 */
final class KsesDirectives
{
    private const BIND = 'data-wp-bind--';

    /** The rule keys that say nothing about an attribute's value. */
    private const NOT_VALUE_RULES = ['required', 'valueless'];

    /**
     * Whether a tag's attribute allowlist checks any value: some attribute's
     * rules are a list holding a key other than required and valueless
     * (_wp_kses_restricts_attribute_values).
     */
    public static function restrictsValues(mixed $attributes): bool
    {
        if (!is_array($attributes)) {
            return false;
        }
        foreach ($attributes as $rules) {
            if (is_array($rules) && array_diff(array_map('strval', array_keys($rules)), self::NOT_VALUE_RULES) !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether a directive, as written, binds an attribute the tag may not
     * take from a binding (_wp_kses_directive_binds_value_restricted_attribute):
     * a data-wp-bind-- directive on a tag that checks values, naming an
     * attribute (compared as written) that is not allowed plainly. Allowed
     * plainly is a rule of exactly true, or a data- name under "data-*".
     */
    public static function bindsRestricted(string $directive, mixed $attributes): bool
    {
        if (!str_starts_with($directive, self::BIND) || !self::restrictsValues($attributes)) {
            return false;
        }
        $bound = substr($directive, strlen(self::BIND));
        if (($attributes[$bound] ?? null) === true) {
            return false;
        }
        return !(str_starts_with($bound, 'data-') && ($attributes['data-*'] ?? null) === true);
    }
}
