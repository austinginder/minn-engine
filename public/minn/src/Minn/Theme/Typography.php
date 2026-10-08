<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * Font sizes as the reference writes them (probe plugin-queue3): a size in
 * px, rem or em, and, with fluid typography on, a clamp() that grows
 * linearly from a minimum at a small viewport to the size (or the preset's
 * own maximum) at the wide one.
 */
final class Typography
{
    private const FLOOR = '14px';
    private const SMALL_VIEWPORT = '320px';
    private const WIDE_VIEWPORT = '1600px';

    /**
     * A CSS length split into its number and unit (px, rem or em, or the
     * units given), converted to another unit on request at a 16px root
     * (or the one given); a bare number is px. Null for anything else.
     *
     * @param array{coerce_to?: string, root_size_value?: int|float, acceptable_units?: list<string>} $options
     * @return array{value: float, unit: string}|null
     */
    public static function valueAndUnit(mixed $raw, array $options = []): ?array
    {
        if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) || empty($raw)) {
            return null;
        }
        $raw = is_numeric($raw) ? $raw . 'px' : $raw;
        $to = (string) ($options['coerce_to'] ?? '');
        $root = (float) ($options['root_size_value'] ?? 16);
        $units = implode('|', array_map(static fn ($unit): string => preg_quote((string) $unit, '/'), (array) ($options['acceptable_units'] ?? ['rem', 'px', 'em'])));
        if (!preg_match('/^(\d*\.?\d+)(' . $units . ')$/', (string) $raw, $m)) {
            return null;
        }
        [$value, $unit] = [(float) $m[1], $m[2]];
        $relative = static fn (string $u): bool => $u === 'em' || $u === 'rem';
        if ($to === 'px' && $relative($unit)) {
            [$value, $unit] = [$value * $root, 'px'];
        } elseif ($unit === 'px' && $relative($to)) {
            [$value, $unit] = [$value / $root, $to];
        } elseif ($relative($to) && $relative($unit)) {
            $unit = $to;
        }
        return ['value' => round($value, 3), 'unit' => $unit];
    }

    /**
     * A font size preset as CSS (wp_get_typography_font_size_value). It
     * stays as written unless fluid typography is on and the size is in a
     * known unit and above the floor (14px, or the settings' minFontSize);
     * then it grows from a minimum (the preset's own, else the size scaled
     * by 1 - 0.075 log2 of its px, held between 0.25 and 0.75, never under
     * the floor) to a maximum (the preset's own, else the size).
     *
     * @param array<string, mixed> $preset
     * @param array<string, mixed> $settings typography and layout, merged over the global settings
     */
    public static function fontSize(array $preset, array $settings): mixed
    {
        if (!isset($preset['size'])) {
            return null;
        }
        $size = $preset['size'];
        $fluid = $settings['typography']['fluid'] ?? false;
        $preferred = self::valueAndUnit($size);
        if (empty($size) || empty($fluid) || ($preset['fluid'] ?? null) === false || $preferred === null) {
            return $size;
        }
        $options = is_array($fluid) ? $fluid : [];
        $floor = self::valueAndUnit(self::valueAndUnit($options['minFontSize'] ?? null) !== null ? $options['minFontSize'] : self::FLOOR, ['coerce_to' => $preferred['unit']]);
        [$min, $max] = [$preset['fluid']['min'] ?? null, $preset['fluid']['max'] ?? null];
        if ($floor !== null && !$min && !$max && $preferred['value'] <= $floor['value']) {
            return $size;
        }
        $max = $max ?: self::number($preferred['value']) . $preferred['unit'];
        if (!$min) {
            $px = $preferred['unit'] === 'px' ? $preferred['value'] : $preferred['value'] * 16;
            $scaled = round($preferred['value'] * min(max(1 - 0.075 * log($px, 2), 0.25), 0.75), 3);
            $min = $floor !== null && $scaled <= $floor['value'] ? self::number($floor['value']) . $floor['unit'] : self::number($scaled) . $preferred['unit'];
        }
        $wide = $settings['layout']['wideSize'] ?? null;
        $widest = $options['maxViewportWidth'] ?? (self::valueAndUnit($wide) !== null ? $wide : self::WIDE_VIEWPORT);
        return self::clamp((string) ($options['minViewportWidth'] ?? self::SMALL_VIEWPORT), (string) $widest, (string) $min, (string) $max, 1) ?? $size;
    }

    /**
     * The clamp() between two sizes over two viewport widths
     * (wp_get_computed_fluid_typography_value): the slope in the minimum's
     * unit, the base in rem. Null when a size or a width is missing or in an
     * unknown unit, or the widths are equal.
     */
    public static function clamp(?string $minViewport, ?string $maxViewport, ?string $minSize, ?string $maxSize, float|int|null $scale): ?string
    {
        $min = self::valueAndUnit($minSize);
        $unit = $min['unit'] ?? 'rem';
        $max = self::valueAndUnit($maxSize, ['coerce_to' => $unit]);
        $narrow = self::valueAndUnit($minViewport, ['coerce_to' => $unit]);
        $wide = self::valueAndUnit($maxViewport, ['coerce_to' => $unit]);
        if ($min === null || $max === null || $narrow === null || $wide === null || $wide['value'] === $narrow['value']) {
            return null;
        }
        $base = (array) self::valueAndUnit($minSize, ['coerce_to' => 'rem']);
        $slope = round(100 * (($max['value'] - $min['value']) / ($wide['value'] - $narrow['value'])) * (float) $scale, 3) ?: 1;
        $offset = self::number(round($narrow['value'] / 100, 3)) . $unit;
        return "clamp({$minSize}, " . self::number($base['value']) . $base['unit'] . " + ((1vw - {$offset}) * " . self::number($slope) . "), {$maxSize})";
    }

    /** A number as PHP writes a float: no trailing ".0". */
    private static function number(float|int $value): string
    {
        return (string) $value;
    }
}
