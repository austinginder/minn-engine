<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Seams;
use Minn\Extension\SignedIn;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Runtime\Refusal;

/** A sign-in surface of the extension's own over Seams::accounts(): sign up, sign in, sign out, reset. */
final readonly class Players
{
    public function __construct(private Seams $minn)
    {
    }

    /** A new account with the password the form chose. */
    #[Route(Method::Post, '/minn-test/v1/players', policy: new Policy(Access::Public))]
    public function register(Request $request): Response
    {
        $body = $request->json();
        $made = $this->minn->accounts()->register((string) ($body['login'] ?? ''), (string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''), (string) ($body['shownAs'] ?? ''), (string) ($body['role'] ?? ''));
        return $made instanceof Refusal ? self::refused($made) : Response::json(['id' => $made->id, 'login' => $made->login], 201);
    }

    /** Signs in, the cookies on the answer. */
    #[Route(Method::Post, '/minn-test/v1/session', policy: new Policy(Access::Public))]
    public function signIn(Request $request): Response
    {
        $body = $request->json();
        $accounts = $this->minn->accounts();
        $answer = Response::json(['ok' => true]);
        [$login, $password] = [(string) ($body['login'] ?? ''), (string) ($body['password'] ?? '')];
        $result = empty($body['remember']) ? $accounts->signIn($answer, $login, $password) : $accounts->signInRemembered($answer, $login, $password);
        return $result instanceof SignedIn ? $result->response : self::refused($result, $result->code === 'too_many_attempts' ? 429 : 400);
    }

    /** Signs out. */
    #[Route(Method::Delete, '/minn-test/v1/session', policy: new Policy(Access::Public))]
    public function signOut(Request $request): Response
    {
        return $this->minn->accounts()->signOut(Response::json(['ok' => true]));
    }

    /** Mails a reset link; with a page, the link opens that page with the key and login in its fragment. */
    #[Route(Method::Post, '/minn-test/v1/lost-password', policy: new Policy(Access::Public))]
    public function lostPassword(Request $request): Response
    {
        $body = $request->json();
        $page = (string) ($body['page'] ?? '');
        $link = $page === '' ? null : static fn (string $login, string $key): string => $page . '#key=' . rawurlencode($key) . '&login=' . rawurlencode($login);
        $words = match (true) {
            !empty($body['html']) => static fn (string $url, string $shownAs): array => ["Reset for {$shownAs}", "<p>Hello <b>{$shownAs}</b>, <a href=\"{$url}\">choose a new password</a>.</p>", ['Content-Type: text/html; charset=UTF-8', 'From: Records Probe <records@minn.localhost>']],
            !empty($body['words']) => static fn (string $url, string $shownAs): array => ["Reset for {$shownAs}", "Hello {$shownAs}, choose a new password: {$url}"],
            default => null,
        };
        $sent = $this->minn->accounts()->lostPassword((string) ($body['login'] ?? ''), $link, $words);
        return $sent instanceof Refusal ? self::refused($sent) : Response::json(['sent' => true]);
    }

    /** Saves a new password from a reset link's key. */
    #[Route(Method::Post, '/minn-test/v1/reset', policy: new Policy(Access::Public))]
    public function reset(Request $request): Response
    {
        $body = $request->json();
        $user = $this->minn->accounts()->resetPassword((string) ($body['login'] ?? ''), (string) ($body['key'] ?? ''), (string) ($body['password'] ?? ''));
        return $user instanceof Refusal ? self::refused($user) : Response::json(['id' => $user->id]);
    }

    /** A new password for the caller (the current one first); fresh cookies on the answer. */
    #[Route(Method::Put, '/minn-test/v1/password', policy: new Policy(Access::SignedIn))]
    public function changePassword(Request $request): Response
    {
        $body = $request->json();
        $words = empty($body['words']) ? null : static fn (string $shownAs): array => ["Password changed for {$shownAs}", "<p>{$shownAs}, your password changed.</p>", ['Content-Type: text/html; charset=UTF-8']];
        $result = $this->minn->accounts()->changePassword(Response::json(['ok' => true]), $this->minn->user(), (string) ($body['current'] ?? ''), (string) ($body['password'] ?? ''), $words);
        return $result instanceof SignedIn ? $result->response : self::refused($result);
    }

    /** A new email for the caller (the current password first). */
    #[Route(Method::Put, '/minn-test/v1/email', policy: new Policy(Access::SignedIn))]
    public function changeEmail(Request $request): Response
    {
        $body = $request->json();
        $words = empty($body['words']) ? null : static fn (string $shownAs): array => ["Email changed for {$shownAs}", "{$shownAs}, your email changed."];
        $result = $this->minn->accounts()->changeEmail($this->minn->user(), (string) ($body['current'] ?? ''), (string) ($body['email'] ?? ''), $words);
        return $result instanceof Refusal ? self::refused($result) : Response::json(['email' => $result->email]);
    }

    private static function refused(Refusal $refusal, int $status = 400): Response
    {
        return Response::json(['code' => $refusal->code, 'message' => $refusal->message, 'data' => $refusal->data], $status);
    }
}
