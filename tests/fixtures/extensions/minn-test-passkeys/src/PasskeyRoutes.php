<?php

declare(strict_types=1);

namespace Minn\Ext\TestPasskeys;

use Minn\Auth\PasskeyRecord;
use Minn\Extension\Seams;
use Minn\Extension\SignedIn;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Refusal;

/** Adding, listing and removing the caller's passkeys, and signing in with one, the way an extension's own sign-in surface would. */
final readonly class PasskeyRoutes
{
    public function __construct(private Seams $minn)
    {
    }

    /** Options to add a passkey to the caller. */
    #[Route(Method::Post, '/minn-test/v1/passkeys/options', policy: new Policy(Access::SignedIn))]
    public function options(Request $request): Response
    {
        return Response::json($this->minn->accounts()->passkeyOptions($this->me()));
    }

    /** Keeps the passkey the browser made. */
    #[Route(Method::Post, '/minn-test/v1/passkeys', policy: new Policy(Access::SignedIn))]
    public function add(Request $request): Response
    {
        $body = $request->json();
        $added = $this->minn->accounts()->addPasskey($this->me(), (array) ($body['credential'] ?? []), (string) ($body['label'] ?? ''));
        return $added instanceof PasskeyRecord ? Response::json($added->toArray(), 201) : self::refused($added);
    }

    /** The caller's passkeys. */
    #[Route(Method::Get, '/minn-test/v1/passkeys', policy: new Policy(Access::SignedIn))]
    public function list(Request $request): Response
    {
        return Response::json(array_map(static fn (PasskeyRecord $passkey): array => $passkey->toArray(), $this->minn->accounts()->passkeys($this->me())));
    }

    /** Removes one of the caller's passkeys. */
    #[Route(Method::Delete, '/minn-test/v1/passkeys/{id:[A-Za-z0-9_-]+}', policy: new Policy(Access::SignedIn))]
    public function remove(Request $request, string $id): Response
    {
        return Response::json(['removed' => $this->minn->accounts()->removePasskey($this->me(), $id)]);
    }

    /** Options to sign in with any passkey for the site. */
    #[Route(Method::Post, '/minn-test/v1/passkeys/sign-in-options', policy: new Policy(Access::Public))]
    public function signInOptions(Request $request): Response
    {
        return Response::json($this->minn->accounts()->passkeySignInOptions());
    }

    /** Signs in with the passkey the browser answered with. */
    #[Route(Method::Post, '/minn-test/v1/passkeys/sign-in', policy: new Policy(Access::Public))]
    public function signIn(Request $request): Response
    {
        $result = $this->minn->accounts()->signInWithPasskey(Response::json([]), (array) ($request->json()['credential'] ?? []));
        if (!$result instanceof SignedIn) {
            return self::refused($result);
        }
        $response = Response::json(['signedIn' => true, 'login' => $result->user->login]);
        foreach ($result->response->cookies as $cookie) {
            $response = $response->withCookie(...$cookie);
        }
        return $response;
    }

    private function me(): \Minn\Content\UserRecord
    {
        return $this->minn->user() ?? throw new RestError('rest_not_logged_in', 'You are not currently logged in.', 401);
    }

    private static function refused(Refusal $refusal): Response
    {
        return Response::json(['code' => $refusal->code, 'message' => $refusal->message, 'data' => $refusal->data], $refusal->code === 'too_many_attempts' ? 429 : 400);
    }
}
