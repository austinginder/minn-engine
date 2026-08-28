# Cron and mail

Suite: `tests/cron-mail.test.php` (25 checks, real mail through Mailpit on the dev
machine). Code: `Minn\Cron\Cron`, `Minn\Mail\*`, `Minn\Auth\PasswordReset`,
`Minn\Login\LoginController`.

## Scheduled posts

The engine has no hook runner, so it does not read the `cron` option. It publishes
scheduled posts directly: a `future` post whose `post_date_gmt` has passed gets
`post_status = publish` and nothing else changes (dates and `post_modified` stay,
the slug was set at scheduling), then the term counts are refreshed. Captured from
the reference's own publish of a due post.

Triggers, any of which runs every due job under a sixty-second lock:

- `wp-cron.php` (any method, `doing_wp_cron` ignored; answers 200 with an empty
  body, as the reference does; `DISABLE_WP_CRON` honoured);
- `wp minn cron`;
- a front request that finds a due post (one indexed query per request).

The same run sweeps expired transients (`_transient_timeout_*` past, both rows go)
and expired sign-in throttle rows.

Known gap: a post scheduled through the engine has no `publish_future_post` event in
the `cron` option, so after an eject WordPress will not publish it until it is saved
again; a post scheduled by WordPress before an install is published by the engine on
time (it reads the row, not the event).

## Mail

`Minn\Mail\Mailer` sends plain-text mail through one of three transports, chosen by
the `minn_mail` option (JSON, or a serialized array from `wp option update
--format=json`): `mail` (PHP's `mail()`, the default), `smtp` (own client: SSL or
STARTTLS, AUTH LOGIN, `host`, `port`, `encryption`, `username`, `password`), or `log`
(appends JSON lines to `wp-content/minn-mail.log`, for development). The sender is
`from_name <from_email>`, defaulting to the site name at `no-reply@` the home host.
`wp minn mail <to>` sends a test message and names the transport.

What sends mail today: the password reset link, the new-account message (login
details plus a link to choose a password), and the moderation notice to
`admin_email` when a comment lands in the queue and `moderation_notify` is on.

## Password reset

The reference's HTTP shapes, captured and matched:

- `GET wp-login.php?action=lostpassword` shows the form; `POST` with `user_login`
  (username or email) answers `302 ?checkemail=confirm`. An unknown account is
  refused on the form and counts toward the sign-in throttle.
- The email link is `wp-login.php?action=rp&key={20 chars}&login={user_login}`.
- `GET action=rp&key=…&login=…` stores `login:key` in the `wp-resetpass-{COOKIEHASH}`
  cookie (path `/wp-login.php`, HttpOnly) and answers `302 ?action=rp`; the form then
  loads from the cookie with the key in a hidden `rp_key` field. A bad or expired key
  answers `302 ?action=lostpassword&error=invalidkey`.
- `POST action=resetpass` with `pass1`, `pass2`, `rp_key` saves the password (hash in
  the `$wp$2y$` scheme, so the reference signs the user in), clears the key, ends
  every session of the user, and shows "Your password has been reset." with a 200.

Storage matches the reference's shape, `user_activation_key = "time:hash"`, valid for
a day, so a pending reset reads the same either way. The hash itself is the engine's
(`$minn$` over the nonce salt): the reference's `$generic$` key hash could not be
reproduced from observed output, so a key the reference issued is refused here (the
reader requests a fresh link) rather than mis-verified, and a key the engine issued is
refused by the reference. Recorded as a divergence; no security delta.
