# Contributing

Minn Engine is a from-scratch, MIT-licensed engine that speaks WordPress's
operational contracts. Two documents govern every change: `docs/style.md` (the
shape of the code, enforced by `tests/style.test.php`) and
`docs/writing-minn.md` (how to add a route, a block, or an extension, and the
capture-then-implement method behind all of them). Read both before opening a
change; they are short.

## The rules that are not negotiable

- **Never copy, port, paraphrase, or mechanically transform WordPress source.**
  Not a function, not a regex. Do not open a WordPress source file while
  implementing engine code. Behaviour comes from a running WordPress, observed
  and captured into `contracts/`. Comparing outputs is always fine; reading
  the implementation is not. This is what keeps the licence MIT.
- **No GPL assets in the repo.** No core JavaScript, CSS, themes or icons.
- **Never execute WordPress core code in-process.**
- **"WordPress" appears only in truthful compatibility statements**, never in a
  name or a domain.

## The shape

- `declare(strict_types=1)`, PSR-12, one class per file under
  `public/minn/src/Minn/`, no free functions, no dependencies, no build step.
- Values that cross a boundary are `final readonly` classes. A closed set is an
  enum. Errors are exceptions (`RestError` for a WP-shaped one).
- The database has one door, `Minn\Db`, prepared statements only. A list
  parameter stands for a list of placeholders: `IN (?)` takes the array.
- Escape at output. Never `unserialize()`. Handlers never touch a superglobal,
  never `echo`, never `exit`.
- `wp-api/` is a mapping layer over `Minn\` classes: normalise, one call, shape
  the return. Logic belongs in `src/Minn/`.

## Proving a change

```bash
php tests/unit.test.php          # pure classes, under a second
php tests/style.test.php         # the shape and the ratchets, about seven seconds
php tests/tools/api-docs.php     # regenerate docs/api/ after touching src/Minn/
./tests/run-all.sh               # everything, against the reference sites (below)
```

### What runs where

`unit` and `style` run on a fresh clone with nothing else installed. Most other
suites compare the engine with a real WordPress serving the same database, and
need that pair set up:

- a test site running the engine (`tests/lib.php` reads `MINN_TEST_ROOT` and
  `MINN_TEST_URL`; the default is `minn.localhost`);
- a parked WordPress of the same version in the site's `wp-reference/`, on the
  same database, answering as the site at `MINN_TEST_REF` (the default is
  `https://ref.minn.localhost`). The maintainer's setup serves it as a Cove
  twin; any web server that answers with the site's host and HTTPS works;
- WP-CLI on `PATH`, and `tests/local.json` (copy `tests/local.example.json`)
  for the names of the other sites some suites drive.

A suite whose reference does not answer skips and says so. The clean-room scan
in `style` (`tests/tools/clone-scan.php`) compares the engine with the
reference's source and passes quietly when `wp-reference/` is missing; run it
where the reference exists before claiming a change is clean.

### Generated files

The style suite fails while one of these is stale. Regenerate after the change
that moves it, and commit the result with the change:

| After touching | Run |
|---|---|
| `src/Minn/` | `php tests/tools/api-docs.php` (docs/api, contracts/api/minn.json) |
| a `#[Route]` or its policy | `php tests/tools/route-catalogue.php` |
| `wp-api/` | `php tests/tools/facade-map.php`, `facade-logic.php` and `facade-gen.php --learn`; a changed function that holds logic needs a fresh verdict in `contracts/api/facade-review.json` (`facade-review.php --next`) |
| a WordPress-named call in `src/Minn/` | `php tests/tools/wordpress-names.php` |
| dead-end areas | `php tests/tools/deadends.php`, then `stub-symbols.php --deadends` |
| a real, dead-end or placeholder symbol | `php tests/tools/compat-status.php` |

`docs/api/` is the engine's own API, one page per namespace, read from the
classes by reflection; `contracts/api/minn.json` is the full model, internals
and source ranges included, and the Minn site renders it at `/api/` with a
page per class. Regenerate after any change to `src/Minn/`; the style suite
fails while it is stale. Read it before adding a class, so the one you need
does not already exist under another name.

A behavioural change ships with a suite. Where WordPress can observe the
behaviour, the suite diffs the engine against it request by request; where it
cannot, the contract says so and a fixture pins the shape. Suites clean up what
they create.

Two ratchets only tighten: in `wp-api/`, query calls and functions over forty
lines (both zero); in `src/Minn/`, methods over eighty lines and classes over
six hundred. Lower a ceiling when you clear an offender. Never raise one.

## What is public

The WordPress contracts, `wp-api/`, the `minn.json` manifest with
`Minn\Extension\Extension` and `Seams`, `bin/minn` and the `wp minn` verbs are
what sites, plugins and extensions rely on: change them only as WordPress or a
documented decision does. Everything else in `src/Minn/` is internal and may
change in any release (`readme.md`, Stability).

## Commits

Emoji-Log, imperative, present tense: `📦 NEW:`, `👌 IMPROVE:`, `🐛 FIX:`,
`📖 DOC:`, `🤖 TEST:`. The body says what changed and what the oracle taught,
not who or when. No Co-Authored-By trailers.

## Prose

Contracts and docs carry no em dash inside a sentence. Comments carry reasoning,
never attribution or dates.
