# Front end: block themes

The engine renders the site's installed block theme, read as data: `theme.json`,
`templates/*.html`, `parts/*.html`, `patterns/*.php`, and the site editor's saved
`wp_template` / `wp_template_part` / `wp_navigation` posts. No theme PHP runs.
Code: `src/Minn/Theme/` (`Theme`, `Templates`, `PatternText`, `PageRenderer`) and
the template blocks in `src/Minn/Blocks/Dynamic/Theme/`.

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
  site's holds `<!-- wp:page-list /-->`). Non-responsive: `<nav class="[is-vertical]
  wp-block-navigation [layout]" aria-label=" {n}">` where n is the shared request
  counter. Responsive: the overlay markup (`modal-{n}`, interactivity attributes,
  overlay colour classes from `overlayTextColor`/`overlayBackgroundColor`; the dialog
  label is always "Menu"). `page-list` marks `current-menu-item` (+`aria-current`) and
  `current-menu-ancestor` by comparing the QUERIED OBJECT'S ID to page ids with no regard
  to type, so a tag archive with term id 2 lights up the page with id 2. Reproduced.
- `comments` family: nothing unless the post has comments or takes them;
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

## Known gaps

- No stylesheets yet: the page links `/minn-engine/blocks.css`, which milestone 20b
  provides along with the theme.json global styles and the container stylesheets.
- Blocks the site's templates do not exercise are best-effort or empty: featured
  images with a thumbnail, `post-excerpt`, query pagination markup (no page two
  exists to capture), `comments-pagination`, `social-links`, `avatar` outside comments.
- Pattern PHP beyond the interpreted grammar renders as nothing.
- The queried-object id quirk in `page-list` is reproduced as observed; a fix upstream
  would be a divergence to re-capture.
