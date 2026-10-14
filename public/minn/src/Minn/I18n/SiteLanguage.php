<?php

declare(strict_types=1);

namespace Minn\I18n;

use Minn\Html;
use Minn\Objects\SiteLocale;
use Minn\Runtime\Runtime;

/**
 * The site's language as pages declare it: the locale in force (a switched
 * one first, then what pre_determine_locale or the WPLANG option give,
 * through the locale filter) and the html element's dir and lang
 * attributes, which a plugin may change through language_attributes.
 */
final class SiteLanguage
{
    /** The locale in force (get_locale). */
    public static function locale(): string
    {
        $stack = Runtime::locales();
        if ($stack->switched()) {
            return (string) $stack->current();
        }
        $early = Runtime::hooks()->filter('pre_determine_locale', [null]);
        if (is_string($early) && $early !== '') {
            return $early;
        }
        $locale = (string) (Runtime::options()->get('WPLANG') ?? '');
        return (string) Runtime::hooks()->filter('locale', [$locale === '' ? 'en_US' : $locale]);
    }

    /** The language tag pages carry: the locale with a hyphen, plain "en" read as en_US. */
    public static function tag(): string
    {
        $locale = self::locale();
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
