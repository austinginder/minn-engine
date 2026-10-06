<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\TypeCapabilities;
use Minn\Http\Access;
use Closure;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\RouteMiss;
use Minn\RestError;

/**
 * Judges a route's policy against the caller, with the reference's
 * refusals: a caller who is not signed in gets the policy's sign-in code
 * at 401, a bad nonce is always 403 rest_cookie_invalid_nonce, and a
 * signed-in caller who lacks a capability gets the refusal code at 403.
 * A subject the policy names is looked up first and answers its 404
 * before any of those. The edit-context policy is judged as well when
 * the request asks for it.
 */
final readonly class PolicyGate
{
    public function __construct(private Caller $caller, private Subjects $subjects, private Types $types)
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
        if ($policy->subject !== null) {
            $this->subject($policy, $captures);
        }
        match ($policy->access) {
            Access::Public => null,
            Access::SignedIn => $this->caller->require($policy->signIn, $policy->signInMessage, $policy->signInStatus),
            Access::Cap, Access::Floor => $this->capabilities($policy),
            Access::Own => $this->own($policy, $captures),
            Access::Type => $this->type($policy, $captures),
            Access::Taxonomy => $this->taxonomy($policy, $captures),
        };
        if ($policy->edit !== null && Context::of($request)->isEdit()) {
            $this->judge($policy->edit, $request, $captures);
        }
    }

    /**
     * The record's 404 when it does not exist. "me" names the caller, who
     * must then be signed in; the reference answers that with its plain
     * sign-in refusal whatever the route's own code is.
     *
     * @param array<string, string> $captures
     */
    private function subject(Policy $policy, array $captures): void
    {
        $raw = (string) ($captures[(string) $policy->param] ?? '');
        if ($raw === 'me') {
            $this->caller->require();
            return;
        }
        if ($policy->subject === null || !$this->subjects->exists($policy->subject, (int) $raw, $captures)) {
            throw new RestError($policy->missingCode(), $policy->missingText(), 404);
        }
    }

    /**
     * A {base} that names no declared type declines the route, so the next
     * one may take it. A write is judged with the type's own capabilities
     * (probe rest-plugin-caps): a missing post first (404), then the
     * caller (401 signed out, 403 refused).
     *
     * @param array<string, string> $captures
     */
    private function type(Policy $policy, array $captures): void
    {
        $slug = $this->types->slugForRestBase((string) ($captures['base'] ?? ''));
        if ($slug === null || !$this->types->isDeclared($slug)) {
            throw new RouteMiss();
        }
        $id = (int) ($captures['id'] ?? 0);
        [$code, $message, $capability] = match ($policy->verb) {
            'create' => ['rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.', TypeCapabilities::create($slug)],
            'edit' => ['rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 'edit_post'],
            'delete' => ['rest_cannot_delete', 'Sorry, you are not allowed to delete this post.', 'delete_post'],
            default => [null, '', ''],
        };
        if ($code === null) {
            return;
        }
        if ($policy->verb !== 'create' && !$this->subjects->postOfType($id, $slug)) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        if (!$this->caller->can($capability, $policy->verb === 'create' ? null : $id)) {
            throw $this->caller->refuse($code, $message);
        }
    }

    /**
     * A {base} that names no registered taxonomy declines the route. A
     * write is judged with the taxonomy's own capabilities (probe
     * rest-plugin-caps): a missing term first (404), then the caller.
     *
     * @param array<string, string> $captures
     */
    private function taxonomy(Policy $policy, array $captures): void
    {
        $config = TermObject::registered((string) ($captures['base'] ?? ''));
        if ($config === null) {
            throw new RouteMiss();
        }
        $taxonomy = (string) $config['taxonomy'];
        $id = (int) ($captures['id'] ?? 0);
        [$code, $message, $capability] = match ($policy->verb) {
            'create' => ['rest_cannot_create', 'Sorry, you are not allowed to create terms in this taxonomy.', TermsController::createCapability($taxonomy)],
            'edit' => ['rest_cannot_update', 'Sorry, you are not allowed to edit this term.', 'edit_term'],
            'delete' => ['rest_cannot_delete', 'Sorry, you are not allowed to delete this term.', 'delete_term'],
            default => [null, '', ''],
        };
        if ($code === null) {
            return;
        }
        if ($policy->verb !== 'create' && !$this->subjects->termOf($id, $taxonomy)) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$this->caller->can($capability, $policy->verb === 'create' ? null : $id)) {
            throw $this->caller->refuse($code, $message);
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
        $raw = (string) ($captures[(string) $policy->param] ?? '0');
        $id = $raw === 'me' ? $this->caller->id() : (int) $raw;
        if ($policy->cap === null || !$this->caller->can($policy->cap, $id)) {
            throw new RestError($policy->refuse, $policy->message, 403);
        }
        foreach ($policy->caps as $capability) {
            if (!$this->caller->can($capability)) {
                throw new RestError($policy->refuse, $policy->message, 403);
            }
        }
    }
}
