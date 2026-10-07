<?php

use Minn\Auth\ApplicationPasswords;
use Minn\Content\Users;
use Minn\Runtime\Runtime;

/**
 * The application password API plugin code calls, over the engine's own
 * store (Minn\Auth\ApplicationPasswords, the reference's format), with the
 * reference's answers and hooks (probe application-passwords-api).
 */
#[AllowDynamicProperties]
class WP_Application_Passwords
{
    const USERMETA_KEY_APPLICATION_PASSWORDS = '_application_passwords';
    const OPTION_KEY_IN_USE = 'using_application_passwords';
    const PW_LENGTH = 24;

    /** The engine's store. */
    private static function store(): ApplicationPasswords
    {
        return new ApplicationPasswords(new Users(Runtime::current()->db));
    }

    public static function is_in_use()
    {
        return (bool) get_site_option(self::OPTION_KEY_IN_USE);
    }

    /** A new password for the user: [the password, its record], or the reference's error for a nameless one. */
    public static function create_new_application_password($user_id, $args = [])
    {
        $name = sanitize_text_field((string) ($args['name'] ?? ''));
        if ($name === '') {
            return new WP_Error('application_password_empty_name', __('An application name is required to create an application password.'), ['status' => 400]);
        }
        [$item, $shown] = self::store()->create((int) $user_id, $name, (string) ($args['app_id'] ?? ''));
        if (!self::is_in_use()) {
            update_site_option(self::OPTION_KEY_IN_USE, true);
        }
        $password = str_replace(' ', '', $shown);
        do_action('wp_create_application_password', $user_id, $item, $password, $args);
        return [$password, $item];
    }

    public static function get_user_application_passwords($user_id)
    {
        return self::store()->all((int) $user_id);
    }

    public static function get_user_application_password($user_id, $uuid)
    {
        return self::store()->find((int) $user_id, (string) $uuid);
    }

    public static function application_name_exists_for_user($user_id, $name)
    {
        return in_array((string) $name, array_column(self::store()->all((int) $user_id), 'name'), true);
    }

    /** Renames a password (the only field the reference changes); true, or its not-found error. */
    public static function update_application_password($user_id, $uuid, $update = [])
    {
        $item = isset($update['name']) ? self::store()->rename((int) $user_id, (string) $uuid, sanitize_text_field((string) $update['name'])) : self::store()->find((int) $user_id, (string) $uuid);
        if ($item === null) {
            return self::notFound();
        }
        do_action('wp_update_application_password', $user_id, $item, $update);
        return true;
    }

    /** Records a use (when and from where); true, or the not-found error. */
    public static function record_application_password_usage($user_id, $uuid)
    {
        $ip = (string) (Runtime::current()->request?->remoteAddress ?? '');
        return self::store()->touch((int) $user_id, (string) $uuid, $ip) === null ? self::notFound() : true;
    }

    public static function delete_application_password($user_id, $uuid)
    {
        $item = self::store()->delete((int) $user_id, (string) $uuid);
        if ($item === null) {
            return self::notFound();
        }
        do_action('wp_delete_application_password', $user_id, $item);
        return true;
    }

    /** Removes every password of the user, each through wp_delete_application_password; how many went. */
    public static function delete_all_application_passwords($user_id)
    {
        $items = self::store()->all((int) $user_id);
        self::store()->deleteAll((int) $user_id);
        foreach ($items as $item) {
            do_action('wp_delete_application_password', $user_id, $item);
        }
        return count($items);
    }

    public static function chunk_password($raw_password)
    {
        return ApplicationPasswords::chunked(preg_replace('/[^a-z\d]/i', '', (string) $raw_password));
    }

    private static function notFound(): WP_Error
    {
        return new WP_Error('application_password_not_found', __('Could not find an application password with that id.'));
    }
}
