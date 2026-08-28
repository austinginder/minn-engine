# Minn Engine

Indistinguishable at the seams. Radically simpler inside.

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's operational contracts (the `wp_*` database schema, the file layout, the `wp/v2` REST surface, the WP-CLI ops verbs) so precisely that hosting infrastructure cannot tell the difference. It pairs with [Minn Admin](https://github.com/austinginder/minn-admin) as its native interface, and it is built agent-first from day one.

It is not a fork. No WordPress code appears in this repository, which is what makes the MIT license possible. See `docs/vision.md` for the full thesis, including the two-tier compatibility model and the license analysis, and `CLAUDE.md` for the ground rules every contribution follows.

## Status

Pre-alpha, vision stage. Milestone 0 is complete: the engine boots from an unmodified `wp-config.php` and serves a page from a database WordPress created, with no WordPress code in the process.

## License

MIT. WordPress is a trademark of the WordPress Foundation; Minn Engine is an independent project, not affiliated with or endorsed by the WordPress Foundation or Automattic.
