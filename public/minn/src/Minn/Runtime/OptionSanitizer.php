<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Escape;

/**
 * A core option's value cleaned as the reference's sanitize_option cleans
 * it (probe sanitize-option), before sanitize_option_{$option}: counts as
 * whole numbers, the site's name and tagline escaped, formats and mail
 * settings stripped of markup, addresses checked and kept as they were
 * (with a settings error) when they are not addresses, word lists split
 * and trimmed, the timezone and language checked against what exists. Any
 * other option is left to its filter, where register_setting puts a
 * plugin's sanitize_callback.
 */
final class OptionSanitizer
{
    private const COUNTS = ['thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h', 'medium_large_size_w', 'medium_large_size_h', 'large_size_w', 'large_size_h', 'mailserver_port', 'comment_max_links', 'page_on_front', 'page_for_posts', 'rss_excerpt_length', 'default_category', 'default_email_category', 'default_link_category', 'close_comments_days_old', 'comments_per_page', 'thread_comments_depth', 'users_can_register', 'start_of_week', 'site_icon', 'fileupload_maxk'];

    private const STRIPPED = ['date_format', 'time_format', 'mailserver_url', 'mailserver_login', 'mailserver_pass', 'upload_path'];

    private const EMAIL = 'The email address entered did not appear to be a valid email address. Please enter a valid email address.';

    private const ERRORS = [
        'siteurl' => 'The WordPress address you entered did not appear to be a valid URL. Please enter a valid URL.',
        'home' => 'The Site address you entered did not appear to be a valid URL. Please enter a valid URL.',
        'timezone_string' => 'The timezone you have entered is not valid. Please select a valid timezone.',
        'permalink_structure' => 'A structure tag is required when using custom permalinks. <a href="https://wordpress.org/documentation/article/customize-permalinks/#choosing-your-permalink-structure">Learn more</a>',
    ];

    /** The value as sanitize_option leaves it, through sanitize_option_{$option}. */
    public static function clean(string $option, mixed $value): mixed
    {
        $original = $value;
        [$value, $error] = self::rule($option, $value);
        if ($error !== null) {
            $value = Runtime::options()->filtered($option);
            \add_settings_error($option, "invalid_{$option}", $error);
        }
        return \apply_filters("sanitize_option_{$option}", $value, $option, $original);
    }

    /**
     * The option's own rule: the cleaned value, and the error that refuses it (null when it stands).
     *
     * @return array{0: mixed, 1: string|null}
     */
    private static function rule(string $option, mixed $value): array
    {
        $text = is_scalar($value) ? (string) $value : '';
        return match (true) {
            in_array($option, ['admin_email', 'new_admin_email'], true) => self::email($text),
            in_array($option, self::COUNTS, true) => [abs((int) ($value)), null],
            in_array($option, ['posts_per_page', 'posts_per_rss'], true) => [self::perPage($value), null],
            in_array($option, ['default_ping_status', 'default_comment_status'], true) => [$text === '' || $text === '0' ? 'closed' : $value, null],
            in_array($option, ['blogdescription', 'blogname'], true) => [Escape::html($text), null],
            $option === 'blog_charset' => [(string) preg_replace('/[^a-zA-Z0-9_-]/', '', $text), null],
            $option === 'blog_public' => [$value === null ? 1 : (int) $value, null],
            in_array($option, self::STRIPPED, true) => [\wp_kses($text, []), null],
            $option === 'gmt_offset' => [is_numeric($value) ? $value : '', null],
            in_array($option, ['siteurl', 'home'], true) => preg_match('#http(s?)://(.+)#i', $text) ? [\sanitize_url($text), null] : [$value, self::ERRORS[$option]],
            $option === 'ping_sites' => [implode("\n", array_map('sanitize_url', array_filter(array_map('trim', explode("\n", $text))))), null],
            default => self::lists($option, $value),
        };
    }

    /** @return array{0: mixed, 1: string|null} */
    private static function lists(string $option, mixed $value): array
    {
        $text = is_scalar($value) ? (string) $value : '';
        return match (true) {
            $option === 'WPLANG' => [!in_array($value, self::languages(), true) && !empty($value) ? Runtime::options()->filtered($option) : $value, null],
            $option === 'illegal_names' => [self::words(is_array($value) ? $value : explode(' ', $text)), null],
            in_array($option, ['limited_email_domains', 'banned_email_domains'], true) => [self::domains(is_array($value) ? $value : explode("\n", $text)), null],
            $option === 'timezone_string' => !in_array($value, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true) && !empty($value) ? [$value, self::ERRORS[$option]] : [$value, null],
            in_array($option, ['permalink_structure', 'category_base', 'tag_base'], true) => self::structure($option, $text),
            $option === 'default_role' => [!\get_role($text) && \get_role('subscriber') ? 'subscriber' : $value, null],
            in_array($option, ['moderation_keys', 'disallowed_keys'], true) => [implode("\n", array_unique(array_filter(array_map('trim', explode("\n", $text))))), null],
            default => [$value, null],
        };
    }

    /** @return array{0: string, 1: string|null} */
    private static function email(string $value): array
    {
        $email = (string) \sanitize_email($value);
        return \is_email($email) ? [$email, null] : [$email, self::EMAIL];
    }

    /** A page size: a whole number, 1 when none, a negative one (but -1, all) made positive. */
    private static function perPage(mixed $value): int
    {
        $count = (int) $value;
        $count = $count === 0 ? 1 : $count;
        return $count < -1 ? abs($count) : $count;
    }

    /** @return array{0: string, 1: string|null} */
    private static function structure(string $option, string $value): array
    {
        $value = str_replace('http://', '', (string) \sanitize_url($value));
        $untagged = $option === 'permalink_structure' && $value !== '' && !preg_match('/%[^\/%]+%/', $value);
        return [$value, $untagged ? self::ERRORS[$option] : null];
    }

    /** @param array<int|string, mixed> $words @return list<string>|string */
    private static function words(array $words): array|string
    {
        $words = array_values(array_filter(array_map(static fn ($w): string => trim((string) $w), $words)));
        return $words === [] ? '' : $words;
    }

    /** @param array<int|string, mixed> $domains @return list<string>|string */
    private static function domains(array $domains): array|string
    {
        $valid = array_values(array_filter(array_map(static fn ($d): string => trim((string) $d), $domains), static fn (string $d): bool => $d !== '' && $d !== '0' && !preg_match('/(--|\.\.)/', $d) && (bool) preg_match('|^([a-zA-Z0-9-\.])+$|', $d)));
        return $valid === [] ? '' : $valid;
    }

    /** The languages the site may be set to: those installed, and the one wp-config names. @return list<string> */
    private static function languages(): array
    {
        $allowed = (array) \get_available_languages();
        if (defined('WPLANG') && WPLANG !== '' && WPLANG !== 'en_US') {
            $allowed[] = WPLANG;
        }
        return $allowed;
    }
}
