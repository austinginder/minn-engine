<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A term's fields in a context, as the reference's sanitize_term_field
 * treats them (probe term-sanitize): the numeric fields are whole numbers,
 * never below zero, in every context, and raw stops there. edit runs
 * edit_term_{field} and edit_{taxonomy}_{field}; db runs pre_term_{field}
 * and pre_{taxonomy}_{field}, where the saving defaults live, and a slug
 * also pre_category_nicename; rss runs term_{field}_rss and
 * {taxonomy}_{field}_rss; any other context runs term_{field} and
 * {taxonomy}_{field} with the context named. Then a text field is escaped
 * for where it goes: a form (edit), an attribute, a script (js).
 */
final class TermFields
{
    /** The fields sanitize_term runs, in its order. */
    public const FIELDS = ['term_id', 'name', 'description', 'slug', 'count', 'parent', 'term_group', 'term_taxonomy_id', 'object_id'];

    private const NUMBERS = ['parent', 'term_id', 'count', 'term_group', 'term_taxonomy_id', 'object_id'];

    private const ESCAPES = ['edit' => 'esc_html', 'attribute' => 'esc_attr', 'js' => 'esc_js'];

    /** One field's value in a context; a lookup with no taxonomy names false, and its filters get false. */
    public static function field(string $field, mixed $value, int $termId, string|false $taxonomy, string $context): mixed
    {
        $number = in_array($field, self::NUMBERS, true);
        if ($number) {
            $value = max(0, (int) $value);
        }
        if ($context === 'raw') {
            return $value;
        }
        $value = self::filtered($field, $value, $termId, $taxonomy, $context);
        $escape = self::ESCAPES[$context] ?? null;
        return $escape === null || $number ? $value : $escape((string) $value);
    }

    /**
     * A term (object or array) with each of its fields in the context, and
     * its filter set to the context; the term's own id goes to the filters.
     *
     * @template T of object|array
     * @param T $term
     * @return T
     */
    public static function term(object|array $term, string $taxonomy, string $context): object|array
    {
        $read = static fn (string $field): mixed => is_object($term) ? ($term->{$field} ?? null) : ($term[$field] ?? null);
        $termId = (int) $read('term_id');
        foreach (self::FIELDS as $field) {
            if ($read($field) === null) {
                continue;
            }
            $value = self::field($field, $read($field), $termId, $taxonomy, $context);
            is_object($term) ? $term->{$field} = $value : $term[$field] = $value;
        }
        is_object($term) ? $term->filter = $context : $term['filter'] = $context;
        return $term;
    }

    /** The field's own filters for the context, in the reference's order. */
    private static function filtered(string $field, mixed $value, int $termId, string|false $taxonomy, string $context): mixed
    {
        return match ($context) {
            'edit' => \apply_filters("edit_{$taxonomy}_{$field}", \apply_filters("edit_term_{$field}", $value, $termId, $taxonomy), $termId),
            'db' => self::saving($field, $value, $taxonomy),
            'rss' => \apply_filters("{$taxonomy}_{$field}_rss", \apply_filters("term_{$field}_rss", $value, $taxonomy)),
            default => \apply_filters("{$taxonomy}_{$field}", \apply_filters("term_{$field}", $value, $termId, $taxonomy, $context), $termId, $context),
        };
    }

    /** The db context's filters: the field's, the taxonomy's, and a slug's old category one. */
    private static function saving(string $field, mixed $value, string|false $taxonomy): mixed
    {
        $value = \apply_filters("pre_{$taxonomy}_{$field}", \apply_filters("pre_term_{$field}", $value, $taxonomy));
        return $field === 'slug' ? \apply_filters('pre_category_nicename', $value) : $value;
    }
}
