# Security review, 2026-10-05

Two findings that surfaced while implementing `wp_kses_uri_attributes` for
the catalogue round. Both were confirmed by sending the same input to both
stacks before anything changed, and both are pinned by suites now.

## Findings and what was done

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 1 | Medium | `Support\Kses` added the post table's global attributes (`class`, `id`, `style`, `title`, `role`, `dir`, `lang`, `hidden`, `tabindex`, any `aria-` or `data-` name) to every tag in the comment set too. An anonymous comment through `wp-comments-post.php` stored `<a style="position:fixed;width:100%;height:100%" class id role tabindex>`, a full-page link once the comment is approved; author profiles and term descriptions written through REST kept the same attributes. | The comment table (`data`) takes only the attributes it lists: `<a href title>` plus the `rel="nofollow ugc"` the comment filter adds. Posted the same comment to both stacks and read the rows. | `KsesPolicy::comment()` allows only listed attributes; `Kses::comment()` serves the comment, profile and term paths, `Kses::post()` the post paths, which are unchanged. The same anonymous comment now stores byte-identical content on both stacks. |
| 2 | Medium | The facade's `wp_kses()` flattened every allowlist to attribute names: value rules were dropped (so `<object data="javascript:…" type="text/html">` passed `wp_kses_post`), the caller's `$allowed_protocols` never reached the filter (a caller asking for `https` only still got `http:` and `mailto:` links), only six of the reference's seventeen URI attributes had their scheme judged (`form[action]`, `button[formaction]` and the rest kept `javascript:` in a plugin's own allowlist), and the post table's global attributes were added to any allowlist a plugin passed. | Probed rule by rule (`contracts/fixtures/api/kses-rules.json`): rules apply, a missing required attribute strips all of the tag's attributes, the PDF object rule accepts only an http(s) URL on the uploads host and port with a `.pdf` path and no query or fragment, a caller's allowlist is literal. | `KsesPolicy::fromAllowlist()` carries the caller's tags, rules, protocols and `wp_kses_uri_attributes()`; `KsesValues` judges the rules; `_wp_kses_allow_pdf_objects`, `wp_kses_check_attr_val` exist; `wp_kses_attr_check` applies rules. |

## Second round: the tag pass and block attributes

Found while sanitizing parsed feeds through kses: the same input sent to
both stacks, 259 cases (`contracts/fixtures/api/kses-split.json`).

| # | Severity | Finding | Reference | Done |
|---|---|---|---|---|
| 3 | Medium | Block delimiter attributes were never filtered: `wp_pre_kses_block_attributes` was not registered on `pre_kses`, and the REST write paths called the tag pass alone. An author without `unfiltered_html` could store `<!-- wp:x/y {"html":"\u003cimg src=x onerror=alert(1)\u003e"} /-->` and any block or plugin rendering that attribute raw would run it. | Every attribute key and string value goes through the same `wp_kses` call and the delimiter is written back (`{"b":"\u003cimg src=\u0022x\u0022\u003e"}`). | `Kses::blockAttributes()` over `Blocks\Parser` and the new `Blocks\Serializer`; `wp_pre_kses_block_attributes` registered after `wp_pre_kses_less_than`; `Kses::sanitize()` runs the whole default pass, and the REST post, revision and media paths use it through `Kses::post()`. |
| 4 | Low | The REST post paths judged attributes against a hand-written table that allowed `img[srcset]`, `img[sizes]`, `source`, `picture` and any `aria-` name, where the reference's post table allows none of them. | `wp_kses_allowed_html('post')` as captured in `data/kses.json`. | `KsesPolicy::post()` is built from `data/kses.json`. |
| 5 | Low | The tag pass differed from the reference in ways that changed stored bytes: tag-name case, the first-wins rule for duplicate attributes, attributes after junk or without a separating space, a quoted value cut by `>` (the reference drops every attribute; the engine kept a value holding `>`), comments with `--` in their body, inert bogus comments, and pseudo-tags such as `< 6 & 7 >`. None let script through on the engine, which escaped more than the reference. | The rules in contracts/runtime.md, "The kses tag pass". | `Kses::filter()` and `Kses::attributeList()` rewritten from the captures; `wp_kses_hair` reads attributes the same way. |

## Named and not fixed here

- A term description keeps a `rel` attribute its author wrote: the engine
  uses the comment set (which lists `rel` for profiles) where the reference
  uses the plain `data` table. An editor-only path; no script or style.
- `wp_kses_one_attr()` returns its input unchanged.

## Where the pins live

`tests/api.test.php` (fixtures `kses-rules`, 24 rows, and `kses-split`,
259 cases), `tests/unit/kses.php`.
