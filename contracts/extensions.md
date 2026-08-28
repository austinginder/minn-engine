# Extensions

How code that is not the engine takes part in a request, and how a WordPress
plugin's behaviour is re-provided on Minn. Code: `public/minn/src/Minn/Extension/`.
Suite: `tests/extensions.test.php` (24 checks). First port: `extensions/minn-block-visibility`.

## What an extension is

A folder under `wp-content/plugins/` (or `mu-plugins/`) with a `minn.json`:

```json
{
  "name": "Block Visibility for Minn",
  "version": "1.0.0",
  "license": "MIT",
  "replaces": ["block-visibility/block-visibility.php"],
  "autoload": {"Minn\\Ext\\BlockVisibility\\": "src/"},
  "extension": "Minn\\Ext\\BlockVisibility\\Extension"
}
```

The class implements `Minn\Extension\Extension` (one method, `register(Seams $minn)`)
in plain modern PHP: PSR-4 from the manifest, typed seams, no globals, no hook
names to remember. The same folder may also be a WordPress plugin; nothing in the
manifest is read by WordPress and nothing in the plugin's PHP is read by the engine.

**Activation** follows the levers the site already has: the extension is active when
one of the plugin files it `replaces` is in `active_plugins`, when its own folder is
in `active_plugins` (a plugin that is also an extension), when its slug is in the
`minn_active_extensions` option (JSON list), or when it lives in `mu-plugins`.
`wp minn info` lists the active set; `minn preflight` reports a replaced plugin as
"provided by" its extension instead of "will not run".

An extension that throws while registering is logged and skipped; the page renders
without it.

## The seams

`Seams` carries the request (`db`, `site`, `request`, `reader`) and the
registrations, each a typed closure, called in registration order:

| Seam | Signature | Fires |
|---|---|---|
| `gateBlocks` | `(Block, Seams): ?bool` | before any named block renders; `false` drops it (and its children) |
| `filterBlocks` | `(Block, string $html, Seams): string` | after a named block renders |
| `shortcode($tag)` | `(array $attrs, ?string $content, Seams): string` | on rendered post content and feed content: `[tag]`, `[tag a="b" flag]`, `[tag]inner[/tag]`; `[[tag]]` is the literal; unregistered tags stay as written (the reference leaves them too, texturized) |
| `filterContent` | `(string $html, array $post): string` | after blocks and shortcodes, for post content and feeds |
| `head` / `footer` | `(Seams): string` | in the themed document, after the theme stylesheet and before `</body>` |
| `bodyClass` | `string` | appended after the theme classes |
| `filterDocument` | `(string $html): string` | the whole themed document before it is sent (what an output buffer did on the reference) |

A manifest may add `"covers"`: what part of the replaced plugin the extension
provides when it is not all of it; preflight prints it and lights AMBER instead of
GREEN for that plugin.

Not yet seams (next ports decide their shape): routes and REST controllers, cron
jobs, CLI verbs, file assets by URL (a plugin folder is web-served, so an extension
can link its own files), Minn Admin surfaces.

## Porting a WordPress plugin

The mapping from the plugin's idioms to the seams is the porting guide, applied
once per plugin, by hand or by an agent, never at runtime:

| WordPress idiom | Seam |
|---|---|
| `render_block` filters, `render_callback` | `gateBlocks`, `filterBlocks`, the block renderer registry |
| `add_shortcode` | `shortcode` |
| `the_content` filters | `filterContent` |
| `wp_head`, `wp_footer`, `wp_enqueue_*` | `head`, `footer` |
| `body_class` | `bodyClass` |
| `is_user_logged_in`, `current_user_can`, roles | `Seams::reader` |
| `get_option`, post meta, `WP_Query` | `Seams::site`, `Minn\Content\*` |
| `$_SERVER['HTTP_USER_AGENT']` and friends | `Seams::request` |

An extension written from the plugin's observed output can be MIT; one written
from the plugin's source is a derivative of that plugin and is GPL. Both are
allowed; the manifest's `license` says which. The engine never depends on either.

## The ports so far (all MIT, from observed output; `extensions/` in the engine repo)

| Extension | Replaces | What it re-provides |
|---|---|---|
| `minn-block-visibility` | block-visibility | the `blockVisibility` rules (below) |
| `minn-simple-custom-css` | simple-custom-css | `<style id="sccss">` from the `sccss_settings` option |
| `minn-ga-google-analytics` | ga-google-analytics | the gtag snippet from `gap_options`, head or footer, anonymize flag |
| `minn-wp-retina-2x` | wp-retina-2x | a `name@2x.ext` file beside a full-size image joins the srcset at twice the width |
| `minn-gallery-custom-links` | gallery-custom-links | anchors from `_gallery_link_*` attachment meta; the plugin's whole-document pass (attribute names lower-cased once something was linked; the count comment before `</body>`) |
| `minn-jetpack-slideshow` | jetpack (covers: the slideshow block only) | the block's images without the lazy `auto` hint, the block's stylesheet, Swiper, and view script from the plugin's own folder, plus the extension's own `assets/wp-globals.js` (the `wp.domReady`, `wp.i18n`, `wp.escapeHtml` browser globals the view script expects the page to provide; the reference ships them from its script library) |
| `minn-mosne-dark-palette` | mosne-dark-palette | the `mosne/dark-palette` navigation item from its attributes (labels, default mode, `enableAuto`, classes), its styles from the plugin's build folder, the generated palette rule (`html[data-theme="dark"]` re-points every `--wp--preset--color--{slug}` to a `--mosne-dark-palette-{slug}` variable from the block's `darkColorsPalette`; the scheme flips when `themeOption` is dark), and the head script that sets `data-theme` before paint; the toggle itself runs on the extension's own `assets/view.js` (the plugin's view script is a module on the block interactivity runtime, which the engine does not provide) |
| `minn-ml-slider`, `minn-modula` | ml-slider, modula (covers: body class only) | the body class each added; the site's content uses neither |
| `minn-autodescription` | autodescription (covers: titles, description, robots, canonical, Open Graph, Twitter, the schema.org graph; not its sitemap or query alterations) | the title (`{page} | {site}`, `{site} | {tagline}` at home), the meta block per page kind, and the ld+json graph with breadcrumbs, from the plugin's settings option and `_genesis_*` meta. Search descriptions are 160 characters, social ones 300, both cut on a word with an ellipsis unless a sentence ended; the og:image is the featured image with its size and alt, else the first content image, else the site icon |

The child theme's `functions.php` printed its own Open Graph block on singular
pages; that is site code, so it lives with the site as
`wp-content/plugins/dogfoodchild-head/` (activated through `minn_active_extensions`),
not in the engine repo.

## Scripts a port cannot borrow

A plugin's front-end script often assumes the reference's own script
library is on the page: the `wp.*` browser globals, or `@wordpress/*`
modules resolved through an import map. The engine carries none of that,
so a port either provides the small globals itself (the slideshow) or
replaces the script with its own (the dark palette). The markup stays
identical either way; the dogfood suite compares bodies with scripts
removed, so a missing runtime shows up only in the browser, as a console
error and a block that never initialises.

## Head parity

`tests/dogfood.test.php` now also compares the head: the title, every meta tag,
the links that name the page (canonical, alternates, icons, the REST discovery
link), and the ld+json graph (one generated author id masked), sorted, with
stylesheets, scripts, and the reference's discovery and emoji plumbing left out.
The engine itself now prints the reference's own head links: the site and comments
feeds, an archive's own feed (a search's `…/feed/rss2/`), a single's comments feed
when it has comments, `rel="https://api.w.org/"`, the JSON alternate for the queried
object, and the site icon set. The `title` seam lets an extension replace the
document title. All twelve dogfood pages match on head and body.

With these, every one of the twelve dogfood pages on dogfood matches the
reference with no normalisation, head and body. Still unported on that site: plugins with nothing on the front end (duplicate-page, filebird,
enable-media-replace, stream, white-label-cms, login-logo, foogallery, coblocks,
gutenslider, carousel-block, carousel-slider, smart-slider-3, nextgen-gallery: none
appear in the site's content).

A navigation child block that renders its own `<li>` (the dark-palette item) is
not wrapped again; the reference does the same.

## First port: block visibility

`extensions/minn-block-visibility` (MIT, from observed output) honours the
`blockVisibility` attribute: `hideBlock`; `browserDevice` rule sets where `mobile`
covers phones and tablets and `other` is everything else, with `hideOnRuleSets`
deciding the sense; `screenSize` as the three hide classes plus the breakpoint
stylesheet in the head; `userRole` (`logged-in`, `logged-out`, `user-role`);
`dateTime` schedules. On dogfood the pages that carry device rules now render
the same group the reference renders for every user agent tried, and the hidden
groups are gone (`tests/dogfood.test.php` covers `/corporate/` and
`/residential/11-fifth-3/`).

Also learned from those pages: under a category-first permalink structure an
unmatched bare path is a category query on the reference, so no 404 guess runs
(`/11-fifth-3/` is 404, not a redirect to the child page); other structures guess.
