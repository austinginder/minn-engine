<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Auth\AuthCookies;
use Minn\Auth\LoginThrottle;
use Minn\Auth\Authenticator;
use Minn\Auth\Sessions;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Auth\Authenticated;
use Minn\Auth\Nonce;
use Minn\Support\Html;
use Minn\Auth\Password;
use Minn\Auth\PasswordReset;
use Minn\Mail\Mailer;
use Minn\Mail\Message;

/**
 * The wp-login.php surface: a GET form, a POST that verifies the password,
 * creates a session, and sets the real auth cookies, and a logout that
 * clears them. The cookies validate on the engine AND on WordPress.
 */
final readonly class LoginController
{
    private const DAY = 86400;

    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Authenticator $authenticator,
        private Sessions $sessions,
        private AuthCookies $cookies,
        private Users $users,
        private LoginThrottle $throttle,
        private PasswordReset $reset,
        private Mailer $mailer,
    ) {
    }

    #[Route(Method::Get, '/wp-login.php')]
    public function form(Request $request): Response
    {
        if ($request->query('action') === 'logout') {
            return $this->logout($request);
        }
        if ($request->has('user_id') && $request->has('cove_login_token')) {
            return $this->tokenLogin($request);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        switch ((string) $request->query('action', '')) {
            case 'lostpassword':
            case 'retrievepassword':
                $error = match ((string) $request->query('error', '')) {
                    'invalidkey' => 'Your password reset link appears to be invalid. Please request a new link below.',
                    'expiredkey' => 'Your password reset link has expired. Please request a new link below.',
                    default => '',
                };
                return Response::html(LoginForm::lostPassword($siteName, $this->permalinks->url('/wp-login.php?action=lostpassword'), $error, ''));
            case 'rp':
                return $this->openResetLink($request);
            case 'resetpass':
                [$user, $key] = $this->resetSession($request);
                if ($user === null) {
                    return Response::redirect($this->permalinks->url('/wp-login.php?action=lostpassword&error=invalidkey'), 302);
                }
                return Response::html(LoginForm::resetPassword($siteName, $this->permalinks->url('/wp-login.php?action=resetpass'), $key, (string) $user['user_login'], ''));
        }
        $message = match ((string) $request->query('checkemail', '')) {
            'confirm' => 'Check your email for the confirmation link, then visit the login page.',
            default => '',
        };
        if ($request->query('password') === 'changed') {
            $message = 'Your password has been changed.';
        }
        return Response::html($this->render($request, '', $message));
    }

    /** POST lostpassword mints a key and mails the link; POST resetpass saves the new password. Null for other actions. */
    private function lostPassword(Request $request): ?Response
    {
        $action = (string) $request->query('action', '');
        if ($action === 'resetpass') {
            return $this->savePassword($request);
        }
        if ($action !== 'lostpassword' && $action !== 'retrievepassword') {
            return null;
        }
        $wait = $this->throttle->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $login = trim((string) ($request->form['user_login'] ?? ''));
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        if ($login === '') {
            return Response::html(LoginForm::lostPassword($siteName, $this->permalinks->url('/wp-login.php?action=lostpassword'), 'Error: Please enter a username or email address.', ''));
        }
        $user = $this->users->findByLogin($login) ?? (str_contains($login, '@') ? $this->users->findByEmail($login) : null);
        if ($user === null) {
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html(LoginForm::lostPassword($siteName, $this->permalinks->url('/wp-login.php?action=lostpassword'), 'Error: There is no account with that username or email address.', ''));
        }
        $key = $this->reset->issue($user);
        $link = $this->permalinks->url('/wp-login.php') . '?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode((string) $user['user_login']);
        $this->mailer->send(new Message(
            [(string) $user['user_email']],
            '[' . $siteName . '] Password Reset',
            "Someone has requested a password reset for the following account:\n\n"
            . "Site Name: {$siteName}\n\nUsername: {$user['user_login']}\n\n"
            . "If this was a mistake, ignore this email and nothing will happen.\n\n"
            . "To reset your password, visit the following address:\n\n{$link}\n\n"
            . "This password reset request originated from the IP address {$request->remoteAddress}.\n",
        ));
        return Response::redirect($this->permalinks->url('/wp-login.php?checkemail=confirm'), 302);
    }

    /**
     * The link from the email: the key moves into a cookie scoped to
     * wp-login.php and the browser lands on the form without the key in
     * its address bar, as on the reference.
     */
    private function openResetLink(Request $request): Response
    {
        $key = (string) $request->query('key', '');
        $login = (string) $request->query('login', '');
        if ($key !== '' && $login !== '') {
            return Response::redirect($this->permalinks->url('/wp-login.php?action=rp'), 302)
                ->withCookie('wp-resetpass-' . $this->cookies->hash(), $login . ':' . $key, ['path' => '/wp-login.php', 'httponly' => true, 'secure' => $request->secure, 'samesite' => 'Lax']);
        }
        [$user, $cookieKey] = $this->resetSession($request);
        if ($user === null) {
            return Response::redirect($this->permalinks->url('/wp-login.php?action=lostpassword&error=invalidkey'), 302);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        return Response::html(LoginForm::resetPassword($siteName, $this->permalinks->url('/wp-login.php?action=resetpass'), $cookieKey, (string) $user['user_login'], ''));
    }

    /** The user and key from the reset cookie, when the key is still good. @return array{0: ?array, 1: string} */
    private function resetSession(Request $request): array
    {
        $cookie = (string) ($request->cookies['wp-resetpass-' . $this->cookies->hash()] ?? '');
        if (!str_contains($cookie, ':')) {
            return [null, ''];
        }
        [$login, $key] = explode(':', $cookie, 2);
        $user = $this->users->findByLogin($login);
        if ($user === null || !$this->reset->verify($user, $key)) {
            $this->throttle->recordFailure($request->remoteAddress);
            return [null, ''];
        }
        return [$user, $key];
    }

    private function savePassword(Request $request): Response
    {
        $wait = $this->throttle->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        [$user, $key] = $this->resetSession($request);
        $formKey = (string) ($request->form['rp_key'] ?? '');
        if ($user === null || !hash_equals($key, $formKey)) {
            return Response::redirect($this->permalinks->url('/wp-login.php?action=lostpassword&error=invalidkey'), 302);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        $pass1 = (string) ($request->form['pass1'] ?? '');
        $pass2 = (string) ($request->form['pass2'] ?? '');
        $action = $this->permalinks->url('/wp-login.php?action=resetpass');
        if ($pass1 === '') {
            return Response::html(LoginForm::resetPassword($siteName, $action, $key, (string) $user['user_login'], 'Error: The password cannot be empty.'));
        }
        if ($pass1 !== $pass2) {
            return Response::html(LoginForm::resetPassword($siteName, $action, $key, (string) $user['user_login'], 'Error: The passwords do not match.'));
        }
        $this->users->update((int) $user['ID'], ['user_pass' => Password::hash($pass1)]);
        $this->reset->clear($user);
        $this->sessions->destroyAll((int) $user['ID']);
        return $this->cookies->clear(Response::html(LoginForm::notice($siteName, 'Password Reset', 'Your password has been reset.', $this->permalinks->url('/wp-login.php'))))
            ->withCookie('wp-resetpass-' . $this->cookies->hash(), ' ', ['expires' => time() - 31536000, 'path' => '/wp-login.php', 'httponly' => true, 'secure' => $request->secure, 'samesite' => 'Lax']);
    }

    /**
     * The one-time login link `wp user login` prints (the captaincore
     * helper's contract): the token in user meta must match, be under
     * fifteen minutes old, and is spent on use. Any failure reads the same
     * so ids cannot be probed.
     */
    private function tokenLogin(Request $request): Response
    {
        $error = 'Invalid one-time login token. <a href="' . $this->permalinks->url('/wp-login.php') . '">Try signing in instead</a>?';
        $wait = $this->throttle->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $user = $this->users->find((int) $request->query('user_id', '0'));
        if ($user === null) {
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        $id = (int) $user['ID'];
        $token = (string) ($this->users->meta($id, 'cove_login_token') ?? '');
        $minted = (int) ($this->users->meta($id, 'cove_login_token_time') ?? 0);
        if ($token === '' || time() - $minted > 15 * 60) {
            $this->users->deleteMeta($id, 'cove_login_token');
            $this->users->deleteMeta($id, 'cove_login_token_time');
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        // The meta holds the token's hash (a database read alone yields no link).
        if (!hash_equals($token, hash('sha256', (string) $request->query('cove_login_token', '')))) {
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        $this->users->deleteMeta($id, 'cove_login_token');
        $this->users->deleteMeta($id, 'cove_login_token_time');
        $expiration = time() + 14 * self::DAY;
        $session = $this->sessions->create($id, $expiration, $request->remoteAddress, (string) ($request->header('user-agent') ?? ''));
        return $this->cookies->attach(Response::redirect($this->permalinks->url('/wp-admin/'), 302), $user, $expiration, $session, $request->secure, persistent: true);
    }

    #[Route(Method::Post, '/wp-login.php')]
    public function signIn(Request $request): Response
    {
        if ($request->query('action') === 'logout') {
            return $this->form($request);
        }
        $reset = $this->lostPassword($request);
        if ($reset !== null) {
            return $reset;
        }
        $wait = $this->throttle->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $user = $this->authenticator->login((string) ($request->form['log'] ?? ''), (string) ($request->form['pwd'] ?? ''));
        if ($user === null) {
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($this->render($request, 'Error: The username or password you entered is incorrect.'));
        }
        // Remember me extends the session from two days to fourteen and keeps
        // the cookie past the browser session; the failure counter is left to
        // lapse, so a sign-in to one account cannot reset guesses at another.
        $remember = !empty($request->form['rememberme']);
        $expiration = time() + ($remember ? 14 : 2) * self::DAY;
        $token = $this->sessions->create((int) $user['ID'], $expiration, $request->remoteAddress, (string) ($request->header('user-agent') ?? ''));
        $redirect = $this->safeRedirect((string) ($request->form['redirect_to'] ?? ''));
        return $this->cookies->attach(Response::redirect($redirect, 302), $user, $expiration, $token, $request->secure, $remember);
    }

    /**
     * Only this site's own URLs are sign-in destinations: a path, or an
     * absolute URL on the home host. Anything else lands in the admin.
     */
    private function safeRedirect(string $target): string
    {
        $home = $this->permalinks->url('');
        if ($target !== '' && str_starts_with($target, '/') && !str_starts_with($target, '//') && !str_starts_with($target, '/\\')) {
            return $home . $target;
        }
        if ($target !== '' && parse_url($target, PHP_URL_HOST) !== null
            && strcasecmp((string) parse_url($target, PHP_URL_HOST), (string) parse_url($home, PHP_URL_HOST)) === 0
            && in_array(strtolower((string) parse_url($target, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $target;
        }
        return $this->permalinks->url('/minn-admin/');
    }

    /**
     * Logging out ends the server-side session as well as the cookies, and
     * needs the session's own nonce so a stray link cannot do it; without
     * the nonce the reader is asked first.
     */
    private function logout(Request $request): Response
    {
        $session = $this->authenticator->session($request->cookies);
        $signedOut = $this->cookies->clear(Response::redirect($this->permalinks->url('/wp-login.php?loggedout=true'), 302));
        if (!$session instanceof Authenticated) {
            return $signedOut;
        }
        $nonce = (string) ($request->query('_wpnonce') ?? $request->form['_wpnonce'] ?? '');
        if (!Nonce::verify($nonce, $session->id(), $session->token, 'log-out')) {
            $link = $this->permalinks->url('/wp-login.php?action=logout&_wpnonce=' . Nonce::create($session->id(), $session->token, 'log-out'));
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Log Out</title></head><body>'
                . '<p>You are attempting to log out of ' . Html::esc((string) ($this->site->option('blogname') ?? 'this site')) . '.</p>'
                . '<p>Do you really want to <a href="' . Html::attr($link) . '">log out</a>?</p></body></html>',
            );
        }
        $this->sessions->destroy($session->id(), $session->token);
        return $signedOut;
    }

    /** A guessed-at address waits out the window; the page says so and the status lets tooling see it. */
    private function tooManyAttempts(Request $request, int $wait): Response
    {
        $minutes = max(1, (int) ceil($wait / 60));
        return Response::html($this->render($request, "Error: Too many failed sign-in attempts. Try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.'), 429)
            ->withHeader('Retry-After', (string) $wait);
    }

    private function render(Request $request, string $error, string $message = ''): string
    {
        return LoginForm::render(
            (string) ($this->site->option('blogname') ?? 'Site'),
            $this->permalinks->url('/wp-login.php'),
            (string) ($request->query('redirect_to') ?? ''),
            $error,
            $message,
        );
    }
}
