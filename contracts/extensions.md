# Extensions

How code that is not the engine takes part in a request, and how a WordPress
plugin's behaviour is re-provided on Minn. Code: `public/minn/src/Minn/Extension/`.
Suites: `tests/extensions.test.php` (24 checks), `tests/extension-records.test.php` (29 checks: REST routes and tables). First port: `extensions/minn-block-visibility`.

## What an extension is

A folder under `wp-content/plugins/` (or `mu-plugins/`) with a `minn.json`:

```json
{
  "name": "Block Visibility for Minn",
  "version": "1.0.0",
  "license": "MIT",
  "replaces": ["block-visibility/block-visibility.php"],
  "autoload": {"Minn\\Ext\\BlockVisibility\\": "src/"},
  "extension": "Minn\\Ext\\BlockVisibility\\Extension",
  "needs": {"http": ["https://api.example.com/"]}
}
```

The class implements `Minn\Extension\Extension` (one method, `register(Seams $minn)`)
in plain modern PHP: PSR-4 from the manifest, typed seams, no globals, no hook
names to remember. `Seams` is the whole surface an extension sees: the ten
registrations below plus the request it runs in (`db`, `site`, `request`,
`reader`) and the outgoing HTTP its manifest asks for (`http`). What the engine does with the registrations lives in
`Minn\Extension\SeamRunner`, which an extension never touches. The same folder may also be a WordPress plugin; nothing in the
manifest is read by WordPress and nothing in the plugin's PHP is read by the engine.

**Activation** follows the levers the site already has: the extension is active when
one of the plugin files it `replaces` is in `active_plugins`, when its own folder is
in `active_plugins` (a plugin that is also an extension), when its slug is in the
`minn_active_extensions` option (JSON list), when it lives in `mu-plugins`, or when
it is the active theme: a theme folder with a `minn.json` is an extension too, on while
it is the theme (the child's, then its parent's) and off the moment the theme changes,
with no plugin switch. A block theme brings the look as templates and `theme.json`;
its manifest adds what templates cannot, such as `routes` for pages that are not
content. A theme's `functions.php`, if it has one, still loads through the runtime.
`wp minn info` lists the active set; `minn preflight` reports a replaced plugin as
"provided by" its extension instead of "will not run".

Each extension registers on a `Seams` of its own, and what it registered joins
the request's only once its `register()` returns: an extension that throws while
registering is logged and skipped whole, and the page renders without it. A
closure the engine hands seams to (a gate, a block filter, a shortcode, `head`,
`footer`) is handed its own extension's.

**`needs`** says what the extension reaches outside the process. `"http"` is a
URL prefix or a list of them, or `true` for any public host. `$minn->http` is a
`Minn\Http` client bound to those prefixes at every hop, on top of any
`hosts:` a call names (a prefix naming only a host is closed with `/`). With
no `needs.http`, every request on `$minn->http` throws a `LogicException`
naming the key: `acme-footer did not ask for http: add "needs": {"http":
["https://api.example.com/"]} to its minn.json.` The other keys the 2026-09-02
review named (`seams`, `options.read`, `content.read`, `meta.write`) come with
the rest of B5. `tests/unit/extension-grants.php` proves the grant.

## The seams

`Seams` carries the request (`db`, `site`, `request`, `reader`, and `user()`:
the signed-in `UserRecord` the request speaks for, or null), the granted
`http`, and the
registrations, each a typed closure, called in registration order:

| Seam | Signature | Fires |
|---|---|---|
| `gateBlocks` | `(Block, Seams): ?bool` | before any named block renders; `false` drops it (and its children) |
| `filterBlocks` | `(Block, string $html, Seams): string` | after a named block renders |
| `shortcode($tag)` | `(array $attrs, ?string $content, Seams): string` | on rendered post content and feed content: `[tag]`, `[tag a="b" flag]`, `[tag]inner[/tag]`; `[[tag]]` is the literal; unregistered tags stay as written (the reference leaves them too, texturized) |
| `filterContent` | `(string $html, array $post): string` | after blocks and shortcodes, for post content and feeds |
| `head` / `footer` | `(Seams): string` | in the themed document, after the theme stylesheet and before `</body>` |
| `bodyClass` | `string` | appended after the theme classes |
| `filterDocument` | `(string $html): string` | the whole themed document before it is sent (what an output buffer did on the reference) |
| `routes` | `object ...$controllers` | controllers whose public methods carry `#[Route]`, as the engine's own do (`Request` and the captures in, `Response` out, a `Policy` each); asked after every engine route and before the themed front, so an extension adds pages but never takes over one the engine answers; a path its pattern refuses falls through to the front |
| `rest` | `object ...$controllers` | REST routes under `/wp-json/` (and `?rest_route=`): controllers with `#[Route]` methods whose pattern leaves out `/wp-json` (`/acme/v1/notes/{id:\d+}`), judged by the engine's REST gate exactly as its own routes are. The sign-in cookie counts only with its `wp_rest` nonce (`X-WP-Nonce` or `_wpnonce`), or an application password; a signed-out caller on an `Access::SignedIn` route gets 401 `rest_not_logged_in`, a nonce that does not verify 403 `rest_cookie_invalid_nonce` on any route, a missing capability 403 with the policy's code. Asked after every engine route and before plugins' routes, inside the same envelope (the REST server's filters run around them); the REST index lists their namespace. `user()` inside a handler is the caller the gate accepted. Each pattern must name a namespace of the extension's own: one under `wp`, `wp-*`, `oembed` or `minn-admin`, or a pattern with no namespace, throws and skips the extension. A batch (`/batch/v1`) does not reach them yet |
| `block($metadata)` | `(array $attributes, string $content, Seams): string` | a block of the extension's own, wherever blocks render: the themed front, REST answers (`content.rendered`) and Minn Admin's editor previews. `$metadata` is a folder holding a `block.json`, or the file, relative to the extension's folder; the engine registers it as a dynamic block type from that file (title, attributes with types, enums and defaults, `parent`, `supports`, `example`), so `wp/v2/block-types` lists it and Minn Admin edits its attributes with a form and offers it in the inserter. `$attributes` arrive with the block.json defaults filled in; `$content` is the block's inner blocks, already rendered. A file that is missing or names no block skips the extension |
| `editorStyles` | `string ...$hrefs` | stylesheets Minn Admin's block previews load after the theme's `style.css` (`minn-admin/v1/editor-styles`): what an extension's blocks are drawn against when the theme's own stylesheet is not all of it; a path from the site root becomes the site's URL |

Extensions register on REST requests too, where their blocks and their `rest` routes take part: the filters, shortcodes, head and page `routes` stay with the themed front, as before. On a page, `user()` and `reader` are the sign-in cookie alone (no nonce), so a page route may read with them but a write belongs on a `rest` route.

## Accounts for a sign-in surface of an extension's own

`$minn->accounts()` (`Extension\Accounts`) lets an extension's REST routes run
their own sign-in surface (a game's players, a members' area) on the site's
accounts. Each call is what the engine's sign-in page does, with the same
throttle, hooks, sessions and mail, handed back as a value the extension words
itself; a refusal is a `Runtime\Refusal` whose `code` and `message` (plain text,
no markup, no "Error:" label) are the first notice and whose `data.notices` lists
every `{code, message}`. They run the sign-in hooks plugins listen on, so they belong on `rest` routes.

| Call | Does | Refusals |
|---|---|---|
| `signIn(Response, login, password)`, `signInRemembered(...)` | the authenticate chain with the password slashed, as a form sends it (login or email); on success a new session and the three sign-in cookies on the given response, `wp_login` fired: `SignedIn {user, response}`. `signIn` lasts two days, ending with the browser; `signInRemembered` fourteen days, kept past it | `too_many_attempts` (`data.retryAfter` seconds; the per-address `LoginThrottle`, shared with the sign-in page); `incorrect_credentials` for every refusal of the chain's own (never which half), a plugin's own refusal in its own words. Each refusal counts against the throttle |
| `signOut(Response)` | ends the session the sign-in cookie names and expires the cookies, `wp_logout` fired, when the request carries that session's REST nonce (the `X-WP-Nonce` header or a form's `_wpnonce`, never the query string); with no live session it only expires the cookies; a live session without its nonce, or with a lapsed one, is refused and changes nothing, so another site, or a navigation to an extension page that signs out, signs nobody out, while a client whose nonce lapsed fetches a fresh one and asks again | `rest_cookie_invalid_nonce` (403) |
| `registrationOpen()` | `users_can_register` | |
| `register(login, email, password, shownAs = '', role = '')` | the sign-in page's registration checks (sanitised name, `illegal_user_logins`, `user_registration_email`, `register_post`, `registration_errors`), then the account with `role` (a role the site has, holding no capability beyond the default role's that WordPress's administrator or the site's administrator holds, so never administrator, editor or a plugin's administrative role; else the site's `default_role`), the chosen password (stored slashed, so wp-login.php accepts it) and, given one, `shownAs` as its display name and nickname (an email-only sign-up makes up a login of its own and shows the person's chosen name instead), and the site's address told (`wp_new_user_notification` for `admin`). No password nag, no mail to the holder, `register_new_user` not fired (its default listener mails a link to set a password). Nobody is signed in: the route calls `signIn` after when it wants that | `registration_closed`, `unknown_role`, `role_not_allowed`; every refusal at once (`empty_username`, `invalid_username`, `username_exists`, `empty_email`, `invalid_email`, `email_exists`, `empty_password`, a plugin's) |
| `registerQuietly(login, email, password, shownAs = '', role = '')` | as `register`, with the site's address told nothing (no `wp_new_user_notification`): for a sign-up whose accounts nobody needs to hear about one by one, such as a game's players | as `register` |
| `passwordHash(password)` | the password hashed as an account stores it (slashed, as a sign-in form sends it, through `wp_hash_password`), for a sign-up that holds it until the email is proven: the hash, never the password, is what the extension keeps | |
| `registerQuietlyWithHash(login, email, hash, shownAs = '', role = '')` | as `registerQuietly`, the account's password the one `passwordHash` hashed earlier: made with a password nobody knows, then given the held hash before anyone can sign in, the user's caches let go; the hashed password signs in here and at wp-login.php | as `register`; a hash that is empty, spaced, or not `$`-led and 20 to 255 characters is `empty_password` |
| `signInVerified(Response, user)` | a sign-in without a password for a user the extension vouches for itself (an emailed single-use link proved the address): as `signInRemembered` once the password checks out, fourteen days, the cookies on the response, `wp_login` fired, any pending reset key dropped: `SignedIn`. The extension's own proof is the only check, so it belongs after a single-use secret of the extension's own | none |
| `mail(to, subject, body, headers = '')` | an email of the extension's own (a sign-up's link) through the site's mailer (`wp_mail`: its filters, an SMTP plugin), headers as `wp_mail` takes them (an HTML mail's `Content-Type`, a `From`): whether it was accepted | |
| `lostPassword(login, ?Closure link, ?Closure words)` | the lost-password page's reset mail (`retrieve_password`); `link(login, key)` makes the mailed link for a reset page of the extension's own (else it opens the sign-in page's reset form), `words(link, displayName)` returns the mail's `[subject, body]` or `[subject, body, headers]` (an HTML mail's `Content-Type`, a `From` of the site's own) for a site whose people never see a login (else the sign-in page's mail, which names the login); the `retrieve_password_*` filters still run after either | its refusals (`empty_username`, `invalid_email`, `invalidcombo`, a plugin's, the mail failing) |
| `changePassword(Response, user, current, new, ?Closure words)` | a signed-in user's new password once the current one checks out (the sign-in throttle counts a wrong one): saved as a profile save saves it (`wp_update_user`, its hooks, the password-change notice to the account's address, `words(displayName)` returning `[subject, body, headers?]` in place of the reference's, early, so plugins still filter it), every session ended and a new one on the response (a cookie is bound to the password it was made under): `SignedIn` | `too_many_attempts`, `incorrect_password`, `empty_password`, a plugin's |
| `changeEmail(user, current, email, ?Closure words)` | a signed-in user's new email once the current password checks out, the old address told (in `words` when given): the user | `too_many_attempts`, `incorrect_password`, `invalid_email`, `email_exists`, a plugin's |
| `resetPassword(login, key, password)` | the reset form's save: the key still good (a day, or `password_reset_expiration`), `validate_password_reset` asked, the password stored (`password_reset`, `after_password_reset`), every session of the account ended; the user. Nobody is signed in | `too_many_attempts`, `invalid_key` (counts against the throttle), `expired_key`, `empty_password`, a plugin's |
| `passkeyOptions(user)` | options for `navigator.credentials.create()` adding a passkey to a signed-in user, in the JSON form `PublicKeyCredential.parseCreationOptionsFromJSON()` reads: a fresh single-use challenge, the site as relying party, the user's random handle, ES256/EdDSA/RS256, a discoverable passkey with user verification required, no attestation, the user's passkeys excluded. The extension adds client extensions such as `prf` in the browser (`contracts/passkeys.md`) | |
| `addPasskey(user, credential, label)` | keeps the passkey the browser made (its `toJSON()`), labelled (trimmed, at most 100 characters): a `PasskeyRecord` (`toArray()` is what a person may see: id, label, transports, created, lastUsed, synced; never the key) | `passkey_refused` with `data.reason` naming the check (`challenge`, `origin`, `party`, `verification`, `key`, `taken`, ...) |
| `passkeySignInOptions()` | options for `navigator.credentials.get()` signing in with any of the site's passkeys (`parseRequestOptionsFromJSON()`): a fresh single-use challenge, no user named | |
| `signInWithPasskey(Response, credential)` | a sign-in with the browser's answer: the passkey checked, its counter kept, then as `signInVerified` (fourteen days, the cookies on the response, `wp_login` fired): `SignedIn`. The authenticate filters are not asked: the passkey is the proof | `too_many_attempts` (the same per-address throttle as passwords); `passkey_refused` (`That passkey could not sign you in.`, `data.reason`); each refusal counts against the throttle |
| `passkeys(user)`, `removePasskey(user, id)` | the user's passkeys newest first; removing one by its id, whether the user had it (another user's id is `false`) | |

A signed-in browser needs the new session's `wp_rest` nonce before it calls a
signed-in route: `wp-admin/admin-ajax.php?action=rest-nonce` hands it out once the
cookie is set. `tests/extension-accounts.test.php` (fixture
`minn-test-records/src/Players.php`) pins all of it, including a password with
quotes and a backslash signing in at `wp-login.php`, also when it was held as
a hash before the account existed. `tests/passkeys.test.php` and
`tests/browser/passkeys.test.js` (fixture `minn-test-passkeys`) pin the passkey calls.

## Tables of an extension's own

`"tables"` in `minn.json` declares tables the extension owns, as a name and its
column and key lines:

```json
"tables": {
  "acme_notes": [
    "id bigint(20) unsigned NOT NULL AUTO_INCREMENT",
    "user_id bigint(20) unsigned NOT NULL DEFAULT 0",
    "body longtext NOT NULL",
    "PRIMARY KEY  (id)",
    "KEY user_id (user_id)"
  ]
}
```

A name is the extension's slug with dashes as underscores, then `_` and more
lowercase letters, digits or underscores (`acme-shop` owns `acme_shop_*`), at
most 64 characters with the prefix; a line is one column or key definition (no
`;`, line break or comment). The engine builds `CREATE TABLE IF NOT EXISTS
{prefix}{name} (...)` in the collation of the site's options table and applies it
through `Runtime\DbDelta` before the extension registers (`Extension\Tables`), so
a route finds its table on the very request that created it. Option
`minn_extension_tables` records, per slug, the version and a digest of the
declaration last applied; while both stand a request applies nothing (one
autoloaded read). A new version, or a changed declaration, applies again:
missing tables are created and missing columns and keys added, in DbDelta's
order. Nothing is ever dropped, renamed or changed: a column a later version
leaves out stays with its data, a type change is not made, and the tables stay
when the extension is switched off. A name outside the extension's own or a
line that is not one definition throws, which skips the extension (logged) and
records nothing; a statement the database refuses does the same, and the next
request tries again. The extension reads and writes its tables through
`$minn->db` (`$minn->db->table('acme_notes')` is the prefixed name): the engine
is their only writer, and they travel with the site's database in a backup.

An extension page is a whole document the extension built, so the engine adds nothing to it. `$minn->frontBar($html, ?PostRecord $post)` gives it the Minn bar a signed-in editor sees on themed pages (the bar's stylesheet before `</head>`, the bar before `</body>`; with a post named, the bar's Edit opens it) and returns anyone else's document unchanged. The bar is settled before extensions register.

A manifest may add `"covers"`: what part of the replaced plugin the extension
provides when it is not all of it; preflight prints it and lights AMBER instead of
GREEN for that plugin. `"shortcodes"` and `"blocks"` name the content tokens
the extension actually renders; preflight treats those as provided and still
AMBERs any other shortcode or third-party block in the site's content.
`"types"` lists extra post types and `"taxonomies"` extra taxonomies, and
the engine registers each on `init` as a plugin registers its own, through
the facade's `register_post_type` and `register_taxonomy`
(`Runtime\DeclaredTypes`, taxonomies first so a term's address under a
type's prefix reads as the term). So they take a plugin's defaults, rewrite
rules, archive, template hierarchy (`single-{type}`, `archive-{type}`,
`taxonomy-{taxonomy}`), REST shape at `wp/v2/{rest_base}` (with `/revisions` when the type supports them and `/autosaves`, as for any type plugin code registers: `Rest\DeclaredRevisionsController`, `Rest\RevisableTypes`) and admin rows. A
type's row: `slug`, `name` (the plural label), `singular`, `rest_base`,
`hierarchical`, `has_archive` (true, or the archive's path), `rewrite` (a
slug, or `{slug, with_front}`, or false), `supports` (default title, editor,
author, thumbnail, excerpt, custom-fields, comments, revisions),
`taxonomies`, `description`, `icon`. A taxonomy's: `slug`, `name`,
`singular`, `types`, `rest_base`, `hierarchical`, `rewrite`. Without a
rewrite, pretty permalinks are `/{slug}/{post_name}/`. A change to the
declared set drops the stored rewrite rules (option `rewrite_rules`) so the
next request makes them again; option `minn_declared_types` holds the set's
hash. The declared-types suite compares the rules, REST answers and pages
(an entry, the archive, a term page, a missing entry) with a WordPress
plugin registering the same; `tests/unit/declared-types.php` pins the
row-to-arguments mapping. Preflight GREENS extra post types a manifest
declares.

Not yet seams (next ports decide their shape): cron
jobs, CLI verbs, file assets by URL (a plugin folder is web-served, so an extension
can link its own files), Minn Admin surfaces.

## Porting a WordPress plugin

The mapping from the plugin's idioms to the seams is the porting guide, applied
once per plugin, by hand or by an agent, never at runtime:

| WordPress idiom | Seam |
|---|---|
| `render_block` filters, `render_callback` | `gateBlocks`, `filterBlocks`, the block renderer registry |
| `add_shortcode` | `shortcode` |
| `the_content` filters | `filterContent` |
| `wp_head`, `wp_footer`, `wp_enqueue_*` | `head`, `footer` |
| `body_class` | `bodyClass` |
| `is_user_logged_in`, `current_user_can`, roles | `Seams::reader` |
| `get_option`, post meta, `WP_Query` | `Seams::site`, `Minn\Content\*` |
| `$_SERVER['HTTP_USER_AGENT']` and friends | `Seams::request` |

An extension written from the plugin's observed output can be MIT; one written
from the plugin's source is a derivative of that plugin and is GPL. Both are
allowed; the manifest's `license` says which. The engine never depends on either.

## The ports so far (all MIT, from observed output; `extensions/` in the engine repo)

| Extension | Replaces | What it re-provides |
|---|---|---|
| `minn-block-visibility` | block-visibility | the `blockVisibility` rules (below) |
| `minn-simple-custom-css` | simple-custom-css | `<style id="sccss">` from the `sccss_settings` option |
| `minn-ga-google-analytics` | ga-google-analytics | the gtag snippet from `gap_options`, head or footer, anonymize flag |
| `minn-wp-retina-2x` | wp-retina-2x | a `name@2x.ext` file beside a full-size image joins the srcset at twice the width |
| `minn-gallery-custom-links` | gallery-custom-links | anchors from `_gallery_link_*` attachment meta; the plugin's whole-document pass (attribute names lower-cased once something was linked; the count comment before `</body>`) |
| `minn-jetpack-slideshow` | jetpack (covers: the slideshow block only; blocks: `jetpack/slideshow`) | the block's images without the lazy `auto` hint, the block's stylesheet, Swiper, and view script from the plugin's own folder, plus the extension's own `assets/wp-globals.js` (the `wp.domReady`, `wp.i18n`, `wp.escapeHtml` browser globals the view script expects the page to provide; the reference ships them from its script library) |
| `minn-mosne-dark-palette` | mosne-dark-palette (blocks: `mosne/dark-palette`) | the `mosne/dark-palette` navigation item from its attributes (labels, default mode, `enableAuto`, classes), its styles from the plugin's build folder, the generated palette rule (`html[data-theme="dark"]` re-points every `--wp--preset--color--{slug}` to a `--mosne-dark-palette-{slug}` variable from the block's `darkColorsPalette`; the scheme flips when `themeOption` is dark), and the head script that sets `data-theme` before paint; the toggle itself runs on the extension's own `assets/view.js` (the plugin's view script is a module on the block interactivity runtime, which the engine does not provide) |
| `minn-ml-slider`, `minn-modula` | ml-slider, modula (covers: body class only) | the body class each added; shortcodes and gallery blocks still AMBER in preflight |
| `minn-autodescription` | autodescription (covers: titles, description, robots, canonical, Open Graph, Twitter, the schema.org graph; not its sitemap or query alterations) | the title (`{page} | {site}`, `{site} | {tagline}` at home), the meta block per page kind, and the ld+json graph with breadcrumbs, from the plugin's settings option and `_genesis_*` meta. Search descriptions are 160 characters, social ones 300, both cut on a word with an ellipsis unless a sentence ended; the og:image is the featured image with its size and alt, else the first content image, else the site icon |

**Retired on the dogfood site (2026-08-29).** Every plugin above loads as code
through the runtime (Track E), so dogfood no longer carries the port
symlinks: its plugins folder holds the 41 WordPress plugins its reference lists,
`minn_active_extensions` is empty, and `wp/v2/plugins` matches the reference
entry for entry. The child theme's Open Graph block, once the site extension
`dogfoodchild-head` (kept under the site's `retired/`), prints from the theme's
own `functions.php`, which the runtime now loads. The ports stay in the engine
repo's `extensions/` for the dev site's extension suite and as porting examples.

## Scripts a port cannot borrow

A plugin's front-end script often assumes the reference's own script
library is on the page: the `wp.*` browser globals, or `@wordpress/*`
modules resolved through an import map. The engine carries none of that,
so a port either provides the small globals itself (the slideshow) or
replaces the script with its own (the dark palette). The markup stays
identical either way; the dogfood suite compares bodies with scripts
removed, so a missing runtime shows up only in the browser, as a console
error and a block that never initialises.

## Head parity

`tests/dogfood.test.php` now also compares the head: the title, every meta tag,
the links that name the page (canonical, alternates, icons, the REST discovery
link), and the ld+json graph (one generated author id masked), sorted, with
stylesheets, scripts, and the reference's discovery and emoji plumbing left out.
The engine itself now prints the reference's own head links: the site and comments
feeds, an archive's own feed (a search's `…/feed/rss2/`), a single's comments feed
when it has comments, `rel="https://api.w.org/"`, the JSON alternate for the queried
object, and the site icon set. The `title` seam lets an extension replace the
document title. All twelve dogfood pages match on head and body.

With these, every one of the twelve dogfood pages on dogfood matches the
reference with no normalisation, head and body. Still unported on that site: plugins with nothing on the front end (duplicate-page, filebird,
enable-media-replace, stream, white-label-cms, login-logo, foogallery, coblocks,
gutenslider, carousel-block, carousel-slider, smart-slider-3, nextgen-gallery: none
appear in the site's content).

A navigation child block that renders its own `<li>` (the dark-palette item) is
not wrapped again; the reference does the same.

## First port: block visibility

`extensions/minn-block-visibility` (MIT, from observed output) honours the
`blockVisibility` attribute: `hideBlock`; `browserDevice` rule sets where `mobile`
covers phones and tablets and `other` is everything else, with `hideOnRuleSets`
deciding the sense; `screenSize` as the three hide classes plus the breakpoint
stylesheet in the head; `userRole` (`logged-in`, `logged-out`, `user-role`);
`dateTime` schedules. On dogfood the pages that carry device rules now render
the same group the reference renders for every user agent tried, and the hidden
groups are gone (`tests/dogfood.test.php` covers `/corporate/` and
`/residential/11-fifth-3/`).

Also learned from those pages: under a category-first permalink structure an
unmatched bare path is a category query on the reference, so no 404 guess runs
(`/11-fifth-3/` is 404, not a redirect to the child page); other structures guess.
