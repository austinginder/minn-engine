<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;
use Minn\Http\Request;
use Minn\Http\Response;

/**
 * The door itself: what a sign-in surface needs beyond checking a
 * password. Sessions are minted here, the three cookies ride out on the
 * response, and the failure counter behind the throttle is kept in one
 * place with the session store and the cookie jar it protects.
 */
final readonly class SignIn
{
    public function __construct(
        private AuthCookies $cookies,
        private LoginThrottle $throttle,
    ) {
    }

    /** Seconds this address must wait before another attempt, or null when it may try now. */
    public function retryAfter(string $address): ?int
    {
        return $this->throttle->retryAfter($address);
    }

    /** Counts one failed attempt from an address. */
    public function recordFailure(string $address): void
    {
        $this->throttle->recordFailure($address);
    }

    /** The cookie-name hash the sign-in cookies, the postpass cookie, and the reset cookie share. */
    public function hash(): string
    {
        return $this->cookies->hash();
    }

    /** Signs a user in for two days: a new session, and the response with the cookies attached for the browser session. */
    public function establish(Response $response, UserRecord $user, Request $request): Response
    {
        return $this->open($response, $user, $request, 2);
    }

    /** Signs a user in for fourteen days, the cookies kept past the browser session: "remember me", and the one-time login link. */
    public function remember(Response $response, UserRecord $user, Request $request): Response
    {
        return $this->open($response, $user, $request, 14);
    }

    /** A session longer than the two-day default is one to keep past the browser session, as the reference's remember-me does. */
    private function open(Response $response, UserRecord $user, Request $request, int $days): Response
    {
        // The cookies settled as the reference settles them, its hooks told and asked (AuthCookies::settle).
        return $this->cookies->attach($response, $this->cookies->settle($user, $days, $request->secure, $request->secure));
    }

    /** Ends one session and clears the cookies from the response, unless send_auth_cookies says not to (AuthCookies::clearing). */
    public function end(Response $response, Authenticated $session): Response
    {
        \WP_Session_Tokens::get_instance($session->id())->destroy($session->token);
        return $this->cookies->clearing() ? $this->cookies->clear($response) : $response;
    }

    /** Ends every session of a user (a password reset) and clears the cookies from the response. */
    public function endAll(Response $response, int $userId): Response
    {
        \WP_Session_Tokens::get_instance($userId)->destroy_all();
        return $this->cookies->clear($response);
    }

    /** The response with the sign-in cookies cleared. */
    public function clear(Response $response): Response
    {
        return $this->cookies->clear($response);
    }
}
