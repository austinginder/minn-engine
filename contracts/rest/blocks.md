# wp/v2/blocks and wp/v2/wp_pattern_category

Synced patterns and reusable blocks (`wp_block` posts) and the taxonomy that
files them. Suite: `tests/reusable-blocks.test.php` (58), diffed request by
request against the oracle for an admin, an author and an anonymous caller,
with a write round trip WordPress reads back.

## Routes

`Rest\BlocksController` puts `wp/v2/blocks` (list, single, create, update,
delete) on the posts machinery the way `NavigationController` does for
`wp_navigation`; `RevisionsController` serves `blocks/{id}/revisions`,
`revisions/{rid}` and `autosaves` beside posts and pages; `TermsController`
and `TermObject` serve `wp_pattern_category` beside categories and tags.

## The block object

Read by editors only, so the shape is closer to editing than viewing:

- view: `id, date, date_gmt, guid{rendered}, modified, modified_gmt, slug, status, type, link, title{raw}, content{raw, protected}, excerpt{rendered, protected}, template (""), meta{footnotes}, wp_pattern_category (term ids), wp_pattern_sync_status, _links`
- edit adds `password` (before slug), `guid.raw`, `content.block_version`, `excerpt.raw`, then `minn_modified`, `minn_lock`, and moves `wp_pattern_sync_status` after them
- no author, no featured_media, no comment fields, no class_list, no rendered title or content
- `link` is the plain post permalink (`/{slug}/` under pretty permalinks), no type prefix, like a navigation menu
- `_links`: `self`, `collection`, `about`, `version-history`, `predecessor-version` (when any), `wp:attachment`, `wp:term` (one entry, `wp_pattern_category`), `curies`; edit context adds `wp:action-publish`, `wp:action-unfiltered-html`, `wp:action-create-wp_pattern_category` (edit_posts), `wp:action-assign-wp_pattern_category`

A draft stores `0000-00-00` for its gmt columns; the wire `date_gmt` and
`modified_gmt` are the local time converted through `gmt_offset`, the way
the reference derives them (`PostObject::gmt`, now used for every type).

## Access

| who | list | single (view) | single (edit) | create |
|---|---|---|---|---|
| anonymous | `[]`, total 0 | 401 `rest_forbidden` "Sorry, you are not allowed to do that." | 401 `rest_forbidden_context` "…edit this post." | 401 `rest_cannot_create` |
| author (edit_posts) | every block they may read | 200 | own: 200; another's: 403 `rest_forbidden_context` | 201 |
| admin | all | 200 | 200 | 201 |

An unknown id is `rest_post_invalid_id` 404 before any capability check. A
trashed block reads for whoever can `read_post` it (admins; anonymous gets
the same 401 as for a published one). Revisions and autosaves need
`edit_post` on the parent (`rest_cannot_read`, 401/403), the parent must be
a `wp_block` (`rest_post_invalid_parent` 404).

## Writes

`title`, `content`, `excerpt`, `status`, `slug`, `wp_pattern_category`
(term ids, replacing), `meta.wp_pattern_sync_status` (stored as post meta;
`""` stores an empty string), `meta.footnotes`. The reply is the edit-context
object. `DELETE` trashes (the reply is the trashed object, `status: trash`,
link `?p=`); `?force=true` removes it (`{deleted: true, previous: …}`).
Every changed save leaves a revision.

## Revisions

A block revision carries `title{raw, rendered}` and `content{raw, rendered}`
in **either** context (a pattern is edited, never viewed); `excerpt{rendered}`
gains `raw` only in edit context; `meta{wp_pattern_sync_status, footnotes}`
where the sync status is `null` (the revision has none of its own). The
`preview_link` field belongs to the autosave POST reply alone, never to a
revision, in any context; this also corrected the shared posts/pages
revision object.

## The taxonomy

`wp_pattern_category` is flat, public to read (anonymous 200 on list and
single), created by anyone with `edit_posts` (like tags), edited and deleted
with `manage_categories`. Edit context on a single term needs
`manage_categories` (`rest_forbidden_context` "Sorry, you are not allowed to
edit this term.", 401/403), a rule that holds for categories and tags too and
now does on the engine. A term's `link` is `/?taxonomy=wp_pattern_category&term={slug}`
whatever the permalink setting (`Permalinks::QUERY_ONLY`); `wp:post_type`
points at `/wp/v2/blocks?wp_pattern_category={id}`.

The list filters `wp_pattern_category` and `wp_pattern_category_exclude`
(and with them `categories`/`tags` on posts, which had no engine filter
before) are `ListQuery::terms`/`termsExclude`, applied as `ID IN (SELECT …)`
clauses in `PostsController::serveList`.

## Honest gaps

- An author reading **another user's trashed** block (or post) gets 200 on the engine and 403 on the reference: the shared `read_post` mapping resolves a trashed post to the status it was trashed from and treats "trashed from publish" as readable. Not touched here; the same divergence exists for posts.
- `types/wp_block?context=edit` lacks `capabilities`, `visibility`, `viewable`, `labels`, `supports`; the types edit-context gap is the same for every type.
- Same-second ordering ties under `orderby=date` are storage order on both stacks and are not asserted.
