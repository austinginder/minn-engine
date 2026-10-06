<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * An account's fields through the filters the reference's wp_insert_user
 * runs, in its order (probe user-insert-filters, contracts/runtime.md
 * "Accounts plugins can change"): the login (pre_user_login), refused when
 * illegal_user_logins names it; the nicename, made from the login for a
 * new account (pre_user_nicename); the email and the URL; the nickname
 * (the login when none), first and last names; the display name, made as
 * the reference makes it when none is given; the description. Each
 * pre_user_* filter carries the reference's sanitisers. Then
 * wp_pre_insert_user_data over the row and insert_user_meta over the meta.
 */
final class UserSave
{
    /**
     * The fields after their filters, or the refusal for an illegal login.
     *
     * @param array<string, mixed> $userdata
     * @param array<string, mixed>|null $existing the stored account on an update
     * @return array<string, mixed>|Refusal
     */
    public static function fields(array $userdata, ?array $existing): array|Refusal
    {
        $given = static fn (string $key): string => isset($userdata[$key]) && is_scalar($userdata[$key]) ? (string) $userdata[$key] : '';
        $stored = static fn (string $key): string => (string) ($existing[$key] ?? '');
        $login = (string) \sanitize_user($given('user_login') !== '' ? $given('user_login') : $stored('user_login'), true);
        $login = trim((string) \apply_filters('pre_user_login', $login));
        if ($existing === null && $login === '') {
            return new Refusal('empty_user_login', 'Cannot create a user with an empty login name.');
        }
        if ($existing === null && \username_exists($login)) {
            return new Refusal('existing_user_login', 'Sorry, that username already exists!');
        }
        $illegal = array_map('strtolower', (array) \apply_filters('illegal_user_logins', []));
        if (in_array(strtolower($login), $illegal, true)) {
            return new Refusal('invalid_username', 'Sorry, that username is not allowed.');
        }
        $nicename = $given('user_nicename') !== '' ? (string) \sanitize_user($given('user_nicename'), true) : (string) \sanitize_title(mb_substr($login, 0, 50));
        $nicename = (string) \apply_filters('pre_user_nicename', $nicename);
        $email = (string) \apply_filters('pre_user_email', $given('user_email'));
        $url = (string) \apply_filters('pre_user_url', $given('user_url'));
        $nickname = (string) \apply_filters('pre_user_nickname', $given('nickname') !== '' ? $given('nickname') : $login);
        $first = (string) \apply_filters('pre_user_first_name', $given('first_name'));
        $last = (string) \apply_filters('pre_user_last_name', $given('last_name'));
        $display = $given('display_name') !== '' ? $given('display_name') : ($existing !== null || trim("{$first} {$last}") === '' ? $login : trim("{$first} {$last}"));
        $display = (string) \apply_filters('pre_user_display_name', $display);
        $description = (string) \apply_filters('pre_user_description', $given('description'));
        return ['user_login' => $login, 'user_nicename' => $nicename, 'user_url' => $url, 'user_email' => $email, 'nickname' => $nickname, 'first_name' => $first, 'last_name' => $last, 'display_name' => $display, 'description' => $description];
    }

    /**
     * wp_pre_insert_user_data over the row an insert or update writes: the
     * row, whether it updates, the account's id (null for a new one), what
     * the caller gave.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $userdata
     * @return array<string, mixed>
     */
    public static function data(array $row, int $userId, array $userdata): array
    {
        return (array) \apply_filters('wp_pre_insert_user_data', $row, $userId > 0, $userId > 0 ? $userId : null, $userdata);
    }

    /**
     * insert_user_meta over the profile meta, then insert_custom_user_meta
     * over the meta_input the caller gave beyond it; the two merged. An
     * update is one whose userdata names an ID.
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $userdata
     * @return array<string, mixed>
     */
    public static function meta(array $meta, int $userId, array $userdata): array
    {
        $user = new \WP_User($userId);
        $update = !empty($userdata['ID']);
        $meta = (array) \apply_filters('insert_user_meta', $meta, $user, $update, $userdata);
        $custom = isset($userdata['meta_input']) && is_array($userdata['meta_input']) ? array_diff_key($userdata['meta_input'], $meta) : [];
        return $meta + (array) \apply_filters('insert_custom_user_meta', $custom, $user, $update, $userdata);
    }
}
