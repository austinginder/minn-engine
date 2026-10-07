<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * wp_authenticate_application_password as the reference answers it (probe
 * application-passwords-api): a user found already stands; outside an API
 * request (application_password_is_api_request) nothing is tried; then the
 * user by login or email, the feature's availability (for the site, for
 * the user), and each of the user's passwords, a match passing through
 * wp_authenticate_application_password_errors, recorded, and announced
 * (application_password_did_authenticate); every refusal announced
 * through application_password_failed_authentication.
 */
final class ApplicationPasswordSignIn
{
    /** The signed-in user, the input unchanged, or the refusal. */
    public static function authenticate(mixed $input, string $username, string $password): mixed
    {
        if ($input instanceof \WP_User) {
            return $input;
        }
        $api = (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('REST_REQUEST') && REST_REQUEST);
        if (!\apply_filters('application_password_is_api_request', $api)) {
            return $input;
        }
        $user = \get_user_by('login', $username);
        if (!$user && \is_email($username)) {
            $user = \get_user_by('email', $username);
        }
        $error = self::refusal($user, $username);
        if ($error !== null) {
            \do_action('application_password_failed_authentication', $error);
            return $error;
        }
        $password = (string) preg_replace('/[^a-z\d]/i', '', $password);
        $item = (new \Minn\Auth\ApplicationPasswords(new \Minn\Content\Users(Runtime::current()->db)))->verify((int) $user->ID, $password);
        if ($item === null) {
            $error = new \WP_Error('incorrect_password', \__('The provided password is an invalid application password.'));
            \do_action('application_password_failed_authentication', $error);
            return $error;
        }
        $errors = new \WP_Error();
        \do_action_ref_array('wp_authenticate_application_password_errors', [$errors, $user, $item, $password]);
        if ($errors->has_errors()) {
            \do_action('application_password_failed_authentication', $errors);
            return $errors;
        }
        \WP_Application_Passwords::record_application_password_usage($user->ID, $item['uuid']);
        \do_action('application_password_did_authenticate', $user, $item);
        return $user;
    }

    /**
     * determine_current_user's application password step: an earlier
     * answer stands; else the user the REST request's application password
     * signed in (the engine checked the password before plugins loaded),
     * heard again as the reference hears it: the feature's availability,
     * wp_authenticate_application_password_errors, then
     * application_password_did_authenticate; a refusal is announced and
     * leaves the request signed out.
     */
    public static function validate(mixed $input): mixed
    {
        $auth = Runtime::booted() ? Runtime::current()->get('application_password_auth') : null;
        if (!empty($input) || !is_array($auth)) {
            return $input;
        }
        $user = \get_userdata((int) $auth['user']);
        $error = self::refusal($user, '');
        if ($error === null) {
            $error = new \WP_Error();
            \do_action_ref_array('wp_authenticate_application_password_errors', [$error, $user, $auth['item'], (string) $auth['password']]);
        }
        if ($error->has_errors()) {
            \do_action('application_password_failed_authentication', $error);
            return $input;
        }
        \do_action('application_password_did_authenticate', $user, $auth['item']);
        return (int) $user->ID;
    }

    /** Why the user may not sign in by application password, or null when they may. */
    private static function refusal(mixed $user, string $username): ?\WP_Error
    {
        return match (true) {
            !$user instanceof \WP_User && \is_email($username) => new \WP_Error('invalid_email', \__('<strong>Error:</strong> Unknown email address. Check again or try your username.')),
            !$user instanceof \WP_User => new \WP_Error('invalid_username', \__('<strong>Error:</strong> Unknown username. Check again or try your email address.')),
            !\wp_is_application_passwords_available() => new \WP_Error('application_passwords_disabled', \__('Application passwords are not available.')),
            !\wp_is_application_passwords_available_for_user($user) => new \WP_Error('application_passwords_disabled_for_user', \__('Application passwords are not available for your account. Please contact the site administrator for assistance.')),
            default => null,
        };
    }
}
