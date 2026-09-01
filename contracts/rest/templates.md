# wp/v2/templates and wp/v2/template-parts

The block theme's templates and parts, as one list per type. This is what
Minn Admin's Design > Templates screen loads, and what its editor opens when
a row is clicked. Suite: `tests/templates.test.php` (45), diffed request by
request against the oracle and re-checked on the dogfood site's child theme.

## The resource

An id is `<theme>//<slug>`, and `<theme>` is always the active stylesheet,
even for a template a plugin registered. Three things can provide one:

| source | where it comes from | wp_id | has_theme_file |
|---|---|---|---|
| `theme` | a `.html` file in the theme's `templates/` or `parts/` | 0 | true |
| `custom` | a `wp_template` / `wp_template_part` post carrying the `wp_theme` term for this theme | the post id | whether a file of that slug also exists |
| `plugin` | `register_block_template()` at runtime | 0 | false |

A saved row shadows the file of the same slug, and `has_theme_file` is what
tells the app it can be reset rather than deleted.

**Order.** Saved rows first, newest `post_date` first (ties by descending id);
then the theme's files **in the order the directory hands them back, unsorted**;
then anything a plugin registered. The unsorted directory order is not a
convenience, it is what the reference does: `scandir(..., SCANDIR_SORT_NONE)`
reproduces its list exactly.

**Derived fields.**

- `is_custom` (templates only, absent on parts) is true for a slug the
  reference does not name in its default template types (`data/template-types.json`,
  captured from `get_default_block_template_types()`).
- `original_source` is `theme` when a file backs it, else `plugin`, else `user`
  when the row has an author, else `site`.
- `author_text` follows it: the theme's own name (its `Theme Name` header, the
  child's for a child theme), the plugin's prefix, the author's display name,
  or the site's `blogname`.
- A **theme file's** title comes from the default template types, then
  theme.json's `customTemplates` (or `templateParts` for a part), and a slug
  neither knows about is its own title **verbatim, not prettified**
  (`single-book_review` stays `single-book_review`). Its description comes from
  the default types or is empty.
- A **saved row's** title and description are the post's own `post_title` and
  `post_excerpt`, with no fallback: a row saved with an empty title serves an
  empty title.
- `content.block_version` (edit context only) is 1 once a block delimiter
  appears in the markup, 0 for markup that is only HTML.
- `minn_modified` is always false and `minn_lock` always null in edit context:
  a template takes no lock and has no autosave slot.

## Two things happen to the markup on the way out

Both apply to files and to saved rows, and to templates and parts alike.

1. **Every `wp:template-part` block is told which theme it belongs to.** The
   attribute is appended last (`{"slug":"header"}` becomes
   `{"slug":"header","theme":"twentytwentyfive"}`), a block that declares one
   already is left alone, and a bare `<!-- wp:template-part /-->` gains
   `{"theme":"..."}`. `Minn\Theme\TemplatePartTheme` appends the pair textually
   so no other byte of the markup moves.
2. **Every `wp:pattern` block is replaced by the pattern's own markup**, trimmed,
   recursively. A slug the theme does not know is left exactly as it is, which
   is how an editor sees that a pattern went missing rather than the block.
   `Minn\Theme\TemplatePatterns` does this before the theme attribute goes on,
   so a pattern that contains template parts still gets them stamped.

**The pattern name rides along only when the pattern is one block.** A pattern
whose content is a single top-level block gets
`metadata: {patternName, name, description?, categories?}` appended to that
block's attributes, built from the pattern file's `Slug`, `Title`,
`Description` and `Categories` headers (the last two only when the header has
them). A pattern with several top-level blocks has no single wrapper to carry
the name and gets none: `twentytwentyfive/hidden-written-by` (one group) is
stamped, `twentytwentyfive/hidden-sidebar` (heading + spacer + query) is not.
The stamp goes on the pattern's raw markup before nested patterns are spliced
in, so when that one block is itself a pattern reference the stamp goes away
with it (`twentytwentyfive/page-shop-home` produces three stamps, not four).

## Gates

Reading is not an administrator's privilege. The reference lets anyone with
`edit_posts` list and read templates, so an editor and an author both see the
layout; a subscriber does not.

| caller | list / read | write / delete |
|---|---|---|
| signed out | 401 `rest_cannot_manage_templates` | 401 |
| subscriber | 403 `rest_cannot_manage_templates` | 403 |
| author, editor | 200 | 403 `rest_cannot_manage_templates` |
| `edit_theme_options` | 200 | 200 |

The message is the same for both types: "Sorry, you are not allowed to access
the templates on this site." A caller without the write capability gets a self
link that allows `GET` only, and no `wp:action-*` links and no `curies` block.
The write check runs before the id is looked up, so a caller who cannot write
gets 403 rather than 404 for a template that does not exist.

Neither list paginates, so neither carries `X-WP-Total` / `X-WP-TotalPages`.

## Writes

- `POST /wp/v2/{base}/{id}` writes the site's own copy, creating the
  `wp_template` row the first time and recording `origin = theme` in post meta
  when the slug has a theme file behind it. `title` and `content` may arrive
  bare or as the `raw` member of their object.
- `DELETE` without `force` moves that copy to the trash, which is already
  enough to hand the theme's file back.
- `DELETE ?force=true` removes the row for good. This is what Minn Admin's
  "Reset to theme" and "Delete" both send.
- A template that exists only as a theme file has nothing to remove:
  400 `rest_invalid_template`, "Templates based on theme files can't be removed."
- An unknown id, or one naming another theme, is 404 `rest_template_not_found`,
  "No templates exist with that id."

## Divergences, stated

- **Trashed rows.** The engine serves published rows only, in the list and in
  the single lookup. The reference's list agrees, but its **single** lookup
  finds a trashed row and serves it as the live template, so a template deleted
  without force reappears when fetched by id. The engine does not reproduce
  that: opening a trashed template in an editor is worse than a missing one.
- **Revisions.** `version-history` counts and the `predecessor-version` link
  are read from the posts table, so a site whose templates were saved through
  WordPress reports them correctly. The engine does not yet **write** a
  revision when it saves a template, and `/wp/v2/templates/{id}/revisions` is
  not served (the greedy id capture answers it 404).
- **A pattern the reference failed to register.** On the dogfood site the
  reference leaves `twentytwentythree/post-meta` unexpanded while the engine
  splices it in. The cause is on the reference's side: a plugin there has left
  the pattern-category registry keyed by integers, and three of the parent
  theme's patterns (the ones that declare `Categories`) never register. The
  engine reads the theme's pattern files directly and has no such hole. Every
  other template and part on that site matches byte for byte.

## The boot payload

`site.stylesheet` and `site.template` are part of this contract even though
they are not part of this route. A template id's owner is compared against
them to tell the theme's own templates from a plugin's, so without them every
customized template reads as "From a plugin" in the app.
