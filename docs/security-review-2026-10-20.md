# Security review, 2026-10-20

A sweep of everything that changed in `public/minn/` since the last one
(fc764963, 2026-10-18): 26 commits, most of them the side-effect diff's
fixes (autosaves, revisions, the database door's text check, comment REST
arguments, term and post writes) and the new audio and video readers
(`Media\Av`: ID3, MPEG, RIFF, Matroska, FLV, MPEG-4, Ogg, FLAC, ASF, the
Exp-Golomb bit reader). Six reviewers, launched together and changing
nothing, each read one attack surface:

- auth and sessions;
- REST and minn-admin authorization;
- files, uploads and the new media readers;
- output encoding;
- injection, the database door and mail;
- runtime isolation and denial of service.

Each started from the earlier reviews (2026-08-28, 2026-09-02, 2026-10-05,
2026-10-13, 2026-10-18), so it reported only what was new. The media
reviewer built hostile files and ran the readers on them. Every finding
below was then checked on the test site, the same input sent to both
stacks, before it was fixed; where the reference does something, the fix
does that, measured, not remembered.

The same day, four agents measured 228 of the facade review's 502 doubts
against the reference (`contracts/api/facade-review.json`). Three of
those real differences were security-relevant and are in the table (17,
20, 21); the rest are parity work, listed in the register.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | High | A 46-byte FLV whose H.264 parameter set ends after `pic_order_cnt_type = 1` hung the worker: the bit reader reads zeros past the end, so the cycle count came out as 4,294,967,295 and the loop ran for hours. Any author uploading a video reached it. | n/a (the reference reports only the file's size for it). | The cycle is capped at 255 (the format's limit) and stops when the bits run out; a set read past its end gives no size. 0.00 s. |
| 2 | Medium | The database door refused every query holding bytes that are not UTF-8, whatever its table. Plugins that keep binary columns (Wordfence's `BINARY(16)` IP addresses) send such bytes on every lockout write and check, so the lockout was never written and every check read "not locked". | A table with a binary or blob column is `binary` and nothing is refused; a latin1 table holds any byte; utf8 refuses four-byte characters, ascii anything past ASCII, another charset what it cannot encode. | Same, measured over sixteen table shapes (`wpdb::get_table_charset` reads every column, `Utf8::fits`); a latin1 value is cut by bytes. |
| 3 | Medium | The latest-posts block in full-content mode printed password-protected posts whole, to any visitor of a page holding the block and to any contributor through the block renderer. | "This content is password protected." in either mode. | Same. |
| 4 | Medium | An ID3 text frame of a million terminators took 14.5 s to split (each part copied the rest of the string), and a 4 MB one exhausted 256 MB. | Shows the first value only. | The text is read along with an offset, at most 1,024 values kept: 0.01 s, 14 MB. |
| 5 | Medium | A 98 KB AVI of 4,096 header lists, each with a stream list claiming 4 GB, took 9 s: each stream list was walked to the end of the file. | n/a. | A stream list ends with the header list holding it: 0.01 s. |
| 6 | Medium | A caption shortcode's `caption` attribute was printed raw. Shortcode attributes are not markup when kses runs on save, so a contributor's caption could carry what kses would have removed. | The attribute's caption goes through the post allowlist; a caption written as the shortcode's content, filtered on save, is printed as it is. | Same. |
| 7 | Low | A movie box of a million empty boxes took 60 MB per MB of file (each box an array), up to about 3.8 GB under a raised memory limit; a file of 8-byte boxes was walked to its end (5.9 s for 16 MB). | Costs more still (1.6 s and 480 MB; 14 s). | At most 4,096 boxes a level; the top-level walk stops once the movie box and the media data are found. |
| 8 | Low | A declared length became one allocation of that size: a 20-byte file declaring a 256 MB ID3 tag asked PHP for 256 MB. | n/a. | No read asks for more than the file holds. |
| 9 | Low | A Matroska track sampled zero times a second, or an FLV whose duration is text or a flag, threw out of the reader, leaving the upload's attachment without metadata (a 500). | Only the file's size. | The readers take only numbers for numbers, and a file whose fields make no sense is measured as its size. |
| 10 | Low | An audio file's cover was saved with the extension and type its tag declared, so a cover declared `text/html` or `application/javascript` was written to uploads as `.htm` or `.js` (when an administrator's upload made the metadata). | The cover is named for what its bytes are: JPEG, PNG and GIF as themselves, anything else `.jpg` of no type, whatever the tag says. | Same. |
| 11 | Low | MPEG audio in a wave file was scanned frame by frame to its end, so a 64 MB file took 13 s (and its rate was wrong). | The data must start with a frame; 51 frames in a row (or fewer to the end) start the stream; 51 at one rate make it constant, nothing more read; otherwise every frame to the end, across gaps, makes the mean. | Same, measured over twenty-six files: 0.00 s. |
| 12 | Low | Comments nested deep made kses recurse once per opener (8,000 overflowed the stack) when `Kses::allowing` was called directly. `wp_kses`, `wp_kses_post` and `wp_filter_kses` escape unclosed openers first, on both stacks, so no visitor reaches it. | n/a. | Past eight levels a comment's `--` is collapsed before its body is passed again: 16,000 openers in 0.01 s. |
| 13 | Low | An author's autosave of their own draft was saved into the post itself even while an editor held its lock, so a stale tab overwrote the editor's work. | Saved in the author's autosave slot while another user holds the lock. | Same (`Runtime\PostLocks`, which `wp_check_post_lock` now delegates to). |
| 14 | Low | `admin-post.php` chose the visitor's or the signed-in handler by the admin cookie but signed the request in by the front end's, so a visitor's handler ran as a signed-in user, and the admin cookie alone signed no one in. | Either cookie signs the request in, the admin cookie's user first, and the signed-in handler runs as that user. | Same, five cookie cases compared over HTTP. |
| 15 | Low | `create_post_autosave()` defined `DOING_AUTOSAVE` for the rest of the request, so any later post save in it kept no revision and plugins' save hooks skipped their work. | Defines nothing; only an autosave request does. | Same. |
| 16 | Low | The engine's own post, comment and user inserts were never checked for text the tables cannot hold (their values went as one list), and a refused post update went on as if saved. | A refused post insert or update is `db_insert_error` / `db_update_error` naming the field, before any save hook; a comment insert is false, its update an error; a term whose name sanitizes to nothing is `invalid_term_name`, one whose description is refused `db_insert_error` (its term row left). | Same; the check reads a list value by value, and `Db::insertId()` is 0 after a refused insert. |
| 17 | Low | A refused meta write still answered: `add_metadata` returned the connection's previous insert id (another row's) and fired `added_*_meta` with it; the updates said true. | False, and no "added" or "updated" action. | Same. |
| 18 | Low | `nonce_user_logged_out` was asked for signed-in users too, so a filter mapping an action to 0 made a visitor's nonce valid for any signed-in user. | Asked only for a visitor. | Same. |
| 19 | Low | `wp_validate_logged_in_cookie()` signed a user in by the front end's cookie in the blog admin too. | False in the blog and network admin: only the admin cookie signs in there. | Same. |
| 20 | Low | `settings_errors()` printed a code and type into the id and class unescaped. | `esc_attr`. | Same. |
| 21 | Low | `do_settings_sections()` printed a section's `before_section` and `after_section` raw. | Through `wp_kses_post`. | Same. |

## The doubts' security fixes

The same day, the doubts' second wave (274 left after the first) was measured by four more
agents. Whatever touched permissions, escaping, files or stored data was fixed first, each to
what the reference was measured doing. Several claims held only for a facade class method called
directly, while the live route already matched WordPress. Those are fixed in the facade too, so a
plugin calling the class gets the reference's answer.

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 22 | Medium | A contributor's post could carry `<!-- wp:template-part {"tagName":"script"} -->`: kses on save left the tag name alone. | A template part's tag names, nested ones too, are kept to tags the allowlist knows; `script` becomes `""`. | Same, in `filter_block_content` and `wp_pre_kses_block_attributes` (the save path) and `filter_block_kses_value`. |
| 23 | Medium | `list_users` alone opened another user's edit view (email, capabilities) over REST. | `rest_forbidden_context` unless the caller may `edit_user`; a list shows edit fields only for users the caller may edit. | Same. |
| 24 | Medium | Deleting a user with itself as the reassign target deleted the user and the content meant to be kept. | `rest_user_invalid_reassign` (400); nothing deleted. | Same. |
| 25 | Medium | REST notes (`type: note`) were created by anyone who could comment, and drafts took comments from administrators. | A note needs `edit_post` on its post and ignores closed and draft; a comment on a draft is refused. | Same. |
| 26 | Medium | A mailed password reset link stayed valid after a good sign-in. | The sign-in clears the pending key. | Same, on the form and in `wp_signon`. |
| 27 | Medium | An active plugin path climbing out of the plugins folder (`zz/../../x.php`) or not ending in `.php` was loaded. | Only `.php` paths that pass `validate_file`. | Same. |
| 28 | Medium | `delete_old_plugin` with `'/x.php'` would have deleted the whole plugins folder. | The plugins folder is never deleted as a tree; a plugin without its own folder loses only its file. | Same, through `$wp_filesystem` when one is set; `..` stays refused. |
| 29 | Medium | An MO translation object over a `.php` path included it. | Each class reads its own format: an MO object over PHP is "Magic marker does not exist"; another revision is "Unsupported revision". | Same, the revision refused on every MO path. |
| 30 | Low | `maybe_serialize` stored `E:` (enum) and loosely serialized strings raw, so WordPress would unserialize them after a switch back. | `is_serialized` strict by default, loose for storage; sixty strings measured. | Same. |
| 31 | Low | The read-more block printed its text unfiltered; the recent posts widget printed links unescaped. | `wp_kses_post`; `esc_url(apply_filters('the_permalink', ...))`. | Same. |
| 32 | Low | Term data bindings read a hidden taxonomy's terms to visitors. | `null` unless the caller may edit the term. | Same. |
| 33 | Low | The RSS widget fetched the site's own address, and fetched text before "http" as part of the URL. | Refused; the URL starts at its first "http". | Same; feeds are fetched at their normalized address and a cached feed past its lifetime is fetched again. |
| 34 | Low | `wpdb::set_prefix` took any prefix; the placeholder token was a fixed hash without `AUTH_SALT`. | `invalid_db_prefix` outside `[a-z0-9_]`; a random token per request. | Same; an empty query (`''`, `'0'`) is false before anything is reset. |
| 35 | Low | Sendmail took senders with `+`, `=` or non-ASCII letters on its command line, and `-f` went even without the sendmail options. | Only ASCII letters, digits, `@ . _ -`; `-f` only with `UseSendmailOptions`; the sender is PHP's `sendmail_from` for the send. | Same. |
| 36 | Low | The facade's sticky, revision-delete, protected-post comment and media MIME checks were looser than the live routes. | Sticky needs `edit_others_posts` and `publish_posts`; a revision delete needs `edit_post` on the parent (the live route too); a protected post's comments need `edit_post`; disallowed MIME types are dropped. | Same. |
| 37 | Low | `WP_REST_Request` validated every argument by its schema, so an argument with its own sanitize callback was type-checked anyway, and one with its own validate callback was coerced. | Only a named validate callback validates; the schema is checked as the argument is sanitized (`rest_parse_request_arg`). | Same, with batch validation and the search fallback checking the sanitize result. |
| 38 | Low | The sitemap providers listed what the sitemap never shows when asked directly: nav menus as a taxonomy, nav menu items as a post type. | A subtype not among the provider's own gives an empty list, before the pre filter. | Same, in each provider's `get_url_list`; the served sitemap no longer checks the subtype a second time. |

Pinned by probes `sweep-1020b`, `feed-urls`, `rest-notes`, `post-doubts`, `comment-doubts` and
`translation-files`, and the login suite's "a good sign-in ends a pending password reset".

## Checked and not a finding

- **`wp_validate_redirect('http://u@ser@minn.localhost/')`** is accepted by the engine and
  refused by the reference, but a browser reads the last `@` and stays on the site's own host.
  The other seventeen tricky addresses compared (backslashes, encoded `@`, ports, fragments,
  leading whitespace) answer the same on both stacks. The relative-path differences are parity
  rows.
- **A comment of 8,000 nested openers through `wp_filter_kses`** (the anonymous commenter's
  path) is escaped in one pass on both stacks, byte for byte.
- **The REST argument rounds** (`SANITIZE_ROUND`, `BARE_REFUSAL`) run after the main round, in
  batches too; refusals say nothing about whether a comment or user exists.
- **A REST batch cannot carry `DOING_AUTOSAVE` into later sub-requests**: autosave routes are
  not batchable.
- **`OptionLock`** takes a lock over after an hour; holding one forever needs an administrator.
- **`Users::setMeta()`** reads its value as stored (a serialized string is revived), as its
  callers all pass what the engine serialized itself; noted in its doc comment.
- **`BlockFilters`** keys its entries by object id; a render that throws can leave one behind.
  Harmless while each request is its own process; to clear in the renderer's `finally` before
  worker mode.

## Parity found on the way

- **Table charsets** (`wpdb::get_table_charset`): the engine took the first collated column's;
  now every column counts, as above, and a table without text has none.
- **Caption ids** are classes (`sanitize_html_class`) and given once a page: a repeat is
  `id-2`, `id-3`, a caption id of the shortcode's own counted with them
  (`RenderState::uniqueCaptionId`).
- **Hostile files the reference cannot read** (the FLV, Matroska and AVI above) still give a few
  more fields on the engine than the reference's bare size: a register row, not a risk.

## Pinned by

`tests/unit/media-av.php` ("files made to hurt the readers"), `tests/unit/kses.php` ("comment
openers 16,000 deep"), probes `table-charsets`, `refused-writes`, `mpeg-in-wave` and
`sweep-1020` (all compared with the reference by the api suite and the hook battery), and the
runtime suite's admin-post rows ("only the front end's cookie", "only the admin cookie"). Each
finding is counted in `tests/tools/scorecard.php` against the check that pins it.
