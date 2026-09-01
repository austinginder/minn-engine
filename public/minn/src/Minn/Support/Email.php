<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * The address rules the reference applies: a local part from a fixed
 * character class, a dotted domain of hyphen-trimmed labels. `check` names
 * the first rule an address breaks; `sanitize` strips what it can and names
 * why it gave up.
 */
final class Email
{
    private const LOCAL_CHARACTER = 'a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-';

    /**
     * Why an address is invalid, or null when it passes the reference's checks.
     *
     * @return string|null the reason the address is refused, null when it passes
     */
    public static function check(string $email): ?string
    {
        if (strlen($email) < 6 || substr_count($email, '@') !== 1) {
            return 'email_too_short';
        }
        [$local, $domain] = explode('@', $email, 2);
        if (!preg_match('/^[' . self::LOCAL_CHARACTER . ']+$/', $local)) {
            return 'local_invalid_chars';
        }
        if (preg_match('/\.{2,}/', $domain) || trim($domain, " \t\n\r\0\x0B.") !== $domain) {
            return 'domain_period_sequence';
        }
        $labels = explode('.', $domain);
        if (count($labels) < 2) {
            return 'domain_no_periods';
        }
        foreach ($labels as $label) {
            if (trim($label, '-') !== $label || !preg_match('/^[a-z0-9-]+$/i', $label)) {
                return 'sub_hyphen_limits';
            }
        }
        return null;
    }

    /**
     * An address with the characters the reference strips removed, and what was removed.
     *
     * @return array{0: string, 1: string|null} the cleaned address (empty when refused) and the reason
     */
    public static function sanitize(string $raw): array
    {
        $email = trim($raw);
        if (strlen($email) < 6 || !str_contains($email, '@') || strrpos($email, '@') === 0) {
            return ['', 'email_too_short'];
        }
        if (substr_count($email, '@') !== 1) {
            return ['', 'email_no_at'];
        }
        [$local, $domain] = explode('@', $email, 2);
        $local = (string) preg_replace('/[^' . self::LOCAL_CHARACTER . ']/', '', $local);
        if ($local === '') {
            return ['', 'local_invalid_chars'];
        }
        $domain = trim((string) preg_replace('/\.{2,}/', '', $domain), " \t\n\r\0\x0B.");
        if ($domain === '' || !str_contains($domain, '.')) {
            return ['', 'domain_no_periods'];
        }
        $labels = [];
        foreach (explode('.', $domain) as $label) {
            $label = trim((string) preg_replace('/[^a-z0-9-]+/i', '', $label), '-');
            if ($label !== '') {
                $labels[] = $label;
            }
        }
        if (count($labels) < 2) {
            return ['', 'domain_no_valid_subs'];
        }
        return [$local . '@' . implode('.', $labels), null];
    }
}
