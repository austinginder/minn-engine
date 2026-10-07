<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\ApplicationPasswords;

/**
 * Application password changes made over REST, as the reference makes them
 * (probe rest-application-password-hooks): the prepared item (the name,
 * and an app id a new one is given) through
 * rest_pre_insert_application_password, then WP_Application_Passwords
 * (which tells plugins through wp_create_, wp_update_ and
 * wp_delete_application_password), then rest_after_insert_application_password.
 */
final class ApplicationPasswordEvents
{
    /**
     * A new password: its record and its plain text in groups of four, or
     * the reference's refusal.
     *
     * @param array<string, mixed> $body
     * @return array{0: array<string, mixed>, 1: string}|\WP_Error
     */
    public static function create(int $userId, array $body, \WP_REST_Request $request): array|\WP_Error
    {
        $fields = array_intersect_key($body, ['name' => true]) + (empty($body['app_id']) ? [] : ['app_id' => $body['app_id']]);
        $prepared = self::prepared($fields, $request);
        $made = \WP_Application_Passwords::create_new_application_password($userId, \wp_slash((array) $prepared));
        if ($made instanceof \WP_Error) {
            return $made;
        }
        [$password, $item] = $made;
        \do_action('rest_after_insert_application_password', $item, $request, true);
        return [$item, ApplicationPasswords::chunked($password)];
    }

    /**
     * A password renamed (when the body names it): its record as it stands.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|\WP_Error
     */
    public static function update(int $userId, string $uuid, array $body, \WP_REST_Request $request): array|\WP_Error
    {
        $prepared = self::prepared(array_intersect_key($body, ['name' => true]), $request);
        $updated = \WP_Application_Passwords::update_application_password($userId, $uuid, \wp_slash((array) $prepared));
        if ($updated instanceof \WP_Error) {
            return $updated;
        }
        $item = (array) \WP_Application_Passwords::get_user_application_password($userId, $uuid);
        \do_action('rest_after_insert_application_password', $item, $request, false);
        return $item;
    }

    /** The item the request prepares (its name; a new one's app id), through rest_pre_insert_application_password. @param array<string, mixed> $fields */
    private static function prepared(array $fields, \WP_REST_Request $request): object
    {
        return (object) \apply_filters('rest_pre_insert_application_password', (object) $fields, $request);
    }
}
