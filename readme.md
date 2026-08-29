# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting infrastructure cannot tell the difference. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it is built agent-first from day one.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

Pre-alpha. The engine boots from an unmodified `wp-config.php`, serves a database WordPress created, and runs no WordPress code in the process.

Working today:

- **A visitor can read the site.** Pretty permalinks (`/%postname%/`), page hierarchies, category, tag, author, and date archives, search, pagination, the query-string redirects (`?p=`, `?page_id=`, `?cat=`), the trailing-slash and "closest match" redirects, and the body-class tokens all resolve exactly as the reference does (93 URL shapes pinned, diffed live). See `contracts/front/permalinks.md`.
- **Minn Admin boots and runs on the engine.** A browser signs in through the engine's login, the admin SPA loads, and the Content view lists and filters every post and page (drafts included) read live through the engine's REST layer. This is the milestone the whole project points at: the admin interface running on the from-scratch engine instead of WordPress. See `contracts/minn-admin.md`.
- **The site renders through its block theme.** Templates, parts, patterns (their PHP text subset interpreted, never executed), site-editor overrides, ~35 template blocks, and a theme.json stylesheet generator: fourteen pages diff clean against the reference's body and the presets match byte for byte. See `contracts/front/theme.md`.
- **Block rendering at parity**: a real block parser and renderer (layout classes, image `srcset`, gallery and style-variation numbering, six server-rendered blocks, the reference's excerpt rules), pinned by a four-family battery diffed live against the reference. See `contracts/blocks.md`.
- `wp/v2` posts, pages, categories, tags, types, users, and `users/me` (read, view and edit context, with `status`/`author` list filtering)
- Creating, updating, and deleting posts and pages, gated by a full capability engine (roles, `map_meta_cap`, cap-gated response links), with the round-trip proven: a write issued to the engine is read back through WordPress
- The `_fields` response filter, including the quirk where filtering the associative types payload yields `[]`
- Block rendering, texturize, and generated excerpts matching the reference byte for byte
- WordPress cookie and REST-nonce authentication, proven in both directions: a session minted by WordPress works on the engine, and a cookie minted by the engine is accepted by WordPress
- A working `/wp-login.php`: a browser signs in against the engine, receives real WordPress auth cookies, and that session is accepted by both the engine and WordPress. Logout clears it

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
    admin/           the Minn Admin bundle
    cli.php          WP-CLI entry point
    layout/          templates of the four shape files above, for install
    bin/minn         preflight, install, eject, status (docs/install.md)
```

Everything else in this repository (`tests/`, `contracts/`, `docs/`, and `site/`, the block theme behind the project's own site) is development only and never ships. On a development site `public/minn` can be a symlink into this repository so several sites run the same code.

Every surface is pinned by two suites: fixtures captured from a reference WordPress, and a live parity diff that treats a running WordPress on the same database as the oracle. Run them with `tests/run-all.sh`. Contract notes and known gaps live in `contracts/`. The whole engine follows `docs/style.md` (modern namespaced PHP under `public/minn/src/Minn/`, enforced by `tests/style.test.php`).

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
