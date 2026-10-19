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
 * A theme's headers as the reference's WP_Theme shows them (probe
 * theme-headers): sanitized as read, in the theme's language, and with
 * markup.
 */
final class ThemeHeaders
{
    /** The tags a name, description or author may keep. */
    private const ALLOWED = ['a' => ['title' => true, 'href' => true], 'abbr' => ['title' => true], 'acronym' => ['title' => true], 'code' => [], 'em' => [], 'strong' => []];

    /**
     * A header's value as shown: the name, description and author through
     * kses, the addresses as URLs; then, unless asked not to, in the theme's
     * language (any header with a value, its text domain loaded first); then
     * with markup a list joined, the author linked to their address (shown
     * the same way), the addresses escaped and the description texturized.
     *
     * @param Closure(): mixed $loadDomain loads the theme's text domain, once for its theme object
     * @param Closure(string): mixed $plain another header of the theme, shown without markup
     */
    public static function shown(string $header, mixed $value, mixed $markup, mixed $translate, string $domain, Closure $loadDomain, Closure $plain): mixed
    {
        if (is_string($value)) {
            $value = match ($header) {
                'Name', 'Description', 'Author' => KsesRules::filter($value, self::ALLOWED),
                'ThemeURI', 'AuthorURI' => Escape::url($value, null, 'db'),
                default => $value,
            };
            if ($translate && $value !== '' && $domain !== '') {
                $loadDomain();
                $value = Gettext::text($value, $domain);
            }
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
     * A theme's text domain loaded from its folder, as load_theme_textdomain
     * loads it: the folder its Domain Path names, its languages folder when
     * it names none. False for a theme that names no domain.
     */
    private static function loadDomain(string $domain, string $folder, string $domainPath): bool
    {
        if ($domain === '') {
            return false;
        }
        $locale = Translations::locale();
        return Translations::fromFolder($domain, $folder . ($domainPath !== '' ? $domainPath : '/languages'), "{$domain}-{$locale}", $locale);
    }
}
