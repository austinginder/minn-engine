# Cron and mail

Suites: `tests/cron-mail.test.php` (25 checks, real mail through Mailpit on the dev
machine) and `tests/cron.test.php` (24 checks: `wp cron` against the reference on the
same database, and the fixture plugin's due hook firing on every trigger). Code:
`Minn\Cron\Cron`, `Minn\Cli\CronCommand`, `Minn\Mail\*`, `Minn\Auth\PasswordReset`,
`Minn\Login\LoginController`.

## Scheduled events and posts

A run does two things: it fires the `cron` option's due hooks, and it publishes due
scheduled posts. Both happen under one sixty-second lock (`minn_cron_lock`).

**Due hooks.** The `cron` option holds every event a plugin scheduled
(`wp_schedule_event` and friends, `contracts/runtime.md` "Cron"). A run reads the
ready events (`wp_get_ready_cron_jobs`), reschedules the recurring ones, removes the
single ones, and fires each hook through the facade (`wp_cron` →
`do_action_ref_array`). Firing needs the booted runtime, so the caller passes it in
as a closure; a run without one (never in production) publishes posts and sweeps only.

**Scheduled posts.** A `future` post whose `post_date_gmt` has passed gets
`post_status = publish` and nothing else changes (dates and `post_modified` stay, the
slug was set at scheduling), then the term counts are refreshed. Captured from the
reference's own publish of a due post. This is by row, not by the `publish_future_post`
event, so a post scheduled by WordPress before an install is still published by the
engine on time.

Triggers:

- `wp-cron.php` (any method, `doing_wp_cron` ignored; answers 200 with an empty
  body, as the reference does; `DISABLE_WP_CRON` honoured) fires due hooks and
  publishes due posts every time. The reference dispatches a locked background
  loopback here; the engine runs the work inline under the same lock, which a
  single-worker server can serve and a monitor reading the endpoint cannot tell
  from the reference (an empty 200 either way);
- `wp minn cron` (and `minn cron`): boots the full runtime, so due hooks fire and
  the daily auto-update check runs; this is the verb a system cron calls;
- a front request that finds a due post runs the same job (one indexed query per
  request to find one).

The same run sweeps expired transients (`_transient_timeout_*` past, both rows go)
and expired sign-in throttle rows.

Known gap: the engine does not spawn a background loopback from an ordinary front
request the way the reference does, so a site with no server cron and no WP-CLI cron
relies on a visitor request that happens to publish a due post to also fire due
hooks. Managed hosts drive cron through `wp-cron.php` or `wp cron event run`, both of
which fire everything.

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
