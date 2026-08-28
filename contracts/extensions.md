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
