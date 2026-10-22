<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Support\Sanitize;

/**
 * The font library's names (probes fonts and font-writes): a font-family
 * list as a family stores it, the identity a face is stored under (its
 * title, which the duplicate check compares), and the font types an
 * upload may carry.
 */
final class FontNames
{
    /** The font types an upload may carry, by extension. */
    public const MIME_TYPES = ['otf' => 'application/vnd.ms-opentype', 'ttf' => 'font/sfnt', 'woff' => 'font/woff', 'woff2' => 'font/woff2'];

    /**
     * Stretch keywords as percentages, replaced one after another in this
     * order, as the reference replaces them: a keyword that holds one
     * replaced before it keeps the rest ("semi-condensed" is "semi-75%",
     * "ultra-expanded" is "ultra-125%").
     */
    private const STRETCH = ['normal' => '100%', 'ultra-condensed' => '50%', 'extra-condensed' => '62.5%', 'condensed' => '75%', 'semi-condensed' => '87.5%', 'semi-expanded' => '112.5%', 'expanded' => '125%', 'extra-expanded' => '150%', 'ultra-expanded' => '200%'];

    /**
     * A font-family list as text, each name trimmed of spaces and quotes,
     * then joined by ", ": a name of letters and hyphens alone as it is,
     * any other (a space, a digit, an underscore) double-quoted.
     */
    public static function family(mixed $fontFamily): string
    {
        $fontFamily = Sanitize::textField((string) $fontFamily);
        if ($fontFamily === '') {
            return '';
        }
        $names = array_filter(array_map(static fn (string $name): string => trim($name, " \t\n\r\0\x0B\"'"), explode(',', $fontFamily)), static fn (string $name): bool => $name !== '');
        return implode(', ', array_map(self::quoted(...), $names));
    }

    /** One name of a font-family list, double-quoted unless it is letters and hyphens alone. */
    public static function quoted(mixed $name): string
    {
        $name = trim((string) $name, " \t\n\r\0\x0B\"'");
        return preg_match('/^[a-zA-Z-]+$/', $name) === 1 ? $name : '"' . $name . '"';
    }

    /**
     * A face's identity: its family list (trimmed, lower case, split at
     * each comma and the spaces after it, each name's quotes trimmed),
     * style (lower case), weight (lower case, normal and bold as numbers),
     * stretch (lower case, keywords as percentages) and unicode range
     * (upper case), joined by ";". A property left out takes its default
     * (normal, 400, 100%, U+0-10FFFF); one given empty stays empty.
     */
    public static function faceSlug(mixed $settings): string
    {
        $settings = (array) $settings + ['fontFamily' => '', 'fontStyle' => 'normal', 'fontWeight' => '400', 'fontStretch' => '100%', 'unicodeRange' => 'U+0-10FFFF'];
        $families = array_map(static fn (string $name): string => trim($name, '"\''), (array) preg_split('/,\s*/', strtolower(trim((string) $settings['fontFamily']))));
        return implode(';', [
            implode(',', $families),
            strtolower((string) $settings['fontStyle']),
            str_replace(['normal', 'bold'], ['400', '700'], strtolower((string) $settings['fontWeight'])),
            str_replace(array_keys(self::STRETCH), array_values(self::STRETCH), strtolower((string) $settings['fontStretch'])),
            strtoupper((string) $settings['unicodeRange']),
        ]);
    }
}
