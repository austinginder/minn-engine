# Front end: block themes

The engine renders the site's installed block theme, read as data: `theme.json`,
`templates/*.html`, `parts/*.html`, `patterns/*.php`, and the site editor's saved
`wp_template` / `wp_template_part` / `wp_navigation` posts. No theme PHP runs.
Code: `public/minn/src/Minn/Theme/` (`Theme`, `Templates`, `PatternText`, `PageRenderer`) and
the template blocks in `public/minn/src/Minn/Blocks/Dynamic/Theme/`.

Suite: `tests/theme.test.php` diffs the `<body>` of fourteen pages against the
reference (styles, scripts, and link tags stripped; hosts, container suffixes,
and per-request ids neutralised; blank lines dropped). Pinned copies in
`contracts/fixtures/theme/`; re-capture with `--capture`. On the dev site
`public/wp-content/themes` is a symlink into `wp-reference/wp-content/themes`
so both stacks read the same theme.

## What the oracle taught

**Resolution to template.** The hierarchy per resolution kind (`Templates::candidates`):
home → `front-page, home, index`; a post → `single-post-{slug}, single-post, single,
singular, index`; a page → its `_wp_page_template` meta, then `page-{slug}, page-{id},
page, singular, index`; category/tag/author → `{tax}-{slug}, {tax}-{id}, {tax}, archive,
index`; date → `date, archive, index`; search → `search, index`; 404 → `404, index`. A
published `wp_template` post with that slug and the theme's `wp_theme` term wins over the
file; parts resolve the same way through `wp_template_part`.

**Document shell.** `<body class="…">` carries the core tokens plus `wp-singular` and
`{post|page}-template-default` on singulars, then `wp-embed-responsive wp-theme-{slug}`.
The body opens with the skip link and `<div class="wp-site-blocks">`; the first `<main>`
gets `id="wp--skip-link--target"`. Template parts wrap in `<header|footer|div
class="wp-block-template-part">` by their theme.json area.

**Patterns.** Theme patterns are PHP files whose code is echo-and-escape calls around
literal strings. `PatternText` interprets exactly that grammar: string literals, `.`
concatenation, `__/_x` (verbatim), `esc_html*/esc_attr*` (special characters encoded
once, so `doesn't` becomes `doesn&#039;t`), `esc_url`, `wp_kses_post`,
`get_template_directory_uri()`, `printf`/`sprintf`, `/* */` comments; the header
docblock is dropped, every byte outside the PHP tags is kept, and, like PHP, the single
newline right after a closing tag is swallowed. Anything else renders as nothing.

**Template blocks** (markup captured from the reference):

- `post-title`: `<h{level} class="wp-block-post-title …">`, linked with
  `target="_self" >` (that space) when `isLink`. Text alignment classes lead the block
  class; font size and custom classes follow it (`has-text-align-right wp-block-post-date`,
  `wp-block-post-title has-x-large-font-size`). A custom class and its numbered style
  companion lead (`taxonomy-post_tag is-style-post-terms-1 is-style-post-terms-1--2
  wp-block-post-terms`).
- `post-content`: `<div class="entry-content [align] wp-block-post-content [font size]
  [layout classes]">`; nothing at all for empty content. In a loop the content stops at
  `<!--more-->` and gains `\n <a href="{link}#more-{id}" class="more-link"><span
  aria-label="Continue reading {title}">(more&hellip;)</span></a>`; on its own page the
  tag becomes `<span id="more-{id}"></span>`.
- `post-date`, `post-terms`, `post-featured-image`: the `style` attribute comes BEFORE
  `class`. Terms join with `<span class="wp-block-post-terms__separator">…</span>`;
  nothing when the post has none. No thumbnail, no figure.
- `post-navigation-link`: an empty `<div class="post-navigation-link-{previous|next}
  wp-block-post-navigation-link">` when there is no neighbour; neighbours are by
  `post_date`, ties broken by id.
- `query` + `post-template`: `inherit:true` reads the main query (the site's
  `posts_per_page`, sticky posts first on page one of the blog); `inherit:false` runs its
  own with `perPage` posts PLUS the sticky posts on top. `<ul class="[align]
  wp-block-post-template [layout]"><li class="wp-block-post post-{id} {post classes}">`;
  `sticky` appears in the post classes only on the blog's first page.
- `query-title`: `Category: <span>…</span>`, `Tag:`, `Author:`, `Month: <span>August 2026</span>`,
  `Search results for: &#8220;…&#8221;`. `term-description`: nothing when empty.
- `site-title`: `<p|h{n} class="wp-block-site-title"><a href="{home}" target="_self"
  rel="home"[ aria-current="page"]>` (home without a trailing slash; `aria-current` on
  the front page only). Empty tagline and no logo render nothing.
- `navigation`: inner links, or the referenced / newest `wp_navigation` post (this
  site's holds `<!-- wp:page-list /-->`), or the first classic `nav_menu` when
  no `wp_navigation` post is published. Classic items become navigation-link
  blocks with `menu-item menu-item-type-* menu-item-object-*` classes. Non-responsive: `<nav class="[is-vertical]
  wp-block-navigation [layout]" aria-label=" {n}">` where n is the shared request
  counter. Responsive: the overlay markup (`modal-{n}`, interactivity attributes,
  overlay colour classes from `overlayTextColor`/`overlayBackgroundColor`; the dialog
  label is always "Menu"). `page-list` marks `current-menu-item` (+`aria-current`) and
  `current-menu-ancestor` by comparing the QUERIED OBJECT'S ID to page ids with no regard
  to type, so a tag archive with term id 2 lights up the page with id 2. Reproduced.
- `comments` family: nothing unless the post has comments or takes them (its
  count as `get_comments_number` filters it); the template lists through
  `WP_Comment_Query` with `build_comment_query_vars_from_block`'s vars
  (approved plus the reader's own held ones, oldest first, threaded and paged
  as the site says, newest first reversing only the top level);
  `comments-title` `One response to &#8220;…&#8221;`; comment list items `comment
  even|odd thread-even|odd depth-{n}` with the FIRST comment `even`; avatars at
  `size`/`2x`; the reply link with the `?replytocom=` href and data attributes; the
  full comment form (cancel link is path-only). Edit links render for nobody
  (anonymous rendering).
- `categories`: `current-cat` and `aria-current` (before `href`) on the viewed
  category. `archives`: `aria-current="page"` on the viewed month. `search`: the
  label gets `screen-reader-text` when `showLabel` is false; the input carries the
  current search term. Search results rank title matches first.
- Images on a page: a budget of three eager `<img>` tags, every `<img>` counting
  (a latest-comments avatar included); the first eager content image gets
  `fetchpriority="high"`, the rest `decoding="async"` only, and lazy images alone carry
  the `auto,` sizes hint.

## Stylesheets (20b)

The head carries two stylesheets of the engine's own: the block stylesheet
(`/minn/assets/blocks.css`, served from `public/minn/assets/`; structure and behaviour for the
core block class names, original work) and the generated global styles (`Minn\Theme\GlobalStyles`,
inline as `#global-styles-inline-css`). The theme's own `style.css` is linked only when the
theme enqueues it from its `functions.php`, which the runtime loads (`contracts/runtime.md`);
the reference links it no other way (a block theme without a `functions.php`, like the dogfood
site's parent copy, gets no link on either stack). Until 2026-08-29 the engine linked it itself;
`tests/styles.test.php` now pins the theme links to the reference's set.

What the generator reproduces from theme.json, checked against the reference by
`tests/styles.test.php`: every `--wp--preset--*` custom property (core default colours,
gradients, shadows, and aspect ratios from `public/minn/data/presets.json` plus the theme's
palette, font sizes, families, spacing, shadows) and every `has-*` preset class,
byte for byte. A preset list saved by the site editor is keyed by origin
(`default`, `theme`, `custom`) rather than written flat; the reference prints the
origins in that order, and the engine flattens both shapes the same way (the dogfood
site's saved styles carry `fontFamilies: {theme: [...], custom: []}`, which printed one
empty `--wp--preset--font-family--` property before this was learned). Families that
declare `fontFace` entries also print `<style class="wp-fonts-local">` at the end of
the head: one `@font-face` per face, `font-family` (quoted only when it has a space),
`font-style`, `font-weight`, `font-display: fallback`, then `src` with `file:./` resolved
against the theme that carries the file (child first) and the format named from the
extension. Every declared face prints, used or not, in theme.json order. Fluid font sizes become `clamp(min, min + ((1vw - 0.2rem) * f), max)`
where f scales between a 320px viewport and the theme's wide size. Root styles land on
`body`, and under `useRootPaddingAwareAlignments` the root padding becomes the
`--wp--style--root--padding-*` properties rather than body padding. Elements (`link`,
headings, `button`, `caption`, with `:hover`/`:focus`/`:active`), per-block styles,
block gaps, and `css` blocks map to the reference's selectors. Style variations get one
rule set per numbered instance the page rendered, containers get their
`wp-container-*` declarations (flex wrap, direction, justification, gap, grid
columns), and galleries get their gap property. The structural layout rules (flow,
constrained, flex, grid, alignments, global padding) are the engine's own on the same
class hooks; the diff against the reference's is empty on this theme.

Verified visually with Playwright at 1280px: home and a single post match the reference
to the pixel in geometry (`.wp-site-blocks`, header, alignwide, navigation boxes measured
equal).

## What the dogfood site taught (milestone 29)

`tests/dogfood.test.php` diffs a real site (dogfood: a child theme on
twentytwentythree, a static front page, `/%category%/%postname%/`, a site-editor
header and footer, synced patterns, social links, a handful of plugins) against its
own parked reference. It carries no fixtures; plugin-only markup is neutralised
(body-class tokens, a dark-palette toggle, a Jetpack slideshow, a gallery plugin's
anchors and its lowercased `viewbox`). Every fact below came out of that diff.

- **Static front page.** `show_on_front=page` renders `page_on_front` at `/` with body
  `home wp-singular page-template page-template-{slug} page page-id-N`; `/page/N/`
  renders it again with `home paged … paged-N page-paged-N`; its own permalink and
  `?page_id=` redirect to `/`; `get_permalink` of that page is the home URL. The
  template hierarchy tries `front-page` before the page's own candidates.
- **Body classes.** `page-template-default` becomes `page-template page-template-{slug}`
  under a custom template (`.` and `/` become `-`); `wp-custom-logo` follows the core
  set when `site_logo` is set; `wp-theme-{parent} wp-child-theme-{child}` close the
  list; the paging tokens sit after `wp-embed-responsive`.
- **Child themes.** Templates, parts, and patterns fall back to the parent; `theme.json`
  merges parent then child (maps key by key, preset lists whole), and the site
  editor's saved `wp_global_styles` post layers on top. Only the child's `style.css`
  is linked.
- **Category structure.** With `/%category%/%postname%/`, a bare category path
  (`/news/`) is the archive too; `/category/news/` stays valid.
- **Navigation.** The block's `textColor`, `fontSize`, `fontFamily`, and typography style
  ride on both `<nav>` and `<ul>` (`has-text-color has-x-color has-x-font-size …
  wp-block-navigation has-x-font-family`, style attribute first). `hasIcon:false` makes
  the overlay buttons plain "Menu"/"Close" with no aria-label and no svg. The aria-label
  is the referenced menu's title, only when the block names a `ref`; a repeated label
  gets " N" (so two unnamed navs read `""` then `" 2"`). Whitespace between items is
  not rendered; a non-link child is wrapped in `<li class="wp-block-navigation-item">`.
  A link whose `id`/`type` match the queried object gets `current-menu-item` and
  `aria-current="page"`; `opensInNewTab` adds `target="_blank"` (two trailing spaces).
- **Element styles.** A block with `style.elements` gets `wp-elements-N` (its own
  counter, parents before children, dynamic blocks numbered before they render so an
  empty pagination still counts) and rules `.wp-elements-N a:where(:not(.wp-element-button)){…}`
  per state. Blocks without element slots (search, navigation, social-links, buttons)
  take no number. Site-title, post-date, and post-excerpt also add `has-link-color`;
  query-title and post-content do not.
- **Dynamic wrappers** (`Minn\Blocks\Wrapper`): classes run text-align, link-colour
  marker, the block's extras (taxonomy, alignment, `columns-N`), custom class and its
  numbered style, `wp-elements-N`, the block class, colour presets, font size, font
  family, then layout classes. Style attribute first for post-title, post-date,
  post-content, query-title, post-template, featured image, site-title.
- **Inline style order.** Groups run border, colour, typography, spacing; typography
  properties in a fixed order with `letter-spacing` after `text-transform`; a spacing
  box's sides in the order the block stored them (`margin-bottom` before `margin-top`
  when saved that way).
- **Featured image.** `<img width height src class="attachment-post-thumbnail
  size-post-thumbnail wp-post-image" alt style decoding fetchpriority|loading srcset sizes />`;
  style is `aspect-ratio`, `height`, `width`, then `object-fit:{scale}` (always). A
  linked image with empty alt borrows the post title. A srcset needs two candidates;
  with one there is neither `srcset` nor `sizes` (content images too).
- **Post excerpt** leaves a space before `</p>` where the more link would go.
- **Query title** without `showPrefix` is the bare term name; `align` lands as
  `alignwide` before the block class.
- **Post template** carries `columns-N` for a grid `columnCount`, then the alignment.
- **Search** with `buttonPosition:button-inside` + `buttonUseIcon` renders the icon
  button (`has-icon`), colour presets on the button, font family on input and button.
- **Social links**: the stored `<ul>` gains the flex layout classes; each link is
  `<li style="color:…" class="wp-social-link wp-social-link-{service} has-x-color
  wp-block-social-link"><a rel="noopener nofollow" target="_blank" href class="wp-block-social-link-anchor">
  {svg}<span class="wp-block-social-link-label screen-reader-text">{Label}</span></a></li>`,
  the icons captured from the reference's output into `public/minn/data/social-icons.json`
  (48 services; unknown services get the share icon).
- **Synced patterns** (`core/block`) render the referenced `wp_block` post's content.
- **Third-party blocks** pass through as stored; their `wp-image-N` images still count
  toward the page's loading budget. Uploads stored at the root of `uploads/` build
  sub-size URLs without a `./` segment.
- **Not reproducible** (plugin runtime): plugin body classes, blocks whose markup a
  plugin produces (dark-palette toggle, Jetpack slideshow), plugins that rewrite the
  page (gallery links, attribute lowercasing), shortcodes (`[eeb_protect_content]`
  stays literal, as it does on the reference without that plugin).

## The global stylesheet, rule by rule (dogfood pass two)

`tests/browser/geometry.test.js` walks every visible element of the dogfood
pages in Chrome and compares box, font, size, weight, line height and letter
spacing against the reference; it is the suite that caught everything below,
none of which markup parity could see. The generated stylesheet now matches the
reference's rule set and rule order on the dogfood pages (the container and
`wp-elements-N` rules excepted: the reference prints those in a separate style
element).

- **Element order is fixed.** Elements print as link, heading, h1 to h6, button,
  caption, cite, whatever order the theme or the saved styles list them in; the
  heading group's line-height therefore loses to an h1's, which is what the theme
  intends. States print base, `:visited`, `:hover`, `:focus`, `:active`.
- **Core's button defaults.** The reference layers its own theme.json under the
  theme's: the button element always carries `border-width: 0`, `inherit` for
  font family, size, style, weight, letter spacing, line height and transform,
  `text-decoration: none` and `calc(0.667em + 2px) calc(1.333em + 2px)` padding,
  each key replaceable by the theme (`public/minn/data/styles.json`). A search
  button is 66px tall with them and 42px without.
- **Declarations print in property order**: alphabetical, except that margin and
  padding sides run top, right, bottom, left.
- **Empty values print nothing.** A saved style can hold `""` for a cleared
  value; the reference drops it rather than printing `font-size: ;`.
- **Block styles print only for rendered blocks.** A core block's theme.json
  styles reach the page only when the block rendered with output; a generated
  excerpt counts for the blocks it keeps; a block the reference has no
  stylesheet for (`core/query`, `core/column`, the pagination pieces, and the
  rest of `STYLESHEET_LESS`) never prints, which is why TT3's
  `.wp-block-query h2` rule is invisible on every page. Blocks from outside core
  print unconditionally. The block's own `css` text prints as written with the
  block selector prefixed without a space (`.wp-block-jetpack-slideshow.swiper-pagination`),
  and the root `styles.css` prints between the preset classes and the block styles.
- **Block root selectors** come from the block's metadata: `p` for the paragraph,
  `.wp-block-list > li`, `.wp-block-button .wp-block-button__link`,
  `.wp-block-table > table`, `.wp-block-icon svg`.
- **Presets keep core's slots.** The default spacing scale (20 to 80) always prints
  whatever `defaultSpacingSizes` says, a theme size with the same slug replacing
  the default in place; theme font sizes print in core's order for core's slugs
  (small, medium, large, x-large) with the theme's own slugs after. A fluid size
  with a px minimum states the additive term in rem (`clamp(20px, 1.25rem + ...`).
- **The engine's block stylesheet** dropped its global `box-sizing: border-box`
  reset (the reference sets it per block; a padded paragraph in a constrained
  layout is 40px wider without the reset), gained the social-links, aligned-image
  (`display: table`, caption as `table-caption`), post-excerpt, and
  button-inside search rules, the two legacy font-size presets, and the
  navigation wrappers' desktop shape; the navigation container inherits its gap.

## Geometry parity on the engine's own theme (Twenty Twenty-Five)

`node tests/browser/geometry.test.js --dev` walks fifteen dev-site pages against the
8123 oracle; all fifteen match. Facts learned there, beyond the dogfood list:

- **Block style variations live in `styles/*.json` too.** A partial with `blockTypes`
  (and a `slug`, else its file name) is a variation of every block it names
  (`styles/blocks/post-terms-1.json` gives `core/post-terms` its `post-terms-1`
  style; the section partials give groups and columns theirs, with nested `blocks`).
  `Theme::json()` folds them in, parent first. A variation's declarations land on the
  block's root selector with the numbered class on the block class
  (`.wp-block-button.is-style-outline--3 .wp-block-button__link`); element rules
  inside it are scoped by the numbered class alone (`.is-style-post-terms-1--2 a:where(…)`);
  nested block styles by `.is-style-X--N .wp-block-y`.
- **Core's outline button defaults** sit under the theme's: `background: transparent none`,
  `border-style: solid`, `color: currentColor` (`data/styles.json`). Border declarations
  print radius, color, width, style, ahead of the alphabetical order.
- **A flow or constrained gap is written onto the children**: the container class gets
  `> * {margin-block-start: G; margin-block-end: 0}` and `> * + *` with the same, never a
  declaration on the container itself.
- **Block stylesheet facts**: the submenu is a zero-sized hidden flex column that keeps its
  1px border (2px by 2px) until hover, focus-within or an expanded toggle opens it to a
  200px floor; the page list is a flex row driven by the navigation layout variables;
  `.wp-block-post-content` is `flow-root`; a border width in an inline style implies a
  solid border on that side; a `figure > a` in an image block is `inline-block`; a
  gallery figure is a flex column with a 13px caption laid over its foot and a 16px
  default gap; `thead`/`tfoot` carry 3px borders; the search label is only `width: 100%`
  (the screen-reader rule prints last so it still wins); a site-title link inherits its
  paragraph's typography; the latest-comments line-height rules wrap the whole descendant
  selector in `:not()`, so both land on the list and 1.8 wins; only `img[class*="wp-image-"]`
  scales to its container, so a broken avatar keeps its 50px attribute box; `1ch` arrow
  margins; latest-posts dates are `.8125em` and latest-comments dates `.75em`.

## Known gaps

- The responsive navigation overlay needs the interactivity script the reference ships;
  the engine keeps the menu inline at every width and hides the open/close buttons.
- Block-library CSS (GPL) is not carried; blocks outside the battery may need rules in
  `blocks.css` as they appear.
- Blocks the site's templates do not exercise are best-effort or empty: query
  pagination markup (no page two exists to capture), `comments-pagination`, `avatar`
  outside comments, search `no-button`/`button-only`, social links with visible labels.
- Pattern PHP beyond the interpreted grammar renders as nothing.
- The queried-object id quirk in `page-list` is reproduced as observed; a fix upstream
  would be a divergence to re-capture.

## What the Minn site theme taught (`site/minn-site` own repo, `docs/site.md`)

A second block theme rendered at parity, this one without twentytwentyfive's
settings, surfaced four facts (`tests/site.test.php` pins them):

- **Skip link target.** When the first `<main>` in the template already carries an
  `id`, the skip link points at that id and nothing is injected; `wp--skip-link--target`
  is only added to a `<main>` without one.
- **`has-global-padding`** is added to constrained containers only under a theme whose
  theme.json sets `settings.useRootPaddingAwareAlignments`; without it the class is absent
  everywhere, in templates and in REST-rendered content alike (`Layout::rootPaddingAware`,
  set from the active theme when the renderer is built).
- **`post-content` without a layout attribute** still carries `is-layout-flow
  wp-block-post-content-is-layout-flow` (the block's default layout), and no container.
- **The rendered template is texturized as a whole** after the blocks (straight quotes in
  the theme's own `core/html` markup curl, `--` becomes an em dash, `6'2"` gets its prime);
  content that arrived already texturized is unchanged by the second pass.

Open under this theme: a `core/separator` with `is-style-wide` gets no numbered
companion on the reference because the theme registers no such variation, while the
engine's fixed registry still numbers it (see `contracts/blocks.md`, style-variation
numbering). The site suite does not visit the block battery for that reason.
