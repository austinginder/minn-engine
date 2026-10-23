# Security review, 2026-10-22

The sweep for the 0.1.0 release candidate (RC4, engine 561f25b1), part of
the audit of the same day (`docs/audit-2026-10-22.md`). It covers
everything that changed in `public/minn/` since the last one (ce7083f6,
2026-10-20): 48 commits, among them the font writes, global styles and
template autosaves over REST, the filesystem credentials flow, the sign-in
cookies following `secure_signon_cookie`, the block classes reaching
plugins at their own filters, the `use_ssl` redirect, and the engine's
page captures holding against `wp_ob_end_flush_all`. Six reviewers,
launched in two waves and changing nothing, each read one attack surface:

- auth and sessions;
- REST and minn-admin authorization;
- files, packages and the signed update;
- output encoding;
- injection, SSRF and mail;
- runtime isolation and denial of service.

Each started from the earlier reviews (2026-08-28, 2026-09-02,
2026-10-05, 2026-10-13, 2026-10-18, 2026-10-20), so it reported only what
was new: fifteen findings. Every one was then checked on the test site,
the same input sent to both stacks, before it was fixed; where the
reference does something, the fix does that, measured, not remembered.
One did not reproduce, and four are named below rather than fixed.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | High | A REST request carrying an administrator's sign-in cookie, no nonce, and another account's application password ran as the administrator. The cookie check settled the cookie's user while plugins loaded, the engine adopted that user because an application-password caller was not counted as cookie-bound, and the facade's gate then signed the request out without the engine's caller hearing it. Any account that may make an application password (a subscriber where registration is open) could aim it at a signed-in administrator from a page that sends the cookie. | The cookie's user wins `determine_current_user`, and with no nonce `rest_cookie_check_errors` signs the request out: 401 `rest_not_logged_in`. | A user the cookie settled (`wp_rest_auth_cookie`) binds the request to its nonce: without one the request is nobody (`Engine::adoptSettledUser`). 401 on both stacks. |
| 2 | Medium | `delete_plugins()` deleted a name with no file in it recursively: `['..']` removed all of wp-content, `['.']` every plugin, `['akismet']` the folder. The engine's own routes refuse such names; a plugin passing input through reached it. | A plain, non-recursive delete: the folder stays, `could_not_remove_plugin` "Could not fully remove the plugin …" ("the plugins …" for several). | A name with no folder removes one file and nothing else (`PluginRemoval::removeFiles`); the message as the reference words it. |
| 3 | Medium | Contributors listed every editorial note (`?type=note`, `?type=all`) on every published post, and Minn Admin's activity feed showed notes as comments to anyone with `edit_posts`. | A note is listed only to someone who may edit its post (an editor, the post's author); others get the list without it. | The same rule in `CommentsController::readable`; the feed leaves notes out. |
| 4 | Medium | A query block ran its query as soon as it rendered, whatever it held, so the block renderer (`/wp/v2/block-renderer/core/query`, any contributor) ran a query of any page size the attributes named, on every request. | The query runs in the blocks that list posts (post template, pagination, no results); a query block from the block renderer, or holding only its title, queries nothing. | The loop runs the first time a block inside asks for it (`QueryBlocks`). |
| 5 | Low | `activate_plugin()` included any file on disk, a media upload under `../uploads/` included, and stored it as active; `validate_plugin()` only checked that the file existed. | `validate_plugin` refuses a path out of the plugins folder (`plugin_invalid`), a missing file (`plugin_not_found`), a file `get_plugins()` does not list (`no_plugin_header`), and `activate_plugin` asks it first. | Both as the reference does. |
| 6 | Low | `PUT /wp/v2/plugins/{plugin}` changed a plugin's status asking only `activate_plugins`, so a filter refusing `activate_plugin` or `deactivate_plugin` for one plugin (a host locking a security plugin on) was never asked. | Asks the plugin's own capability with its file when the status changes: 403 `rest_cannot_activate_plugin` / `rest_cannot_deactivate_plugin`. | The same (`PluginsController::update`). |
| 7 | Low | The curl transports (`WP_Http_Curl`, the Requests curl transport, `Minn\Http`) followed any protocol curl knows: `file://`, `gopher://`, `dict://`, for plugin code that hands them an address directly. | `Protocol "file" is disabled`. | HTTP and HTTPS only, redirects included (`Http\Transport`). |
| 8 | Low | The cookie check read the stored sessions itself, so a plugin's session manager (`session_token_manager`) that refuses a session was never asked. | `wp_validate_auth_cookie` asks the manager; a refused session is `auth_cookie_bad_session_token`. | The manager decides once the runtime is up (`Auth\Cookie::check`). |
| 9 | Low | Minn Admin installed language packs under `DISALLOW_FILE_MODS`, asking only `manage_options`. | Installing a language is `install_languages`, taken away when file changes are off. | `LanguageController` asks `install_languages`. |
| 10 | Low | The engine's own client let NAT64 (`64:ff9b::/96`, `64:ff9b:1::/48`), site-local (`fec0::/10`) and multicast (`ff00::/8`) IPv6 addresses through its private-address check, since PHP's global-range flag passes them. | n/a (the reference refuses IPv6 literals and resolves over IPv4). | Refused (`Http\Destination`). |

## Named and not fixed

- **The engine's own SQL quoting ignores a multibyte connection charset** (Low). `Query\Sql::escape`
  is a fixed table, so a site whose wp-config sets `DB_CHARSET` to gbk, big5, sjis, cp932 or
  gb18030 could have a quote closed by a lead byte, through front-end search among others. The
  reference escapes with the connection (`mysqli_real_escape_string`). No site Minn serves uses
  one, and preflight should refuse them before 0.1.0 is installed anywhere that might: a register
  row.
- **Fan-out through distinct synced patterns or post-content loops** (Low). Depth is capped (64)
  and a source already open further out is refused, but distinct sources still nest
  combinatorially, as on the reference, whose guards are the same; the engine reads each synced
  pattern from the database where the reference has it cached. An author's content, rendered per
  view: a register row for a render budget.
- **A recovery pause from visitor-chosen headers** (Low). A request counts toward pausing a
  plugin when it carries no query, body, upload or cookie; its path and headers still count, so a
  plugin that fails at boot on a header could be paused by two such requests. The reference never
  pauses outside an administrator's recovery session. A register row.
- **The sign-in cookies carry no SameSite flag** (Low, accepted). Since 57182094 they are sent as
  the reference sends them; finding 1 was reachable from more places because of it, and is fixed
  at its root.

## Checked and not a finding

- **The block custom-CSS strip with a JSON-escaped key** (`{"style":{"css":…}}`, from a
  contributor or an author, through the save filters and through a REST create): the CSS is gone
  on both stacks, whichever way the key is spelled.
- **Font writes, global styles, template autosaves, the filesystem credentials flow**: admin or
  `edit_theme_options` only, nonces checked, uploads font-only.
- **`Runtime\OutputFloor`**: hold and release balanced in `finally` at all four captures.
- **The renderer's class stages**: class names pass through the tag processor's escaping.

## Parity found on the way

- **`get_blogs_of_user`** fires its filter on neither stack on a single site; hook status now lists
  it as a network's hook.
- **`delete_plugins`** on the reference goes through `WP_Filesystem` (and asks
  `filesystem_method`); the engine deletes directly. Kept in the hook battery's divergences.
- **A query block's children** each run their own query on the reference (six queries for a
  template, pagination and no results); the engine runs one and shares it.

## Pinned by

The application-passwords suite ("a sign-in cookie without its nonce beside another account's
application password"), probe `sweep-1022` (26 rows: notes, the plugin's own capability,
`validate_plugin` and `activate_plugin`, `delete_plugins`, the curl transports, the session
manager, query blocks; compared with the reference by the api suite and the hook battery), and
`tests/unit/http.php` (the IPv6 ranges). The language install is pinned by its source until the
minn-admin suite can turn file changes off. Each finding is counted in
`tests/tools/scorecard.php` against the check that pins it.
