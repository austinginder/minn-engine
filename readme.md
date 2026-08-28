# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting infrastructure cannot tell the difference. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it is built agent-first from day one.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

Pre-alpha. The engine boots from an unmodified `wp-config.php`, serves a database WordPress created, and runs no WordPress code in the process.

Working today:

- `wp/v2` posts, pages, categories, tags, types, users, and `users/me` (read, view and edit context)
- Creating, updating, and deleting posts and pages, gated by a full capability engine (roles, `map_meta_cap`, cap-gated response links), with the round-trip proven: a write issued to the engine is read back through WordPress
- The `_fields` response filter, including the quirk where filtering the associative types payload yields `[]`
- Block rendering, texturize, and generated excerpts matching the reference byte for byte
- WordPress cookie and REST-nonce authentication, proven in both directions: a session minted by WordPress works on the engine, and a cookie minted by the engine is accepted by WordPress. The engine can also verify a password and create its own session that WordPress then accepts

Every surface is pinned by two suites: fixtures captured from a reference WordPress, and a live parity diff that treats a running WordPress on the same database as the oracle. Run them with `tests/run-all.sh`. Contract notes and known gaps live in `contracts/rest/`.

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
