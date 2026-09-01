# Install and eject

The engine goes into an existing WordPress webroot in one command and comes
back out in one command. Nothing it does touches `wp-config.php`, `wp-content/`,
or the database's content.

On a host, from the directory that contains `wp-config.php`:

```
bash <(curl -sL https://minn.run/install)
```

That script downloads the latest `minn.zip` release (the `public/minn/` tree,
Minn Admin bundled at `minn/admin`), runs preflight, and hands off to
`php minn/bin/minn install`. Flags: `--force`, `--engine-url=URL`, `--park=DIR`.
The installer lives at the repo root as `install.sh` and is served from
https://minn.run/install.

The same swap, run by hand from a checkout:

```
php minn/bin/minn preflight /path/to/public
php minn/bin/minn install   /path/to/public [--park=/path/to/wp-parked] [--force]
php minn/bin/minn status    /path/to/public
php minn/bin/minn eject     /path/to/public
```

The four verbs also run through WP-CLI once `minn/cli.php` is loaded: on a
WordPress site `wp --require=/path/to/minn/cli.php minn preflight`; on an
engine site simply `wp minn status`.

## Preflight

Reads `wp-config.php` as text (nothing in it runs), connects to the database, and
reports, each line GREEN, AMBER, or RED:

- the webroot's state (WordPress, engine, or neither) and the database it names;
- the active theme, and its parent: a block theme (`templates/index.html` in
  the child or parent) is GREEN, a classic PHP theme (`index.php` in the parent)
  is GREEN, a theme with neither is RED. Leftover PHP templates on a block
  theme are AMBER (those files do not run);
- active plugins and mu-plugins: AMBER, each named, because none of them will run;
- shortcodes in `post`, `page`, `wp_block`, `wp_template`, `wp_template_part`,
  and `wp_navigation` content: GREEN when a `minn.json` lists the tag under
  `shortcodes`, AMBER otherwise;
- third-party block namespaces (`<!-- wp:vendor/name`): GREEN when a
  `minn.json` lists the name under `blocks`, AMBER otherwise. Core blocks
  (`<!-- wp:paragraph`, `<!-- wp:core/…`) are the engine's and stay quiet;
- menus: published `wp_navigation` posts are GREEN; classic `nav_menu`
  terms with items are GREEN (the engine reads them when a navigation block
  has no `wp_navigation` post);
- extra tables: AMBER, grouped by family, because they are plugin data the
  engine does not read;
- extra post types: GREEN when an active `minn.json` lists the slug under
  `types`, AMBER otherwise.

RED stops `install` unless `--force` is passed.

Preflight still reads `wp-config.php` as text (nothing in it runs). It accepts
string literals, `getenv('…')`, and the official image's
`getenv_docker('WORDPRESS_DB_NAME', 'fallback')` (env, then `{NAME}_FILE`, then
the fallback). The engine itself loads `wp-config.php` normally for CLI and web.

## Install

1. Moves WordPress's own files (`wp-admin/`, `wp-includes/`, `index.php`, every
   `wp-*.php` except `wp-config.php`, `xmlrpc.php`, `license.txt`, `readme.html`) to
   the park directory, `wp-parked/` beside the webroot by default. `rename()` first;
   if the park is on another filesystem (Docker volumes), copy then remove. They
   leave the webroot so nothing there can run WordPress.
2. Copies the engine's `minn/` folder in (or leaves it when the webroot's `minn` is
   already the engine, as on a development site with a symlink).
3. Writes the shape files from `minn/layout/`: `index.php`, `wp-login.php`,
   `wp-settings.php`, `wp-cli.yml`, `wp-includes/version.php`, `wp-admin/index.php`.
   `wp-admin/index.php` has to exist on disk: hosts such as Kinsta 403 a
   directory without an index, and `/wp-admin/` must keep 302ing to
   `/minn-admin/`.
4. Writes empty PHP placeholders for every `wp-includes/*.php` and
   `wp-admin/includes/*.php` the reference has (`data/reference-files.json`),
   so a plugin `require ABSPATH . 'wp-admin/includes/plugin.php'` resolves.
   The engine already provides those symbols; the files are the contract.
5. Records what it did in `minn/.install.json`: the park, the entries moved,
   the engine version.

Static CSS and JS are served from inside `minn/` (`minn/admin/assets/`,
`minn/assets/`). Hosts that 404 missing `.js`/`.css` find those files on
disk at `/minn/...`, so they do not need extra copies at the webroot.

`.htaccess`, `wp-content/`, and `wp-config.php` stay where they are. The copy skips
`.git`, `node_modules`, `tests`, and `docs` inside the engine and the admin bundle.
Minn Admin is served from `minn/admin` (the app's `minn-admin.php` plus `assets/`).
A development engine often has that path as a symlink; a deploy copies the
directory in. Without it, `/minn-admin/` answers 500 "The Minn Admin app is not
linked into this engine."

**Opcode cache.** `index.php` and `wp-settings.php` keep their paths but change
contents, so a PHP opcode cache can serve the previous version until it revalidates
(`opcache.revalidate_freq`, two seconds by default): the first request after a swap
in either direction can be a 500 on such a server. Clear the cache where the host
offers it (Kinsta's cache tools, `php -r 'opcache_reset();'` through the web SAPI, a
PHP-FPM reload); with the default settings it heals itself within seconds. Seen and
reproduced on FrankenPHP during the round trip below.

## Eject

Removes the shape files, any leftover copies from an earlier installer
(`minn-admin-asset/`, `minn-engine/`, `wp-includes/js/jquery/`), the engine's
`wp-includes/` and `wp-admin/` (shape files plus require placeholders), and
`minn/` (a symlink is unlinked, a copy deleted), moves every parked entry
back, and removes the park when it is empty. The tree is the one install
found, byte for byte (`tests/install.test.php` proves it on a scratch webroot).

The engine's only footprint in the database is its sign-in throttle rows
(`wp_options`, `minn_login_throttle_*`); everything else it writes is in the shapes
WordPress writes.

## Proven on a real site

Round trip on a fresh `cove clone` of dogfood (25 active plugins, a child block
theme, a static front page): preflight AMBER, install parked 18 entries, the front
page and `/about/` rendered through the engine, `wp user list` and `wp minn status`
answered, eject restored WordPress (200, its own body classes, `wp core version`
7.1), all in under a minute. The scratch-webroot suite covers the same steps on
every run.
