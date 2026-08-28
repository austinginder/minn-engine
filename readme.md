# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting infrastructure cannot tell the difference. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it is built agent-first from day one.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

Pre-alpha. The engine boots from an unmodified `wp-config.php`, serves a database WordPress created, and runs no WordPress code in the process.

Working today:

- **A visitor can read the site.** Pretty permalinks (`/%postname%/`), page hierarchies, category, tag, author, and date archives, search, pagination, the query-string redirects (`?p=`, `?page_id=`, `?cat=`), the trailing-slash and "closest match" redirects, and the body-class tokens all resolve exactly as the reference does (93 URL shapes pinned, diffed live). See `contracts/front/permalinks.md`.
- **Minn Admin boots and runs on the engine.** A browser signs in through the engine's login, the admin SPA loads, and the Content view lists and filters every post and page (drafts included) read live through the engine's REST layer. This is the milestone the whole project points at: the admin interface running on the from-scratch engine instead of WordPress. See `contracts/minn-admin.md`.
- `wp/v2` posts, pages, categories, tags, types, users, and `users/me` (read, view and edit context, with `status`/`author` list filtering)
- Creating, updating, and deleting posts and pages, gated by a full capability engine (roles, `map_meta_cap`, cap-gated response links), with the round-trip proven: a write issued to the engine is read back through WordPress
- The `_fields` response filter, including the quirk where filtering the associative types payload yields `[]`
- Block rendering, texturize, and generated excerpts matching the reference byte for byte
- WordPress cookie and REST-nonce authentication, proven in both directions: a session minted by WordPress works on the engine, and a cookie minted by the engine is accepted by WordPress
- A working `/wp-login.php`: a browser signs in against the engine, receives real WordPress auth cookies, and that session is accepted by both the engine and WordPress. Logout clears it

Every surface is pinned by two suites: fixtures captured from a reference WordPress, and a live parity diff that treats a running WordPress on the same database as the oracle. Run them with `tests/run-all.sh`. Contract notes and known gaps live in `contracts/`. New engine code follows `docs/style.md` (modern namespaced PHP, enforced by `tests/style.test.php`); the older procedural files migrate as they are touched.

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
