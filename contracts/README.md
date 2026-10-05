# Contracts

The compatibility contract is the product. Everything in this directory is a
machine-readable or fixture-backed specification of a WordPress operational
surface that Minn Engine commits to honoring, written from observed behavior
and never from WordPress source code (see the legal ground rules in CLAUDE.md).

`lexicon.md` is the policy layer on top of the inventory: which WordPress
families Minn Speaks, Hears, or Mutes. WordPress speaks to tooling, plugin
PHP, and humans in a browser. Minn answers the first two. `/wp-admin/` is
not a Minn surface. Agents read the lexicon before adding a runtime symbol.

Planned layout:

- `lexicon.md` — Speak / Hear / Mute: the WordPress families Minn implements,
  the ones it recognizes so plugins load, and the ones it will never host
  (no `/wp-admin/` UI; Minn Admin is the only admin). The Minn site theme
  renders a copy at `/lexicon/`; this file is the policy agents read.
- `schema/` — the `wp_*` table contract: tables, columns, semantics, and the
  known value quirks (serialized blobs, `''` versus `'closed'` option values,
  local versus GMT datetime columns).
- `rest/` — the `wp/v2` route contract: routes, params, response shapes,
  captured as fixtures from a live WordPress instance.
- `round-trip.md` — the second definition of done: a real site's day of
  work on WordPress and on Minn, compared row by row, then handed back to
  WordPress (`tests/round-trip.test.php`).
- `blocks.md` + `fixtures/blocks/` — block rendering: the per-family battery,
  render-time additions, dynamic blocks, generated excerpts.
- `front/` — the public-site contract: permalink structures, URL resolution
  and redirects, body-class tokens, later feeds and sitemaps.
- `cli/` — the WP-CLI ops verb contract: the verbs fleet tooling actually
  invokes, with expected output shapes.
- `boot/` — the file layout and `wp-config.php` contract: which files exist,
  which constants tooling reads, and how the boot chain hands off.
- `fixtures/` — captured input and output pairs from a reference WordPress
  site. Comparing behavior is the spec-capture method; fixtures are data, not
  code, and carry no license entanglement.

Nothing here is implemented until a contract file says exactly what "done"
means for it, and the definition of done is always the same: real tooling run
against the engine cannot tell it is not WordPress.
