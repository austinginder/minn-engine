<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The value rules an allowlist attribute may carry, as the reference judges
 * them: lengths count bytes, bounds need a whole number (up to six digits,
 * up to six spaces either side), "valueless" compares the attribute's form,
 * "values" compares without case, and a callback decides for itself. A rule
 * the reference does not know passes. Where the reference fatals (a values
 * rule that is not a list, a callback that does not exist) the value fails.
 */
final class KsesValues
{
    /**
     * Whether a value meets every rule its attribute carries; "required" is
     * the tag's concern, so it is skipped here.
     *
     * @param array<array-key, mixed> $rules
     */
    public static function satisfies(string $value, string $valueless, array $rules): bool
    {
        foreach ($rules as $rule => $expected) {
            if (strtolower((string) $rule) !== 'required' && !self::check($value, $valueless, (string) $rule, $expected)) {
                return false;
            }
        }
        return true;
    }

    /** One rule against one value; $valueless is "y" for a bare attribute and "n" for one with a value. */
    public static function check(string $value, string $valueless, string $rule, mixed $expected): bool
    {
        return match (strtolower($rule)) {
            'maxlen' => strlen($value) <= (int) $expected,
            'minlen' => strlen($value) >= (int) $expected,
            'maxval' => self::wholeNumber($value) && (int) $value <= (int) $expected,
            'minval' => self::wholeNumber($value) && (int) $value >= (int) $expected,
            'valueless' => strtolower($valueless) === strtolower((string) $expected),
            'values' => is_array($expected) && in_array(strtolower($value), array_map(static fn (mixed $allowed): string => strtolower((string) $allowed), $expected), true),
            'value_callback' => is_callable($expected) && (bool) $expected($value),
            default => true,
        };
    }

    private static function wholeNumber(string $value): bool
    {
        return preg_match('/^\s{0,6}[0-9]{1,6}\s{0,6}$/', $value) === 1;
    }
}
