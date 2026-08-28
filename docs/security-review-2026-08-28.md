# Security review, 2026-08-28

A line-by-line review of `public/minn/` (about 13,000 lines across 100 classes)
before the engine faces the public internet, done as milestone 27. Three passes
covered Auth/Login/Http/Db/Cli, Rest/Admin/Content/Support, and
Media/Front/Theme/Blocks. Every finding below was checked against the cited source
before it was acted on, and where the reference's behaviour decides the fix, the
reference was probed on the same database and the answer captured into
`tests/security.test.php` (34 checks) so the fix cannot regress silently.

## Findings and what was done

Severity is the reviewer's; "Reference" says whether WordPress on the same database
behaves the same way (probed), which is what decided the shape of each fix.

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | High | No HTML allowlist on post title/content/excerpt, media caption/description, or autosaves for callers without `unfiltered_html` (stored XSS by Author/Contributor). | Filters them; captured byte for byte. | `Support\Kses` (own allowlist filter: tags, attributes, URL schemes, style properties; comments pass through) applied in every write path unless the caller has `unfiltered_html`. |
| 2 | High | Comment content stored and rendered raw. | Comment allowlist, links get `rel="nofollow ugc"`. | Same filter with the comment allowlist; ugc marking; author name/url as text and URL. |
| 3 | High | Password-protected posts rendered in full on the front end, in feeds, and in listings. | Shows the password form, "There is no excerpt because this is a protected post.", a "Protected: " title prefix, and no comments. | `Content\PasswordGate`, used by post-content, post-excerpt, post-title, latest-posts, feeds, comments, and the interim renderer. |
| 4 | High | Block attributes reached tag names, class and style attributes, hrefs, and the page's `<style>` element unescaped. | n/a (engine rendering) | `Styles::value` rejects values that could close a declaration or carry code; `Styles::slug` for class tokens; `Html::addClasses`, navigation, search, social links, comments, query and post blocks escape at the sink; `tagName`, `arrow`, `buttonPosition`, `linkTarget` are enumerated; hrefs go through `Kses::url`. |
| 5 | Medium | `core/template-part` slug was a path-traversal primitive. | n/a | `Theme::safe()`: templates and parts are file names, never paths. |
| 6 | Medium | Site comments feed, latest-comments block, and the REST comments list exposed comments on private, draft, and protected posts. | Restricts to published, unprotected posts. | Joined on post status and password; the REST single comment checks the post too; moderators keep the full view. |
| 7 | Medium | SVG uploads accepted; `shell.php.png` kept its inner extension. | Refuses SVG; neutralises inner extensions. | `svg` removed from the allowlist; `Uploads::sanitizeName` joins inner extensions with `_`. |
| 8 | Medium | Contributors and Authors could list every draft, pending, and private post with `status=`. | Own unpublished rows only. | Author scoping unless `edit_others_*`; `private` needs `read_private_*`. |
| 9 | Medium | `/wp/v2/users/{id}` answered for users with no published content. | 401 `rest_user_cannot_view`. | Same gate as the list. |
| 10 | Medium | Comments could be created on draft, trashed, or closed posts. | 403 `rest_comment_draft_post` / `rest_comment_trash_post` / `rest_comment_closed`. | Same codes and messages. (A dangling `parent` is accepted by the reference and stays accepted.) |
| 11 | Medium | Term names and user profile fields stored raw. | Tags stripped from names; descriptions filtered; `javascript:` URLs dropped. | `Kses::text`, `Kses::filter(COMMENT)`, `Kses::url`, matched to the captured results. |
| 12 | Medium | `redirect_to` after sign-in accepted any URL. | `wp_safe_redirect` | Paths and same-host URLs only; anything else lands in the admin. |
| 13 | Medium | Logout only cleared cookies (session stayed live) and needed no nonce. | Destroys the session; asks for confirmation without a nonce. | `Sessions::destroy` on logout; a `log-out` nonce (generic action support added to `Nonce`); confirmation page without it. |
| 14 | Medium | Sign-in throttle was reset by any successful sign-in from the address. | n/a | The counter lapses on its own; success no longer clears it. |
| 15 | Low | Unbounded `postsToShow`, `commentsToShow`, `perPage`. | Caps at 100. | Capped. |
| 16 | Low | Self-referencing synced patterns, menus, parts, patterns, and very deep nesting could exhaust the stack. | Guards synced patterns. | `RenderState::enter/leave` cycle guard on every nested source; nesting capped at 64. |
| 17 | Low | `]]>` in a display name or term could close a feed's CDATA; the Atom self link was unescaped. | n/a | `Feeds::cdata()`; attribute escaping. |
| 18 | Low | Session cookies were persisted for two days without Remember Me. | Session cookies. | `expires` is 0 unless Remember Me. |
| 19 | Low | `X-Forwarded-Proto` from the client decided the cookie scheme. | Ignores it. | Only the server's `HTTPS`/port decide. |
| 20 | Low | Trashed posts were mapped as drafts for capability checks. | Uses `_wp_trash_meta_status`. | Same. |
| 21 | Low | The one-time login token was stored in plain text; failures answered 500. | (helper plugin) | The meta holds `sha256(token)`; failures answer 403. Link shape unchanged. |
| 22 | Low | Missing or placeholder salts signed cookies under a public string. | Generates salts. | The engine refuses to serve (logged 500) until `wp-config.php` has real keys. |
| 23 | Low | A logged-in user's User-Agent could inject a fake session entry into the length-blind session reader. | n/a | IP and UA are stripped of the characters the reader keys on before they are stored. |
| 24 | Low | Roles unvalidated on create/update; duplicate or malformed email accepted on update; `orderby=email` for anonymous listing; media attachable to posts the caller cannot edit; `post_status` accepted any string; `PostWriter::setMeta` inserted duplicate rows. | Probed: `rest_user_invalid_role`, `rest_user_invalid_email`, `rest_forbidden_orderby`, `rest_cannot_edit` ("upload media to this post"). | All matched; `setMeta` is an upsert; status is an enum. |
| 25 | Low | Pattern `printf` with a bad format was a fatal; `X-Powered-By` carried the version. | n/a | Caught; version dropped. |
| 26 | Info | `wp/v2/blocks` listed titles for anonymous callers. | Empty list. | Empty without `edit_posts`. |

Reported and set aside after probing the reference: an Author may set `sticky` on
their own post (the reference allows it, so the engine keeps allowing it); a
dangling comment `parent` is accepted by the reference; `user_activation_key`
hashing and the `read_private_*` mapping for custom roles are parity notes without
a security delta at stock roles. Still open, by design or for a later milestone:
`disallowed_keys` visible to `moderate_comments` holders; attachments whose parent is
unpublished remain listable; the throttle keys on `REMOTE_ADDR` only (see
`docs/hardening.md` for the proxy rule); settings values are stored without the
reference's per-option sanitisers (admin-only surface).

## Checked and found sound

Every database access is a prepared statement through `Minn\Db` with allowlisted
`ORDER BY` tokens and generated placeholders for `IN` lists; nothing calls
`unserialize()`, `eval`, or a shell; the cookie, nonce, and password schemes match
the reference and every secret compare is constant-time; a fresh session token is
minted on each sign-in; every mutating REST route demands the session-bound nonce;
`per_page` is capped everywhere; edit-context fields, emails, and hashes stay out of
public objects; asset serving is confined by `realpath`; the pattern interpreter has
no include, eval, or file-read path; redirects are always built from the `home`
option plus a path.

## Where the pins live

`tests/security.test.php` (this review), `tests/hardening.test.php` (throttle,
headers, failure pages), and the existing REST, theme, and probe suites, which all
still pass after the changes.
