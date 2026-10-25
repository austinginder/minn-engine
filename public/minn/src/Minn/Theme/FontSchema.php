<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The font library's tree sanitizer (WP_Font_Utils::sanitize_from_schema),
 * as the reference answers it (probed 2026-10-24): only the keys the schema
 * names are kept; a key whose rule is a callable gets the callable's answer
 * for whatever its value is, a list included (a text sanitizer makes a list
 * empty); a rule that is an array reads an object as a schema of its own,
 * and a list as a list of that rule's first entry; anything an object level
 * comes out with that is empty ('', '0', 0, false, null, []) is left out,
 * while a list keeps every item. A rule that is not callable leaves the value
 * as it is (the reference stops on a TypeError there).
 */
final class FontSchema
{
    /**
     * The tree with only the keys the schema names, each through its rule.
     *
     * @param mixed $tree
     * @param mixed $schema
     * @return array<array-key, mixed>
     */
    public static function sanitize(mixed $tree, mixed $schema): array
    {
        if (!is_array($tree) || !is_array($schema)) {
            return [];
        }
        $out = [];
        foreach ($tree as $key => $value) {
            if (!array_key_exists($key, $schema)) {
                continue;
            }
            $value = self::value($value, $schema[$key]);
            if (!empty($value)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private static function value(mixed $value, mixed $rule): mixed
    {
        if (!is_array($rule)) {
            return is_callable($rule) ? $rule($value) : $value;
        }
        if (is_array($value) && array_is_list($value) && isset($rule[0])) {
            return array_map(static fn (mixed $item): mixed => self::value($item, $rule[0]), $value);
        }
        return self::sanitize($value, $rule);
    }
}
