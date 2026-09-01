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
php tests/style.test.php         # the shape and the ratchets
php tests/tools/api-docs.php     # regenerate docs/api/ after touching src/Minn/
./tests/run-all.sh               # everything, with the oracles it starts itself
```

`docs/api/` is the engine's own API, one page per namespace, read from the
classes by reflection. Regenerate it after any change to `src/Minn/`; the
style suite fails while it is stale. Read it before adding a class, so the
one you need does not already exist under another name.

A behavioural change ships with a suite. Where WordPress can observe the
behaviour, the suite diffs the engine against it request by request; where it
cannot, the contract says so and a fixture pins the shape. Suites clean up what
they create.

Two ratchets only tighten: in `wp-api/`, query calls and functions over forty
lines (both zero); in `src/Minn/`, methods over eighty lines and classes over
six hundred. Lower a ceiling when you clear an offender. Never raise one.

## Commits

Emoji-Log, imperative, present tense: `📦 NEW:`, `👌 IMPROVE:`, `🐛 FIX:`,
`📖 DOC:`, `🤖 TEST:`. The body says what changed and what the oracle taught,
not who or when. No Co-Authored-By trailers.

## Prose

Contracts and docs carry no em dash inside a sentence. Comments carry reasoning,
never attribution or dates.
