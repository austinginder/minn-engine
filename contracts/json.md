# JSON

`Minn\Json` is the one door for JSON in the engine and in code written for
Minn. The door itself is engine-defined; what it repairs, and every byte the
WordPress-shaped surfaces write through it, comes from the reference.

```php
use Minn\Json;

Json::encode($rows);              // {"url":"https://example.com/a","name":"Café"}
Json::decode($text);              // objects as arrays; JsonException when it is not JSON
Json::decode($stored, or: []);    // the fallback, for text that may be empty or broken
Json::file($path);                // the same, read from a file; the error names the file
```

Code: `public/minn/src/Minn/Json.php`. Proof: `tests/unit/json.php` (16 cases,
two of them read the reference's own rows), the api suite's `wp_json_encode`
and `functions-utils` rows, and the runtime suite's cut-string route.

## Encoding

- **The default form** is `Json::READABLE`: slashes and non-ASCII characters
  written as themselves. `Json::ESCAPED` is the form `wp_json_encode()` writes
  with no flags, and every REST body keeps it: `Rest\Reply`, `Rest\Embed`, and
  the runtime's REST output for plugin routes. Any other `json_encode()` flags
  pass through as given (the import map, block comment attributes and inline
  script data keep the flags their markup needs).
- **Invalid UTF-8 never fails an encode.** When the first attempt fails, the
  value is repaired and encoded again: each invalid byte of every string and
  every key becomes `?` and the valid characters stay, objects are read as
  plain objects of their public properties. This is the reference's fallback,
  pinned row by row (`functions-utils`: "json convert string", "json sanity
  check").
- **What cannot be encoded even then throws `JsonException`**: `NAN` and
  `INF`, and nesting past the depth (code `JSON_ERROR_DEPTH`). The reference's
  `wp_json_encode()` answers `false` for both; the facade catches and answers
  `false`.
- **`JSON_THROW_ON_ERROR` skips the repair.** Asked to throw, the reference
  lets `json_encode()`'s own exception through instead of repairing, and so
  does `Json::encode()`. `JSON_PARTIAL_OUTPUT_ON_ERROR` succeeds on the first
  attempt on both, with `null` for the bad string.
- **`repair()` throws a plain `Exception`** ("Reached depth limit") past the
  depth, because the reference's `_wp_json_sanity_check()` throws exactly
  that and the facade maps onto it.

## Decoding

- **Objects come back as arrays.** There is no object mode; the one caller
  that needs one (`Rest\RuntimeRoutes::decode()`, which keeps an empty object
  as `{}` on its way back out) calls `json_decode()` itself, and the style
  suite's ratchet allows that one call and no other.
- **Broken text throws `JsonException`** (`Syntax error`, code 4) unless the
  call names a fallback with `or:`. The fallback is for text that is not JSON;
  the JSON `null` is a value and comes back as `null`.
- **`file()` names the file.** A missing or unreadable file throws
  `"{path} could not be read."`, a broken one `"{path} is not JSON: {reason}"`,
  each with no PHP warning. With `or:`, either gives the fallback.
- **The engine's own data files are read without a fallback**, so a missing
  `public/minn/data/*.json` fails loudly. Files a theme, plugin or site
  provides (theme.json, style variations, `minn.json`, translation packs) are
  read with `or: null` and treated as empty when broken, as before.

## REST bodies with bad bytes

A plugin route that returns a string cut mid-character
(`substr('Café au lait', 0, 4)`) is answered by the reference with the data:
`200`, `{"title":"Caf?","url":"https:\/\/example.com\/a"}`. The engine used to
cast a failed `json_encode()` to a string and answer `200` with an empty body.
The runtime suite's fixture plugin carries the route (`minn-test/v1/cut`) and
the suite compares both stacks byte for byte.
