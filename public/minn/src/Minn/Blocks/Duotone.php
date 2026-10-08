<?php

declare(strict_types=1);

namespace Minn\Blocks;

/**
 * Duotone filters (probe block-supports): a CSS color read into its red,
 * green, blue (0 to 255) and alpha (0 to 1, to two places) channels, and
 * the hidden SVG filter that maps an image's shades onto a list of colors,
 * one table of values per channel.
 */
final class Duotone
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 0 0" width="0" height="0" focusable="false" role="none" style="visibility: hidden; position: absolute; left: -9999px; overflow: hidden;" ><defs><filter id="%s"><feColorMatrix color-interpolation-filters="sRGB" type="matrix" values=" .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 .299 .587 .114 0 0 " /><feComponentTransfer color-interpolation-filters="sRGB" ><feFuncR type="table" tableValues="%s" /><feFuncG type="table" tableValues="%s" /><feFuncB type="table" tableValues="%s" /><feFuncA type="table" tableValues="%s" /></feComponentTransfer><feComposite in2="SourceGraphic" operator="in" /></filter></defs></svg>';

    /**
     * The filter for a list of colors; colors that do not parse are left out.
     *
     * @param list<string> $colors
     */
    public static function svg(string $filterId, array $colors): string
    {
        $tables = ['r' => [], 'g' => [], 'b' => [], 'a' => []];
        foreach ($colors as $color) {
            $rgba = self::parse((string) $color);
            if ($rgba === null) {
                continue;
            }
            foreach (['r', 'g', 'b'] as $channel) {
                $tables[$channel][] = (string) ($rgba[$channel] / 255);
            }
            $tables['a'][] = (string) $rgba['a'];
        }
        return sprintf(self::SVG, $filterId, implode(' ', $tables['r']), implode(' ', $tables['g']), implode(' ', $tables['b']), implode(' ', $tables['a']));
    }

    /**
     * A color's channels: hex (3, 4, 6 or 8 digits), rgb()/rgba() and hsl()/hsla().
     *
     * @return array{r: float, g: float, b: float, a: float}|null
     */
    public static function parse(string $input): ?array
    {
        $input = strtolower(trim($input));
        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $input, $m) === 1) {
            $hex = strlen($m[1]) <= 4 ? implode('', array_map(static fn (string $c) => $c . $c, str_split($m[1]))) : $m[1];
            $alpha = strlen($hex) === 8 ? round(hexdec(substr($hex, 6, 2)) / 255 * 100) / 100 : 1;
            return ['r' => (float) hexdec(substr($hex, 0, 2)), 'g' => (float) hexdec(substr($hex, 2, 2)), 'b' => (float) hexdec(substr($hex, 4, 2)), 'a' => (float) $alpha];
        }
        if (preg_match('/^rgba?\(\s*([\d.]+)(%?)[\s,]+([\d.]+)(%?)[\s,]+([\d.]+)(%?)\s*(?:[,\/]\s*([\d.]+)(%?)\s*)?\)$/', $input, $m) === 1) {
            $channel = static fn (string $v, string $pct): float => max(0.0, min(255.0, $pct === '%' ? (float) $v * 2.55 : (float) $v));
            return ['r' => $channel($m[1], $m[2]), 'g' => $channel($m[3], $m[4]), 'b' => $channel($m[5], $m[6]), 'a' => self::alpha($m[7] ?? null, $m[8] ?? '')];
        }
        if (preg_match('/^hsla?\(\s*([\d.-]+)(deg|rad|grad|turn)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%\s*(?:[,\/]\s*([\d.]+)(%?)\s*)?\)$/', $input, $m) === 1) {
            return self::hsl((float) $m[1], (float) $m[3], (float) $m[4]) + ['a' => self::alpha($m[5] ?? null, $m[6] ?? '')];
        }
        return null;
    }

    private static function alpha(?string $value, string $pct): float
    {
        if ($value === null || $value === '') {
            return 1.0;
        }
        return max(0.0, min(1.0, $pct === '%' ? (float) $value / 100 : (float) $value));
    }

    /** @return array{r: float, g: float, b: float} hue in degrees, saturation and lightness in percent */
    private static function hsl(float $hue, float $saturation, float $lightness): array
    {
        $hue = fmod(fmod($hue, 360) + 360, 360) / 360;
        $s = max(0, min(100, $saturation)) / 100;
        $l = max(0, min(100, $lightness)) / 100;
        if ($s == 0) {
            return ['r' => $l * 255, 'g' => $l * 255, 'b' => $l * 255];
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $channel = static function (float $t) use ($p, $q): float {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);
            return 255 * match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };
        return ['r' => round($channel($hue + 1 / 3)), 'g' => round($channel($hue)), 'b' => round($channel($hue - 1 / 3))];
    }
}
