# Security review, 2026-10-18

A sweep of everything that changed in `public/minn/`, `install.sh` and
`scripts/` since the last one (42738b55, 2026-10-13): 138 commits, most of
them moving the facade's logic into `src/Minn/Runtime/`, plus the Minn
Legacy Pack, the media modal's uploads, page-builder support and the fleet
hunt's fixes. Six reviewers, launched together and changing nothing, each
read one attack surface:

- auth and sessions;
- REST and minn-admin authorization;
- files, packages, uploads and the signed install and update;
- output encoding;
- injection, outbound requests and mail;
- runtime isolation and denial of service.

Each reviewer started from the earlier reviews (2026-08-28, 2026-09-02,
2026-10-05, 2026-10-13), so it reported only what was new, and compared
every moved body with its facade original. The moves were found faithful:
no capability, nonce, escape, cast or allowlist was dropped on the way.
Every finding below was then checked on the test site, the same request or
input sent to both stacks, before it was fixed.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | Medium | The engine renders a post's blocks itself, then runs `the_content` and parses again, in `do_blocks`'s place, any block markup a filter added. It parsed the whole rendering, so block delimiters a commenter wrote, printed by a comments block in the post's own content (or in an embed's markup), came alive as blocks: a search form, a form, a pattern. | The comment text stays inert. | Only markup a filter put before or after the engine's rendering is parsed (`RenderState::renderAdded`); where a filter changed the rendering itself, nothing is. |
| 2 | Medium | A post-content block had no guard against rendering a post already being rendered, so a post that shows its own content nested to the depth limit, and two posts whose inheriting loops list each other branched into about a million queries on every view of a listing (an author's two posts take the site down). | A post's content renders once; an inheriting loop inside the main loop walks a copy of the main query from its start, running no query. | Same (`RenderState::postContent`; the loop walks `clone $main`, rewound). The loop's `<li>` classes are now asked for the item's own post, which a nested loop had changed. |
| 3 | Medium | `install.sh` (`minn.run/install`) installed whatever `minn.zip` it downloaded, checking neither its sha256 nor its signature: the one install path that skipped what the self-update enforces. | n/a. | It downloads `minn.zip.sig` too and installs only an archive signed with a key it carries (the same keys as `EngineUpdate::KEYS`, kept equal by the install suite); it also asks for PHP 8.3, the engine's floor. |
| 4 | Low | A plugin's own salt scheme was salted from `SECRET_KEY` even when that was the installer's placeholder or a copy of another key, so tokens a plugin signed that way (The Events Calendar's) could be forged from public text. | Leaves out a placeholder or a value two keys share, for every scheme (a core scheme too), and uses the option of that name instead. | Same, compared four ways on both stacks. |
| 5 | Low | `minn eject` moved back whatever the install record's `park` named. The record is a file in the webroot: a compromised plugin could point an eject run as root at `/etc`. A parked link was copied as its target when the park sat on another filesystem. | n/a. | Install marks the park (`.minn-park`, naming the webroot); eject takes the record's park only when it carries that mark, never a link, and `--park=DIR` names it outright. A parked link comes back as a link. |
| 6 | Low | Reviving a stored object set a property the class does not declare through its `__set`, so a stored blob could run plugin code. | Its `unserialize` never calls `__set` (it runs `__wakeup` and `__destruct`, which the engine refuses already). | A class with `__set` stays a stdClass of its properties. |
| 7 | Low | When a plugin cancelled the comment form's redirect, the engine redirected to the commenter's `redirect_to` unchecked. | A blank 200; the address is never sent. | Same. |
| 8 | Low | `wpdb::_insert_replace_helper()`, public since the facade keeps WordPress's visibility, built any statement type a caller passed. | Builds only INSERT and REPLACE; any other type sends nothing. | Same. |
| 9 | Low | A walk of a plugin's files that ran out of time or memory was never cached, so every request began it again and the site stayed down. | n/a. | The walk is marked as it begins; one begun two minutes ago that never finished holds the folder back, until it changes or is installed or activated again. |
| 10 | Low | The symbol cache kept a folder by its name alone, so a plugin and a theme of one name (Kubio's) took each other's entry, and every request walked both folders again. | n/a. | Kept by parent and name (`plugins/kubio`, `themes/kubio`); entries an older engine kept by name alone go. |
| 11 | Low | A 404 lines up the same scripts as any page, so the page the Legacy Pack note names for Minn Admin's banner could be any text a visitor typed, of any length. | n/a. | A 404 keeps the page noted before; a page's address is cut at 200 characters. |
| 12 | Low | A reply to a comment marked spam or in the trash was taken (only a held parent was refused). | Refuses all three ("replies to unapproved comments are not allowed"). | Same: the parent's status is read as a number. |
| 13 | Low | `bloginfo()` and `get_bloginfo(..., 'display')` printed the site's name and description as stored: no texturize, no character conversion, no escaping. | `wptexturize`, `convert_chars` and `esc_html` on the `bloginfo` filter. | Same three callbacks. |

## Checked and not a finding

- **The media modal's upload settings** are encoded with unescaped slashes, so a value holding
  `</script>` would end the script. The reference prints them the same way, byte for byte, and
  the values come only from filters and options.
- **A page list's submenu items** print a navigation's custom overlay colours as given (escaped
  as an attribute, not run through the style filter). So does the reference.
- **`wp_validate_auth_cookie('')`** with only the logged-in cookie satisfies the logged_in
  scheme and no other, on both stacks.
- **`is_protected_meta()`** sets the same invisible characters aside as the reference (fourteen
  keys compared).
- **The wp-admin doorway, the Legacy Pack install route and the async upload** check the admin
  cookie, the capabilities and the nonces before anything runs, as the reference does.

## Parity found on the way

- **A navigation's overlay colours** were missing from a submenu's list and from the mobile
  overlay's container. They print now as the reference prints them: a named colour as its
  classes (winning over a custom one), a custom one as `has-*` and a declaration through the
  style filter (probe `navigation-overlay`).
- **`wp_upload_dir($time)`** ignored the time, so `media_handle_upload` never dated a file by its
  parent post (finding 11 of 2026-10-13 had no effect).
- **`get_current_user_id()`** returns the id as a number, as the reference casts it.

## Named and not fixed

These need Austin's call:

- **An older signed release.** `install.sh` installs any release a trusted key signed, an old
  one included: the signature covers the archive's bytes, not its freshness. This is the
  freshness half of row 14984 (release key revocation and freshness).
- **Minn Admin's PHP in `minn/admin/`.** The release ships Minn Admin whole, so its PHP files
  sit in the webroot and answer when requested directly. The copy that runs is the one in
  `wp-content/plugins`, which Minn Admin updates; the `minn/admin` copy changes only with an
  engine release. Nothing there is exploitable today (`progress.php` is token-gated; the rest
  declare and return).
- **The Legacy Pack is checked by a checksum from the service that serves it**, not signed as
  engine releases are. A bad pack would serve script to every visitor.
- **The first request after an engine update** lays Minn Admin into `wp-content/plugins` and,
  on a site whose folder was empty, activates it: a read and write of `active_plugins` that
  could lose an administrator's change made at the same instant. WordPress's own activation
  has the same read and write.

## Where the pins live

- Probes compared with the reference in the api suite: `sweep-1018` (the insert helper's
  statement types, protected meta keys, `bloginfo`, the uploads folder for a time, the
  logged-in cookie's schemes) and `navigation-overlay`.
- `tests/security-sweep.test.php`: a comment's block markup, a post showing its own content,
  two posts whose loops list each other, and `SECRET_KEY` four ways.
- `tests/install.test.php`: the curl installer's keys and its refusals, a signed install and its
  eject, and an eject refused a park without the mark.
- `tests/runtime.test.php`: a cancelled comment redirect, a symbol walk that never finished.
- `tests/ajax.test.php`: a 404 and the Legacy Pack note. `tests/comment-form.test.php`: replies
  to spam and trashed parents. `tests/unit/stored-objects.php`: `__set`.
- `tests/tools/scorecard.php` lists every finding with its pin.
