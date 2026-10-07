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
- **Capabilities are declared, not checked.** A route's requirement is a `Policy` on the
  attribute (`#[Route(Method::Post, '/wp/v2/media', policy: new Policy(Access::Cap,
  'upload_files', refuse: 'rest_cannot_create', message: '...'))]`) and the router has
  the gate judge it before the handler runs; a router cannot be built without a gate.
  `Access` names the five answers (Public, SignedIn, Cap, Floor, Own), the codes and
  messages are the reference's, and `edit:` carries the policy for the edit context.
  A handler keeps only the residual decision a policy cannot state (own post versus
  others' once the record is loaded, publish, author reassignment), asked of the
  caller once and clearly. A route with no policy is counted by the style suite's
  ratchet, and that count only falls.
- **One path, not two.** Every request boots the WordPress runtime, so a branch on
  `Runtime::booted()` keeps a second way of doing a job that no request takes. When a
  WordPress-shaped path lands, the engine's own path for the same job goes in the same
  change; it does not wait beside it. The style suite counts the branches, and that
  count only falls.
- **The engine does its work in Minn classes.** `src/Minn` tells plugins what happened
  through hooks, which it may fire, but it does not call the facade's WordPress
  functions to get its work done: that is the facade reached from underneath. Behaviour
  plugins filter takes the filter as a closure (`docs/writing-minn.md`). The style suite
  counts calls from `src/Minn` into WordPress-named functions, the hook API aside, and
  that count only falls.

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

## How it reads

The rules above say what a file looks like. These say how a reader gets
through it, and each one is a ratchet in the style suite: the count only
falls.

- **Rows become records.** A repository returns `PostRecord`, `UserRecord`,
  `TermRecord`, or `CommentRecord`; a body reads `$post->title`, never
  `$row['post_title']`. A raw row is only ever a partial `SELECT` on its way
  to a shape.
- **A request's shape is an object.** What a list is narrowed to
  (`Rest\ListQuery`, `Content\CommentFilter`, `Content\PostFilter`) is read
  once into typed fields with named-argument construction, then handed on.
- **Objects are made once.** `Rest\Services` has one memoised getter per
  shared object; a controller takes the two or three it calls. No autowiring;
  `get()` by name fails loudly.
- **No method past eighty lines, no class past six hundred.** Split along the
  seam that is already there and name both halves.
- **Every public method has a sentence.** The docblock is what `/api/` and an
  agent read first; the signature is what they read second.
- **A boolean parameter is two methods.** Name the branch
  (`install()` / `replace()`), or pass the caller, so the call site says what
  it does.
- **A front door needs no import.** The few classes a caller reaches for
  from anywhere (`Minn\Db`, `Minn\Http`) sit at the root of the namespace
  with plain static methods, so `Minn\Http::get($url)` works inline in any
  file. Each is a written-out method (no `__callStatic`), and whatever it
  hands back is used through its methods, never named by the caller.
  Options are named arguments, so a misspelt one fails at the call. A
  front door keeps one swappable seam (`Minn\Http::fake()`) so code that
  calls it can be tested.
- **Regenerate after every change**: `php tests/tools/api-docs.php` for
  `src/Minn/`, `php tests/tools/facade-map.php` for `wp-api/`. The style suite
  fails while either is stale.

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
