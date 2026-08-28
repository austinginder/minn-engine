# Contract: wp/v2/settings

Status: implemented (read + write, the full registered payload). Suite:
`tests/settings.test.php` (10 checks: payload parity, the manage_options
gate on both verbs, cross-stack write round trips both directions,
unregistered-key behavior).

## Gate

`manage_options` on every method. Below it (editor, anonymous):
`rest_forbidden` — 401 anonymous, 403 authenticated. Nonce failure outranks
as everywhere.

## The payload (27 keys, capture order)

Plugin-registered keys lead: `blog_public` (int),
`minn_admin_maintenance` (bool, default false), `users_can_register`
(int), `default_role`, `comment_moderation` (int), `comment_registration`
(int), `show_avatars` (int). Then core's: `title` (blogname),
`description` (blogdescription), `url` (siteurl), `email` (admin_email),
`timezone` (timezone_string), `date_format`, `time_format`,
`start_of_week` (int), `language` (WPLANG; empty stored value serves
"en_US" and writing "en_US" stores ''), `use_smilies` (bool),
`default_category` (int), `default_post_format`, `posts_per_page` (int),
`show_on_front`, `page_on_front` (int), `page_for_posts` (int),
`default_ping_status`, `default_comment_status`, `site_logo`
(int or null; null when the option is absent), `site_icon` (int).

## Writes

POST/PUT/PATCH with a JSON object; registered keys update their mapped
options, unregistered keys are silently ignored; the response is the full
fresh payload. New option rows are written with autoload `auto`.

**The boolean null quirk (oracle-caught, kept):** core stores boolean
false as the empty string, and then cannot re-type '' on read — so after
ANY writer sets a boolean setting to false, the API serves `null` for it
(both stacks, both writers, verified). A missing option still serves the
registered default (`minn_admin_maintenance` → false). Do not "fix" the
engine to serve false for a stored ''.

## Known gaps

- Value validation (enum/range rejection with `rest_invalid_param`) is not
  implemented; writes coerce by type instead.
- `site_icon`/`site_logo` side effects (favicon routes) are out of scope.
