# wp/v2/global-styles

The site editor's saved styles and the active theme's own, as the reference
serves them. Suite: `tests/global-styles.test.php` (fixtures without the
oracle, then request-by-request parity for an admin, an editor, an author
and an anonymous caller, plus a write round trip the oracle reads back).
Unit: `tests/unit/style-settings.php`.

## Routes

| route | methods | who |
|---|---|---|
| `/wp/v2/global-styles/{id}` | GET, POST, PUT, PATCH | read: anyone who can `edit_posts`; edit context and writes: `edit_theme_options` |
| `/wp/v2/global-styles/themes/{stylesheet}` | GET | `edit_posts`; only the active stylesheet answers |
| `/wp/v2/global-styles/themes/{stylesheet}/variations` | GET | same |
| `/wp/v2/global-styles/{parent}/revisions` | GET | `edit_theme_options` |
| `/wp/v2/global-styles/{parent}/revisions/{id}` | GET | `edit_theme_options` |

There is **no collection, no create, no delete**: `GET /global-styles`,
`POST /global-styles` and `DELETE /global-styles/{id}` are all
`rest_no_route`. The post is made on first use (see below).

## The resource

One `wp_global_styles` post per theme, published, titled "Custom Styles",
named `wp-global-styles-{stylesheet}` (suffixed when taken), tied to the
theme by its `wp_theme` term, discussion closed, body
`{"version": 3, "isGlobalStylesUserThemeJSON": true }` on creation. The
finder is the newest published post carrying the term; a post without
the term is invisible to it, which is why creating one under wp-cli
without a user (the term assignment is dropped for user 0) leaves the
reference making a second post on its next request. `Theme\UserStyles`
owns find, ensure, decode and save.

**Item** (`{id}`): `id`, `title{raw, rendered}` (rendered is texturized,
never kses-filtered), `settings`, `styles`, in editing context the app's
`minn_modified: false` and `minn_lock: null`, then `_links`:

- `self` with `targetHints.allow` `["GET"]`, or `["GET","POST","PUT","PATCH"]` for `edit_theme_options`
- `about` → `/wp/v2/types/wp_global_styles`
- `version-history` with `count` (real revisions) and the revisions href
- `wp:action-publish` for everyone who can read
- `wp:action-edit-css` when the caller has `edit_css` (single site: `unfiltered_html`, so editors too)
- `curies`

An empty `settings` or `styles` is `{}` on the wire; a list-shaped node in
the stored body reads as empty.

**Settings normalization** (`Theme\StyleSettings`), applied to the saved
settings, to the theme's and to each variation's:

- preset lists (`color.palette/gradients/duotone`, `typography.fontSizes/fontFamilies`,
  `spacing.spacingSizes`, `shadow.presets`, `dimensions.aspectRatios/dimensionSizes`, at the root
  and under each `blocks.{name}`) are keyed by origin: `theme` for a theme file, `custom` for
  the site editor's post, `default` for the engine's defaults
- `appearanceTools: true` is removed and spelled out, group by group in the order
  `background, border, color, dimensions, position, spacing, typography`; a group already
  present keeps its place and gains its flags at the end (`background.backgroundImage/backgroundSize/gradient`,
  `border.color/radius/style/width`, `color.link/heading/button/caption`,
  `dimensions.aspectRatio/height/minHeight/minWidth/width`, `position.sticky`,
  `spacing.blockGap/margin/padding`, `typography.lineHeight/textColumns`). A flag the node
  sets itself keeps its value.

**Styles**: every `var:preset|kind|slug` token resolves to
`var(--wp--preset--kind--slug)` on the way out; the stored body keeps the
tokens as written.

## Writes

`POST`, `PUT` and `PATCH` are the same operation: `title` (a string or
`{raw}`; anything else ignored), `settings`, `styles`. A node the body
leaves out, or sends as `null`, keeps its stored value; one it names is
replaced whole (there is no deep merge). A scalar `settings` or `styles`
is `rest_invalid_param` 400 with `details.{name}.code = rest_invalid_type`
("{name} is not of type object."). The stored body is
`{"styles":…,"settings":…,"isGlobalStylesUserThemeJSON":true,"version":3}`
(json_encode defaults: `/` escaped, an empty node as `[]`). The reply is
the item in editing context. Every save that changes the title or the
body leaves one revision; an unchanged save leaves none.

## The theme routes

`themes/{stylesheet}` answers `{settings, styles, _links.self}` for the
active stylesheet only; any other installed theme is `rest_theme_not_found`
404 (checked after the capability). The body is the engine's defaults,
`data/global-styles.json`, captured from the reference under a block theme
with no theme.json at all, with the theme's own theme.json merged over them
(maps key by key, lists replace) after normalization. Two facts the merge
alone does not give:

- a theme.json switches `shadow.defaultPresets` on unless it sets it
- block style partials (`styles/**/*.json` with `blockTypes`) land as
  `styles.blocks.{type}.variations.{slug}`, and a root `styles.variations.{slug}`
  (section styles inside theme.json or a variation) is distributed to every block
  type the partial of that slug names, the blocks it has to add in name order

`variations` is every JSON file under `styles/` that names no `blockTypes`,
parent theme's first, in path order, `$schema` dropped, `settings` and
`styles` normalized like the theme's. Under a theme without theme.json the
theme route is the defaults alone and the variations list is empty.

## Revisions

`{parent}/revisions` lists the real revisions newest first (date, then id
descending). Without `per_page` every row comes back and `X-WP-TotalPages`
is 1; with it, pages, and a page past the end is
`rest_revision_invalid_page_number` 400. A row is `settings` and `styles`
(both present whenever the stored body holds anything under either, both
absent when it is blank), then `author`, `date`, `date_gmt`, `id`,
`modified`, `modified_gmt`, `parent`; no `_links`, no `title`, no
`content`. Gates, in order: signed in (`rest_cannot_read` 401), the parent
is a global-styles post (`rest_post_invalid_parent` 404), the caller may
edit the theme (`rest_cannot_read` 403). `revisions/{id}` for a revision of
another post or none is `rest_post_invalid_id` "Invalid revision ID." 404.

## Refusals

| case | code | status |
|---|---|---|
| unknown id, or an id of another post type (checked first, even anonymous) | `rest_global_styles_not_found` "No global styles config exists with that ID." | 404 |
| editing context without `edit_theme_options` | `rest_forbidden_context` "Sorry, you are not allowed to edit this global style." | 401 anonymous, 403 signed in |
| read without `edit_posts` (subscriber, anonymous) | `rest_cannot_view` "Sorry, you are not allowed to view this global style." | 401 / 403 |
| write without `edit_theme_options` | `rest_cannot_edit` "Sorry, you are not allowed to edit this global style." | 401 / 403 |
| theme routes without `edit_posts` (checked before the stylesheet) | `rest_cannot_read_global_styles` "Sorry, you are not allowed to access the global styles on this site." | 401 / 403 |
| theme routes for any other stylesheet | `rest_theme_not_found` "Theme not found." | 404 |

Authors (no `edit_theme_options`) read the item and the theme routes but
not the revisions, and their `self` link allows GET only.

## Honest gaps

- `appearanceTools` under `settings.blocks.{name}` is not expanded (only the root flag was captured).
- A root `styles.variations` inside the site editor's own post is returned as written, not distributed.
- Only the observed per-request expansion list is applied; the reference may switch on further flags for future settings.
- The revision "first save also snapshots the previous state" case seen once on a post created without a user was not reproduced and is not modelled: one revision per changed save.
