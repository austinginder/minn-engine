# Minn Engine code style

The engine speaks WordPress at its seams and nowhere else. The seams (table names,
route paths, JSON keys, cookie names, file names) are fixed by compatibility and are
ugly on purpose. Everything behind them is ours, and it should be a joy to read and
to change. This guide is enforced by `tests/style.test.php`; the suite, not this
prose, is the rule.

## The shape

- **PHP 8.3 minimum.** Use the language: `readonly` classes, constructor promotion,
  enums, `match`, named arguments, first-class callables, typed properties, union and
  intersection types, `never` for functions that do not return.
- **`declare(strict_types=1);`** is the first statement of every file.
- **Namespaces, PSR-4, no build step.** Code lives under `public/minn/src/Minn/` and maps to the
  `Minn\` namespace by `Minn\Autoloader` (a dozen lines, registered from `public/minn/bootstrap.php`). No Composer, no
  vendor directory, no dependencies. One class per file, file named for the class.
- **PSR-12 formatting.** Four-space indentation, no space padding inside parentheses,
  `function foo(string $bar): void`. This is the one place the engine deliberately
  does not look like WordPress code.
- **Classes over free functions.** Behaviour is grouped into small final classes with
  one clear responsibility. There are no free functions in the engine; the only file
  outside `public/minn/src/Minn/` is `public/minn/bootstrap.php` (required by `public/wp-settings.php`), which registers the autoloader and
  hands off to `Minn\Engine`.
- **Immutable values.** Data that crosses a boundary (a request, a response, a
  resolved route, a post record) is a `final readonly class` with promoted properties.
  Mutation is a method that returns a new instance (`$response->withHeader(...)`).
- **Enums for closed sets.** `Method`, `Kind`, `PostStatus`, `Context`. A string that
  can only be one of five values is an enum, not a string. A post type is not one:
  the set is open to every plugin, so it stays a string.

## Boundaries

- **`Request` in, `Response` out.** Handlers are pure functions of the request: they
  receive a `Minn\Http\Request` and return a `Minn\Http\Response`. They never read
  `$_GET`, `$_POST`, `$_SERVER`, or `$_COOKIE`, never call `header()`, never `echo`,
  never `exit`. The kernel does the I/O once, at the edge.
- **Errors are exceptions.** A WP-shaped REST error is `throw new RestError('rest_no_route',
  'No route was found...', 404)`. The kernel renders it. No handler builds an error
  payload by hand.
- **Database through `Minn\Db`.** Prepared statements only, always through the
  `Db` helpers (`row`, `rows`, `value`, `execute`, `insert`, `update`). Table names come
  from `$db->table('posts')`; `global $table_prefix` never appears.
- **Escaping at output.** `Html::esc()` at the point of rendering, never earlier.
  Values are stored and passed raw.
- **Serialized blobs are read, never executed.** `Minn\Support\Serialized` parses
  WordPress's serialized-PHP by tolerant byte scanning. `unserialize()` does not appear
  anywhere in `src/`. This rule has held under real pressure every milestone.
- **Capabilities are declared, not checked.** A route's requirement is metadata on the
  handler (`#[Route(Method::POST, '/wp/v2/posts', cap: 'edit_posts')]`) and the router
  enforces it before the handler runs. A handler that needs a finer-grained check
  (own post vs others') asks `Caps::can($user, 'edit_post', $id)` once and clearly.

## Naming

- Classes: `PascalCase`, nouns (`PermalinkResolver`, `PostRepository`, `RestError`).
- Methods: `camelCase`, verbs (`resolve`, `find`, `render`). Boolean-returning methods
  read as questions (`isPublic`, `hasParent`, `canEdit`).
- No abbreviations that save fewer than four characters. `$request`, not `$req`;
  `$attachment`, not `$att`. WordPress column names are used verbatim when the value
  IS the column (`post_name`, `post_status`) so that the seam stays visible.
- Constants and enum cases: `PascalCase` cases (`PostStatus::Publish`), because they
  read as values, not as shouting.

## Comments

- A docblock explains **why**, and only when the code cannot. Typed signatures carry
  the what. No `@param`/`@return` restating a type the signature already declares.
- Oracle-learned facts are recorded next to the code that depends on them, in one or
  two sentences, in the present tense: "The reference omits the author link when
  `post_author` is 0." Never "fixed by", never a date, never a name.
- No commented-out code. Git remembers.

## Tests

- Every behavioural unit ships with a suite, and the suite is named for the surface,
  not the file (`permalinks.test.php`, not `resolver.test.php`).
- A pure class (no database, no request) is proven in `tests/unit/` first: a file
  returning cases, each a label and a closure, run by `php tests/unit.test.php` in
  under a second. Write the case while writing the class.
- Suites prove two ways where an oracle exists: pinned fixtures (run without the
  oracle) and a live diff (run against it). A behaviour the oracle cannot observe is
  documented as engine-defined in the matching contract.
- Suites clean up what they create, in a shutdown handler, so read fixtures never
  drift.

## The lint

`tests/style.test.php` asserts, for every file under `public/minn/src/Minn/`:

- starts with `<?php` then `declare(strict_types=1);`
- declares a namespace matching its path
- contains no `global `, `unserialize(`, `extract(`, `eval(`, `$_GET`, `$_POST`,
  `$_COOKIE`, `$_SERVER`, `$_FILES`, `header(`, `echo `, `exit`, `die(` outside the
  files allowed to touch the edge (`Minn\Http\Request`, `Minn\Http\Response`)
- uses four-space indentation, no tabs

and reports any procedural `public/minn/src/*.php` file that appears (there are none; the count
is expected to stay at zero).

Two ratchets ride in the same suite and only ever tighten: in `wp-api/`, query calls
and functions over forty lines (both at zero); in `src/Minn/`, methods over eighty
lines and classes over six hundred. Lower a ceiling when a file loses its last
offender; never raise one.
