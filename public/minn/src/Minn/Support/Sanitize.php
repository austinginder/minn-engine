<?php

declare(strict_types=1);

namespace Minn\Support;

use Minn\Html;
use Minn\I18n\SiteLanguage;
use Minn\Runtime\Runtime;

/**
 * Text made fit to store the way WordPress's sanitizers make it, each ending
 * in the filter plugins hook there, handed the result and what was given: a
 * title (accents gone when saving), a key, a line of plain text, a user name,
 * an email address and the check of one.
 */
final class Sanitize
{
    /** A title as a slug's source (sanitize_title): accents gone when saving; the fallback when the filter leaves nothing. */
    public static function title(mixed $title, mixed $fallback = '', mixed $context = 'save'): string
    {
        $given = (string) $title;
        $clean = (string) Runtime::hooks()->filter('sanitize_title', [$context === 'save' ? Accents::strip($given, SiteLanguage::locale()) : $given, $given, $context]);
        return $clean === '' && $fallback !== '' ? (string) $fallback : $clean;
    }

    /** A key (sanitize_key): lower case letters, digits, underscores and dashes only. */
    public static function key(mixed $key): string
    {
        return (string) Runtime::hooks()->filter('sanitize_key', [(string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)), $key]);
    }

    /** One line of plain text (sanitize_text_field): tags, line breaks, extra spaces and stray octets gone; anything but text is empty. */
    public static function textField(mixed $text): string
    {
        $clean = is_object($text) || is_array($text) ? '' : Html::textField(
            (string) $text,
            false,
            static fn (string $t): string => Escape::validUtf8($t),
            static fn (string $t): string => Kses::lessThan($t, static fn (string $plain): string => Escape::html($plain)),
        );
        return (string) Runtime::hooks()->filter('sanitize_text_field', [$clean, $text]);
    }

    /**
     * A user name (sanitize_user): tags, accents, octets and references gone,
     * spaces collapsed; strictly, only letters, digits, spaces and _ . - @.
     */
    public static function user(mixed $username, mixed $strict = false): string
    {
        $given = (string) $username;
        $name = Accents::strip(Html::stripAllTags($given), SiteLanguage::locale());
        $name = (string) preg_replace(['|%([a-fA-F0-9][a-fA-F0-9])|', '/&.+?;/'], '', $name);
        if ($strict) {
            $name = (string) preg_replace('|[^a-z0-9 _.\-@]|i', '', $name);
        }
        return (string) Runtime::hooks()->filter('sanitize_user', [trim((string) preg_replace('|\s+|', ' ', $name)), $given, $strict]);
    }

    /**
     * A class name for HTML (sanitize_html_class): escaped octets and
     * anything but letters, digits, _ and - taken out; the fallback's when
     * nothing is left; then the filter.
     */
    public static function htmlClass(mixed $classname, mixed $fallback = ''): mixed
    {
        $sanitized = (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', (string) $classname));
        if ($sanitized === '' && $fallback !== '') {
            return self::htmlClass($fallback);
        }
        return Runtime::hooks()->filter('sanitize_html_class', [$sanitized, $classname, $fallback]);
    }

    /** An email address with what may not be in one taken out (sanitize_email), and why when nothing is left. */
    public static function email(mixed $email): string
    {
        [$clean, $reason] = Email::sanitize((string) $email);
        return (string) Runtime::hooks()->filter('sanitize_email', [$clean, (string) $email, $reason]);
    }

    /** The address when it reads as one, else false (is_email), as the filter leaves it. */
    public static function isEmail(mixed $email): mixed
    {
        $reason = Email::check((string) $email);
        return Runtime::hooks()->filter('is_email', [$reason === null ? (string) $email : false, (string) $email, $reason]);
    }
}
