<?php

use Minn\Auth\ApplicationPasswords;
use Minn\Runtime\ApplicationPasswordRecords;

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

    public static function is_in_use()
    {
        return ApplicationPasswordRecords::inUse();
    }

    /** A new password for the user: [the password, its record], or the reference's error for a nameless one. */
    public static function create_new_application_password($user_id, $args = [])
    {
        return ApplicationPasswordRecords::create($user_id, $args);
    }

    public static function get_user_application_passwords($user_id)
    {
        return ApplicationPasswordRecords::all($user_id);
    }

    public static function get_user_application_password($user_id, $uuid)
    {
        return ApplicationPasswordRecords::find($user_id, $uuid);
    }

    public static function application_name_exists_for_user($user_id, $name)
    {
        return ApplicationPasswordRecords::nameExists($user_id, $name);
    }

    /** Renames a password (the only field the reference changes); true, or its not-found error. */
    public static function update_application_password($user_id, $uuid, $update = [])
    {
        return ApplicationPasswordRecords::update($user_id, $uuid, $update);
    }

    /** Records a use (when and from where); true, or the not-found error. */
    public static function record_application_password_usage($user_id, $uuid)
    {
        return ApplicationPasswordRecords::recordUsage($user_id, $uuid);
    }

    public static function delete_application_password($user_id, $uuid)
    {
        return ApplicationPasswordRecords::delete($user_id, $uuid);
    }

    /** Removes every password of the user, each through wp_delete_application_password; how many went. */
    public static function delete_all_application_passwords($user_id)
    {
        return ApplicationPasswordRecords::deleteAll($user_id);
    }

    /** The site's fast hash of a password ($generic$, the same each time). */
    public static function hash_password($password)
    {
        return wp_fast_hash($password);
    }

    /** Whether a password matches a stored hash: the fast hash, or an older kind through wp_check_password. */
    public static function check_password($password, $hash)
    {
        if (!str_starts_with((string) $hash, '$generic$')) {
            return wp_check_password($password, $hash);
        }
        return wp_verify_fast_hash($password, $hash);
    }

    /** A user's list stored as given; update_user_meta's answer. */
    protected static function set_user_application_passwords($user_id, $passwords)
    {
        return update_user_meta($user_id, static::USERMETA_KEY_APPLICATION_PASSWORDS, $passwords);
    }

    public static function chunk_password($raw_password)
    {
        return ApplicationPasswords::chunked(preg_replace('/[^a-z\d]/i', '', (string) $raw_password));
    }

}
