# Contract: wp/v2/users management

Status: implemented (edit-context list/single, ordering, create, update
with role changes, delete with reassignment). Suite:
`tests/users.test.php` (23 checks). The headline proof: an engine-created
user signs in through real WordPress — the engine writes genuine
`$wp$2y$10$` password hashes.

Read-side basics (view objects, /me, the failure matrix) live in
`contracts/rest/auth.md` and `caps.md`; this contract covers management.

## List

- View context (unchanged): only users who authored published content.
- `context=edit` needs `list_users` → `rest_forbidden_context`
  ("Sorry, you are not allowed to **edit** users." — not "list").
- `orderby` (name | id | registered_date | slug | email; default name),
  `order` (asc default), `include`, `exclude`, `slug` (user_nicename,
  comma list), `search` (case-insensitive substring of login, nicename,
  or display name; not email), `per_page`/`page` + `X-WP-Total`
  headers, `_fields`. `minn_switch_url` is absent with no switch provider
  installed — matching the oracle, where the lazy field also stays away.
- Single `context=edit`: self, or `list_users`.
- Edit-object `targetHints.allow`: GET/POST/PUT/PATCH always (an edit
  object is only reachable by someone who may write it), DELETE only when
  the VIEWER holds `delete_users` — an author sees 4 verbs on their own
  record, an administrator 5.

## Create (`create_users`)

Body `username`/`email`/`password` required (aggregated
`rest_missing_callback_param`), plus `roles`, `name`, `first_name`,
`last_name`, `nickname`, `description`, `url`, `locale`. Writes:

- The users row: unique sanitized `user_nicename` (-2, -3 … on
  collision), `user_registered` GMT now, `user_activation_key` empty. The
  display name is `name` when given, otherwise first and last name, either
  one alone, or the login; `nickname` plays no part (captured 2026-10-05).
- Nothing is mailed and no reset key is minted: the caller chose the
  password. `user_count` is refreshed on every create and delete (the row
  is left alone on a site without one).
- `user_pass` = `"$wp" + bcrypt(base64(hmac_sha384(password, "wp-sha384")))`,
  cost 10 — the inverse of the verify scheme in contracts/rest/caps.md;
  WordPress's own login accepts it (suite-proven).
- The 14 default meta rows core writes, in order: nickname, first_name,
  last_name, description, rich_editing "true", syntax_highlighting
  "true", infinite_scrolling "true", comment_shortcuts "false",
  admin_color "modern", use_ssl "0", show_admin_bar_front "true", locale,
  `{prefix}capabilities` (serialized single-role map),
  `{prefix}user_level` (administrator 10, editor 7, author 2,
  contributor 1, subscriber 0).

201 + Location, edit-context body.

**Duplicate identity quirk (oracle-caught):** an existing username or
email surfaces as core's bare WP_Error — HTTP **500**, `data: null`
(`existing_user_login` / `existing_user_email`), not a 400.

## Update

`POST|PUT|PATCH /wp/v2/users/{id}`, and `/wp/v2/users/me` for the signed-in
user (signed out, that is `404 rest_user_invalid_id`, as on the
reference). Self, or `edit_users`. Fields: name, email, url, slug, password
(re-hashed), first/last/nickname/description/locale meta,
`meta.show_admin_bar_front`, and `roles` (needs `promote_users`; rewrites
the capabilities meta + user_level). Response: fresh edit object.

## Delete

`reassign` is REQUIRED (missing → `rest_missing_callback_param`), force is
required (`501 rest_trash_not_supported`, "Users do not support
trashing…"), gate `delete_users`. A numeric reassign moves every post's
`post_author` (invalid target → `400 rest_user_invalid_reassign`); an
empty/false reassign deletes the user's posts. Removes the row + all
usermeta; response `{ deleted: true, previous }`.

## Known gaps

- No `roles`/email validation beyond existence checks (bad role strings
  are stored as-is; core validates against the role registry).
- `who=authors`, `roles`, `capabilities`, `has_published_posts` list filters.
- Multisite semantics (spam/deleted flags, network caps) — single-site only.
