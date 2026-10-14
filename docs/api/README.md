# Minn API

The engine's own classes under `public/minn/src/Minn/`, one page per namespace, read from the code by `php tests/tools/api-docs.php`. Signatures are reflection, prose is the docblocks. Regenerate after any change to `src/Minn/`; the style suite fails when this folder is stale. The WordPress-facing facade (`wp-api/`) is not here: its map is `contracts/api/mappings.json`.

| Namespace | Classes | What lives there |
|---|---|---|
| [`Minn`](minn.md) | 8 | the front door, the autoloader, the one database door, the REST error |
| [`Minn\Admin`](admin.md) | 29 | the minn-admin/v1 namespace and serving the Minn Admin app |
| [`Minn\Auth`](auth.md) | 20 | passwords, sessions, cookies, nonces, roles and capabilities |
| [`Minn\Blocks`](blocks.md) | 23 | the block parser and renderer |
| [`Minn\Blocks\Dynamic`](blocks-dynamic.md) | 8 | dynamic core blocks that render from data |
| [`Minn\Blocks\Dynamic\Theme`](blocks-dynamic-theme.md) | 6 | the template blocks a block theme composes with |
| [`Minn\Cli`](cli.md) | 18 | the wp verbs the engine answers itself |
| [`Minn\Content`](content.md) | 42 | the repositories and records: posts, users, terms, comments, and the render pipeline |
| [`Minn\Cron`](cron.md) | 1 | scheduled publishing |
| [`Minn\Extension`](extension.md) | 8 | the extension contract and its seams |
| [`Minn\Feed`](feed.md) | 8 |  |
| [`Minn\Front`](front.md) | 44 | URL resolution, permalinks, feeds, sitemaps and the public page |
| [`Minn\Html`](html.md) | 7 | the HTML tag processor |
| [`Minn\Html\Tree`](html-tree.md) | 13 |  |
| [`Minn\Http`](http.md) | 38 | request, response, routing, and the outgoing client |
| [`Minn\I18n`](i18n.md) | 14 |  |
| [`Minn\Login`](login.md) | 5 | /wp-login.php and the sign-in surface |
| [`Minn\Mail`](mail.md) | 24 | sending mail and the notices the engine sends |
| [`Minn\Media`](media.md) | 14 | uploads, image sizes and attachment metadata |
| [`Minn\Objects`](objects.md) | 14 |  |
| [`Minn\Ops`](ops.md) | 18 |  |
| [`Minn\Query`](query.md) | 10 | shared SQL fragments |
| [`Minn\Rest`](rest.md) | 87 | the wp/v2 surface: shapes and controllers |
| [`Minn\Runtime`](runtime.md) | 131 | the WordPress runtime plugins load against |
| [`Minn\Support`](support.md) | 35 | escaping, serialized readers, small helpers |
| [`Minn\Theme`](theme.md) | 41 | the block-theme reader, templates, global styles and the page renderer |
| [`Minn\Widgets`](widgets.md) | 2 |  |
