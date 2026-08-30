# Contract: wp/v2/media

Status: implemented (list, single, upload over both transports, GD
sub-sizes, field edits, force delete including files). Suite:
`tests/media.test.php` (every upload is proven by reading it
back through real WordPress byte-identically — the serialized metadata
blob is the crux).

The uploads root is SHARED with the oracle: `wp-reference/wp-content/uploads`
is a symlink into `public/wp-content/uploads`, mirroring the shared
database. Static files are served by the web server directly.

## The object

View context: `{ id, date, date_gmt, guid{rendered}, modified,
modified_gmt, slug, status, type: attachment, link (?attachment_id=),
title{rendered}, author, featured_media: 0, comment_status (open on
upload), ping_status: closed, template, meta: [], class_list,
minn_attached_to, description{rendered}, caption{rendered}, alt_text,
media_type (image|file), mime_type, media_details, post (parent id or
null), source_url, filename, filesize, _links }`.

Edit context: raw+rendered duals, `permalink_template`, `generated_slug`,
`missing_image_sizes: []`, plus the plugin's image-editor facts
(`image_quality {default: 82, sizes: []}`, `exif_orientation: 1`,
`image_save_progressive: false`, `image_output_format: null`).
`filename`/`filesize` appear in BOTH contexts.

- `description.rendered` for images is the attachment-page HTML: a
  `<p class="attachment">` link around a lazy medium-size `<img>` with a
  srcset of the same-aspect sub-sizes in stored order plus the full image,
  and `sizes="auto, (max-width: Wpx) 100vw, Wpx"`. Empty when no medium
  size exists (small originals — recorded gap).
- `_links`: self (targetHints by edit capability), collection, about
  (types/attachment), author (embeddable, when author > 0), replies
  (embeddable), and in edit context the `wp:action-*` links —
  **`curies` appears ONLY when a `wp:*` link does** (view-context
  objects have none).
- `media_details.image_meta` mirrors the stored EXIF block: 13 keys, `alt`
  LAST (after `keywords`) — the stored `image_meta` is `a:13`, not 12.

## The metadata blob (`_wp_attachment_metadata`)

`a:6:{ width, height, file, filesize, sizes: a:N, image_meta: a:13 }`;
each size is `{ file, width, height, mime-type, filesize }` in that order.
The engine WRITES this exact serialized shape (deterministic construction)
and READS it with a tolerant byte scan — never `unserialize()`. Proof:
WordPress serves an engine upload's REST object byte-identical to the
engine's own create response, in both directions.

## Sub-sizes

The ladder comes from the OPTIONS WordPress stores (`thumbnail_size_*` +
`thumbnail_crop`, `medium_size_*`, `medium_large_size_*` (height 0 =
unconstrained), `large_size_*`), generated in the order medium, large,
thumbnail, medium_large — the order the stored sizes map carries. Fit
sizes use `round(dim * ratio)` (1200x800 → large 1024x683); a size equal
to the original is skipped; thumbnail center-crops and requires both
dimensions. JPEG/WebP quality 82. The 2560px `-scaled` threshold is a
recorded gap.

## Routes

- List: `post_status = inherit` attachments, newest first, `per_page`
  (default 10) / `page`, `include`, `author` / `author_exclude` (id lists),
  `parent` (id list; `0` is unattached), `media_type`
  (`image|video|text|application|audio`, else `400 rest_invalid_param`
  with `media_type[0] is not one of …`), `mime_type`, `search` (every
  word in title/excerpt/content), `after` / `before` (exclusive on
  site-local `post_date`; invalid is `400 rest_invalid_date`), `_fields`,
  `X-WP-Total` headers; `context=edit` needs `edit_posts`. Minn Admin's
  Mine / Unattached / type / month filters are these query args.
- Single: 404 `rest_post_invalid_id`; edit context needs `edit_post`.
- Create (`upload_files`; author qualifies): multipart field `file` (the
  Minn Admin app's transport) or a raw body with
  `Content-Disposition: attachment; filename=...`. Type is resolved by
  extension against a fixed map. Files land in `uploads/Y/m/` with `-N`
  collision suffixes. Images get sub-sizes + the metadata blob. 201 +
  `Location`, edit-context body. Anonymous → 401 `rest_cannot_create`
  ("…create posts as this user."); authenticated without the cap → 403
  ("…upload media on this site.").
- Update: `title`, `caption` (post_excerpt), `description` (post_content),
  `alt_text` (`_wp_attachment_image_alt`), `post` (parent); bumps
  modified stamps; gate `edit_post`.
- Delete: force required — without it `501 rest_trash_not_supported`;
  with it the row, its meta, the original file and every generated size
  are removed, response `{ deleted: true, previous }`.

## Known gaps

- The `-scaled` big-image threshold (>2560px) and EXIF rotation.
- Non-image attachments carry no parsed `media_details` (empty array vs
  core's object shapes for PDFs/audio).
- A malformed JSON body should 400 (`rest_invalid_json`) before auth, as
  core does.
- `minn_attached_to` is always null (parent identity payload not built).
- Real content sniffing (finfo) vs the extension map.
