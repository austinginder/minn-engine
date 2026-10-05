# Security review, 2026-10-05

Two findings that surfaced while implementing `wp_kses_uri_attributes` for
the catalogue round. Both were confirmed by sending the same input to both
stacks before anything changed, and both are pinned by suites now.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | Medium | `Support\Kses` added the post table's global attributes (`class`, `id`, `style`, `title`, `role`, `dir`, `lang`, `hidden`, `tabindex`, any `aria-` or `data-` name) to every tag in the comment set too. An anonymous comment through `wp-comments-post.php` stored `<a style="position:fixed;width:100%;height:100%" class id role tabindex>`, a full-page link once the comment is approved; author profiles and term descriptions written through REST kept the same attributes. | The comment table (`data`) takes only the attributes it lists: `<a href title>` plus the `rel="nofollow ugc"` the comment filter adds. Posted the same comment to both stacks and read the rows. | `KsesPolicy::comment()` allows only listed attributes; `Kses::comment()` serves the comment, profile and term paths, `Kses::post()` the post paths, which are unchanged. The same anonymous comment now stores byte-identical content on both stacks. |
| 2 | Medium | The facade's `wp_kses()` flattened every allowlist to attribute names: value rules were dropped (so `<object data="javascript:…" type="text/html">` passed `wp_kses_post`), the caller's `$allowed_protocols` never reached the filter (a caller asking for `https` only still got `http:` and `mailto:` links), only six of the reference's seventeen URI attributes had their scheme judged (`form[action]`, `button[formaction]` and the rest kept `javascript:` in a plugin's own allowlist), and the post table's global attributes were added to any allowlist a plugin passed. | Probed rule by rule (`contracts/fixtures/api/kses-rules.json`): rules apply, a missing required attribute strips all of the tag's attributes, the PDF object rule accepts only an http(s) URL on the uploads host and port with a `.pdf` path and no query or fragment, a caller's allowlist is literal. | `KsesPolicy::fromAllowlist()` carries the caller's tags, rules, protocols and `wp_kses_uri_attributes()`; `KsesValues` judges the rules; `_wp_kses_allow_pdf_objects`, `wp_kses_check_attr_val` exist; `wp_kses_attr_check` applies rules. |

## Named and not fixed here

- A term description keeps a `rel` attribute its author wrote: the engine
  uses the comment set (which lists `rel` for profiles) where the reference
  uses the plain `data` table. An editor-only path; no script or style.
- `wp_kses_one_attr()` returns its input unchanged.

## Where the pins live

`tests/api.test.php` (fixture `kses-rules`, 24 rows), `tests/unit/kses.php`.
