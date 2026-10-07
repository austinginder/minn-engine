<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Mail\Mailer;

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
    public static function retrievePassword(string $login): bool|\WP_Error
    {
        $errors = new \WP_Error();
        $login = trim(\wp_unslash($login));
        $user = false;
        if ($login === '') {
            $errors->add('empty_username', \__('<strong>Error:</strong> Please enter a username or email address.'));
        } elseif (str_contains($login, '@')) {
            $user = \get_user_by('email', $login) ?: \get_user_by('login', $login);
            if (!$user) {
                $errors->add('invalid_email', \__(self::NO_ACCOUNT));
            }
        } else {
            $user = \get_user_by('login', $login);
        }
        $user = \apply_filters('lostpassword_user_data', $user, $errors);
        \do_action('lostpassword_post', $errors, $user);
        $errors = \apply_filters('lostpassword_errors', $errors, $user);
        if ($errors->has_errors()) {
            return $errors;
        }
        if (!$user instanceof \WP_User) {
            return new \WP_Error('invalidcombo', \__(self::NO_ACCOUNT));
        }
        if (!\apply_filters('send_retrieve_password_email', true, $user->user_login, $user)) {
            return true;
        }
        $key = \get_password_reset_key($user);
        if ($key instanceof \WP_Error) {
            return $key;
        }
        $notice = Mailer::noticesFor(Runtime::current()->site)->passwordReset($user->user_login, $user->user_email, self::resetLink($user, $key), (string) (Runtime::current()->request?->remoteAddress ?? ''));
        $title = \apply_filters('retrieve_password_title', $notice->subject, $user->user_login, $user);
        $message = \apply_filters('retrieve_password_message', $notice->body, $key, $user->user_login, $user);
        $email = (array) \apply_filters('retrieve_password_notification_email', ['to' => $user->user_email, 'subject' => $title, 'message' => $message, 'headers' => ''], $key, $user->user_login, $user);
        if (!\wp_mail($email['to'] ?? $user->user_email, \wp_specialchars_decode((string) ($email['subject'] ?? $title)), (string) ($email['message'] ?? $message), $email['headers'] ?? '')) {
            return new \WP_Error('retrieve_password_email_failure', \__('<strong>Error:</strong> The email could not be sent. Your site may not be correctly configured to send emails.'));
        }
        return true;
    }

    /** A new password for a user, announced before (password_reset) and after (after_password_reset). */
    public static function resetPassword(\WP_User $user, string $password): void
    {
        \do_action('password_reset', $user, $password);
        \wp_set_password($password, $user->ID);
        \update_user_meta($user->ID, 'default_password_nag', false);
        \do_action('after_password_reset', $user, $password);
    }

    /**
     * A new account for a login and an email, with a generated password and
     * the nag to change it: its id, or every refusal at once (the name
     * empty, illegal, taken or barred; the email empty, malformed or taken;
     * whatever register_post and registration_errors add).
     */
    public static function registerNewUser(string $login, string $email): int|\WP_Error
    {
        $errors = new \WP_Error();
        $name = \sanitize_user($login);
        $email = (string) \apply_filters('user_registration_email', $email);
        $nameError = match (true) {
            $name === '' => ['empty_username', '<strong>Error:</strong> Please enter a username.'],
            !\validate_username($login) => ['invalid_username', '<strong>Error:</strong> This username is invalid because it uses illegal characters. Please enter a valid username.'],
            (bool) \username_exists($name) => ['username_exists', '<strong>Error:</strong> This username is already registered. Please choose another one.'],
            in_array(strtolower($name), array_map('strtolower', (array) \apply_filters('illegal_user_logins', [])), true) => ['invalid_username', '<strong>Error:</strong> Sorry, that username is not allowed.'],
            default => null,
        };
        if ($nameError !== null) {
            $errors->add($nameError[0], \__($nameError[1]));
            $name = $nameError[0] === 'invalid_username' ? '' : $name;
        }
        $emailError = match (true) {
            $email === '' => ['empty_email', \__('<strong>Error:</strong> Please type your email address.')],
            !\is_email($email) => ['invalid_email', \__('<strong>Error:</strong> The email address is not correct.')],
            (bool) \email_exists($email) => ['email_exists', sprintf(\__('<strong>Error:</strong> This email address is already registered. <a href="%s">Log in</a> with this address or choose another one.'), \wp_login_url())],
            default => null,
        };
        if ($emailError !== null) {
            $errors->add(...$emailError);
            $email = $emailError[0] === 'invalid_email' ? '' : $email;
        }
        \do_action('register_post', $name, $email, $errors);
        $errors = \apply_filters('registration_errors', $errors, $name, $email);
        if ($errors->has_errors()) {
            return $errors;
        }
        $id = \wp_create_user($name, \wp_generate_password(12, false), $email);
        if (!is_int($id) || $id < 1) {
            return new \WP_Error('registerfail', sprintf(\__('<strong>Error:</strong> Could not register you&hellip; please contact the <a href="mailto:%s">site admin</a>!'), \get_option('admin_email')));
        }
        \update_user_meta($id, 'default_password_nag', true);
        \do_action('register_new_user', $id);
        return $id;
    }

    /**
     * A new account announced: to the site's address (unless only the user
     * is told), then to the user with a link to set their password (unless
     * only the site is, or the old call shape asks for nobody).
     */
    public static function newUserNotification(int $userId, mixed $deprecated, string $notify): void
    {
        $user = \get_userdata($userId);
        if (!in_array($notify, ['user', 'admin', 'both', ''], true) || !$user instanceof \WP_User) {
            return;
        }
        $site = \wp_specialchars_decode((string) \get_option('blogname'), ENT_QUOTES);
        if ($notify !== 'user' && \apply_filters('wp_send_new_user_notification_to_admin', true, $user)) {
            $message = sprintf(\__('New user registration on your site %s:'), $site) . "\r\n\r\n" . sprintf(\__('Username: %s'), $user->user_login) . "\r\n\r\n" . sprintf(\__('Email: %s'), $user->user_email) . "\r\n";
            $mail = (array) \apply_filters('wp_new_user_notification_email_admin', ['to' => \get_option('admin_email'), 'subject' => \__('[%s] New User Registration'), 'message' => $message, 'headers' => ''], $user, $site);
            \wp_mail($mail['to'] ?? '', \wp_specialchars_decode(sprintf((string) ($mail['subject'] ?? ''), $site)), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
        }
        if ($notify === 'admin' || (empty($deprecated) && $notify === '') || !\apply_filters('wp_send_new_user_notification_to_user', true, $user)) {
            return;
        }
        $key = \get_password_reset_key($user);
        if ($key instanceof \WP_Error) {
            return;
        }
        $message = sprintf(\__('Username: %s'), $user->user_login) . "\r\n\r\n" . \__('To set your password, visit the following address:') . "\r\n\r\n" . self::resetLink($user, $key) . "\r\n\r\n" . \wp_login_url() . "\r\n";
        $mail = (array) \apply_filters('wp_new_user_notification_email', ['to' => $user->user_email, 'subject' => \__('[%s] Login Details'), 'message' => $message, 'headers' => ''], $user, $site);
        \wp_mail($mail['to'] ?? '', \wp_specialchars_decode(sprintf((string) ($mail['subject'] ?? ''), $site)), (string) ($mail['message'] ?? ''), $mail['headers'] ?? '');
    }

    /** The link that opens the password form for a user's key. */
    private static function resetLink(\WP_User $user, string $key): string
    {
        return \network_site_url('wp-login.php?login=' . rawurlencode($user->user_login) . "&key={$key}&action=rp", 'login');
    }
}
