<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Objects\Errors;
use Minn\Objects\User;
use Minn\Support\Sanitize;

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
        if ($input instanceof User) {
            return $input;
        }
        $api = (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) || (defined('REST_REQUEST') && REST_REQUEST);
        if (!Runtime::hooks()->filter('application_password_is_api_request', [$api])) {
            return $input;
        }
        $user = Handoff::userBy('login', $username);
        if (!$user && Sanitize::isEmail($username)) {
            $user = Handoff::userBy('email', $username);
        }
        $error = self::refusal($user, $username);
        if ($error !== null) {
            Runtime::hooks()->action('application_password_failed_authentication', [$error]);
            return $error;
        }
        $password = (string) preg_replace('/[^a-z\d]/i', '', $password);
        $item = (new \Minn\Auth\ApplicationPasswords(new \Minn\Content\Users(Runtime::current()->db)))->verify((int) $user->ID, $password);
        if ($item === null) {
            $error = Handoff::newError('incorrect_password', Gettext::text('The provided password is an invalid application password.'));
            Runtime::hooks()->action('application_password_failed_authentication', [$error]);
            return $error;
        }
        $errors = Handoff::newError();
        Runtime::hooks()->actionRefArray('wp_authenticate_application_password_errors', [$errors, $user, $item, $password]);
        if ($errors->has_errors()) {
            Runtime::hooks()->action('application_password_failed_authentication', [$errors]);
            return $errors;
        }
        ApplicationPasswordRecords::recordUsage($user->ID, $item['uuid']);
        Runtime::hooks()->action('application_password_did_authenticate', [$user, $item]);
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
        $user = Handoff::user((int) $auth['user']);
        $error = self::refusal($user, '');
        // With the feature off for the whole site nothing is tried and nothing announced, so
        // rest_authentication_errors hears no refusal and the request reads as signed out (suite identity).
        if ($error !== null && $error->get_error_code() === 'application_passwords_disabled') {
            return $input;
        }
        if ($error === null) {
            $error = Handoff::newError();
            Runtime::hooks()->actionRefArray('wp_authenticate_application_password_errors', [$error, $user, $auth['item'], (string) $auth['password']]);
        }
        if ($error->has_errors()) {
            Runtime::hooks()->action('application_password_failed_authentication', [$error]);
            return $input;
        }
        Runtime::hooks()->action('application_password_did_authenticate', [$user, $auth['item']]);
        return (int) $user->ID;
    }

    /** Why the user may not sign in by application password, or null when they may. */
    private static function refusal(mixed $user, string $username): ?Errors
    {
        return match (true) {
            !$user instanceof User && Sanitize::isEmail($username) => Handoff::newError('invalid_email', Gettext::text('<strong>Error:</strong> Unknown email address. Check again or try your username.')),
            !$user instanceof User => Handoff::newError('invalid_username', Gettext::text('<strong>Error:</strong> Unknown username. Check again or try your email address.')),
            !\wp_is_application_passwords_available() => Handoff::newError('application_passwords_disabled', Gettext::text('Application passwords are not available.')),
            !\wp_is_application_passwords_available_for_user($user) => Handoff::newError('application_passwords_disabled_for_user', Gettext::text('Application passwords are not available for your account. Please contact the site administrator for assistance.')),
            default => null,
        };
    }
}
