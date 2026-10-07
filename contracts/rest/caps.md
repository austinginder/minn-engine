# Contract: capabilities and login

Status: implemented (role resolution, the meta-cap mapping, password login,
session creation, and the edit-context user object). Suite:
`tests/caps.test.php` (7 checks, including a 19-case capability matrix diffed
against the oracle).

This is the surface every write path will stand on: `current_user_can` is
how the engine decides who may change what, and login is how a session comes
into being in the first place.

## Roles

- Role definitions live in the `{prefix}user_roles` option (a serialized
  `{role: {name, capabilities:{cap:bool}}}` map), parsed without
  `unserialize()`. A fresh install with no such option falls back to the
  seeded contract copy in `public/minn/data/roles.json`.
- A user's roles come from the `{prefix}capabilities` usermeta (`{role:
  true}`). The user's effective capabilities are the union of the granted
  primitives across those roles.
- The five bundled roles carry the reference's exact capability counts:
  administrator 61, editor 34, author 10, contributor 5, subscriber 2.

## The meta-cap mapping (map_meta_cap subset)

Primitive capabilities (`edit_posts`, `manage_options`, `publish_posts`, …)
are checked directly. The meta capabilities the engine needs are mapped to
the primitives actually required for a specific post:

- `edit_post` / `delete_post` on a post the user authored →
  `edit_posts` / `delete_posts`, plus `edit_published_posts` /
  `delete_published_posts` when the post is published (or the `_private_`
  variant when private).
- The same on a post authored by someone else → the `_others_` primitive,
  plus `_published_` / `_private_` as above.
- Pages map through the `_pages` primitives identically.
- `read_post` → `read` for a published post or the author's own, else
  `read_private_{type}s`.
- A meta cap called without a post id maps to `do_not_allow` (deny), matching
  the reference's "must check against a specific post" behavior.

Proven by a 19-case matrix against the live oracle: an author edits their own
draft and their own published post but not another user's post, while an
editor edits everyone's; an author holds `publish_posts` and `upload_files`
but not `manage_options`, `edit_others_posts`, or `promote_users`.

## Login and sessions

- Password verification for modern installs: the stored hash is
  `$wp$` + bcrypt of `base64( HMAC-sha384( password, "wp-sha384" ) )`, so
  `password_verify( base64(hmac_sha384(pw, "wp-sha384")), substr(hash, 3) )`.
  A bare `$2y$` bcrypt hash, an argon2id hash, and legacy phpass `$P$` verify
  too (probe `password-rehash`).
- New hashes use PHP's default bcrypt cost: 10 up to PHP 8.3, **12 from PHP
  8.4**. The engine pinned 10 until 2026-10-07; under PHP 8.5 WordPress then
  rewrote every engine-made hash at its next sign-in, and because the auth
  cookie carries the hash's last four characters, that silently signed out
  every Minn session (the round trip caught it once its reference ran on the
  same PHP as the engine). `wp_hash_password_algorithm` then
  `wp_hash_password_options` choose the algorithm and options, for hashing and
  for `wp_password_needs_rehash` alike; under bcrypt anything outside `$wp$`
  or at another cost needs a rehash, under another algorithm PHP's own rule
  decides, and `password_needs_rehash` has the last word. A good sign-in
  (username or email step of the authenticate chain, and the non-booted
  fallback) replaces an outdated hash through `wp_set_password`; a failed one
  leaves it.
- `minn_create_session()` generates a 43-char token, stores
  `sha256(token) => {expiration, ip, ua, login}` in the `session_tokens`
  usermeta (serialized by the engine, existing live sessions preserved and
  expired ones pruned), and returns the raw token. The auth cookie and nonce
  are then minted from it.
- **Full circle, proven:** the engine verifies a password, creates a session
  row, and WordPress itself accepts a request made with the resulting cookie
  and nonce. The engine is now a session issuer, not only a validator.
- `minn_login()` returns the user row without setting a cookie or creating a
  session, so the caller controls the flow.

## Edit-context user object

`GET /wp/v2/users/me?context=edit` (authenticated) adds the private fields
and matches the reference byte for byte:

- Extra keys in order: `username`, `first_name`, `last_name`, `email`,
  `locale`, `nickname`, `roles`, `registered_date`, `capabilities`,
  `extra_capabilities`.
- `registered_date` is the `user_registered` column as ISO with a literal
  `+00:00` suffix.
- `locale` falls back to the site language (`en_US`) when the user has no
  `locale` meta.
- `capabilities` is the union of role primitives **plus each role name as a
  pseudo-capability** (`administrator: true`), rendered as a JSON object.
- `extra_capabilities` is the raw `{prefix}capabilities` map (`{role: true}`).
- `meta` carries `persisted_preferences: []`.
- `_links` drops to just `self` and `collection`.

## A bug the milestone caught

An empty-content post must yield an empty excerpt (`""`), not an empty
paragraph (`<p></p>`). Only surfaced once a title-only post existed in the
fixture set; the parity diff caught it immediately.

## Known gaps

- No `wp-login.php` form or cookie-setting HTTP endpoint yet: login is an
  engine primitive, not an exposed route. Logout (`minn_destroy_session`)
  exists but is likewise unexposed.
- Legacy phpass password hashes.
- `context=edit` on arbitrary users (only `me` renders the edit shape).
- Application passwords, `promote_users` flows, multisite super-admin.
- Roles beyond the built-in five (a plugin-registered role would need its
  option entry, which the parser already reads).

## Policies on routes (2026-09-02)

A route's authorization is data on its attribute, `policy: new Policy(...)`,
judged by the router before the handler runs; a router cannot be built
without the gate that judges it. `Minn\Http\Access` names the five answers:

| Access | Judged as | Anonymous | Signed in, refused |
|---|---|---|---|
| `Public` | never resolved | | |
| `SignedIn` | `Caller::require(signIn, signInMessage)` | 401 `signIn` | |
| `Cap` | signed in, then `cap` and every `caps` entry | 401 `signIn` | 403 `refuse` |
| `Floor` | signed in as `rest_forbidden`, then `edit_posts` and every `caps` entry | 401 `rest_forbidden` | 403 `rest_forbidden` |
| `Own` | signed in, then `cap` on the id the `param` capture holds | 401 `signIn` | 403 `refuse` |

A bad nonce is always 403 `rest_cookie_invalid_nonce`, from `Caller::require`.
`edit:` carries a second policy judged as well when the request asks for the
edit context. Defaults are the reference's common pair: `rest_not_logged_in`
/ "You are not currently logged in." to sign in, `rest_forbidden` / "Sorry,
you are not allowed to do that." to refuse. The front's router judges the
same policies against the session cookie alone (401 / 403 with the policy's
codes); its routes are all public today.

The style suite ratchets the count of routes with no policy: 157 of 222
after the first cut (the 65 whose first statement was a pure gate), 117
after the second (menus, templates, global-styles themes, sessions, the
per-user preference routes as `Own edit_user`, and the inline floors), 84
after the third, which declared `Access::Public` on the routes that are
public on purpose (the front, feeds, sitemaps, probes, assets, sign-in, the
app shell, the REST index and types), so a bare route now means "not yet
decided" and nothing else. The
rest still decide inside their bodies, mostly after loading the record
(the reference answers 404 for a missing parent before it refuses the
capability, which a policy cannot order), and each moves onto the
attribute as its controller is touched. The generated API docs print each
route's policy after its pattern.

## What a route takes (2026-09-02)

A route also declares the parameters it reads, `args: [Args::CONTEXT,
Args::POSTS]` for the query and `body: [Settings::SCHEMA]` for the JSON
body, and the REST index publishes them per endpoint. The descriptions,
types, defaults and enums in `Minn\Http\Args` were captured from the
reference, so a client reading either index is told the same thing, and
since the same day the router enforces them: a declared argument that does
not validate is refused before the policy is judged, in the reference's
order and shape. `contracts/rest/arguments.md` is that contract.

The rule is that a route declares only what its handler really reads, and
each set in `Args` names the code that consumes it (`Rest\Context::of`,
`Rest\ListQuery::fromRequest`, and the rest). An argument published for a
route that ignores it would be worse than none, because a client would
build a request around it. Each collection has its own set (`POSTS`,
`PAGES`, `MEDIA`, `USERS`, `TERMS`, `COMMENTS`, `SEARCH`) because the
reference's `orderby` enums and defaults differ per resource; the
remaining routes publish an empty argument map, which is what every route
did before. `_fields` and `_embed` are declared nowhere, as the reference
lists neither.

The index is not diffed against the reference (`probes` compares the site
keys, the namespaces, and that `/wp/v2/posts` is described), so a route
that has not declared its arguments yet is honest rather than wrong.

## The record before the caller (2026-09-02)

The reference looks the record up before it looks at the caller: an
anonymous `DELETE /wp/v2/posts/999999` is `rest_post_invalid_id` at 404,
not a 401, and an author's `POST /wp/v2/menus/999999` is `rest_term_invalid`,
not a 403. A policy now names the record its capture points at,
`subject: Subject::Post, param: 'id'`, and `Rest\PolicyGate` asks
`Rest\Subjects` for it first, answering the subject's own 404
(`Http\Subject::missingCode()`; `missing:` overrides it, as the revision
routes do with `rest_post_invalid_parent`). Then the sign-in check, then
the capability. A post subject reads its type from the `{base}` capture
(posts, pages, blocks, media, navigation, menu-items) and a term subject
its taxonomy; `me` names the caller and is never missing, only signed out,
which the reference refuses with its plain `rest_not_logged_in` whatever
the route's own code is. Status is not consulted: a trashed post exists.

That order gives most record routes a policy they could not state before:
posts, pages, blocks, navigation and media edits are `Own edit_post`
(deletes `Own delete_post`, refused `rest_cannot_delete`), comments
`Cap moderate_comments`, terms `Cap manage_categories`, users
`Own edit_user` (delete `Cap delete_users` behind a required `reassign`),
revisions and autosaves `Own edit_post` on the parent, menus and menu
items `Cap edit_theme_options` on the record, application passwords
`Own edit_user` with the reference's code per action. A public single
read carries the subject and its edit-context residual
(`edit: Own edit_post` as `rest_forbidden_context`); what remains in the
handler is the read gate on an unpublished record, which the reference
judges per status.

`Access::Type` is the declared-type catch-all's policy: the `{base}`
capture must name a declared post type or the route declines
(`RouteMiss`) and the next one is tried, so `/wp/v2/settings` no longer
matches `/wp/v2/{base}` when the Allow header is built. Seven routes are
bare now: the sign-in pages, the auto-updates toggle, and the block and
navigation creates, whose capability is the type's.

`edit_user` on oneself maps to no primitive at all, and a meta capability
that asks nothing further is held; `Capabilities::can` returned false for an
empty requirement before the `Own` policies used it.
