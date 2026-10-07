<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Runtime\Runtime;

/**
 * Rewrite endpoints plugins add (add_rewrite_endpoint: a shop's account
 * pages, an app's /json/), matched as the reference's endpoint rules match
 * them: the first registered endpoint name among the path's segments, what
 * follows it (slashes and all) its query var's value, '' when nothing
 * does; the address before it resolves as usual and counts only where the
 * endpoint was placed (EP_PAGES, EP_PERMALINK, EP_ROOT for the site's root,
 * and the archives' and attachments' own masks).
 */
final class Endpoints
{
    /** The reference's endpoint masks, by what they let an endpoint follow. */
    private const MASKS = ['permalink' => 1, 'attachment' => 2, 'date' => 4 | 8 | 16 | 32, 'root' => 64, 'search' => 256, 'categories' => 512, 'tags' => 1024, 'authors' => 2048, 'pages' => 4096];

    /**
     * The endpoint a path names: the address before it, its query var and
     * value, and where it may be placed; null for none.
     *
     * @return array{base: string, var: string, value: string, places: int}|null
     */
    public static function split(string $path): ?array
    {
        $endpoints = Runtime::booted() ? (array) ($GLOBALS['wp_rewrite']->endpoints ?? []) : [];
        $segments = array_values(array_filter(explode('/', trim(rawurldecode($path), '/')), static fn (string $s) => $s !== ''));
        foreach ($endpoints === [] ? [] : $segments as $at => $segment) {
            foreach ($endpoints as $endpoint) {
                [$places, $name, $var] = array_values((array) $endpoint) + [0, '', ''];
                if ($segment === $name && is_string($var) && $var !== '' && ($at > 0 || ((int) $places & self::MASKS['root']) !== 0)) {
                    $base = array_slice($segments, 0, $at);
                    return ['base' => $base === [] ? '/' : '/' . implode('/', $base) . '/', 'var' => $var, 'value' => implode('/', array_slice($segments, $at + 1)), 'places' => (int) $places];
                }
            }
        }
        return null;
    }

    /** Whether an endpoint placed so may follow the address a resolution stands for. */
    public static function allows(int $places, Resolution $resolution): bool
    {
        $mask = match ($resolution->kind) {
            Kind::Page => self::MASKS['pages'],
            Kind::Single => $resolution->record instanceof PostRecord && $resolution->record->type === 'attachment' ? self::MASKS['attachment'] : self::MASKS['permalink'],
            Kind::Home => self::MASKS['root'],
            Kind::Category => self::MASKS['categories'],
            Kind::Tag => self::MASKS['tags'],
            Kind::Author => self::MASKS['authors'],
            Kind::Date => self::MASKS['date'],
            Kind::Search => self::MASKS['search'],
            default => 0,
        };
        return ($places & $mask) !== 0;
    }
}
