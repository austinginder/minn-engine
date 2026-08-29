<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;

/**
 * The decisions behind wp_insert_user: what a new account needs, which email
 * and login collide, the resolved profile fields, and which columns an
 * update changes. The sanitisers and lookups arrive as closures so the
 * reference's filters apply. Behaviour pinned by contracts/fixtures/api/functions.json.
 */
final readonly class UserInsert
{
    public const META_KEYS = ['nickname', 'first_name', 'last_name', 'description', 'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 'show_admin_bar_front', 'locale'];

    /**
     * @param Closure(string): string $sanitizeLogin
     * @param Closure(string): string $sanitizeSlug
     * @param Closure(string): bool $isEmail
     * @param Closure(string): bool $loginTaken
     * @param Closure(string): int $emailOwner 0 when nobody has it
     * @param Closure(string): bool $roleExists
     */
    public function __construct(private Closure $sanitizeLogin, private Closure $sanitizeSlug, private Closure $isEmail, private Closure $loginTaken, private Closure $emailOwner, private Closure $roleExists)
    {
    }

    /**
     * The validated, resolved fields, or the refusal the reference gives.
     *
     * @param array<string, mixed> $userdata
     * @param array<string, mixed>|null $existing the current row on an update
     * @return array{login: string, email: string, nicename: ?string, display_name: string, role: ?string}|Refusal
     */
    public function resolve(array $userdata, ?array $existing): array|Refusal
    {
        $update = $existing !== null;
        $login = $update ? (string) $existing['user_login'] : ($this->sanitizeLogin)(trim((string) ($userdata['user_login'] ?? '')));
        if (!$update) {
            if ($login === '') {
                return new Refusal('empty_user_login', 'Cannot create a user with an empty login name.');
            }
            if (mb_strlen($login) > 60) {
                return new Refusal('user_login_too_long', 'Username may not be longer than 60 characters.');
            }
            if (($this->loginTaken)($login)) {
                return new Refusal('existing_user_login', 'Sorry, that username already exists!');
            }
            if (empty($userdata['user_pass'])) {
                return new Refusal('empty_user_pass', 'A password is required for a new user.');
            }
        }
        $email = isset($userdata['user_email']) ? (string) $userdata['user_email'] : ($update ? (string) $existing['user_email'] : '');
        if ($email !== '' && !($this->isEmail)($email)) {
            return new Refusal('invalid_email', 'The email address isn&#8217;t correct.');
        }
        $owner = $email !== '' ? ($this->emailOwner)($email) : 0;
        if ($owner > 0 && (!$update || $owner !== (int) $userdata['ID'])) {
            return new Refusal('existing_user_email', 'Sorry, that email address is already used!');
        }
        $role = isset($userdata['role']) ? (string) $userdata['role'] : null;
        if ($role !== null && !($this->roleExists)($role)) {
            return new Refusal('invalid_role', 'Invalid role.');
        }
        return [
            'login' => $login,
            'email' => $email,
            'nicename' => isset($userdata['user_nicename']) && $userdata['user_nicename'] !== '' ? ($this->sanitizeSlug)((string) $userdata['user_nicename']) : ($update ? (string) $existing['user_nicename'] : null),
            'display_name' => isset($userdata['display_name']) && $userdata['display_name'] !== '' ? (string) $userdata['display_name'] : ($update ? (string) $existing['display_name'] : ''),
            'role' => $role,
        ];
    }

    /** The fields a new account is created from. @param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved @return array<string, mixed> */
    public static function account(array $userdata, array $resolved, string $defaultRole): array
    {
        return [
            'login' => $resolved['login'],
            'password' => (string) $userdata['user_pass'],
            'email' => $resolved['email'],
            'url' => (string) ($userdata['user_url'] ?? ''),
            'nicename' => $resolved['nicename'],
            'display_name' => $resolved['display_name'],
            'role' => $resolved['role'] ?? $defaultRole,
            'nickname' => (string) ($userdata['nickname'] ?? $resolved['login']),
            'first_name' => (string) ($userdata['first_name'] ?? ''),
            'last_name' => (string) ($userdata['last_name'] ?? ''),
            'description' => (string) ($userdata['description'] ?? ''),
            'locale' => (string) ($userdata['locale'] ?? ''),
        ];
    }

    /**
     * The columns an update actually changes.
     *
     * @param array<string, mixed> $existing
     * @param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved
     * @param Closure(string): string $url the URL sanitiser
     * @param Closure(string): string $hash the password hasher
     * @return array<string, string>
     */
    public static function changes(array $userdata, array $existing, array $resolved, Closure $url, Closure $hash): array
    {
        $candidates = [
            'user_email' => $resolved['email'],
            'user_url' => isset($userdata['user_url']) ? $url((string) $userdata['user_url']) : null,
            'user_nicename' => $resolved['nicename'],
            'display_name' => $resolved['display_name'],
            'user_registered' => $userdata['user_registered'] ?? null,
        ];
        $columns = [];
        foreach ($candidates as $column => $value) {
            if ($value !== null && (string) $value !== (string) ($existing[$column] ?? '')) {
                $columns[$column] = (string) $value;
            }
        }
        if (!empty($userdata['user_pass'])) {
            $columns['user_pass'] = $hash((string) $userdata['user_pass']);
        }
        return $columns;
    }
}
