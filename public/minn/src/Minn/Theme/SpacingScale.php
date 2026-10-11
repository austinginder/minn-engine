<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The spacing sizes a theme.json spacing scale makes (probe
 * theme-json-css): Medium (slug 50) at the medium step; below it, half the
 * other steps rounded down, each divided by (or less) the increment, named
 * Small, X-Small, 2X-Small; above it the rest, multiplied by (or plus) it,
 * named Large, X-Large, 2X-Large; slugs ten apart, values rounded to two
 * places. An invalid scale makes none.
 */
final class SpacingScale
{
    /**
     * The sizes the scale makes, smallest first.
     *
     * @param array<string, mixed> $scale operator, increment, steps, mediumStep, unit
     * @return list<array{name: string, slug: string, size: string}>
     */
    public static function sizes(array $scale): array
    {
        foreach (['increment', 'steps', 'mediumStep'] as $key) {
            if (!is_numeric($scale[$key] ?? null)) {
                return [];
            }
        }
        $operator = $scale['operator'] ?? null;
        if (!in_array($operator, ['+', '*'], true) || !is_string($scale['unit'] ?? null) || (int) $scale['steps'] < 1) {
            return [];
        }
        [$increment, $medium, $unit, $steps] = [(float) $scale['increment'], (float) $scale['mediumStep'], $scale['unit'], (int) $scale['steps']];
        $step = static fn (int $k): float => $operator === '+' ? $medium + $k * $increment : $medium * $increment ** $k;
        $below = intdiv($steps - 1, 2);
        $sizes = [];
        for ($k = $below; $k >= 1; $k--) {
            $sizes[] = ['name' => self::name($k, 'Small'), 'slug' => (string) (50 - 10 * $k), 'size' => self::format($step(-$k), $unit)];
        }
        $sizes[] = ['name' => 'Medium', 'slug' => '50', 'size' => self::format($medium, $unit)];
        for ($k = 1; $k <= $steps - 1 - $below; $k++) {
            $sizes[] = ['name' => self::name($k, 'Large'), 'slug' => (string) (50 + 10 * $k), 'size' => self::format($step($k), $unit)];
        }
        return $sizes;
    }

    /** Small, X-Small, 2X-Small, and so on (Large likewise). */
    private static function name(int $distance, string $side): string
    {
        return match (true) {
            $distance === 1 => $side,
            $distance === 2 => "X-{$side}",
            default => ($distance - 1) . "X-{$side}",
        };
    }

    private static function format(float $value, string $unit): string
    {
        return rtrim(rtrim(number_format(round($value, 2), 2, '.', ''), '0'), '.') . $unit;
    }
}
