# Changelog

## **v0.2.0** - Unreleased

### Added

* **Extensions answer pages and REST routes of their own.** An extension (a folder with a `minn.json`) hands `Seams::routes` and `Seams::rest` controllers marked with `#[Route]`. Its pages are asked after every engine route and before the themed front, and its REST routes under `/wp-json/` and `?rest_route=` go through the same sign-in checks as Minn's own and appear in the REST index. Namespaces under `wp`, `wp-*`, `oembed` and `minn-admin` are refused, so an extension cannot take over one of Minn's. `Seams::user()` is the signed-in user, and `Seams::frontBar` puts the Minn bar on an extension's own page for a signed-in editor.

* **Extensions bring their own blocks, tables, content types and theme.** `Seams::block` registers a block from its `block.json` with a render, so the front draws it and Minn Admin edits it with forms (`Seams::editorStyles` styles its previews). A manifest's `tables` are created under the site's prefix and never dropped or altered. Its `types` and `taxonomies` get archives, addresses and REST routes as a plugin's would, with rewrite rules equal to WordPress's. A theme with a `minn.json` is an extension while it is the active theme, with no plugin to switch on.

* **Extensions can run a sign-in of their own.** `Seams::accounts()` signs people up, in and out, resets passwords on the extension's own page, and changes a password or email, all with the sign-in page's checks, throttle, sessions and cookies. The emails can be in the extension's own words, HTML and from its own sender, and plugins can still filter them. A sign-up can skip the note to the site admin, or hold the account until its email is proven.

* **`MINN_WP_API` keeps plugins off public pages, or off entirely.** `define('MINN_WP_API', 'auto')` in `wp-config.php` loads plugins everywhere but public pages, and `'off'` never loads them. Unset, a site boots as before (`'on'`). Under `auto` and `off`, a page an extension answers runs without the WordPress layer at all: on a site of built pages, 20 ms a page became 3 to 5 ms with every page byte for byte the same. Minn Admin's System view shows the mode.

* **Revisions and autosaves for every post type** a plugin or extension registers, at `wp/v2/<type>/<id>/revisions` and `/autosaves`, as WordPress registers them. Minn Admin's editor read 404 for both on a custom type.

* **A query loop with enhanced pagination moves between pages without reloading**, as WordPress's does, with its router, prefetch and loading bar.

* **The font library's Google Fonts collection** comes through the update service, cleaned as WordPress cleans it, and the pattern directory answers.

* **Minn Admin follows the site's default colour scheme** for anyone who never picked their own, and the Minn bar on the front does too.

* **`minn login` prints a one-time sign-in link without WP-CLI.** `php minn/bin/minn login <webroot> [<user>]` signs in the user named, or the first administrator, through Minn's own sign-in page, and works whatever `MINN_WP_API` is set to. `--to=<path>` lands somewhere other than the admin, and `--format=json` adds the user, their roles and when the link expires. Cove uses it for `cove login`.

### Changed

* **Minn reports WordPress 7.1.4**, the security release of November 11, so a host that compares versions with wordpress.org does not offer to update the site. Its fixes are carried where they apply to Minn (see Security); the rest touch screens Minn does not have (the Customizer, multisite, the erasure screen).

* **A theme's files are read once a request.** Its style partials, `theme.json` and pattern headers were read again by each part that asked; under `open_basedir`, a page went from 209% to 175% of WordPress's time.

### Fixed

* **Sites on nginx reach every WordPress entry point.** Hosts such as Kinsta run a request for a `.php` file only when the file exists, so after an install `wp-cron.php` (the host's own cron), `wp-comments-post.php`, `wp-admin/admin-ajax.php`, `wp-admin/admin-post.php` and the rest answered 404 before Minn saw them. The install now lays a small file under each of those names that hands the request to Minn, an update lays any that are missing, and eject removes them before WordPress's own come back.

* **Hosts that disable `getmyuid()` no longer take the site down.** Kinsta turns that PHP function off; Minn's check of how the site writes its own files called it, so a plugin that asks (Code Snippets) made every request fail. Minn now reads the owner of its own files instead, and answers `direct` there as WordPress does.

* **Signed-in visitors reach sites with a page cache.** On a site whose page cache installs `advanced-cache.php` (WP Rocket and others), every request from someone signed in answered with an error page in 0.1.0: the sign-in check took the early files Minn loads for that drop-in for the whole WordPress layer. It now asks WordPress's session manager only once that layer has loaded.

* **Minn Admin updates itself on hosts with a prepend loader.** Where `getmyuid()` was off, Minn took the owner from the first script PHP ran, which on Kinsta is a loader owned by root, so it decided it could not write the site's files and every plugin, theme and Minn Admin update failed without a reason. It reads its own files' owner, and an update that cannot write now says which file method it would need.

* **Minn Admin's newer screens have what they ask for.** Items say whether you may delete them (`minn_can_delete`), so Move to trash and Duplicate show only where they work; the Overview counts and lists custom content types, shows an Editor everyone's recent work, and takes a plugin's own stat cards (`minn_admin_overview_stats`); and the app is told which types you cannot add to, which a plugin puts first in the New menu, and what plugin blocks are called.

* **A mail plugin that replaces `wp_mail` takes over mail.** Minn declares WordPress's replaceable functions from `wp-includes/pluggable.php` in the webroot, where WordPress declares them, so a plugin that checks where `wp_mail` lives (FluentSMTP, for one) no longer stands aside and leaves the site sending no mail.

* **A page's template is read and written through `wp/v2`**, where it always read empty; a write takes only a template the type may use, refused as WordPress refuses it.

* **Themes that do not turn block gaps on get no gap rules**, as on WordPress, so a theme written for its own stylesheet keeps its margins.

* **Classic themes without a header, sidebar or footer file** get WordPress's stand-in ones, `?paged=2` moves to the pretty `/page/2/` address, and an empty later feed page is a 404.

* **With a persistent object cache, transients live in it** as WordPress keeps them, not in the options table, and the site language is read through its filters.

* **Script modules are fetched at the priority WordPress gives them** and print after the modules they import.

* **A menu item with no title** (its page deleted, or a custom link) reads as WordPress names it, and the plugin list is kept for the request as WordPress keeps it.

* **A plugin whose hook fails inside Minn's code is paused**, where the failure was blamed on Minn and every request answered with an error page (found on a Kinsta site).

* **`minn install --help` prints help** where it installed.

* **The post's author may edit an ordinary comment on their own post over REST**, as WordPress lets them.

* **The Post Navigation Link block's chevrons are » and «**, its label wraps the title as WordPress's does, and it renders on a single post reached any way.

* **A `$1` or `\1` in a block's class or an image's `srcset` stays text**, where it was read as a reference into the markup.

* **The language list offers Balochi**, which wordpress.org added.

* **The curl installer purges page caches.** It waits until the site answers as Minn (an opcode cache can keep running the old `index.php` for half a minute), then runs `wp cache flush`, which runs the host's purge hooks, so visitors stop getting WordPress's cached pages. `--no-cache-flush` skips it.

* **Eject says so when it cannot remove an empty park** instead of printing a PHP warning.

* **WordPress's other stand-alone pages answer as WordPress's do.** `wp-links-opml.php` lists the site's links as OPML, `wp-mail.php` refuses with WordPress's page, `wp-signup.php` and `wp-activate.php` send visitors to registration, and `wp-trackback.php` asked directly answers its error, where each was a 404 page.

* **A theme's own PHP templates render.** A child of a block theme with its own `page.php`, or a page given a PHP template of the theme's (a folder down included), showed the parent's block template; it shows the PHP template, as WordPress does.

* **A theme's stylesheet comes after the site's global styles**, so a rule in the theme's `style.css` wins over a global style of the same weight, as on WordPress.

* **Plugins that read the request's server variables under WP-CLI no longer warn.** Minn makes them whole when it starts, as WordPress does, on the web and the command line.

* **Mail addresses with accented or other non-ASCII letters** are accepted by PHPMailer once one has been added, as WordPress accepts them.

### Security

* **Interactivity bindings cannot add event handlers or change what an object loads** (WordPress 7.1.4). A `data-wp-bind--onclick` printed a handler from state, and a binding on an `<object>`'s `data` or `type` changed the file it loads; both are now ignored and reported as WordPress reports them, and an address that is not safe for a link leaves the link's own address in place.

* **Minn's interactivity script ignores `__proto__` in state and context**, reads server state only from its own script element, and never sets an `on…` attribute in the browser (WordPress 7.1.4).

* **Content filtering drops a binding to an attribute that only takes certain values** (WordPress 7.1.4). Where kses allows an attribute only with listed values, a `data-wp-bind--` directive for it could set any value in the browser, so it is removed.

* **The Categories and Comments Pagination blocks escape their labels** (WordPress 7.1.4), which an author without unfiltered HTML could fill with markup.

* **Editorial notes keep to their post's editors over REST** (WordPress 7.1.4). A comment moderator who cannot edit the post can no longer change or delete a note on it, and listing notes needs edit access to each post asked for. Its post's editors can now read a note by its id, which answered 404 for everyone.

* **The block parser accepts only the whitespace WordPress does inside a block's delimiter** (WordPress 7.1.4): a vertical tab no longer starts or ends one, so content cannot read as a block to Minn and as text to an editor.

* **Upgrades unpack into a folder with a random name** and keep `wp-content/upgrade/index.php` (WordPress 7.1.4), so a package's files cannot be reached at an address someone could guess while it installs.

* **A visitor can no longer switch a plugin off.** Minn pauses a plugin that fails while the site starts, so one bad plugin cannot take the site down. A request built to make a plugin fail (an odd path, header or value) could pause it, a security plugin included. Such a request now only notes the failure, and Minn pauses the plugin when its own request to the site fails the same way.

* **Five wrong guesses burn a one-time sign-in link.** A link minted by the CaptainCore helper carries only seven characters, and Minn accepts those links too. Minn now forgets a user's link after five wrong tries, from any mix of addresses, as the helper does on WordPress.

* **Database quoting follows the connection's character set.** On a site whose `DB_CHARSET` is a multibyte one (gbk, big5, sjis), a crafted search could close a quoted value; Minn now quotes on the connection the query runs on, a database drop-in's own included.

* **REST requests are answered only where WordPress answers them.** `/wp-cron.php?rest_route=...` and `/wp-admin/admin-ajax.php?rest_route=...` ran a REST request where plugins assume cron or Ajax.

* **The links OPML escapes link addresses as addresses**, so a `javascript:` link a plugin stored never reaches a feed reader.

* **One page can no longer make the engine render without end** through patterns that include each other many times over; a request has a budget of sources it opens.

* **The installer reads the site's address without loading its plugins** before it flushes the caches.

## **v0.1.0** - 2026-10-22

The first release of Minn, a from-scratch, MIT-licensed engine for an existing WordPress site. One command swaps it into the webroot beside the site's untouched `wp-config.php`, `wp-content/` and database, and one command swaps WordPress back. Visitors read the site through its theme, Minn Admin runs on top as the whole admin, plugins written for WordPress load through Minn's own implementation of the functions and hooks they call, and backup, migration and WP-CLI tooling keep working because the files, tables, cookies and REST answers are the shapes they expect. Every surface is checked against a real WordPress on the same database, request by request.

A pre-alpha: not ready for production sites yet. What 1.0 waits on, and where each measure stands, is listed at minn.run.

### Added

* **Install and eject in one command each.** From the folder that holds `wp-config.php`, `bash <(curl -sL https://minn.run/install)` checks the download's signature against Minn's release keys, runs a preflight (the theme, plugins, shortcodes, blocks and extra tables Minn will and will not serve, each GREEN, AMBER or RED), parks WordPress's own files beside the webroot, and puts Minn in their place. `php minn/bin/minn eject .` puts WordPress back. Nothing in `wp-config.php`, `wp-content/` or the database changes either way.

* **Visitors read the site.** Pretty permalinks, page hierarchies, category, tag, author and date archives, search, pagination, feeds, sitemaps, embeds, comments and the redirects WordPress makes all resolve as WordPress resolves them. Block themes render through their templates, parts, patterns and theme.json styles, and classic themes run their PHP templates.

* **Minn Admin is the admin.** Signing in at `/wp-login.php` opens Minn Admin, which runs on the engine unchanged: content, the editor with autosaves, revisions and locks, media, comments, users, settings, plugins, themes and updates. `/wp-admin/` sends people there. Minn Admin lives where WordPress keeps it, as the plugin in `wp-content/plugins/minn-admin`: Minn puts the copy it ships there on the first request after an install or update (never over a newer one), Minn Admin's own updates keep it current, and it stays after an eject, so WordPress keeps the same admin.

* **WordPress plugins load.** Plugins and mu-plugins run against Minn's own implementation of the functions, classes and hooks they call (options, posts, users, terms, queries, shortcodes, scripts and styles, REST routes, cron, mail, HTTP, translations), written from captured behaviour and not from WordPress's code. A plugin that calls something Minn does not provide is held back with the reason instead of taking the site down; AI provider plugins (Anthropic, Google, OpenAI) are held back for now, as Minn carries WordPress's AI client but not yet the classes a provider is built on. Activating one checks its Requires PHP, Requires at least and Requires Plugins headers and runs its activation hook, as WordPress does.

* **The `wp/v2` REST API answers as WordPress does.** Posts, pages, media, comments, terms, users, settings, revisions, block patterns and global styles, read and write, with the same capabilities, error codes, headers and `_fields` filtering. Cookie and nonce sign-ins are interchangeable with WordPress in both directions, and application passwords work for scripts and agents.

* **Hosting tooling cannot tell.** `wp-config.php` keeps its stock shape, `wp-includes/version.php` reports a WordPress version, and the WP-CLI verbs fleet tooling runs (`option`, `user`, `plugin`, `theme`, `cron`, `search-replace`, `cache flush` and more) run through `wp-cli.yml` against the engine, so backups, migrations and monitors keep working.

* **Minn updates itself, signed.** Once a day Minn asks updates.minn.run, the Minn update service, whether a newer release is out and offers it in Minn Admin as Update Minn. Releases are published on GitHub and served through the service; the update checks the download against its sha256 and against an Ed25519 signature made with Minn's release key, then swaps the engine folder in two renames, putting the old one back if anything fails. A second, recovery key kept offline is trusted too, so a lost or exposed release key can be replaced by a signed release rather than a reinstall on every site. From the command line, `php minn/bin/minn update` does the same and `--check` only asks.

* **One address for the outside world.** Plugin and theme update checks, directory search and details, translations and package downloads go to updates.minn.run, which answers in wordpress.org's own shapes. A Minn site never contacts wordpress.org itself, and the service passes nothing about the site on.
