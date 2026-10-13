# Security review, 2026-10-13

A sweep of `public/minn/` after the 0.1.0 scorecard work, done the way
`/dev-minn-engine-coverage` now asks of every release. Six reviewers, launched
together and changing nothing, each read one attack surface:

- auth and sessions;
- REST and minn-admin authorization;
- files, packages and the signed update;
- output encoding;
- injection, outbound requests and mail;
- runtime isolation and denial of service.

Each reviewer started from the earlier reviews (2026-08-28, 2026-09-02,
2026-10-05) and the audit of 2026-10-12, so it reported only what was new.
Every finding below was then checked on the test site. Where the reference
has an answer, the same request or input went to both stacks; the
reference's rule was captured case by case and the engine fixed to match it.
One report was not a finding: a reset key still valid after a sign-in is
the reference's own behaviour.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | High | `Support\Kses::url` judged a scheme on the raw text, so a scheme broken by a tab or a line break (which browsers drop) reached social-link, navigation-link and comment-author links, and a stored `user_url`. | `esc_url` empties it. | The scheme is judged with tabs, line breaks and control characters removed; every caller shares the one function. |
| 2 | High | A navigation link's class names, and a navigation's overlay colours, went into `class=""` unescaped, so a contributor's block attributes could add attributes to the page. | Escapes both. | Escaped; the class names also sit where the reference puts them (a space before a link's, a submenu's on its item and its list), and a submenu inside any navigation is wired for hover. |
| 3 | High | Two requests carrying input that made a plugin fail during `init` paused that plugin for every visitor: the recovery strike counted any request. | Pauses nothing outside an administrator's recovery session. | A failure counts toward a pause only on a plain request (GET or HEAD with no query, body, upload or cookie). A plugin broken for everyone still fails those and is still paused. |
| 4 | Medium | A published post of a type nobody may query (global styles, menus, a plugin's private type) rendered on the front end, asked by name and type, by query var or by rewrite path. | Drops the type: 404, or the post of that name. | Same; a name with no post now gets the reference's 404 guess, too. |
| 5 | Medium | `wp_http_validate_url` let safe requests reach shared address space (CGNAT, cloud metadata hosts), benchmarking, documentation, 6to4 relay, multicast and reserved ranges. | Refuses all of them. | Anything outside the global range is refused; 27 addresses compared. |
| 6 | Medium | `GET /wp/v2/tags?post=` (any taxonomy) listed the terms of drafts, private and pending posts to anyone. | `rest_forbidden_context` unless the post is of the taxonomy's types and publicly viewable or readable. | Same rule, 36 cases compared. |
| 7 | Medium | Subscribers listed and created REST comments on password-protected posts without the password. | `rest_cannot_read_post` without the password unless the caller may edit the post. | Same rule, 24 cases compared. |
| 8 | Medium | A duotone preset slug that theme.json does not register reached the figure's class and the SVG filter's id. | Reads it as a custom value; escapes the id. | Same. |
| 9 | Medium | No password length limit: legacy phpass hashed the whole input, so one large post password held a worker for seconds. | Refuses over 4,096 characters before hashing. | Same, for every hash and check path. |
| 10 | Medium | A password reset key stayed valid after the address or password changed. | Clears it on either change. | Same, through `wp_update_user`, REST and `wp_set_password`. |
| 11 | Medium | `media_handle_upload` ignored its `$overrides` and ran as a sideload (other filters, no upload test). | Runs `wp_handle_upload` with the overrides, dated by the parent post. | Same. |
| 12 | Medium | `MINN_TRUSTED_PROXIES = '*'` treated every forwarded hop as trusted, so every visitor was the proxy and twenty bad sign-ins locked everyone out. | n/a (no throttle). | `*` trusts the connecting address only; the client is the address it appended. |
| 13 | Low | Built-in types other than posts and pages took the posts family's capabilities: subscribers read published patterns, editors others' private patterns and menu-item autosaves. | Each type's registered names; a pattern's own names asked directly answer as the posts family's. | Same; 60 cases compared. |
| 14 | Low | A navigation block naming a draft or private menu showed it. | Shows the latest published menu instead, unlabelled; a missing menu shows nothing. | Same, byte for byte. |
| 15 | Low | `wp plugin delete` accepted a slug that climbs out of the plugins folder. | WP-CLI deletes only installed plugins. | Refused as not found. |
| 16 | Low | The sign-in throttle counted with a read and a write (parallel failures lost counts), gave each IPv6 address its own budget, and swept every row unbounded; `wp-cron.php` ran in full on every hit. | n/a. | One atomic statement per failure; IPv6 counted by its /64 (IPv4 clients a dual-stack server shows mapped keep their own); bounded sweeps; an idle cron hit returns at once. |
| 17 | Low | `set_sql_mode` could turn on `NO_BACKSLASH_ESCAPES`, under which the engine's backslash-escaped literals end early. | Escapes through the connection. | The mode is never set. |
| 18 | Low | A string `author__in` / `author__not_in` reached the SQL as typed. | Reads ids (an id list for the exclusion, the leading id for the inclusion). | Same, 8 cases compared. |
| 19 | Low | An archive's `__MACOSX/` entries skipped the size, path and link checks but were still unpacked. | n/a. | Checked like every entry; only the archive's one folder is unpacked. |
| 20 | Low | For argon2 hashes the cookie's password fragment was the same for every password, so a password change did not end other cookies, and the reference's cookies did not validate. | Last four characters for anything but phpass and plain bcrypt. | Same. |
| 21 | Low | `minn eject` followed any path its install record named. | n/a. | Only relative paths inside the webroot. |
| 22 | Low | A URL attribute bound by an interactive block kept any scheme; calendar labels from translations were printed raw. | `set_attribute` runs URLs through `esc_url`; labels escaped. | Refused schemes empty the attribute; labels escaped. |

## Named and not fixed here

- **Safe requests are not pinned to the address they were checked against.** The facade's
  `wp_safe_remote_*` path validates a name's address, then lets curl resolve it again, so a
  name that changes its answer between the two (DNS rebinding) can reach an address the check
  refused. The reference has the same gap. The engine's own requests (`Minn\Http`) already pin
  every hop with `Http\Destination::pinned`; the facade's transport should go through it too.
- **Responses are not capped while they arrive.** The facade's requests read a whole response
  (decompressed) before truncating it, and downloads land in memory before they are written. A
  server answering a contributor's URL-details or oEmbed request, or an author's media-from-URL,
  can exhaust memory. The reference stops at `limit_response_size` and streams downloads.
- **Escaping hardening, safe today because content is filtered on save:** the search block's
  button text and the latest-comments block's post titles print as stored, and image `srcset`
  values come straight from metadata.
- **Files unpacked by `extractTo` take the process umask** rather than `FS_CHMOD_FILE` /
  `FS_CHMOD_DIR`, and the staging folder's creation is not checked.

## Where the pins live

- Probes compared with the reference in the api suite: `rest-terms-for-post`,
  `rest-protected-comments`, `reset-key-changes`, `builtin-type-caps`, and `sweep-rules`
  (navigation class names and menus, duotone presets, author lists, the addresses a safe request
  refuses).
- `tests/security-sweep.test.php` (in run-all): non-public types on the front end, upload
  overrides on both stacks, the recovery pause, a long post password, the CLI's plugin delete,
  and the SQL mode.
- `tests/unit/security-sweep.php`: URL schemes, address ranges, password length, the cookie
  fragment, eject paths, bound URL attributes, archive notes and throttle keys;
  `tests/unit/trusted-proxies.php`: the wildcard.
- `tests/tools/scorecard.php` lists every finding with its pin; the "Security findings fixed"
  measure counts them.
