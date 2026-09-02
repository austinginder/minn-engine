# `Minn\Login`

/wp-login.php and the sign-in surface

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`LoginController`](#logincontroller) | final readonly class | 329 | Signing in. The page people see is /minn-admin/login: the form, the |
| [`LoginForm`](#loginform) | final class | 137 | The sign-in page markup. |
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

Route: `GET /wp-login.php`

The sign-in, lost-password, reset, and logout pages.

### `signIn(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/login`

Route: `POST /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `POST /wp-login.php`

Handles the posted form for each of those pages.

Internals: `lostPassword()` (private, line 97), `openResetLink()` (private, line 139), `resetSession()` (private, line 156), `savePassword()` (private, line 171), `tokenLogin()` (private, line 204), `safeRedirect()` (private, line 272), `logout()` (private, line 291), `action()` (private, line 315), `actionUrl()` (private, line 326), `base()` (private, line 336), `tooManyAttempts()` (private, line 342), `render()` (private, line 349)


## LoginForm

`final class Minn\Login\LoginForm` · `public/minn/src/Minn/Login/LoginForm.php`

The sign-in page markup.

- const `CSS` = `'body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;        background:#0b0b0d; color:#ececed; font:15px/1.5 "Hanken Grotesk","Helvetica Neue",sans-serif; } form { width:320px; background:#151518; border:1px solid #242429; border-radius:14px; padding:28px; } h1 { font-size:19px; font-weight:700; margin:0 0 18px; } .hint { font-size:13px; color:#9d9da7; margin:0 0 12px; } .hint a { color:#ececed; } .msg { background:#14291c; border:1px solid #1f4a2c; border-radius:8px; padding:9px 12px; font-size:13px; margin-bottom:12px; } label { display:block; font-size:12px; color:#9d9da7; margin:14px 0 5px; } input[type=text],input[type=password] { width:100%; box-sizing:border-box; padding:9px 11px;        background:#0b0b0d; border:1px solid #31313a; border-radius:8px; color:#ececed; font-size:14px; } .switch { display:flex; align-items:center; gap:10px; margin-top:16px; font-size:13px; color:#9d9da7; cursor:pointer; } .switch input { position:absolute; opacity:0; width:0; height:0; } .switch .knob { position:relative; flex:0 0 34px; width:34px; height:20px; border-radius:999px; background:#31313a;        border:1px solid #3a3a44; transition:background .15s, border-color .15s; } .switch .knob::after { content:""; position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%;        background:#ececed; transition:transform .15s; } .switch input:checked + .knob { background:#6459f0; border-color:#6459f0; } .switch input:checked + .knob::after { transform:translateX(14px); background:#fff; } .switch input:focus-visible + .knob { outline:2px solid #8a80f8; outline-offset:2px; } button { margin-top:18px; width:100%; padding:10px; background:#6459f0; color:#fff; border:0;        border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; } .err { background:rgba(228,107,107,.12); border:1px solid rgba(228,107,107,.4); color:#e46b6b;        padding:9px 11px; border-radius:8px; font-size:13px; margin-bottom:14px; }'`

Used by: `Minn\Login\LoginController`

### static `render(string $siteName, string $action, string $redirectTo, string $error, string $message = '', string $lostPasswordUrl = ''): string`

The sign-in page's HTML.

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

Internals: `page()` (private, line 110)


## ServeLogin

`final class Minn\Login\ServeLogin` · `public/minn/src/Minn/Login/ServeLogin.php` · implements `Throwable`, `Stringable`

Thrown by the wp-login.php shape file when plugin code require's it
mid-request (a hide-login plugin serving its own sign-in URL, the
perfmatters pattern). Engine::respond() catches it and answers the
current request with the sign-in surface.

Used by: `Minn\Engine`, `Minn\Runtime\Plugins`

