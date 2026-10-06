# Minn API

The engine's own classes under `public/minn/src/Minn/`, one page per namespace, read from the code by `php tests/tools/api-docs.php`. Signatures are reflection, prose is the docblocks. Regenerate after any change to `src/Minn/`; the style suite fails when this folder is stale. The WordPress-facing facade (`wp-api/`) is not here: its map is `contracts/api/mappings.json`.

| Namespace | Classes | What lives there |
|---|---|---|
| [`Minn`](minn.md) | 6 | the front door, the autoloader, the one database door, the REST error |
| [`Minn\Admin`](admin.md) | 27 | the minn-admin/v1 namespace and serving the Minn Admin app |
| [`Minn\Auth`](auth.md) | 19 | passwords, sessions, cookies, nonces, roles and capabilities |
| [`Minn\Blocks`](blocks.md) | 16 | the block parser and renderer |
| [`Minn\Blocks\Dynamic`](blocks-dynamic.md) | 9 | dynamic core blocks that render from data |
| [`Minn\Blocks\Dynamic\Theme`](blocks-dynamic-theme.md) | 5 | the template blocks a block theme composes with |
| [`Minn\Cli`](cli.md) | 16 | the wp verbs the engine answers itself |
| [`Minn\Content`](content.md) | 33 | the repositories and records: posts, users, terms, comments, and the render pipeline |
| [`Minn\Cron`](cron.md) | 1 | scheduled publishing |
| [`Minn\Extension`](extension.md) | 8 | the extension contract and its seams |
| [`Minn\Feed`](feed.md) | 8 |  |
| [`Minn\Front`](front.md) | 31 | URL resolution, permalinks, feeds, sitemaps and the public page |
| [`Minn\Html`](html.md) | 6 | the HTML tag processor |
| [`Minn\Html\Tree`](html-tree.md) | 13 |  |
| [`Minn\Http`](http.md) | 29 | request, response, routing, and the outgoing client |
| [`Minn\I18n`](i18n.md) | 9 |  |
| [`Minn\Login`](login.md) | 4 | /wp-login.php and the sign-in surface |
| [`Minn\Mail`](mail.md) | 23 | sending mail and the notices the engine sends |
| [`Minn\Media`](media.md) | 9 | uploads, image sizes and attachment metadata |
| [`Minn\Ops`](ops.md) | 6 |  |
| [`Minn\Query`](query.md) | 4 | shared SQL fragments |
| [`Minn\Rest`](rest.md) | 58 | the wp/v2 surface: shapes and controllers |
| [`Minn\Runtime`](runtime.md) | 57 | the WordPress runtime plugins load against |
| [`Minn\Support`](support.md) | 24 | escaping, serialized readers, small helpers |
| [`Minn\Theme`](theme.md) | 24 | the block-theme reader, templates, global styles and the page renderer |
