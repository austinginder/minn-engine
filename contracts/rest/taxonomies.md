# wp/v2/taxonomies

The registry the reference exposes, seeded once from its edit-context
payload into `data/taxonomies.json` (category, post_tag, nav_menu,
wp_pattern_category) and served by `Minn\Rest\Taxonomies` (suite
`admin-surfaces`, section 2, at live parity).

- `GET /wp/v2/taxonomies` is a whole-payload map keyed by slug (so
  `_fields` filters the map, like types). `?type=post` keeps the taxonomies
  attached to that type.
- View context (anonymous) carries `name, slug, description, types,
  hierarchical, rest_base, rest_namespace, _links`. `?context=edit` needs a
  signed-in caller and adds `capabilities, labels, show_cloud, visibility`;
  a taxonomy the caller cannot `manage_terms` on is left out of the map (or
  refused with `rest_forbidden_context` on the single route).
- `GET /wp/v2/taxonomies/{slug}` is the single; unknown → 404
  `rest_taxonomy_invalid`.
- The capability names the reference maps (`edit_categories`,
  `delete_categories`, `manage_post_tags`, `edit_post_tags`,
  `delete_post_tags` → `manage_categories`; `assign_*` → `edit_posts`) are
  in `Capabilities::map`.
- Site-declared taxonomies (a `minn.json` `taxonomies` list) are not
  registered yet; only the core set is served.
