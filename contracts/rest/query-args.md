# REST collection query args

Minn Admin's library filters (Mine, Unattached, month, search) are query
args on `wp/v2/*` lists. WordPress honours the arg; if the engine never
reads it, the UI looks broken while the request is a 200.

The Mine miss on media was `author=3`: the app sent it, the oracle
narrowed to 17, the engine kept returning 817. Collection filters the
hunter then found (`author_exclude`, `parent_exclude`, `menu_order`,
`slug`, `exclude`, users `search`, comments `exclude` /
`parent_exclude`) are implemented from that same capture. Comments
also honour the matching `include` / `parent` pair, plus search,
after/before, author lists, author_email, and type (see
`contracts/rest/comments.md`).

## How to uncover the next one

`php tests/tools/rest-query-gap.php` (needs the oracle on :8123):

1. OPTIONS each collection on the running oracle. That JSON is captured
   data: the GET `args` WordPress advertises. Never WordPress source.
2. GET the collection on both stacks, then GET again with each arg set to
   a discriminating value (`author=1`, `parent=0`, `search=the`, …).
3. An arg is **ignored** when the oracle's `X-WP-Total` moves and the
   engine's does not.

Dogfood (richer media):

```
php tests/tools/rest-query-gap.php \
  --engine=https://dogfood.localhost \
  --ref=http://127.0.0.1:8124
```

Paging (`page`, `per_page`, `offset`), `context`, `_fields`, and sort
keys are skipped: they do not change the total the way a filter does.

The tool is a hunt, not a ratchet, and is not in `run-all.sh`. When a
live ignore is a filter Minn Admin actually sends, implement it from the
oracle wording the same way as media `author` / `parent` / `media_type`.
