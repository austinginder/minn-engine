<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Auth\Sessions;
use Minn\Content\Users;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Fields;
use Minn\Rest\Reply;
use Minn\RestError;

/**
 * A person's sign-in sessions, read from the same session_tokens store
 * the login endpoint writes. Verifiers are the store's keys (the sha256
 * of each cookie token), so the app can name one without ever seeing
 * the token. Expired rows are skipped, not garbage-collected here.
 */
final readonly class SessionsController
{
    public function __construct(
        private Users $users,
        private Sessions $sessions,
        private Caller $caller,
    ) {
    }

    /** A user's sessions, the caller's own marked. */
    #[Route(Method::Get, '/minn-admin/v1/users/{id:\d+}/sessions', policy: new Policy(Access::Own, 'edit_user', param: 'id', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function list(Request $request, string $id): Response
    {
        $userId = $this->target($id);
        $current = $this->caller->id() === $userId ? hash('sha256', (string) $this->caller->session()?->token) : '';
        $now = time();
        $items = [];
        foreach ($this->sessions->read($userId) as $verifier => $session) {
            $expiration = (int) ($session['expiration'] ?? 0);
            if ($expiration !== 0 && $expiration < $now) {
                continue;
            }
            $items[] = [
                'verifier' => $verifier,
                'ip' => (string) ($session['ip'] ?? ''),
                'ua' => (string) ($session['ua'] ?? ''),
                'login' => (int) ($session['login'] ?? 0),
                'expiration' => $expiration,
                'current' => $verifier === $current,
            ];
        }
        usort($items, static fn (array $a, array $b): int => $b['login'] <=> $a['login']);
        return Reply::item(['sessions' => $items], Fields::fromQuery($request->query));
    }

    /** Signs the person out everywhere; a caller acting on themselves keeps the session they are using. */
    #[Route(Method::Delete, '/minn-admin/v1/users/{id:\d+}/sessions', policy: new Policy(Access::Own, 'edit_user', param: 'id', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function destroyAll(Request $request, string $id): Response
    {
        $userId = $this->target($id);
        if ($this->caller->id() === $userId) {
            $this->sessions->destroyOthers($userId, hash('sha256', (string) $this->caller->session()?->token));
        } else {
            $this->sessions->destroyAll($userId);
        }
        return Reply::item(['ok' => true], null);
    }

    /** Signs one session out. */
    #[Route(Method::Delete, '/minn-admin/v1/users/{id:\d+}/sessions/{verifier:[a-f0-9]{40,64}}', policy: new Policy(Access::Own, 'edit_user', param: 'id', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function destroy(Request $request, string $id, string $verifier): Response
    {
        $userId = $this->target($id);
        if (!$this->sessions->destroyKey($userId, $verifier)) {
            throw new RestError('not_found', 'Session not found', 404);
        }
        return Reply::item(['ok' => true], null);
    }

    /** The user from the path: the caller themselves, or anyone the caller may edit. */
    private function target(string $id): int
    {
        $userId = (int) $id;
        if ($userId <= 0) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
        if ($this->users->find($userId) === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        return $userId;
    }
}
