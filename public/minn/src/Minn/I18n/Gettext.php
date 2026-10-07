<?php

declare(strict_types=1);

namespace Minn\I18n;

use Closure;
use Minn\Runtime\Runtime;

/**
 * Translation as plugins see it (__, _x, _n and the rest map here): the
 * message looked up in its domain's loaded files (loading the domain just
 * in time on first use, through the loader the runtime hands over), else
 * the English, then the gettext filters a plugin may change it through.
 * Engine code translates here rather than through the WordPress names;
 * with no runtime (an engine CLI verb) there is nothing loaded, and the
 * English stands.
 */
final class Gettext
{
    /** @var (Closure(string): mixed)|null loads a domain on its first use */
    private static ?Closure $loader = null;

    /** The runtime's just-in-time loader for a domain's files. @param Closure(string): mixed $loader */
    public static function loadWith(Closure $loader): void
    {
        self::$loader = $loader;
    }

    /** A message (__). */
    public static function text(string $text, string $domain = 'default'): string
    {
        $translation = self::lookup($domain, Catalog::key($text, null)) ?? $text;
        $translation = \apply_filters('gettext', $translation, $text, $domain);
        return (string) \apply_filters("gettext_{$domain}", $translation, $text, $domain);
    }

    /** A message in a context (_x). */
    public static function inContext(string $text, string $context, string $domain = 'default'): string
    {
        $translation = self::lookup($domain, Catalog::key($text, $context)) ?? $text;
        $translation = \apply_filters('gettext_with_context', $translation, $text, $context, $domain);
        return (string) \apply_filters("gettext_with_context_{$domain}", $translation, $text, $context, $domain);
    }

    /** The form a count takes (_n): the domain's own plural, else English's. */
    public static function plural(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        $translation = self::pluralForm($single, $plural, $number, $domain, null);
        $translation = \apply_filters('ngettext', $translation, $single, $plural, $number, $domain);
        return (string) \apply_filters("ngettext_{$domain}", $translation, $single, $plural, $number, $domain);
    }

    /** The form a count takes in a context (_nx). */
    public static function pluralInContext(string $single, string $plural, int $number, string $context, string $domain = 'default'): string
    {
        $translation = self::pluralForm($single, $plural, $number, $domain, $context);
        $translation = \apply_filters('ngettext_with_context', $translation, $single, $plural, $number, $context, $domain);
        return (string) \apply_filters("ngettext_with_context_{$domain}", $translation, $single, $plural, $number, $context, $domain);
    }

    /** A domain's translation of a message key, or null (no runtime to have loaded one, no file, no entry). */
    public static function lookup(string $domain, string $key): ?string
    {
        if (self::$loader === null) {
            return null;
        }
        (self::$loader)($domain);
        return Runtime::textDomains()->translate($domain, $key);
    }

    /** The plural form a count takes in a domain, or English's when no file has the message. */
    public static function pluralForm(string $single, string $plural, int $number, string $domain, ?string $context): string
    {
        if (self::$loader !== null) {
            (self::$loader)($domain);
            $form = Runtime::textDomains()->translatePlural($domain, Catalog::key($single, $context), $number);
            if ($form !== null) {
                return $form;
            }
        }
        return $number === 1 ? $single : $plural;
    }
}
