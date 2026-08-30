# Classic themes: the PHP template runner

The engine dispatches a classic (PHP-template) theme the way the reference
does: the URL resolves, the main query stands, template_redirect fires, the
hierarchy picks a PHP file, template_include filters it, and load_template()
runs it as the response body. The theme prints its own document; the engine
contributes the reference's wp_head and wp_footer defaults as hooks the
theme's own wp_head()/wp_footer() calls fire. Everything here was captured
against the shop-dogfood oracle (a real production site: anchor-theme, 48
active plugins) on 2026-08-30.

## What decides classic vs block

A block theme ships `templates/index.html` (child or parent). Anything else
with an `index.php` in the parent theme is classic and dispatches here;
theme.json alongside PHP templates is a classic theme with editor tokens
(anchor-theme is exactly this, and `wp_is_block_theme()` on the reference
draws the same line: an EMPTY templates/ directory does not make a block
theme). `Minn\Theme\Theme::active()` is the block check;
`Minn\Theme\ClassicTheme::active()` the classic one. With neither, the
interim template stands in, and the same happens when the symbol gate
refused the theme's functions.php (`Plugins::skipped()` has `theme:{slug}`):
running PHP templates without the theme's own helpers would fatal.

## Dispatch order (the loader)

`Minn\Theme\ClassicRenderer::template()` walks the conditionals in the
reference's order; the first true conditional whose template getter returns
a file wins, and a true conditional with no file falls through to the next:

    is_404, is_search, is_front_page, is_home, is_privacy_policy,
    is_post_type_archive, is_tax, is_attachment, is_single, is_page,
    is_singular, is_category, is_tag, is_author, is_date, is_archive,
    then index.php.

So single.php missing means singular.php, then index.php. The candidate
lists per type live in `Minn\Theme\Hierarchy` (front-page.php; home.php;
page templates with the custom `_wp_page_template` first, then
page-{slug}.php, page-{id}.php, page.php; single-{type}-{slug}.php down to
single.php; category/tag/taxonomy/author/date/archive/search/404 as the
reference tries them; urldecoded slug variants ahead of raw when they
differ). The facade get_{type}_template() functions are thin wrappers over
get_query_template(), which fires `{type}_template_hierarchy` and
`{type}_template` and keeps working for block themes (the canvas). Note the
type-name sanitiser strips underscores, so front_page filters as
`frontpage_template_hierarchy`, matching the reference.

## The main query and the content pipeline

`Minn\Theme\MainQueryBridge` (shared with the block-theme PageRenderer)
stands the main query: a plugin's archive runs through WP_Query so
pre_get_posts applies, everything else seeds the engine's own listing; then
`wp` and `template_redirect` fire. Loop tags (have_posts/the_post), the
conditionals, body_class and post_class all read that standing query.

`the_content()` under a classic theme runs the engine's whole pipeline
(`Minn\Theme\ClassicContent`): block render, password gate, the more-tag
teaser off singular views, cached embeds, extension seams, runtime
shortcodes, then the `the_content` filter for plugin code. A bare
`apply_filters('the_content', ...)` anywhere else stays a plain filter run
(the reference's the_content defaults are deliberately not registered).
`get_the_excerpt()` keeps texturized entities (`&#8217;`), as the reference
does; decoding them was a bug the theme's cards surfaced.

## wp_head and wp_footer defaults

Registered by `_minn_classic_head_defaults()` at setup_theme priority 1, so
they precede the theme's own hooks the way the reference's default-filters
registrations precede plugin loading. All by interface name, so a plugin's
remove_action() finds them:

The registration table below was captured from the reference's own hook
tables (`$wp_filter['wp_head']`, observed data, 2026-08-30) and the engine
registers the same names at the same priorities:

| hook | callback | prio | notes |
|---|---|---|---|
| wp_head | _wp_render_title_tag | 1 | title-tag support only; document_title filter pipeline; `-` texturizes to `&#8211;` |
| wp_head | wp_robots | 1 | directives from the wp_robots filter chain; see "The robots meta" below |
| wp_head | wp_resource_hints | 2 | dns-prefetch for enqueued asset hosts off the page's own host, then filter-added preconnects |
| wp_head | feed_links / feed_links_extra | 2 / 3 | automatic-feed-links support checked at print time; shared HeadLinks strings |
| wp_head | wp_oembed_add_discovery_links | 4 AND 10 | the reference registers it twice; it prints ONCE, ahead of the styles (the priority-4 firing wins, the engine guards with a per-request flag so remove_action at either priority still lands) |
| wp_head | rest_output_link_wp_head, rsd_link, wp_generator, rel_canonical, wp_shortlink_wp_head | 10 | in THIS order: generator prints before canonical. rest_output prints `<link rel="https://api.w.org/">` plus, when the view has a queried object, the JSON alternate (`wp/v2/{posts\|pages\|categories\|tags\|users}/{id}`; term archives and authors get one too), with NO trailing newline; rsd_link continues the same physical line with the EditURI link and ends it. Canonical on singular only; shortlink on singular only as `<link rel='shortlink' href='{home}/?p={id}' />` (single quotes, pages too) |
| wp_head | wp_site_icon | 99 | picks the smallest generated sub-size ≥ 32/192/180/270, like the reference |
| wp_footer | wp_print_speculation_rules | 10 | registered in defaults (before plugin hooks) so it prints first in its bucket; classic + signed-out + pretty permalinks only |
| wp_head/wp_footer | _minn_classic_bar_head / _minn_classic_bar_footer | 200 | the Minn front bar |

## The robots meta

`wp_robots()` runs the `wp_robots` filter over an empty array; the directive
order in the tag is the filter registration order. The reference registers
four defaults (captured from its filter table) and the engine registers the
same names in `defaults/filters.php`: `wp_robots_noindex` (blog_public 0),
`wp_robots_noindex_embeds` (embed views), `wp_robots_noindex_search`
(search results), `wp_robots_max_image_preview_large`. So a search page
prints `noindex, follow, max-image-preview:large` and an ordinary page
`max-image-preview:large`. A 404 does NOT get noindex in WP 7.1.

The engine's block stylesheet and (when the theme ships theme.json) the
global styles print through the existing `_minn_print_engine_styles` slot
at priority 8, inside wp_print_styles order. `wp-img-auto-sizes-contain`
enqueues first (wp_enqueue_scripts priority 0) as an inline style, matching
the reference's queue position.

## Menus

`wp_nav_menu()` is a thin facade over `Minn\Runtime\NavMenu::build()`:
menu by name, assigned location, or first-menu-with-items; item decoration
(menu-item, type and object tokens, menu-item-home on the front-page item);
current markers (current-menu-item plus the page compat tokens
page_item/page-item-{id}/current_page_item; the posts-page item is current
on is_home and current_page_parent while a single post is on view; custom
items match the request URL); the menu-parent/ancestor chain;
menu-item-has-children when depth != 1; the wp_nav_menu_* filter set; the
default items_wrap `<ul id="menu-{slug}" class="menu">` with repeat ids
counted up.

Custom-item rules (fixture capture, item URL pointed at the reference's own
host): a custom item whose URL equals home carries `menu-item-home` on
EVERY view, seated after the current tokens; it is current only on the
exact request URL (so `/` yes, `/page/2/` and `?s=` no), and while current
it also gets `current_page_item` with no page_item/page-item-N tokens. Note
a page-object front-page item seats menu-item-home BEFORE its current
tokens; the custom shape seats it after. The two stacks serve different
hosts off one database, so the parity suite keeps its shared custom item
off-host and pins these tokens in an engine-only battery.

The current-{object}-parent/-ancestor family on singular views (fixture
capture, post 1 in category 1 and page 6 under page 2): a taxonomy item
holding one of the post's terms gets `current-{type}-ancestor` (type is the
queried post type) right after its object token, then `current-menu-parent`
and `current-{type}-parent` appended in the later passes; the {type}-parent
pass matches OBJECT IDS ONLY, so the post's own item picks up
`current-post-parent` when its post id collides with the term id (post 1 /
term 1), exactly as the reference does. On a hierarchical type the
ancestor chain marks the parent page's item `current-page-ancestor
current-page-parent` in the typed scan alone, with NO current-menu-parent. `Walker` delegates traversal to `Minn\Runtime\TreeWalk`
(display_element stays virtual, so a subclass override changes recursion);
`Walker_Nav_Menu` prints the reference's li/a markup with the
nav_menu_css_class / nav_menu_item_id / nav_menu_link_attributes /
nav_menu_item_title / walker_nav_menu_start_el filters and the
item_spacing preserve/discard whitespace. A theme's custom walker
(anchor's Anchor_Nav_Walker) plugs in unchanged — the primary nav matched
the oracle byte for byte, including aria-current="page" and is-active on
the posts page.

## The tags the gate was missing

body_class()/get_body_class() read the class list the renderer stood
(`Minn\Theme\BodyClasses::classic`): the core query tokens with
wp-singular and {type}-template-default spliced ahead of the type token,
privacy-policy in front on that page, then logged-in, wp-custom-logo (the
theme mod), wp-embed-responsive (support-gated), the numbered paging
tokens re-seated, wp-theme-{template} (+ wp-child-theme-{stylesheet}), and
the body_class filter last. The bare `paged` token seats after the view tokens (`home blog paged
wp-embed-responsive paged-2`), the numbered paging tokens after the embed
token. Verified byte-identical against the oracle for front (static page),
blog home, paged, single, page, privacy, category, search, and 404 views.
post_class(), the_archive_title(), the_archive_description() are echo
wrappers over the existing getters. the_posts_pagination() wraps
paginate_links in `_navigation_markup` (nav.navigation.pagination,
screen-reader h2, div.nav-links); its defaults are "Posts pagination" for
both the heading and the aria-label, and a caller's screen_reader_text
becomes the aria-label when none is given. Page numbers carry NO aria-label
in the reference: the "Page N" labels seen on shop-dogfood come from
WooCommerce's `wc_add_aria_label_to_pagination_numbers` on the
`paginate_links_output` filter, which the engine's paginate_links applies
to its string output (array output skips it), so the plugin decorates both
stacks identically. The engine once hardcoded those labels in the facade;
do not put plugin behaviour back in that layer.

`get_the_archive_title()` prefixes the bare name the way the block path
does: `Category: <span>Uncategorized</span>`, `Tag:`, `Author:` (fixture
captures), `Year: <span>2026</span>`, `Month: <span>August 2026</span>`
(date captures; `Day:` with `F j, Y` inferred from those two, uncaptured),
the taxonomy's singular label for custom taxonomies, `Archives:` for post
type archives (uncaptured, WP-conventional). The filter receives
`($title, $original_title, $prefix)`. Search stays `Search Results for: …`
with no span.

`get_search_form()` loads the theme's own `searchform.php` when one exists
(child then parent), buffering its output through load_template; the built
default form is the fallback. anchor-theme's sidebar form matched the
oracle byte for byte through this path.

The classic main query fills page 1 like the reference: sticky posts ride
on TOP of the page without consuming its slots (`Posts::listing` with
stickyExtra, the same shape the query block uses), and later pages are
plain date order.

## Facts the oracle taught (fixed engine-wide, not just classic)

- **date_i18n does not shift numeric timestamps**: the timestamp arrives
  pre-offset and its digits print as-is (gmdate semantics), attached to the
  site timezone only for timezone-name formats. The engine used to subtract
  gmt_offset and re-apply the zone; with America/New_York the stored -4
  offset made every formatted date a day early (the anchor CVE cards
  caught it).
- **wp_embed_defaults computes from $content_width** (500 fallback), height
  `min(ceil(w*1.5), 1000)`; it never reads the embed_size_w/h options. The
  numbers feed the `_oembed_{md5(url.serialize(attr))}` postmeta cache key,
  so a mismatch silently misses every cached embed.
- **WP_Embed::shortcode answers from the postmeta cache first** ('{{unknown}}'
  short-circuits to the plain link); only a miss fetches.
- **wp_localize_script prints slashes unescaped** (`"/wp-admin/..."`, not
  `"\/wp-admin\/..."`).
- **The jquery alias depends on jquery-core alone** in the reference;
  jquery-migrate stays registered but nothing default-enqueues it.
- **get_pagenum_link builds on the current request** (path with its page/N
  segment swapped, query string kept), so the posts page paginates at
  /blog/page/2/ and search pagination keeps ?s=.
- **The posts page paginates like home**: page/N under it serves the blog's
  page N; past the last page is 404. (The resolver used to 404 all of them.)
- **Classic global styles**: root padding lands on body outright
  (`padding-top: 0px;...`, 0px when unset) and the has-global-padding rules
  are absent; only under useRootPaddingAwareAlignments do the custom
  properties and .has-global-padding rules exist. This is keyed off the
  theme.json flag, not classic-vs-block.
- **The site icon links use generated sub-sizes** (smallest ≥ requested),
  not the original file.

## Honest gaps (as of 2026-08-30)

- core/image lightbox (Interactivity API server markup: trigger button,
  overlay, importmap, module data) is not rendered; images degrade to plain
  figures. The eager-image/fetchpriority budget can differ on posts where
  the reference's lightbox markup shifts image order.
- The engine does not register the editor-package script handles (react,
  lodash, wp-data, wp-element...), so WooCommerce Blocks integrations print
  their console.error fallbacks and wc-blocks/wp-theme/wp-components CSS
  never enqueues. Deliberate for now.
- Speculation rules print on classic pages only; the block path's wp_footer
  capture does not include them yet (the reference prints them there too).
- The block path prints no robots meta at all, and its head has no EditURI
  or shortlink link (the reference prints all three under block themes
  too). The block suites diff bodies, so nothing pins these yet; the
  classic path is the one held to the head battery.
- wp_page_menu renders the list shape without the reference's page-walker
  chrome (heading, container class variants); no fallback caller depends on
  it yet.
- Head parity on shop-dogfood is bounded by the plugins its symbol gate
  still skips (perfmatters removes feed links and moves scripts;
  genesis-blocks, seriously-simple-podcasting print their own tags). The
  theme-owned surface diffs clean.

## The fixture suite (in run-all)

`tests/classic.test.php` renders the fixture theme on the DEV site (TT5-era
full WordPress, no head-mangling plugins), diffs eight page bodies against
the live reference, and compares a head battery line for line (title,
robots, feed links, oEmbed discovery, the rest+RSD line, generator,
canonical, shortlink) — the first suite to pin the classic head. It builds
a real menu through the reference's wp-cli (custom, post, page, child page,
term items), lowers posts_per_page so pagination renders, and restores
everything on shutdown. The five gaps it surfaced on 2026-08-30 (archive
title prefix, search noindex, RSD+shortlink, the nav parent/ancestor
family, two normaliser artifacts) are all fixed and pinned above, plus
three more the deeper diffs reached: the paged body-class order, sticky
posts consuming page-1 slots, and the comments-feed rule below. 23 checks,
green, in run-all between theme and styles.

Suite gotchas: the head normaliser folds BOTH hosts in raw and urlencoded
form (the oEmbed discovery href embeds its own host urlencoded); the shared
custom menu item stays off-host (see Menus above) with the home-item
behaviour pinned engine-only; `minn_test_pin_theme` restores only the
FIRST pin's saved theme per process, so a suite pinning the fixture over
lib.php's TT5 pin still puts the site's own theme back (this was a real
bug: a standalone classic run used to strand the dev site on TT5).

A singular view's own comments feed prints while comments OR pings are
open on it, or once it has comments: the reference prints it for a page
with closed comments, open pings, and a zero count (the old rule demanded
the static front page or a first comment; the dogfood suite stayed green
under the corrected rule).
