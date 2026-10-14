# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting infrastructure cannot tell the difference. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it is built agent-first from day one.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

0.1.0, the first release. Minn swaps into an existing WordPress site's webroot in one command and back out in one, beside the site's untouched `wp-config.php` and database and its own `wp-content/`. Visitors read the site through its theme, [Minn Admin](https://github.com/austinginder/minn-admin) is the admin, plugins written for WordPress load against Minn's own implementation of the functions and hooks they call, and backup, migration and WP-CLI tooling keep working. What it does is listed in `changelog.md`.

Measured against WordPress 7.1.3 on the same database:

- **The whole PHP API is accounted for.** All 3,914 functions, 380 classes and 4,075 methods WordPress defines are either implemented and proven by a probe that compares Minn's answer with WordPress's, or a deliberate dead end that answers harmlessly. The dead ends are mostly the screens Minn replaces (wp-admin, the block and site editors, the Customizer) and XML-RPC.
- **Every hook WordPress fires is accounted for**, 2,543 of them.
- **No page runs more database queries than WordPress** on the five pages measured: the front page, a post, an archive, a search, and the REST posts list.
- **91 test suites, about 26,700 checks**, most of them comparing Minn's answer with a running WordPress on the same database. 87.6% of the engine's lines run under them.
- **A security sweep before the release**: 30 findings, each fixed and pinned by a test. Reporting a vulnerability: `security.md`.
- **Never contacts wordpress.org.** Update checks, directory search, translations and downloads go to updates.minn.run, Minn's update service.

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

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
