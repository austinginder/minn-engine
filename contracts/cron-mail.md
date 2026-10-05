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

Triggers, each running the same job (`Cron::create` is the one recipe):

- `wp-cron.php` (any method, `doing_wp_cron` ignored; answers 200 with an empty
  body, as the reference does; `DISABLE_WP_CRON` honoured) fires due hooks and
  publishes due posts every time, inline, as the reference's own wp-cron.php runs
  its jobs before it answers. `DOING_CRON` is defined before the plugins load, as
  the reference's wp-cron.php defines it, so a callback that asks `wp_doing_cron()`
  hears yes;
- `wp minn cron` (and `minn cron`): boots the full runtime under `DOING_CRON`, so
  due hooks fire and the daily auto-update check runs; this is the verb a system
  cron calls. `wp cron event run` fires a hook without `DOING_CRON`, as WP-CLI does;
- an ordinary front request that finds a due post or a due event (one indexed
  query for the post, one read of the `cron` option) runs the job **after its
  response is sent**: the page goes out, the connection closes
  (`fastcgi_finish_request`, which FrankenPHP and PHP-FPM provide; a plain flush
  elsewhere), and the run follows in the same process with `DOING_CRON` defined.
  The reference spawns a background loopback request for the same work; the
  engine's way needs no loopback, so a host that blocks loopback requests (a
  classic WordPress cron failure) is not a failure here. A due post therefore
  shows on the request after the one that found it, as it does on the reference.

Order within a run: due posts publish first, then the option's events fire, then
the sweeps, then (once a day) the auto-update pass. A callback that throws ends the
events step and is reported (`scheduled events stopped: …`, also in the error log);
the event was already rescheduled or removed, so it does not repeat, and the sweeps
still run. The auto-update stamp is written before the pass so a failing update is
retried the next day rather than on every trigger; each refusal is reported by name
(`automatic update of {file} refused: {reason}`).

The same run sweeps expired transients (`_transient_timeout_*` past, both rows go)
and expired sign-in throttle rows.

The System view's cron list (`minn-admin/v1/system/cron`, `Ops\Diagnostics::cron`)
shows the option's events (hook, next run, recurrence as its two largest units,
argument count) beside the engine's scheduled posts, soonest first; `disabled`
reflects `DISABLE_WP_CRON`. The option is read through `Runtime\CronTable::fromBlob`,
the one reader of the stored blob in `src/Minn`.

The engine does not spawn the reference's loopback and does not write its
`doing_cron` transient; the sixty-second `minn_cron_lock` option is the run lock.

## Mail

`Minn\Mail\Mailer` sends the engine's own plain-text mail. With WordPress's
runtime loaded it goes through `wp_mail()`, so the mail filters and the
`phpmailer_init` hook (how an SMTP plugin takes over) apply as on the reference,
and the sender is the reference's `WordPress <wordpress@{host}>`. The `minn_mail`
option (JSON, or a serialized array from `wp option update --format=json`) is the
site's own transport, applied inside `wp_mail()` before `phpmailer_init`: `mail`
(PHP's `mail()`, the default), `smtp` (`host`, `port`, `encryption` ssl, tls or
none, `username`, `password`; PHPMailer's SMTP client), or `log` (appends JSON
lines to `wp-content/minn-mail.log`, for development); `from_email` and
`from_name`, when set, replace the default sender before the `wp_mail_from`
filters. Without the runtime, `Mailer` composes the message itself
(`Mail\Composer`) and delivers it over the same transports, defaulting to the
site name at `no-reply@` the home host. `wp minn mail <to>` sends a test message
and names the transport. How a message is written and sent: `contracts/runtime.md`,
"Sending mail".

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
