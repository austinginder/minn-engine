# `Minn\Login`

/wp-login.php and the sign-in surface

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`LoginController`](#logincontroller) | final readonly class | 334 | Signing in. The page people see is /minn-admin/login: the form, the |
| [`LoginForm`](#loginform) | final class | 136 | The sign-in page markup. |
| [`ServeLogin`](#servelogin) | final class | 3 | Thrown by the wp-login.php shape file when plugin code require's it |

## LoginController

`final readonly class Minn\Login\LoginController` · `public/minn/src/Minn/Login/LoginController.php`

Signing in. The page people see is /minn-admin/login: the form, the
lost-password and reset flows, and logout all live there and link there.
/wp-login.php stays as the address tooling knows (one-time login links,
uptime probes, scripted sign-ins) and answers in place with the same
shapes; a bare GET of it sends a browser to the clean page. Sessions
and cookies validate on the engine AND on WordPress either way.

- const `PATH` = `'/minn-admin/login'`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Auth\Authenticator $authenticator, Minn\Auth\Sessions $sessions, Minn\Auth\AuthCookies $cookies, Minn\Content\Users $users, Minn\Auth\LoginThrottle $throttle, Minn\Auth\PasswordReset $reset, Minn\Mail\Mailer $mailer)
```

### `form(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/login`

Route: `GET /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `GET /wp-login.php`

### `signIn(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/login`

Route: `POST /minn-admin/login/{segment:lost-password|reset|logout}`

Route: `POST /wp-login.php`


## LoginForm

`final class Minn\Login\LoginForm` · `public/minn/src/Minn/Login/LoginForm.php`

The sign-in page markup.

### static `render(string $siteName, string $action, string $redirectTo, string $error, string $message = '', string $lostPasswordUrl = ''): string`

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


## ServeLogin

`final class Minn\Login\ServeLogin` · `public/minn/src/Minn/Login/ServeLogin.php` · implements `Throwable`, `Stringable`

Thrown by the wp-login.php shape file when plugin code require's it
mid-request (a hide-login plugin serving its own sign-in URL, the
perfmatters pattern). Engine::respond() catches it and answers the
current request with the sign-in surface.

