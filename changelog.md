# Changelog

## **v0.1.0** - Unreleased

The first release of Minn, a from-scratch, MIT-licensed engine for an existing WordPress site. One command swaps it into the webroot beside the site's untouched `wp-config.php`, `wp-content/` and database, and one command swaps WordPress back. Visitors read the site through its theme, Minn Admin runs on top as the whole admin, plugins written for WordPress load through Minn's own implementation of the functions and hooks they call, and backup, migration and WP-CLI tooling keep working because the files, tables, cookies and REST answers are the shapes they expect. Every surface is checked against a real WordPress on the same database, request by request.

### Added

* **Install and eject in one command each.** From the folder that holds `wp-config.php`, `bash <(curl -sL https://minn.run/install)` runs a preflight (the theme, plugins, shortcodes, blocks and extra tables Minn will and will not serve, each GREEN, AMBER or RED), parks WordPress's own files beside the webroot, and puts Minn in their place. `php minn/bin/minn eject .` puts WordPress back. Nothing in `wp-config.php`, `wp-content/` or the database changes either way.

* **Visitors read the site.** Pretty permalinks, page hierarchies, category, tag, author and date archives, search, pagination, feeds, sitemaps, embeds, comments and the redirects WordPress makes all resolve as WordPress resolves them. Block themes render through their templates, parts, patterns and theme.json styles, and classic themes run their PHP templates.

* **Minn Admin is the admin.** Signing in at `/wp-login.php` opens Minn Admin, which runs on the engine unchanged: content, the editor with autosaves, revisions and locks, media, comments, users, settings, plugins, themes and updates. `/wp-admin/` sends people there.

* **WordPress plugins load.** Plugins and mu-plugins run against Minn's own implementation of the functions, classes and hooks they call (options, posts, users, terms, queries, shortcodes, scripts and styles, REST routes, cron, mail, HTTP, translations), written from captured behaviour and not from WordPress's code. A plugin that calls something Minn does not provide is held back with the reason instead of taking the site down. Activating one checks its Requires PHP, Requires at least and Requires Plugins headers and runs its activation hook, as WordPress does.

* **The `wp/v2` REST API answers as WordPress does.** Posts, pages, media, comments, terms, users, settings, revisions, block patterns and global styles, read and write, with the same capabilities, error codes, headers and `_fields` filtering. Cookie and nonce sign-ins are interchangeable with WordPress in both directions, and application passwords work for scripts and agents.

* **Hosting tooling cannot tell.** `wp-config.php` keeps its stock shape, `wp-includes/version.php` reports a WordPress version, and the WP-CLI verbs fleet tooling runs (`option`, `user`, `plugin`, `theme`, `cron`, `search-replace`, `cache flush` and more) run through `wp-cli.yml` against the engine, so backups, migrations and monitors keep working.

* **Minn updates itself, signed.** Once a day Minn asks updates.minn.run, the Minn update service, whether a newer release is out and offers it in Minn Admin as Update Minn. Releases are published on GitHub and served through the service; the update checks the download against its sha256 and against an Ed25519 signature made with Minn's release key, then swaps the engine folder in two renames, putting the old one back if anything fails. From the command line, `php minn/bin/minn update` does the same and `--check` only asks.

* **One address for the outside world.** Plugin and theme update checks, directory search and details, translations and package downloads go to updates.minn.run, which answers in wordpress.org's own shapes. A Minn site never contacts wordpress.org itself, and the service passes nothing about the site on.
