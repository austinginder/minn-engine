<?php

declare(strict_types=1);

namespace Minn\Extension;

use Closure;
use Minn\Auth\AuthCookies;
use Minn\Auth\Authenticated;
use Minn\Auth\Authenticator;
use Minn\Auth\Cookie;
use Minn\Auth\LoginThrottle;
use Minn\Auth\Password;
use Minn\Auth\PasswordReset;
use Minn\Auth\Sessions;
use Minn\Auth\SignIn;
use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Login\LoginHooks;
use Minn\Objects\Errors;
use Minn\Runtime\AccountFlows;
use Minn\Runtime\Handoff;
use Minn\Runtime\Refusal;
use Minn\Runtime\Runtime;
use Minn\Runtime\UserWrites;
use Minn\Support\Entities;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;

/**
 * Accounts for a sign-in surface of an extension's own (a game's players, a
 * members' area), from its REST routes: the sign-in, sign-out, registration
 * and password reset the engine's sign-in page runs, with the same
 * throttle, hooks, sessions and mail, handed back as values the extension
 * words itself. A refusal is a Refusal: its code, its message as plain
 * text, and every code and message in data.notices. A refused sign-in
 * never says which half was wrong (code incorrect_credentials) unless a
 * plugin refused it in its own words. They run the sign-in hooks plugins
 * listen on, so they belong on an extension's REST routes.
 */
final readonly class Accounts
{
    /** The authenticate chain's own refusals, which read as one. */
    private const REFUSALS = ['empty_username', 'empty_password', 'invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed'];

    private Users $users;
    private Authenticator $authenticator;
    private SignIn $signIn;

    public function __construct(
        Db $db,
        private Request $request,
    ) {
        $this->users = new Users($db);
        $cookie = new Cookie($db, $this->users, new Sessions($this->users));
        $this->authenticator = new Authenticator($cookie, $this->users);
        $this->signIn = new SignIn(new AuthCookies($db, $cookie), new LoginThrottle($db));
    }

    /**
     * Signs in with a login (or email) and password, as the sign-in page
     * does, for two days ending with the browser: the response with the new
     * session's cookies and the user, or the refusal (too_many_attempts with
     * data.retryAfter in seconds, incorrect_credentials, or a plugin's own).
     */
    public function signIn(Response $response, string $login, string $password): SignedIn|Refusal
    {
        return $this->attempt($response, $login, $password, $this->signIn->establish(...));
    }

    /** Signs in as signIn does, remembered: fourteen days, the cookies kept past the browser session. */
    public function signInRemembered(Response $response, string $login, string $password): SignedIn|Refusal
    {
        return $this->attempt($response, $login, $password, $this->signIn->remember(...));
    }

    /** @param Closure(Response, UserRecord, Request): Response $open */
    private function attempt(Response $response, string $login, string $password, Closure $open): SignedIn|Refusal
    {
        $wait = $this->signIn->retryAfter($this->request->remoteAddress);
        if ($wait !== null) {
            return new Refusal('too_many_attempts', 'Too many failed sign-ins. Try again in a few minutes.', ['retryAfter' => $wait, 'notices' => []]);
        }
        $hooks = new LoginHooks($this->users);
        // Checked slashed, as a sign-in form's password arrives and as accounts store it (AccountFlows::registerWithPassword).
        $user = $hooks->authenticate($login, (string) Slashes::add($password));
        if (!$user instanceof UserRecord) {
            $this->signIn->recordFailure($this->request->remoteAddress);
            return self::refusal($user, 'incorrect_credentials', 'The username or password is incorrect.');
        }
        $response = $open($response, $user, $this->request);
        $hooks->signedIn($user);
        return new SignedIn($user, $response);
    }

    /** Ends the session the request's sign-in cookie names (when there is one) and clears the cookies on the response. */
    public function signOut(Response $response): Response
    {
        $session = $this->authenticator->session($this->request->cookies);
        if (!$session instanceof Authenticated) {
            return $this->signIn->clear($response);
        }
        $response = $this->signIn->end($response, $session);
        (new LoginHooks($this->users))->signedOut($session->id());
        return $response;
    }

    /** Whether the site takes new accounts (users_can_register). */
    public function registrationOpen(): bool
    {
        return (bool) Runtime::options()->filtered('users_can_register');
    }

    /**
     * A new account with the password its holder chose, the role asked for
     * (one the site has, else the site's default role) and, when given, the
     * name shown for it (display name and nickname; an email-only sign-up
     * passes a login of its own making), when the site takes new accounts:
     * the user, or every refusal (registration_closed, unknown_role; the name empty, illegal, taken or barred; the
     * email empty, malformed or taken; no password; whatever plugins add).
     * The site's address is told; nobody is signed in.
     */
    public function register(string $login, string $email, string $password, string $shownAs = '', string $role = ''): UserRecord|Refusal
    {
        if (!$this->registrationOpen()) {
            return new Refusal('registration_closed', 'This site is not taking new accounts.', ['notices' => []]);
        }
        if ($role !== '' && !isset(Runtime::current()->capabilities->roles()->all()[$role])) {
            return new Refusal('unknown_role', "The site has no role named {$role}.", ['notices' => []]);
        }
        $id = AccountFlows::registerWithPassword($login, $email, $password, $shownAs, $role);
        $user = is_int($id) ? $this->users->find($id) : null;
        return $user ?? self::refusal($id, 'registerfail', 'The account could not be made.');
    }

    /**
     * A password reset link mailed to the account a login or email names,
     * as the lost-password page sends it: true, or the refusal. $link makes
     * the link from the account's login and the key, for a reset page of the
     * extension's own (which hands both to resetPassword); without it the
     * link opens the sign-in page's reset form. $words writes the mail
     * (subject, body, and optionally headers: an HTML mail's Content-Type, a
     * From) from the link and the account's display name, for a site whose
     * people never see a login; without it the mail is the sign-in page's,
     * which names the login.
     *
     * @param (Closure(string, string): string)|null $link
     * @param (Closure(string, string): array{0: string, 1: string, 2?: string|list<string>})|null $words
     */
    public function lostPassword(string $login, ?Closure $link = null, ?Closure $words = null): true|Refusal
    {
        $sent = AccountFlows::retrievePassword($login, $link, $words);
        return $sent === true ? true : self::refusal($sent, 'retrieve_password_failed', 'The reset email could not be sent.');
    }

    /**
     * A new password for the account a reset link named, as the reset form
     * saves one: the key must still be good (a day, or what
     * password_reset_expiration says), plugins may refuse the password
     * (validate_password_reset), and every session of the account ends. The
     * user, or the refusal (too_many_attempts, invalid_key, expired_key,
     * empty_password, a plugin's). Nobody is signed in.
     */
    public function resetPassword(string $login, string $key, string $password): UserRecord|Refusal
    {
        $wait = $this->signIn->retryAfter($this->request->remoteAddress);
        if ($wait !== null) {
            return new Refusal('too_many_attempts', 'Too many tries. Try again in a few minutes.', ['retryAfter' => $wait, 'notices' => []]);
        }
        $user = $this->users->findByLogin($login);
        $lifetime = (int) Runtime::hooks()->filter('password_reset_expiration', [PasswordReset::LIFETIME]);
        $status = $user === null ? 'invalid' : (new PasswordReset($this->users))->status($user, $key, $lifetime);
        if ($user === null || $status !== 'valid') {
            $this->signIn->recordFailure($this->request->remoteAddress);
            return $status === 'invalid'
                ? new Refusal('invalid_key', 'This reset link is not valid. Ask for a new one.', ['notices' => []])
                : new Refusal('expired_key', 'This reset link has expired. Ask for a new one.', ['notices' => []]);
        }
        if ($password === '') {
            return new Refusal('empty_password', 'The password cannot be empty.', ['notices' => []]);
        }
        $errors = Handoff::newError();
        Runtime::hooks()->action('validate_password_reset', [$errors, Handoff::newUser($user->id)]);
        if ($errors->has_errors()) {
            return self::refusal($errors, 'password_refused', 'That password was refused.');
        }
        AccountFlows::resetPassword(Handoff::newUser($user->id), (string) Slashes::add($password));
        Handoff::sessionTokens($user->id)->destroy_all();
        return $this->users->find($user->id) ?? $user;
    }

    /**
     * A new password for a signed-in user, once the current one checks out:
     * saved as a profile save saves it (wp_update_user, its hooks and its
     * notice to the account's address, in the extension's words when $words
     * gives them), every session of the account ended, and a new one on the
     * response: a sign-in cookie is bound to the password it was made under,
     * so the old ones stop working anyway. Refusals: too_many_attempts,
     * incorrect_password (counts against the throttle), empty_password, a
     * plugin's.
     *
     * @param (Closure(string): array{0: string, 1: string, 2?: string|list<string>})|null $words the notice from the display name
     */
    public function changePassword(Response $response, UserRecord $user, string $current, string $new, ?Closure $words = null): SignedIn|Refusal
    {
        $refused = $this->checkCurrent($user, $current);
        if ($refused !== null) {
            return $refused;
        }
        if ($new === '') {
            return new Refusal('empty_password', 'The new password cannot be empty.', ['notices' => []]);
        }
        $saved = self::worded('password_change_email', $words, $user, static fn (): mixed => UserWrites::update(['ID' => $user->id, 'user_pass' => Slashes::add($new)]));
        if (!is_int($saved)) {
            return self::refusal($saved, 'password_not_saved', 'The password could not be saved.');
        }
        Handoff::sessionTokens($user->id)->destroy_all();
        $fresh = $this->users->find($user->id) ?? $user;
        return new SignedIn($fresh, $this->signIn->remember($response, $fresh, $this->request));
    }

    /**
     * A new email for a signed-in user, once the current password checks
     * out: saved as a profile save saves it, the old address told (in the
     * extension's words when $words gives them). Refusals:
     * too_many_attempts, incorrect_password, invalid_email, email_exists, a
     * plugin's.
     *
     * @param (Closure(string): array{0: string, 1: string, 2?: string|list<string>})|null $words the notice from the display name
     */
    public function changeEmail(UserRecord $user, string $current, string $email, ?Closure $words = null): UserRecord|Refusal
    {
        $refused = $this->checkCurrent($user, $current);
        if ($refused !== null) {
            return $refused;
        }
        $email = trim($email);
        if (!Sanitize::isEmail($email)) {
            return new Refusal('invalid_email', 'The email address is not correct.', ['notices' => []]);
        }
        $holder = UserWrites::emailExists($email);
        if ($holder !== false && (int) $holder !== $user->id) {
            return new Refusal('email_exists', 'This email address is already registered.', ['notices' => []]);
        }
        $saved = self::worded('email_change_email', $words, $user, static fn (): mixed => UserWrites::update(['ID' => $user->id, 'user_email' => Slashes::add($email)]));
        if (!is_int($saved)) {
            return self::refusal($saved, 'email_not_saved', 'The email could not be saved.');
        }
        return $this->users->find($user->id) ?? $user;
    }

    /** The current password, judged under the sign-in throttle: null when it checks out, else the refusal. */
    private function checkCurrent(UserRecord $user, string $current): ?Refusal
    {
        $wait = $this->signIn->retryAfter($this->request->remoteAddress);
        if ($wait !== null) {
            return new Refusal('too_many_attempts', 'Too many tries. Try again in a few minutes.', ['retryAfter' => $wait, 'notices' => []]);
        }
        // Stored slashed, as a sign-in form sends it (AccountFlows::registerWithPassword).
        if (!Password::verify((string) Slashes::add($current), $user->passwordHash)) {
            $this->signIn->recordFailure($this->request->remoteAddress);
            return new Refusal('incorrect_password', 'The current password is incorrect.', ['notices' => []]);
        }
        return null;
    }

    /**
     * Runs a save whose notice goes through $filter (password_change_email,
     * email_change_email) with the extension's words in place of the
     * reference's, early, so plugins' filters still have the last word.
     */
    private static function worded(string $filter, ?Closure $words, UserRecord $user, Closure $save): mixed
    {
        if ($words === null) {
            return $save();
        }
        $replace = static function (mixed $mail) use ($words, $user): array {
            [$subject, $body, $headers] = $words($user->displayName !== '' ? $user->displayName : $user->login) + [2 => ''];
            // The subject is a sprintf pattern for the site's name there: a literal % stays one.
            return array_replace((array) $mail, ['subject' => str_replace('%', '%%', $subject), 'message' => $body, 'headers' => $headers]);
        };
        Runtime::hooks()->add($filter, $replace, 1, 1);
        try {
            return $save();
        } finally {
            Runtime::hooks()->remove($filter, $replace, 1);
        }
    }

    /** A refusal from the WordPress layer's errors: the first as the answer, all of them in data.notices; the chain's own as one sentence. */
    private static function refusal(mixed $errors, string $code, string $message): Refusal
    {
        $notices = [];
        if ($errors instanceof Errors) {
            foreach ($errors->get_error_codes() as $each) {
                $notices[] = ['code' => (string) $each, 'message' => self::plain((string) $errors->get_error_message($each))];
            }
        }
        $own = array_values(array_filter($notices, static fn (array $n): bool => !in_array($n['code'], self::REFUSALS, true)));
        if ($code === 'incorrect_credentials') {
            $notices = $own;
        }
        $first = $notices[0] ?? ['code' => $code, 'message' => $message];
        return new Refusal($first['code'], $first['message'], ['notices' => $notices]);
    }

    /** A notice's words without its markup or its "Error:" label. */
    private static function plain(string $html): string
    {
        $text = trim(Entities::decode(strip_tags($html), ENT_QUOTES));
        return trim((string) preg_replace('/^Error:\s*/', '', $text));
    }
}
