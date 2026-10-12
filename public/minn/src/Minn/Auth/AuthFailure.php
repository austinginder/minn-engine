<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * Why a request is not authenticated, as the reference's error code: a
 * missing or invalid cookie, or a good one sent without a nonce, is
 * rest_not_logged_in (the request is simply anonymous); a good cookie with
 * a nonce that does not verify is rest_cookie_invalid_nonce. The reason
 * tells a good cookie sent without its nonce apart: that request is
 * nobody, whoever plugin code then names.
 */
final readonly class AuthFailure
{
    public const COOKIE_WITHOUT_NONCE = 'a sign-in cookie without its nonce';

    public function __construct(public string $code, public string $reason = '')
    {
    }

    /** A good sign-in cookie sent without the nonce that would let it vouch for its user. */
    public static function cookieWithoutNonce(): self
    {
        return new self('rest_not_logged_in', self::COOKIE_WITHOUT_NONCE);
    }

    /** No usable session. */
    public static function notLoggedIn(): self
    {
        return new self('rest_not_logged_in');
    }

    /** A session whose nonce did not verify. */
    public static function invalidNonce(): self
    {
        return new self('rest_cookie_invalid_nonce');
    }
}
