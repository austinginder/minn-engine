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
            return $this->cookies->clear(Response::redirect($this->permalinks->url('/wp-login.php?loggedout=true'), 302));
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
            return Response::html($error, 500);
        }
        $id = (int) $user['ID'];
        $token = (string) ($this->users->meta($id, 'cove_login_token') ?? '');
        $minted = (int) ($this->users->meta($id, 'cove_login_token_time') ?? 0);
        if ($token === '' || time() - $minted > 15 * 60) {
            $this->users->deleteMeta($id, 'cove_login_token');
            $this->users->deleteMeta($id, 'cove_login_token_time');
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($error, 500);
        }
        if (!hash_equals($token, (string) $request->query('cove_login_token', ''))) {
            $this->throttle->recordFailure($request->remoteAddress);
            return Response::html($error, 500);
        }
        $this->users->deleteMeta($id, 'cove_login_token');
        $this->users->deleteMeta($id, 'cove_login_token_time');
        $expiration = time() + 14 * self::DAY;
        $session = $this->sessions->create($id, $expiration, $request->remoteAddress, (string) ($request->header('user-agent') ?? ''));
        return $this->cookies->attach(Response::redirect($this->permalinks->url('/wp-admin/'), 302), $user, $expiration, $session, $request->secure);
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
        $this->throttle->clear($request->remoteAddress);
        // Remember me extends the session from two days to fourteen.
        $expiration = time() + (!empty($request->form['rememberme']) ? 14 : 2) * self::DAY;
        $token = $this->sessions->create((int) $user['ID'], $expiration, $request->remoteAddress, (string) ($request->header('user-agent') ?? ''));
        $redirect = (string) ($request->form['redirect_to'] ?? '');
        if ($redirect === '') {
            $redirect = $this->permalinks->url('/minn-admin/');
        }
        return $this->cookies->attach(Response::redirect($redirect, 302), $user, $expiration, $token, $request->secure);
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
