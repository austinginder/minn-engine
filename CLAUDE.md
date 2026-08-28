# Minn Engine — ground rules

Minn Engine is a from-scratch, MIT-licensed engine that reimplements WordPress's
operational contracts. These rules are load-bearing. Read `docs/vision.md` for
the full argument behind them.

## Legal ground rules (non-negotiable)

The MIT license survives only if the implementation stays clean:

1. **Never copy, port, paraphrase, or mechanically transform WordPress source
   code.** Not a function, not a regex, not a "just translate this file."
   WordPress code is GPL and any derivation of it is GPL. Do not open WordPress
   source files while implementing engine code.
2. **Write from behavioral specifications.** When a WordPress quirk must be
   understood, capture it as a behavioral note or a fixture (observed inputs and
   outputs from a running WordPress site) and implement from that note. Fixtures
   captured from live behavior are the spec; comparing outputs is always fine.
3. **No GPL assets ship in this repository.** No WordPress core JavaScript,
   block-library CSS, bundled themes, or Dashicons. Check the license of every
   dependency before adding it.
4. **Never execute GPL plugin or WordPress code in-process.** There is no
   hook-compatibility shim, by design (see Tier 2 below).
5. **Trademark:** "WordPress" appears only in truthful compatibility statements
   ("compatible with WordPress", "reads a WordPress database"). Never in a
   project name, domain, or anything implying endorsement.

Functional interface names (table names, column names, route paths, JSON keys,
CLI verbs, file names like `wp-config.php` and `wp-settings.php`) are the
contract surface and are fine to match exactly. Interfaces are not expression;
implementations are, and every implementation line here must be original.

## The two-tier compatibility line

**Tier 1 — the operational contract (sacred):** the `wp_*` database schema
(including tolerating serialized-PHP blobs in options and postmeta), the file
layout and `wp-config.php` shape, `wp/v2` REST core routes, WP-CLI ops verbs,
permalinks, feeds, sitemaps, and a block-rendering subset for `post_content`.
The definition of done for any Tier 1 surface: real hosting, backup, migration,
and fleet tooling runs against it and cannot tell it is not WordPress.

**Tier 2 — never:** hook-level PHP plugin compatibility, wp-admin, the PHP
template hierarchy, and legacy layers beyond read-tolerance. Extensions target
the engine's own declarative-manifest contract instead, and Minn Admin is the
admin interface.

## Engineering conventions

- Modern PHP, minimal dependencies, no build step.
- Prepared statements only. Never `unserialize()` untrusted data (read
  serialized blobs with tolerant parsers, never by executing them).
- Escaping happens at output boundaries; capability requirements are declared
  route metadata, not scattered imperative checks.
- Every behavioral unit ships with a test. The suites, not the prose, are
  ground truth.

## Prose style

In all user-facing prose (readme, docs, changelog, UI strings) do not use an
em dash inside a sentence. Rewrite with a period, colon, semicolon, comma, or
parentheses. The one sanctioned use is the list-item label separator
(`**Feature** — description`).

## Git

Emoji-Log commits (https://github.com/ahmadawais/Emoji-Log): `📦 NEW:`,
`👌 IMPROVE:`, `🐛 FIX:`, `📖 DOC:`, `🚀 RELEASE:`, `🤖 TEST:` in imperative
present tense. Commit after each verified unit of work. Never add
`Co-Authored-By` trailers.
