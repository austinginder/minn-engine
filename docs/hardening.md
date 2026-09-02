# Hardening

What the engine does on its own, and what the server in front of it must do.

## In the engine

- **No stack traces.** `Minn\Http\Failure` turns `display_errors` off unless the site's
  `wp-config.php` sets `WP_DEBUG_DISPLAY` (or `WP_DEBUG`) on, sends a plain 500 page for
  any uncaught error or fatal, logs the cause with `error_log` (to
  `wp-content/debug.log` when `WP_DEBUG_LOG` is true, or the path it names), and a plain
  503 with `Retry-After` when the database cannot be reached.
- **Sign-in throttle.** `Minn\Auth\LoginThrottle`: twenty failed sign-ins from one
  address in fifteen minutes, and that address gets 429 with `Retry-After` until the
  window ends. Password and one-time-link failures share the counter, and a successful
  sign-in does not reset it (one owned account must not launder guesses at another);
  the window lapses on its own. Rows live in `wp_options` as `minn_login_throttle_*`,
  autoload off, written as one upsert so two first failures cannot race. A sign-in
  for a username that does not exist still verifies the password against a real
  hash of a password nobody knows, so the answer takes as long either way.
- **Error pages stand alone.** A failure part-way through a render (a classic
  template that throws, a fatal) discards every open output buffer before the error
  page is sent, so no half of a page precedes it.
- **Mail headers are one line each.** A caller's custom header name or value has its
  line breaks folded to spaces and NUL removed before it is written, so a plugin
  cannot inject a header through one.
- **Stored markup.** Callers without `unfiltered_html` have their post, media, comment,
  term, and profile markup filtered by `Minn\Support\Kses`, an allowlist written for
  the engine and matched against the reference's results. A URL attribute is judged
  on a copy decoded as deep as it goes (character references, named and numeric,
  with or without semicolons, and percent escapes, repeated until nothing changes)
  with whitespace and control characters removed: whatever stands before the first
  colon must be an allowed scheme or it is cut off and the rest judged again, so
  `&#106;avascript:`, `javascript&colon;`, `jav&#x09;ascript:` and `j%61vascript:`
  all lose their scheme exactly as the reference cuts them. Every attribute value is
  stored normalised the way the reference stores it (references decoded to their
  characters, the five markup characters kept escaped, an apostrophe as `&apos;`,
  invalid references and stray ampersands as `&amp;`); text between tags keeps its
  valid references, pads decimal ones to three digits, and escapes stray brackets.
  `tests/unit/kses.php` pins seventy captured cases. Password-protected posts
  show only the password form.
- **Packages.** `Minn\Admin\Packages` is the one way a theme, plugin, or extension
  reaches disk. A folder name is a plain name (never `.` or `..`) and the path it
  resolves to must be a direct child of `themes/` or `plugins/` before anything is
  removed or replaced. An archive must hold exactly one such folder, no absolute or
  dotted paths, no symbolic-link entries, at most twenty thousand entries, and at most
  512 MB unpacked. Downloads go through `Minn\Http\Download`: https on every redirect
  hop (at most five), a wordpress.org update must stay on `downloads.wordpress.org`
  across every hop, bodies over the cap fail rather than truncate. A language pack
  installs only when the bundle manifest names its SHA-256 and the download matches
  it. Every wordpress.org update records the archive's SHA-256, version, and package
  URL under `archives` in the `minn_updates` option, so an audit can ask what code
  arrived and when.
- **Sign-in and sign-out.** `redirect_to` is honoured for this site's own URLs only;
  logging out destroys the server-side session and needs the session's `log-out`
  nonce (a confirmation page stands in when it is missing).
- **Headers.** Every response carries `X-Content-Type-Options: nosniff`. The sign-in page
  and the admin add `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy:
  strict-origin-when-cross-origin`, and a no-store `Cache-Control`, which is what the
  reference sends there.
- **Cookies.** Auth cookies are `HttpOnly`, `SameSite=Lax`, `Secure` when the request
  was HTTPS, on the reference's paths (`/wp-admin`, `/wp-content/plugins`, and `/` for
  the logged-in cookie).
- **Salts.** The engine refuses to serve until `wp-config.php` carries real keys and
  salts; the installer placeholder counts as missing.
- **Addresses.** The throttle keys on `REMOTE_ADDR`. Behind a proxy or CDN that is the
  proxy's address; terminate that at the server (have it rewrite the client address
  into `REMOTE_ADDR`, as Caddy's `trusted_proxies` and nginx's `real_ip` do) rather
  than trusting `X-Forwarded-For` in the engine.
- **Database.** Every query is a prepared statement through `Minn\Db`. Serialized blobs
  are read by tolerant scanners and the engine's own decoder; nothing calls
  `unserialize()` on data from the database or a request.

## At the server

The one rule the engine cannot enforce for itself: **PHP must not execute under
`wp-content/uploads/`** (or anywhere under `wp-content/` except the engine's own
`minn/` folder, which is outside it). Uploads are user-supplied files.

Caddy (Cove, FrankenPHP):

```
@uploads_php path_regexp ^/wp-content/uploads/.*\.(php|phtml|phar)(\.|$)
respond @uploads_php 403
```

nginx:

```
location ~* ^/wp-content/uploads/.*\.(php|phtml|phar)(\.|$) { return 403; }
```

Apache (`wp-content/uploads/.htaccess`):

```
<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
```

Also at the server: TLS with HSTS, `client_max_body_size` in line with the upload
limit, and `X-Powered-By` stripped if the PHP version should not be advertised (the
engine's own header names Minn).
