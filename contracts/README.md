# Contracts

The compatibility contract is the product. Everything in this directory is a
machine-readable or fixture-backed specification of a WordPress operational
surface that Minn Engine commits to honoring, written from observed behavior
and never from WordPress source code (see the legal ground rules in CLAUDE.md).

Planned layout:

- `schema/` — the `wp_*` table contract: tables, columns, semantics, and the
  known value quirks (serialized blobs, `''` versus `'closed'` option values,
  local versus GMT datetime columns).
- `rest/` — the `wp/v2` route contract: routes, params, response shapes,
  captured as fixtures from a live WordPress instance.
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
