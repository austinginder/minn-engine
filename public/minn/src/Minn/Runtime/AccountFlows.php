<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Mail\Mailer;
use Minn\I18n\Gettext;
use Minn\Objects\Errors;
use Minn\Objects\User;
use Minn\Support\Entities;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;

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
        $key = UserWrites::resetKey($user);
        if ($key instanceof Errors) {
            return $key;
        }
        // Written in the user's language (probe account-flows).
        $sent = RecipientLocale::forUser((int) $user->ID, static function () use ($user, $key): bool {
            $notice = Mailer::noticesFor(Runtime::current()->site)->passwordReset($user->user_login, $user->user_email, self::resetLink($user, $key) . '&wp_lang=' . rawurlencode((string) Translations::userLocale($user)), (string) (Runtime::current()->request?->remoteAddress ?? ''));
            $title = Runtime::hooks()->filter('retrieve_password_title', [$notice->subject, $user->user_login, $user]);
            $message = Runtime::hooks()->filter('retrieve_password_message', [$notice->body, $key, $user->user_login, $user]);
            $email = (array) Runtime::hooks()->filter('retrieve_password_notification_email', [['to' => $user->user_email, 'subject' => $title, 'message' => $message, 'headers' => ''], $key, $user->user_login, $user]);
            return (bool) Handoff::mail($email['to'] ?? $user->user_email, Entities::decode((string) ($email['subject'] ?? $title), ENT_NOQUOTES), (string) ($email['message'] ?? $message), $email['headers'] ?? '');
        });
        if (!$sent) {
            return Handoff::newError('retrieve_password_email_failure', Gettext::text('<strong>Error:</strong> The email could not be sent. Your site may not be correctly configured to send emails.'));
        }
        return true;
    }

    /** A new password for a user, announced before (password_reset) and after (after_password_reset). */
    public static function resetPassword(User $user, string $password): void
    {
        Runtime::hooks()->action('password_reset', [$user, $password]);
        Handoff::setPassword($password, $user->ID);
        MetaData::updateUser($user->ID, 'default_password_nag', false);
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
        [$name, $email, $errors] = self::vetRegistration($login, $email);
        if ($errors->has_errors()) {
            return $errors;
        }
        $id = UserWrites::create($name, Handoff::password(12, false), $email);
        if (!is_int($id) || $id < 1) {
            return self::registerFailed();
        }
        MetaData::updateUser($id, 'default_password_nag', true);
        Runtime::hooks()->action('register_new_user', [$id]);
        return $id;
    }

    /**
     * A new account whose holder chose the password (a sign-up form of an
     * extension's own): the same checks as registerNewUser, then the account
     * with that password (as a form sends it, slashed) and, when one is
     * given, the name shown for it (display name and nickname) and the role
     * (else the site's default role), and the site told. The holder is
     * mailed nothing: there is no password to set.
     */
    public static function registerWithPassword(string $login, string $email, string $password, string $shownAs = '', string $role = ''): int|Errors
    {
        [$name, $email, $errors] = self::vetRegistration($login, $email);
        if ($password === '') {
            $errors->add('empty_password', Gettext::text('<strong>Error:</strong> Please enter a password.'));
        }
        if ($errors->has_errors()) {
            return $errors;
        }
        $account = ['user_login' => Slashes::add($name), 'user_email' => Slashes::add($email), 'user_pass' => Slashes::add($password)];
        $account += $shownAs === '' ? [] : ['display_name' => Slashes::add($shownAs), 'nickname' => Slashes::add($shownAs)];
        $id = UserWrites::insert($role === '' ? $account : $account + ['role' => $role]);
        if (!is_int($id) || $id < 1) {
            return self::registerFailed();
        }
        self::newUserNotification($id, null, 'admin');
        return $id;
    }

    /**
     * The name and email a registration asks for, judged as the reference
     * judges them: the sanitised name, the filtered email, and the refusals
     * (none when the registration may go ahead).
     *
     * @return array{0: string, 1: string, 2: Errors}
     */
    private static function vetRegistration(string $login, string $email): array
    {
        $errors = Handoff::newError();
        $name = Sanitize::user($login);
        $email = (string) Runtime::hooks()->filter('user_registration_email', [$email]);
        $nameError = match (true) {
            $name === '' => ['empty_username', '<strong>Error:</strong> Please enter a username.'],
            !UserWrites::validUsername($login) => ['invalid_username', '<strong>Error:</strong> This username is invalid because it uses illegal characters. Please enter a valid username.'],
            (bool) UserWrites::usernameExists($name) => ['username_exists', '<strong>Error:</strong> This username is already registered. Please choose another one.'],
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
            (bool) UserWrites::emailExists($email) => ['email_exists', sprintf(Gettext::text('<strong>Error:</strong> This email address is already registered. <a href="%s">Log in</a> with this address or choose another one.'), SiteUrls::login())],
            default => null,
        };
        if ($emailError !== null) {
            $errors->add(...$emailError);
            $email = $emailError[0] === 'invalid_email' ? '' : $email;
        }
        Runtime::hooks()->action('register_post', [$name, $email, $errors]);
        return [$name, $email, Runtime::hooks()->filter('registration_errors', [$errors, $name, $email])];
    }

    /** The refusal when the account row could not be written. */
    private static function registerFailed(): Errors
    {
        return Handoff::newError('registerfail', sprintf(Gettext::text('<strong>Error:</strong> Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'), Runtime::options()->filtered('admin_email')));
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
            // The site's address is told in its owner's language (the account holding it, else the site's).
            RecipientLocale::forSite(static function () use ($user, $site): void {
                $message = sprintf(Gettext::text('New user registration on your site %s:'), $site) . "\r\n\r\n" . sprintf(Gettext::text('Username: %s'), $user->user_login) . "\r\n\r\n" . sprintf(Gettext::text('Email: %s'), $user->user_email) . "\r\n";
                $mail = (array) Runtime::hooks()->filter('wp_new_user_notification_email_admin', [['to' => Runtime::options()->filtered('admin_email'), 'subject' => Gettext::text('[%s] New User Registration'), 'message' => $message, 'headers' => ''], $user, $site]);
                Handoff::mail($mail['to'] ?? '', Entities::decode(sprintf((string) ($mail['subject'] ?? ''), $site), ENT_NOQUOTES), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
            });
        }
        if ($notify === 'admin' || (empty($deprecated) && $notify === '') || !Runtime::hooks()->filter('wp_send_new_user_notification_to_user', [true, $user])) {
            return;
        }
        $key = UserWrites::resetKey($user);
        if ($key instanceof Errors) {
            return;
        }
        RecipientLocale::forUser((int) $user->ID, static function () use ($user, $key, $site): void {
            $message = sprintf(Gettext::text('Username: %s'), $user->user_login) . "\r\n\r\n" . Gettext::text('To set your password, visit the following address:') . "\r\n\r\n" . self::resetLink($user, $key) . "\r\n\r\n" . SiteUrls::login() . "\r\n";
            $mail = (array) Runtime::hooks()->filter('wp_new_user_notification_email', [['to' => $user->user_email, 'subject' => Gettext::text('[%s] Login Details'), 'message' => $message, 'headers' => ''], $user, $site]);
            Handoff::mail($mail['to'] ?? '', Entities::decode(sprintf((string) ($mail['subject'] ?? ''), $site), ENT_NOQUOTES), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
        });
    }

    /** The link that opens the password form for a user's key. */
    private static function resetLink(User $user, string $key): string
    {
        return SiteUrls::site('wp-login.php?login=' . rawurlencode($user->user_login) . "&key={$key}&action=rp", 'login');
    }
}
