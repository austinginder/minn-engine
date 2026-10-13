<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;

/**
 * Whether the WordPress version Minn reports and the PHP it runs on meet a
 * requirement (a plugin's "Requires at least" and "Requires PHP"), and where
 * to read about updating PHP.
 */
final class VersionCheck
{
    /** Whether the reported WordPress version is at least the one required (is_wp_version_compatible); nothing required always is. */
    public static function wordpress(mixed $required): bool
    {
        return empty($required) || version_compare((string) $GLOBALS['wp_version'], (string) $required, '>=');
    }

    /** Whether PHP is at least the version required (is_php_version_compatible); nothing required always is. */
    public static function php(mixed $required): bool
    {
        return empty($required) || version_compare(PHP_VERSION, (string) $required, '>=');
    }

    /** The page about updating PHP (wp_get_update_php_url): the filter's, unless it gives no address. */
    public static function updatePhpUrl(): string
    {
        $url = Runtime::hooks()->filter('wp_update_php_url', [self::defaultUpdatePhpUrl()]);
        return is_string($url) && $url !== '' ? $url : self::defaultUpdatePhpUrl();
    }

    /** The page about updating PHP before any filter (wp_get_default_update_php_url), in the site's language. */
    public static function defaultUpdatePhpUrl(): string
    {
        return Gettext::inContext('https://wordpress.org/support/update-php/', 'localized PHP upgrade information page');
    }
}
