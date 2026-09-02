<?php

declare(strict_types=1);

namespace Minn\Login;

use Minn\Auth\Authenticator;
use Minn\Auth\SignIn;
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
use Minn\Auth\PortableHash;

/**
 * Signing in. The page people see is /minn-admin/login: the form, the
 * lost-password and reset flows, and logout all live there and link there.
 * /wp-login.php stays as the address tooling knows (one-time login links,
 * uptime probes, scripted sign-ins) and answers in place with the same
 * shapes; a bare GET of it sends a browser to the clean page. Sessions
 * and cookies validate on the engine AND on WordPress either way.
 */
final readonly class LoginController
{
    private const DAY = 86400;

    public const PATH = '/minn-admin/login';

    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Authenticator $authenticator,
        private SignIn $signIn,
        private Users $users,
        private PasswordReset $reset,
        private Mailer $mailer,
    ) {
    }

    /** Clean path segment => the action wp-login.php spells with ?action=. */
    private const SEGMENTS = ['lost-password' => 'lostpassword', 'reset' => 'rp', 'logout' => 'logout'];

    /** The sign-in, lost-password, reset, and logout pages. */
    #[Route(Method::Get, self::PATH)]
    #[Route(Method::Get, self::PATH . '/{segment:lost-password|reset|logout}')]
    #[Route(Method::Get, '/wp-login.php')]
    public function form(Request $request): Response
    {
        if ($request->path === '/wp-login.php' && $request->query === []) {
            return Response::redirect($this->permalinks->url(self::PATH), 302);
        }
        if ($this->action($request) === 'logout') {
            return $this->logout($request);
        }
        if ($request->has('user_id') && $request->has('cove_login_token')) {
            return $this->tokenLogin($request);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        switch ($this->action($request)) {
            case 'lostpassword':
            case 'retrievepassword':
                $error = match ((string) $request->query('error', '')) {
                    'invalidkey' => 'Your password reset link appears to be invalid. Please request a new link below.',
                    'expiredkey' => 'Your password reset link has expired. Please request a new link below.',
                    default => '',
                };
                return Response::html(LoginForm::lostPassword($siteName, $this->actionUrl($request, 'lostpassword'), $error, ''));
            case 'rp':
                return $this->openResetLink($request);
            case 'resetpass':
                [$user, $key] = $this->resetSession($request);
                if ($user === null) {
                    return Response::redirect($this->actionUrl($request, 'lostpassword', 'error=invalidkey'), 302);
                }
                return Response::html(LoginForm::resetPassword($siteName, $this->actionUrl($request, 'resetpass'), $key, $user->login, ''));
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
        $action = $this->action($request);
        if ($action === 'resetpass') {
            return $this->savePassword($request);
        }
        if ($action === 'postpass') {
            // The reference hashes the password into a ten-day cookie and sends the reader back; a wrong password simply stays locked.
            $back = $this->safeRedirect((string) ($request->form['redirect_to'] ?? $request->header('referer') ?? ''));
            return Response::redirect($back === $this->permalinks->url('/minn-admin/') ? $this->permalinks->url('/') : $back, 302)
                ->withCookie('wp-postpass_' . $this->signIn->hash(), PortableHash::hash((string) ($request->form['post_password'] ?? '')), ['expires' => time() + 10 * self::DAY, 'path' => '/', 'secure' => $request->secure, 'samesite' => 'Lax']);
        }
        if ($action !== 'lostpassword' && $action !== 'retrievepassword') {
            return null;
        }
        $wait = $this->signIn->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $login = trim((string) ($request->form['user_login'] ?? ''));
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        if ($login === '') {
            return Response::html(LoginForm::lostPassword($siteName, $this->actionUrl($request, 'lostpassword'), 'Error: Please enter a username or email address.', ''));
        }
        $user = $this->users->findByLogin($login) ?? (str_contains($login, '@') ? $this->users->findByEmail($login) : null);
        if ($user === null) {
            $this->signIn->recordFailure($request->remoteAddress);
            return Response::html(LoginForm::lostPassword($siteName, $this->actionUrl($request, 'lostpassword'), 'Error: There is no account with that username or email address.', ''));
        }
        $key = $this->reset->issue($user);
        $link = $this->actionUrl($request, 'rp', 'key=' . rawurlencode($key) . '&login=' . rawurlencode($user->login));
        $this->mailer->send(
            Mailer::noticesFor($this->site)->passwordReset($user->login, $user->email, $link, $request->remoteAddress),
        );
        return Response::redirect($this->permalinks->url($this->base($request) . '?checkemail=confirm'), 302);
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
            return Response::redirect($this->actionUrl($request, 'rp'), 302)
                ->withCookie('wp-resetpass-' . $this->signIn->hash(), $login . ':' . $key, ['path' => $this->base($request), 'httponly' => true, 'secure' => $request->secure, 'samesite' => 'Lax']);
        }
        [$user, $cookieKey] = $this->resetSession($request);
        if ($user === null) {
            return Response::redirect($this->actionUrl($request, 'lostpassword', 'error=invalidkey'), 302);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        return Response::html(LoginForm::resetPassword($siteName, $this->actionUrl($request, 'resetpass'), $cookieKey, $user->login, ''));
    }

    /** The user and key from the reset cookie, when the key is still good. @return array{0: ?array, 1: string} */
    private function resetSession(Request $request): array
    {
        $cookie = (string) ($request->cookies['wp-resetpass-' . $this->signIn->hash()] ?? '');
        if (!str_contains($cookie, ':')) {
            return [null, ''];
        }
        [$login, $key] = explode(':', $cookie, 2);
        $user = $this->users->findByLogin($login);
        if ($user === null || !$this->reset->verify($user, $key)) {
            $this->signIn->recordFailure($request->remoteAddress);
            return [null, ''];
        }
        return [$user, $key];
    }

    private function savePassword(Request $request): Response
    {
        $wait = $this->signIn->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        [$user, $key] = $this->resetSession($request);
        $formKey = (string) ($request->form['rp_key'] ?? '');
        if ($user === null || !hash_equals($key, $formKey)) {
            return Response::redirect($this->actionUrl($request, 'lostpassword', 'error=invalidkey'), 302);
        }
        $siteName = (string) ($this->site->option('blogname') ?? 'Site');
        $pass1 = (string) ($request->form['pass1'] ?? '');
        $pass2 = (string) ($request->form['pass2'] ?? '');
        $action = $this->actionUrl($request, 'resetpass');
        if ($pass1 === '') {
            return Response::html(LoginForm::resetPassword($siteName, $action, $key, $user->login, 'Error: The password cannot be empty.'));
        }
        if ($pass1 !== $pass2) {
            return Response::html(LoginForm::resetPassword($siteName, $action, $key, $user->login, 'Error: The passwords do not match.'));
        }
        $this->users->update($user->id, ['user_pass' => Password::hash($pass1)]);
        $this->reset->clear($user);
        return $this->signIn->endAll(Response::html(LoginForm::notice($siteName, 'Password Reset', 'Your password has been reset.', $this->permalinks->url($this->base($request)))), $user->id)
            ->withCookie('wp-resetpass-' . $this->signIn->hash(), ' ', ['expires' => time() - 31536000, 'path' => $this->base($request), 'httponly' => true, 'secure' => $request->secure, 'samesite' => 'Lax']);
    }

    /**
     * The one-time login link `wp user login` prints (the captaincore
     * helper's contract): the token in user meta must match, be under
     * fifteen minutes old, and is spent on use. Any failure reads the same
     * so ids cannot be probed.
     */
    private function tokenLogin(Request $request): Response
    {
        $error = 'Invalid one-time login token. <a href="' . $this->permalinks->url($this->base($request)) . '">Try signing in instead</a>?';
        $wait = $this->signIn->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $user = $this->users->find((int) $request->query('user_id', '0'));
        if ($user === null) {
            $this->signIn->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        $id = $user->id;
        $token = (string) ($this->users->meta($id, 'cove_login_token') ?? '');
        $minted = (int) ($this->users->meta($id, 'cove_login_token_time') ?? 0);
        if ($token === '' || time() - $minted > 15 * 60) {
            $this->users->deleteMeta($id, 'cove_login_token');
            $this->users->deleteMeta($id, 'cove_login_token_time');
            $this->signIn->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        // The meta holds the raw token (the helper mu-plugin's own format);
        // a hash is still accepted for a link minted before the change.
        $provided = (string) $request->query('cove_login_token', '');
        if (!hash_equals($token, $provided) && !hash_equals($token, hash('sha256', $provided))) {
            $this->signIn->recordFailure($request->remoteAddress);
            return Response::html($error, 403);
        }
        $this->users->deleteMeta($id, 'cove_login_token');
        $this->users->deleteMeta($id, 'cove_login_token_time');
        return $this->signIn->remember(Response::redirect($this->permalinks->url('/minn-admin/'), 302), $user, $request);
    }

    /** Handles the posted form for each of those pages. */
    #[Route(Method::Post, self::PATH)]
    #[Route(Method::Post, self::PATH . '/{segment:lost-password|reset|logout}')]
    #[Route(Method::Post, '/wp-login.php')]
    public function signIn(Request $request): Response
    {
        if ($this->action($request) === 'logout') {
            return $this->form($request);
        }
        $reset = $this->lostPassword($request);
        if ($reset !== null) {
            return $reset;
        }
        $wait = $this->signIn->retryAfter($request->remoteAddress);
        if ($wait !== null) {
            return $this->tooManyAttempts($request, $wait);
        }
        $user = $this->authenticator->login((string) ($request->form['log'] ?? ''), (string) ($request->form['pwd'] ?? ''));
        if ($user === null) {
            $this->signIn->recordFailure($request->remoteAddress);
            return Response::html($this->render($request, 'Error: The username or password you entered is incorrect.'));
        }
        // Remember me extends the session from two days to fourteen and keeps
        // the cookie past the browser session; the failure counter is left to
        // lapse, so a sign-in to one account cannot reset guesses at another.
        $remember = !empty($request->form['rememberme']);
        $redirect = $this->safeRedirect((string) ($request->form['redirect_to'] ?? ''));
        $response = Response::redirect($redirect, 302);
        return $remember ? $this->signIn->remember($response, $user, $request) : $this->signIn->establish($response, $user, $request);
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
        $signedOut = Response::redirect($this->permalinks->url($this->base($request) . '?loggedout=true'), 302);
        if (!$session instanceof Authenticated) {
            return $this->signIn->clear($signedOut);
        }
        $nonce = (string) ($request->query('_wpnonce') ?? $request->form['_wpnonce'] ?? '');
        if (!Nonce::verify($nonce, $session->id(), $session->token, 'log-out')) {
            $link = $this->actionUrl($request, 'logout', '_wpnonce=' . Nonce::create($session->id(), $session->token, 'log-out'));
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Log Out</title></head><body>'
                . '<p>You are attempting to log out of ' . Html::esc((string) ($this->site->option('blogname') ?? 'this site')) . '.</p>'
                . '<p>Do you really want to <a href="' . Html::attr($link) . '">log out</a>?</p></body></html>',
            );
        }
        return $this->signIn->end($signedOut, $session);
    }

    /**
     * The action asked for: a clean path segment (/minn-admin/login/reset)
     * or wp-login.php's ?action=. The reset flow's two halves share the
     * "reset" segment; a POST there saves the password.
     */
    private function action(Request $request): string
    {
        $segment = substr($request->path, strlen(self::PATH) + 1);
        if (str_starts_with($request->path, self::PATH . '/') && isset(self::SEGMENTS[$segment])) {
            $action = self::SEGMENTS[$segment];
            return $action === 'rp' && $request->method === Method::Post ? 'resetpass' : $action;
        }
        return (string) $request->query('action', '');
    }

    /** An action's address on the page this request came in on: a path under the clean page, a query on wp-login.php. */
    private function actionUrl(Request $request, string $action, string $query = ''): string
    {
        if ($request->path === '/wp-login.php') {
            return $this->permalinks->url('/wp-login.php?action=' . $action . ($query === '' ? '' : '&' . $query));
        }
        $segment = array_search($action === 'resetpass' ? 'rp' : $action, self::SEGMENTS, true);
        return $this->permalinks->url(self::PATH . '/' . $segment . ($query === '' ? '' : '?' . $query));
    }

    /** The sign-in page this request arrived at: the clean page, or wp-login.php for the tooling that still names it. */
    private function base(Request $request): string
    {
        return $request->path === '/wp-login.php' ? '/wp-login.php' : self::PATH;
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
            $this->permalinks->url($this->base($request)),
            (string) ($request->query('redirect_to') ?? ''),
            $error,
            $message,
            $this->actionUrl($request, 'lostpassword'),
        );
    }
}
