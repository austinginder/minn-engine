# Security review, 2026-11-12

The sweep of the 0.2.0 release candidate (RC1, built from 572e1685). It covers what changed in `public/minn/` since the 2026-10-23 sweep (`bda1b2c5..572e1685`, 127 files):
- the extension surface: pages and REST routes, blocks, tables, declared types, the sign-in seams and `MINN_WP_API`;
- recovery's request to the site itself;
- the laid `pluggable.php` and the page-cache early load;
- the upgrade folder and `minn login`;
- the WordPress 7.1.4 ports;
- the Minn Admin data the engine now mirrors.

Three reviewers read the unpacked release candidate, one surface each, changing nothing and sending no request:
- extensions and accounts;
- boot, recovery, updates and files;
- content, rendering and the admin's data.

No reviewer read WordPress source. Each started from the 2026-10-20, 10-22 and 10-23 reviews and reported only what was new. Their reports are kept beside the build (`dist/0.2.0-rc1/findings-*.md`, not in git).

Every finding was then checked on the test site, sending the same input to both stacks, before it was fixed. Each fix ships with a check that fails on the code before it.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 2.1 | High | The default park (`wp-parked` beside the webroot) left a complete WordPress, with no `wp-config.php`, inside the parent folder. Where that folder is served (an addon domain in `public_html`), anyone could open `/wp-parked/wp-admin/setup-config.php`. Unchanged since 0.1.0. | n/a | Install refuses the default park when the parent looks served: it holds `index.php`, `.htaccess`, `wp-config.php` or `wp-load.php`, or carries a host's webroot name. Every park also carries an Apache refusal, which eject removes. Suite `install`. |
| 3.1 | High | Minn's interactivity script bound any address from context, so an author's `data-wp-context` set a `javascript:` `href` (or `formaction`) that ran on click. | Measured in a browser with the reference's own script: it removes the attribute. | The script binds no address whose scheme a link may not carry, and removes the attribute as the reference does. It never binds `srcdoc` or `form`, and a style binding sets no `url()`. Suite `browser/interactivity`. |
| 1.1 | Medium | Maintenance mode did not hold REST in any mode, extension pages in any mode, or public pages under `MINN_WP_API` auto/off. The engine removed Minn Admin's REST hold although it has none of its own. Predates this cycle in part. | Holds pages and REST with a 503 (measured). | The engine keeps Minn Admin's holds. While maintenance is on, every request boots in full, and extension pages answer only someone with `edit_posts`. Suite `wp-api-modes`. |
| 1.2 | Medium | On REST, an extension's blocks rendered without its block gates and content filters, so a block Block Visibility hid on the page came back in `content.rendered`. | `content.rendered` runs the content filters (hypothesis). | REST keeps the gates, block filters, shortcodes and content filters. Suite `extensions`. |
| 3.2 | Medium | A post's author could give a comment on their post to any user over REST and read that user's email back. | Allows it (measured, as an Author). | Minn refuses a changed `author` without `moderate_comments`, as `create` does. A deliberate divergence, reported to Austin as a possible upstream issue. Suite `security-sweep`. |
| 1.3 | Low | `Accounts::register*` accepted any role the site has, so an extension passing a visitor's choice through could make an administrator. | `register_new_user` never takes a role (hypothesis). | A role holding more than the default role is refused (`role_not_allowed`). Suite `extension-accounts`. |
| 1.4 | Low | `Accounts::signOut` ended the session the cookie named, with no nonce, so a page on another site could sign a player out. | Logout needs its nonce (hypothesis). | Only the session the REST gate accepted is ended; otherwise only the cookies are cleared. Groddle's client sends its nonce. Suite `extension-accounts`. |
| 2.2 | Low | Recovery walked a storage failure's trace to the plugin that happened to write at boot, so a full disk, a deadlock or a cache backend going down could pause a firewall or 2FA plugin. | Nothing is paused outside recovery mode (hypothesis). | A failure in `Db`, `Options`, `OptionWrites`, `Transients` or a drop-in is not walked. Unit `recovery-blame`. |
| 2.3 | Low | The self-check sent a fake sign-in cookie, and confirmed a pause whatever failed. | n/a | The check sends no cookie and sends browser headers. Matching the failure itself is left (below). |
| 2.4 | Low | The strikes row grew by one time per failing request and was autoloaded. Concurrent first failures each sent a check. | n/a | One time per extension, written only when new, autoload off. The check is claimed with an insert, so one request asks. Suite `recovery`. |
| 2.5 | Low | `advanced-cache.php` read the request before the server variables were made whole (the 10-23 #2 fix stopped short). | Made whole first (measured: an empty `REQUEST_URI` reads as `X-Original-URL`). | `fixServerVars()` runs at the top of `bootstrap.php`. Suite `dropins`. |
| 2.6 | Low | The upgrade folder's `index.php` was written only if the folder already existed, and an install that failed after unpacking left its package there. | To settle. | The folder and its index are made before the unzip, and a failed install's working folder under `upgrade/` is removed. Suite `upgrades`. |
| 2.7 | Low | `minn login` built its link on the database's `home`, so a moved site's old domain received a live token. | n/a | The link uses wp-config.php's `WP_HOME`, and minting is refused when `WP_HOME` is set in a way Minn cannot read. Suite `cli`. |
| 3.3 | Low | Minn Admin's bell listed editorial notes. | The plugin's `get_comments` leaves them out (measured). | Notes are left out of both queries. Suite `minn-v1`. |
| 3.4 | Low | Style bindings set CSS kses refuses (`url()`). | Binds it (not measured further). | The client sets no `url()`, `image-set()`, `expression()`, `@import` or backslash in a style binding. Suite `browser/interactivity`. |
| 3.5 | Low | With canonical redirects off, a query loop's page links were built from the raw path, so `//elsewhere/` gave links to another host. | Answers `//path` with a 301 to the collapsed path even without `redirect_canonical` (measured). | Leading slashes and backslashes collapse to one. Suite `security-sweep`. |
| 1.5 | Info | An extension's table could take a core table's name (slug `term`, table `term_relationships`) or a name inside another extension's. | n/a | Both refused. Unit `extension-tables`. |
| 1.6 | Info | Concurrent wrong guesses at a one-time link each read the same miss count, and a stored hash of a token still opened it. | n/a | The helper's seven-character token burns on its first miss, and the hash branch is gone. Suite `hardening`. |
| 1.7 | Info | The Minn bar and its nonce followed the cookie's session, not the reader plugins settle on. | Follows the settled user (hypothesis). | The bar is built only for the settled reader's own session. Suite `security-sweep`. |
| 2.8 | Info | The laid `wp-includes/pluggable.php` requested directly answered a 500 that named the engine and the path. | An empty 200 (measured). | The header returns when the engine is not loaded. Suite `security-sweep`. |
| 2.9 | Info | Under `MINN_WP_API` auto, the System view passed while plugins no longer decided who reads a public page. | n/a | The row warns. Suite `wp-api-modes`. |
| 2.10 | Info | `confirmed()`'s comment said a token is spent either way. | n/a | Corrected. |
| 3.6 | Info | `<!-- wp:template--part {"tagName":"script"} /-->` passes kses and is stored as `wp:template-part` with that tag. | The same kses output (measured), and the reference renders a `<script>` wrapper; Minn refuses the tag at render. | Left: Minn's render already refuses it. Reported to Austin as a possible upstream issue. |
| 3.7 | Info | `?paged=` (and `?hour=`, a sitemap page) at `PHP_INT_MIN` answered 500: `abs()` made it a float. | Answers 200 (measured). | `Support\Whole::abs` reads it as 0, used by every whole-number read in `src`. Suite `security-sweep`. |
| 3.8 | Info | A hostile pattern-directory answer (an object where text belongs) failed the route for the hour it was kept. | n/a | Every field reads as a plain value or nothing. Suite `security-sweep`. |
| 3.9 | Info | A decoy element with the server data's id switched off interactivity server state. | n/a | The script reads the first `script` element with that id. Suite `browser/interactivity`. |
| 3.10 | Info | `wp_kses_one_attr` skipped the 7.1.4 binding check. | Drops it, and keeps the whitespace around any refused attribute (measured). | Both now as the reference. Probe `shortcode-run`, 12 rows. |

## Two findings measured against the reference wrongly at first

- **3.1.** The first browser comparison showed the reference binding `javascript:` too. Its `?p=` request had followed the reference's redirect to the post's address on `minn.localhost`, so both visits ran the engine. The reference's own pages also load `/wp-includes/` scripts from that host, so the second measurement served the reference's own interactivity script from its folder through the browser's request routing. A reference-side check must request the reference's path directly and serve its `/wp-includes/` from `wp-reference/`.
- **3.2** was measured with requests straight to the reference's REST route, with no redirect, and then confirmed against the fixed engine refusing.

## Left, and why

- **Matching the self-check's failure (2.3).** Recovery still pauses when the engine's own request fails anywhere in the same extension, not only with the same file, line and message. What a visitor can steer in that request is now only the server's own address, which every self-request carries. Matching the failure exactly would need the original failure stored, a transformative change for the next cycle.
- **`MINN_WP_API` auto/off (Info, boot reviewer).** On the runtime-free lane, sign-in is judged on the stored sessions alone, and plugin access controls do not run on public pages. That is what the mode is for; the System view now warns (2.9).
- **Parity notes, not security.** At `PHP_INT_MIN`, `?hour=` answers 200 where the reference answers 404, and a sitemap page answers 200 where the reference redirects to a float-named sitemap. With `redirect_canonical` removed, the reference still moves `//path` to `/path`, and the engine does not.
