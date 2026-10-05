<?php

declare(strict_types=1);

namespace Minn\I18n;

/**
 * Where a script's JSON translations are found, and how they reach the
 * page. A script is placed by its URL: inside a plugin or a theme, its path
 * there; elsewhere on the site, its path from the site root; off the site,
 * nowhere. The JSON files are named by the md5 of that path (with ".min.js"
 * read as ".js"), or by the script's handle in a folder a plugin names.
 */
final class ScriptTranslations
{
    /**
     * A script's path inside its plugin, theme or site, and the languages
     * subfolder for it ("plugins", "themes", or "" for the site); the path
     * is false for a script off the site.
     *
     * @return array{0: string|false, 1: string}
     */
    public static function place(string $src, string $siteUrl, string $pluginsUrl, string $themesUrl): array
    {
        if (str_starts_with($src, '/') && !str_starts_with($src, '//')) {
            $src = self::origin($siteUrl) . $src;
        }
        $url = explode('?', $src, 2)[0];
        foreach (['plugins' => $pluginsUrl, 'themes' => $themesUrl] as $folder => $base) {
            $base = rtrim($base, '/') . '/';
            if ($base !== '/' && str_starts_with($url, $base)) {
                $inside = explode('/', substr($url, strlen($base)), 2);
                return [$inside[1] ?? '', $folder];
            }
        }
        $site = rtrim($siteUrl, '/') . '/';
        return str_starts_with($url, $site) ? [substr($url, strlen($site)), ''] : [false, ''];
    }

    /** The script made absolute the way place() reads it, for the filter that may move it. */
    public static function absolute(string $src, string $siteUrl): string
    {
        return str_starts_with($src, '/') && !str_starts_with($src, '//') ? self::origin($siteUrl) . $src : $src;
    }

    /** The md5 a translation file is named by: a minified script shares its source's file. */
    public static function fileHash(string $relative): string
    {
        return md5((string) preg_replace('/\.min\.js$/', '.js', $relative));
    }

    /** The start of a translation file's name: the core domain goes by locale alone. */
    public static function prefix(string $domain, string $locale): string
    {
        return $domain === 'default' ? $locale : "{$domain}-{$locale}";
    }

    /** The inline script that hands a script's translations to wp.i18n before the script runs. */
    public static function block(string $domain, string $json): string
    {
        return "( function( domain, translations ) {\n"
            . "\tvar localeData = translations.locale_data[ domain ] || translations.locale_data.messages;\n"
            . "\tlocaleData[\"\"].domain = domain;\n"
            . "\twp.i18n.setLocaleData( localeData, domain );\n"
            . '} )( ' . json_encode($domain, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ', ' . $json . ' );';
    }

    private static function origin(string $siteUrl): string
    {
        $parts = parse_url($siteUrl);
        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
