<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\Authenticated;
use Minn\Auth\AuthFailure;
use Minn\Auth\Authenticator;
use Minn\Auth\Capabilities;
use Minn\Http\Request;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * Who is making this REST call. Resolved once from the cookie and nonce;
 * an anonymous or failed caller has id 0 and every capability check fails.
 */
final class Caller
{
    private Authenticated|AuthFailure|null $resolved = null;

    public function __construct(
        private readonly Request $request,
        private readonly Authenticator $authenticator,
        private readonly Capabilities $capabilities,
    ) {
    }

    /**
     * Settles the caller as a session already proven elsewhere: an
     * in-process request a plugin makes through rest_do_request() carries
     * no nonce, so it runs as whoever the outer request resolved.
     */
    public function resolveAs(Authenticated $session): void
    {
        $this->resolved = $session;
    }

    /** Settles the caller as nobody: plugin code (determine_current_user) signed the request out. */
    public function resolveAnonymous(): void
    {
        $this->resolved = AuthFailure::notLoggedIn();
    }

    /**
     * Whether a sign-in cookie vouches for this caller (or failed to, for
     * want of its nonce): a cookie's nonce vouches for its own user only.
     */
    public function cookieBound(): bool
    {
        $resolved = $this->resolve();
        return $resolved instanceof Authenticated ? $resolved->applicationPassword === null : $resolved->code === 'rest_cookie_invalid_nonce';
    }

    /** Settles the caller as one whose cookie's nonce does not vouch for the user plugin code named. */
    public function resolveInvalidNonce(): void
    {
        $this->resolved = AuthFailure::invalidNonce();
    }

    /** The session, or null for an anonymous or refused caller. */
    public function session(): ?Authenticated
    {
        $resolved = $this->resolve();
        return $resolved instanceof Authenticated ? $resolved : null;
    }

    /** The caller's user id, 0 when anonymous. */
    public function id(): int
    {
        return $this->session()?->id() ?? 0;
    }

    /**
     * Whether the caller holds a capability, on a post when given. With
     * plugins loaded the answer is WordPress's: the same mapping, then
     * map_meta_cap and user_has_cap, where role editors and lock plugins
     * have their say.
     */
    public function can(string $capability, ?int $postId = null): bool
    {
        if (Runtime::booted()) {
            return \user_can($this->id(), $capability, ...($postId === null ? [] : [$postId]));
        }
        return $this->capabilities->can($this->id(), $capability, $postId);
    }

    /**
     * Whether the caller can edit posts of some type shown in REST: what
     * the reference asks before showing statuses, block types and the
     * active theme. A plugin's types count once plugins are loaded.
     */
    public function editsAnyRestType(): bool
    {
        $caps = Runtime::booted()
            ? array_unique(array_map(static fn ($type) => (string) $type->cap->edit_posts, \get_post_types(['show_in_rest' => true], 'objects')))
            : ['edit_posts', 'edit_pages', 'edit_theme_options'];
        foreach ($caps as $cap) {
            if ($this->can($cap)) {
                return true;
            }
        }
        return false;
    }

    /** The capability engine. */
    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    /**
     * The session, or the reference's refusal: a bad nonce is always 403
     * rest_cookie_invalid_nonce; no identity is the caller-supplied error.
     */
    public function require(string $code = 'rest_not_logged_in', string $message = 'You are not currently logged in.', int $status = 401): Authenticated
    {
        $resolved = $this->resolve();
        if ($resolved instanceof Authenticated) {
            return $resolved;
        }
        if ($resolved->code === 'rest_cookie_invalid_nonce') {
            throw new RestError('rest_cookie_invalid_nonce', 'Cookie check failed', 403);
        }
        throw new RestError($code, $message, $status);
    }

    /**
     * The floor every Minn Admin route shares: a signed-in caller who can
     * edit posts, plus any further capability named, all refused with the
     * same rest_forbidden the reference uses. Returns the caller's id.
     */
    public function requireFloor(string ...$capabilities): int
    {
        $userId = $this->require('rest_forbidden', 'Sorry, you are not allowed to do that.')->id();
        $this->requireCap('edit_posts', ...$capabilities);
        return $userId;
    }

    /** 403 rest_forbidden unless the caller holds every capability named. */
    public function requireCap(string ...$capabilities): void
    {
        foreach ($capabilities as $capability) {
            if (!$this->can($capability)) {
                throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
            }
        }
    }

    /** 401 for an anonymous caller, 403 for one who is signed in but refused. */
    public function refuse(string $code, string $message): RestError
    {
        return new RestError($code, $message, $this->id() > 0 ? 403 : 401);
    }

    private function resolve(): Authenticated|AuthFailure
    {
        return $this->resolved ??= $this->authenticator->restFromRequest($this->request);
    }
}
