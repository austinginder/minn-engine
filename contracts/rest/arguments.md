# Arguments before the caller

The reference judges a request's declared arguments before it looks at
who is asking, and before it looks the record up. The engine does the
same: `Http\Router` runs `Rest\ArgCheck` on a matched route before the
policy gate, so an invalid parameter is a 400 for an anonymous caller and
an administrator alike, and a missing post with a bad `context` is a 400,
not a 404. Every fact below was captured from the running reference
(`tests/settings.test.php`, `tests/unit/arg-check.php`, and the probe
transcripts of 2026-09-02).

## The order

1. **A JSON body that does not parse** is refused on every route, GET
   included, before anything else: `rest_invalid_json`, "Invalid JSON body
   passed.", 400, with `json_error_code` and `json_error_message` beside
   the status. Only a body sent as `application/json` counts; a body under
   another content type, an empty body, and a body that parses to a scalar
   (`null`, `"str"`) are not errors.
2. **A required argument that did not arrive** is
   `rest_missing_callback_param`, "Missing parameter(s): a, b", 400, with
   `params` as the list of names. Presence is the test, so `{"username":
   ""}` is present. This is why an anonymous `POST /wp/v2/users {}` is a
   400 and not a 401.
3. **The shared collection parameters** (`context`, `page`, `per_page`,
   `search`; `Args::SHARED`) are judged first, and a refusal among them is
   answered alone: `?per_page=x&orderby=bogus` names only `per_page`.
4. **The route's own parameters** are judged together once the shared ones
   pass, listed in the order the route declares them (which is the
   reference's registration order): `?orderby=bogus&include=a&order=up`
   reads "Invalid parameter(s): include, order, orderby".
5. Then the policy, then the handler.

The refusal is `rest_invalid_param`, "Invalid parameter(s): a, b", 400,
with `params` (name => message) and `details` (name => `{code, message,
data}`, the schema's own refusal: `rest_invalid_type` carries
`{"param": name}`, `rest_not_in_enum` and `rest_out_of_bounds` carry
`null`, `rest_invalid_email` and `rest_invalid_date` carry `null`).
An array item names its index: `include[1] is not of type integer.` An
enum of three or more options takes the serial comma ("view, embed, and
edit"); two options read "asc and desc".

## What is validated

A route declares the parameters it reads on its attribute (`args:` for the
query, `body:` for the JSON body), each set captured from the reference's
index and named after the code that consumes it (`Minn\Http\Args`,
`Rest\Settings::SCHEMA`). Only a declared argument is judged; a value the
handler never reads is neither published nor refused. What is declared:

| Route | Query set | Body set |
|---|---|---|
| `GET /wp/v2/posts` | `CONTEXT`, `POSTS` | |
| `GET /wp/v2/pages` | `CONTEXT`, `PAGES` (adds `menu_order`, `parent`, `orderby=menu_order`) | |
| `GET /wp/v2/media` | `CONTEXT`, `MEDIA` (`media_type`, `mime_type`, `after`, `before`) | |
| `GET /wp/v2/users` | `CONTEXT`, `USERS` (its own `orderby` enum, default `name`, `order` default `asc`) | |
| `GET /wp/v2/{categories,tags,wp_pattern_category}` | `CONTEXT`, `TERMS` (term `orderby` enum, `post`) | |
| `GET /wp/v2/comments` | `CONTEXT`, `COMMENTS` (`post`, `status`, `type`, `author_email`, `after`, `before`) | |
| `GET /wp/v2/search` | `SEARCH` (its `context` is view or embed only; `type`, `subtype`) | |
| single posts, pages, media, users, terms, comments; types; taxonomies | `CONTEXT` | |
| `POST/PUT/PATCH /wp/v2/settings` | | `Settings::SCHEMA` (every registered setting) |
| `POST /wp/v2/users` | | `USER_CREATE` (`username`, `email`, `password` required; `email` format) |
| `POST/PUT/PATCH /wp/v2/users/{id}` | | `USER_EDIT` |

`_fields` and `_embed` are read on every route and declared on none: the
reference lists neither in its index and validates neither
(`?_fields[][]=a` passes).

**A handler-judged argument.** The posts `status` filter is refused by the
caller's capabilities before its enum: an anonymous `?status=bogus` is
`rest_forbidden_status` "Status is forbidden." (details status 401), an
administrator's is `rest_not_in_enum`, and the enum message always names
`status[0]` whichever item was wrong. The entry carries
`Args::HANDLER_VALIDATES`, the router leaves it alone, the index drops the
key, and `PostsController::visibleStatuses` judges both in that order.

## Known divergences

- The term filters (`categories`, `tags`, and their excludes) are declared
  as the id list only. The reference also takes a taxonomy query object
  (`{terms, include_children, operator}`) and words the list refusal as
  "categories is not a valid Term ID List. Reason: categories[0] is not of
  type integer." with `{"position": 0}`; the engine says "categories[0] is
  not of type integer." and does not read the object form.
- Arguments the engine does not read are not refused: comments `orderby`,
  categories `parent` and `hide_empty`, the `offset` and `after`/`before`
  filters on posts and pages, users `roles`/`capabilities`/`who`.
- A query value in array form (`?slug[]=hello-world`) is validated but the
  list handlers read the comma form only, so it narrows nothing.
- The settings `url` has `format: uri`, which neither stack refuses: the
  reference sanitises `"nope"` to `http://nope` and stores it, the engine
  stores the string as sent. A probe of that key on a shared database
  rewrites `siteurl` on both stacks; restore it by hand.
