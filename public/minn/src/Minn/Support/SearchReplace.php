<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * String replace that walks serialized-PHP arrays of scalars without
 * unserialize, so a domain change in an option blob keeps its lengths.
 */
final class SearchReplace
{
    /**
     * A value with one string replaced, and how many times.
     *
     * @return array{0: string, 1: int} replacement and how many times $old occurred
     */
    public static function in(string $value, string $old, string $new): array
    {
        if ($old === '' || !str_contains($value, $old)) {
            return [$value, 0];
        }
        $decoded = Serialized::decode($value);
        if ($decoded !== Serialized::INVALID) {
            $count = 0;
            $walked = self::walk($decoded, $old, $new, $count);
            return [Serialized::encode($walked), $count];
        }
        $count = substr_count($value, $old);
        return [str_replace($old, $new, $value), $count];
    }

    private static function walk(mixed $value, string $old, string $new, int &$count): mixed
    {
        if (is_string($value)) {
            $count += substr_count($value, $old);
            return str_replace($old, $new, $value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::walk($item, $old, $new, $count);
            }
            return $out;
        }
        return $value;
    }
}
