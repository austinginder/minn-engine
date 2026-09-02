<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\RestError;

/**
 * Judges a route's policy against the caller, with the reference's
 * refusals: a caller who is not signed in gets the policy's sign-in code
 * at 401, a bad nonce is always 403 rest_cookie_invalid_nonce, and a
 * signed-in caller who lacks a capability gets the refusal code at 403.
 * The edit-context policy is judged as well when the request asks for it.
 */
final readonly class PolicyGate
{
    public function __construct(private Caller $caller)
    {
    }

    /** The judge as the router takes it. */
    public function closure(): Closure
    {
        return fn (Policy $policy, Request $request, array $captures): mixed => $this->judge($policy, $request, $captures);
    }

    /**
     * Throws the refusal the policy names, or returns.
     *
     * @param array<string, string> $captures
     */
    public function judge(Policy $policy, Request $request, array $captures): void
    {
        match ($policy->access) {
            Access::Public => null,
            Access::SignedIn => $this->caller->require($policy->signIn, $policy->signInMessage),
            Access::Cap, Access::Floor => $this->capabilities($policy),
            Access::Own => $this->own($policy, $captures),
        };
        if ($policy->edit !== null && Context::of($request)->isEdit()) {
            $this->judge($policy->edit, $request, $captures);
        }
    }

    private function capabilities(Policy $policy): void
    {
        // The Minn Admin floor refuses an anonymous caller with the same rest_forbidden it refuses a capability with.
        $policy->access === Access::Floor
            ? $this->caller->require($policy->refuse, $policy->message)
            : $this->caller->require($policy->signIn, $policy->signInMessage);
        foreach ($policy->capabilities() as $capability) {
            if (!$this->caller->can($capability)) {
                throw new RestError($policy->refuse, $policy->message, 403);
            }
        }
    }

    /** @param array<string, string> $captures */
    private function own(Policy $policy, array $captures): void
    {
        $this->caller->require($policy->signIn, $policy->signInMessage);
        $id = (int) ($captures[(string) $policy->param] ?? 0);
        if ($policy->cap === null || !$this->caller->can($policy->cap, $id)) {
            throw new RestError($policy->refuse, $policy->message, 403);
        }
    }
}
