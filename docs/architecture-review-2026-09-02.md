# Architecture review, 2026-09-02

A structural read of `public/minn/` (357 engine classes, 49,000 lines under
`src/Minn/`; 32,000 lines of facade) taken the same day the security review's
fix list and its first three breaking changes landed. The question this time
was not "is it safe" but "is the internal API the right one to freeze at
0.1.0", judged under one lens: Minn is meant to be the web runtime an agent
operates. That lens is spelled out in `docs/agent-first.md`. Every route
should be a self-describing ability, every caller should hold only the
authority it was granted, every write should be attributable and reversible,
and the whole thing should stay small enough to reason about.

Seven reviewers read one area each (root, Http and Rest; Runtime and the
facade's doors; Content, Auth, Media and Support; Blocks, Theme, Front and
Extension; Ops, Admin, Cli, Cron and Mail; the contract corpus and the live
index; the WP Registry API). Every finding below was checked at the cited line
before it was written down. Nothing under `wp-reference/` was opened.

## What the previous audit left, re-verified

Breaking changes 1 to 3 (policy on the attribute, one `Context`, the kernel as
a function of the request) and the post-audit five (transactions, route args,
trusted proxies, `wp-abilities/v1`, `Minn\Ops`) are in and consistent. Two
residues: `Engine::answer` builds the `Context` and then `Api::forRequest`
rebuilds `Site` and `Capabilities` without it (`Engine.php:180-187`,
`Rest/Services.php:114-117`); and the front router carries a second inline
gate (`Engine.php:259-270`) that ignores `Access`, dead today because all
thirty front and login policies are public.

Of the six changes the first audit ranked after those, this read keeps four,
sharpens one and drops one:

| Then | Now |
|---|---|
| 4. Retire the 35-key state bag | Kept, and it is worse than counted: 35 literal keys plus two dynamic families, about 134 sites in 33 files, and `Runtime::set()` is public, so plugin code can overwrite `current_user`, `engine_routes` and the realpath map (`Runtime/Runtime.php:247`). |
| 5. The front end talks to the runtime through Seams | Kept; the count is 64 facade-touching lines, not forty: 20 on the classic path (which is the facade by nature and should move under `Runtime\`), 41 on the block path, 3 holder plumbing. |
| 6. Records lose ArrayAccess; a declarative post type | See the Content section. |
| 7. Transactions, writer-owned columns, revisions in the writer | Transactions landed on five writes; `Ops/` still has none and `PluginState::deactivate` writes `active_plugins` once per replaced file. Revisions and columns: see the Content section. |
| 8. One way to build a REST shape | Kept and measured: five build signatures over nine shape classes, 50 call sites. |
| 9. Split Admin into Ops and MinnAdmin | Done as a file split. Dropped as a rename: `Minn\Admin` is the right name for the one client. What is still misplaced is listed under Ops below. |

## Functional gaps found on the way

Status, same day: F1 and F2 landed in 2b46acc and cf9ae1f (the front request runs
the job after its response, under `DOING_CRON`), F3 and F5 to F8 in 6e8fd0a, F4 in
4004428 (`Router::allowed`, suite `allow`, whose divergent list names B1 and B7 as
what settles each remaining case). Two things surfaced while proving them: a no-term
`/wp/v2/search` answered 500 (fixed), and `POST wp/v2/settings` stores a value the
reference refuses as invalid, which is B1's "args validated before the policy".

These are not structure. They are things the contracts say happen and the
code does not do, and they go first.

| # | Gap | Where | Fix |
|---|---|---|---|
| F1 | **The WordPress `cron` option is never executed.** Plugins schedule events through `wp_schedule_event`; `wp_cron()` and `_minn_run_cron()` are defined and have zero callers. Every trigger (`/wp-cron.php`, a front request, `wp minn cron`) runs `Cron\Cron::run()`, which publishes posts, sweeps transients and runs auto-updates, and never reads the option. `contracts/runtime.md` says the hooks run. | `wp-api/cron.php:207-231`, `Cron/Cron.php:28-49`, `Front/ProbeController.php:54-61`, `Cli/MinnCommand.php:103-110` | `Cron::run()` gains a step over `Runtime\CronTable::due()` that fires each due hook through the runtime; `Cron::due()` answers true when the option has a due event; `Ops\Diagnostics::cron()` lists the option's events. |
| F2 | **`wp minn cron` never applies auto-updates.** The CLI builds `Cron` without its `Updates` argument, so a host that drives cron from the system (the fleet pattern) never auto-updates. The last-run stamp is written before the run, so a failure waits a day to retry, and `Updates::runAuto` swallows every refusal. | `Cli/MinnCommand.php:105`, `Cron/Cron.php:23,39-41`, `Ops/Updates.php:238-246` | One construction recipe for `Cron` (`Services::cron()` and `Cli\Runtime::cron()`), stamp after success, journal the failures (see Ops). |
| F3 | **The `head` seam fires before the engine stylesheets, the contract says after.** On every front request the runtime is booted, the engine's stylesheets travel inside `wp_head`, and the extension head prints first. | `Theme/PageRenderer.php:220-232`, `contracts/extensions.md` | Print the extension head after the runtime head, or hand the stylesheet block into the seam; pin in the extensions suite. |
| F4 | **`Allow: GET` on every REST response**, including a `201 Created` and a `401` on a POST. The contract pins it for a GET read only. | `Rest/Reply.php:23` | Set `Allow` from the route map after dispatch. |
| F5 | **The index advertises two routes nothing answers.** The declared-types catch-all registers `/wp/v2/{base}` and `/wp/v2/{base}/{id}` with open constraints, the index lists every pattern, and a caller of either gets `rest_no_route`. `system/logs/{id}` is listed twice with two escapings. | `Rest/DeclaredPostsController.php:29-60`, `Rest/IndexController.php:47-62` | `Route(index: false)`, or one concrete pattern per declared type (the types are in hand at `Api.php:169`). |
| F6 | **A plugin can rewrite the symbol cache that gates its own load.** The scan is cached in `minn_runtime_symbols` keyed by folder name, trusted on mtime and file count alone, and the option is writable through the facade. | `Runtime/Symbols.php:34-45` | Carry the reader version and a hash of the scan in the entry; make the option one of the guarded names. |
| F7 | **`force=1` deletes a term and trashes a template.** Seven routes read `force` through boolean validation; templates compare to the string `'true'`. | `Rest/TemplatesController.php:161` | `Request::flag()` at all eight sites. |
| F8 | **`Loader` is built twice per front request**, each globbing every `minn.json`. | `Engine.php:238,242` | One instance. |

## Breaking changes to make before 0.1.0

Each changes `src/Minn/` only. None changes a seam WordPress tooling or a
plugin can see. Ranked by what the agent-first surfaces need first.

### B1. Settle `Http\Route` and `Http\Policy`, and make the route table a catalogue

Today the route table is a dispatcher with policy metadata. Nothing names a
route, no write declares its body, no route declares its output, the declared
args are documentation the router never enforces (`Http/Router.php:71-95`
never reads `$route->args`), and `Router::table()` cannot be read without a
live database because `Api::controllers()` resolves the caller, the roles and
the theme at build time (`Rest/Api.php:73,84,107`).

Change: `Route` gains `name` (default `Class::method`), `body` (an `Args`
set for the JSON body), `output` (a schema constant on the shape), `index`
(whether the index lists it) and `Policy` gains `missing` (the 404 code an
`Own` policy answers before its 403, with a `Rest\Subjects` lookup keyed by
capture name). The gate validates declared args before it judges the policy,
which is the captured reference order and the concrete reason nine routes
cannot carry a policy today (`Rest/ApplicationPasswordsController.php:22-23`,
`Rest/UsersController.php:284`). `Router::table()` returns typed rows and
needs no database. A `Rest\Catalogue` reads it and emits both the REST index
and the abilities registrations.

Cost: the `Route` constructor (additive), the gate closure (one producer, one
consumer), about thirty attributes that can then state a policy. The 84 bare
routes classify as 17 genuinely public with an edit-context residual, 27
statable today with existing `Access` kinds, 30 record-dependent (unblocked
by `missing`), 8 per-`{base}` capture (need an `Access::Type` answer that
resolves `TypeCapabilities` from a capture), 2 bare by contract.

### B2. One `Grant` beside `Policy`, and the agent key as its first caller

There is one kind of caller today: a user with a role. An application
password is the user. The gate judges `Policy` alone.

Change: `Auth\Grant` (a readonly value: the abilities or route names a
credential may call, a floor such as read-only or own-content, an expiry)
held on an application password, and `PolicyGate::judge` becomes
`policy ∧ grant`. The credential row stays in the reference's
`_application_passwords` meta so WordPress still reads it; the grant is an
extra key WordPress ignores. This is the change that lets a site owner hand an
agent less than the site.

Cost: `PolicyGate` (one class), `Auth\ApplicationPasswords` (one field),
`Authenticator::restFromRequest` (attach the grant to the caller). Nothing
external.

### B3. Who: `Runtime\PluginIdentity`, `Runtime\Attribution`, and an owner on every hook

A loaded plugin is the process. The loader knows which file it is including
(`Runtime/Plugins.php:157-180`), the realpath map turns any path into a
plugin (`Recovery::blame`, `Runtime/Recovery.php:50-76`), and then the
knowledge is thrown away: `Hooks` stores `['function', 'accepted_args']` and
nothing else (`Runtime/Hooks.php:78`), so no later call can be attributed.

Change: `PluginIdentity` (slug, main file in the `active_plugins` spelling,
real directory, kind) built once per include; `Attribution` on the runtime
instance with `enter`/`leave`/`current`, pushed at the seven dispatch seams
(the include, `Hooks::run`, `fireAll`, the REST server callback, `WP_Block`,
shortcodes, `Abilities::execute`); a parallel `owners[hook][priority][id]`
map on `Hooks` written in `add()`. The entry array itself cannot change
(`WP_Hook::bound` shares it by reference and plugins read it), which is why
the owner is a parallel map decided now.

Cost: under a hundred lines, zero hot-path cost, nothing removed from the
facade. This is the precondition for every door below.

### B4. Where: one funnel per door, addressable by type

The doors a plugin reaches the world through already funnel, but not all to
one place, and none is a typed method a policy could sit on:

| Door | Funnel today | The problem |
|---|---|---|
| Options | `Runtime\Options` (four statements) | `Content\Site::setOption` is a second door reachable by plugins (`Content/Site.php:23-31`), and `pre_option_{name}` lets any plugin answer another's option |
| Roles | `WP_Roles::persist` writing `wp_user_roles` | one funnel, no judge |
| User capabilities | three `WP_User` writes into `Runtime\Meta` | one funnel, no judge |
| Outbound HTTP | `Http\Client::send` | one funnel, no host record; the facade's own PHPMailer opens sockets beside it |
| SQL | `Db::run` (prepared) and `wpdb::query`, which calls mysqli directly with a public `$dbh` (`wp-api/classes/wpdb.php:59,199`) | the second door is uncounted and unattributed |
| Filesystem | none | static scan only; do not promise enforcement |
| Hook removal | `Hooks::remove`/`removeAll` plus by-reference edits of `$wp_filter` | audit only; removing another plugin's hook is the cooperation idiom |

Change: `wpdb::query` routes through `Db::raw()` with an observer so both
doors share a counter; `Site::setOption` and `Runtime\Options` become one
door; `Runtime\Doors::option/grant/outbound(...)` are the named places a
`Grant` from B3 is asked; `Runtime\Ledger` records what each plugin wanted,
audit first. The string bag retires into typed holders (boot values on the
constructor, registries as memoised instances including `Abilities`, page
counters on `RenderState`), and `Runtime::set()` stops being public.

Cost: medium and mechanical (134 bag sites). Once the doors are typed methods
on named classes, enforcement is additive calls at four line numbers.

### B5. The extension manifest declares its authority; `Seams` loses `db`

One `Seams` instance is built per request (`Engine.php:237-239`) and handed
to every extension in turn (`Extension/Loader.php:118-131`), so an extension
that throws half-way leaves its earlier registrations live, `title` is a
single slot where the last writer wins, and a duplicate shortcode tag
silently replaces. Through it an extension holds the whole `Db`, and `Site`
with its two option writers. Outside it, every static in the engine and every
facade function is callable, because nothing scopes the autoloader.

Change: `"needs"` in `minn.json` (`seams`, `options.read`, `content.read`,
`meta.write`, `http`); `Extension\Grant::fromManifest`; the loader builds one
`Seams` per manifest over its grant and merges the registrations on success
only; `Seams` loses `$db`, keeps `$request` and `$reader`, gains
key-allowlisted `$options`, read-only `$content`, allowlisted `$meta` and a
host-bound `$http`; each registration throws when its seam is not in
`needs`; a manifest without `needs` gets no seams. A `Seams::block()`
registration replaces the one static reach the ports make today. A `minn
extension lint` refuses `Runtime::`, `Db::`, `Reader::current`, `Client::`
and `$GLOBALS` in an extension's `src/` until isolation makes the refusal
physical.

Cost: 4 lines in 2 of the 10 ports (`$minn->db` reads become
`$minn->content`), 10 manifests gain a `needs` block, the seams fixture too.
This is the one breaking change here that touches a published contract, and
it is the reason it must land before 0.1.0.

### B6. The engine's front end goes through `Seams`, and the facade becomes one participant

Of the 64 facade-touching lines in Blocks, Theme and Front, the 20 on the
classic path are the facade by nature (`ClassicRenderer`, `ClassicContent`,
half of `MainQueryBridge` run PHP templates and seed `$GLOBALS['wp']`) and
move under `Runtime\Front\`. The 41 on the block path split into 33
closure-shaped sites that become seven new registrations (`renderBlock`,
`filterBodyClasses`, `archiveTitle`, `takeoverTemplate`,
`templateCandidates`, `imageSources`, `mainQuery` with `beforeRender`,
`rewriteRules` with `queryVars`) and 8 data-shaped sites that take a
`Registry` of types, taxonomies and block templates by constructor. A
`Runtime\FacadeExtension` registers after the loader so plugin filters keep
running after extensions, as they do today.

Then a theme page with no plugins renders without the facade loaded, which is
not true today (`Runtime::boot()` is unconditional at `Engine.php:210` and
`PageRenderer::documentTitle` always calls `\_minn_document_title`), and real
extensions, the facade, and later an isolated runner consume one surface.

In the same pass, the twenty leaves that ask `RenderState::current()` take
the state as an argument; every dynamic block closure already receives the
`Renderer`, which owns it. `Runtime::renderState()`/`useRenderState()`
(landed this morning) are then deleted.

### B7. One verb to build a REST shape; `Access::Floor` goes

Five signatures over nine shape classes (`view`/`edit`, `build`, `view` with
a base, `item`), and `PostObject::edit(PostRecord, int $userId)` is called
six times with the caller's id every time. `targetHints.allow` is computed by
hand at 18 sites in 13 files. `rest_invalid_param` is hand-built at 20 sites
in four shapes while `Schema` produces the structure and has one caller.
Pagination is parsed five ways with different answers to the same bad input
(`per_page=abc` is one item on posts and a 400 on search; a page past the end
is a 400 on posts and an empty 200 on users).

Change: `build(Record, Context)` on every shape; `Rest\Allow` for hints;
`RestError::invalidParam(string, Refusal)`; `ListQuery::fromRequest` as the
only reader, validated through `Schema` against `Args::LISTING`, which B1
makes free. `Access::Floor` is exactly `Access::Cap` with `edit_posts` and
`signIn === refuse`; replace the enum case with `Policy::floor(...)` so
`Access` has four answers and the engine's REST layer stops naming one
client. `Api::controllers()` stops constructing fifteen `Minn\Admin`
controllers unconditionally; an `Admin\Bootstrap` appends them.

Cost: about 50 shape call sites, 18 hint sites, 20 error sites, 43 Admin
attributes. This is the cheapest moment; once extensions build shapes the
count is in the hundreds.

### B8. `Minn\Ops` is transport-neutral for real

The file split is done and the dependency direction is right, but `Ops/`
throws `RestError` 46 times (`Packages` 31, `Updates` 10, `Logs` 5) and
`Diagnostics::payload` takes a `Request`. The CLI already pays: it branches on
`$error->status === 409` (`Cli/PluginCommand.php:435-437`). An MCP server or
an ability calling `Packages::unpack()` would receive an HTTP-shaped
exception.

Change: `Ops\Refusal` with a code and facts; the admin controllers map it to
`RestError` with the same wire code and status; `Diagnostics::payload(ServerFacts)`
with `ServerFacts::fromRequest` and `fromSapi`. Move the pieces still wearing
`Admin`: theme scanning (written four times) and activation (twice) into
`Ops\Themes`; the GitHub release lookup into `Packages`; `Translations`' own
zip loop onto the guarded unpacker; `LanguageChoices` (reached from
`wp-api/l10n.php`) out of `Admin`. `Cli\Installer` and `Cli\Preflight` have
no WP-CLI calls and are `Ops` wearing `Cli`; `Preflight` returns a list of
typed lights so an MCP caller gets structure and the CLI words it. The
twinned `installOne`/`installArchive` in the plugin and theme commands
collapse onto one `Packages::installSource`.

Cost: two to three hours for the refusal type; a day for the rest.

### B9. Names that will hurt once frozen

`Caller::require()` is a reserved word as a method name (18 callers) and
`Caller::refuse()` returns an error 33 sites throw by hand while
`requireCap()` throws itself; `Reply::item(..., Fields::fromQuery(...))` is
written 65 times beside `Reply::answer($request, ...)`, which the docblock
says is what nearly every handler ends with; `Rest\Context` (the enum, 21
sites) and `Minn\Context` (the request value, 4 references) collide, and
inside `Rest/` every `Context` is the enum; `AbilitiesController::category_`;
`Services::get()` and its 38-entry `NAMED` map have zero callers and a
sentence in `docs/style.md`. Rename the root value while it has four
references, make `refuse()` throw, make `item()` private, delete `get()`.

## Content, Auth and Media

Under the lens this slice has to make three things true: an entity is one
declaration, a write is reversible, and authority has one door. It is clean at
its seams and none of the three is true yet.

### B10. One `PostType` declaration

A post type's facts are spelled in twelve places: `data/types.json`,
`data/types-admin.json` (read twice, once raw by `StructureController`),
`data/registry.json` (the facade's full registration, read by `Runtime\Registry`
only), `Auth\TypeCapabilities` (a plural rule and a one-entry fold table),
`Runtime\Registry::capabilities()` (the real capability-map derivation, which
nothing in Auth, Content or Rest reads), two `restBase` derivations, three
spellings of the revisioned-type list, the permalink branches, `ContentScan`'s
core list, the manifest-declared types re-shaped three times, and the per-type
field branches in the shapes and the writer. The consequence is concrete: a
plugin type registered with its own `capability_type` maps to `edit_posts` on
engine routes while the facade's type object says `edit_agent_tasks`; a
manifest-declared type gets `supports: title, editor` invented twice; and
`types-admin.json` says `wp_block` has revisions while `PostWriter` refuses to
snapshot it.

Change: `Content\PostType` (readonly: slug, labels, rest base, hierarchy,
archive, supports, a capability map built exactly as `Registry::capabilities()`
builds it, revisions, autosave, taxonomies, meta schema, template, viewable,
edit cap) and `Content\PostTypes`, one memoised registry seeded from one merged
`data/post-types.json`, then the manifests, then `Runtime\Registry::registerPostType`
delegating in. `Rest\Types`, `AdminTypes`, `Capabilities::mapPostCapability`,
`PostWriter::maybeSaveRevision` and both `restBase()` become projections of it.
Cost: 13 `TypeCapabilities::` sites, 5 `restBase(` sites, 3 JSON readers, 2
hardcoded lists. This is what makes "one declaration adds an entity" true.

### B11. Reversibility owned by the writer

`maybeSaveRevision` has three callers (the REST update, user styles, and the
facade's `wp_save_post_revision` for post and page). Nothing else snapshots:
reusable blocks, templates, navigation, media edits, menu items, declared types
(the call reaches the writer and the writer refuses the type), trash and
destroy. And the snapshot is of the new row read back after the update, so the
first edit of any post loses its pre-edit state. Fourteen compound writes run
outside a transaction, among them the REST update itself (columns, terms,
extended fields, recount, revision), user update and delete (the delete issues
its own raw SQL and orphans revisions and term links instead of calling
`PostWriter::destroy`), comment update and delete, menu create and delete,
media attach and remove, template save, `setTerms`, and `Sessions::create`
(a read-modify-write of the whole blob, so two concurrent sign-ins can drop a
session).

Change: `PostWriter::update()` opens the transaction, seeds a revision of the
current row when the type revisions and none exists, updates, snapshots the
new row; `updateSilently()` for the three callers that must not (the autosave
slot and the two guid fix-ups); `PostWriter::db()` goes (it exists so a
controller can open the transaction the writer should own). A repository verb
that issues more than one statement is atomic. `Content\NewPost` with defaults
for the 23 columns replaces the eight hand-spelled full rows. One
`Db::update($table, $columns, $where)` (the style guide already names it)
replaces the one-UPDATE-per-column loops in `Users` and `Comments`, and one
meta door replaces three upsert idioms. Cost: 11 `writer->update(` sites in 6
files, 14 transaction sites, 8 row literals.

### B12. One door for authority, and per-user capabilities honoured

Role changes are spelled three times (`Content/Users.php:119`,
`Rest/UsersController.php:278`, `Cli/UserCommand.php:306`), all writing the
capabilities blob through `setMeta`. The facade goes around all three:
`WP_User::set_role` and `add_cap` write the blob through `Runtime\Meta` with
arbitrary capability maps, and `WP_Roles::persist` writes `wp_user_roles`
through `Runtime\Options`, beside the engine's own `Site::setOption`. Five
explicit doors, two generic ones, and no place a rule can sit. Worse,
`Capabilities::primitivesOf` unions the capabilities of the user's registered
roles and nothing else (`Auth/Capabilities.php:59-71`), so a per-user
capability a plugin grants is written to the blob and never honoured on an
engine route. That is a parity divergence with the reference, which reads the
user's own grants.

Change: `Auth\Grants` (`setRole`, `addCapability`, `defineRole`,
`removeRole`, each taking the actor) writing both the blob and
`wp_user_roles`; `Runtime\Meta` and both option doors refuse the two keys and
redirect there; `Capabilities::primitivesOf` reads the user's own grants from
the same blob. The caller-dimension rule from B3 and B4 ("plugin P may not
grant administrator") is judged inside `Grants`, which is the one place it can
be. Cost: 3 engine sites, 2 facade classes, 2 generic doors.

### B13. A typed actor instead of an ambient reader

"Who" is four things: `Content\Reader` (visibility), `Rest\Caller` (HTTP
identity), `Auth\Capabilities` keyed by a bare `int $userId`, and the id itself
threaded by hand. The split is principled; the problem is that none of them is
a value an agent-facing API hands around. `Reader::current()` is read
ambiently at 11 sites in 10 files, including inside `Posts::scope()`
(`Content/Posts.php:205`), so the repository answers differently on the CLI and
in unit tests. `Capabilities::fromDb` runs three times per request, so a role
definition a plugin persists is forgotten by one instance and kept by the
other two.

Change: `Auth\Actor` (id, roles, session token, and an `Origin` enum:
anonymous, cookie, application password, CLI, plugin, agent) built once by
`Authenticator` and carried on `Context`; `Capabilities::can(Actor, string,
?int $objectId)`; `PostFilter::visibleTo(Reader)` instead of the ambient read.
The signature change is the part to make before the freeze: 33
`capabilities->can(` sites now, and `Grants` and the agent key both need the
actor's origin. The object id parameter is named `$postId` and carries a user
id for `edit_user`; rename it while it is one method.

### Smaller, all verified

- **Trashed posts read too widely** (roadmap item, confirmed):
  `Capabilities::mapPostCapability` swaps a trashed status for the status it
  was trashed from before the `read_post` branch (`Auth/Capabilities.php:126-135`),
  so a post trashed from publish answers `read` to any signed-in user; the
  reference answers 403. One method; capture the owner's case first.
- `Phpass` and `PortableHash` are byte-identical apart from default rounds and
  an argument order; merge (2 + 2 callers). The "two throttle pruners" claim
  from the first audit is rejected: `LoginThrottle::prune` and
  `Sessions::prune` are different stores. What `Sessions` does duplicate is
  `isLive`, which re-scans the blob with a second regex.
- `Serialized::decode` maps an object to `stdClass` and `encode` writes it
  back as one, so an option holding an object loses its class on a round
  trip. A 25-line `SerializedObject` value fixes it; `Options::fromStorage`
  converts `stdClass` for plugins and keeps the rest.
- `Posts::published()` has zero callers and a doubled docblock the API docs
  print; twenty double docblocks in the slice print the wrong sentence.
- The bracket ratchets stand at post 246 of 248, user 73 of 73, comment 78 of
  78, and the regex counts raw rows in `PostInsert` and `PostRecord` itself,
  so it cannot reach zero as written. Drop `ArrayAccess` from the four records
  and count `->column(` instead.
- `Terms::find(string $taxonomy, int $id)` and `Terms::row(int $id, string
  $taxonomy)` flip their arguments; `Terms::delete(TermRecord, bool
  $hierarchical)` should read hierarchy from the taxonomy; `Comments::page`
  returns an array pair where `Page` exists; `Db::escape` has one caller,
  `esc_sql`, and belongs to the facade.

## Additive work after the cut, by surface

Everything here lands without breaking `src/Minn` or the facade once B1 to
B9 are in. Grouped by the four surfaces `docs/agent-first.md` promises.

**The catalogue.** `Rest\RouteAbilities` walks the typed route table on
`wp_abilities_api_init` and registers each engine route as an ability: name
from the handler, description from the docblock sentence every public method
already has, category from the namespace, input schema from args and
captures, permission from the policy, execute through the router, the
readonly and destructive annotations from the method. An OpenAPI exporter
reads the same rows. `wp-abilities/v1` today carries the three core abilities
and needs a session even to list them.

**The grant.** `Runtime\Doors` asks the plugin's `Grant` at the four funnels
and refuses in `enforce` mode; `Symbols` records literal arguments for a
fixed sink list (hosts, tables, roles, option names) and flags `eval`,
variable includes and backticks, so `Symbols::manifestOf()` produces the
inferred manifest `wp minn preflight` and the Extensions view print before
activation. A per-request `Runtime\Meter` keyed by attribution gives governor
limits.

**The journal.** `Ops\Journal` appends one JSON line per write at the funnels
(options on an allowlist, roles, plugin state, themes, updates with the
archive hash, sessions, language packs, cron outcomes) with an `Ops\Actor`
(user, agent key, plugin, extension, cron, CLI). The system view lists it as
a log source; `wp minn journal` and one ability read it. Today the only
durable change record is `minn_updates.archives`, overwritten per folder and
written for updates only.

**The staging primitive.** A SQLite dialect behind `Db` and `minn clone`.
One class and one verb; nothing above depends on it, and agent workflows will
pull on it early.

**The registry.** `Ops\BuildHash` (the registry's folder rule: every file
under the folder as `sha256  prefix/slug/path`, symlinks as `SYMLINK:target`,
`node_modules` and `.git` skipped, sorted bytewise, hashed with a trailing
newline), `Ops\Registry` (batch the hashes through the public check route,
cache verdicts twelve hours beside the update offers) and `Ops\Verdict`.
Preflight and the Extensions view show the verdict; `Updates::pluginOffers`
lists a salvage build from the public patches manifest when wordpress.org has
nothing. Facts that shape it, from the registry's own code: the public shard
answers carry a verdict and no slug, an all-embargoed build reads as clean and
must be shown as "no published findings", a per-site variant hash reads as
unaudited, single-file plugins are hashed by no current client, and nothing
today accepts an artifact keyed by hash from a site, so the manifest diff
needs a new origin route with a nameability gate.

## Legibility artifacts

What an agent reads before it reads PHP, and what is missing:

- **`contracts/api/routes.json`**, dumped from the typed route table: method,
  pattern, handler, docblock sentence, structured policy, args, refusal codes,
  the contract and the suite. It feeds the index, the OpenAPI export, the
  abilities bridge and a policy diff across releases. Today `minn.json` holds
  the policy as a sentence.
- **`contracts/index.json`**, generated from a short header in each contract
  (status, suite, fixtures, code, captured, reference) and checked by the
  style suite like the API docs. `contracts/README.md` promises `schema/`,
  `cli/` and `boot/` directories that do not exist, and the known-gaps
  sections of `rest/posts.md`, `rest/caps.md` and `front/permalinks.md` still
  say writes, application passwords and feeds are missing.
- **`contracts/fixtures/manifest.json`**: which capture made each fixture,
  when, against which reference. No fixture says today, and hosts are baked
  into 1,300 places on disk and normalised at compare time.
- **`tests/config.php`** over a documented `.env`, and `tests/README.md`. A
  third party cannot run the parity suites without editing: the `wp` path is
  absolute, 68 literal reference URLs, an admin password literal in six test
  files, and a customer-shaped dogfood host named in seven docs and
  contracts. Scrub both before the repo goes public.
- **`contracts/numbers.json`** from a tool, read by the site. The front page
  says 276 classes, 46 suites and 93 URL cases; the code has 357, 51 and 102,
  and `code-size.json` is 42 percent behind.
- **`routes` in `minn.json`**: an extension cannot register a REST route
  today, though the vision says the manifest declares routes. Named classes
  whose `#[Route]` attributes the loader registers on the same router put
  extensions in the catalogue and under the ratchet for free.

## Where the confidence stops

Every finding above was read at its cited line; the counts are from grep and
from the tools. The cron gap was confirmed by grepping for callers of the two
functions. Nothing was probed against the reference WordPress, so the
refusal-order divergences in B7 are consistent within Minn and plausible
against the reference, not proven. The landscape claims in
`docs/agent-first.md` are dated research. Nothing in the repository was
modified by the review itself; the two documents are the only change.
