# Contract: term management (wp/v2/categories, wp/v2/tags)

Status: implemented (list parameters, create, update, force delete).
Suite: `tests/terms.test.php` (19 checks, cross-stack both directions).
Read-shape basics live in `contracts/rest/posts.md`; this covers the
management surface the editor and taxonomy views drive.

## List parameters

`orderby` (name default | count | id | slug), `order` (asc default),
`search` (name substring), `include`, `per_page`/`page` with the
`X-WP-Total` headers, `_fields`.

## Capability model (oracle-pinned)

- **Tags may be created by `edit_posts` holders** — the editor's
  type-a-new-tag flow works for authors. Categories need
  `manage_categories`.
- Update and delete need `manage_categories` for both taxonomies.
- `targetHints.allow`: GET, plus the write verbs for `manage_categories`
  holders — except DELETE is withheld on the DEFAULT category (its row
  shows 4 verbs even to an administrator).
- Deleting the default category: `403 rest_cannot_delete` (the
  capability refusal, BEFORE the force check).

## Create

`name` required (aggregated missing-param error). Slug from the name
(or given), unique within the taxonomy (-2, -3 …). Categories accept
`parent` and `description`. 201 + Location, the term object (a
parented category carries the embeddable `up` link).

**Duplicate name**: `400 term_exists`, `data.term_id` = the existing id,
plus core's odd top-level `additional_data: [id, id]` — reproduced
verbatim.

## Update / delete

Update: name, slug, description, parent; returns the fresh object.
Delete requires force (`501 rest_trash_not_supported`); children are
reparented to the deleted term's parent, relationships are detached,
response `{ deleted: true, previous }`.

## Known gaps

- No `hide_empty`, `post` (terms-of-a-post) or `parent` list filters.
- `count` on the deleted term's former posts is not recounted here (the
  write path's recount covers assignment changes; a delete leaves
  detached posts uncounted exactly as observed so far — revisit with a
  counted-term delete capture).
