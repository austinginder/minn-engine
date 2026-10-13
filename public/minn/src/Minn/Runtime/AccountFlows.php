<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Mail\Mailer;
use Minn\I18n\Gettext;
use Minn\Objects\Errors;
use Minn\Objects\User;
use Minn\Support\Entities;
use Minn\Support\Sanitize;

/**
 * The account functions a sign-in page and plugins call, with the
 * reference's checks, words, hooks and mail (probe account-flows): asking
 * for a password reset (retrieve_password), resetting one
 * (reset_password), registering (register_new_user), and telling the site
 * and the new user about a registration (wp_new_user_notification).
 */
final class AccountFlows
{
    private const NO_ACCOUNT = '<strong>Error:</strong> There is no account with that username or email address.';

    /**
     * A reset link mailed to the account a login or email names: true, or
     * the refusal (no name, no such account, a plugin's error, reset not
     * allowed, or the mail failing).
     */
    public static function retrievePassword(string $login): bool|Errors
    {
        $errors = Handoff::newError();
        $login = trim(stripslashes($login));
        $user = false;
        if ($login === '') {
            $errors->add('empty_username', Gettext::text('<strong>Error:</strong> Please enter a username or email address.'));
        } elseif (str_contains($login, '@')) {
            $user = Handoff::userBy('email', $login) ?: Handoff::userBy('login', $login);
            if (!$user) {
                $errors->add('invalid_email', Gettext::text(self::NO_ACCOUNT));
            }
        } else {
            $user = Handoff::userBy('login', $login);
        }
        $user = Runtime::hooks()->filter('lostpassword_user_data', [$user, $errors]);
        Runtime::hooks()->action('lostpassword_post', [$errors, $user]);
        $errors = Runtime::hooks()->filter('lostpassword_errors', [$errors, $user]);
        if ($errors->has_errors()) {
            return $errors;
        }
        if (!$user instanceof User) {
            return Handoff::newError('invalidcombo', Gettext::text(self::NO_ACCOUNT));
        }
        if (!Runtime::hooks()->filter('send_retrieve_password_email', [true, $user->user_login, $user])) {
            return true;
        }
        $key = \get_password_reset_key($user);
        if ($key instanceof Errors) {
            return $key;
        }
        $notice = Mailer::noticesFor(Runtime::current()->site)->passwordReset($user->user_login, $user->user_email, self::resetLink($user, $key) . '&wp_lang=' . rawurlencode((string) \get_user_locale($user)), (string) (Runtime::current()->request?->remoteAddress ?? ''));
        $title = Runtime::hooks()->filter('retrieve_password_title', [$notice->subject, $user->user_login, $user]);
        $message = Runtime::hooks()->filter('retrieve_password_message', [$notice->body, $key, $user->user_login, $user]);
        $email = (array) Runtime::hooks()->filter('retrieve_password_notification_email', [['to' => $user->user_email, 'subject' => $title, 'message' => $message, 'headers' => ''], $key, $user->user_login, $user]);
        if (!Handoff::mail($email['to'] ?? $user->user_email, Entities::decode((string) ($email['subject'] ?? $title), ENT_NOQUOTES), (string) ($email['message'] ?? $message), $email['headers'] ?? '')) {
            return Handoff::newError('retrieve_password_email_failure', Gettext::text('<strong>Error:</strong> The email could not be sent. Your site may not be correctly configured to send emails.'));
        }
        return true;
    }

    /** A new password for a user, announced before (password_reset) and after (after_password_reset). */
    public static function resetPassword(User $user, string $password): void
    {
        Runtime::hooks()->action('password_reset', [$user, $password]);
        Handoff::setPassword($password, $user->ID);
        \update_user_meta($user->ID, 'default_password_nag', false);
        Runtime::hooks()->action('after_password_reset', [$user, $password]);
    }

    /**
     * A new account for a login and an email, with a generated password and
     * the nag to change it: its id, or every refusal at once (the name
     * empty, illegal, taken or barred; the email empty, malformed or taken;
     * whatever register_post and registration_errors add).
     */
    public static function registerNewUser(string $login, string $email): int|Errors
    {
        $errors = Handoff::newError();
        $name = Sanitize::user($login);
        $email = (string) Runtime::hooks()->filter('user_registration_email', [$email]);
        $nameError = match (true) {
            $name === '' => ['empty_username', '<strong>Error:</strong> Please enter a username.'],
            !\validate_username($login) => ['invalid_username', '<strong>Error:</strong> This username is invalid because it uses illegal characters. Please enter a valid username.'],
            (bool) \username_exists($name) => ['username_exists', '<strong>Error:</strong> This username is already registered. Please choose another one.'],
            in_array(strtolower($name), array_map('strtolower', (array) Runtime::hooks()->filter('illegal_user_logins', [[]])), true) => ['invalid_username', '<strong>Error:</strong> Sorry, that username is not allowed.'],
            default => null,
        };
        if ($nameError !== null) {
            $errors->add($nameError[0], Gettext::text($nameError[1]));
            $name = $nameError[0] === 'invalid_username' ? '' : $name;
        }
        $emailError = match (true) {
            $email === '' => ['empty_email', Gettext::text('<strong>Error:</strong> Please type your email address.')],
            !Sanitize::isEmail($email) => ['invalid_email', Gettext::text('<strong>Error:</strong> The email address is not correct.')],
            (bool) \email_exists($email) => ['email_exists', sprintf(Gettext::text('<strong>Error:</strong> This email address is already registered. <a href="%s">Log in</a> with this address or choose another one.'), SiteUrls::login())],
            default => null,
        };
        if ($emailError !== null) {
            $errors->add(...$emailError);
            $email = $emailError[0] === 'invalid_email' ? '' : $email;
        }
        Runtime::hooks()->action('register_post', [$name, $email, $errors]);
        $errors = Runtime::hooks()->filter('registration_errors', [$errors, $name, $email]);
        if ($errors->has_errors()) {
            return $errors;
        }
        $id = \wp_create_user($name, Handoff::password(12, false), $email);
        if (!is_int($id) || $id < 1) {
            return Handoff::newError('registerfail', sprintf(Gettext::text('<strong>Error:</strong> Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'), Runtime::options()->filtered('admin_email')));
        }
        \update_user_meta($id, 'default_password_nag', true);
        Runtime::hooks()->action('register_new_user', [$id]);
        return $id;
    }

    /**
     * A new account announced: to the site's address (unless only the user
     * is told), then to the user with a link to set their password (unless
     * only the site is, or the old call shape asks for nobody).
     */
    public static function newUserNotification(int $userId, mixed $deprecated, string $notify): void
    {
        $user = Handoff::user($userId);
        if (!in_array($notify, ['user', 'admin', 'both', ''], true) || !$user instanceof User) {
            return;
        }
        $site = Entities::decode((string) Runtime::options()->filtered('blogname'), ENT_QUOTES);
        if ($notify !== 'user' && Runtime::hooks()->filter('wp_send_new_user_notification_to_admin', [true, $user])) {
            $message = sprintf(Gettext::text('New user registration on your site %s:'), $site) . "\r\n\r\n" . sprintf(Gettext::text('Username: %s'), $user->user_login) . "\r\n\r\n" . sprintf(Gettext::text('Email: %s'), $user->user_email) . "\r\n";
            $mail = (array) Runtime::hooks()->filter('wp_new_user_notification_email_admin', [['to' => Runtime::options()->filtered('admin_email'), 'subject' => Gettext::text('[%s] New User Registration'), 'message' => $message, 'headers' => ''], $user, $site]);
            Handoff::mail($mail['to'] ?? '', Entities::decode(sprintf((string) ($mail['subject'] ?? ''), $site), ENT_NOQUOTES), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
        }
        if ($notify === 'admin' || (empty($deprecated) && $notify === '') || !Runtime::hooks()->filter('wp_send_new_user_notification_to_user', [true, $user])) {
            return;
        }
        $key = \get_password_reset_key($user);
        if ($key instanceof Errors) {
            return;
        }
        $message = sprintf(Gettext::text('Username: %s'), $user->user_login) . "\r\n\r\n" . Gettext::text('To set your password, visit the following address:') . "\r\n\r\n" . self::resetLink($user, $key) . "\r\n\r\n" . SiteUrls::login() . "\r\n";
        $mail = (array) Runtime::hooks()->filter('wp_new_user_notification_email', [['to' => $user->user_email, 'subject' => Gettext::text('[%s] Login Details'), 'message' => $message, 'headers' => ''], $user, $site]);
        Handoff::mail($mail['to'] ?? '', Entities::decode(sprintf((string) ($mail['subject'] ?? ''), $site), ENT_NOQUOTES), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
    }

    /** The link that opens the password form for a user's key. */
    private static function resetLink(User $user, string $key): string
    {
        return SiteUrls::site('wp-login.php?login=' . rawurlencode($user->user_login) . "&key={$key}&action=rp", 'login');
    }
}
