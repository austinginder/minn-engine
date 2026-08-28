<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Auth\AuthCookies;
use Minn\Auth\Authenticator;
use Minn\Auth\Sessions;
use Minn\Content\Site;
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
    ) {
    }

    #[Route(Method::Get, '/wp-login.php')]
    public function form(Request $request): Response
    {
        if ($request->query('action') === 'logout') {
            return $this->cookies->clear(Response::redirect($this->permalinks->url('/wp-login.php?loggedout=true'), 302));
        }
        return Response::html($this->render($request, ''));
    }

    #[Route(Method::Post, '/wp-login.php')]
    public function signIn(Request $request): Response
    {
        if ($request->query('action') === 'logout') {
            return $this->form($request);
        }
        $user = $this->authenticator->login((string) ($request->form['log'] ?? ''), (string) ($request->form['pwd'] ?? ''));
        if ($user === null) {
            return Response::html($this->render($request, 'Error: The username or password you entered is incorrect.'));
        }
        // Remember me extends the session from two days to fourteen.
        $expiration = time() + (!empty($request->form['rememberme']) ? 14 : 2) * self::DAY;
        $token = $this->sessions->create((int) $user['ID'], $expiration, $request->remoteAddress, (string) ($request->header('user-agent') ?? ''));
        $redirect = (string) ($request->form['redirect_to'] ?? '');
        if ($redirect === '') {
            $redirect = $this->permalinks->url('/minn-admin/');
        }
        return $this->cookies->attach(Response::redirect($redirect, 302), $user, $expiration, $token, $request->secure);
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
