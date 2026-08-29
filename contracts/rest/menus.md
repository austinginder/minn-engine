# Contract: wp/v2/menus, menu-items, menu-locations

Status: reads and writes implemented. Suite: `tests/menus.test.php` (47
checks: live read parity plus create/rename/reorder/delete round trips
both directions).

Classic `nav_menu` terms and `nav_menu_item` posts. Block themes keep
`wp_navigation` posts as the primary front-end store; these routes are
what Minn Admin's Menus view calls.

## Auth

Unauthenticated GET is 401 `rest_cannot_view` ("Sorry, you are not allowed
to view menus." / "menu items" / "menu locations"). A signed-in caller
without `edit_posts` is 403 with the same code (a subscriber). Authors
can GET; `targetHints.allow` is `["GET"]` for them and the full
GET/POST/PUT/PATCH/DELETE list for `edit_theme_options`.

## menus

A `nav_menu` term: `{ id, description, name, slug, meta: [], locations,
auto_add, _links }`. `auto_add` is true when the term id is in the
`nav_menu_options` option's `auto_add` list. `locations` are slugs from
the active theme's `theme_mods_{stylesheet}.nav_menu_locations` that
point at this menu. Missing id is 404 `rest_term_invalid` "Term does not
exist."

`?post={itemId}` lists the menus that contain that item.

Writes need `edit_theme_options`. POST `/wp/v2/menus` with `{name}` is 201
and a Location header; missing name is `rest_missing_callback_param`, an
empty name is `empty_term_name`, a duplicate is `menu_exists` 400 with
`data.term_id` and `additional_data: [id]` (the message wraps the name in
`<strong>`). POST/PUT/PATCH `/wp/v2/menus/{id}` renames (and reslugs) and
can set `description`. DELETE without `force=true` is 501
`rest_trash_not_supported` "Menus do not support trashing..."; with force
it returns `{deleted: true, previous}` (view object, no `_links`) and
deletes the menu's items too. Authors get 403 `rest_cannot_create`
"Sorry, you are not allowed to create terms in this taxonomy."

## menu-items

A `nav_menu_item` post with `_menu_item_*` meta resolved: `{ id, title:
{ rendered } (edit adds raw), status, url, attr_title, description, type,
type_label, object, object_id, parent, menu_order, target, classes, xfn,
invalid, meta: [], menus, _links }`. Edit context also carries
`minn_modified` / `minn_lock` (the Minn Admin plugin is on the oracle).

- `type` is `post_type`, `custom`, or `taxonomy`.
- `type_label` is `Page`, `Post`, `Category`, `Tag`, or `Custom Link`.
- `url` is the object's permalink (or the stored custom URL).
- `title` is the item's post_title, else the object's title, else the custom URL.
- `classes` / `xfn` are lists; an empty stored value is `[""]`.
- `invalid` is true when the pointed-at post or term is missing or in trash.
- `?menus={termId}` lists one menu's items.
- Missing id is 404 `rest_post_invalid_id` "Invalid post ID."

Writes need `edit_theme_options`. POST `/wp/v2/menu-items` is 201 in edit
context (title.raw, minn_modified/minn_lock, action links). A custom item
needs a title (`rest_title_required` otherwise). `title` may be a string
or `{raw}`. Default `type` is `custom`, default `menu_order` is 1, default
`status` is `publish`. `menus` attaches the item to a `nav_menu` term;
omitting it leaves an orphan. POST/PUT/PATCH `{id}` updates title, url,
parent, menu_order, and the other stored fields. Authors get 403
`rest_cannot_create` "Sorry, you are not allowed to create posts as this
user." DELETE without force is 501 "Menu items do not support trashing...";
with force `{deleted: true, previous}` in view context without `_links`.

## menu-locations

Block themes register no classic locations. The reference returns `[]`
with no pagination headers. The engine matches that.

## Front

A `core/navigation` block with no inner blocks uses the referenced
`wp_navigation` post, else the newest published one, else the first
classic `nav_menu`. Classic items render as `core/navigation-link` with
`className` ` menu-item menu-item-type-{type} menu-item-object-{object}`
and an empty `title=""` on the anchor, matching the reference. Proven on
the engine site's header with `wp_navigation` drafted.
