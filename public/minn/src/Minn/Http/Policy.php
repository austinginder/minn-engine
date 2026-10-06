<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * What a route requires of its caller, as data on the route: the router
 * enforces it before the handler runs, so a grep over the attributes is
 * the authorization surface. The two refusals are the reference's: a
 * caller who is not signed in gets the sign-in code (401), a signed-in
 * caller who lacks the capability gets the refusal code (403); how a bad
 * nonce is answered belongs to whoever judges the policy. A policy that
 * names a subject has the record looked up first, and answers the
 * record's 404 before either refusal, which is the reference's order.
 *
 * Written inline in the attribute with `new`, which is what an attribute
 * argument allows: `policy: new Policy(Access::Cap, 'upload_files',
 * refuse: 'rest_cannot_create', message: '...')`.
 */
final readonly class Policy
{
    /**
     * @param list<string> $caps further capabilities every one of which the caller must hold
     * @param string|null $param the pattern capture holding the object id an Own policy judges, or the subject's id
     * @param Policy|null $edit a policy judged as well when the request asks for the edit context
     * @param Subject|null $subject the record the capture names, looked up before the caller is judged
     * @param string|null $missing the 404 code when the subject does not exist; the subject's own when null
     * @param string|null $missingMessage its message; the subject's own when null
     * @param int $signInStatus the status a signed-out caller is refused with (the reference answers some routes' signed-out writes 404, not 401)
     * @param string|null $verb what a Type or Taxonomy route does (create, edit, delete), judged with the type's or taxonomy's own capabilities
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
        public ?Subject $subject = null,
        public ?string $missing = null,
        public ?string $missingMessage = null,
        public int $signInStatus = 401,
        public ?string $verb = null,
    ) {
    }

    /** Whether the route is open to anyone with nothing to look up, so the gate need not run at all. */
    public function isPublic(): bool
    {
        return $this->access === Access::Public && $this->edit === null && $this->subject === null;
    }

    /** The 404 code a missing subject earns. */
    public function missingCode(): string
    {
        return $this->missing ?? $this->subject?->missingCode() ?? 'rest_no_route';
    }

    /** The 404 message a missing subject earns. */
    public function missingText(): string
    {
        return $this->missingMessage ?? $this->subject?->missingMessage() ?? 'No route was found matching the URL and request method.';
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

    /** The policy as data, for the route catalogue. @return array<string, mixed> */
    public function toArray(): array
    {
        $row = ['access' => $this->access->name, 'describe' => $this->describe()];
        if ($this->cap !== null) {
            $row['cap'] = $this->cap;
        }
        if ($this->caps !== []) {
            $row['caps'] = $this->caps;
        }
        if ($this->param !== null) {
            $row['param'] = $this->param;
        }
        if ($this->subject !== null) {
            $row['subject'] = $this->subject->name;
            $row['missing'] = ['code' => $this->missingCode(), 'message' => $this->missingText()];
        }
        if ($this->access !== Access::Public && $this->access !== Access::Type) {
            $row['signIn'] = ['code' => $this->signIn, 'message' => $this->signInMessage];
            $row['refuse'] = ['code' => $this->refuse, 'message' => $this->message];
        }
        if ($this->edit !== null) {
            $row['edit'] = $this->edit->toArray();
        }
        return $row;
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
            Access::Type => 'declared type {' . $this->param . '}',
            Access::Taxonomy => 'registered taxonomy {' . $this->param . '}',
        };
        if ($this->subject !== null) {
            $line .= '; ' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $this->subject->name)) . " {{$this->param}} must exist";
        }
        return $this->edit === null ? $line : "{$line}; edit context: " . $this->edit->describe();
    }
}
