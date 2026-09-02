<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * What a route requires of its caller, as data on the route: the router
 * enforces it before the handler runs, so a grep over the attributes is
 * the authorization surface. The two refusals are the reference's: a
 * caller who is not signed in gets the sign-in code (401), a signed-in
 * caller who lacks the capability gets the refusal code (403); how a bad
 * nonce is answered belongs to whoever judges the policy.
 *
 * Written inline in the attribute with `new`, which is what an attribute
 * argument allows: `policy: new Policy(Access::Cap, 'upload_files',
 * refuse: 'rest_cannot_create', message: '...')`.
 */
final readonly class Policy
{
    /**
     * @param list<string> $caps further capabilities every one of which the caller must hold
     * @param string|null $param the pattern capture holding the object id an Own policy judges
     * @param Policy|null $edit a policy judged as well when the request asks for the edit context
     */
    public function __construct(
        public Access $access = Access::Public,
        public ?string $cap = null,
        public array $caps = [],
        public ?string $param = null,
        public string $signIn = 'rest_not_logged_in',
        public string $signInMessage = 'You are not currently logged in.',
        public string $refuse = 'rest_forbidden',
        public string $message = 'Sorry, you are not allowed to do that.',
        public ?Policy $edit = null,
    ) {
    }

    /** Whether the route is open to anyone, so the caller need not be resolved at all. */
    public function isPublic(): bool
    {
        return $this->access === Access::Public && $this->edit === null;
    }

    /**
     * Every capability the policy asks for by name, the one on the object
     * included, so a listing can show what a route takes.
     *
     * @return list<string>
     */
    public function capabilities(): array
    {
        $all = $this->access === Access::Floor ? ['edit_posts'] : [];
        if ($this->cap !== null) {
            $all[] = $this->cap;
        }
        return [...$all, ...$this->caps];
    }

    /** The policy in one line, for the route index and the docs. */
    public function describe(): string
    {
        $line = match ($this->access) {
            Access::Public => 'public',
            Access::SignedIn => 'signed in',
            Access::Cap => 'cap ' . implode(' + ', $this->capabilities()),
            Access::Floor => 'floor ' . implode(' + ', $this->capabilities()),
            Access::Own => "cap {$this->cap} on {{$this->param}}",
        };
        return $this->edit === null ? $line : "{$line}; edit context: " . $this->edit->describe();
    }
}
