# wp/v2/navigation

The block theme's navigation menus, stored as `wp_navigation` posts. This is
what Minn Admin's Design > Navigation screen lists, opens, renames and
deletes. Suite: `tests/navigation.test.php` (39), diffed request by request
against the oracle and re-checked on the dogfood site's four real menus.

## A menu is a post with almost nothing on it

The reference registers `wp_navigation` with the post machinery, so the
routes, the query arguments and the write lifecycle are the ones
`wp/v2/posts` has. The payload is much thinner:

```
id, date, date_gmt, guid, modified, modified_gmt, slug, status, type, link,
title, content, template, _links
```

No author, no excerpt, no featured media, no comment or ping status, no meta,
no taxonomies, and therefore no `class_list`. Edit context adds the raw halves
of `guid`, `title` and `content`, `content.block_version`, `password` before
`slug`, and Minn Admin's `minn_modified` / `minn_lock`; it does **not** add
`permalink_template` or `generated_slug`.

`_links` carries self, collection, about, `version-history`,
`predecessor-version` when a revision exists, `wp:attachment` and `curies`.
There is no `author` link even when `post_author` is set, and no `replies` or
`wp:term`. Edit context adds `wp:action-publish` and
`wp:action-unfiltered-html` for a caller who holds them, and nothing else: a
menu has no author to assign and no taxonomies to create terms in.

**`link` is the plain post permalink**, structure and all. On a site running
`/%postname%/` a menu links to `/navigation/`; on one running
`/%category%/%postname%/` the same menu links to `/uncategorized/navigation/`.
A menu with no slug yet (a draft) links to `?p={id}`, like any other post.

## Gates

`wp_navigation` folds its whole capability family onto `edit_theme_options`
(`Minn\Auth\TypeCapabilities`), and `read_post` still maps to `read`. Reading
is public; changing anything is an administrator's business.

| caller | list / read | edit context | create / update / delete |
|---|---|---|---|
| signed out | 200 | 401 `rest_forbidden_context` | 401 `rest_cannot_create` / `rest_cannot_edit` |
| subscriber, author, editor | 200 | 403 `rest_forbidden_context` | 403 |
| `edit_theme_options` | 200 | 200 | 200 (201 on create) |

The edit-context refusal reads "Sorry, you are not allowed to edit posts in
this post type", the same sentence posts use.

## The rendered menu

`content.rendered` is the menu's blocks rendered, which for most menus means
`core/page-list` or a list of `core/navigation-link` blocks. Two facts the
oracle settled here:

- **A page list dresses its items as navigation items only inside a
  navigation block.** On its own, which is how this route renders one, the
  reference emits plain `<li class="wp-block-pages-list__item …">` with a bare
  `wp-block-navigation__submenu-container` and no submenu toggle, no
  `wp-block-navigation-item` classes, and no interactivity directives.
  `RenderState::enterNavigation()` marks the span the navigation block owns so
  `Blocks\Dynamic\Theme\Navigation` can tell the two apart.
- The item class ends with a separator whether or not anything follows it:
  a standalone leaf is `class="wp-block-pages-list__item "`, and one inside a
  navigation whose block sets no colours is
  `class="wp-block-pages-list__item wp-block-navigation-item open-on-hover-click "`.

The navigation blocks are registered on the content renderer
(`Blocks\Renderer::forDb`), not only on the theme's page renderer, because
this route renders a menu with no page around it.

## What the screen needs besides this route

- `minn-admin/v1/navigation/usage` is the plugin's own route and answers
  through the runtime. It calls `get_block_templates()` to find which template
  parts reference each menu, so two engine-side facts had to be true first:
  the facade's `get_block_templates()` reads **saved rows as well as theme
  files** (it now goes through `Minn\Theme\TemplateIndex`, the same index
  `wp/v2/templates` serves, and returns the markup as stored so a caller can
  resolve patterns itself), and the active theme is in the runtime container
  on the REST path (`Engine::bootRuntimeForRest` sets `theme`, which it never
  did before; without it every template read over REST came back empty).
- `resolve_pattern_blocks()` is not provided, so a menu referenced only from
  inside a theme pattern is not yet counted as used. The plugin guards the
  call with `function_exists`, so the list still renders.

## Fixed along the way

`_links.self.targetHints.allow` is now computed from the caller's
capabilities in **every** context, not only in edit context. The reference
does that for every post type; the engine used to answer a flat `["GET"]` in
view context even to an administrator.
