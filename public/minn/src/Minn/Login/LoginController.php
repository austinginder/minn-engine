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
        return Response::html($this->render($request, ''));
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

    private function render(Request $request, string $error): string
    {
        return LoginForm::render(
            (string) ($this->site->option('blogname') ?? 'Site'),
            $this->permalinks->url('/wp-login.php'),
            (string) ($request->query('redirect_to') ?? ''),
            $error,
        );
    }
}
