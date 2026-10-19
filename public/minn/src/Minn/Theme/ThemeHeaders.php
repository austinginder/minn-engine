<?php

declare(strict_types=1);

namespace Minn\Theme;

use Closure;
use Minn\Content\Texturize;
use Minn\I18n\Gettext;
use Minn\Runtime\KsesRules;
use Minn\Runtime\Translations;
use Minn\Support\Escape;

/**
 * A theme's headers as the reference's WP_Theme reads and shows them
 * (probe theme-headers): sanitized as read, in the theme's language, and
 * with markup.
 */
final class ThemeHeaders
{
    /** The tags a name or a status may keep. */
    private const PLAIN = ['abbr' => ['title' => true], 'acronym' => ['title' => true], 'code' => [], 'em' => [], 'strong' => []];
    /** The tags a description or an author may keep: those, and links. */
    private const ALLOWED = ['a' => ['title' => true, 'href' => true]] + self::PLAIN;

    /** @var \WeakMap<object, array<string, mixed>>|null each theme object's headers as read */
    private static ?\WeakMap $read = null;

    /**
     * A header as WP_Theme::get reads it, once for its theme object (probe
     * theme-headers): Tags as a list; a value sanitized (the name and status
     * through kses, the description and author too with links, the addresses
     * as URLs, the version and requirements, and each tag, without tags; the
     * rest as written); Status "publish" when an existing theme leaves it
     * out.
     */
    public static function read(object $theme, string $header, mixed $raw): mixed
    {
        self::$read ??= new \WeakMap();
        $known = self::$read[$theme] ?? [];
        if (!array_key_exists($header, $known)) {
            $value = $header === 'Tags' && is_string($raw) ? ($raw === '' ? [] : array_map('trim', explode(',', $raw))) : $raw;
            $value = in_array($value, ['', false, []], true) ? $value : self::sanitized($header, $value);
            $known[$header] = $header === 'Status' && $value === '' && $theme->exists() ? 'publish' : $value;
            self::$read[$theme] = $known;
        }
        return $known[$header];
    }

    private static function sanitized(string $header, mixed $value): mixed
    {
        return match ($header) {
            'Name', 'Status' => KsesRules::filter($value, self::PLAIN),
            'Description', 'Author' => KsesRules::filter($value, self::ALLOWED),
            'ThemeURI', 'AuthorURI' => Escape::url($value, null, 'db'),
            'Version', 'RequiresWP', 'RequiresPHP' => strip_tags((string) $value),
            'Tags' => array_map(static fn (mixed $tag): string => strip_tags((string) $tag), (array) $value),
            default => $value,
        };
    }

    /**
     * A header's value as shown, sanitized as read (read()): unless asked
     * not to, in the theme's language (any header with a value, its text
     * domain loaded first); then with markup a list joined, the author linked
     * to their address (shown the same way), the addresses escaped and the
     * description texturized.
     *
     * @param Closure(): mixed $loadDomain loads the theme's text domain, once for its theme object
     * @param Closure(string): mixed $plain another header of the theme, shown without markup
     */
    public static function shown(string $header, mixed $value, mixed $markup, mixed $translate, string $domain, Closure $loadDomain, Closure $plain): mixed
    {
        if (is_string($value) && $translate && $value !== '' && $domain !== '') {
            $loadDomain();
            $value = Gettext::text($value, $domain);
        }
        if (!$markup || !is_string($value) && !is_array($value)) {
            return $value;
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        $authorUri = $header === 'Author' && $value !== '' ? (string) $plain('AuthorURI') : '';
        return match (true) {
            $authorUri !== '' => sprintf('<a href="%1$s">%2$s</a>', Escape::url($authorUri), $value),
            $header === 'AuthorURI', $header === 'ThemeURI' => Escape::url($value),
            $header === 'Description' => Texturize::apply($value),
            default => $value,
        };
    }

    /** @var \WeakMap<object, bool>|null what each theme object's text domain load answered */
    private static ?\WeakMap $loaded = null;

    /** A theme object's text domain (WP_Theme::load_textdomain), loaded once for it. */
    public static function loadFor(object $theme): bool
    {
        self::$loaded ??= new \WeakMap();
        return self::$loaded[$theme] ??= self::loadDomain((string) $theme->get('TextDomain'), (string) $theme->get_stylesheet_directory(), (string) $theme->get('DomainPath'));
    }

    /**
     * A theme's text domain named to load from its folder, as
     * load_theme_textdomain names it: the folder its Domain Path names, its
     * languages folder when it names none. False for a theme that names no
     * domain.
     */
    private static function loadDomain(string $domain, string $folder, string $domainPath): bool
    {
        return $domain !== '' && Translations::fromFolder($domain, $folder . ($domainPath !== '' ? $domainPath : '/languages'));
    }
}
