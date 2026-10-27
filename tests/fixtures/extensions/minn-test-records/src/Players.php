<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Content\Users;
use Minn\Extension\Seams;
use Minn\Extension\SignedIn;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Runtime\Refusal;

/** A sign-in surface of the extension's own over Seams::accounts(): sign up (now, or from a held hash), sign in (by password, or vouched for), sign out, reset, its own mail. */
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
        $args = [(string) ($body['login'] ?? ''), (string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''), (string) ($body['shownAs'] ?? ''), (string) ($body['role'] ?? '')];
        $accounts = $this->minn->accounts();
        $made = empty($body['quietly']) ? $accounts->register(...$args) : $accounts->registerQuietly(...$args);
        return $made instanceof Refusal ? self::refused($made) : Response::json(['id' => $made->id, 'login' => $made->login], 201);
    }

    /** A password hashed for holding (a sign-up waiting on its email). */
    #[Route(Method::Post, '/minn-test/v1/players/hash', policy: new Policy(Access::Public))]
    public function hash(Request $request): Response
    {
        return Response::json(['hash' => $this->minn->accounts()->passwordHash((string) ($request->json()['password'] ?? ''))]);
    }

    /** A new account from a held hash, quietly. */
    #[Route(Method::Post, '/minn-test/v1/players/hashed', policy: new Policy(Access::Public))]
    public function registerHashed(Request $request): Response
    {
        $body = $request->json();
        $made = $this->minn->accounts()->registerQuietlyWithHash((string) ($body['login'] ?? ''), (string) ($body['email'] ?? ''), (string) ($body['hash'] ?? ''), (string) ($body['shownAs'] ?? ''), (string) ($body['role'] ?? ''));
        return $made instanceof Refusal ? self::refused($made) : Response::json(['id' => $made->id, 'login' => $made->login], 201);
    }

    /** Signs a login in without its password, as an emailed link would; the cookies on the answer. */
    #[Route(Method::Post, '/minn-test/v1/session/verified', policy: new Policy(Access::Public))]
    public function signInVerified(Request $request): Response
    {
        $table = $this->minn->db->table('users');
        $id = (int) $this->minn->db->value("SELECT ID FROM {$table} WHERE user_login = ?", [(string) ($request->json()['login'] ?? '')]);
        $user = $id > 0 ? (new Users($this->minn->db))->find($id) : null;
        if ($user === null) {
            return Response::json(['code' => 'no_user'], 404);
        }
        return $this->minn->accounts()->signInVerified(Response::json(['ok' => true]), $user)->response;
    }

    /** An email of the extension's own; html sends it as HTML from a sender of its own. */
    #[Route(Method::Post, '/minn-test/v1/mail', policy: new Policy(Access::Public))]
    public function mail(Request $request): Response
    {
        $body = $request->json();
        $headers = empty($body['html']) ? '' : ['Content-Type: text/html; charset=UTF-8', 'From: Records Probe <records@minn.localhost>'];
        return Response::json(['sent' => $this->minn->accounts()->mail((string) ($body['to'] ?? ''), (string) ($body['subject'] ?? ''), (string) ($body['body'] ?? ''), $headers)]);
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
