<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Runtime\Runtime;

/**
 * The sign-in as plugins see it, when they are loaded. The credentials go
 * through the reference's authenticate chain, so a plugin may refuse a
 * sign-in (a breached password, a second factor, a locked account) or
 * accept one the password alone would not; wp_login_failed is the chain's
 * own report. wp_login follows a good sign-in and wp_logout a sign-out.
 * The engine's default refusals stay one vague sentence; a plugin's is
 * shown in its own words, through login_errors.
 */
final readonly class LoginHooks
{
    /** The chain's own refusals, which the sign-in page words as one. */
    private const DEFAULT_REFUSALS = ['empty_username', 'empty_password', 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed'];

    public function __construct(
        private Users $users,
    ) {
    }

    /** Whether the chain can be asked: only with plugins loaded. */
    public function available(): bool
    {
        return Runtime::booted();
    }

    /**
     * The credentials through wp_authenticate and the authenticate filters,
     * as the reference's sign-in runs them: the user, or the refusal as
     * [code, message].
     *
     * @return UserRecord|array{0: string, 1: string}
     */
    public function authenticate(string $login, string $password): UserRecord|array
    {
        \do_action_ref_array('wp_authenticate', [&$login, &$password]);
        $result = \wp_authenticate($login, $password);
        if ($result instanceof \WP_User) {
            return $this->users->find((int) $result->ID) ?? ['authentication_failed', ''];
        }
        return $result instanceof \WP_Error
            ? [(string) $result->get_error_code(), (string) $result->get_error_message()]
            : ['authentication_failed', ''];
    }

    /** The sentence the sign-in page shows for a refusal, as plain text; null for the engine's own wording. */
    public function refusal(string $code, string $message): ?string
    {
        if (in_array($code, self::DEFAULT_REFUSALS, true) || trim($message) === '') {
            return null;
        }
        $shown = (string) \apply_filters('login_errors', $message);
        return trim(html_entity_decode(strip_tags($shown), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null;
    }

    /**
     * After a good sign-in. The chain read the user through the runtime,
     * which cached their meta before the new session was written, so the
     * cache is let go first: a plugin reading session_tokens on wp_login
     * (CleanTalk keeps the first session's address) must see the new one.
     */
    public function signedIn(UserRecord $user): void
    {
        if (Runtime::booted()) {
            \wp_cache_delete($user->id, 'user_meta');
            \do_action('wp_login', $user->login, new \WP_User($user->id));
        }
    }

    /** A sign-in page request arriving, as wp-login.php announces it: login_init, then login_form_{action}. */
    public function enter(string $action): void
    {
        if (Runtime::booted()) {
            \do_action('login_init');
            \do_action("login_form_{$action}");
        }
    }

    /**
     * What plugins put on the sign-in page, where the reference's page puts
     * it: the title (login_title), the head (login_enqueue_scripts, then
     * login_head, which prints the styles and scripts), the body classes
     * (login_body_class), the header's link and words (login_headerurl,
     * login_headertext), the message above the form (login_message), the
     * fields inside it (login_form, lostpassword_form or resetpass_form, as
     * the page is), and the footer (login_footer).
     *
     * @return array{title: string, head: string, bodyClass: string, headerUrl: string, headerText: string, message: string, form: string, footer: string}|array{}
     */
    public function page(string $action, string $title, string $siteName, string $homeUrl, ?UserRecord $user = null): array
    {
        if (!Runtime::booted()) {
            return [];
        }
        $printed = static function (string $hook, mixed ...$args): string {
            ob_start();
            \do_action($hook, ...$args);
            return (string) ob_get_clean();
        };
        // In the reference's order: the page's head and header, then the form's own hook (the sign-in's,
        // the lost-password form's, or the new-password form's, with its user), then the footer.
        $parts = ['title' => (string) \apply_filters('login_title', $title, 'Log In')];
        $parts['head'] = $printed('login_enqueue_scripts') . $printed('login_head');
        $parts['bodyClass'] = implode(' ', array_map('sanitize_html_class', (array) \apply_filters('login_body_class', ['login', 'no-js', 'login-action-' . $action], $action)));
        $parts['headerUrl'] = (string) \apply_filters('login_headerurl', $homeUrl);
        $parts['headerText'] = (string) \apply_filters('login_headertext', $siteName);
        $parts['message'] = (string) \apply_filters('login_message', '');
        $parts['form'] = match ($action) {
            'lostpassword', 'retrievepassword' => $printed('lostpassword_form'),
            'rp', 'resetpass' => $printed('resetpass_form', $user === null ? null : new \WP_User($user->id)),
            default => $printed('login_form'),
        };
        $parts['footer'] = $printed('login_footer');
        return $parts;
    }

    /** Where a good sign-in lands, as login_redirect says (the requested address and the user beside it). */
    public function landing(string $redirect, string $requested, UserRecord $user): string
    {
        return Runtime::booted() ? (string) \apply_filters('login_redirect', $redirect, $requested, new \WP_User($user->id)) : $redirect;
    }

    /** Where a sign-out lands, as logout_redirect says. */
    public function leaving(string $redirect, string $requested, int $userId): string
    {
        return Runtime::booted() ? (string) \apply_filters('logout_redirect', $redirect, $requested, new \WP_User($userId)) : $redirect;
    }

    /** After a sign-out ended the session. */
    public function signedOut(int $userId): void
    {
        if (Runtime::booted()) {
            \do_action('wp_logout', $userId);
        }
    }
}
