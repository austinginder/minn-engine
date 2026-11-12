# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting, backup and migration tools operate a Minn site the way they operate a WordPress site. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it was built with coding agents from the first commit, every change gated by suites that compare it with a running WordPress.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

0.2.0, a pre-alpha: not ready for production sites yet. Some of the measures 1.0 waits on are still open; [minn.run](https://minn.run/#where-minn-stands) lists them, and `docs/audit-2026-10-22.md` has the figures. Minn swaps into an existing WordPress site's webroot in one command and back out in one, beside the site's untouched `wp-config.php` and database and its own `wp-content/`. Visitors read the site through its theme, [Minn Admin](https://github.com/austinginder/minn-admin) is the admin, plugins written for WordPress load against Minn's own implementation of the functions and hooks they call, and backup, migration and WP-CLI tooling keep working. What it does is listed in `changelog.md`.

Measured against WordPress 7.1.3 on the same database:

- **The whole PHP API is accounted for.** All 3,914 functions, 380 classes and 4,075 methods WordPress defines are either implemented and proven by a probe that compares Minn's answer with WordPress's, or a deliberate dead end that answers harmlessly. The dead ends are mostly the screens Minn replaces (wp-admin, the block and site editors, the Customizer) and XML-RPC.
- **Every hook WordPress fires on a single site is accounted for**, 2,542 of them, 97% proven against WordPress weighted by plugin installs.
- **No page runs more database queries than WordPress** on the five pages measured: the front page, a post, an archive, a search, and the REST posts list.
- **96 test suites, about 30,800 checks**, most of them comparing Minn's answer with a running WordPress on the same database. 88.4% of the engine's lines run under them.
- **Security sweeps before the release**, the last on October 22: 91 findings across every review since August, each fixed and pinned by a test, and a few named and left open on purpose, listed in `docs/security-review-2026-10-22.md`. Reporting a vulnerability: `security.md`.
- **Never contacts wordpress.org.** Update checks, directory search, translations and downloads go to updates.minn.run, Minn's update service.
- **Signed updates.** The installer and the engine's own updater install only a release carrying an Ed25519 signature from a key built into the engine.
- **Reversible.** `minn eject` gives WordPress back its files byte for byte. A round-trip suite gives WordPress and Minn the same day of work on copies of real sites and compares every row each leaves behind (`contracts/round-trip.md`).
- **About a third of the size.** The engine (`src/Minn`, about 116,000 lines) and the WordPress-shaped facade plugins call (`wp-api`, about 87,000) come to about 203,000 lines of PHP, against about 618,000 in WordPress 7.1.3 with its admin and bundled libraries.

### Who it is for today

A content site (pages, posts, forms, a classic PHP theme or a block theme) whose operator wants an MIT engine, signed updates and no contact with wordpress.org, and who already runs [Minn Admin](https://github.com/austinginder/minn-admin) on WordPress. Preflight names what will and will not load before anything changes, and the swap is one command each way.

### Not yet

Said plainly, so nobody finds out the hard way:

- **No new sites without WordPress.** Minn runs an existing WordPress site: it needs the `wp-config.php` and the database WordPress created. There is no installer that creates the tables.
- **Not faster than WordPress.** Pages run fewer queries than WordPress's on the five measured, but every request loads the whole facade, so a request takes longer overall: about a quarter longer on the last local benchmark, most of it at boot.
- **No wp-admin, block editor or Customizer.** Minn Admin is the only admin. A plugin's own settings screen exists on Minn only when Minn Admin has an adapter for it.
- **Stores and page builders are limited.** A WooCommerce store's shop, cart and checkout run, but running the store needs Minn Admin adapters. Elementor, Brizy, Beaver Builder and Bricks open their editors with the separate Minn Legacy Pack; Divi 5, Oxygen 4 and Etch do not yet.
- **Native extensions are front-end only.** A `minn.json` extension can shape what a page renders (`contracts/extensions.md`). Routes, settings, cron and CLI verbs still mean writing a WordPress plugin.
- **The agent surfaces in `docs/agent-first.md` are a plan.** Route policies are machine-readable today (`contracts/api/routes.json`); scoped agent keys, a write journal, a catalogue export and a disposable clone are not built.

## Requirements

- PHP 8.3 or newer, with `mysqli`, `mbstring` and `sodium`.
- MySQL or MariaDB, the database WordPress already uses.
- Recommended, as for WordPress: `curl` (outgoing requests), `gd` or `imagick` (image sizes), `zip` (installing plugins and themes), `exif`, `fileinfo`, `intl`, and OPcache.
- A web server that blocks `/minn/` except `minn/assets/`, and `MINN_TRUSTED_PROXIES` behind a CDN or load balancer (`docs/hardening.md`).

## Install

On a host, from the WordPress webroot (the directory with `wp-config.php`):

```
bash <(curl -sL https://minn.run/install)
```

That downloads the latest engine zip, runs preflight, and swaps the webroot over.
`wp-config.php`, `wp-content/`, and the database stay where they are; the first
request puts Minn Admin in `wp-content/plugins/minn-admin`, where it stays after
an eject. Reverse it with `php minn/bin/minn eject .`. See `docs/install.md`.

## Layout

The engine is one folder, `public/minn/`, dropped into a WordPress webroot beside an untouched `wp-config.php`:

```
public/
  index.php          stock front controller
  wp-config.php      the file WordPress generated, never edited
  wp-settings.php    two lines: require minn/bootstrap.php
  wp-cli.yml         routes the WP-CLI verbs to the engine (contracts/cli.md)
  wp-includes/
    version.php      the version file tooling parses (contracts/layout.md)
  wp-content/        themes and uploads
  minn/
    bootstrap.php    version, autoloader, Minn\Engine::serve()
    src/Minn/        the engine (PSR-4)
    assets/  data/   the engine's own stylesheet and seeded registries
    admin/           the Minn Admin release it ships, laid into wp-content/plugins
    cli.php          WP-CLI entry point
    layout/          templates of the four shape files above, for install
    bin/minn         preflight, install, eject, status, update (docs/install.md)
```

Everything else in this repository (`tests/`, `contracts/`, `docs/`, `scripts/`, the changelog, and `site/`, the block theme behind the project's own site) is development only and never ships. `php scripts/build-release.php` builds the release archive: the engine as committed plus the latest Minn Admin release, whole, at `minn/admin`, signed with the release key. On a development site `public/minn` can be a symlink into this repository so several sites run the same code.

Every surface is pinned by two suites: fixtures captured from a reference WordPress, and a live parity diff that treats a running WordPress on the same database as the oracle. Run them with `tests/run-all.sh`. Contract notes and known gaps live in `contracts/`. The whole engine follows `docs/style.md` (modern namespaced PHP under `public/minn/src/Minn/`, enforced by `tests/style.test.php`).

Most suites need a reference WordPress serving the same database as the engine, and today that setup is the maintainer's local one. On a fresh clone, `php tests/unit.test.php` (pure classes, no database, under a second) and `php tests/style.test.php` (the code's shape and ratchets) run as they are. `CONTRIBUTING.md` says what the rest need.

## Stability

What a site, a plugin or an extension can rely on across 0.x releases:

- the WordPress contracts Minn speaks: the database, the file layout, `wp/v2`, WP-CLI, and the functions, classes and hooks plugins call (`wp-api/`);
- the `minn.json` manifest, `Minn\Extension\Extension` and `Minn\Extension\Seams`;
- `bin/minn` and the `wp minn` verbs.

Everything else under `src/Minn/` is the engine's own inside and can change in any release, including a minor one. `docs/api/` documents it so it can be read, not so it can be built on. A part that is promised later will say so in its docs.

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
