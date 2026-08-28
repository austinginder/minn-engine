# Block rendering

How `post_content` block markup becomes `content.rendered` (and, from the
front end, the page). Captured from a battery of one published post per
block family in the shared database: `contracts/fixtures/blocks/{family}.html`
(the stored markup), `{family}.rendered.html` (the reference rendering),
`manifest.json` (post and attachment ids). Rebuild with
`php tests/tools/blocks-battery.php`; suite `tests/blocks.test.php` diffs the
engine against the pinned rendering and the live reference. The suites'
cleanups read the manifest and leave the battery alone.

Code: `src/Minn/Blocks/` (`Parser`, `Block`, `Renderer`, `Layout`, `ImageTags`,
`RenderState`, `Dynamic/*`). `Minn\Content\Blocks::render()` is the front door.

## What the oracle taught

**Parsing and passthrough.** Delimiters disappear; every byte of each block's
own HTML stays, including the newlines around delimiters, so blocks separated
by a blank line in storage render three blank lines apart. Freeform HTML
between blocks passes through untouched. `<!--more-->` stays in the output.

**Render-time additions, by block.**

- `core/paragraph`: `wp-block-paragraph` appended to the END of the class list.
- Layout containers get `is-layout-{type}` and `wp-block-{block}-is-layout-{type}`
  appended: group (flow by default, `has-global-padding is-layout-constrained` for
  constrained, `is-nowrap` / `is-vertical` / `is-content-justification-{x}` for
  flex), columns (flex), column, quote, details (flow), buttons (flex, never a
  container class), gallery (`wp-block-gallery-{n}` then flex), cover (its
  INNER container gets `has-global-padding is-layout-constrained
  wp-block-cover-is-layout-constrained`), latest-posts (flow).
- A `wp-container-core-{block}-is-layout-{suffix}` class appears when the layout
  has rules of its own (nowrap, justification, vertical, grid column width, a
  block gap; columns always). The suffix is deterministic per stored layout
  (identical layouts share it; a key-order change alters it) but its derivation
  is not observable from the outside. **The engine emits its own deterministic
  suffix** (`md5` of the layout and gap); every suite neutralises the suffix
  before comparing (`minn_test_neutralise`). Recorded divergence.
- Images carrying `wp-image-{id}` get `loading="lazy" decoding="async" width
  height` prepended (gallery images also `data-id`), and `srcset` plus
  `sizes="auto, (max-width: {w}px) 100vw, {w}px"` appended, closing with ` />`.
  The srcset lists the displayed size first, then the same-ratio sizes in stored
  order (thumbnail's crop ratio excludes it), then the original. Covers and
  media-text images get the same treatment.
- **One counter per request** numbers galleries (`wp-block-gallery-{n}`), style
  variations (`is-style-wide is-style-wide--{n}`), and search inputs
  (`wp-block-search__input-{n}`), in render order, continuing across the posts
  of a list response. `RenderState` holds it.
- Which `is-style-*` classes get a numbered companion depends on the active
  theme's registered block styles. The engine mirrors the reference's theme
  (separator `wide`, button `outline`) until it reads theme data (milestone 20).
- Texturize runs over the whole result last; `pre`/`code` stay verbatim.

**Dynamic blocks** (server-rendered, captured on this database): `latest-posts`
(title link, optional `<time>` with ISO offset and `date_format`), `categories`
(non-empty, by name, tab-indented `cat-item cat-item-{id}` rows), `archives`
(months with published posts, single-quoted hrefs, `F Y` labels), `search` (the
form, ids from the shared counter), `tag-cloud` (8pt to 22pt by count, every tag
8pt when counts tie, `tag-link-position-{n}`), `latest-comments` (gravatar
sha256 at 48/96, author link when a URL exists, twenty-word excerpt with
`&hellip;`).

**Generated excerpts** (`Minn\Content\Excerpt`, re-derived from a probe post):
only paragraph, heading, list, quote, pullquote, verse, preformatted, table,
group, columns, column, media-text, html, more, and freeform contribute;
containers pass their allowed children through; everything else vanishes with
its content (code, details, buttons, image, gallery, cover, dynamic blocks, and
`list-item`, so a modern nested list adds nothing while a classic `<li>` list
does). The text stops at the first `<!--more-->`. Block-level tags read as a
space, inline tags and `<br>` as nothing. 55 words, ` [&hellip;]`, `<p>` wrap,
texturize.

## Known gaps

- The container suffix value (above).
- Blocks not in the battery render as stored markup with no additions:
  audio, video, file, embeds (oEmbed needs a network fetch), social links,
  navigation, the post/site template blocks, footnotes, calendar, RSS.
- Style-variation numbering is a fixed registry, not theme data.
- Front-end rendering (milestone 20) also needs the container stylesheets these
  classes point at and the loading-attribute rules for above-the-fold images.
