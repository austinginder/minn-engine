<?php

declare(strict_types=1);

namespace Minn\I18n;

use Minn\Html;
use Minn\Objects\SiteLocale;
use Minn\Runtime\Runtime;
use Minn\Runtime\Translations;

/**
 * The site's language as pages declare it: the locale in force (a switched
 * one first, then what the WPLANG option gives, through the locale filter) and the html element's dir and lang
 * attributes, which a plugin may change through language_attributes.
 */
final class SiteLanguage
{
    /**
     * The locale in force (get_locale): a switched one, else the configured
     * one. pre_determine_locale is determine_locale's, not this one's (probe
     * text-domain-timing); the reference asks it while registering the media
     * player's strings, as the engine now does (probe default-scripts).
     */
    public static function locale(): string
    {
        $stack = Runtime::locales();
        return $stack->switched() ? (string) $stack->current() : self::configured();
    }

    /** The locale the WPLANG option names (en_US when it names none), through the locale filter. */
    public static function configured(): string
    {
        $locale = (string) (Runtime::options()->get('WPLANG') ?? '');
        return (string) Runtime::hooks()->filter('locale', [$locale === '' ? 'en_US' : $locale]);
    }

    /** The language tag pages carry: the locale in force (determine_locale; probe sweep-1020) with a hyphen, plain "en" read as en_US. */
    public static function tag(): string
    {
        $locale = Translations::locale();
        return str_replace('_', '-', $locale === 'en' ? 'en_US' : $locale);
    }

    /** Whether the site's language is written right to left (is_rtl). */
    public static function isRtl(): bool
    {
        $locale = $GLOBALS['wp_locale'] ?? null;
        return $locale instanceof SiteLocale && $locale->is_rtl();
    }

    /** The html element's dir and lang attributes (lang for html, xml:lang for xhtml or a non-HTML html_type). */
    public static function attributes(string $doctype = 'html'): string
    {
        $attributes = [];
        if (self::isRtl()) {
            $attributes[] = 'dir="rtl"';
        }
        $tag = self::tag();
        $type = Runtime::options()->filtered('html_type');
        if ($type === 'text/html' || $doctype === 'html') {
            $attributes[] = 'lang="' . Html::attr($tag) . '"';
        }
        if ($type !== 'text/html' || $doctype === 'xhtml') {
            $attributes[] = 'xml:lang="' . Html::attr($tag) . '"';
        }
        return (string) Runtime::hooks()->filter('language_attributes', [implode(' ', $attributes), $doctype]);
    }
}
