# `Minn\Login`

/wp-login.php and the sign-in surface

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`LoginController`](#logincontroller) | final readonly class | 460 | Signing in. The page people see is /minn-admin/login: the form, the |
| [`LoginForm`](#loginform) | final class | 213 | The sign-in page markup. |
| [`LoginHooks`](#loginhooks) | final readonly class | 114 | The sign-in as plugins see it, when they are loaded. The credentials go |
| [`LoginNotices`](#loginnotices) | final readonly class | 119 | What a sign-in page tells the reader, held as the reference's pages hold |
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
- const `SEGMENTS` = `array (   'lost-password' => 'lostpassword',   'reset' => 'rp',   'logout' => 'logout',   'register' => 'register', )` — Clean path segment => the action wp-login.php spells with ?action=.

Used by: `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Auth\Authenticator $authenticator, Minn\Auth\SignIn $signIn, Minn\Content\Users $users, Minn\Auth\PasswordReset $reset, Minn\Mail\Mailer $mailer)
```


### `form(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/login`

Route: `GET /minn-admin/login/{segment:lost-password|reset|logout|register}`

Route: `GET /wp-login.php (public)`

The sign-in, lost-password, reset, and logout pages.

### `signIn(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/login`

Route: `POST /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `POST /wp-login.php (public)`

Handles the posted form for each of those pages.

Internals: `checkEmail()` (private, line 111), `signInNotices()` (private, line 122), `lostPassword()` (private, line 132), `retrieve()` (private, line 178), `openResetLink()` (private, line 195), `resetSession()` (private, line 212), `savePassword()` (private, line 227), `register()` (private, line 267), `validateReset()` (private, line 292), `parts()` (private, line 307), `tokenLogin()` (private, line 319), `safeRedirect()` (private, line 397), `logout()` (private, line 416), `action()` (private, line 446), `actionUrl()` (private, line 458), `base()` (private, line 468), `tooManyAttempts()` (private, line 474), `render()` (private, line 482)


## LoginForm

`final class Minn\Login\LoginForm` · `public/minn/src/Minn/Login/LoginForm.php`

The sign-in page markup.

- const `CSS` = `'body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;        background:#0b0b0d; color:#ececed; font:15px/1.5 "Hanken Grotesk","Helvetica Neue",sans-serif; } form { width:320px; background:#151518; border:1px solid #242429; border-radius:14px; padding:28px; } h1 { font-size:19px; font-weight:700; margin:0 0 18px; } .hint { font-size:13px; color:#9d9da7; margin:0 0 12px; } .hint a { color:#ececed; } .msg { background:#14291c; border:1px solid #1f4a2c; border-radius:8px; padding:9px 12px; font-size:13px; margin-bottom:12px; } label { display:block; font-size:12px; color:#9d9da7; margin:14px 0 5px; } input[type=text],input[type=password] { width:100%; box-sizing:border-box; padding:9px 11px;        background:#0b0b0d; border:1px solid #31313a; border-radius:8px; color:#ececed; font-size:14px; } .switch { display:flex; align-items:center; gap:10px; margin-top:16px; font-size:13px; color:#9d9da7; cursor:pointer; } .switch input { position:absolute; opacity:0; width:0; height:0; } .switch .knob { position:relative; flex:0 0 34px; width:34px; height:20px; border-radius:999px; background:#31313a;        border:1px solid #3a3a44; transition:background .15s, border-color .15s; } .switch .knob::after { content:""; position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%;        background:#ececed; transition:transform .15s; } .switch input:checked + .knob { background:#6459f0; border-color:#6459f0; } .switch input:checked + .knob::after { transform:translateX(14px); background:#fff; } .switch input:focus-visible + .knob { outline:2px solid #8a80f8; outline-offset:2px; } button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;        border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; } .err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;        padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; } .err p, .msg p { margin:0; } .err p + p, .msg p + p { margin-top:6px; } .err ul { margin:0; padding-left:18px; } .err a, .msg a { color:inherit; }'`

Used by: `Minn\Login\LoginController`

### static `render(string $siteName, string $action, string $redirectTo, string $error, string $message = '', string $lostPasswordUrl = '', array $parts = array ( )): string`

The sign-in page's HTML. With plugins loaded, $parts carries what they
put on the page (LoginHooks::page()): the title, head, body classes,
the header's link and words, a message above the form, fields inside
it, and the footer, each where the reference's page puts it.

- `@param array{title?: string, head?: string, bodyClass?: string, headerUrl?: string, headerText?: string, message?: string, errors?: string, messages?: string, form?: string, footer?: string} $parts`

### static `embedded(array $args, string $top, string $middle, string $bottom): string`

The sign-in form a theme embeds in a page, as distinct from the engine's
own sign-in page above. Every label, id and class is a seam themes and
plugins style against, so the shape is fixed.

- `@param array<string, mixed> $args the parsed wp_login_form arguments`

### static `lostPassword(string $siteName, string $action, string $error, string $message, array $parts = array ( )): string`

The lost-password form.

- `@param array{title?: string, head?: string, bodyClass?: string, headerUrl?: string, headerText?: string, message?: string, form?: string, footer?: string} $parts what plugins put on the page (LoginHooks::page())`

### static `register(string $siteName, string $action, string $error, string $login, string $email, string $redirect, array $parts = array ( )): string`

The registration form: a username and an email, what plugins add,
where a sign-up lands (registration_redirect's answer), and word that
the confirmation comes by email.

- `@param array{title?: string, head?: string, bodyClass?: string, headerUrl?: string, headerText?: string, message?: string, form?: string, footer?: string} $parts`

### static `resetPassword(string $siteName, string $action, string $key, string $login, string $error, array $parts = array ( )): string`

The new-password form; the key rides in a hidden field as on the reference.

- `@param array{title?: string, head?: string, bodyClass?: string, headerUrl?: string, headerText?: string, message?: string, form?: string, footer?: string} $parts`

### static `notice(string $siteName, string $title, string $message, string $loginUrl): string`

A message with a link back to sign-in.

### static `checkEmail(string $siteName, string $loginUrl, array $parts = array ( )): string`

The check-your-email page: no form, only its word (in the message
area) and the way back to sign in.

- `@param array{title?: string, head?: string, bodyClass?: string, message?: string, errors?: string, messages?: string, footer?: string} $parts`

Internals: `notices()` (private, line 176), `page()` (private, line 184)


## LoginHooks

`final readonly class Minn\Login\LoginHooks` · `public/minn/src/Minn/Login/LoginHooks.php`

The sign-in as plugins see it, when they are loaded. The credentials go
through the reference's authenticate chain, so a plugin may refuse a
sign-in (a breached password, a second factor, a locked account) or
accept one the password alone would not; wp_login_failed is the chain's
own report. wp_login follows a good sign-in and wp_logout a sign-out.
The refusal is shown as the page's notices (LoginNotices): the chain's
own as one vague sentence, a plugin's in its own words.

Used by: `Minn\Login\LoginController`, `Minn\Login\LoginForm`

```php
__construct(Minn\Content\Users $users)
```


### `available(): bool`

Whether the chain can be asked: only with plugins loaded.

### `authenticate(string $login, string $password): Minn\Content\UserRecord|WP_Error|null`

The credentials through wp_authenticate and the authenticate filters,
as the reference's sign-in runs them: the user, or the refusal (a
WP_Error, or null when the chain gave none).

### `signedIn(Minn\Content\UserRecord $user): void`

After a good sign-in. The chain read the user through the runtime,
which cached their meta before the new session was written, so the
cache is let go first: a plugin reading session_tokens on wp_login
(CleanTalk keeps the first session's address) must see the new one.

### `enter(string $action): void`

A sign-in page request arriving, as wp-login.php announces it: login_init, then login_form_{action}.

### `page(string $action, string $title, string $siteName, string $homeUrl, ?Minn\Content\UserRecord $user = NULL, ?Minn\Login\LoginNotices $notices = NULL): array`

What plugins put on the sign-in page, where the reference's page puts
it: the title (login_title), the head (login_enqueue_scripts, then
login_head, which prints the styles and scripts), the body classes
(login_body_class), the header's link and words (login_headerurl,
login_headertext), the message above the form (login_message), the
page's notices (login_errors, login_messages), the fields inside the
form (login_form, lostpassword_form or resetpass_form, as the page
is), and the footer (login_footer).

- `@return array{title: string, head: string, bodyClass: string, headerUrl: string, headerText: string, message: string, errors?: string, messages?: string, form: string, footer: string}|array{}`

### `landing(string $redirect, string $requested, Minn\Content\UserRecord $user): string`

Where a good sign-in lands, as login_redirect says (the requested address and the user beside it).

### `leaving(string $redirect, string $requested, int $userId): string`

Where a sign-out lands, as logout_redirect says.

### `signedOut(int $userId): void`

After a sign-out ended the session.


## LoginNotices

`final readonly class Minn\Login\LoginNotices` · `public/minn/src/Minn/Login/LoginNotices.php`

What a sign-in page tells the reader, held as the reference's pages hold
it: notices by code, each an error or (severity "message") a message,
their words as HTML. Printed, the errors are one paragraph, or a list
when there are several, and the messages a paragraph each; with plugins
loaded the sign-in page hands them through wp_login_errors first, and
every page prints them through login_errors and login_messages. The
authenticate chain's own refusals read as the engine's one sentence, so
a page never says which half of a sign-in was wrong.

- const `REFUSALS` = `array (   0 => 'empty_username',   1 => 'empty_password',   2 => 'invalid_username',   3 => 'invalid_email',   4 => 'incorrect_password',   5 => 'authentication_failed', )` — The chain's own refusals, which the page words as one.
- const `REFUSED` = `'<strong>Error:</strong> The username or password you entered is incorrect.'` — The one sentence for any of them.

Used by: `Minn\Login\LoginController`, `Minn\Login\LoginForm`, `Minn\Login\LoginHooks`


### static `none(): self`

No notices.

### static `plain(string $code, string $text, string $severity = ''): self`

The engine's own plain words as one notice; an "Error:" lead takes the reference's strong label.

### static `refused(mixed $refusal): self`

A sign-in refused: the chain's refusal, or (none given, or not one) the engine's one sentence.

### static `of(mixed $errors): self`

The notices a WP_Error holds (a plugin may have handed back something else: then none).

### `with(string $code, string $html, string $severity = ''): self`

These notices and one more.

### `isEmpty(): bool`

Whether there are none.

### `wpError(): WP_Error`

As the WP_Error plugins are handed.

### `forSignIn(string $redirect): self`

The sign-in page's notices after wp_login_errors, which is handed
where a sign-in would land; without plugins, as they are.

### `areas(): array`

The error and message areas' HTML ('' for an area with none),
through login_errors and login_messages with plugins loaded.

- `@return array{errors: string, messages: string}`

Internals: `words()` (private, line 134)


## ServeLogin

`final class Minn\Login\ServeLogin` · `public/minn/src/Minn/Login/ServeLogin.php` · implements `Throwable`, `Stringable`

Thrown by the wp-login.php shape file when plugin code require's it
mid-request (a hide-login plugin serving its own sign-in URL, the
perfmatters pattern). Engine::respond() catches it and answers the
current request with the sign-in surface.

Used by: `Minn\Engine`, `Minn\Runtime\Plugins`

