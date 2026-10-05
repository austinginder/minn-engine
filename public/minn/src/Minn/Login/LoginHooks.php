<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Content\Site;
use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Runtime\Runtime;
use Minn\Support\Html;

/**
 * What the reference tells plugins about a sign-in, when plugins are loaded:
 * wp_login with the user after a good one, wp_login_failed with the reason
 * after a bad one (security and logging plugins read it), wp_logout with the
 * user id after a sign-out. A form with an empty field is turned away before
 * any of it, as there. The authenticate filter, through which a plugin may
 * refuse a sign-in, is not run yet (contracts/runtime.md).
 */
final readonly class LoginHooks
{
    public function __construct(
        private Users $users,
        private Site $site,
    ) {
    }

    /** After a sign-in attempt with both fields filled: the user signed in, or null. */
    public function attempted(string $login, ?UserRecord $user): void
    {
        if (!Runtime::booted()) {
            return;
        }
        if ($user !== null) {
            \do_action('wp_login', $user->login, new \WP_User($user->id));
            return;
        }
        \do_action('wp_login_failed', $login, $this->reason($login));
    }

    /** After a sign-out ended the session. */
    public function signedOut(int $userId): void
    {
        if (Runtime::booted()) {
            \do_action('wp_logout', $userId);
        }
    }

    /** The reference's refusal: nobody by that name or address, or the wrong password for someone. */
    private function reason(string $login): \WP_Error
    {
        $name = '<strong>' . Html::esc($login) . '</strong>';
        $lost = ' <a href="' . Html::attr(rtrim((string) ($this->site->option('siteurl') ?? ''), '/') . '/wp-login.php?action=lostpassword') . '">Lost your password?</a>';
        if ($this->users->findByLogin($login) !== null) {
            return new \WP_Error('incorrect_password', '<strong>Error:</strong> The password you entered for the username ' . $name . ' is incorrect.' . $lost);
        }
        if (!str_contains($login, '@')) {
            return new \WP_Error('invalid_username', '<strong>Error:</strong> The username ' . $name . ' is not registered on this site. If you are unsure of your username, try your email address instead.');
        }
        if ($this->users->findByEmail($login) !== null) {
            return new \WP_Error('incorrect_password', '<strong>Error:</strong> The password you entered for the email address ' . $name . ' is incorrect.' . $lost);
        }
        return new \WP_Error('invalid_email', 'Unknown email address. Check again or try your username.');
    }
}
