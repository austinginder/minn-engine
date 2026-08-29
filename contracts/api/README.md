# The runtime interface inventory

What plugin code can call, captured mechanically from the running reference.
These files are the header file the WordPress runtime facade is written
against; nothing here is implementation.

| File | Source | Holds |
|---|---|---|
| `functions.json` | `wp eval-file tests/tools/api-inventory.php` (reflection) | every function declared under `wp-includes/` and `wp-admin/`: declaring file, parameters (name, type, default, by-ref, variadic), return type, deprecated flag |
| `classes.json` | same | every class, interface and trait: kind, parent, interfaces, constants, properties, own methods with signatures |
| `constants.json` | same | the constants the reference defines; per-site values (credentials, salts, paths, cookies) are replaced by `{"perSite": true}` |
| `globals.json` | same | the global variable names present after boot, with their type or class |
| `hooks.json` | `php tests/tools/hook-inventory.php` (scan of the firing calls) | every hook name, kind (action/filter), the largest argument count seen, the files that fire it; `{*}` marks a dynamic segment |
| `lifecycle.json` | the `minn-hook-trace` mu-plugin in the reference | the first firing of every hook, in order, for thirteen request kinds (home, single, page, category, search, 404, feed, REST list and index, login, cron, robots, sitemap) with its argument count |
| `meta.json` | | the reference version, PHP version, capture time |

Re-capture after a reference upgrade. The tracer only runs when
`MINN_HOOK_TRACE` names a file (see `tests/tools/api-inventory.php` and the
scratch battery in the skill notes); the reference on 8123 never has it set.

Behaviour is not in these files. It comes from probe batteries run on the
reference (`contracts/fixtures/api/`) and from whole-plugin parity.
