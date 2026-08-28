# Install and eject

The engine goes into an existing WordPress webroot in one command and comes
back out in one command. Nothing it does touches `wp-config.php`, `wp-content/`,
or the database's content.

```
php minn/bin/minn preflight /path/to/public
php minn/bin/minn install   /path/to/public [--park=/path/to/wp-parked] [--force]
php minn/bin/minn status    /path/to/public
php minn/bin/minn eject     /path/to/public
```

The same four verbs run through WP-CLI from any directory once `minn/cli.php` is
loaded: on a WordPress site `wp --require=/path/to/minn/cli.php minn preflight`; on
an engine site simply `wp minn status`.

## Preflight

Reads `wp-config.php` as text (nothing in it runs), connects to the database, and
reports, each line GREEN, AMBER, or RED:

- the webroot's state (WordPress, engine, or neither) and the database it names;
- the active theme, and its parent: a block theme (`theme.json` plus `templates/`)
  is GREEN, a classic theme is RED (the engine renders block themes only), PHP
  templates inside a block theme are AMBER;
- active plugins and mu-plugins: AMBER, each named, because none of them will run;

RED stops `install` unless `--force` is passed.

## Install

1. Moves WordPress's own files (`wp-admin/`, `wp-includes/`, `index.php`, every
   `wp-*.php` except `wp-config.php`, `xmlrpc.php`, `license.txt`, `readme.html`) to
   the park directory, `wp-parked/` beside the webroot by default. They are moved,
   not copied, so nothing at the webroot can run WordPress.
2. Copies the engine's `minn/` folder in (or leaves it when the webroot's `minn` is
   already the engine, as on a development site with a symlink).
3. Writes the four shape files from `minn/layout/`: `index.php`, `wp-settings.php`,
   `wp-cli.yml`, `wp-includes/version.php`.
4. Records what it did in `minn/.install.json`: the park, the entries moved, the
   engine version.

`.htaccess`, `wp-content/`, and `wp-config.php` stay where they are. The copy skips
`.git`, `node_modules`, `tests`, and `docs` inside the engine and the admin bundle.

**Opcode cache.** `index.php` and `wp-settings.php` keep their paths but change
contents, so a PHP opcode cache can serve the previous version until it revalidates
(`opcache.revalidate_freq`, two seconds by default): the first request after a swap
in either direction can be a 500 on such a server. Clear the cache where the host
offers it (Kinsta's cache tools, `php -r 'opcache_reset();'` through the web SAPI, a
PHP-FPM reload); with the default settings it heals itself within seconds. Seen and
reproduced on FrankenPHP during the round trip below.

## Eject

Removes the shape files, the engine's `wp-includes/`, and `minn/` (a symlink is
unlinked, a copy deleted), moves every parked entry back, and removes the park when
it is empty. The tree is the one install found, byte for byte
(`tests/install.test.php` proves it on a scratch webroot).

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
