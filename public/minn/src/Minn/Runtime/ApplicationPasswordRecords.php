<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\ApplicationPasswords;
use Minn\Content\Users;
use Minn\I18n\Gettext;
use Minn\Support\Sanitize;

/**
 * A user's application passwords as WordPress's API keeps them
 * (WP_Application_Passwords), over the engine's store
 * (Minn\Auth\ApplicationPasswords, the reference's format): made, found,
 * renamed, used, removed one by one or all together, with the reference's
 * answers and actions.
 */
final class ApplicationPasswordRecords
{
    /** The site option saying any password was made. */
    private const IN_USE = 'using_application_passwords';

    /** Whether any user has made an application password (is_in_use). */
    public static function inUse(): bool
    {
        return (bool) SiteOptions::get(self::IN_USE);
    }

    /**
     * A new password for the user (create_new_application_password): [the
     * password, its record], or the reference's error for a nameless one.
     */
    public static function create(mixed $userId, mixed $args = []): mixed
    {
        $name = Sanitize::textField((string) ($args['name'] ?? ''));
        if ($name === '') {
            return Handoff::newError('application_password_empty_name', Gettext::text('An application name is required to create an application password.'), ['status' => 400]);
        }
        [$item, $shown] = self::store()->create((int) $userId, $name, (string) ($args['app_id'] ?? ''));
        if (!self::inUse()) {
            SiteOptions::update(self::IN_USE, true);
        }
        $password = str_replace(' ', '', $shown);
        Runtime::hooks()->action('wp_create_application_password', [$userId, $item, $password, $args]);
        return [$password, $item];
    }

    /**
     * A user's passwords (get_user_application_passwords).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(mixed $userId): array
    {
        return self::store()->all((int) $userId);
    }

    /** One of a user's passwords by its uuid (get_user_application_password), or null. */
    public static function find(mixed $userId, mixed $uuid): ?array
    {
        return self::store()->find((int) $userId, (string) $uuid);
    }

    /** Whether one of a user's passwords has the name (application_name_exists_for_user). */
    public static function nameExists(mixed $userId, mixed $name): bool
    {
        return in_array((string) $name, array_column(self::all($userId), 'name'), true);
    }

    /** A password renamed, the only field the reference changes (update_application_password): true, or its not-found error. */
    public static function update(mixed $userId, mixed $uuid, mixed $update = []): mixed
    {
        $item = isset($update['name']) ? self::store()->rename((int) $userId, (string) $uuid, Sanitize::textField((string) $update['name'])) : self::find($userId, $uuid);
        if ($item === null) {
            return self::notFound();
        }
        Runtime::hooks()->action('wp_update_application_password', [$userId, $item, $update]);
        return true;
    }

    /** A use recorded, when and from where (record_application_password_usage): true, or the not-found error. */
    public static function recordUsage(mixed $userId, mixed $uuid): mixed
    {
        $ip = (string) (Runtime::current()->request?->remoteAddress ?? '');
        return self::store()->touch((int) $userId, (string) $uuid, $ip) === null ? self::notFound() : true;
    }

    /** A password removed (delete_application_password): true, or the not-found error. */
    public static function delete(mixed $userId, mixed $uuid): mixed
    {
        $item = self::store()->delete((int) $userId, (string) $uuid);
        if ($item === null) {
            return self::notFound();
        }
        Runtime::hooks()->action('wp_delete_application_password', [$userId, $item]);
        return true;
    }

    /** Every password of the user removed, each told to wp_delete_application_password (delete_all_application_passwords); how many went. */
    public static function deleteAll(mixed $userId): int
    {
        $items = self::all($userId);
        self::store()->deleteAll((int) $userId);
        foreach ($items as $item) {
            Runtime::hooks()->action('wp_delete_application_password', [$userId, $item]);
        }
        return count($items);
    }

    private static function notFound(): mixed
    {
        return Handoff::newError('application_password_not_found', Gettext::text('Could not find an application password with that id.'));
    }

    private static function store(): ApplicationPasswords
    {
        return new ApplicationPasswords(new Users(Runtime::current()->db));
    }
}
