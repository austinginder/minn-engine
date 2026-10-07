# `Minn\Login`

/wp-login.php and the sign-in surface

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`LoginController`](#logincontroller) | final readonly class | 346 | Signing in. The page people see is /minn-admin/login: the form, the |
| [`LoginForm`](#loginform) | final class | 150 | The sign-in page markup. |
| [`LoginHooks`](#loginhooks) | final readonly class | 121 | The sign-in as plugins see it, when they are loaded. The credentials go |
| [`ServeLogin`](#servelogin) | final class | 3 | Thrown by the wp-login.php shape file when plugin code require's it |

## LoginController

`final readonly class Minn\Login\LoginController` · `public/minn/src/Minn/Login/LoginController.php`

Signing in. The page people see is /minn-admin/login: the form, the
lost-password and reset flows, and logout all live there and link there.
/wp-login.php stays as the address tooling knows (one-time login links,
uptime probes, scripted sign-ins) and answers in place with the same
shapes; a bare GET of it sends a browser to the clean page. Sessions
and cookies validate on the engine AND on WordPress either way.

- const `DAY` = `86400`
- const `PATH` = `'/minn-admin/login'`
- const `SEGMENTS` = `array (   'lost-password' => 'lostpassword',   'reset' => 'rp',   'logout' => 'logout', )` — Clean path segment => the action wp-login.php spells with ?action=.

Used by: `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Auth\Authenticator $authenticator, Minn\Auth\SignIn $signIn, Minn\Content\Users $users, Minn\Auth\PasswordReset $reset, Minn\Mail\Mailer $mailer)
```


### `form(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/login`

Route: `GET /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `GET /wp-login.php (public)`

The sign-in, lost-password, reset, and logout pages.

### `signIn(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/login`

Route: `POST /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `POST /wp-login.php (public)`

Handles the posted form for each of those pages.

Internals: `lostPassword()` (private, line 101), `openResetLink()` (private, line 143), `resetSession()` (private, line 160), `savePassword()` (private, line 175), `tokenLogin()` (private, line 208), `safeRedirect()` (private, line 284), `logout()` (private, line 303), `action()` (private, line 333), `actionUrl()` (private, line 344), `base()` (private, line 354), `tooManyAttempts()` (private, line 360), `render()` (private, line 367)


## LoginForm

`final class Minn\Login\LoginForm` · `public/minn/src/Minn/Login/LoginForm.php`

The sign-in page markup.

- const `CSS` = `'body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;        background:#0b0b0d; color:#ececed; font:15px/1.5 "Hanken Grotesk","Helvetica Neue",sans-serif; } form { width:320px; background:#151518; border:1px solid #242429; border-radius:14px; padding:28px; } h1 { font-size:19px; font-weight:700; margin:0 0 18px; } .hint { font-size:13px; color:#9d9da7; margin:0 0 12px; } .hint a { color:#ececed; } .msg { background:#14291c; border:1px solid #1f4a2c; border-radius:8px; padding:9px 12px; font-size:13px; margin-bottom:12px; } label { display:block; font-size:12px; color:#9d9da7; margin:14px 0 5px; } input[type=text],input[type=password] { width:100%; box-sizing:border-box; padding:9px 11px;        background:#0b0b0d; border:1px solid #31313a; border-radius:8px; color:#ececed; font-size:14px; } .switch { display:flex; align-items:center; gap:10px; margin-top:16px; font-size:13px; color:#9d9da7; cursor:pointer; } .switch input { position:absolute; opacity:0; width:0; height:0; } .switch .knob { position:relative; flex:0 0 34px; width:34px; height:20px; border-radius:999px; background:#31313a;        border:1px solid #3a3a44; transition:background .15s, border-color .15s; } .switch .knob::after { content:""; position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%;        background:#ececed; transition:transform .15s; } .switch input:checked + .knob { background:#6459f0; border-color:#6459f0; } .switch input:checked + .knob::after { transform:translateX(14px); background:#fff; } .switch input:focus-visible + .knob { outline:2px solid #8a80f8; outline-offset:2px; } button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;        border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; } .err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;        padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; }'`

Used by: `Minn\Login\LoginController`

### static `render(string $siteName, string $action, string $redirectTo, string $error, string $message = '', string $lostPasswordUrl = '', array $parts = array ( )): string`

The sign-in page's HTML. With plugins loaded, $parts carries what they
put on the page (LoginHooks::page()): the title, head, body classes,
the header's link and words, a message above the form, fields inside
it, and the footer, each where the reference's page puts it.

- `@param array{title?: string, head?: string, bodyClass?: string, headerUrl?: string, headerText?: string, message?: string, form?: string, footer?: string} $parts`

### static `embedded(array $args, string $top, string $middle, string $bottom): string`

The sign-in form a theme embeds in a page, as distinct from the engine's
own sign-in page above. Every label, id and class is a seam themes and
plugins style against, so the shape is fixed.

- `@param array<string, mixed> $args the parsed wp_login_form arguments`

### static `lostPassword(string $siteName, string $action, string $error, string $message): string`

The "forgot password" form: one field, posts to itself.

### static `resetPassword(string $siteName, string $action, string $key, string $login, string $error): string`

The new-password form; the key rides in a hidden field as on the reference.

### static `notice(string $siteName, string $title, string $message, string $loginUrl): string`

A message with a link back to sign-in.

Internals: `page()` (private, line 123)


## LoginHooks

`final readonly class Minn\Login\LoginHooks` · `public/minn/src/Minn/Login/LoginHooks.php`

The sign-in as plugins see it, when they are loaded. The credentials go
through the reference's authenticate chain, so a plugin may refuse a
sign-in (a breached password, a second factor, a locked account) or
accept one the password alone would not; wp_login_failed is the chain's
own report. wp_login follows a good sign-in and wp_logout a sign-out.
The engine's default refusals stay one vague sentence; a plugin's is
shown in its own words, through login_errors.

- const `DEFAULT_REFUSALS` = `array (   0 => 'empty_username',   1 => 'empty_password',   2 => 'invalid_username',   3 => 'invalid_email',   4 => 'incorrect_password',   5 => 'authentication_failed', )` — The chain's own refusals, which the sign-in page words as one.

Used by: `Minn\Login\LoginController`, `Minn\Login\LoginForm`

```php
__construct(Minn\Content\Users $users)
```


### `available(): bool`

Whether the chain can be asked: only with plugins loaded.

### `authenticate(string $login, string $password): Minn\Content\UserRecord|array`

The credentials through wp_authenticate and the authenticate filters,
as the reference's sign-in runs them: the user, or the refusal as
[code, message].

- `@return UserRecord|array{0: string, 1: string}`

### `refusal(string $code, string $message): ?string`

The sentence the sign-in page shows for a refusal, as plain text; null for the engine's own wording.

### `signedIn(Minn\Content\UserRecord $user): void`

After a good sign-in. The chain read the user through the runtime,
which cached their meta before the new session was written, so the
cache is let go first: a plugin reading session_tokens on wp_login
(CleanTalk keeps the first session's address) must see the new one.

### `enter(string $action): void`

A sign-in page request arriving, as wp-login.php announces it: login_init, then login_form_{action}.

### `page(string $action, string $title, string $siteName, string $homeUrl): array`

What plugins put on the sign-in page, where the reference's page puts
it: the title (login_title), the head (login_enqueue_scripts, then
login_head, which prints the styles and scripts), the body classes
(login_body_class), the header's link and words (login_headerurl,
login_headertext), the message above the form (login_message), the
fields inside it (login_form), and the footer (login_footer).

- `@return array{title: string, head: string, bodyClass: string, headerUrl: string, headerText: string, message: string, form: string, footer: string}|array{}`

### `landing(string $redirect, string $requested, Minn\Content\UserRecord $user): string`

Where a good sign-in lands, as login_redirect says (the requested address and the user beside it).

### `leaving(string $redirect, string $requested, int $userId): string`

Where a sign-out lands, as logout_redirect says.

### `signedOut(int $userId): void`

After a sign-out ended the session.


## ServeLogin

`final class Minn\Login\ServeLogin` · `public/minn/src/Minn/Login/ServeLogin.php` · implements `Throwable`, `Stringable`

Thrown by the wp-login.php shape file when plugin code require's it
mid-request (a hide-login plugin serving its own sign-in URL, the
perfmatters pattern). Engine::respond() catches it and answers the
current request with the sign-in surface.

Used by: `Minn\Engine`, `Minn\Runtime\Plugins`

