# The round trip

The second definition of done (`docs/vision.md` §3) is that any WordPress
site can resume on Minn: nothing lost, nothing locked in, nothing stops.
`tests/round-trip.test.php` is the test for it. It takes a copy of a real
site, gives WordPress and Minn the same day of work, compares what each
left behind row by row, then hands Minn's result back to WordPress and
checks that WordPress can read it and keep going.

The parity suites ask whether the engine answers like WordPress. This one
asks the question a site owner asks: if I switch, work for a day, and switch
back, is my site whole?

## The site

A local copy of a production site, with Minn installed in `public/` and
WordPress parked in `wp-reference/` (`wp minn install --park`). The first
one is `cove-minn.localhost`, a copy of cove.run (a classic PHP theme,
Gravity Forms, CleanTalk, CoBlocks, Code Snippets, Minn Admin, a WP
Freighter tenant with the `stacked_15_` prefix). `MINN_ROUNDTRIP_ROOT`
points the suite elsewhere.

How the first copy was made, so the next one can be:

1. `cove add` the local site; stream the database from the host with
   `wp db export -` over SSH (read-only: nothing is written remotely) and
   import it; copy `wp-content`; search-replace the URLs with WP-CLI on the
   parked copy.
2. Install the engine (`wp minn install --park=wp-reference`), copy
   `wp-config.php` into the parked copy, symlink its `wp-content` back to
   `public/wp-content`, and give it a `router.php`.
3. Set a local password for an administrator, write it to
   `private/round-trip.json` (`user`, `password`, `url`, `oracle`), and
   export the starting point to `private/baseline.sql`.

Nothing from the site enters this repository: the sign-in, the baseline,
and the report stay in the site's `private/` folder.

Two pieces make the copy fit to test on:

- **It runs offline.** `wp-content/mu-plugins/zz-round-trip-offline.php`
  refuses outbound HTTP to anything but this machine (spam checks, license
  pings and update checks never leave) and defines `DISABLE_WP_CRON`, so
  page loads run no scheduled jobs. Both stacks load it, so a day's
  footprint is the work and nothing else.
- **The oracle stands in for the site over HTTPS.** `php -S 127.0.0.1:8129
  router.php` serves a request as HTTPS when it carries `X-Forwarded-Proto:
  https`, and the suite sends `Host: cove-minn.localhost` with it, so
  WordPress signs in with secure cookies and builds links exactly as it
  does for the real site. Every request goes over IPv4 on both stacks, so
  plugins that key visitors by address (CleanTalk) see `127.0.0.1` on each.

## The day

1. Restore `baseline.sql` (tables a day created are dropped) and take a
   snapshot.
2. **WordPress has the day.** A visitor browses (the front page, a post
   and one of its comment pages, the feed, a search, a missing page, the
   REST posts index, the sign-in page). Then the owner signs in through
   `wp-login.php`, changes the tagline, revises the oldest post and renames
   it, uploads a photo (made on the spot, the same bytes every run) and
   describes it, adds a category and a tag, publishes a post that uses all
   of them with the photo featured and in the content, comments and
   replies, revises a page, updates the profile through
   `/wp/v2/users/me`, adds an editor, and trashes a draft. The snapshot
   after is WordPress's footprint; the files it uploaded are removed.
3. Restore, and **Minn has the same day**, request for request. Browsing
   compares each answer's status and where it redirects.
4. **WordPress takes it back**, on Minn's database: signs the owner in with
   the same password, reads the new post (title, status, category, tag,
   featured photo, the photo in the content), renders it, sends the
   renamed post's old address on to the new one (Minn does too), reads the
   photo's description and finds every size on disk, reads the revised
   post and its revisions, the comment thread, the tagline, the page, the
   profile and the trashed draft, signs Minn's new editor in as an editor,
   accepts the session Minn signed in without a second sign-in, then keeps
   working (revises Minn's post, answers the reply), and Minn reads what
   WordPress wrote.
5. Restore, remove every uploaded file, and check the site is back at its
   baseline.

## Footprints

A snapshot is every table with the site's prefix, rows keyed by what they
are: options by name, meta by owner and key (with a count for repeats),
everything else by primary key. A footprint is what changed from one
snapshot to the next, one line per row (`added`, `removed`, or the columns
that changed; serialized arrays are compared leaf by leaf). To lay two
stacks' days side by side:

- times inside the run read `{now}` (as UTC or the site's local time), and
  Unix times a common span after it read `{now+14d}` and the like;
- password, reset and application-password hashes read `{hash}`, session
  keys `{sha256}`, UUIDs `{uuid}`, loopback addresses `{loopback}`;
- rows the day created read by kind and order (`{attachment#1}`,
  `{revision#2}`, `{user#1}`) wherever their IDs appear, so one missing row
  does not renumber the rest;
- the auto-increment IDs of meta and option rows never compare (nothing
  points at them).

Transients are left out of both footprints: they are caches. Everything
else is compared, and every difference has to be fixed or explained.

## Explained differences

Each is in `$EXPLAINED` in the suite with a test narrowing it to the part of
the row the reason is about, so anything else on that row still fails.

- **Minn's own options** (`minn_*`): the plugin symbol scan and similar
  bookkeeping, in options WordPress never reads.
- **Pingbacks.** Publishing on WordPress adds `_pingme` and `_encloseme` to
  the post and schedules `do_pings`: its to-do list for pingbacks,
  trackbacks and enclosure checks. Minn sends none of those, so it queues
  none. A site switched back finds no pings owed for posts written on Minn.
- **Image bytes.** The parked WordPress cuts sizes with Imagick, the engine
  with GD: the same files at the same dimensions, a different `filesize`.
- **CleanTalk's cron lock** is a fresh random number on every run.
- **The sign-in page** (`GET /wp-login.php`) sends a visitor to Minn
  Admin's (`/minn-admin/login`); the form's post is still taken there.

## What it found

The first runs, and what each became (each matched to the oracle):

| Finding | Where it is now |
|---|---|
| `POST /wp/v2/users/me` answered 404, so profile edits from any client but Minn Admin were lost | `contracts/rest/users.md` |
| A REST-made user was mailed a set-password link and stored a reset key; the reference does neither | `contracts/rest/users.md`, `contracts/cron-mail.md` |
| A REST-made user's display name was the login; the reference uses first and last name | `contracts/rest/users.md` |
| `user_count` never moved | `contracts/rest/users.md` |
| A new post's guid was always `?p=`; the reference stores the permalink for a live post | `contracts/rest/writes.md` |
| `categories: []` on create still gave the default category; an update never did | `contracts/rest/writes.md` |
| Trash only flipped the status: no `__trashed` slug, no desired slug, no revision, modified time unmoved | `contracts/rest/writes.md` |
| No `_wp_old_slug` / `_wp_old_date`, so a renamed post's old address was lost to WordPress | `contracts/rest/writes.md` |
| Uploads lacked the `1536x1536` / `2048x2048` sizes and ignored `intermediate_image_sizes_advanced` | `contracts/runtime.md` |
| A sign-in rewrote every stored session with four keys, dropping anything a plugin attached; an empty `ua` was stored | `contracts/runtime.md` |
| CleanTalk did not load (`wp_blacklist_check`); loaded, every page was a 500 (no-argument actions) | `contracts/runtime.md` |
| CleanTalk's settings (an `ArrayObject` inside an option) read back as a string, and the plugin saved defaults over them | `contracts/runtime.md` |
| Plugins heard nothing of a sign-in (`wp_login`, `wp_login_failed`, `wp_logout`) | `contracts/runtime.md` |
| `admin-ajax.php` did not declare `DOING_AJAX`, so a plugin took the nonce refresh for a page view | `contracts/runtime.md` |

## Open

Found by the round trip and not done yet:

- Comments are not paged: with `page_comments` on, `comment-page-N`
  serves the post with every comment.
- `authenticate` and the other sign-in filters do not run, so a plugin
  cannot refuse a sign-in or add a second factor.
- `admin-ajax.php` answers only `rest-nonce`; a plugin's `wp_ajax_*` and
  `wp_ajax_nopriv_*` actions are not dispatched.
- A stored object of a class a plugin has loaded comes back a plain object
  where the reference instantiates the class, so plugin code calling its
  methods fails on Minn. Written back it now keeps its class
  (`contracts/runtime.md`).
- Scheduled jobs are off in the copy; whether the site's cron keeps
  running on Minn is a second day to write.

Running it: `php tests/round-trip.test.php` (about twenty seconds; the
oracle on 8129 must be up, `run-all.sh` starts it).
`MINN_ROUNDTRIP_KEEP=1` leaves the site as the swap back made it, for
looking around; the next run restores it.
