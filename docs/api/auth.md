# `Minn\Auth`

passwords, sessions, cookies, nonces, roles and capabilities

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`ApplicationPasswords`](#applicationpasswords) | final readonly class | 135 | Application passwords as the reference stores them: a serialized list in |
| [`AuthCookies`](#authcookies) | final readonly class | 54 | The three cookies a sign-in sets: the auth cookie on the admin and |
| [`AuthFailure`](#authfailure) | final readonly class | 16 | Why a request is not authenticated, as the reference's error code: a |
| [`Authenticated`](#authenticated) | final readonly class | 15 | A validated session: the user row and the raw session token behind it. |
| [`Authenticator`](#authenticator) | final readonly class | 108 | Resolves the current user two ways. A page load carries the cookie alone; |
| [`Capabilities`](#capabilities) | final readonly class | 151 | The capability engine: a user's roles from {prefix}capabilities usermeta, |
| [`Cookie`](#cookie) | final readonly class | 73 | The logged_in auth cookie: username\|expiration\|token\|hmac, with |
| [`LoginThrottle`](#loginthrottle) | final readonly class | 76 | Failed sign-ins per address, so a password guesser meets a wall: twenty |
| [`Nonce`](#nonce) | final class | 30 | The wp_rest nonce: ten characters of HMAC-md5(tick\|wp_rest\|uid\|token) |
| [`Password`](#password) | final class | 32 | The stored password scheme. A modern "$wp$2y$..." value is bcrypt over |
| [`PasswordReset`](#passwordreset) | final readonly class | 45 | Password reset keys in the reference's storage shape: user_activation_key |
| [`Phpass`](#phpass) | final class | 60 | The portable phpass hash ($P$), from Openwall's public description of the |
| [`PortableHash`](#portablehash) | final class | 60 | The portable phpass hash ("$P$"), the shape the reference stores in |
| [`Roles`](#roles) | final class | 64 | Role definitions from the site's {prefix}user_roles option, parsed by a |
| [`Salts`](#salts) | final class | 34 | The site's own secret material, read from the constants wp-config.php |
| [`Sessions`](#sessions) | final readonly class | 155 | The session_tokens usermeta store: {sha256(token): {expiration, ip, ua, |
| [`TypeCapabilities`](#typecapabilities) | final readonly class | 36 | The capability names a post type's permissions are built from. Posts and |

## ApplicationPasswords

`final readonly class Minn\Auth\ApplicationPasswords` · `public/minn/src/Minn/Auth/ApplicationPasswords.php`

Application passwords as the reference stores them: a serialized list in
the user's _application_passwords meta, each entry uuid, app_id, name,
password (a hash), created, last_used, last_ip. New passwords are 24
characters shown in groups of four; the stored hash is phpass, which the
reference verifies too, so a password made here survives a switch back.
A hash the reference made with its own fast scheme cannot be verified here.

- const `META` = `'_application_passwords'`

Used by: `Minn\Auth\Authenticator`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`

```php
__construct(Minn\Content\Users $users)
```


### `all(int $userId): array`

- `@return list<array<string, mixed>>`

### `find(int $userId, string $uuid): ?array`

- `@return array<string, mixed>|null`

### `create(int $userId, string $name, string $appId): array`

- `@return array{0: array<string, mixed>, 1: string} the stored record and the one-time plaintext`

### `rename(int $userId, string $uuid, string $name): ?array`

### `touch(int $userId, string $uuid, string $ip): ?array`

Records a use: the time and the address it came from.

### `delete(int $userId, string $uuid): ?array`

### `deleteAll(int $userId): int`

### `verify(int $userId, string $password): ?array`

The record a plaintext (spaces ignored) unlocks for the user, or null.

### static `generate(): string`

Twenty-four letters and digits.

### static `chunked(string $plain): string`

The plaintext as shown once: groups of four, space separated.

Internals: `change()` (private, line 125), `save()` (private, line 140), `uuid()` (private, line 145)


## AuthCookies

`final readonly class Minn\Auth\AuthCookies` · `public/minn/src/Minn/Auth/AuthCookies.php`

The three cookies a sign-in sets: the auth cookie on the admin and
plugins paths (the secure variant over HTTPS, keyed off that scheme's
salt) and the logged_in cookie on the site root, which is the one REST
reads. Every value shares the username|expiration|token|hmac shape.

Used by: `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Login\LoginController`

```php
__construct(Minn\Db $db, Minn\Auth\Cookie $cookie)
```


### `hash(): string`

The hash suffixed onto every cookie name: md5 of the siteurl option.

### `attach(Minn\Http\Response $response, Minn\Content\UserRecord $user, int $expiration, string $token, bool $secure, bool $persistent = false): Minn\Http\Response`

A non-persistent sign-in keeps the server expiry but sends session cookies the browser drops on close.

### `clear(Minn\Http\Response $response): Minn\Http\Response`

### static `mint(Minn\Content\UserRecord $user, int $expiration, string $token, string $scheme): string`

A cookie value under any scheme's salt (auth, secure_auth, logged_in).


## AuthFailure

`final readonly class Minn\Auth\AuthFailure` · `public/minn/src/Minn/Auth/AuthFailure.php`

Why a request is not authenticated, as the reference's error code: a
missing or invalid cookie is rest_not_logged_in (the request is simply
anonymous); a good cookie with a bad nonce is rest_cookie_invalid_nonce.

Used by: `Minn\Auth\Authenticator`, `Minn\Rest\Caller`

```php
__construct(string $code)
```

- readonly `string $code`

### static `notLoggedIn(): self`

### static `invalidNonce(): self`


## Authenticated

`final readonly class Minn\Auth\Authenticated` · `public/minn/src/Minn/Auth/Authenticated.php`

A validated session: the user row and the raw session token behind it.

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Auth\Authenticator`, `Minn\Auth\Cookie`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Rest\Caller`

```php
__construct(Minn\Content\UserRecord $user, string $token, ?array $applicationPassword = NULL)
```
- `@param array<string, mixed>|null $applicationPassword the record that authenticated this call, when Basic auth did`

- readonly `Minn\Content\UserRecord $user`
- readonly `string $token`
- readonly `?array $applicationPassword`

### `id(): int`


## Authenticator

`final readonly class Minn\Auth\Authenticator` · `public/minn/src/Minn/Auth/Authenticator.php`

Resolves the current user two ways. A page load carries the cookie alone;
a REST call must also carry a nonce bound to the same session.

Used by: `Minn\Admin\AppController`, `Minn\Engine`, `Minn\Front\CommentPostController`, `Minn\Login\LoginController`, `Minn\Rest\Api`, `Minn\Rest\ApplicationPasswordsController`, `Minn\Rest\Caller`, `Minn\Rest\IndexController`

```php
__construct(Minn\Auth\Cookie $cookie, Minn\Content\Users $users)
```


### static `fromDb(Minn\Db $db): self`

### `session(array $cookies): Minn\Auth\Authenticated|Minn\Auth\AuthFailure`

- `@param array<string, string> $cookies`

### `rest(array $cookies, ?string $nonce): Minn\Auth\Authenticated|Minn\Auth\AuthFailure`

- `@param array<string, string> $cookies`

### `restFromRequest(Minn\Http\Request $request): Minn\Auth\Authenticated|Minn\Auth\AuthFailure`

A REST caller: the cookie session with its nonce first; failing that,
HTTP Basic credentials carrying an application password, which need no
nonce. A wrong Basic pair is reported as not logged in.

### `applicationPassword(Minn\Http\Request $request): ?Minn\Auth\Authenticated`

The user an Authorization: Basic header's application password unlocks, with the use recorded.

### static `applicationPasswordsAvailable(Minn\Http\Request $request): bool`

Application passwords need HTTPS, unless plugin code says otherwise through the reference's filter.

### static `applicationPasswordsAvailableFor(Minn\Content\UserRecord $user): bool`

- `@param array<string, mixed> $user`

### `login(string $username, string $password): ?Minn\Content\UserRecord`

Username and password to a user row; no session is created here.


## Capabilities

`final readonly class Minn\Auth\Capabilities` · `public/minn/src/Minn/Auth/Capabilities.php`

The capability engine: a user's roles from {prefix}capabilities usermeta,
the primitives those roles grant, and the meta-capability mapping for
edit_post, delete_post, and read_post.

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\AdminTypes`, `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\Dashboard`, `Minn\Admin\HiddenIntegrations`, `Minn\Admin\LanguageController`, `Minn\Admin\Notifications`, `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Front\CommentPostController`, `Minn\Rest\Api`, `Minn\Rest\Caller`, `Minn\Runtime\Runtime`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Auth\Roles $roles)
```


### static `fromDb(Minn\Db $db): self`

### `roles(): Minn\Auth\Roles`

### `rolesOf(int $userId): array`

- `@return list<string> role slugs`

### `primitivesOf(int $userId): array`

- `@return array<string, true> the union of primitives the user's roles grant`

### `can(int $userId, string $capability, ?int $postId = NULL): bool`

Does the user hold every primitive the (possibly meta) capability maps to?

### `map(string $capability, int $userId, ?int $postId = NULL): array`

The primitives a capability requires, all of which must be held.

- `@return list<string>`

Internals: `mapPostCapability()` (private, line 104), `fold()` (private, line 152), `trashedFrom()` (private, line 157)


## Cookie

`final readonly class Minn\Auth\Cookie` · `public/minn/src/Minn/Auth/Cookie.php`

The logged_in auth cookie: username|expiration|token|hmac, with
key  = HMAC-md5(username|fragment|expiration|token, logged_in salt)
hmac = HMAC-sha256(username|expiration|token, key)
and the session live in the user's session_tokens store.

Used by: `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Engine`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Auth\Sessions $sessions)
```


### `name(): string`

The cookie name this site uses: a hash of the siteurl option.

### `mint(Minn\Content\UserRecord $user, int $expiration, string $token): string`

### `validate(string $value): ?Minn\Auth\Authenticated`

### `fromJar(array $cookies): ?string`

The logged_in cookie from a request's cookie jar: the canonical name
first, then any logged_in cookie, since tooling may send a name
derived from a different host.

- `@param array<string, string> $cookies`

Internals: `signature()` (private, line 84)


## LoginThrottle

`final readonly class Minn\Auth\LoginThrottle` · `public/minn/src/Minn/Auth/LoginThrottle.php`

Failed sign-ins per address, so a password guesser meets a wall: twenty
failures in fifteen minutes and the address waits out the window. The
counters live in the options table (autoload off), one row per address,
as "window start:count"; a successful sign-in clears the row.

- const `LIMIT` = `20`
- const `WINDOW` = `900`
- const `PREFIX` = `'minn_login_throttle_'`

Used by: `Minn\Engine`, `Minn\Login\LoginController`

```php
__construct(Minn\Db $db)
```


### `retryAfter(string $address, ?int $now = NULL): ?int`

Seconds the address must wait, or null when it may try.

### `recordFailure(string $address, ?int $now = NULL): void`

### `clear(string $address): void`

Internals: `read()` (private, line 55), `write()` (private, line 64), `prune()` (private, line 76), `key()` (private, line 86)


## Nonce

`final class Minn\Auth\Nonce` · `public/minn/src/Minn/Auth/Nonce.php`

The wp_rest nonce: ten characters of HMAC-md5(tick|wp_rest|uid|token)
under the nonce salt, accepted for the current tick and the one before.

- const `LIFETIME` = `86400`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Auth\Authenticator`, `Minn\Front\AdminBar`, `Minn\Front\Resolver`, `Minn\Login\LoginController`, `Minn\Rest\RevisionsController`

### static `tick(): float`

### static `create(int $userId, string $token, string $action = 'wp_rest'): string`

### static `verify(string $nonce, int $userId, string $token, string $action = 'wp_rest'): bool`

### static `at(float $tick, int $userId, string $token, string $action): string`


## Password

`final class Minn\Auth\Password` · `public/minn/src/Minn/Auth/Password.php`

The stored password scheme. A modern "$wp$2y$..." value is bcrypt over
base64(HMAC-sha384(password, "wp-sha384")); a bare "$2y$" value is plain
bcrypt. Legacy phpass "$P$" hashes are not verified.

Used by: `Minn\Auth\ApplicationPasswords`, `Minn\Auth\AuthCookies`, `Minn\Auth\Authenticator`, `Minn\Auth\Cookie`, `Minn\Cli\UserCommand`, `Minn\Content\Users`, `Minn\Login\LoginController`, `Minn\Rest\UsersController`

### static `verify(string $password, string $hash): bool`

### static `hash(string $password): string`

A stored hash in the modern scheme: "$wp" plus bcrypt over the pre-hash.

### static `fragment(string $hash): string`

The four characters of the hash the cookie key is derived from: the
LAST four for a "$wp$" hash, offset 8 for legacy phpass. Getting this
wrong yields a cookie that verifies nowhere while every other check
passes, so both branches are pinned by the auth suite.


## PasswordReset

`final readonly class Minn\Auth\PasswordReset` · `public/minn/src/Minn/Auth/PasswordReset.php`

Password reset keys in the reference's storage shape: user_activation_key
holds "time:hash", the key itself travels in the email link and is good
for a day. The hash is the engine's own ($minn$ over the nonce salt); a
key the reference issued ($generic$) is not readable here, so it is
refused and the reader asks for a fresh link.

- const `LIFETIME` = `86400`

Used by: `Minn\Cli\UserCommand`, `Minn\Engine`, `Minn\Login\LoginController`, `Minn\Rest\UsersController`

```php
__construct(Minn\Content\Users $users)
```


### `issue(Minn\Content\UserRecord $user): string`

Mints a key, stores its hash, returns the key for the link.

### `verify(Minn\Content\UserRecord $user, string $key): bool`

True when the key matches the stored hash and has not expired.

### `status(Minn\Content\UserRecord $user, string $key): string`

"valid", "expired" (a matching key past its day), or "invalid".

### `clear(Minn\Content\UserRecord $user): void`

Internals: `hash()` (private, line 57)


## Phpass

`final class Minn\Auth\Phpass` · `public/minn/src/Minn/Auth/Phpass.php`

The portable phpass hash ($P$), from Openwall's public description of the
scheme: a log2 iteration count, an eight-character salt, and an iterated
MD5 in phpass's own base-64 alphabet. The reference still verifies these
for application passwords, which makes them the portable choice.

- const `ALPHABET` = `'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'`

Used by: `Minn\Auth\ApplicationPasswords`

### static `hash(string $password, int $log2Rounds = 11): string`

### static `verify(string $password, string $hash): bool`

Internals: `crypt()` (private, line 31), `encode()` (private, line 47)


## PortableHash

`final class Minn\Auth\PortableHash` · `public/minn/src/Minn/Auth/PortableHash.php`

The portable phpass hash ("$P$"), the shape the reference stores in
the post-password cookie: an iteration count character, an eight
character salt, and MD5 iterated over salt and password, encoded in
phpass's own base64 alphabet. Implemented from the published algorithm
so cookies are accepted in both directions.

- const `ALPHABET` = `'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'`

Used by: `Minn\Content\PasswordGate`, `Minn\Login\LoginController`

### static `hash(string $password, int $countLog2 = 13): string`

The reference's cookies carry 2^13 iterations; it accepts nothing weaker.

### static `verify(string $password, string $hash): bool`

Internals: `crypt()` (private, line 33), `encode()` (private, line 48)


## Roles

`final class Minn\Auth\Roles` · `public/minn/src/Minn/Auth/Roles.php`

Role definitions from the site's {prefix}user_roles option, parsed by a
bounded scan of the serialized blob, with the seeded contract copy as the
fallback for a fresh install.

- const `LEVELS` = `array (   'administrator' => 10,   'editor' => 7,   'author' => 2,   'contributor' => 1,   'subscriber' => 0, )`

Used by: `Minn\Auth\Capabilities`, `Minn\Cli\UserCommand`, `Minn\Content\Users`, `Minn\Rest\UsersController`

```php
__construct(Minn\Db $db)
```


### static `level(string $role): int`

The wp_user_level stored beside the capabilities meta.

### static `serializeSingle(string $role): string`

The {role: true} capabilities meta in its serialized form.

### `forget(): void`

Drops the parsed map so the next read sees a rewritten option.

### `all(): array`

### static `parse(?string $blob): ?array`

Each role is a string key over a two-entry array of "name" and
"capabilities"; the capabilities map is {cap: bool}. Null when the
blob has another shape, so the caller can fall back.


## Salts

`final class Minn\Auth\Salts` · `public/minn/src/Minn/Auth/Salts.php`

The site's own secret material, read from the constants wp-config.php
defines, so the engine keys off exactly what the install used.

- const `SCHEMES` = `array (   'logged_in' =>    array (     0 => 'LOGGED_IN_KEY',     1 => 'LOGGED_IN_SALT',   ),   'nonce' =>    array (     0 => 'NONCE_KEY',     1 => 'NONCE_SALT',   ),   'auth' =>    array (     0 => 'AUTH_KEY',     1 => 'AUTH_SALT',   ),   'secure_auth' =>    array (     0 => 'SECURE_AUTH_KEY',     1 => 'SECURE_AUTH_SALT',   ), )`

Used by: `Minn\Auth\AuthCookies`, `Minn\Auth\Cookie`, `Minn\Auth\Nonce`, `Minn\Auth\PasswordReset`, `Minn\Engine`, `Minn\Front\CommentPostController`

### static `configured(): bool`

True when every key and salt is defined and none is the installer's placeholder.

### static `for(string $scheme): string`

### static `hash(string $data, string $scheme): string`

HMAC-md5 under the scheme's salt: the primitive every cookie and nonce is built from.


## Sessions

`final readonly class Minn\Auth\Sessions` · `public/minn/src/Minn/Auth/Sessions.php`

The session_tokens usermeta store: {sha256(token): {expiration, ip, ua,
login}}. Read by a bounded scan of the serialized blob and written by
serializing it ourselves, so stored data is never executed.

Used by: `Minn\Admin\SessionsController`, `Minn\Auth\Authenticator`, `Minn\Auth\Cookie`, `Minn\Engine`, `Minn\Login\LoginController`, `Minn\Rest\Api`

```php
__construct(Minn\Content\Users $users)
```


### static `generateToken(): string`

A 43-character token; only sha256(token) is ever stored or compared.

### `isLive(int $userId, string $token): bool`

### `create(int $userId, int $expiration, string $ip, string $userAgent): string`

Creates a session, pruning expired ones, and returns the raw token.

### `destroyAll(int $userId): void`

Ends every session of the user (a password change).

### `destroy(int $userId, string $token): bool`

Removes one session; true when it existed.

### `destroyKey(int $userId, string $key): bool`

Removes the session stored under a key (the sha256 of its token); true when it existed.

### `destroyOthers(int $userId, string $keepKey): void`

Ends every session of the user except the one stored under the given key.

### `read(int $userId): array`

- `@return array<string, array{expiration: int, ip: string, ua: string, login: int}>`

### static `serialize(array $sessions): string`

The map in PHP's serialized form, entry keys in the order given.

Internals: `parseEntry()` (private, line 120), `prune()` (private, line 138), `write()` (private, line 144), `string()` (private, line 164)


## TypeCapabilities

`final readonly class Minn\Auth\TypeCapabilities` · `public/minn/src/Minn/Auth/TypeCapabilities.php`

The capability names a post type's permissions are built from. Posts and
pages own their families (edit_posts, edit_others_pages, ...). Navigation
menus fold every one of them onto edit_theme_options, which is why an
editor may read a menu and only an administrator may change one; the
reference's own registration says the same thing.

- const `FOLDED` = `array (   'wp_navigation' => 'edit_theme_options', )` — Types whose whole cap family collapses to a single name.

Used by: `Minn\Auth\Capabilities`, `Minn\Rest\PostObject`, `Minn\Rest\PostsController`, `Minn\Rest\PostsWriteController`

### static `of(string $type, string $capability): string`

A cap name built from the post/page family, folded when the type folds.

### static `plural(string $type): string`

### static `edit(string $type): string`

### static `editOthers(string $type): string`

### static `publish(string $type): string`

### static `readPrivate(string $type): string`

