<?php

declare(strict_types=1);

namespace Minn\Auth;

/**
 * Why a request is not authenticated, as the reference's error code: a
 * missing or invalid cookie is rest_not_logged_in (the request is simply
 * anonymous); a good cookie with a bad nonce is rest_cookie_invalid_nonce.
 */
final readonly class AuthFailure
{
    public function __construct(public string $code)
    {
    }

    public static function notLoggedIn(): self
    {
        return new self('rest_not_logged_in');
    }

    public static function invalidNonce(): self
    {
        return new self('rest_cookie_invalid_nonce');
    }
}
