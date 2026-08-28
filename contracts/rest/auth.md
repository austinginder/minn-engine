# Contract: authentication (cookies, sessions, REST nonces)

Status: implemented for the logged_in cookie and the `wp_rest` nonce, read
paths only. Suite: `tests/auth.test.php` (16 checks, both directions).

The bar this surface has to clear is higher than shape parity, because a
session is a shared secret between the browser, the engine, and whatever
WordPress tooling still touches the site. **Both directions are proven:** a
session minted by WordPress authenticates against the engine, and a cookie
minted by the engine authenticates against WordPress, byte-identical.

## The scheme

Cookie name: `wordpress_logged_in_` + `md5( siteurl )`.

Cookie value: `username|expiration|token|hmac`, where

```
pass_frag = last 4 chars of a "$wp$"-prefixed hash,
            else substr( user_pass, 8, 4 )
key       = HMAC-md5( "username|pass_frag|expiration|token", LOGGED_IN_KEY . LOGGED_IN_SALT )
hmac      = HMAC-sha256( "username|expiration|token", key )
```

Session validity: `sha256( token )` must be a live key in the user's
`session_tokens` usermeta, whose entry carries an `expiration` timestamp.

REST nonce (`wp_rest` action, logged-in user):

```
tick  = ceil( time() / ( 86400 / 2 ) )
nonce = substr( HMAC-md5( "tick|wp_rest|uid|token", NONCE_KEY . NONCE_SALT ), -12, 10 )
```

The current tick and the one before it are both accepted, which is what
gives a nonce its ~12-to-24-hour life.

## THE password-fragment trap (cost an hour, worth the note)

Every description of WordPress auth says the cookie key is derived from
`substr( $user->user_pass, 8, 4 )`. That is true only for the historical
phpass format (`$P$B…`). Modern installs store `$wp$2y$…` (bcrypt behind a
wrapper), and those derive from the **last four characters** instead.

The failure mode is nasty: the cookie parses, the user is found, the session
is live, the nonce verifies, and only the HMAC comparison fails, so every
symptom points at salts or at the nonce. It was found by brute-forcing the
fragment window against a known-good WordPress-minted cookie (start=59,
len=4 on a 63-char hash) and confirmed against a second user with a
different password. `minn_pass_fragment()` branches on the `$wp$` prefix and
both branches are pinned.

## Behavior matrix (engine and reference agree on every rung)

| Request to `/wp/v2/users/me` | Status | Code |
|---|---|---|
| No cookie, no nonce | 401 | `rest_not_logged_in` |
| Valid cookie, no nonce | 403 | `rest_cookie_invalid_nonce` |
| Valid cookie, wrong nonce | 403 | `rest_cookie_invalid_nonce` |
| Malformed cookie, valid nonce | 401 | `rest_not_logged_in` |
| Valid cookie, nonce minted for another user | 403 | `rest_cookie_invalid_nonce` |
| Valid cookie + matching nonce | 200 | the user object |

Note the ordering: cookie failures are 401 and nonce failures are 403, so a
caller can tell "you are not signed in" from "your nonce is stale" — which
is exactly the distinction a client needs to decide between re-authenticating
and re-fetching a nonce.

## Users surface

- Public list contains only users who authored published `post` or `page`
  content, ordered by display name. `X-WP-Total` counts that same set.
- Public user objects carry `id`, `name`, `url`, `description`, `link`,
  `slug`, `avatar_urls`, `meta`, `_links` — never email, roles, or
  capabilities.
- `avatar_urls` are Gravatar URLs keyed 24/48/96, hashed with **sha256** of
  the lowercased, trimmed email (not the historical md5), with `?s={size}&d=mm&r=g`.
- `_links.self[0].targetHints.allow` is `["GET"]` for other users and the
  full write verb list when the authenticated caller views their own record.
- Errors: `rest_user_invalid_id` "Invalid user ID." (404).

## Known gaps

- Application passwords and the `Authorization` header path.
- `context=edit` on users (email, roles, capabilities, registered_date).
- Login itself: no `wp-login.php` equivalent, so sessions are still minted by
  WordPress. The engine can verify and mint cookie values but does not yet
  create session rows, log in, or log out.
- Nonce lifetime assumes the default `nonce_life`; a site filtering it would
  diverge.
- No capability model yet, so nothing consumes the authenticated user beyond
  `users/me` and the self-view hint.
