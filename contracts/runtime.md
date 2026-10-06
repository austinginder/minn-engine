# The WordPress runtime

How plugin code written for WordPress runs on the engine, unmodified. Code:
`public/minn/src/Minn/Runtime/` (the services) and `public/minn/wp-api/` (the
procedural facade plugins call). Suites: `tests/hooks.test.php` (114),
`tests/api.test.php` (1,888: five probe transcripts at parity plus every
facade signature against the inventory), `tests/runtime.test.php` (24: an
unmodified fixture plugin on real pages and its REST routes diffed against
the reference).

## The shape

Plugins are PHP that calls WordPress functions and hooks into lifecycle
actions. The engine provides those functions itself, as thin delegations into
its own classes, and fires the actions from its own request path. Nothing
here is WordPress code: every function is written from
`contracts/api/*.json` (the interface, extracted by reflection) and
`contracts/fixtures/api/*.json` (behaviour, captured by probe batteries run on
the reference). Section 5 of `docs/vision.md` has the legal reasoning.
`contracts/lexicon.md` is the Speak / Hear / Mute policy: which WordPress
families the runtime implements, which it records so plugins boot, and
which it will never host. `/wp-admin/` is Mute. Admin registrations are
Hear. Do not graduate a mute placeholder into `wp-api/` to shrink the
skip list.

| Piece | Where | What it is |
|---|---|---|
| `Runtime\Hooks` | `src/Minn/Runtime/Hooks.php` | the hook registry: add/remove/has, filter/action, the current-filter stack, did counts, the `all` hook |
| `Runtime\Runtime` | `src/Minn/Runtime/Runtime.php` | one per request: db, site, request, reader, capabilities, engine and site paths; loads the facade, defines the constants, holds plugin-visible state |
| `Runtime\Options` | | options as PHP values (decoded without `unserialize()`, objects become `stdClass`), cached for the request so a value written then read keeps its type |
| `Runtime\ObjectCache`, `Runtime\Shortcodes`, `Runtime\Assets` | | the per-request object cache; the shortcode registry and expansion; the script/style registry |
| `Runtime\Constants` | | the fixed constants from `data/constants.json` (captured) plus the per-site ones computed here; never overrides wp-config.php |
| `Minn\Context` | `src/Minn/` | one request as a value: db, site, request, reader, capabilities, and the two paths; built once at the front door, `withReader()` for the surface that resolves its reader later |
| `Runtime\Symbols` | | the static symbol read that gates loading (below) |
| `Runtime\Registry`, `Runtime\PostQuery` | | post types, taxonomies, statuses (data/registry.json + registrations); the SELECT behind WP_Query |
| `Rest\RuntimeRoutes` | `src/Minn/Rest/` | plugin routes answered after the engine's own; the runtime's namespaces folded into the index |
| `Runtime\Plugins` | | loads mu-plugins then `active_plugins`, fires the lifecycle |
| `wp-api/*.php` | | the facade: `hooks`, `load`, `option`, `plugin`, `l10n`, `formatting`, `kses`, `link-template`, `user`, `meta`, `shortcodes`, `pluggable`, `theme`, `functions`, `post`, `taxonomy`, `query`, `media`, `comment`, `cron`, `http`, `widgets`, `template`, `upgrade`, `rest-api`, `script-loader`, `admin`, `customize`; `classes/` holds `WP_Error`, `WP_User`, `WP_Role`/`WP_Roles`, `WP_Post`, `WP_Term`, `WP_Post_Type`, `WP_Taxonomy`, `WP_Query`, `WP_Comment`, `WP_Theme`, `WP_Screen`, `WP_Widget`/`WP_Widget_Factory`, `WP_Image_Editor`(+`_GD`), `WP_Http`/`WP_HTTP_Response`/`WP_Http_Cookie`, `WP_REST_Request`/`Response`/`Server`/`Controller`, `wpdb`, `WP_Scripts`/`WP_Styles`, `NOOP_Translations`; `defaults/filters.php` holds the reference's own registrations |
| `data/constants.json`, `data/kses.json`, `data/mime.json`, `data/registry.json`, `data/api-names.json` | | captured tables: constants, the kses allowlists and entity names, the mime map, the post type / taxonomy / status registries with the query-var template, and the interface's function and class names (what the symbol gate counts) |

The facade is deliberately procedural (global functions, loose types, no
`declare(strict_types)`): it is the interface plugins were written against.
`docs/style.md` governs `src/Minn/`; the facade is outside it and its own
rules are: every function's signature matches the inventory (the suite
checks all of them), every behaviour is pinned by a probe row, no WordPress
source is ever consulted.

## Loading

`Runtime::boot()` runs in `Engine::respond()` after the reader is known and
before the engine's own extensions register. Then `Plugins::load()`:

1. every `wp-content/mu-plugins/*.php`, alphabetically, then `muplugins_loaded`
2. every entry of `active_plugins`, in stored order, then `plugins_loaded`,
   `sanitize_comment_cookies`, `setup_theme`, `after_setup_theme`, `init`,
   `wp_loaded` (the order the reference fires them; `contracts/api/lifecycle.json`)

`minn-admin/minn-admin.php` is never loaded as code: the engine answers that
plugin itself. Each file is included in a clean scope, once. A plugin that
throws while loading is logged and skipped.

**One context per request (2026-09-02).** `Minn\Context` is the request as a
value: the database door, the site's options, the request, the reader, the
capability engine, and the engine and site paths. `Engine::respond()` builds
exactly one, before the surfaces branch; REST and the front each resolve
their own reader (the nonce-bound caller, or the session cookie) and ask for
`withReader()` rather than building a second graph. `Runtime` takes that
context instead of nine loose arguments and copies its values onto the
properties plugin code reaches for by name (`Runtime::current()->db` and the
rest are unchanged), so the runtime and the engine cannot disagree about
what request they are answering. `Runtime::contentDir()` and `isSecure()`
are the context's answers.

The extension seams live on the runtime too (`useSeams()` / `seams()`);
`Extension\Extensions` is now a lookup with no state of its own, so a
request cannot inherit the extensions of the one before it. `Reader` holds
nothing either: `Reader::current()` reads the runtime's reader, and without
a runtime (the command line, unit tests) nobody is reading, which is the
default those paths already had. `Engine::frontPipeline` takes the context
in place of the database, the site, and the capability engine.

The render pipeline holds nothing of its own either (2026-09-02). The
runtime carries the request's `RenderState` (`renderState()` /
`useRenderState()`, the counters that number galleries, style variations
and search inputs across a whole response) and its block `Renderer`
(`blockRenderer()`, built over that request's own database door), so a
request cannot count on from the one before it. `Layout`'s root-padding
flag is render configuration on the state rather than a static of its own,
and `reset()` leaves it alone because it describes the theme, not the page.
`Db::current()` is the request's connection when a runtime is booted and
the process's otherwise, and the deep render sites that reached for
`Db::shared()` (the two theme block classes, `Front\Permalinks`,
`Mail\Mailer`) ask it instead; `Db::shared()` is now only what opens a
connection, at the front door and on the command line.

Rendering with no request behind it (the command line, the unit suite)
shares one off-request state and renderer, because counters have to survive
between calls; that is the documented fallback in `RenderState::current()`
and `Content\Blocks::renderer()`, and it is the same behaviour those paths
had before.

Still ambient: nothing per-request outside the runtime. What remains is the
threading itself, which would let the leaves take a render context as an
argument instead of asking `RenderState::current()` (about twenty call
sites across `Layout`, `Wrapper`, `Elements`, `ImageTags`, `GlobalStyles`
and the facade's `media.php`), and would end the core's one question to the
runtime in `Db::current()`.

**The kernel is a function of the request (2026-09-02).** `Engine::serve()`
is the only place the engine touches the world: it installs the failure
handlers, asks `Engine::answer(Request): Response` for the response, and
sends it. `answer()` returns the response for anything that can happen (a
database that cannot be reached, salts that are not set, a failure
anywhere underneath), and `Engine::handle()` under it returns the response
of whichever surface owns the request. Nothing below the front door
returns `never` any more, and `Response::send()` writes headers and body
without `exit`, so the call sites return rather than relying on the
process ending.

A failure is answered in the language the request asked in:
`Failure::report()` for a page, `Failure::reportJson()` for a REST route
(the reference's error object, `internal_server_error` at 500), so Minn
Admin no longer gets HTML on a 500. Neither says anything about the cause
unless the site has debug display on; the cause always goes to the log.
Pinned by `tests/unit/failure.php`.

**Worker mode is still not reachable, and it is not static hygiene that
blocks it.** Answering several requests from one process was tried
directly: the responses are correct, but the same page rendered twice in a
process differs by the script modules the first render marked as printed.
Starting each boot with empty registries fixes that and breaks far more:
plugin files register their hooks as they are included, `include_once`
means an include happens once per process, so the second request gets a
hook table no plugin can fill again and loses every plugin and theme
registration (a 59 KB page became 34 KB). Until a plugin's registrations
can be replayed, a second boot inherits the first's registries on purpose;
`Runtime::boot()` says so where a reader will look for it. What worker
mode needs is plugin re-execution, not tidier statics.

**Operations are their own namespace (2026-09-02).** `Minn\Ops` holds what
the engine can be asked to *do*, with nothing of the admin client in it:
`Packages` (put a theme, plugin or extension on disk, or take one off),
`Updates` (ask wordpress.org, apply an offer, record the archive's hash),
`Diagnostics` (system, cron, autoload), `Logs`, `InstalledSoftware`, and
`CoreStatus`. Each takes only a site, a database door, or a path, so the
CLI, cron, a REST controller and an ability can all call the same class;
`Cli\*` and `Cron\Cron` already do, which is what the split records.

`Minn\Admin` keeps what belongs to Minn Admin itself: the
`minn-admin/v1` controllers, the boot payload, the app bundle, the
appearance and hidden-integration preferences, and the overview's
dashboard, feed and formatting. The cut is by dependency, not by taste:
`Translations` stayed in `Admin` because it reads the Minn Admin bundle
for its catalogue, and `Format` stayed because it is the dashboard's own
number and date formatting. `LanguageChoices` is in neither place
properly (it renders a select for the facade's `wp_dropdown_languages`)
and is left where it is, named here so it is not mistaken for settled.

**The symbol gate.** Before a plugin folder is included, `Runtime\Symbols`
tokenises every PHP file in it and lists the global functions it calls and
the classes it instantiates, extends, or reads statically, minus what the
plugin itself declares and what it guards with `function_exists()` /
`class_exists()`. Only names in the reference's own interface
(`data/api-names.json`) count: a plugin's integrations with other plugins
and PHP extensions are its business. If anything in that list is not
provided by the runtime,
the plugin is not loaded and the list is recorded (`Plugins::skipped()`).
The verdict has a third list, `redeclares`: functions the folder declares
without a `function_exists()` guard that the runtime already defines. On
the reference a plugin may redefine a pluggable (`wp_mail`,
`wp_authenticate`, and the rest of `pluggable.php`) because that file
loads after the plugins; the facade loads first, so such a plugin would
not compile. Only a collision in the plugin's MAIN file refuses the load
(with the collision named): a redeclaration elsewhere in the folder may
sit behind a conditional include the static read cannot follow, and
WooCommerce's Abilities API polyfill is exactly that, so the folder-level
list is advisory (`runtime-report.php` and `compat-scan.php` print it,
`loads` ignores it). A collision that really compiles ends in the
recovery path like any other boot fatal. Hosting the override (loading
the pluggable set after the plugins, as the reference does) is the real
fix and is a runtime milestone. A method named like a global function is
never a declaration: the reader tracks class, trait, interface, enum, and
anonymous-class bodies by brace depth. The class half of the read (`new`, `extends`,
`implements`, `instanceof`, `::`) is proven by `tests/unit/symbols.php`
along with the rest of the verdict; it had silently reported nothing
before that suite existed.
The read is cached in the `minn_runtime_symbols` option, keyed by the
folder's newest modification time and the reader's own version
(`Symbols::READER`), so a change to the token reader retires every cached
scan; the option is one the facade refuses to write (see "Options" below),
so plugin code cannot forge its own verdict. This is what keeps a site rendering when
a plugin needs a piece of the runtime that does not exist yet, and it is the
list the next piece of runtime is built from.
`MINN_SITE_ROOT=<root> php tests/tools/runtime-report.php` prints the
per-plugin verdict for any site.

**Stand-ins step aside.** An engine extension whose manifest `replaces` a
plugin file registers only while that plugin is not running as code.

## The lifecycle on a themed page

`PageRenderer` fires `template_redirect` before rendering, `wp_head` in the
head, `wp_footer` before `</body>`, and applies `body_class`; post content
and feed content run the runtime's shortcodes and then the `the_content`
filter after the engine's own pipeline (blocks, texturize, autop, the
extension seams). `shutdown` fires from a PHP shutdown function.

Inside `wp_head` the reference's own registrations run at their priorities:
`wp_enqueue_scripts` at 1, `wp_print_styles` at 8, `wp_print_head_scripts`
at 9; inside `wp_footer`, `_wp_footer_scripts` at 20. The engine's own
stylesheets (blocks.css and the global styles) print from a callback at
`wp_head` priority 8 registered after `wp_print_styles`, so plugin styles
come first and a plugin's `wp_head` output at a lower priority precedes
them, as on the reference. The theme's `style.css` is not the engine's to
link: the reference links it only when the theme enqueues it, so the theme
does that itself (below).

**The active theme's `functions.php` (2026-08-29).** Between `setup_theme`
and `after_setup_theme`, as the reference's lifecycle shows, the runtime
includes the child theme's `functions.php` and then the parent's, each through
the same symbol gate as a plugin folder (reported as `theme:{slug}` by
`runtime-report.php` when it cannot load). Templates, parts, and patterns never
run as PHP; a block theme's `functions.php` only registers hooks, enqueues its
stylesheet, and registers block styles, pattern categories, and bindings. This
retired the last site extension on dogfood: the child theme's Open Graph
block now prints from its own `wp_head` callback.

**Not in the defaults, by design:** the reference's `the_content` /
`the_title` / `the_excerpt` chains (`wptexturize`, `wpautop`, `do_blocks`,
`do_shortcode`, …). That pipeline is the engine's own, so `the_content` fires
on the rendered HTML for plugin callbacks only. A plugin hooking
`the_content` below priority 10 to see raw content sees rendered content
instead. Known gap.

## Facts the probes settled

- **Hooks** (`contracts/fixtures/api/hooks.json`): callbacks run by
  ascending priority then insertion; one added at a higher priority during
  a run takes part in that run, one added at the current or a lower
  priority waits; one removed before its turn is skipped; `has_filter`
  with a callback returns its lowest priority; string and int priorities
  share a bucket; `'Class::m'` and `['Class', 'm']` are one callback; the
  `all` hook sees every firing with the name first and every argument
  regardless of `accepted_args`; `did_action` and `did_filter` count
  separately; nested application of the same hook is independent.
- **Options**: a missing option is `false`; `default_option_{name}` wins
  over the passed default; `pre_option_{name}` short-circuits; a written
  value keeps its PHP type when read again in the same request (`5` stays
  int, `false` stays false, `null` reads back as `''`); `update_option`
  returns false when nothing changed; a transient with no expiry has no
  timeout row; an expired transient is deleted on read. The options the
  engine keeps for itself (`Runtime\Options::GUARDED`: the symbol-gate cache
  `minn_runtime_symbols`, `minn_recovery_strikes`, `minn_cron_lock`, and the
  `minn_login_throttle_*` rows) are refused through `add_option`,
  `update_option` and `delete_option` (false, no hooks fired); reads are
  ordinary. A plugin could otherwise rewrite the cache that gates its own
  load.
- **Escaping**: `esc_html` and `esc_attr` are identical, keep known named
  entities and numeric references, and encode unknown ones (`&bogus;` →
  `&amp;bogus;`); `esc_textarea` double-encodes; `esc_js` strips
  backslashes, escapes quotes, and turns newlines into `\n`; `esc_url`
  prepends `http://` to a bare host, refuses unknown schemes and
  `javascript:`/`data:`, encodes `&` as `&#038;` and `'` as `&#039;` in the
  display context only, and strips `"`, `<`, `>`, and NUL.
- **Sanitising**: `sanitize_text_field` escapes a `<` that starts a
  tag-looking run, strips tags with their script/style bodies, collapses
  whitespace, and removes percent-encoded octets; `sanitize_title` runs
  through the `sanitize_title` filter, where the default
  `sanitize_title_with_dashes` lives (so a plugin can remove it);
  `sanitize_email` keeps the local part's case and drops brackets.
- **kses**: stray `<` and `>` in text become entities; `<script>` loses its
  tags but keeps its text; `style` keeps only the listed properties (the
  engine's own `Support\Kses`); the allowed-tag table is
  `data/kses.json`, captured from `wp_kses_allowed_html('post')`, for the
  facade and the REST write paths alike; numeric entities normalise to at
  least three digits (`&#65;` → `&#065;`). The tag pass in detail: "The
  kses tag pass" below.
- **URLs**: `home_url`/`site_url` honour `http`, `https`, and `relative`;
  `admin_url` is https when the stored siteurl is (`FORCE_SSL_ADMIN` is
  defined true then); `content_url`, `plugins_url`, and the theme URIs take
  the request's scheme; `plugins_url()` with an absolute URL as the path
  appends it anyway; a script's relative `src` is prefixed with the stored
  siteurl verbatim (`rel/a.js` becomes `…localhostrel/a.js`, as on the
  reference); `add_query_arg` encodes keys and leaves values raw. The dev
  environment's helper mu-plugin rewrites `home`/`siteurl`; the probe
  removes its filters on both stacks first.
- **Users**: `WP_User->data` carries the ten user columns; `roles` and
  `allcaps` come from the capabilities meta over the site's roles; an
  anonymous `WP_User` answers `false` for unknown properties; `is_super_admin`
  is `delete_users`; `current_user_can('exist')` is true for everyone.
- **Shortcodes**: `[tag/]` gives an empty attribute array, `[[tag]]` is the
  literal, the first `[/tag]` closes and content is not re-expanded,
  `shortcode_parse_atts` keys bare words and quoted values by position.
- **Assets** (`contracts/fixtures/api/admin.json`): a script tag is
  `<script id="{h}-js" src="{src}"></script>` (id first); `ver=false` adds
  the reference version, `null` adds nothing; localized data prints in
  `{h}-js-extra` with every scalar cast to string (`5` → `"5"`, `false` →
  `""`); inline code prints in `{h}-js-before`/`-after` and `{h}-inline-css`;
  under `WP_DEBUG` each inline block ends with a `sourceURL` comment
  (unverified with `WP_DEBUG` off); `defer` prints `data-wp-strategy="defer"
  defer`, `async` prints `async data-wp-strategy="async"`; registering a
  handle twice is refused.
- **Admin registrations**: `add_menu_page` returns `toplevel_page_{slug}`
  and records `[menu_title, cap, slug, page_title, 'menu-top …', hook,
  icon]` in `$menu`; `add_submenu_page` and the `add_*_page` wrappers
  return false (and record nothing) when the current user lacks the
  capability; hook names for the core parents are the reference's
  (`settings_page_{slug}`, `appearance_page_{slug}`, …);
  `remove_menu_page` returns the row; `add_meta_box` stores
  `{id, title, callback, args}` and `remove_meta_box` stores `false`;
  `wp_add_dashboard_widget` needs a current screen; the Settings API
  markup (`settings_fields`, `do_settings_sections`, `settings_errors`,
  `get_submit_button`) is pinned row by row.

## The content, media, and REST layers (E2)

Probes: `tests/tools/{content,media,rest}-probe.php`, fixtures
`contracts/fixtures/api/{content,media,rest}.json`. The rows below are the
facts that shaped the implementation.

- **Posts**: `WP_Post` casts `ID`, `post_parent`, `menu_order` to int and
  keeps `post_author` and `comment_count` as strings; `ancestors`,
  `page_template`, `post_category`, `tags_input` are dynamic reads.
  `wp_insert_post` leaves `post_name` empty and `post_date_gmt` zero for
  drafts, assigns the default category to a published post, refuses only
  when title, content, and excerpt are all empty (`empty_content`), and
  creates a post of an unregistered type without complaint; it expects
  slashed input. `wp_update_post` saves a revision through
  `wp_insert_post`, so the last `save_post` a listener sees on an update is
  the revision's (`$update` false). `wp_trash_post` returns the object as it
  was before trashing and stores the previous status in
  `_wp_trash_meta_status`; `wp_untrash_post` restores to draft.
  `wp_delete_post` fires `before_delete_post` for the post and each revision.
- **WP_Query**: sticky posts ride on top of page one of a home query
  (never counted in `found_posts`, never on `fields => ids`, only when they
  match the queried post type); a negative `cat` does not make the query a
  category archive; `nopaging`/`-1` disable paging; `no_found_rows` gives
  zero `found_posts`; an unrun main query has empty `query_vars`, so
  `get_query_var('paged')` is `''` and every conditional tag is false on
  the command line. `get_posts` ignores stickies and counts nothing;
  `get_pages` orders parents before their subtrees.
- **Terms**: `term_exists` answers strings (`term_id`, `term_taxonomy_id`)
  and a bare string id when no taxonomy is given; `wp_set_object_terms`
  returns existing terms' ids as strings and newly created ones as ints;
  `get_term(0)` is `WP_Error invalid_term "Empty Term."`; a duplicate name
  under a different parent is allowed in a hierarchical taxonomy and gets a
  `-2` slug; deleting the default category returns `0`;
  `get_the_category` adds the legacy `cat_ID`/`category_*` names.
- **Types**: `register_post_type` derives `rest_base` false,
  `query_var` = name, `rewrite {slug, with_front, pages, feeds, ep_mask 1}`,
  the `post` capability map, `_builtin` false, `map_meta_cap` true; a name
  with spaces is registered under its sanitized key, a name over 20
  characters is refused (`post_type_length_invalid`); built-in types cannot
  be unregistered; the labels template is the `post` type's with name,
  singular, menu_name replaced.
- **Media**: `wp_create_image_subsizes` makes `medium` and `large` first,
  then the rest in registration order, saving metadata after each so the
  final `wp_update_attachment_metadata` returns false; sizes equal to the
  original are skipped; `image_resize_dimensions` returns false for a
  same-size or upscaling request; `wp_get_attachment_image_src` with an
  array size picks the smallest registered size that covers it and
  constrains it; an image is lazy by default outside a template and an
  explicit `loading => false` takes the one `fetchpriority="high"`;
  `srcset` lists the meta sizes in order then the full file; the guid of an
  inserted attachment is its attachment page; `wp_prepare_attachment_for_js`
  lists `thumbnail, medium, large, full` only.
- **Cron**: the `cron` option holds `{ts: {hook: {md5(serialize(args)):
  {schedule, args, interval?}}}, version: 2}`; identical recurring events
  at different timestamps are allowed; a single event within ten minutes
  of an identical one is refused; `wp_unschedule_hook` returns the count
  removed; the engine's `wp_cron()` runs due hooks (single events removed,
  recurring rescheduled, then the action fires). `wp_cron()` is called from
  `Cron::run()` (through an injected closure, since firing needs the booted
  runtime) on every wp-cron.php hit, on `wp minn cron`, and after the response
  of a front request that found a due post or event; the `wp cron event *` verbs
  (`Minn\Cli\CronCommand`) boot the full runtime and operate the same option.
  See `contracts/cron-mail.md`.
- **HTTP**: `wp_remote_*` returns `{headers (CaseInsensitiveDictionary),
  body, response {code, message}, cookies, filename, http_response}` or
  `WP_Error http_request_failed`; a relative `src` given to the script
  loader is prefixed verbatim; `wp_safe_*` refuses loopback and private
  hosts; the client sets `CURLOPT_NOSIGNAL` so a resolver timeout honours
  the request timeout (30 s otherwise on macOS).
- **Comments**: `get_comments_number` returns the post row's string count
  (int 0 for a missing post); `wp_update_comment` returns 1, false, or
  `invalid_comment_id`; a first delete trashes.
- **REST**: `register_rest_route` before `rest_api_init` is fine (the server
  is created lazily and fires the action once); a second registration
  without `override` appends endpoints and the first keeps answering; the
  namespace root is registered with the first route; `get_routes` shapes
  every endpoint as `{methods: {GET: true}, accept_json, accept_raw,
  show_in_index, args, callback, permission_callback}`; a missing
  permission callback allows the request; `false` from it is
  `rest_forbidden` 401 (403 signed in); required checks run before
  validation; an argument without a schema `type` is accepted as given on
  a route while `rest_validate_value_from_schema` still checks a bare
  `enum`; parameters merge defaults first then URL, query, body, JSON;
  `get_params` puts defaulted keys first; errors from callbacks become
  responses with the status from their data (500 and `data: null` when
  bare, `additional_errors` for the rest); the engine's own `wp/v2`
  routes serve `rest_do_request` in-process, running as the outer
  request's user (an in-process request carries no nonce; the engine's
  caller is settled from the runtime's reader), and `register_rest_field`
  additions are attached to their items.
- **What plugin code decides before the engine answers** (suite
  `rest-gate`, 9, against the reference with the same fixture plugin):
  `rest_authentication_errors` may refuse any request, engine routes and
  the index included; `rest_pre_dispatch` may answer any request outright;
  a route a `rest_endpoints` filter removed is `rest_no_route` even when
  the engine has a handler for it. `RuntimeRoutes::gate()` runs the three
  before the engine's router, in the reference's order. A plugin route
  registered under `wp/v2` answers: the engine's declared-types catch-all
  declines an unknown base (`Http\RouteMiss`, which the router swallows
  to try the next route) instead of answering no-route itself. Not yet
  applied to engine routes: `rest_request_before_callbacks`,
  `rest_request_after_callbacks`, `rest_post_dispatch`.
- **Blocks** (`contracts/fixtures/api/blocks.json`): `parse_blocks` is the
  engine's own parser as arrays; `serialize_block_attributes` writes
  lower-case hex escapes for `<>&"` and `--`; `register_block_type` refuses
  upper-case or unnamespaced names and a second registration of the same
  name (`false`), adds `lock` and `metadata` attribute schemas, and turns
  `editor_script`/`style`/... strings into the `*_handles` lists; a
  registered dynamic block renders through its callback on the engine's
  front end too (`_minn_bridge_dynamic_block` registers it with the
  engine's renderer) and a static core block inside a plugin's block takes
  its classes from the engine's renderer; `get_block_wrapper_attributes`
  puts the caller's class first, then align, className, the default
  class, the color classes, then `id` from `anchor`, then the caller's
  other attributes, and prints no `style` when a preset slug is given;
  `block.json` registration names handles `{name-with-dashes}-editor-script`
  etc., reads `*.asset.php` for dependencies and version, and does not
  carry the file's `textdomain` onto the type; `prepare_attributes_for_render`
  drops attributes that fail their schema and fills defaults;
  `excerpt_remove_blocks` keeps freeform text and renders only the
  allowlisted blocks; `WP_Rewrite` is recorded, never used to resolve
  (`flush_rewrite_rules` writes nothing).
- **Templates**: `load_template` gives the file the query vars and the
  globals (`$post` stays unset with no post set up); `locate_template`
  looks in the child then parent theme; `get_template_part` returns false
  when nothing matched.

## Block filters, interactive blocks, and script libraries

Landed after the dogfood site's plugins started loading as code and its
pages diverged (block-visibility, mosne-dark-palette, a jQuery-dependent
admin script). Fixture: `contracts/fixtures/api/interactivity.json`
(72 rows, `interactivity-probe.php`).

- **Every block the engine renders passes through the plugin-facing
  filters**: `pre_render_block` (a non-null return replaces the block),
  `render_block_data` (the parsed array a plugin returns is what the
  engine renders, and keys it added ride along to `render_block`),
  `render_block`, then `render_block_{name}`, each with a `WP_Block`
  instance. The engine's own dynamic blocks are no exception, so a
  visibility plugin can hide a core group. `Runtime\BlockFilters` is the
  bridge; `WP_Block::render` of a static core block returns the engine's
  rendering without applying the filters a second time.
- **Images a filter removes give their loading budget back.** The
  reference charges its eager-image budget on the final content, after
  filters ran; the engine charges at block render, so a block whose
  filtered output lost `<img>` tags refunds them (and the high-priority
  slot if it went with them). A plugin's `wp_get_attachment_image` inside
  a page render draws on the same budget as the engine's own images.
- **Directive processing** (`wp_interactivity_process_directives`, and
  automatically on the outermost interactive block once its inner blocks
  rendered): `data-wp-interactive` sets the namespace (a string or
  `{"namespace":..}`; empty sets none), `data-wp-context` merges a JSON
  layer scoped to the element and its subtree (an `ns::` prefix targets
  another namespace; invalid JSON is ignored), a path is `state.a.b` or
  `context.a.b`, optionally `ns::`-prefixed or `!`-negated; a Closure
  in state is called during evaluation, with `wp_interactivity_get_context`
  and `wp_interactivity_get_element` (`['attributes' => [...]]`) answering
  for that element. `data-wp-bind--attr`: null removes the attribute,
  false removes it unless the attribute starts with `aria-` or `data-`
  (those get `"false"`; true gives `"true"`), true on any other attribute
  is the bare name, scalars are set escaped; an array logs
  `doing_it_wrong` and changes nothing. A replaced attribute keeps its
  position; a new one is inserted right after the tag name, after earlier
  insertions; a removal takes only the attribute's text (the space before
  it stays). `data-wp-class--x` appends or removes one class (an empty
  list removes the attribute); `data-wp-style--prop` rewrites `style` as
  `prop:value;` declarations, the bound one moved to the end, a falsy
  value dropping it; `data-wp-text` replaces the inner HTML with the
  escaped scalar (booleans, null, arrays give an empty element);
  `<template data-wp-each="path">` (or `data-wp-each--key`) clones its
  contents per item with `data-wp-each-child="ns::path"` inserted first on
  each top-level tag and `context.item` (or the key) set, unless the next
  sibling already carries `data-wp-each-child`; template contents are never
  processed in place. Uppercase tags and attributes match; unquoted values
  work; markup that does not close what it opens comes back untouched. A
  path without a namespace or without a reference logs `doing_it_wrong`.
  Outside processing `wp_interactivity_state()` with no namespace,
  `get_context`, and `get_element` log and return `[]`/`null`.
- **Script handles the reference registers itself**: `jquery-core`
  (`/wp-includes/js/jquery/jquery.min.js?ver=3.7.1`), `jquery-migrate`
  (`?ver=3.4.1`), and the `jquery` alias that depends on both. The engine
  ships the MIT-licensed files under `assets/vendor/jquery/` (jQuery from
  the jQuery CDN plus the `jQuery.noConflict();` line the reference appends,
  byte-identical to the reference's copy; Migrate unmodified) and serves
  them at the reference's paths. A handle whose dependency is unregistered
  never prints, nor does anything that depends on it.

## Script modules and the client runtime

Fixture: `contracts/fixtures/api/script-modules.json` (51 rows,
`script-modules-probe.php`); the engine's module URLs are masked to their ids
on both stacks because the engine serves its own files.

- **Registry** (`wp_register_script_module` etc., `WP_Script_Modules`):
  the first registration of an id wins; dependencies are strings or
  `['id' => .., 'import' => 'static'|'dynamic']` (default static); a missing
  dependency logs `doing_it_wrong` at registration and the module never
  prints; an invalid `fetchpriority` logs and falls back to `auto`;
  `get_registered` answers `src, version, dependencies, in_footer,
  fetchpriority`; enqueueing an unregistered id sits in the queue and prints
  nothing; `enqueue` with a src registers first.
- **Printing**: only enqueued modules print `<script type="module">` tags,
  attributes in alphabetical order (`data-wp-router-options`, `fetchpriority`
  unless auto, `id` `{id}-js-module`, `src`, `type`), once each (`done`);
  `print_head_enqueued_script_modules` takes the ones not `in_footer`,
  `print_enqueued_script_modules` the rest. The import map lists every
  registered dependency (static and dynamic, depth first in declaration
  order, of enqueued modules including already printed ones), never the
  enqueued modules themselves, and prints nothing when empty. Preloads
  (`rel, href, id, fetchpriority`) cover static dependencies that are not
  themselves enqueued. URLs: `?ver=` from the version (false takes
  `wp_version`, null adds none) through `esc_url`, so a bare `c.js` prints
  as `http://c.js` and `&` as `&#038;`.
- **Data**: `print_script_module_data` applies `script_module_data_{id}` to
  every enqueued module and dependency and prints the non-empty ones as
  `<script id="wp-script-module-data-{id}" type="application/json">` with
  `<`/`>` hex-escaped and slashes and quotes plain; it is not marked done.
  The interactivity runtime's data is `{config, state}` (non-empty parts,
  config first); the router's is its two i18n strings. The a11y live-region
  markup prints once the `@wordpress/a11y` tag has printed.
- **Hooks**: `wp_head` 10 import map, head modules, preloads; `wp_footer`
  10 modules and data, 20 a11y, 21 translations; `admin_print_footer_scripts`
  9 import map, 10 modules, preloads, data, 11 translations, 20 a11y.
- **Defaults** the reference registers (all `in_footer`, `fetchpriority`
  low): `@wordpress/interactivity`, `@wordpress/a11y`,
  `@wordpress/interactivity-router` (a11y dynamic, interactivity static),
  and the block-library view modules for navigation, image, query (router
  dynamic), search, file, form (no deps), accordion, tabs, playlist, each
  carrying `data-wp-router-options="{"loadOnClientNavigation":true}"`. The
  engine registers the same ids against `/minn/assets/*.js`.
- **block.json**: `viewScriptModule` registers through
  `register_block_script_module_id` (id `{name-with-dashes}-view-script-module`,
  `-{n}` from the second entry; a non-`file:` value is used as the id as is;
  the `.asset.php` beside the file supplies dependencies and version) and
  `WP_Block::render` enqueues `view_script_module_ids`. The engine's own
  navigation block enqueues `@wordpress/block-library/navigation/view`
  whenever it emits interactive markup.
- **The client runtime is the engine's own** (`assets/interactivity.js`,
  MIT, no Preact): reactive proxies with effect tracking, per-element
  context layers that inherit and write through to the parent, `store()`
  merging with getters kept as getters and generator actions driven with
  their scope, server state from the data script, and the directives
  `interactive`, `context`, `bind`, `class`, `style`, `text`, `on`,
  `on-window`, `on-document`, `init`, `watch`, `each` (server-rendered
  children hydrated, client re-render on change). Exports match the
  package's names; the Preact hooks throw a clear error. The navigation
  view module (`assets/navigation-view.js`) implements the `core/navigation`
  store the block's markup names; `a11y.js` and `interactivity-router.js`
  are minimal (the router navigates for real). Verified by
  `tests/browser/interactivity.test.js`: the dogfood site's mobile overlay
  and a third-party plugin's toggle (mosne-dark-palette) run on it.

## The HTML API: the tag processor

Fixture: `contracts/fixtures/api/html-tag-processor.json` (62 rows,
`html-tag-processor-probe.php`). `WP_HTML_Tag_Processor` maps onto
`Minn\Html\Tags` (a streaming tokenizer with in-place edits) and
`Minn\Html\Decoder` (character references).

- **Tokens** (`next_token`): `#tag` (name uppercase; closers and the
  `/>` flag reported; a slash inside an unquoted value is not a flag),
  `#text`, `#comment` (`<!-- -->` and `--!>`, abruptly closed `<!-->` and
  `<!--->`, bogus `<!...>` as `COMMENT_AS_INVALID_HTML`, `<![CDATA[..]]>` as a
  CDATA lookalike, `<?xml ..?>` as a PI lookalike with the text after the
  target), `#doctype` (token name `html`, modifiable text everything after
  `<!DOCTYPE`), `#processing-instruction` for `<?php ... ?>` only (tag
  `php`), `#funky-comment` for `</3>` and `</ p>`. `</>` is skipped. Raw
  text elements (script, style, textarea, title, and the other four) hold
  their content as modifiable text and their closer is never visited; an
  unterminated token pauses the processor (`paused_at_incomplete_token`)
  and leaves the source unchanged. `next_tag` skips text and, unless
  `tag_closers => 'visit'`, closers; `tag_name` compares uppercase,
  `class_name` exactly, `match_offset` counts from 1 and 0 matches nothing;
  after a false the processor stays at the end.
- **Reads**: `get_attribute` decodes references (named, numeric, the legacy
  names without a semicolon, but not in an attribute when `=` or an
  alphanumeric follows: `&ampy` stays), answers true for a bare attribute,
  the first of duplicates, and null off an opener; names are lowercase;
  `get_modifiable_text` decodes text, textarea, and title but not script or
  comments. `has_class` and `class_list` are case-sensitive and see pending
  edits; `class_list` is distinct in source order.
- **Edits** are pending until the processor moves, seeks, or prints, and
  reads reflect them: a new attribute is inserted right after the tag name
  (each newer one before the earlier ones), an existing one is replaced in
  place with the caller's spelling and double quotes, values escape `"`,
  `<`, `>`, `&`, `'` (as `&apos;`) and double-encode existing references;
  true prints the bare name, false and `remove_attribute` delete the text of
  every duplicate leaving the whitespace, null is refused, invalid names
  (`bad name`, `x=y`, `a"b`, empty) are refused; removing an attribute that
  only exists as a pending set cancels the set and returns false.
  `add_class` appends with one space (an empty class appends nothing but its
  space), `remove_class` drops the class and the whitespace before it,
  inner whitespace otherwise survives, an emptied class attribute is
  removed, `set_attribute('class')` discards pending class edits and later
  class edits build on the new value. `set_modifiable_text` works on text
  (`&` and `<` encoded), comments, and raw text elements (script and style
  turn `</s` into `</\u0073`), never on other tags.
- **Bookmarks**: at most ten; `seek` applies pending edits, moves back,
  and re-reads the token; releasing or seeking an unknown name is false.

## The small symbols the dogfood plugins were short of

Fixture: `contracts/fixtures/api/symbols.json` (67 rows, `symbols-probe.php`).
Facts worth keeping:

- `use_block_editor_for_post_type`: the type must exist and be
  `show_in_rest`; attachments and revisions never qualify; then the filter.
- `htmlentities2` keeps named references and decimal numeric ones
  (`&#39;`) but not hex ones (`&#x41;` becomes `&amp;#x41;`); unknown names
  are encoded.
- Registered meta is keyed by object subtype: `register_post_meta`
  registers under the post type, a later registration of the same key
  replaces the first, `get_registered_meta_keys('post')` lists only the
  subtype-less keys, `default` is present only when given. The reference
  registers `footnotes` for every editor-supporting post type; the engine
  does the same for the built-in types at load.
- Sitemaps: `get_sitemap_url('index')` is `/wp-sitemap.xml`,
  `posts`/`taxonomies`/`users` build `/wp-sitemap-{name}-{subtype}-{page}.xml`
  (page 0 reads as 1; an unknown provider or subtype is false); the
  server object exposes `registry`, `renderer`, `index`, three providers,
  `sitemaps_enabled()` from `blog_public`, and `wp_sitemaps_get_max_urls`
  is 2000 through `wp_sitemaps_max_urls`.
- `get_edit_term_link` is `term.php?taxonomy=..&tag_ID=..&post_type=..`
  (the taxonomy's first object type unless one is given), null for an
  unknown term or taxonomy or when the user cannot `edit_term`.
- `feed_content_type`: rss and rss2 `application/rss+xml`, rss-http
  `text/xml`, atom `application/atom+xml`, rdf `application/rdf+xml`,
  anything else `application/octet-stream`. `get_bloginfo_rss` escapes the
  whole value as HTML, tags included, typographic quotes untouched.
- `delete_post_meta_by_key` deletes across every post and answers false
  when nothing matched or the key is empty.
- kses: `pre_comment_author_name`, `pre_term_description`, and
  `pre_link_description` always carry `wp_filter_kses`; `kses_init_filters`
  adds the three content save filters, `title_save_pre`, and comment
  content (post rules for users with `unfiltered_html`, plain kses
  otherwise); `kses_remove_filters` takes those five away and nothing else.
- Screen options: `add_screen_option` stores the args on the current
  screen (`get_option($name)` gives them back, `($name, 'default')` a key);
  `get_hidden_columns` reads `manage{screen}columnshidden` from the user's
  options and, without one, `default_hidden_columns`. `wp_dashboard_setup`
  fires its action. `wp_iframe` prints a `<!DOCTYPE html>` document with
  `wp-toolbar` on `<html>`, `wp-core-ui` on `<body>`, the callback's
  output, no admin bar; `iframe_header` titles the page `{site} &rsaquo;
  {title} &#8212; WordPress`; `iframe_footer` prints the auth-check
  markup, then closes the document.
- `WP_Term_Query` normalises its vars (taxonomy, include, exclude, name,
  slug to arrays; number and offset to integers), keeps `terms` null until
  `query()` or `get_terms()` runs, answers `[]` for an unknown taxonomy,
  and agrees with `get_terms()` for the same arguments by construction.
- A single-site reference never loads `WP_Site`, `WP_Site_Query`,
  `WP_Network`, or `WP_Network_Query`, so the symbol gate no longer counts
  them; `get_main_site_id`, `get_current_blog_id`, `is_main_site`,
  `get_current_network_id` answer 1, true, 1.
- Filesystem: `get_filesystem_method` is `direct`,
  `request_filesystem_credentials` true, `WP_Filesystem()` installs a
  `WP_Filesystem_Direct` (`WP_Filesystem_Base` parent) with the base
  directories under ABSPATH; `mkdir` of an existing directory is false,
  `copy` refuses to overwrite unless told, `delete` of a missing file is
  true and of a non-empty directory needs `recursive`, `getchmod` is three
  digits, `gethchmod` ten characters starting with `u` for a file, and
  `dirlist` entries carry `name, perms, permsn, number, owner, group, size,
  lastmodunix, lastmod, time, type` (plus `files` on directories).
- `wp_validate_boolean`: the string `false` in any case is false, everything
  else is a bool cast (`no`, `off`, `null` are true).
- `wp_image_src_get_dimensions` matches the URL's file name against the
  metadata's file and sizes regardless of host.
- `get_edit_user_link` is `profile.php` for the current user, `` for an
  unknown user or when the user cannot `edit_user`; `wp_get_post_revision`
  answers null for a missing post and for one that is not a revision.
- `delete_theme` of a theme that is not on disk is true; an empty slug is
  false. `_wp_oembed_get_object` is a singleton `WP_oEmbed` whose provider
  table is `data/oembed-providers.json` (captured from the reference).

## Connectors, the old-post comment closer, and plugin_loaded

Fixture: `contracts/fixtures/api/connectors.json` (68 rows, `connectors-probe.php`).
The probe stands the plugins and the lifecycle itself on a CLI boot of the
engine (`Plugins::load` fires init), because the reference has run them by the
time `wp eval-file` starts.

- **The registry** is `WP_Connector_Registry` over `Minn\Runtime\Connectors`.
  `_wp_connectors_init` runs at init 15: it registers the four core rows
  (anthropic, google, openai, akismet) and then fires `wp_connectors_init` with
  the registry, which is where plugins add theirs (Jetpack's `wordpress_com`
  card, Minn Admin's CleanTalk adapter). `_wp_register_default_connector_settings`
  and `_wp_connectors_pass_default_keys_to_ai_client` sit at init 20.
- **Registration is refused, in this order**, with `_doing_it_wrong` and a null
  return: an id outside `[a-z0-9_-]`, an id already registered, a missing or
  empty `type`, a missing or empty `name` (whitespace passes), a non-array
  `authentication`, a method other than api_key / application_password / none,
  a `plugin.is_active` that is not callable. Unknown keys are dropped; the
  stored row is name, description ('' unless a string), type, authentication
  (method, credentials_url, setting_name, constant_name, env_var_name, in that
  order whatever order the caller used), logo_url when given, plugin (file when
  given, then is_active, `__return_true` by default). An api_key connector
  without a setting name gets `connectors_{type}_{id}_api_key`. `unregister`
  returns the row it removed; a miss is `_doing_it_wrong` + null, as is
  `get_registered` of an unknown id. `set_instance` only takes effect during
  the init action; outside it the call is refused and the instance kept.
- **Keys**: `_wp_connectors_mask_api_key` shows keys of four characters or
  fewer whole, otherwise the last four behind at most sixteen bullets (the
  count is in bytes). `_wp_connectors_get_api_key_source` answers env, then
  constant (a string constant only), then database, else none; empty values
  do not count. Application-password credentials are "user:password" split at
  the first colon and trimmed (either half empty means both empty); the
  sanitiser accepts only an array of the two fields; the stored form the
  getter honours is that array (a "user:password" string in the option reads
  as none), after the env and constant sources.
- **What is not there**: `_wp_connectors_is_ai_api_key_valid` always refuses
  (there is no AI client registry); the provider logo resolver returns null;
  the script-module data and the settings-dispatch masking for the wp-admin
  page are not provided. A connector's key setting is registered only while
  its provider plugin is active, which the app reads as `registered`; the
  registration's args are the engine's own, no provider plugin having been
  captured.
- **Comments close on old posts** (`_close_comments_for_old_post`, on
  `comments_open` at 10): with `close_comments_for_old_posts` on and
  `close_comments_days_old` above zero, a `post` whose `post_date_gmt` is more
  than that many whole days old reads closed (fifteen days old stays open at a
  setting of fifteen), whatever its status. Pages and other types never close,
  and the `close_comments_posts_types` filter changed nothing on the reference.
  A missing post (and post id 0 with no global post) passes the value through.
  With the setting off, `get_option('close_comments_days_old')` on the
  reference is the default 14 when the row is absent; the engine returns
  false there (no option defaults yet).
- **`wp_update_post` keeps a stored `post_date_gmt`** across an update that
  names only `post_date`: the GMT column is recomputed only when it is zero.
- **`plugin_loaded` and `mu_plugin_loaded`** fire after each file with the
  file's path. Jetpack schedules its entire configuration (the connection
  manager, and with it the connector card) from `plugin_loaded`, so without it
  Jetpack loads but never configures.
- **The runtime server's route table includes the engine's own routes**
  (`WP_REST_Server::get_routes()` and `get_namespaces()` fold in
  `Rest\EngineRoutes`, the router's patterns in the reference's regex form;
  each entry answers through the engine). Plugin code reads that table to
  learn what the site serves: Minn Admin's comment detection treats a missing
  `/wp/v2/comments` as the feature being off, which hid the Comments view on
  the engine for the wrong reason.
- **`wp_is_file_mod_allowed`** is true unless `DISALLOW_FILE_MODS` says
  otherwise, through the `file_mod_allowed` filter (it was a placeholder,
  which made every connector's Install button disappear).

## E3 as placeholders: the admin host that is not there

Minn has no `/wp-admin/` and will not grow one; Minn Admin is the admin.
`contracts/lexicon.md` calls this Mute (the screens) and Hear (the
registrations). What plugins need is for admin *symbols* to exist so they
load and stay quiet. Three generated layers do that:

- **Placeholder symbols**: `tests/tools/stub-symbols.php` writes
  `wp-api/placeholders.php` and `wp-api/classes/placeholders/Placeholders.php`
  from the inventory for the names in `data/placeholder-symbols.json` (what
  the dogfood plugins asked for: `WP_List_Table`, `Walker`, the `IXR_*`
  family, `PclZip`, the upgraders, `WP_User_Query`, `WP_Session_Tokens`,
  the REST controller classes, the POMO classes, 141 functions). Each has the
  inventory's signature and returns the neutral value of its documented type
  (arrays `[]`, bools false, strings `''`, otherwise null); classes keep
  their parents and constants, and inherit rather than redeclare a method a
  hand-written parent has. Regenerate after adding names; never edit.
- **The placeholder trace**: every generated body opens with
  `PlaceholderTrace::hit('name')`, which does nothing unless the site has a
  `wp-content/minn-placeholder-trace.log` file; then it appends
  `symbol, caller file:line, request path` per call. Create the file, drive
  traffic (the suites and a few curls), read it, delete it. The first run
  over all 25 dogfood plugins and every suite found 410 calls to only four
  symbols: `wp_is_jsonp_request` (Jetpack, on every REST call),
  `IXR_Client::__construct` (Jetpack's connection client, on admin REST
  calls), and nextgen-gallery's router calling `wp_old_slug_redirect()` and
  `redirect_canonical()` from `template_redirect`. The three functions are
  now real (below); `IXR_Client` stays inert because an XML-RPC client for
  the wordpress.com connection is admin-side plumbing Minn does not host.
- **Former slugs redirect** (`_wp_old_slug`): the reference keeps every
  slug a post ever had as meta rows and redirects a request for one to the
  post's current link (301), keeping `/page/N/` and dropping the query
  string, for `post` only (a page's old slug 404s, since pages resolve by
  `pagename`), and regardless of status: a draft's or trashed post's old
  slug lands on its `?p=` form, which then answers 404. The engine's
  resolver does this itself (`Posts::byOldSlug`, `Resolver::formerSlug`) on
  both the pretty path and `?name=`, before any plugin runs, so the facade's
  `wp_old_slug_redirect()` finds nothing to do on a 404 it did not cause.
  `redirect_canonical($url, $do_redirect)` re-runs the engine's resolution
  (`Front\Canonical`) for the current or the named URL, redirects 301 to
  the location when there is one, or returns it when told not to redirect;
  `wp_is_jsonp_request()` is `isset($_GET['_jsonp'])`, an empty or invalid
  callback included. Cases are in the permalink fixture (102 cases).
- **The file skeleton**: `minn install` writes every `wp-includes/*.php` and
  `wp-admin/includes/*.php` the reference has (`data/reference-files.json`,
  1,139 files) as one-line placeholders, plus `wp-admin/index.php` so a host
  that 403s a directory without an index still 302s `/wp-admin/` to
  `/minn-admin/`. A plugin `require ABSPATH . 'wp-admin/includes/plugin.php'`
  gets a file that does nothing; the engine already provides the symbols.
  Nothing outside those two trees is written, so no engine route is shadowed.
  The placeholder files are gitignored; `tests/tools/site-skeleton.php` still
  writes them on the local sites (run-all does for the two local ones).
- **Globals plugins read directly**: `$wp_scripts` and `$wp_styles` are
  live views of the registries (a plugin may reorder `$wp_styles->queue`;
  the printer takes it back), `$allowedposttags`, `$allowedtags`,
  `$allowedentitynames`, and `$wp_version` is the release the engine speaks
  (`Engine::WP_VERSION`, 7.1; Jetpack refuses anything older than 6.9).

What loading all 25 dogfood plugins then taught the front end:

- **Output buffers plugins open** (WP Retina 2x starts one in `wp_head`
  and closes it in `wp_footer`; Smart Slider and Gallery Custom Links wrap
  the page from `template_redirect`): `Runtime::capture` keeps a sentinel
  under its buffer, flushes buffers a callback opened through their
  handlers, and takes from the sentinel what a callback closed early.
- **The main query is seeded from the engine's resolution** before
  `template_redirect` (`Runtime\MainQuery::vars` to `_minn_seed_main_query`):
  `is_front_page`, `is_singular`, `get_queried_object_id`, `get_search_query`
  answer for the page being rendered, which is what an SEO plugin reads.
- **The document title runs through the reference's filters**
  (`pre_get_document_title`, `document_title_separator`,
  `document_title_parts`, `document_title`) over the engine's own parts
  (`Front\DocumentTitle`); `wp_get_document_title()` answers the same.
- **srcset and sizes run through** `wp_calculate_image_srcset` and
  `wp_calculate_image_sizes` (Retina 2x adds its `@2x` candidates there);
  a plugin block's images get the content-image treatment (dimensions,
  loading, srcset) but not the `auto` sizes hint, as observed.
- **`get_search_link`** is `/search/{term}/` under pretty permalinks.
- **The wp.* utility packages** (`wp-polyfill`, `wp-hooks`, `wp-i18n`,
  `wp-dom-ready`, `wp-escape-html`, `wp-url`, `wp-html-entities`,
  `wp-a11y`, `wp-api-fetch`) are the engine's own MIT scripts under
  `assets/wp/`, registered with the reference's dependency graph, so a
  plugin script that depends on them prints (Jetpack's slideshow view).
  The editor packages (`wp-element`, `wp-components`, `wp-data`, ...) are not
  provided; scripts depending on them still do not print.

## Application passwords

Live parity suite: `tests/application-passwords.test.php` (30 checks, the
same sequence on both stacks). The oracle needs
`wp-reference/wp-content/mu-plugins/zz-application-passwords.php` (the suite
and run-all write it) because the reference refuses application passwords
on plain HTTP.

- **Availability**: HTTPS, or the `wp_is_application_passwords_available`
  filter; `wp_is_application_passwords_available_for_user` per user. When
  unavailable every route answers 501 `application_passwords_disabled`
  after argument validation (a missing `name` is still 400
  `rest_missing_callback_param`), the `/wp-json/` index's `authentication`
  is `[]`; when available it names
  `{"application-passwords":{"endpoints":{"authorization":".../wp-admin/authorize-application.php"}}}`.
- **Routes** under `wp/v2/users/{id|me}/application-passwords`: GET list,
  POST create (201, the plaintext once as `password`), DELETE all
  (`{deleted, count}`), `/introspect` (the password that authenticated the
  call; 404 `rest_no_authenticated_app_password` under a cookie),
  `/{uuid}` GET, POST/PUT/PATCH rename, DELETE (`{deleted, previous}`
  without links). Unknown user 404 `rest_user_invalid_id`, unknown password
  404 `rest_application_password_not_found`, anonymous 401. `name` needs
  one non-space character (`rest_too_short`, "1 character" singular);
  `app_id` is a UUID or empty (`rest_no_matching_schema`); a duplicate name
  is allowed. Items: `uuid, app_id, name, created, last_used, last_ip`
  (site-local ISO stamps without offset), `_links.self` with the full
  method list.
- **Basic auth**: an `Authorization: Basic login:password` header
  authenticates a REST call as the user (spaces in the password ignored,
  no nonce needed) after the cookie session fails; it records `last_used`
  and `last_ip`. A wrong pair is reported as plain 401 `rest_not_logged_in`,
  and public routes still answer.
- **Storage**: the user's `_application_passwords` meta, a serialized list
  of `uuid, app_id, name, password, created, last_used, last_ip`, the same
  shape the reference reads. The plaintext is 24 letters and digits shown
  in groups of four.
- **Hashes**: both stacks store the reference's `$generic$` fast hash
  (`Auth\FastHash`: a 30-byte keyed BLAKE2b in URL-safe base64, confirmed
  by reproducing the reference's own hashes byte for byte,
  `tests/unit/auth-hashes.php`). A password made on either stack signs in on
  the other, and the suite proves both directions. Until 2026-10-05 the
  engine could not verify the reference's hash, so every application
  password a WordPress site had stopped working after a switch to Minn
  (the first resumability break, `docs/vision.md`). Phpass `$P$` (WordPress
  before 6.8, and the engine before this) and `$wp$` hashes still verify,
  as the reference verifies them. Without sodium, new passwords fall back
  to phpass.

## A plugin's rewrite rules route the front end

A plugin that registers its own URLs the reference way now works end to
end (2026-08-30 evening; CaptainCore Manager's `/account/` SPA was the
proving ground): `add_rewrite_rule()` records into the WP_Rewrite globals,
and `Minn\Front\PluginRules` matches those recorded rules against the
request path during resolution. 'top' rules outrank everything the engine
would resolve (so `/account/` routes to the plugin even though a page
named account exists, exactly as the reference orders its rule array);
'bottom' rules catch what the engine resolved to a 404. A matched rule's
query string substitutes `$matches[N]`, parses to vars, and keeps only
vars the reference would recognise: the built-in public list plus whatever
the `query_vars` filter admits, which is how a plugin registers its own.
Content vars (p, page_id, pagename, s, paged) resolve to that content;
anything else runs the home query under the rule, the reference's shape.
The kept vars ride into the main query so `get_query_var()` answers them.

The takeover half is `template_include`: the classic runner always applied
it; `PageRenderer` now applies it under block themes too (the engine's
`wp-api/template-canvas.php` path is the incoming value) and a swap to a
real PHP file is loaded as the whole response. Pinned by the fixture
plugin's routed page in `tests/runtime.test.php` (byte-identical on both
stacks; the reference side needs `wp rewrite flush` because it matches
from the flushed option while the engine matches the live registrations).
Not carried: external (non-index.php) rules and `add_rewrite_endpoint`
masks. A plugin template that calls wp_head() under a BLOCK theme gets
only runtime-registered hooks (the classic head state is not stood); the
classic path sets the full head state, which is where CaptainCore runs.

## The shop-dogfood symbols round (2026-08-31)

Seventeen functions and five classes took shop-dogfood from 27 loading
plugins to 33: gravityforms, gravitysmtp, advanced-custom-fields-pro,
code-snippets, perfmatters, and redirection all load as code now, and
every Minn Admin surface the reference offers (Forms, Email, Snippets,
Redirects, Backups, Field Groups, Performance, plus Commerce) answers on
the engine. Probed facts worth keeping:

- get_lastpostmodified covers the public types (a future-dated page moves
  'any', a nav_menu_item does not); the 'server' variant carries a
  microsecond suffix. sanitize_mime_type KEEPS case. like_escape
  backslashes only % and _. The network option functions read and write
  the plain options table on a single site. wp_default_editor answers
  tinymce even where user_can_richedit probes false (the CLI does, no
  browser). wp_match_mime_types re-orders its result keys in a pattern the
  probes could not explain ([n..4 desc, 2, 3, 1] by position); the engine
  keeps natural pattern order, membership and value order match.
- WP_MatchesMapRegex urlencodes every substitution; `Front\PluginRules`
  now does the same, so a captured '&x=1' cannot split into extra query
  vars. WP_Ajax_Response's XML and send() envelope are pinned from
  captures. WP_Textdomain_Registry records paths and scans the language
  dir (`Support\Paths::translationDir`); the boot sets the global.
- PHPMailer\PHPMailer\{PHPMailer,SMTP,Exception} are the engine's own
  (since 2026-10-05 the whole class: "Sending mail" below). Gravity SMTP
  assigns the STATIC PHPMailer::$validator before anything else, so the
  property must exist. Its sandbox (test_mode) short-circuits before send
  either way.
- WP_User_Query graduated from placeholder to real (`Runtime\UserQuery`):
  WooCommerce's sales report runs one on the front. Probed shapes: 'ID'
  fields come back as STRINGS, a column array as stdClass records holding
  only those columns, 'all' as WP_User. `Support\Serialized::encode` now
  writes objects with PHP's own serializer (maybe_serialize's contract;
  writing runs nothing, reads stay on the tolerant decoders).
- perfmatters' hidden login works end to end: the new wp-login.php SHAPE
  FILE (layout + installer + suite updated) boots like index.php when hit
  directly, and when plugin code require's it mid-request it throws
  `Login\ServeLogin`, which Engine::respond() catches to answer the
  current request with the sign-in surface. The engine CLI's login token
  is now stored RAW, the captaincore-helper mu-plugin's own format: with
  a hide-login plugin standing $pagenow, the helper validates the token
  itself and compares verbatim (hashes minted earlier still verify).
  /wp-login.php direct now 403s on shop-dogfood BY THE PLUGIN, oracle
  parity; sign in through the hidden slug.

## The second symbols round: everything but polylang (2026-08-31)

Sixteen more plugins load; shop-dogfood runs 48 of its 49 (rank-math,
seriously-simple-podcasting, search-filter and pro, akismet, smush,
user-switching, wpmudev-updates, user-role-editor-pro, bnfw,
genesis-blocks, the WooCommerce satellite trio, and the rest). All seven
parity pages AND the head meta stay byte-identical with rank-math
printing its SEO tags on both stacks, and the editor-panels list matches
the reference exactly. Only polylang stays gated: loading a multilingual
rewrite layer deserves its own verified session. Facts:

- Rank Math strips the category base on this site: BOTH stacks 404
  /category/security/ and serve /security/ (which resolves to the
  Security PAGE, colliding slug and all). The parity script's category
  path moved; do not read that 404 as a regression.
- Auth cookies mint per scheme through `AuthCookies::mint` and parse to
  the probed five-key map; an empty token gets a fresh session.
  `PasswordHash` rides `Auth\PortableHash`. check_comment applies the
  option gauntlet (both probe cases refuse under the dev defaults because
  comment_previously_approved demands an approved history;
  `Comments::hasApprovedByEmail` is the lookup).
- The abilities API is a recording registry (`Runtime\Abilities`) that
  fires wp_abilities_api_init ONCE on first access, the reference's lazy
  shape; the eval probe could not register because it fired the action
  out of band, so registration-on-the-hook is the pinned path.
- The style engine's declarations run through the kses css filter
  (probed: a-b drops, --my-var and url(x.png) stay, an injected
  close-brace tail is cut); pretty with no indent joins one spaced line,
  pretty inside a rule stacks tab-indented lines. `Support\Kses::css`
  loosened to the reference on two probed points, custom properties and
  relative image urls, and tightened with an explicit data: block.
- `Walker_Category` prints the same cat-item markup as the engine's own
  wp_list_categories path. Feed helpers ride the classic content
  pipeline; the av shortcodes carry the captured cache-busting source and
  bare-link fallback with a shared per-request instance counter.
  insert_with_markers writes the captured four-line preamble block
  (`Support\Markers`). get_lastpostmodified, network options,
  wp_unique_post_slug (`PostWriter::uniqueSlug`), and the rest are probed
  one-liners over existing Minn classes.
- Mute names added to the placeholder list for these plugins: the block
  editor settings pair (E4 pending), wp(), wp_timezone_choice, the admin
  screen and checklist chrome, WP_Site_Health, WP_Automatic_Updater.
  The trace log says whether any fires on the front.
- Still short of the reference: insertBlocks lists 36 of the oracle's 43
  (genesis-blocks/SSP/arve block registrations pending), and the oracle
  under `php -S` can DIE during a heavy search request; when a parity run
  shows the reference side empty, restart 8127 before reading diffs.

## Stored objects, and the one symbol WooCommerce 11 was short of (2026-09-02)

shop-dogfood answered every page with `Call to undefined function WC()` from
orderable. Two facts behind it, both settled against the oracle:

- **The symbol gate skipped WooCommerce 11 for one function**,
  `rest_get_allowed_schema_keywords` (its email editor's schema validator
  calls it). The facade returns `Rest\Schema::KEYWORDS`, the reference's
  list in its order: title, description, default, then the twenty-two
  endpoint keywords. The gate's verdict is computed on every request
  against the loaded facade; only the token scan is cached, so a new
  symbol takes effect without clearing `minn_runtime_symbols`. The three
  abilities-API names WooCommerce's vendor copy declares are guarded by
  `function_exists('wp_register_ability')` in its bootstrap, so they never
  compile against the engine and the main-file-only redeclare rule is right
  to let the plugin through.
- **A plugin that depends on a skipped plugin still runs.** orderable asks
  `active_plugins` whether WooCommerce is active, not whether `WC()` exists,
  and the option still names it. On the reference an inactive WooCommerce is
  absent from the option, so dependents step aside; on the engine a skipped
  plugin is invisible to them. Open: whether `Plugins::skipped()` should be
  subtracted from what the runtime hands back for `active_plugins`, so a
  gate skip degrades the way a deactivation does instead of fataling the
  page. Not done; the fix that mattered was making WooCommerce load.

With WooCommerce running, `/cart/`, `/my-account/` and every product page
fataled in CoBlocks: `has_coblocks_block(WP_Post $post)` received a
stdClass. CoBlocks caches its `wp_template_part` query in a transient, and
that transient was WRITTEN BY WORDPRESS before the site moved: an array of
`O:7:"WP_Post":24:{...}` records. `Support\Serialized::decode` turned every
object record into a stdClass by design. The reader still instantiates
nothing; it now takes a reviver closure, and `Runtime\StoredObjects` is the
registry the facade fills at load (`wp-api/defaults/stored-objects.php`:
WP_Post, WP_Term, WP_Comment, each `new WP_X($properties)`). Option reads
(`Options::fromStorage`) and `maybe_unserialize` (which meta reads go
through) pass that reviver; every other decode call stays plain. A record
naming any other class stays a stdClass of its properties, where the
reference hands back `__PHP_Incomplete_Class`; the probe row pins only what
both agree on (an object of no known class). Writes changed to match too:
`Options::toStorage` and `maybe_serialize` no longer cast a top-level object
to an array, so an option holding a post is stored `O:7:"WP_Post":24:{` on
both stacks (the facade's WP_Post declares the reference's twenty-four
properties in its order, which the byte prefix row checks). Not revived:
WP_User (its stored shape nests a `data` row; nothing on the dogfood sites
stores one). Unit file `tests/unit/stored-objects.php`; probe rows
`option object bytes`, `option object top-level`, `option term and comment
revive`, `unknown class stays unknown`.

The oracle's own `/feed/` answers 500 on shop-dogfood's reference (a plugin
under `php -S`); the engine's feed renders. Not chased.

## What a plugin cannot do yet

All twenty-five of the dogfood site's plugins load as code now
(`runtime-report.php`), and the nine dogfood pages render at parity with
them running, Jetpack included. Remaining Speak / Hear gaps, in order:
`WP_HTML_Processor` (the tag processor exists; the tree-aware one does
not); `WP_Term_Query` as a real query
object; a front-end main query fed from the engine's own resolution
(conditional tags on the command line only). Mute, and staying mute:
`WP_List_Table`, screens and screen options, `iframe_header`, upgrader
skins, the Customizer and widget *screens*, XML-RPC, multisite UI. Those
names stay placeholders (`contracts/lexicon.md`). Plugin settings PHP is
ignored; Minn Admin adapters own the UI.

## The facade stays a mapping layer

`wp-api/` reads like WordPress because its names, signatures, loose types,
and globals are the interface; that cannot change. Its thickness can. The
rule going forward: a facade function normalises loose input, calls one
`Minn\` method, and shapes the return; queries, decisions, and loops live in
`src/Minn/` under the style guide. `Hooks`, `Options`, `PostQuery`,
`Registry`, `Symbols` already work that way; `post.php`, `taxonomy.php`,
`media.php`, `comment.php`, `rest-api.php`, `blocks.php` carry logic that
belongs in `src/Minn/Runtime/`. The extraction is the next cleanup pass,
lint-enforced like the style migration was.

The pass started with `taxonomy.php` (2026-08-30): `Runtime\TermQuery`
holds the reads (arguments to SQL, the tree filters, the fields shapes, the
single-term lookups), `Runtime\TermWriter` the write decisions (duplicate
rules, slug uniqueness, parent checks, relationships and their actions), and
`Runtime\Refusal` is what a refused operation returns before the facade
turns it into `WP_Error`. The slug sanitiser is handed in as a closure so
the reference's `sanitize_title` filters still apply. The facade file went
from 1,011 lines to 717 and makes no queries. `tests/style.test.php` now
carries a ratchet for the whole facade: per-file query-call counts and the
list of functions over forty lines may only shrink.

The pass finished the same day. The facade makes no queries at all (every
read and write goes through a repository or a `Runtime\` class), and no
facade function runs past forty lines: 856 are five lines or fewer, 307
under fifteen, 140 under forty, none longer. What moved, and where:

| Facade | Minn class | What it holds |
|---|---|---|
| `taxonomy.php` | `Runtime\TermQuery`, `Runtime\TermWriter` | term reads, tree filters, fields shapes; insert/update/delete rules and relationships |
| `rest-api.php` | `Rest\Schema`, `Rest\RouteArgs` | schema validate/sanitize/context filter and the type vocabulary; route argument defaults |
| `post.php` | `Runtime\PostInsert`, `Runtime\PostLookup`, `Runtime\Pages` | column fill, empty check, status/date/slug resolution, categories; lookups and counts; page tree order |
| `upgrade.php` | `Runtime\DbDelta` | create-or-add-columns-and-keys |
| `comment.php` | `Runtime\CommentQuery` | comment reads and the approval breakdown |
| `meta.php` | `Runtime\Meta` | the four meta tables' reads and row-level writes |
| `blocks.php` | `Runtime\BlockMetadata` | block.json to type settings |
| `misc.php` | `Runtime\UserInsert` | login/email/role rules, account fields, update columns |
| `media.php` | `Media\Sizing` | resize box, size selection, srcset candidates, editor sizes |
| `template.php` | `Runtime\Avatar` | avatar arguments, hash, Gravatar query, classes, attributes |
| `formatting.php`, `http.php` | `Support\Url` | esc_url cleanup, bracket encoding, query merging, HTTP URL validation |

Two conventions came out of it. A Minn class never constructs a WordPress
object: it returns arrays, rows, or a `Runtime\Refusal` (code, message,
data), and the facade turns those into `WP_Term`, `WP_Post`, or `WP_Error`.
And anything the reference filters (`sanitize_title`, `is_email`,
`number_format_i18n`, option reads, capability checks) is handed into the
class as a closure rather than reimplemented, so plugin filters keep
applying. The ratchet's two lists are empty; a new query or a new forty-line
function in `wp-api/` fails the style suite.

The second pass (2026-08-30, later the same day) brought `wp-api/classes/`
under the same ratchet; the class files had never been measured, and the
longest methods in the facade lived there. The rule for a class is the
rule for a function: a `WP_*` method normalises, calls one `Minn\` method,
and shapes the return into the object plugin code expects. Nothing in
`classes/` runs past forty lines now, and the facade as a whole makes one
query fewer (the `WP_User` slug lookup joined the `Users` repository). What
moved this time:

| Facade | Minn class | What it holds |
|---|---|---|
| `WP_Query` | `Runtime\QueryFlags`, `Runtime\QueriedObject` | the is_* flags a set of query variables implies; which object a query is about (term by id or slug, post type, posts page, post, author) |
| `WP_Http` | `Minn\Http::send`, `Http\Transport`, `Http\Outbound`, `Http\Exchange` | the curl transport: request value in, status + last-hop headers + Set-Cookie values + body out; `Minn\Http::send` is the door, so a test's `Minn\Http::fake()` answers `wp_remote_*()` too |
| `WP_REST_Server` | `Rest\RouteMatch`, `Rest\RouteIndex`, `Rest\AdditionalFields` | handler lookup by method and path with captured params and defaults; the index description of one route; which object type a wp/v2 route serves |
| `WP_REST_Request` | `Rest\ParamCheck` | the required / validate / sanitize pass over declared arguments |
| `WP_Block_Supports` | `Blocks\Supports` | wrapper class, style, and id from a block's supports and attributes |
| `WP_Filesystem_Direct` | `Support\DirectoryListing` | the directory walk (dot entries, hidden entries, recursion) |
| `cron.php` | `Runtime\CronTable` | the cron option as data: keys, duplicate window, insert, remove, find, next run, due |
| `blocks.php` | `Blocks\QueryVars`, `Blocks\Selector` | Query Loop context to query variables; a block type's root or feature selector |
| `rest-api.php` | `Rest\Schema::endpointArgs` | item schema to endpoint argument map |
| `meta.php` | `Runtime\Meta::idsToUpdate` | which of a key's rows an update touches |
| `formatting.php` | `Support\Email`, `Support\Entities` | the address rules and their refusal reasons; special-character encoding with the quote styles and the no-double-encode rule |
| `media.php` | `Media\Sizing::constrain`, `Media\Kind`, `Media\Uploads::attachmentFiles` | the constrained box; image/audio/video by MIME then extension; every file an attachment owns |
| `comment.php` | `Content\Comments::changedColumns` | the columns an update really changes, with the approval shorthands |

The callbacks plugin code supplies (a REST `validate_callback`, a block's
`render_callback`, a route's `schema`) are still invoked from the facade or
handed in as closures that return plain values; a `Minn\` class never sees a
`WP_Error`. The oracle earned its keep once more here: the `plugin-surface2`
fixture's template-part hash had drifted with the reference's own header
(both stacks agreed on the new value), which the extended suite surfaced on
its first run.

The third pass turned to the facade classes that had no Minn behind them
at all. The rule there: a `WP_*` class keeps its shape (properties, method
names, return types plugin code reads) and a Minn class owns the state or the
work. `Media\Canvas` holds the GD bitmap and every pixel operation, and both
`WP_Image_Editor_GD` and the engine's own `Images::makeSubsizes` draw through
it (one `Sizing::constrain` rule now, where the engine and the facade used to
carry two). `Runtime\Patterns` is the pattern, pattern-category and
block-style store the three `WP_Block_*_Registry` classes read; `Runtime\OEmbed`
matches providers, parses JSON and XML payloads, builds the embed markup and
strips newlines around `<pre>`; `Theme\Folder` reads a theme folder (headers
through the facade's reader so `extra_theme_headers` still applies, template
folder, screenshot, block-theme check, file lookup); `Rest\Fields::select`
is the `_fields` selection a controller applies; `Front\SitemapXml` builds
the index and URL-set documents for the engine's sitemap routes and the
facade's `WP_Sitemaps_Renderer` alike. Left as shape on purpose: `WP_Rewrite`
and `WP` (recorded state), `wpdb` (the SQL seam), `WP_Error`, `WP_Screen`,
`WP_REST_Response` (value objects), `customize.php` and `WP_Widget` (Hear).

`contracts/api/mappings.json` is the audit trail (`php tests/tools/facade-map.php`
regenerates it; the style suite fails when it is stale). Every facade function
and method is listed with the Minn methods it calls, the facade functions it
composes, and its length, under one of four kinds: `minn` (calls into
`src/Minn`), `composes` (calls other facade functions only), `leaf` (plain PHP
with no call either way), `noop` (at most one line). The numbers at the time
of writing: 2,272 functions, 257 minn, 643 composes, 298 leaf, 1,080 noop,
285 distinct Minn methods. `--leaves` lists the leaves longest first; the
suite pins how many run past fifteen lines, and the sixteen that do are shape
by design (`wpdb` result shaping, constructors that copy rows into objects,
`WP_Hook::apply_filters` over the shared hook storage, `WP_Screen`, generic
list sorting). The leaf pass that produced the number moved `wpautop`
(`Content\Autop`), `remove_accents` (`Support\Accents`), entity decoding and
tag stripping (`Support\Entities::decode`, `Html::stripAllTags`), path and
mode spellings (`Support\Paths`), query strings (`Url::buildQuery`), JSON
sanitising (`Support\Json`), CURIE compaction (`Rest\Links`) and block asset
handles (`BlockMetadata::assetHandle`).

The composing functions got the same treatment next: the map's `composes`
kind hides logic between facade calls, so the longest ones were read one by
one. What carried a decision moved: `Slug::dashes` (the reference's dashed
title, save-context folding included), `Uploads::layout` (where uploads live
from the options), `Parser::contains` (block search by delimiter),
`Content\CommentClasses`, `Schema::combining` (anyOf / oneOf branch
selection), `Url::parse` / `withScheme` / `safeRedirect`, `Support\Time::span`,
`Html::textField`, `TermQuery::coerce`, `Rest\RouteTable::normalise`, and
`Blocks\BlockName`. What stayed is orchestration: hooks fired around one
call, `WP_Error` construction, option reads that plugin filters may change.

Round five (same evening) settled the rest of the over-15 list by
classification rather than extraction, so a later pass does not re-read
them: `WP_Meta_Query::parse_query_vars` moved (`Runtime\Meta::
clausesFromQueryVars`, the flat meta_key/meta_value vars merged ahead of an
explicit meta_query); everything else stays with a reason. `WP_REST_Request
::parse_json_params` is guard clauses and WP_Error shaping; `WP_Hook::
apply_filters` runs standalone instances over their own storage with local
nesting state and cannot delegate to `Runtime\Hooks`; `WP_Screen::get` is
the Hear screen-identity registry; `WP_User`/`WP_Post_Type` constructors
and `WP_Theme::__get` are loose-input hydration; `wpdb::update` is the
format juggling already ruled mapping; `wp_convert_widget_settings` is
widget-option (Hear) data conversion; `WP_REST_Response::as_error` and
`WP_REST_Server::register_route` are WP-shape construction and recording.
The leaf ceiling is 13. The longest composing functions were read too and
stay for cause: `_minn_comment_form_body` and `wp_list_comments` interleave
a hook or a walk-state global with nearly every line; `image_downsize`
composes facade siblings around its filter; `_wp_make_subsizes`' per-size
editor loop with incremental metadata writes is the reference's observable
behaviour and meets the engine's own `Media\Images` pipeline one layer down
at `Media\Canvas`; `url_to_postid` already rides Posts and verifies through
Permalinks; `get_bloginfo` is a key switch, which is the mapping.

The review also surfaced a REAL gap, bigger than thinning: the engine has
two disconnected sitemap implementations. `Front\Sitemaps` serves
/wp-sitemap.xml (pinned at oracle parity by the probes suite, direct SQL,
fixed providers) while the facade's `WP_Sitemaps` registry exists for
plugin code, and the front routes never consult it, so a plugin's
registered provider or wp_sitemaps_* filters change NOTHING on the served
sitemap. Unifying them (front routes consulting the registry and filters
when the runtime is booted) is capture-first milestone work, not a
refactor: the two even order content differently (post_date vs ID), so the
oracle has to arbitrate. Until then this is a Speak gap: sitemap plugins
appear to work but their output never ships.

Round four (2026-08-30, after the classic runner landed): the archive-title
labels the classic facade and the block path computed separately unified in
`Theme\ArchiveTitle` (one source of truth for Category:/Tag:/Author:/date
labels; search and post-type archives stay with their callers because their
captured shapes differ); `WP_Filesystem_Direct`'s recursive tree work and
the symbolic-to-octal permission conversion moved to `Support\Files` (its
deleteTree deliberately unlinks a symlinked directory instead of following
it: the dev sites symlink plugin folders into the tree); `wp_list_sort` and
wpdb's output-format shaping moved to `Support\Lists`. The over-15-line
leaf ceiling ratcheted 16 to 14. What stayed put on review: value-object
constructors (`WP_User`, `WP_Post_Type`, `WP_Theme::__get`), recorded state
(`WP_Rewrite`), Hear (`customize.php`, `WP_Widget`), and wpdb's remaining
format juggling, which is the mapping itself.

## The second plugin surface: WooCommerce loads

WooCommerce 11 on a fresh lab site (`minnwoo.localhost`, oracle on
127.0.0.1:8126, 18 sample products, Twenty Twenty-Five) referenced 99
symbols the runtime lacked. Admin-only, email-editor, tracker and importer
names became placeholders; the rest are real and probe-backed
(`tests/tools/plugin-surface-probe.php`, 77 rows, and
`plugin-surface2-probe.php`, 109 rows, in `contracts/fixtures/api/`). What
the oracle taught, by area:

- **The hook object** (`WP_Hook`, `$wp_filter`): the reference's registry
  is an array of hook objects whose `callbacks[priority][id]` holds
  `function` and `accepted_args`; the engine's `Runtime\Hooks` now stores
  that shape and each `$wp_filter[name]` shares its storage by reference, so
  a plugin editing callbacks in place edits what the engine runs.
  Priorities stay sorted on insertion; `remove_all_filters` empties the
  hook but keeps it; `current_priority()` reads the running level, false
  when idle; `do_action` passes the first argument as the value.
  `$wp_actions`, `$wp_filters`, `$wp_current_filter`, `$wp_roles` are bound
  the same way.
- **Meta, tax and date queries** (`Minn\Query\{MetaSql,TaxSql,DateSql}`):
  plugins embed `get_sql()` output verbatim, so the fragments match byte
  for byte. Meta: one join per clause (the table's own name, then mt1,
  mt2...), INNER unless an OR group holds a NOT EXISTS clause (then every
  join is LEFT, and NOT EXISTS carries its key in the ON and reads
  `alias.post_id IS NULL`); under an OR group an equality-shaped clause
  (=, IN, BETWEEN, LIKE, REGEXP, RLIKE, >, >=, <, <=) shares the first such
  sibling's alias, under AND never; a key-only clause is
  `alias.meta_key = 'k'` alone, value-only `alias.meta_value = 'v'` alone,
  both wrap in parentheses; NUMERIC casts as SIGNED, DECIMAL(n,m) as
  written; LIKE wraps in % after escaping. Tax: IN joins
  term_relationships (own name, then tt1...; shared between IN siblings
  under OR), NOT IN and AND are subqueries, EXISTS joins term_taxonomy, a
  term nobody has is `0 = 1`, NOT IN of a missing term drops out. Date:
  after/before as full datetimes (inclusive bounds close the unit:
  `2025-06-30 23:59:59`), parts as YEAR()/MONTH()/DAYOFMONTH()/DAYOFWEEK()
  /HOUR() in that order, IN lists, BETWEEN pairs; unknown columns fall
  back to posts.post_date, `comment_date` maps to the comments table.
- **Block templates** (`Runtime\BlockTemplates`, `WP_Block_Templates_Registry`,
  `register_block_template`): names must be `plugin//slug`, lowercase, and
  unique (`template_no_prefix`, `template_name_no_uppercase`,
  `template_already_registered`); the template answers as
  `{active theme}//slug` with source and origin `plugin`, `is_custom`
  true, `has_theme_file` null, `status` publish, `post_types` as given.
  `get_block_templates()` lists theme files (source theme,
  `has_theme_file` true, `is_custom` false) then registered templates
  keyed by their registered name, unless the query names a `post_type`
  without `slug__in`; `get_block_template()` resolves both the theme id
  and the plugin's own name. `Theme\Templates::template()` falls back to a
  registered template's content, so a plugin's `single-product` renders.
  `render_block_core_template_part()` called directly prints `<header >`
  with no block-support class (the reference adds that class only inside
  a block render).
- **Hooked blocks**: `traverse_and_serialize_blocks` calls the visitors
  before and after every block with (block, parent, previous/next);
  `make_before_block_visitor` emits the parent's `first_child` hooks before
  the first child then the block's `before`, `make_after_block_visitor`
  the block's `after` then the parent's `last_child` after the last;
  `insert_hooked_blocks` serialises each hooked type (`<!-- wp:name /-->`)
  through `hooked_block_types`, `hooked_block`, `hooked_block_{name}`,
  skipping types the anchor's `metadata.ignoredHookedBlocks` lists;
  `apply_block_hooks_to_content` returns the content untouched when
  nothing is hooked and no `hooked_block_types` filter is registered.
- **Query loop vars** (`build_query_vars_from_query_block`): the block's
  `context['query']` (a post-template child sees it; the query block
  itself provides rather than uses it) becomes post_type, order, orderby,
  post__not_in (sticky "exclude" adds the sticky ids before `exclude`),
  tax_query (`['relation' => 'AND', [taxQuery clauses with
  include_children false], ['relation' => 'OR', post_format slug clause]]`),
  offset `perPage * (page - 1) + offset` (never capped by `pages`),
  posts_per_page, author__in (present when `author` is set, empty for
  ""), s, post_parent__in; sticky "only" gives post__in plus
  ignore_sticky_posts. `build_comment_query_vars_from_block`: orderby
  comment_date_gmt, ASC, status approve, no_found_rows false, post_id,
  hierarchical threaded when thread_comments is on, number/paged only
  when page_comments is on.
- **Classic comment templating**: `wp_list_comments` resets the odd/even
  and thread counters, prints the html5 item (`\t\t<li id="comment-N"
  class="...">` ... `</li><!-- #comment-## -->`) with the avatar, author
  link (`class="url" rel="ugc external nofollow"`), `<time datetime>`
  link, awaiting-moderation note, wpautop'd text and the reply link (`<div
  class="reply">`, `data-belowelement="div-comment-N"`); replies nest in
  `<ol class="children">`; `comment_order` desc reverses the top level; a
  `callback` replaces the item, `end-callback` the close. `comment_form`
  prints the reference's form: comment field first, then author/email/url,
  then the cookies consent (appended even to custom `fields` when
  `show_comments_cookies_opt_in` is on), the block-theme submit wrapper
  `<p class="form-submit wp-block-button">` (kept with a custom
  `submit_button`, whose class then drops the button classes); closed
  comments print nothing and fire `comment_form_comments_closed`.
  `comments_template` fills `$wp_query->comments` and loads the theme's
  comments.php or the engine's own `wp-api/theme-compat/comments.php`
  (`<!-- You can start editing here. -->`, the "One response to
  &#8220;title&#8221;" heading, navigation, `<ol class="commentlist">`, the
  form). Avatars inside the main loop count against the three eager
  images (no `loading="lazy"`); outside it they lazy-load.
  `get_comment_reply_link` returns null at or past max_depth and false
  when comments are closed; `get_comments_link` ends in `#comments` with
  comments and `#respond` without; `get_comments_pagenum_link` is
  `comment-page-N/#comments`; `paginate_links` prints prev/next, end and
  mid runs with one ellipsis per gap, page 1 without the format,
  `add_args` through `&#038;`, null with fewer than two pages.
- **Sign-in and roles**: `wp_signon` runs the `authenticate` chain
  (`wp_authenticate_username_password`, `wp_authenticate_email_password`
  at 20: `empty_username`, `empty_password`, `invalid_username`,
  `invalid_email`, `incorrect_password`), then `wp_set_auth_cookie` mints
  a session and sends the three cookies; `add_role` returns the WP_Role
  (null for an existing or empty name) and `get_role` sees it at once;
  `get_password_reset_key` stores `time:$minn$...` (the reference's
  `$generic$` fast hash is not derivable, see application passwords) and
  `check_password_reset_key` answers `invalid_key` / `expired_key` / the
  user; `wp_get_password_hint` carries a literal `&amp;`.
- **Small facts**: `wp_is_jsonp_request`, `wp_set_option_autoload_values`
  (false for unchanged or missing, column `on`/`off`), `_wp_filter_build_unique_id`
  (`Class::method` for static arrays), `wp_unique_term_slug` (`-2` suffix
  within the taxonomy only), `update_termmeta_cache` (meta per id, false
  for an empty list), `sanitize_post_field` (edit escapes, attribute and
  js escape, ID/post_parent/menu_order cast to int in every context,
  post_author not), `validate_username` (strict sanitising must leave it
  unchanged), `wp_list_categories` / `wp_tag_cloud` / `wp_dropdown_categories`
  markup (`Minn\Front\TermLists`), `get_post_class` order
  (`Minn\Content\PostClasses`), `get_query_template` names the engine's
  `wp-api/template-canvas.php` under a block theme when the theme ships a
  block template for one of the names, `shortcode_unautop` unwraps a
  paragraph holding only a registered shortcode, an `exclude_tree` of ""
  excludes nothing (it emptied every term list before), and wptexturize
  turns a standalone hyphen into an en dash (`a - b`, `end -`, `- start`;
  `a-b` stays), which the document title depends on.

### A plugin's post types and taxonomies on the front end

The resolver (`Front\Resolver::pluginRoute`) reads the runtime registry
once plugins have registered: a type's archive answers at its `has_archive`
slug (a string) or its rewrite slug (`true`), and wins over a page of the
same name (`/shop/` is the product archive on the reference, not the shop
page); a single answers under the type's rewrite slug; a term under the
taxonomy's rewrite slug, hierarchical paths checked whole. Two resolution
kinds carry them, `Kind::Taxonomy` (the term row) and `Kind::PostTypeArchive`
(the registered type), with the reference's body classes (`single
single-product postid-N` without a format class, `archive tax-product_cat
term-clothing term-28`, `archive post-type-archive post-type-archive-product`),
template candidates (`single-{type}-{slug}`, `single-{type}`;
`taxonomy-{tax}-{slug}`, `taxonomy-{tax}`, `taxonomy`, `archive`;
`archive-{type}`, `archive`), query vars (`product=hoodie&post_type=product&name=hoodie`,
`product_cat=clothing`, `post_type=product`) and titles (the type's label
through `post_type_archive_title`, which WooCommerce answers with "Shop").
Template candidates run through the reference's `{type}_template_hierarchy`
filters as PHP names (WooCommerce sends its taxonomies to `archive-product`
that way; it registers no `taxonomy-product_cat` template).

What the lab taught about the main query: a plugin archive's main query is
a real `WP_Query` (`_minn_run_main_query`), made the main query before it
runs so `is_main_query()` holds inside `pre_get_posts`, with no
`posts_per_page` in its vars (WooCommerce keeps a page size it finds and
only supplies its 16 when the var is empty; `fill_query_vars` leaves the
var "" and the option applies at run time). After the main query stands the
engine fires `wp` (with the request object) then `template_redirect`;
WooCommerce's breadcrumbs, product data and gallery all need `wp`. A single
of a plugin type seeds by name and type (its vars carry no `p`). Support
declared with `add_post_type_support` before the type registers waits
aside; it used to create a skeleton row that made `post_type_exists` true
and WooCommerce skip its own registration. Every page-list item carries
`open-on-hover-click`. A bridged plugin block renders once through
`WP_Block` with the context `render_block()` builds; the engine's
`BlockFilters` applies the render filters around it and the block object
skips them (`minn_filters` false), because WooCommerce's collection
renderer resets its state after the first `render_block_woocommerce/product-collection`
and returns "" on a second pass. `$wp_embed` is a real `WP_Embed`
(the [embed] shortcode, URLs alone on a line, handlers, then a link) on
`the_content` at 8. `get_block_wrapper_attributes` names a class once.

Known gaps after this pass: the engine registers no core shortcodes
(`[gallery]`, `[caption]`, `[audio]`, `[video]`, `[playlist]`, `[embed]`)
where the reference registers seven; `wp_list_comments` prints only the
html5 format; `logged_in_as` markup is unverified; pretty URLs for a
plugin's post types and taxonomies (`/product/hoodie/`,
`/product-category/clothing/`) do not resolve yet, which is the next step
on the WooCommerce lab.

## Block context in a plugin's query loop (2026-08-31)

- Rendering a `WP_Block`'s inner blocks passes each inner block's context
  through the `render_block_context` filter at every nesting level, and the
  filtered context cascades to that block's children. This is how a query
  loop (core post-template, WooCommerce product-template) hands `postId`
  down; the engine rebuilds the inner block when the filter changed its
  context. Probe rows: blocks fixture, "context cascade *".
- A core block rendered through the bridge inside a runtime `WP_Block`
  pushes the context's `postId` onto the engine renderer's post stack, so
  core/post-title and friends resolve the loop's per-item post.
- `WP_Theme_JSON_Resolver::get_user_global_styles_post_id()` finds the
  active stylesheet's `wp_global_styles` post by its wp_theme term and
  creates it with the captured shape when missing (publish, author 0,
  title "Custom Styles", name `wp-global-styles-{stylesheet}`, closed
  discussion, `{"version": 3, "isGlobalStylesUserThemeJSON": true }`),
  attaching the theme term so the next call finds it. Under `wp eval-file`
  the REFERENCE orphans a new post per process because the term attach
  fails there; do not pin cross-process ids against eval-file.
- The cleantalk connector is registered by the minn-admin plugin on the
  `wp_connectors_init` action, not by the engine's defaults. It appears
  exactly when the plugin loads, and re-firing the action duplicates it
  (the probe's doing_it_wrong row).
- CAPTURE RULE for the api-family fixtures: flush rewrite rules FROM THE
  REFERENCE and boot it once before capturing. An engine-side flush writes
  a rules option without the minn-admin rule, minn-admin's guard re-flushes
  on the next reference boot, and `extra_rules_top` balloons with taxonomy
  rules for that one boot (the bistable "blocks: rewrite" row).

## The Block Hooks API (2026-09-01)

Probe `block-hooks-probe.php`, fixture `contracts/fixtures/api/block-hooks.json` (15 rows).
A plugin asks for its block next to an anchor; WooCommerce puts the
mini-cart and the customer account after `core/navigation` in a header.

- **Both visitors run before a block is serialized.** The traversal calls
  the before-visitor and the after-visitor, THEN serializes between their
  markup. Serializing in between freezes the anchor before an
  `after`-position visitor has mutated its attributes, which is what
  silently broke the metadata pass. The fire order for a group holding two
  children is: before(group), after(group), first_child(group),
  before(child1), after(child1), before(child2), after(child2),
  last_child(group).
- **`insert_hooked_blocks_and_set_ignored_hooked_blocks_metadata`** is one
  pass that inserts AND records: the insert runs first (so it still reads
  the anchor's own ignores), then the metadata records what went in. Each
  position therefore fires `hooked_block_types` twice, consecutively. This
  is the callback pattern retrieval and template parts use.
- **`ignoredHookedBlocks`** on the anchor's `metadata` attribute suppresses
  a block that would otherwise be inserted there.
- **`hooked_block` and `hooked_block_{name}`** receive the parsed block
  (`blockName`, empty `attrs`, empty inner), the position, and the parsed
  anchor; returning null suppresses the insertion.
- **A pattern retrieved from the registry** carries its hooked blocks, with
  the pattern ARRAY as the context (`blockTypes`, `categories` are how a
  plugin recognises a header pattern).
- **A template part** wraps its content in a virtual `core/template-part`
  block before applying hooks, then unwraps with
  `remove_serialized_parent_block`: that wrapper is why a part fires six
  positions (before/after/first_child/last_child of the part plus its
  child's own), not two, and its context is the `WP_Block_Template`.
  `remove_serialized_parent_block` / `extract_serialized_parent_block` are
  pure offset slices (`strpos('-->')+3`, `strrpos('<!--')`), so a string
  that is not a block still comes back sliced.
- **On the engine's own front end** the seam is `Minn\Runtime\BlockHooks`,
  called by the template-part and pattern theme blocks; it stays inert
  until a plugin actually hooks something. `Theme::patternMeta()` reads the
  pattern file's `Block Types` and `Categories` headers for the context.
- **`$wp_locale` is bound with the runtime**, not built on first use:
  plugin code reads the names off the global (WooCommerce's block settings
  read `weekday_abbrev`, and a null global fatals the whole page). English
  strings through the translation filters; the engine renders no core
  translations yet.

## wp_get_global_styles (2026-09-01)

The active theme's merged styles node: the engine's own `data/styles.json`
defaults under the theme's `theme.json` styles, plus the site editor's
saved global styles. Every `var:preset|…` token is resolved to the custom
property it names (`var:preset|color|base` reads back as
`var(--wp--preset--color--base)`); the CSS writer resolves them on the way
out instead, so `GlobalStyles::styles()` is the raw node and
`resolvedStyles()` the one a data caller sees. A path that names nothing
yields the whole tree, matching the reference's array read. WooCommerce
gates its `woocommerce-block-theme-has-button-styles` body class on
`elements.button` being present here.

NOTE for probe rows: the api suite compares transcripts with json_encode,
so a row must not pin a key ORDER that is only a merge artifact (the
styles maps are the case in point; their CSS emission order is pinned by
the styles suite instead). Pin the value by name, or the sorted key set.

## The site's script pack, and what the React cart needed (2026-09-01)

WooCommerce's block cart and checkout are React. The `@wordpress/*` packages
they import are **GPL-2.0-or-later** (audited on npm: element 8.6.0, data
10.54.0, api-fetch; core's `license.txt` is plain GPL with no MPL clause and
the built `js/dist` files carry no headers), so the engine never ships them.
`vendor/react.min.js` is MIT, but the wrappers are not.

Instead the SITE installs them into its own wp-content, the way it installs
a language pack: `wp minn scripts install [--from=<WordPress tree>]`, which
defaults to the WordPress the swap parked beside the webroot, so the
packages match the site's own core version by construction.
`Minn\Runtime\ScriptPack` reads the pack's own
`script-loader-packages.php` manifest (65 packages, keyed `name.js` with
dependencies and version) plus the fifteen vendor handles WordPress
registers outside it, and registers only handles the engine has not already
provided: the engine's nine MIT packages under `minn/assets/wp` always win.
A site owner adding GPL files to a site that already runs GPL plugins is not
the engine distributing GPL; nothing under `minn/` changes.

Four engine facts the React cart exposed, each now pinned in the api probe:

- **A handle is "enqueued" when it only rides in as a dependency**, however
  deep. `wp_script_is($h, 'enqueued')` recurses the queue's dependency
  trees. WooCommerce attaches its whole `wcSettings` blob only when it finds
  `wc-settings` enqueued, and nothing ever queues that handle by name.
- **The footer printer hangs off the `wp_print_footer_scripts` ACTION**, not
  off `wp_footer`: `wp_footer` fires `wp_print_footer_scripts` at 20, which
  fires the action, and `_wp_footer_scripts` is hooked to THAT at 10. The
  indirection is the contract, because plugins hook the action ahead of the
  printer to add inline data (WooCommerce at priority 1). Printing straight
  from `wp_footer` runs before all of them and drops their data.
- **apiFetch must be told where the REST API is.** The reference adds an
  inline script after `wp-api-fetch` with the root-URL middleware, the
  nonce middleware, the media-upload middleware, and the nonce endpoint.
  Without the root middleware a caller resolves its namespace against the
  site root, so the Store API is requested at `/wc/store/v1/cart` with no
  `/wp-json/` in front of it.
- **One unregistered handle anywhere in a dependency tree drops every script
  above it.** A missing `moment` (a vendor handle outside the packages
  manifest) silently cost the entire WooCommerce cart bundle.

Also landed: `WP_REST_Server::serve_batch_request_v1()` (HTTP 207 with
`responses`, one enveloped {body, status, headers} per entry in request
order; which methods a batch accepts is the route's own schema) and
`WP_Scripts::print_translations()` (false: the engine carries no core
JavaScript translations yet, which is what the reference answers for an
untranslated handle).

Known gap: `print_translations` never prints `setLocaleData`, so a plugin's
JavaScript strings stay English even where its PHP is translated.

## Assets: dependency lists, and $wp->request (2026-09-01)

- **A dependency list is an ARRAY of handles; anything else is discarded.**
  `wp_register_script`/`wp_register_style` given `''`, `null`, `false`, or
  even a bare handle string all register with NO dependencies. Casting
  instead (`(array) ''`) registers a dependency on the empty string, which
  no handle satisfies, so the asset and everything above it silently stops
  printing. WooCommerce registers `woocommerce-general` with `deps => ''`;
  that one cast cost `woocommerce.css`, `woocommerce-layout.css` and
  `woocommerce-smallscreen.css`, and left the My Account forms unstyled.
- **`wp_common_block_scripts_and_styles` fires `enqueue_block_assets`** from
  `wp_enqueue_scripts`. Plugins hang block-theme stylesheets off that action
  (WooCommerce enqueues `woocommerce-blocktheme.css` there), so leaving it
  unfired costs them with no error.
- **`$wp->request` is the request path alone**, no leading or trailing
  slash and no query string, set when the main query is seeded. Plugin code
  builds URLs from it: WooCommerce points its add-to-cart form at
  `home_url( add_query_arg( $_GET, $wp->request ) )`, so an empty request
  posted the form to the SITE ROOT — the item was added but the shopper
  landed on the home page with no notice.

Open: on a singular render the engine does not set the global `$product`
(WooCommerce sets it from `the_post`), so WooCommerce's add-to-cart block
believes it is a descendant of the single-product block. Visible as the
form action losing its trailing slash and a missing `product` wrapper
class; behaviour is otherwise correct.

## What a classic commerce theme needed (Storefront, 2026-09-01)

WooCommerce's own Storefront theme (classic PHP, 4.6.2) runs on the engine.
Two things blocked it, and the symbol report named neither clearly:

- **Four template tags**: `get_the_category_list`, `get_the_tag_list` (over
  `get_the_term_list`, which was a placeholder), `the_post_navigation` /
  `get_the_post_navigation` with the adjacent-post link family, and
  `edit_comment_link` / `get_edit_comment_link`. Shapes captured: category
  lists emit a `post-categories` UL when the separator is empty and bare
  `rel="category tag"` links when it is not; term links carry `rel="tag"`;
  adjacent links carry `rel="prev"`/`rel="next"` inside the caller's format,
  with `%link` the anchor and `%title` the title. In the navigation block an
  explicit `aria_label` wins, otherwise a caller-supplied
  `screen_reader_text` drives it, and only with neither does the default
  stand.
- **`WP_Theme` implements `ArrayAccess`.** Storefront reads its own headers
  as array offsets, and without it the theme fatals with "Cannot use object
  of type WP_Theme as array" — which the symbol gate reports as a skipped
  THEME with no missing functions, not as a missing symbol. The offsets are
  display names (`Name`, `Title`, `Author Name`, `Theme Root URI`, …), not
  the internal header keys; writes are ignored.

GOTCHA: the symbol gate's cache (`minn_runtime_symbols`) is keyed on the
plugin or theme's mtime, so it does NOT notice the ENGINE gaining the
functions a skipped component wanted. After adding runtime symbols, delete
that option (from the REFERENCE's wp-cli on a swapped site) or the
component stays skipped and the interim template keeps standing in.

Storefront gaps still open: `wp_page_menu` omits the Home item and the
`current_page_item` class, and the widget sidebars do not render, so the
header nav is unstyled and the sidebar is absent. The product grid,
breadcrumbs, sorting, result count, pagination, prices and sale badges all
match the reference.

## The page-list menu and block widgets (2026-09-01)

- **`wp_page_menu`** builds the list a classic theme falls back to when no
  menu is assigned. Captured rules: the optional home item writes an empty
  class attribute (`<li >`), a page item carries `page_item page-item-{id}`
  plus `current_page_item`, and current is decided by the QUERIED OBJECT,
  not `is_page()` — a plugin can point an archive at its page, as
  WooCommerce does for the shop. When the args carry a non-empty
  `fallback_cb` (which is how `wp_nav_menu` invokes it) the list is wrapped
  in a plain `<ul>` and the caller's `before`/`after` are IGNORED; without
  it, before/after are used and no ul is added. Storefront's whole header
  nav is this path, and the missing ul was why its CSS bound to nothing.
- **`wp_widgets_init` runs on `init` at priority 1** and must register
  `WP_Widget_Block` before firing the action. Every widget the block editor
  saves is a block widget, so a sidebar filled in a modern WordPress has
  nothing else in it: without that class the instances never register and
  `dynamic_sidebar` renders an empty container.
- A block widget's wrapper gains a second class named after the first
  block in its content (`core/search` is `widget_search`, `core/paragraph`
  is `widget_text`, `core/latest-posts` is `widget_recent_entries`; a block
  with no legacy equivalent adds nothing). `widget_block_content` runs
  `do_blocks` at 9 and `do_shortcode` at 11. The reference also runs
  `wp_filter_content_tags` at 12 to fit out images; the engine renders
  images through `Blocks\ImageTags` on the way out instead, so adding it
  would fit the same images twice.

## What a failure looks like (2026-09-01)

`Minn\Http\Failure` is the whole public face of an error, and the engine's
boundary is `Engine::serve()`: a database connection failure answers 503
"Error establishing a database connection", missing salts and any uncaught
Throwable answer 500 "Something went wrong", and a PHP fatal that never
becomes a Throwable is caught by a shutdown handler that sends the same
500. Detail never reaches the page; it goes to the log as one line,
`Minn Engine: {class}: {message} in {file}:{line}`.

Observed, with WP_DEBUG_LOG on and WP_DEBUG_DISPLAY off:

| Case | Response | Log |
|---|---|---|
| Undefined function in a plugin | 500 page | `Error: Call to undefined function …` |
| Uncaught exception | 500 page | `RuntimeException: …` |
| TypeError from bad arguments | 500 page | `TypeError: …` |
| Fatal after output began | 500 page | `Error: …` |
| `E_USER_WARNING` | page renders normally | PHP's own warning line |
| Undefined array key | page renders normally | PHP's own notice line |
| Exception in a REST handler | 500 HTML page | `RuntimeException: …` |
| A plugin that will not parse | site keeps serving, plugin skipped | `plugin X failed while loading: syntax error …` |

Two facts worth keeping: a REST failure answers HTML, not JSON, and the
reference does the same (its own "WordPress › Error" page), so that is
parity rather than a gap. And a plugin with a syntax error is skipped by
the symbol gate instead of taking the site down, which is better than the
reference's behaviour.

Recovery (2026-09-01): a fatal in a plugin or theme no longer keeps a site
down. The file the failure came from names the extension it belongs to
(`Minn\Runtime\Recovery::blame()`), the engine records that extension as
paused and answers with the error page, and the NEXT request loads without
it, so the site comes back on its own. `Minn\Http\Failure::onFatal()` is
the seam; the engine installs the recorder once the database is in hand.

- A pause is a site-wide decision, so it needs two things a single crafted
  request cannot supply. First, the failure must happen while the
  extensions BOOT: `Plugins::load()` arms recovery around the plugin and
  theme includes and the boot actions through `wp_loaded`, and disarms it
  after. A failure later in the request (a REST callback, a shortcode, a
  template) answers to that request's input; it is logged and the request
  ends in the error page, and nothing is paused however often it repeats.
  Second, it must be the SECOND boot failure inside ten minutes: the first
  is a strike in the engine's `minn_recovery_strikes` option (JSON, pruned
  as it is read), the second pauses. A pause or a resume clears the strikes.
- The paused list is stored where WordPress stores it, in the same shape:
  `paused_plugins` keyed by plugin file, `paused_themes` by stylesheet,
  each holding `type`, `file`, `line`, `message`. A site that ejects back
  to WordPress finds the pause it left with.
- Only `plugins/` and `themes/` are ever blamed: a fatal in the engine is
  not a plugin's fault and pausing something would not fix it. A symlinked
  plugin reports its real path; blame maps it home through the same
  realpath map `plugins_url()` uses.
- A fatal before the database is reachable cannot be recorded; the error
  page still answers.
- `wp minn recovery [status|resume <name> [--theme]|resume-all]`.
- Recording never takes the request down: a failure inside recovery is
  logged and the error page still goes out.
- Suite `tests/recovery.test.php` (17) drives the whole loop against a
  plugin that really fatals at `init`, and against one whose REST route
  fatals three times without being paused.

DIFFERENCE from the reference, deliberate: WordPress emails the
administrator a recovery-mode link and keeps the site broken until they
use it. Minn pauses first and tells the log, so the site is already back
by the time anyone reads it. There is no recovery-mode email and no
recovery-mode session.

With debug display on (`WP_DEBUG_DISPLAY`, else `WP_DEBUG`), an uncaught
Throwable answers the same page with the cause on it: the class, the
message, the file and the line (`Failure::detailed()`). A site that has
not asked to see errors never learns that much from a response.

`public/wp-content/mu-plugins/minn-error-lab.php` on the dev site triggers
each case on demand (`/?minn-error=fatal|exception|error|warning|notice|late`,
plus `/wp-json/minn-error-lab/v1/boom`). It is inert without the parameter.

## Reading the catalogue: what the popular themes and plugins ask for

The symbol gate is a static read, so it can judge a plugin's source with
no database and no facade loaded. That turns "which of WordPress's
plugins would run on Minn" from a question you answer one site at a time
into one you answer over the whole directory in a few minutes, and the
answer is a work queue rather than an opinion.

- `Minn\Runtime\SymbolGap` is the part of the reference's interface the
  runtime does not answer: the names in `data/api-names.json` that no
  facade file defines. `Symbols::missingAgainst()` judges a folder
  against an exported gap; `Symbols::missing()` asks the running engine
  the same question. The two agree row for row, checked against the 48
  plugins on the anchor dogfood site.
- `php tests/tools/symbol-gap.php` writes `contracts/api/symbol-gap.json`.
  `tests/tools/compat-scan.php <gap> <list>` scans a directory of folders
  with nothing but the tokeniser. It is built to run somewhere that holds
  plugin source but no engine: the WP Beacon mirror box carries a git
  repository per wp.org slug, ranked by installs in its own `state.sqlite`,
  so a run extracts each ranked slug's HEAD tree through tmpfs, scans it,
  and throws it away.
- `tests/tools/compat-report.php <report> [installs]` turns the verdicts
  into the queue. The order is a **greedy set cover, not a frequency
  count**: the symbol that appears in the most plugins is usually not the
  one that finishes any of them, so each step picks the symbol that turns
  the most components green once everything above it exists. A symbol that
  completes nothing on its own is still taken when it has the most installs
  behind it, because it moves the most components closer to done.
- What the first read said, and why the shape matters more than the number:
  most of the catalogue was already one or two names short, not
  fundamentally unsupported. Of the 697 skipped plugins in the first scan
  of the top 2,000, **296 were exactly one symbol short**. The work is a
  long tail of small, well-understood functions, and it pays out in steps.

The queue is dominated by two kinds of name. Front-end template tags and
helpers are real work and get written. Names that exist only to draw a
wp-admin screen (list tables, the customizer's objects, the importer,
TinyMCE, the core upgrader) are Mute by the rule in `lexicon.md`: they are
recorded as placeholders so a plugin boots, and they never graduate just
to shorten a list. The gate is whole-folder, so an admin-only symbol
blocks front-end loading too; that is the reason placeholders exist at all.

GOTCHA when adding a placeholder class: the generated file loads before
`wp-api/*.php`, so a placeholder cannot extend a class declared there. The
customizer's subclasses live in `wp-api/customize.php` beside their parent
for exactly that reason.

### Facts the three catalogue waves settled

Each of these was wrong or missing in the engine and was found by running
a probe against the reference, never by reading its source.

- **A comment page link collapses to the bare permalink** for page one
  when a site reads oldest comments first, and for the caller's *stated*
  last page when it reads newest first. A caller that states no page count
  gets no collapse at all, so `get_previous_comments_link()` never
  collapses and `get_next_comments_link()` can. The engine used to write
  `comment-page-N` for every page.
- **A posts navigation puts the older posts in the previous slot.** A
  reader moving back through an archive is moving forward through the page
  numbers, so `nav-previous` holds the next page's link.
- **`get_comment()` falls back to the comment being read for any empty
  id**, not only for `null`. Every `the_*` comment tag passes `0`.
- **The author posts link carries no title attribute.**
- **`wp_logout_url()` puts the action ahead of the redirect** in its query
  string.
- **`wp_trim_excerpt()` is plain text**: the rendered excerpt's markup is
  stripped back off.
- **`fetchpriority="high"` goes to the first eager image that covers at
  least 50,000 pixels**, filterable through `wp_min_priority_img_pixels`,
  so a thumbnail or an avatar leaves the flag for a later image. The
  engine gave it to the first eager image whatever its size. Derived by
  bisecting on the reference: 273x182 does not qualify, 274x183 does.
- **A proxy is skipped for the name `localhost` and for the site's own
  host only**, never for a loopback address written out in full.
- **The language picker's attribute order is not tidy**: the English
  option carries `data-installed` before `selected`, an installed
  translation carries it after.
- **`[gallery]` treats `ids` as the include list** and orders by
  `post__in` unless the caller asked for another order.

### Honest gap: the eager-image budget on shop-dogfood

On `shop-dogfood`'s `/about/` the body holds exactly one image, 1000x750.
The reference gives it `fetchpriority="high"` and no lazy attribute; the
engine marks it `loading="lazy"`, which means `RenderState::nextImage()`
had already passed three by the time the only visible image was rendered.
Something ahead of the body is spending the budget: a call to
`wp_get_loading_optimization_attributes` for markup that is built and then
discarded, or one image counted more than once on the way through the
content filters.

This predates the catalogue waves (verified against the engine as it
stood at commit 80363f4) and is not the 50,000-pixel rule, which is
right. What is missing is the rule for **which calls count** against the
budget. Deriving it needs a probe that renders a page on the reference
with a hook counting every call, not a guess.

### Inlining a small stylesheet

A style registered with a `path` datum is saying it may be inlined.
`wp_maybe_inline_styles()` runs on `wp_head` at priority 1, and for each
enqueued style whose file is small enough it reads the file, drops the
style's `src`, and adds the CSS as an `after` inline block, so the page
carries `<style id="{handle}-inline-css">` instead of a `<link>`.

The rule, derived by bisecting on the reference:

- The cap is **per file, never cumulative**. Three 9,000-byte stylesheets
  all inline, 27,000 bytes in total; one 50,000-byte stylesheet does not.
- The default cap is **40,000 bytes**, and 40,001 is already too big. The
  number is only visible by watching what the caller passes into
  `styles_inline_size_limit`: asking `apply_filters()` with a default of
  your own tells you nothing, which is a trap worth remembering for any
  filtered constant.
- An inlined style keeps the **file's own URL** as its `sourceURL` trailer
  (under `WP_DEBUG`), not the handle, so the engine carries the resolved
  src across the unsourcing step.

This was found by implementing `get_parent_theme_file_uri()`: Twenty
Twenty-Five's `functions.php` calls it, so until then the whole theme file
was gate-skipped and never ran. The moment it loaded, the theme enqueued
its stylesheet and the missing inliner showed as a `<link>` the reference
does not print. Every symbol added lets more real code run, and that code
names the next gap.

## The shop-dogfood review (2026-09-06)

A walk of https://shop-dogfood.localhost against its parked WordPress on 8127
turned up one fatal, one wrong redirect, a silent recovery, and the last
plugin the symbol gate skipped. Every fact below was captured from the
reference before the engine changed.

**An option add is an upsert.** The reference writes every `add_option` as
`INSERT ... ON DUPLICATE KEY UPDATE option_name, option_value, autoload`
(read from the MariaDB general log). Two requests that both find a transient
expired both delete it and both add it back; on the reference the second
write lands over the first, on the engine it threw `Duplicate entry
'_transient_timeout_coblocks_template_parts_query'`. `Runtime\Options::upsert()`
is the one writer now (`add()` and the CLI's `option add` / first `option
update` share it); it answers true when a row was inserted or changed, false
when the same value was already there, which is what `add_option` reports.

**`wpdb::prepare` quotes only a bare `%s`.** Captured matrix: `%s` becomes
`'a'`; `%1$s` becomes `a` with no quotes, so a plugin's `TRUNCATE %1$s` names
its table (Gravity SMTP's debug-log purge failed only on the engine); `'%1$s'`
keeps the quotes it was written with; `%2$s-%1$s` addresses the list;
`%1$s %s` gives `a 'a'` because unnumbered placeholders count on their own
from the first argument (vsprintf's rule); `%d` casts (`12abc` is 12); `%.2f`
keeps its precision; `%5s` pads and does not quote; `%i` and `%1$i` backtick
an identifier; a placeholder with no argument behind it empties the whole
query. `Runtime\Placeholders::fill()` holds the rules; the facade escapes
through the connection and strips the placeholder marker. Not pinned: with
`%%` and a surplus argument the reference leaks its per-site placeholder hash
into the returned string.

**`$shortcode_tags` is the registry.** The global is bound by reference to
`Runtime\Shortcodes::tags()` in `_minn_bind_hook_globals`, so Redirection's
`array_merge([], $shortcode_tags)` / `remove_all_shortcodes()` / restore
sequence works (it was `array_merge` on null: `/pricing/` answered 500 where
the reference 301s to `/plans/`).

**Recovery blames the plugin file the loader checks.** `Recovery::blame()`
used to name `folder/subfolder` for a fatal below a plugin's top level;
`Plugins::load` compares the paused list against `active_plugins` entries, so
`orderable/inc` and `redirection/models` sat in `paused_plugins` and paused
nothing, and the site never came back on its own. The name is now the active
plugin file whose folder holds the fatal's file (the bare file for a
single-file plugin, the folder alone when no active entry claims it). The
first fatal after the fix paused `polylang/polylang.php` on the second strike
and the next request recovered, as designed.

**Polylang loads.** Its seven missing symbols: `sanitize_user_field`
(contexts raw/edit/db/display/attribute/js; `ID` cast to int; the field's
hooks drop the `user_` prefix, so `user_url` is filtered by `pre_user_url` /
`user_url`, and in edit context `user_url` passes through `esc_url` before
`esc_attr`), the reference's default user filters (`pre_user_{display_name,
first_name,last_name,nickname}`: sanitize_text_field + wp_filter_kses +
_wp_specialchars at 30, and `user_*` _wp_specialchars at 30;
`pre_user_description` wp_filter_kses; `pre_user_email` trim + sanitize_email
+ wp_filter_kses, `user_email` sanitize_email; `pre_user_url`
wp_strip_all_tags + sanitize_url + wp_filter_kses, `user_url` esc_url),
`_get_non_cached_ids` (ints, deduplicated, order kept, 0 kept),
`wp_cache_add_multiple` (per-key results, an existing key false),
`wp_cache_set_terms_last_changed`, `_split_shared_term` (the id back; a term
row belongs to one taxonomy row on any site the engine meets),
`wp_apply_generated_classname_support` (`[]` for core/paragraph, whose
className support is false; `['class' => 'wp-block-x-y']` otherwise);
`wp_popular_terms_checklist` is a placeholder (a wp-admin checklist).
Then `_get_page_link`, which the gate had missed (below): a published page's
pretty path, `%pagename%` with `leavename`, the slug path of an unpublished
page with `sample`, `?page_id=N` for an unpublished page otherwise, and
`?page_id=` for a page that does not exist; `get_page_link` answers the
static front page with the home URL and filters `page_link` with `$sample`.

**The calendar.** `WP_Widget_Calendar` was the class Polylang's own calendar
extends. `get_calendar()` draws the month `Minn\Front\Calendar` renders:
the month comes from the `$m` / `$year` / `$monthnum` globals (the query's
vars; the reference ignores year and month keys in its `$args`), else the
site's current month; the first row's padding cell is `<td colspan="N"
class="pad">` and the last row's `<td class="pad" colspan="N">` (attribute
order differs, themes style both); a day with a published post of the type
links its day archive with `aria-label="Posts published on F j, Y"` in that
fixed format whatever `date_format` says; today's cell carries
`id="today"`; the nav links the nearest months with posts on either side as
`&laquo; Aug` / `Aug &raquo;` and prints `&nbsp;` where there is none;
`initial` chooses weekday initials or three-letter abbreviations; the output
passes the `get_calendar` filter. The widget prints `<div id="calendar_wrap"
class="calendar_wrap">` for the first instance on a page and `<div
class="calendar_wrap">` after; `update()` keeps the old instance and sets a
sanitize_text_field'd title; `form()` prints the title input. It is
registered in `wp_widgets_init` before the block widget. The other thirteen
default widgets (Pages, Archives, Media ×4, Meta, Search, Text, Categories,
Recent Posts, Recent Comments, RSS, Tag Cloud, Nav Menu) are still missing:
a classic sidebar holding one renders nothing for it (roadmap E5).

**Two blind spots in the symbol gate.** (1) A `class_exists()` guard
followed by `require_once ABSPATH . 'wp-includes/default-widgets.php'` is
not a fallback: the site skeleton provides that file empty, so the require
succeeds and the class is still missing, and the plugin fatals at load. The
gate rightly ignores guarded names; the engine-side rule is that every class
in the reference's interface a plugin might extend has to exist. (2) A method
declared with the same name as a missing global function hid the call to
that function: the verdict subtracted `declared` (every function token) from
the calls instead of `declaredGlobal`. Fixed (`Symbols::READER` is 3, so
cached verdicts are re-read); the sharper gate found `_get_page_link` in
Polylang at once. Probe-runtime note: `init` never fires under
`run-api-probe.php`, so a row that needs the widget factory calls
`wp_widgets_init()` itself when `widgets_init` has not run.

**Small:** `curl_close()` is gone from `Http\Transport` (deprecated in PHP 8.5,
a no-op since 8.0).

## The default widgets (2026-09-07)

Every widget the reference registers in `wp_widgets_init` now exists, in
its order: Pages, Calendar, Archives, Audio, Image, Gallery, Video, Meta,
Search, Text, Categories, Recent Posts, Recent Comments, RSS, Tag Cloud,
Navigation Menu, Custom HTML, Block. `tests/tools/widgets-probe.php`
renders each through `the_widget()` with the wrappers `<w class="%s">` /
`<t>` and pins the output, the update() shapes, the factory's names and
options, and the template functions behind them; fixture
`contracts/fixtures/api/widgets.json` (55 rows), diffed by the api suite.
The classes live in `wp-api/classes/`; the list, dropdown and archive
markup they print comes from `Front\PageList`, `Front\ListSpacing` and
`Front\Archives` over `Content\Posts`.

**Facts the widgets settled.**

- `wp_list_pages`: `<li class="page_item page-item-N[ page_item_has_children][ current_page_item|current_page_ancestor[ current_page_parent]]"><a href="…"[ aria-current="page"]>Title</a>`; children under `\n<ul class='children'>\n`, one tab per level, `</ul>\n`; `</li>\n` closes each; `title_li` wraps as `<li class="pagenav">Title<ul>…</ul></li>`; `depth` 1 keeps the has-children class, `-1` flattens; `include` keeps nesting among the included, and a page whose parent is not in the set stands at the top; `item_spacing` discard drops the whitespace. `wp_dropdown_pages`: `<select name='…'[ class='…'] id='…'>` (name, class, id), `\t<option class="level-N" value="…"[ selected="selected"]>` with three `&nbsp;` per level, `show_option_none` before the pages.
- `wp_get_archives`: `\t<li><a href='…'>Label</a>[&nbsp;(count)]</li>\n`; option format `\t<option value='…'> Label [&nbsp;(count)]</option>\n`; monthly `F Y`, yearly `Y`, daily `F j, Y`, weekly `F j, Y&#8211;F j, Y` linking `?m=YYYY&w=N` (the query form even under pretty permalinks), postbypost by date and alpha by title with the post's link and title.
- Widget templates keep the reference's tabs: Pages `\n\t\t\t<ul>\n\t\t\t\t` + list + `\t\t\t</ul>\n\n\t\t\t`; Archives the same with `wp_get_archives` echoing in place; Categories the same with `wp_list_categories`; Meta `\n\t\t<ul>\n\t\t\t` + register + `\t\t\t<li>` + loginout + the two feeds + `\n\n\t\t\t` + powered-by (its default ends in a newline) + `\t\t</ul>\n\n\t\t`; Text `\t\t\t<div class="textwidget">…</div>\n\t\t`; Recent Posts prints `\n\t\t` before its wrapper and eleven tabs before each `<li>`.
- Dropdown ids: Archives `archives-dropdown-{number}`; Categories `cat` for the first instance on a page, then `categories-dropdown-{number}`; Recent Comments `recentcomments` for the first, then `recentcomments-{number}`. The dropdown script ends with `\n//# sourceURL=WP_Widget_X%3A%3Awidget` (the widgets add it themselves; `wp_print_inline_script_tag` adds none).
- Default titles: Pages, Archives, Meta, Categories, Recent Posts, Recent Comments, Tags (or the taxonomy's name); Search, Text, Nav Menu and the media widgets have none; RSS says Unknown Feed.
- Recent Posts iterates `$query->posts` without `the_post()`, so the global post is untouched (the reference's av ids and gallery ids after it are `…-0-…`).
- Media widgets: `update()` validates each field against the instance schema and skips what fails (a non-integer id, a negative width, a value outside an enum, `yes` as a boolean, a comma list for an array), sanitises what passes (uri → `esc_url_raw`, so `javascript:` becomes `""`), and keeps the old instance's other keys; `''` is a valid boolean and reads false. An image by URL prints `<img class="image …" src alt width height decoding="async" loading="lazy" />`; an attachment gets `image wp-image-N {classes} attachment-{size} size-{size}` with `style="max-width: 100%; height: auto;"`, its link, then the caption figure; a video by URL renders only for YouTube or Vimeo (other URLs go to oEmbed, which is empty for a file), with `width="…" height="…"` stripped and the wrap at `width:100%;`; a gallery passes its ids as `include`.
- The audio and video shortcodes: `id="audio-{post}-{instance}"` (post 0 outside a post; the engine had the two the wrong way round), `loop` / `autoplay` / `muted` printed bare when on, a YouTube or Vimeo source typed `video/youtube` / `video/vimeo`, and the cache buster joined with `&` when the URL already has a query. `[gallery]`, `[caption]`, `[wp_caption]`, `[audio]` and `[video]` are registered by default now, as the reference registers them.
- `get_search_form()` prints the reference's multi-line form (tabs and all), the `searchform` variant without html5 support, and `aria_label`; `remove_theme_support()` records the feature as removed so a block theme's implied support does not return.
- `wp_nav_menu` names its container `menu-{menu slug}-container` when `container_class` is empty (it used the theme location before).
- `wp_tag_cloud` with `show_count` puts `<span class="tag-link-count"> (N)</span>` inside the link.
- `img_caption_shortcode`: `<figure [id="x" ][aria-describedby="caption-x" ]style="width: Wpx" class="wp-caption align…">…<figcaption [id="caption-x" ]class="wp-caption-text">…</figcaption></figure>`; the content alone when the width is 0 or the caption empty; the `div`/`p` shape without html5 caption support.
- `fetch_feed` fetches through the safe HTTP client and reports `WP HTTP Error: …` on a transport failure; a host that does not resolve is now `A valid URL was not provided.` in `wp_http_validate_url`, as the reference says. (Since 2026-10-05 the engine parses feeds too: "Reading feeds" below.)
- The Recent Comments head style (`<style>.recentcomments a{…}</style>`) prints when the widget is active in a sidebar; the probe could not make one active in either stack, so that string is unpinned.

**Oracle quirk, not a gap (shop-dogfood, 2026-09-07):** the parked
WordPress on 8127 answers `/feed/` with a 500 and a body cut off by a
critical-error element. Advanced Responsive Video Embedder's
`oembed_dataparse` filter fatals under PHP 8.3 when an embed's provider
name is null (`sane_provider_name(NULL)`), mid-way through the feed's
items. The engine renders the same feed complete; the page for the post
with that embed cannot be compared until the plugin is fixed upstream.

## The top of the catalogue (2026-10-05)

The plugins with the most installs that the gate still skipped were each
one or two names short. This round takes the names that block every
wp.org plugin above a million installs, judged against the plugins as
they ship today (not the September mirror scan: Jetpack now asks for
`WP_Admin_Bar` where it asked for `wp_supports_ai`, AIOSEO for
`WP_Block_Parser`, WPForms for `wp_kses_uri_attributes`).

**Placeholders (Mute, `data/placeholder-symbols.json`).** The importer's
upload screen and cleanup (`wp_import_upload_form`,
`wp_import_handle_upload`, `wp_import_cleanup`), the image editor's
actions (`stream_preview_image`, `wp_save_image`, `wp_restore_image`),
`wp_category_checklist`, `wp_plupload_default_settings`,
`wp_heartbeat_settings`, `wp_admin_bar_render`, `_unzip_file_pclzip`, and
the classes `WP_Admin_Bar`, `WP_Recovery_Mode_Link_Service` (its two
constants are what All-In-One Security reads) and `WP_Translations`
(Loco Translate only tests `instanceof`; translations themselves are a
separate gap). Every caller of these is a wp-admin screen or an upgrader
path.

**The helpers (fixture `contracts/fixtures/api/plugin-symbols3.json`).**

- `convert_invalid_entities`: only the exact unpadded decimal references
  `&#128;` to `&#159;` change, each to the Unicode reference its
  Windows-1252 character meant; the five codes Windows-1252 leaves
  unassigned (129, 141, 143, 144, 157) are removed. Hex spellings,
  padded spellings and references without a semicolon stay as written.
- `wp_is_valid_utf8`: well-formed UTF-8. Overlong forms, surrogates, code
  points past U+10FFFF, stray continuation bytes, truncated sequences and
  the bytes F5 to FF fail; NUL, noncharacters and a byte order mark pass.
- `wp_remove_surrounding_empty_script_tags`: the trimmed input must be
  `<script>` + at least one character + `</script>`, tag names in any
  case, no attributes; the code comes back untrimmed (`<script>a</script><script>b</script>`
  gives `a</script><script>b`). Anything else fires `_doing_it_wrong`
  (version `6.4`) and returns a `console.error("Function …() used incorrectly in PHP. …")`
  line in place of the script.
- `wp_autoload_values_to_autoload`: `yes`, `on`, `auto-on`, `auto`. The
  filter can only narrow it: its result is intersected with that list
  with the filtered keys kept (`['no', 'yes']` gives `{"1": "yes"}`). A
  filter that returns a string fatals the reference.
- `wp_get_comment_fields_max_lengths` reads the comments table as it is:
  a character column allows its declared length, a text column ten fewer
  than it holds (tinytext 245, text 65525, mediumtext 16777205). Checked
  on an altered table, where both stacks gave the same numbers.
- `wp_kses_uri_attributes`: seventeen names (`action` to `xmlns`),
  filterable.
- `wp_high_priority_element_flag`: true until something takes high fetch
  priority. Only a real boolean moves it (0, 1, `'yes'`, null and arrays
  just read it), and the setter returns the value after the call. An
  image that asks for `fetchpriority="high"` always keeps it and closes
  the flag; an eager image (`loading` false) takes it only while the
  flag is open. The flag is the same state the engine's own block images
  claim, so a plugin that closes it (Elementor does, to place its own)
  leaves every later image without the attribute.
- `_get_dropins`: on a single site, the eight files from `advanced-cache.php`
  to `fatal-error-handler.php`, each with its description and `WP_CACHE`
  or `true`.
- `get_post_mime_types`: six groups (image, audio, video, documents,
  spreadsheets, archives), each `[label, manage label, _n_noop count
  label]`, through `post_mime_types`.
- `extract_from_markers`: every block's lines in order. A line opens or
  closes a block when it contains `# BEGIN {marker}` / `# END {marker}`
  anywhere, case-sensitive (so `# BEGIN GG` opens `G`); lines that start
  with `#` are dropped (the preamble, nested markers) while indented
  comments stay; lines split on `\n` only, keeping `\r`; a block still
  open at the end keeps the empty line after the final newline.
- `apache_mod_loaded` is false off Apache whatever default it is handed
  (the engine returned the default), so `got_mod_rewrite` is false under
  FrankenPHP, nginx and the command line. Apache and LiteSpeed fall to
  `apache_get_modules()` or the default; that branch is not probed.
- `saveDomDocument` writes `saveXML()` with every `\n` as `\r\n` and
  returns nothing. An unwritable target fatals the reference, so it is
  not pinned.
- `add_allowed_options` appends each new name to its group once, creating
  groups; a group handed as a string adds nothing (the reference warns).
  With no list it works on the global `$allowed_options` and returns it.
  `add_option_whitelist` is the deprecated name (5.5.0).
- `wp_is_recovery_mode` is false: the engine keeps no recovery-mode
  session, its own recovery pauses the failing plugin.
- `wp_supports_ai`: false without running the filter when `WP_AI_SUPPORT`
  is defined and falsy; otherwise `(bool) apply_filters('wp_supports_ai', true)`
  (a filter returning `'yes'` reads true).

**Gate blind spot this round hit.** A `function_exists('x')` anywhere in a
folder excuses every call to `x()` in it. Jetpack 16.2 guards
`wp_supports_ai()` in its search package and calls it bare in
`_inc/lib/class-jetpack-ai-settings.php`, so once the other two names it
lacked existed, the gate passed it and it fatalled at boot on the dogfood
site until `wp_supports_ai` was written (recovery paused it after the
second failure, as designed). Guards that count per file would have caught
it, at the price of skipping plugins that guard in a loader and call from
an included file.

## kses past the attribute names (2026-10-05)

What `wp_kses` does with the parts of an allowlist beyond tag and attribute
names; fixture `contracts/fixtures/api/kses-rules.json` (probe
`tests/tools/kses-rules-probe.php`), and the security record in
`docs/security-review-2026-10-05.md`.

- A caller's allowlist is literal. Only the attributes it lists are kept
  (nothing global is implied), a listed attribute is allowed whatever it
  maps to (even `false`: the reference only asks whether the name is
  there), an array is a list of value rules, and `data-*` lets the tag take
  any `data-` name. The post table already lists its global attributes per
  tag; the comment table (`data`) lists none.
- Value rules: `maxlen` / `minlen` count bytes; `maxval` / `minval` need a
  whole number of one to six digits with up to six spaces either side;
  `valueless` compares the attribute's form (`y` bare, `n` with a value);
  `values` compares without case; `value_callback` decides; an unknown rule
  passes. A rule that fails drops the attribute. A `values` rule that is
  not a list, or a callback that does not exist, fatals the reference; the
  engine just drops the value.
- `required`: if a required attribute is missing or was dropped, the tag
  keeps none of its attributes (`<object class="x">` gives `<object>`).
- `_wp_kses_allow_pdf_objects` (the post table's `object[data]` rule): an
  `http` or `https` URL (scheme lowercase) whose host and port equal those
  of `wp_upload_dir()['url']`, the current month's folder (not `baseurl`,
  not the home or site URL), with no credentials, query or fragment, and a
  path ending in `.pdf` (case-sensitive). `object[type]` must be
  `application/pdf` in any case.
- `a[download]` in post content is valueless: `<a download>` stays, any
  value drops it.
- The caller's `$allowed_protocols` decide which schemes survive in every
  URI attribute (`wp_kses_uri_attributes()`, filterable); an empty list
  means the default list. A cut scheme leaves the rest of the value
  (`http://x` becomes `//x`, `mailto:a@b` becomes `a@b`).
- `wp_allowed_protocols` follows `kses_allowed_protocols` on every call
  until `wp_loaded` starts; from then on the last list it computed stands,
  even when a filter is added before the next call. Not pinned in the
  fixture, because the engine's probe runner never fires `wp_loaded`;
  checked on both stacks by hand.

## Reading feeds (2026-10-05)

`fetch_feed()` returns a feed object with the SimplePie API plugins call
(`SimplePie\SimplePie`, `Item`, `Author`, `Category`, `Enclosure`,
`Source`, the captions, credits, ratings and the rest, the `Cache\Base`,
`HTTP\Response` and `RegistryAware` interfaces, `Misc`, and the
pre-namespace names `SimplePie`, `SimplePie_Item`, ... as aliases), plus
`WP_Feed_Cache_Transient`, `WP_SimplePie_File` and
`WP_SimplePie_Sanitize_KSES`. None of it is SimplePie: `Minn\Feed` reads
the bytes (`Charset`, `Tree`, `Document`, `Locator`, `Iri`, `Dates`,
`Parts`) and `wp-api/simplepie/` maps the API onto it. The classes load the
first time anything names them, as the reference loads them only inside
`fetch_feed()`. The RSS widget (`wp_widget_rss_output`,
`wp_widget_rss_process`, `WP_Widget_RSS`) and the `core/rss` block
(`render_block_core_rss`) print the items. Suite `tests/feeds.test.php`
stages `tests/fixtures/feeds` in the test site's uploads and diffs
`tests/tools/feeds-probe.php` on both stacks, 120 rows.

- `fetch_feed($url)`: `wp_feed_cache_transient_lifetime` sees `(43200,
  $url)`, then `wp_feed_options` sees `($feed, $url)` by reference, then
  the cache asks the same filter again with `(43200, md5($url))`. The
  parsed feed is cached in the site transients `feed_{md5(url)}` (the tree
  under `child`, with `type`, `headers`, `build`) and `feed_mod_{md5(url)}`
  (the time); a second fetch sends no request. The engine reads a cache the
  reference wrote; the reference refetches over one the engine wrote (its
  cache records a build number particular to the install). `fetch_feed('')`
  is a feed with no items and no error.
- Errors, as `simplepie-error`: `WP HTTP Error: …`; `Retrieved unsupported
  status code "404"`; `{url} is invalid XML, likely due to invalid
  characters. XML error: Mismatched tag at line 3, column 58` (the XML
  parser's own message; lines count one more than the document's when it
  has an XML declaration, because the reference reads the document with a
  line added after it); `A feed could not be found at `{url}`; the status
  code is `200` and content-type is `text/plain; charset=UTF-8``.
- An HTML page is searched for `<link rel="alternate">` feeds (RSS, Atom,
  RDF, XML types), resolved against the page; the first that is a feed is
  read, `subscribe_url()` names it, `get_all_discovered_feeds()` lists all.
- Encodings: a byte-order mark, then the XML declaration, then the
  Content-Type charset; ISO-8859-1 and Windows-1252 read as Windows-1252;
  everything comes back UTF-8. Named HTML references (`&mdash;`) read as
  characters unless the document carries its own DOCTYPE (then they are an
  `Undeclared entity error`, as on the reference).
- The tree (`$feed->data['child']`, `get_item_tags()`, `get_channel_tags()`,
  `get_feed_tags()`, `get_image_tags()`): every element is `data` (its own
  text, the whitespace between children included), `attribs` by namespace,
  `xml_base`, `xml_base_explicit`, `xml_lang`, and `child` when it has
  children. An Atom text construct of type `xhtml` keeps its markup as text
  (`<div>X<b>HTML</b> title</div>`, the xhtml namespace dropped); a `title`
  in RSS 2.0, 1.0 or 0.90 is stored escaped (`&amp;`, `&lt;`, `&quot;`,
  `&#039;`), a namespaced one (`dc:title`) is not.
- Values come back sanitized by construct: text escaped (double quotes,
  not single), HTML through `wp_kses_post` (a `<script>` loses its tags,
  keeps its text; relative URLs stay relative), URLs resolved and normalized
  (scheme and host lower case, default port dropped, dot segments removed,
  spaces and non-ASCII percent-encoded) then escaped. RSS titles and
  descriptions are HTML; an RSS image title is text (so it is escaped
  twice); Atom follows its `type`. A value that is only whitespace is
  absent.
- Relative URLs resolve against an explicit `xml:base`, else the channel's
  own link, else the feed URL; `get_base()` on an item is its permalink.
- Items: undated items first, as they came, then the dated ones newest
  first (equal dates in the reverse of document order). `get_id()`: Atom
  id, guid, dc:identifier, `rdf:about`, else `md5(permalink . title .
  content)` over the sanitized values (also what `get_id(true)` returns).
  Dates: Atom published, Atom updated, `pubDate`, `dc:date`;
  `get_updated_date()` only Atom updated. `get_date()` defaults to
  `j F Y, g:i a` and formats in PHP's time zone (UTC under WordPress);
  `'U'` gives the integer.
- Links: Atom links by `rel` (alternate by default; IANA URIs answer to the
  short name too), RSS `<link>`, and a guid unless `isPermaLink="false"`;
  `get_permalink()` falls back to the first enclosure. Authors: Atom
  authors, the RSS `<author>` as an email, `dc:creator` as a name; an item
  with none takes the feed's (Atom). Categories: Atom (term, scheme,
  label), RSS (text, `domain` as scheme), `dc:subject` (type `subject`);
  `get_label()` falls back to the term. Enclosures: Media RSS contents
  (with the item's thumbnails), Atom enclosure links, RSS enclosures;
  `get_length()` is an integer (`"x"` is 0), `get_size()` megabytes to two
  places, `get_real_type()` the type or one guessed from the extension.
- Channel: `get_language()` is the channel's language, else the root's
  `xml:lang` for Atom and RSS 1.0 (an empty string), else null; an RSS image
  with no size is 88 by 31.
- Several URLs (`fetch_feed([...])`): each read on its own, the items merged
  and sorted the same way, `get_title()` null, each item's `get_feed()` its
  own feed.
- The RSS widget output: `<li><a class='rsswidget' href='…'>title</a>`
  (plain title when the item has no link, `Untitled` without a title),
  then ` <span class="rss-date">` (the date format, the timestamp as
  given, not moved to the site's zone), `<div class="rssSummary">` (the
  description decoded, stripped, 55 words and ` [&hellip;]`, attribute
  escaped; empty div without one), ` <cite>` (the author's name, empty
  when there is only an email). No items: `<ul><li>An error has occurred,
  which probably means the feed is down. Try again later.</li></ul>`.
- The `core/rss` block: `<ul class="[is-grid columns-N ][has-dates
  ][has-authors ][has-excerpts ]{supports} wp-block-rss">`, each
  `<li class='wp-block-rss__item'>` with the title decoded, stripped and
  escaped (`(no title)` when empty; linked with `target="_blank"` and
  `rel` when set), `<time datetime="{c}" …>{date}</time> ` in the site's
  zone, `<span class="wp-block-rss__item-author">by {name}</span>`, and the
  excerpt (`excerptLength` words). An error is the placeholder notice with
  `<strong>RSS Error:</strong>`; no items, the "An error has occurred"
  notice.
- `wp_http_validate_url` now asks `http_allowed_safe_ports` (`[80, 443,
  8080]`, host, URL) for a URL that names a port, as the reference does.
- TLS trust: the reference verifies against the certificate list it ships
  (`wp-includes/certificates/ca-bundle.crt`), the engine against the
  system's. A certificate only the system trusts (a local development CA,
  such as the test site's own) is fetched by the engine and refused by the
  reference, so the widgets probe no longer reads the test site's own
  HTTPS feed; the staged feeds suite covers the widget instead.
- Not done: error columns on the line the reference fills with its entity
  declarations (a document with no line break after its XML declaration);
  autodiscovery through plain `<a>` links; the Flash-era `embed()` players
  (empty); `get_local_date()` converts the common strftime codes only.

## The Requests library (2026-10-05)

Plugins call the Requests library directly (`WpOrg\Requests\Requests::request`
and `request_multiple` in All-In-One Security and Jetpack, `IdnaEncoder` in
Elementor and Site Kit, `Ipv6::check_ipv6`, `Exception\Http\Status403`,
`Cookie`), so the engine has its own: 64 classes under the reference's names
in `wp-api/requests/`, the work in `Minn\Http` (`Punycode`, `Ipv6`,
`CookieText`, `CertificateName`, `IriParts`, `RawResponse`,
`RequestsNames`), sending through the engine's curl client. Suite
`tests/requests.test.php` stages `tests/fixtures/requests/echo.php` (it
answers with the request it saw) and diffs `tests/tools/requests-probe.php`
on both stacks, 71 rows. The reference side runs inside WordPress without
WP-CLI (`tests/tools/run-reference-probe.php`): WP-CLI ships its own copy of
the library and registers it first, so under WP-CLI that copy answers on
both stacks, and the engine adds only `Requests` and
`WP_HTTP_Requests_Hooks` around it.

- On the wire: `User-Agent: php-requests/2.0.17` (the `useragent` option),
  `Accept: */*`, `Accept-Encoding: deflate, gzip, br, zstd` (curl
  negotiates and decodes), `Connection: close`; a header whose value is an
  array is sent as `Array`. GET, HEAD and DELETE carry data in the query
  string; other methods send it as a form body (curl labels it
  `application/x-www-form-urlencoded`) or as the string given.
- Responses: `status_code`, `protocol_version` (1.1), `success` (2xx),
  `raw` (status line, headers, blank line, body), headers by lower-case
  name with every value kept (`$headers['x'] ` joins them with a bare
  comma, `getValues()` lists them), the `Connection` header dropped, a
  chunked body joined and a compressed one inflated. A request that is not
  blocking returns an empty Response; with `filename` the body goes to the
  file and `body` stays empty.
- Redirects are followed by the library, up to `redirects` (10): the
  method and data stay for 301, 302 and 307, a 303 becomes GET; `history`
  holds the earlier responses, the latest first; past the limit,
  `Too many redirects` (`toomanyredirects`). `follow_redirects` false
  returns the 3xx itself.
- Hooks, in order: `requests.before_request` (5 arguments, by reference;
  a hook can add headers), `curl.before_request` and `curl.before_send`
  (the curl handle), `curl.after_send`, `curl.after_request` (the raw
  response and info), `requests.before_parse` (6),
  `requests.before_redirect_check` (4), `requests.before_redirect` (5)
  for each hop, and `requests.after_request` (4) once, for the final
  answer. Priorities run lowest first.
- Errors: a transport failure is `cURL error {n}: {curl's message}` of type
  `curlerror` (inside `request_multiple`, an `Exception\Transport\Curl` of
  type `cURLEasy` in that request's slot); a non-http(s) URL is `Only
  HTTP(S) requests are handled.` (`nonhttp`); wrong argument types are
  `Exception\InvalidArgument` naming the caller, the position, the expected
  type and `gettype()` of what came (`integer`, not `int`), a plain
  function written `::name()`. `throw_for_status()`: a redirect only when
  redirects are refused (`Redirection not allowed`), otherwise the status's
  class (`404 Not Found`, `418 I'm A Teapot`, `StatusUnknown` with the
  response's code); `Http::get_class()` returns a known class with a
  leading backslash. `decode_body()` raises `Unable to parse JSON data:
  {json_last_error_msg}` (`response.invalid`).
- Cookies: `Set-Cookie` answers fill the response's jar, a cookie without
  a domain taking the request's host (host-only) and without a path the
  request path up to its last slash; attributes normalize (expires and
  max-age as timestamps, max-age counted from the reference time, the
  domain without its leading dot, case kept); a host-only cookie matches
  only the same text. `cookies` sent as `name=value; ...`.
  `format_for_set_cookie()` writes `secure=1` for a flag and ends in `"; "`
  when there are no attributes. The jar returns what it was given (a
  string stays a string until a request needs it).
- `IdnaEncoder::encode()` writes Punycode without Nameprep (case kept); a
  label of 64 bytes or more is refused (`idna.provided_too_long`,
  `idna.encoded_too_long`). `Ipv6::compress()` strips leading zeros only
  from groups that continue with a digit (`0db8` stays) and folds the first
  longest zero run. `Iri` normalizes as the feed reader does but keeps
  non-ASCII text (`->uri` encodes it); `->port` keeps a default port the
  text drops.
- `Session`: relative URLs resolve against the session's, its headers and
  data go under the request's (GET data after the request's own query),
  `useragent` and other names set on the session become options.
- The deprecated `Requests_*` names load as aliases of the namespaced
  classes, with the reference's one `E_USER_DEPRECATED` notice the first
  time one is used. `Requests::get_certificate_path()` is
  `wp-includes/certificates/ca-bundle.crt`; the engine verifies against the
  system's certificates when that file is absent.
- `wp_remote_*` (the engine's own `WP_Http`) now sends the same
  `Accept-Encoding` and `Connection: close`, fires `http_api_curl` with the
  curl handle, the parsed arguments and the URL, and adds no Content-Type
  of its own to a form body (it had added `; charset=UTF-8`). Not done: the
  `requests-{$hook}` actions `wp_remote_*` fires on the reference (it goes
  through Requests there; the engine's `WP_Http` does not), the
  `_redirection` key in the arguments `http_api_curl` sees, and
  `curl_multi` (`request_multiple` sends one after another; the answers
  come back in input order, the reference's in completion order).

## The kses tag pass (2026-10-05)

How `wp_kses` reads markup, captured input by input (224 `wp_kses_post`
cases, `wp_kses_split`, `wp_pre_kses_less_than`,
`wp_pre_kses_block_attributes`, `serialize_block_attributes`); fixture
`contracts/fixtures/api/kses-split.json` (probe
`tests/tools/kses-split-probe.php`). `Support\Kses::sanitize()` is the
whole default pass and is what the REST write paths call, so a save through
`wp/v2` and `wp_kses_post()` give the same bytes.

- Order: control characters out, references normalized in one pass (a
  known name or an accepted code point stays, decimal padded to three
  digits, `&#X24;` written `&#x24;`, anything else `&amp;`), then the
  `pre_kses` filters (`wp_pre_kses_less_than`, then
  `wp_pre_kses_block_attributes`), then the tag pass.
- `wp_pre_kses_less_than`: a `<` that reaches the next `<` or the end
  without a `>` is run through `esc_html` (quotes become `&quot;` and
  `&#039;`): `<p title='x'` gives `&lt;p title=&#039;x&#039;`.
- Block delimiters are parsed, each attribute key and string value goes
  through the same `wp_kses` call, and the blocks are written back:
  `core/` dropped, spacing canonical, an unclosed block closed, `--->` read
  as `-->`, JSON with `\u003c`, `\u003e`, `\u0026`, `\u0022`, `\u005c`
  and `\u002d\u002d`, `{}` written `[]`, `1.0` written `1`. Because the
  less-than pass runs first, a raw `<` inside a delimiter's JSON breaks the
  delimiter into escaped text instead. URL-looking values are text here
  (`javascript:` in a block attribute stays).
- The tag pass splits on `<` to the next `>`. A comment keeps its body as
  text with every `--` collapsed to `-` (`<!-- a -- b -->` gives
  `<!-- a - b -->`; `<!---->` disappears; `<!-->` gives `<!--&gt;-->`).
  `</` before a non-letter (`</ b>`, `</1>`) and `<!` before a lower-case
  letter (`<!x>`, `<!doctype html>`) are inert bogus comments and stay as
  written; `</>`, `<!X>`, `<!DOCTYPE html>`, `<! x>`, `<![CDATA[x]]>`,
  `<?php ?>` and any run whose name is not allowed (`<6>`, `< 6 & 7 >`,
  `<x->`) go entirely. Whitespace may follow `<` and the `/` of a closing
  tag (`< /b >` gives `</b>`).
- A tag keeps the case it was written in (`<B CLASS="y">` gives
  `<B class="y">`); attribute names are lowercased. A closing tag never
  keeps attributes.
- Attributes: a name runs to whitespace, `=`, a quote or `/` (so
  `@class="x"` is the name `@class`, never `class`); `=` may have spaces
  around it; a bare value runs to whitespace and may hold quotes
  (`class=x"` gives `class="x&quot;"`); an empty value is kept
  (`title=` gives `title=""`); junk between attributes (stray quotes, `=`,
  `/`) is skipped, and no space is needed after a quoted value; the first
  of two same-named attributes wins. A quoted value that never closes (the
  run ended at a `>` inside it) costs the tag every attribute:
  `<p class="x" title="y>z">` gives `<p>` and the text `z"&gt;`. A
  trailing `/` (`<br/ >`, `<p class="x"// >`) writes ` />`.
- `data-` names need a letter, digit, `_` or `-` after the dash
  (`data-a.b`, `data-a:b` and `data-` go); `aria-` names are only the nine
  the table lists.
- `wp_kses_split` on its own (no less-than pass) reads a last `<` with no
  `>` as a tag: `x <p title="q"` gives `x <p title="q">`.

## Translations (2026-10-05)

Text domains load from `.l10n.php` and `.mo` files; `__()`, `_x()`,
`_n()` and `_nx()` look them up before their filters run. `Minn\I18n`
reads both formats (`MoFile`, `PhpFile`, both byte orders of `.mo`),
evaluates Plural-Forms with its own parser (`PluralExpression`; nothing is
handed to PHP to run), and keeps the request's domains (`TextDomains`,
`Runtime::textDomains()`). The gettext classes plugins extend (`MO`,
`Translations`, `Gettext_Translations`, `Translation_Entry`,
`Plural_Forms`, `WP_Translation_Controller`, `WP_Translations`,
`WP_Translation_File`) live in `wp-api/classes/POMO.php` and its
neighbours over those classes. Fixture `contracts/fixtures/api/l10n.json`
(probe `tests/tools/l10n-probe.php`, language files in
`tests/fixtures/languages/`); `tests/unit/i18n.php` pins the parser.

- **`load_textdomain($domain, $mofile, $locale)`** runs, in order:
  `pre_load_textdomain` (null, domain, mofile, locale; anything else is
  the answer), `override_load_textdomain` (false, …; true stops with
  true), the `load_textdomain` action (domain, mofile),
  `load_textdomain_mofile` (mofile, domain), `translation_file_format`
  ('php', domain), then `load_translation_file` (file, domain, locale,
  the locale defaulting to `determine_locale()`) for each candidate. With
  the php format the candidates are `x.l10n.php` then `x.mo`, and the
  first that reads wins: when the `.l10n.php` is there the `.mo` is never
  read. With `mo`, only the `.mo`. A file that is not there returns false
  after both candidates were filtered.
- **Lookups.** Keys are `context` + `\x04` + original (no context: the
  original alone; an empty context is no context). An empty translation
  counts as none. Several files in one domain answer in load order, the
  first with the message winning, each file under its own plural rule.
  `_n()` with no translation follows English (`$number === 1`).
- **Plural-Forms.** Without the header a file is English (two forms,
  `n != 1`). An expression that does not parse sends every number to the
  first form (checked with `plural=n > ;`).
- **`unload_textdomain($domain, $reloadable)`** fires
  `override_unload_textdomain` (false, domain, reloadable) and the
  `unload_textdomain` action every time, and returns whether files were
  loaded. Unloaded without `$reloadable`, a domain does not come back on
  its own.
- **`get_translations_for_domain`** is a `WP_Translations` for a loaded
  domain (its `translate` and `translate_plural` fall back to the
  original) and one shared `NOOP_Translations` otherwise.
- **`load_plugin_textdomain($domain, false, $rel)`** reads
  `WP_PLUGIN_DIR/$rel/{domain}-{locale}` (`.l10n.php` or `.mo`) when it
  is there; when it is not, it names the folder and returns true without
  loading. It fires no `plugin_locale` filter. `load_theme_textdomain`
  and `load_muplugin_textdomain` work the same way, and a folder named in
  code holds `{domain}-{locale}` files even inside the themes root; only
  the active theme's own domain (its `Text Domain` header, or the slug)
  reads its `Domain Path` (or `/languages`) by locale alone (`pl_PL.mo`).
- **Just in time** (suite `tests/l10n.test.php`, which stages files
  before either stack starts, because the reference reads the languages
  folder once per request). A domain used before anyone loaded it is
  looked for once, in any locale: its named folder when the file is
  there, then `WP_LANG_DIR/plugins/`, then `WP_LANG_DIR/themes/`, then the
  named folder's file even though it is missing (the load is attempted
  and fails). A domain looked for and not found stays known and empty, so
  the next lookup does not search again; that includes a lookup in
  `en_US`, which keeps a later switch to another locale from finding it
  just in time.
- **Unloading for good** closes a domain only when it really had files:
  it does not come back on its own, and a locale switch unloads it
  without reloading it. Unloading a domain that was merely looked for
  forgets it, so the next lookup searches again.
- **The core domain** loads between `setup_theme` and `after_setup_theme`
  from `WP_LANG_DIR/{locale}.mo` (`load_default_textdomain` unloads
  `default` reloadably first, then loads).
- **Locale switching.** `switch_to_locale` is false for the locale in
  force and for one with no core file in `WP_LANG_DIR` (`en_US` always
  qualifies); otherwise it pushes the locale, reloads the core domain,
  then every known domain in the order it first appeared (unloaded
  reloadably, loaded again from the resolution above, or left known and
  empty), fires `change_locale`, and returns true. `get_locale()` and
  `determine_locale()` answer the switched locale. `restore_previous_locale`
  returns the locale it went back to (false when nothing was switched);
  `restore_current_locale` goes back to the start and returns that locale.
- **The MO class.** `import_from_file` fills `headers` (names as written)
  and `entries` keyed like the lookups; a plural entry is keyed by its
  singular and carries `plural`; `add_entry` takes an entry or an array;
  `merge_with` lets the other's entries replace these;
  `select_plural_form` and `get_plural_forms_count` follow the file's
  Plural-Forms. `NOOP_Translations` is English throughout.

- **Script translations** (fixture `contracts/fixtures/api/script-l10n.json`).
  `load_script_textdomain($handle, $domain, $path)` is false for an
  unregistered handle (no filters run). With a folder it tries
  `{path}/{prefix}-{handle}.json` first; then it places the script by its
  URL (made absolute from the site's origin when it starts with `/`, the
  query dropped): inside a plugin or theme, its path there and the
  `plugins` or `themes` languages subfolder; elsewhere on the site, its path
  from the site root and the languages folder itself; off the site, false.
  `load_script_textdomain_relative_path` (path, src, false) may change or
  refuse it. The md5 of that path names the file, `.min.js` read as `.js`
  after the filter: `{path}/{prefix}-{md5}.json`, then
  `{lang}[/plugins|/themes]/{prefix}-{md5}.json`; the prefix is
  `{domain}-{locale}`, or the locale alone for `default`. Nothing found,
  or the path refused, ends in one more `load_script_translations(false,
  …)`. Each candidate runs `pre_load_script_translations` (null, file,
  handle, domain), `load_script_translation_file`, then
  `load_script_translations` (the file's contents, as written).
- `wp_set_script_translations` is false for an unregistered handle;
  otherwise it records the domain and folder on the script and adds
  `wp-i18n` to its dependencies. The script prints its blocks in the order
  `-js-extra`, `-js-translations`, `-js-before`, the script, `-js-after`;
  the translations block hands the file to `wp.i18n.setLocaleData`, and
  is left out when no file is found.
- `wp_print_scripts($handles)` prints the named handles and what they
  depend on at once, queued or not, and fires `wp_print_scripts` either way
  (it used to print only the head queue whatever it was handed).
- The engine's own `wp.i18n` (`assets/wp/i18n.js`) evaluates Plural-Forms
  with the same parser as the server, ported to JavaScript; it no longer
  builds a function from the translation file's text.

Not yet: `switch_to_user_locale`, the admin and network core files, and
the engine's own front-end strings, which are written in English and do
not pass through `__()`.

## WP-CLI on the runtime (2026-10-05)

A WP-CLI command the engine does not answer itself no longer stops at
"This command needs WordPress itself": the engine's WordPress runtime
stands in (`contracts/layout.md`). What that took, each found by running
real commands on both stacks:

- **Hooks added before the runtime existed.** WP-CLI's `add_wp_hook`
  writes `$wp_filter[$tag][$priority][$id] = ['function', 'accepted_args']`
  when `add_filter` does not exist yet (that is how `--user`,
  `--skip-plugins` and `--skip-themes` work). `_minn_bind_hook_globals()`
  now adopts those plain-array entries into the registry instead of
  resetting `$wp_filter` over them.
- **The plugin and theme lists go through `get_option`.** WP-CLI skips
  plugins by filtering `pre_option_active_plugins` / `option_active_plugins`
  (removed again at `plugins_loaded`) and themes by filtering
  `option_template` / `option_stylesheet` from `setup_theme`; a plugin that
  switches others off per request does the same. `Plugins::load` read the
  raw rows before.
- **`$_wp_using_ext_object_cache`** exists from the start (null) and
  `wp_using_ext_object_cache($using)` stores into it and returns the
  previous value; WP-CLI reads the global after loading.
- **Dynamic properties.** The 96 classes the reference marks
  `#[AllowDynamicProperties]` (read by reflection into
  `data/dynamic-properties.json`) are marked here too, placeholders
  included; WP-CLI's own `post list` sets `$post->url`. The api suite fails
  if one is missing.
- **REST, for WooCommerce's CLI** (fixture `contracts/fixtures/api/rest-options.json`).
  `rest_handle_options_request` answers an OPTIONS request to a plugin's
  route with the route's help-context description (namespace, methods,
  endpoints and their arguments, the schema when the route declares one,
  the self link for a route without parameters) and one no route matches
  with `[]` (200). It is added by `rest_api_default_filters` on
  `rest_api_init`, after the plugins' own `rest_pre_dispatch` callbacks:
  ACF's returns nothing at the same priority and would swallow the answer
  if it ran later. A HEAD request is served by the route's GET handler
  (`wp wc … --format=count` sends HEAD). WooCommerce passes its REST field
  list as `WP_Query`'s `fields`; anything but a string reads as `all`.
- **Not yet:** OPTIONS on the engine's own `wp/v2` routes (in-process and
  over HTTP both 404 where the reference describes the route; the B1
  "Route::output" item), and Jetpack's `status`, which needs the XML-RPC
  client (Mute).

## Pluggable functions (2026-10-05)

The 39 functions the reference defines in `pluggable.php` (the list is
`data/pluggable.json`, from the inventory) live in `wp-api/pluggable.php`,
each behind `function_exists`, and that file loads after the active
plugins and before `plugins_loaded` (`Runtime::loadPluggables()`, called by
the plugin loader; a boot that loads no plugins calls it at once). So a
plugin that defines its own `wp_mail`, `wp_hash_password`,
`wp_generate_password` or `wp_new_user_notification` replaces the engine's,
as SMTP, security and membership plugins rely on. The rest of what used to
sit in that file (`wp_die` and its handlers, `is_wp_error`, the nonce field
and referer helpers, `sanitize_user`, the login form tags) moved to the
files their families belong in, since plugins call those while loading.

- The symbol gate counts the deferred names as provided (they do not exist
  yet when it runs) and never as a collision; the exported gap carries
  them as `pluggable`.
- A plugin must guard its override with `function_exists`, here as on the
  reference: activation includes the plugin after the pluggables exist,
  and the reference refuses an unguarded `wp_mail` with "Cannot redeclare".
- Not yet: `wp_notify_postauthor` and `_wp_sanitize_utf8_in_redirect` are
  still missing. The engine's own mail (password reset, moderation) is
  sent by `Minn\Mail`, not through `wp_mail`, so a plugin's replacement
  does not see it.

## Drop-ins (2026-10-05)

Suite `tests/dropins.test.php` stages the files in `tests/fixtures/dropins`
into both stacks and compares (the reference's `wp-content` is its own).

- **`object-cache.php`** loads before the facade (`Runtime::boot`), from a
  function, as the reference loads it; `wp_using_ext_object_cache()` is then
  true. Its functions and its `WP_Object_Cache` win; every facade cache
  function is defined only where the drop-in left a gap, and the newer ones
  (`*_multiple`, `incr`/`decr`) work through the drop-in's single-key
  functions, while `wp_cache_supports`, `wp_cache_flush_runtime` and
  `wp_cache_flush_group` answer false for a cache that brought none of
  its own. Then `wp_cache_init()` runs and the reference's groups are
  declared: 22 global (`blog-details` … `userslugs`) and the request-only
  `counts`, `plugins`, `theme_json`, `themes`. Without a drop-in the
  engine's own `WP_Object_Cache` (a view over `Runtime\ObjectCache`, with
  `cache_hits`/`cache_misses`) is `$wp_object_cache`.
- **`db.php`** is required with `$wpdb` global, so a drop-in that assigns
  `$wpdb = new Its_DB(...)` (Query Monitor's subclass) provides the
  database object; the facade's `wpdb` is made only when it did not.
- **`advanced-cache.php`** loads from `bootstrap.php`, at global scope, when
  `WP_CACHE` is true and `enable_loading_advanced_cache_dropin` (read from
  the hooks added before the runtime, `Runtime\EarlyFilters`) allows it:
  after the hook API, before the database and the object cache, as on the
  reference. A page cache's top-level variables are globals there, as WP
  Super Cache needs. WP-CLI switches the file off through that filter.
- **Maintenance mode**: a `.maintenance` in the webroot whose `$upgrading`
  is under 600 seconds old (and `enable_maintenance_mode`) answers every
  request first: the site's `maintenance.php` when there is one (the
  reference serves it with whatever status it sets, 200 by default), else
  503 with `Retry-After: 600`, the title "Maintenance" and "Briefly
  unavailable for scheduled maintenance. Check back in a minute." on the
  engine's own error page. An older file is ignored. The engine used to
  ignore `.maintenance` entirely, so `wp maintenance-mode activate` did not
  close the site.
- **Not yet**: `db-error.php` (the engine's own database-down page shows
  instead), `php-error.php` and `fatal-error-handler.php` (the engine's own
  failure page), `sunrise.php` (multisite).

## Sending mail (2026-10-05)

Plugins drive PHPMailer directly (SMTP plugins configure it in
`phpmailer_init`, Gravity SMTP and WP Mail SMTP read `ErrorInfo`, call
`preSend()` and `getSentMIMEMessage()`, swap the static `$validator`), so the
engine has the whole of it: `PHPMailer` (99 public methods, 7.1.1's
properties and constants), `SMTP` (32) and `Exception` in
`wp-api/classes/PHPMailer.php`, `WP_PHPMailer` beside it, and `wp_mail()`
rewritten to the reference's contract (`pluggable.php`, its parts in
`wp-api/mail.php`). The work is in `Minn\Mail`: `Composer` writes the
message from a `Draft`, `HeaderWords` encodes and decodes header text,
`TextWrap` and `Transfer` wrap and encode bodies, `AddressRules` checks,
parses and Punycodes addresses, `SmtpSession` speaks SMTP, `DataLines`
cuts DATA, `Dkim` signs, `HtmlMessage` reads HTML messages, `HostEntry`
reads the Host setting. Suite `tests/mail.test.php` diffs
`tests/tools/phpmailer-probe.php` (71 rows, nothing sent) and
`tests/tools/phpmailer-smtp-probe.php` (33 rows, against
`tests/fixtures/mail/fake-smtp.php`, one server per stack, the recorded
sessions compared line by line) on both stacks, then routes `wp_mail()`
through the `minn_mail` SMTP setting; `tests/unit/mail.php` pins the parts,
DKIM byte for byte against `tests/fixtures/mail/dkim-reference.json`.

- **The message.** Header order: Date, To (not for `mail()`, which takes
  To and Subject as arguments and the sent copy appends them after the MIME
  header), From, Cc, Bcc (only for mail, sendmail and qmail), Reply-To,
  Subject, Message-ID, X-Priority, X-Mailer, Disposition-Notification-To,
  custom headers, MIME-Version, Content-Type, Content-Transfer-Encoding.
  Line endings are CRLF for smtp and mail, PHP_EOL otherwise, and the
  choice is static (it sticks to the class until the next `preSend()`).
  No To and no Cc writes `To: undisclosed-recipients:;`; only Cc writes no
  To; `SingleTo` writes none. A From without a name is the bare address;
  `X-Mailer` is `PHPMailer 7.1.1 (https://github.com/PHPMailer/PHPMailer)`
  unless set, nothing when set to blanks; `X-Priority` is written for any
  non-null value, 0 included.
- **Dates and ids.** `MessageDate` is kept, reformatted as
  `D, j M Y H:i:s O` with its own offset, only when it reads as a moment
  already past; anything else (future, `now`, unparseable) becomes the
  current time. `MessageID` is kept when it is `<local@domain>` with no
  space, `<` or second `@`; otherwise `<{unique id}@{host}>`, the host being
  `Hostname`, `SERVER_NAME`, `gethostname()` or `localhost.localdomain`.
- **Encodings.** A body declared 8bit without 8-bit bytes goes out 7bit
  with `us-ascii` part charsets (a single-part message keeps its charset
  and drops the transfer-encoding header); a line over 998 characters
  forces quoted-printable unless the encoding is base64; a multipart top
  level says `Content-Transfer-Encoding: 8bit`. Header text: quoted when a
  phrase needs it, encoded words in B (more than a third needs encoding;
  multibyte text cut at whole characters) or Q, folded to 63 characters
  for `mail()`, 998 otherwise.
- **Multipart.** `alt`, `inline`, `attach` and their combinations nest
  `multipart/mixed` > `alternative` > `related` with boundaries
  `b1=_{id}` to `b3=_{id}`; the alternative's HTML part is `text/html`
  whatever `ContentType` says. A calendar part (`text/calendar;
  method=…`, the calendar's own METHOD upper-cased when known, else
  REQUEST) rides only in `alt` and `alt_attach`. Attachments name the file
  `name=` and `filename=` (quoted when they hold a special, encoded words
  when 8-bit); an identical attachment and a repeated inline content id
  are written once; a file gone by send time fails as `File Error: Could
  not open file: {path}`.
- **`msgHTML()`** embeds files a `src` or `background` names relative to
  the base directory (no scheme, no leading slash, no `../`, no query; any
  type, an image or not; nothing at all without a base directory, so the
  working directory is never read; a leading `./` dropped) and raster
  `data:` URIs (not SVG), each under `{first 32 hex of SHA-256 of the URL,
  or of the data}@phpmailer.0`, data ones named `embed{n}`; the text
  version drops head, title, style and script, strips tags and decodes
  entities into the charset, or is `This is an HTML-only message. To view
  it, activate HTML in your email application.` when nothing is left.
- **Addresses.** Duplicates (case-insensitively) are refused without an
  error; an IDN domain is queued and Punycoded at `preSend()` (with the
  charset as the source encoding); `setFrom()` sets `Sender` only when it
  is empty and `$auto`. Errors read `Invalid address:  ({kind}): {address}`
  (two spaces: the message ends in one).
- **SMTP.** Debug levels: 1 client lines (credentials as `[credentials
  hidden]`), 2 replies, 3 connection events, 4 raw inbound lines; `echo`
  prints a timestamp and a tab with continuation lines indented, `html`
  escapes and drops line breaks (the SMTP class adds a timestamp, PHPMailer
  does not). Command failures are `{COMMAND} command failed` with the
  reply's detail, code and enhanced code; a refused connection is `Failed
  to connect to server` with the errno and its text. AUTH picks the named
  mechanism when the server offers it, else CRAM-MD5, LOGIN, PLAIN in that
  order; DATA cuts a line over 998 at its last space (or at 997), tabs
  header continuations, then doubles leading dots. The Host setting is a
  `;` list of `[ssl://|tls://]host[:port]`; STARTTLS runs when asked or
  offered (`SMTPAutoTLS`). PHPMailer sends RCPT for To, Cc and Bcc, DATA
  when at least one was accepted, RSET between keep-alive messages, QUIT
  otherwise, then reports `SMTP Error: The following recipients failed:
  {address}: {detail}…`; a failed connection in non-throwing mode is `SMTP
  connect() failed. https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting`
  plus the SMTP error's parts.
- **DKIM** (rsa-sha256, relaxed/simple): the standard headers in message
  order plus `DKIM_extraHeaders` that are custom headers, `i=` before
  `z=`, the signature in 73-character pieces after the MIME header.
- **`wp_mail()`**: `wp_mail` filter, `pre_wp_mail` (non-null returns at
  once), the global `$phpmailer` (a throwing `WP_PHPMailer`, validator
  `is_email`) cleared, `wp_mail_content_type` asked once before the sender
  and once with any boundary appended, `wp_mail_from` (default
  `wordpress@{home host without www.}`), `wp_mail_from_name` (`WordPress`),
  `setFrom` (a refusal fires `wp_mail_failed` without the embeds), To, Cc,
  Bcc and Reply-To split on every comma (quoted names keep their quotes;
  a refused entry is skipped), `isMail()`, the charset (blog charset
  unless the headers set one; a boundary leaves it empty), custom headers
  but MIME-Version and X-Mailer (Subject and To in the headers argument are
  just custom headers), attachments (a string key names the file, a missing
  file is skipped) and embeds (the key is the content id), then
  `phpmailer_init` outside the try (a hook that throws escapes), send, and
  `wp_mail_succeeded` or `wp_mail_failed` with the arguments and
  `phpmailer_exception_code`.
- **Not verified against the reference**: sendmail and qmail delivery
  (the tests send nothing through a local MTA), `SingleTo` outside SMTP,
  S/MIME `sign()`, XOAUTH2 (an `OAuthTokenProvider`'s `getOauth64()` is
  used when one is set), a STARTTLS that succeeds (the fake server has no
  TLS), `needsSMTPUTF8()`, and PHPMailer's language files (`setLanguage()`
  answers false for anything but `en`, as WordPress ships none;
  `WP_PHPMailer` passes its messages through `__()`). The `POP3`, `OAuth`
  and `DSNConfigurator` classes are not provided; the reference does not
  load them either.

## The HTML processor (2026-10-05)

`WP_HTML_Processor` was a placeholder; core blocks and plugins (Interactivity
API directives, block supports, Jetpack, Site Kit) walk markup with it to
know where a tag sits. The engine now builds the tree the reference builds:
`Minn\Html\Tree\Builder` runs the WHATWG tree construction algorithm over the
engine's tokenizer (insertion modes in `HeadRules`, `BodyRules`,
`TableRules`, foreign content in `ForeignRules`), and every node opened or
closed becomes an `Event` with its breadcrumbs. The facade
(`wp-api/classes/WP_HTML_Processor.php`) reports those events as tokens;
`WP_HTML_Token.php`, `WP_HTML_Decoder.php` and `WP_Token_Map.php` hold the
helper classes. Suite `tests/html-api.test.php` diffs
`tests/tools/html-processor-probe.php` on both stacks (31 rows: about 350
fragments and documents walked token by token, normalize, queries,
bookmarks, edits, the helper classes, the tag processor underneath);
`tests/unit/html-tree.php` pins the parts. Outside the suite the walk was
checked against 1,306 real posts from two other local sites, all equal.

- **Walking.** A fragment (`create_fragment`, BODY context only: any
  other context, or an encoding other than exactly `UTF-8`, returns null)
  starts under `HTML>BODY`; a document (`create_full_parser`, or the
  constructor, which `_doing_it_wrong`s without the unlock code) reports
  `HTML`, `HEAD` and `BODY` too, the doctype as a top-level `html` token.
  Implied elements are virtual tokens (no attributes, no edits, no
  bookmarks). Text arrives one kind at a time (a run of NUL bytes, dropped
  in HTML; a run of white space; the rest), so `" a"` is two text tokens;
  the line break after `<pre>`/`<listing>` is dropped from the first. Void
  elements, elements read whole (SCRIPT, STYLE, TEXTAREA, TITLE, XMP,
  IFRAME, NOEMBED, NOFRAMES) and self-closed foreign elements have no
  closer. At the end of the input every open element closes and nothing
  implied is added (a lone doctype stays alone).
- **Where the reference stops** (`get_last_error()` is `unsupported`, the
  exception names the token, its offset and text, the stack and the
  formatting list): foster parenting (non-white-space text or most elements
  directly in a table), the adoption agency when a special element sits
  inside the formatting element ("Cannot extract common ancestor") or the
  closer names no active one ("any other end tag"), reopening a formatting
  element, PLAINTEXT, a comment after `</body>` or `</html>`, text in a
  frameset. `normalize()` and `serialize()` return null there.
- **Current spec, not the old one.** `select` is handled in body (no "in
  select" mode): DIV, B and HR may sit inside it, a second SELECT or an
  INPUT closes it, TEXTAREA and KEYGEN do not. Scripting is off, so
  NOSCRIPT is ordinary markup. Quirks mode (missing or legacy doctype in a
  document) lets a TABLE open inside a P.
- **Queries.** `next_tag()` takes `breadcrumbs` (`*` matches one level,
  names compared case-insensitively); `match_offset` counts only among
  breadcrumb matches. Bookmarks mark real tokens, closers included; a seek
  re-walks from the start, and a failed seek leaves the processor at its
  end.
- **Serializing.** Tag names lower-cased (SVG's `foreignObject`,
  `viewBox` and the rest restored), attributes double-quoted with the first
  of a repeated name kept, `& < > " '` escaped in text and values, RCDATA
  escaped, raw text left alone, a line break after the PRE, LISTING and
  TEXTAREA openers, ` />` on self-closed foreign elements, comments from
  their whole text, funky comments and a body's doctype dropped.
- **The tag processor**, found through this work and now as the reference:
  `</>` is a presumptuous tag token; `<?target …?>` is a processing
  instruction when the target is alphanumeric and not `xml` (it may end at
  `>`), a PI lookalike comment for another XML name, an invalid comment
  otherwise; text keeps a `<` that opens nothing (`a < b` is one token) and
  a `<` ending the input pauses; one leading line break is dropped from a
  TEXTAREA and from the text after PRE or LISTING; only the HTML standard's
  106 legacy names decode without a semicolon (`&level` stays, it was `≤vel`);
  `set_modifiable_text()` escapes all five characters in text and only the
  element's own closer in TITLE and TEXTAREA (which it had refused);
  `change_parsing_namespace()` takes effect (CDATA sections and no raw text
  outside HTML); `WP_HTML_Doctype_Info` reports
  `indicated_compatibility_mode`.
- **Not the same yet**: `_doing_it_wrong()` fires its action but raises no
  notice on the engine (the reference raises an E_USER_NOTICE when
  WP_DEBUG is on), so the direct constructor is silent; the tag processor's
  protected properties (`$html`, `$parser_state`, ...) are not there for a
  subclass to read; `WP_HTML_Processor` declares its own
  `set_modifiable_text()` (implied tokens refuse edits); the reference's
  `MAX_SEEK_OPS` limit is not enforced.

## What the round trip turned up in the runtime (2026-10-05)

`tests/round-trip.test.php` (`contracts/round-trip.md`) runs one day of
work on a copy of a real site, once on WordPress and once on the engine,
and compares what each left in the database. With the site's own plugins
loaded, it found these, each now matched to the oracle:

- **An action fired with nothing hands its callbacks one empty string.**
  `do_action('wp_enqueue_scripts')` calls a callback registered for one
  argument with `''`; `do_action_ref_array($hook, [])` calls it with none.
  CleanTalk's `ct_enqueue_scripts_public($_hook)` requires its parameter,
  so the engine's zero-argument call was an ArgumentCountError and a 500 on
  every page. `Hooks::action()` now supplies the `''` (engine-fired
  lifecycle actions included); `Hooks::actionRef()` is the array form.
- **PHP's array wrappers read back as themselves.** `ArrayObject`,
  `ArrayIterator` and `RecursiveArrayIterator` serialize their state as
  numbered parts (`O:11:"ArrayObject":4:{i:0;flags;i:1;storage;i:2;members;
  i:3;iterator class}`). The reader refused integer property keys, so
  CleanTalk's `cleantalk_data` (which nests two of them) came back as the
  raw string; the plugin took its settings for missing, rebuilt defaults,
  and saved over the account name and every counter. The reader now takes
  integer keys, and `StoredObjects::reviver()` rebuilds the three wrappers
  through their constructors (nothing else runs); written back with PHP's
  own serializer they are the same bytes. A wrapper carrying member
  properties, or wrapping an object, stays a record of its parts. Unit
  rows in `tests/unit/stored-objects.php`.
- **A plugin's own objects keep their class when written back.** A record
  of a class no reviver knows (Freemius keeps `FS_Plugin`, `FS_Plugin_Plan`,
  `FS_Site`, `FS_User` and `FS_Plugin_License` in its accounts option;
  Gravity Forms, Elementor, Google Analytics and SiteGround's migrator
  store their own) still comes back a stdClass of its properties, but
  `Serialized` keeps its class name and stored property names (visibility
  markers, numbered parts, a private name a parent class shares) beside it,
  and `encode()` writes it back under them: unchanged it is the same bytes,
  changed it is still its class. A stdClass and PHP's array wrappers are
  encoded the same way so a record inside them keeps its class too. Before
  this, a plugin that read such an option on the engine and saved it back
  turned every object in it into `stdClass` for good. Probe rows `a plugin
  object saved back keeps its class` and `an object of no known class is
  written back as it was read` match the reference byte for byte. Still
  open: the reference instantiates a class the plugin has loaded, so plugin
  code calling its methods works there and fails on the engine.
- **An option holding an object is decoded afresh on every read.** The
  option cache handed back the same decoded object each time; a plugin that
  changed it and called `update_option` had the engine compare the object
  with itself, find no change, and skip the write. The reference caches the
  stored text and decodes on each read, so the change is saved; the engine
  now does the same for any value with an object in it (scalars and plain
  arrays stay cached as decoded values). Post, user, term and comment meta
  already decoded on every read.
- **`wp_blacklist_check`** (deprecated in 5.5) is the one symbol that kept
  CleanTalk from loading: `_deprecated_function(…, '5.5.0',
  'wp_check_comment_disallowed_list()')`, then that check's answer.
- **A sign-in tells plugins.** See "admin-ajax.php and the sign-in
  chain" below: the engine's sign-in runs the `authenticate` chain, and
  `wp_login` / `wp_logout` follow as before. CleanTalk records the
  sign-in address in `_cleantalk_ip_keeper_data` on `wp_login`. Still not
  fired: the cookie actions (`set_auth_cookie`, `set_logged_in_cookie`,
  `attach_session_information`).
- **`/wp-admin/admin-ajax.php` declares `DOING_AJAX`** before anything
  loads, as the reference's own file does; CleanTalk counted the Minn Admin
  nonce refresh as a page view without it. The endpoint itself is now the
  reference's (below).
- **Uploads are cut into every size the site has.** `Media\Images::ladder()`
  is the four sizes from the options, `1536x1536` and `2048x2048` (every
  site has them; the round trip's 1600-wide photo was missing its 1536),
  then any `add_image_size` registrations, in the metadata order the
  reference writes. `intermediate_image_sizes_advanced` filters the list
  when plugins are loaded: Gravity Forms registers three image-choice sizes
  and takes them back out there for every upload but its own. The engine
  cuts the sizes before the attachment row exists, so the filter's
  attachment id is 0 where the reference passes the new id.
- **Stored sessions are written back as they were read.** `Sessions` keeps
  each entry's serialized text and re-emits it untouched when another
  session is added or removed, so data a plugin attached through
  `attach_session_information` survives an engine sign-in. A new session
  from a request without a User-Agent has no `ua` key, as on the
  reference.
- **A post save fires the reference's hooks with the reference's
  arguments, in its order.** `wp_after_insert_post` takes four (`$post_id`,
  `$post`, `$update`, `$post_before`); the facade passed three in the
  wrong order, so Seriously Simple Podcasting's callback threw and every
  WooCommerce order made on shop-dogfood was a 500. An update now fires
  `edit_post_{type}`, `edit_post` and `post_updated` before `save_post`,
  and the defaults the reference registers are registered here: the
  revision of an update is saved from `wp_after_insert_post` at priority 9
  (`wp_save_post_revision_on_insert`), `wp_save_post_revision` stays on
  `post_updated` at 10 as the switch a plugin unhooks to turn revisions
  off, and `wp_check_for_changed_slugs` / `wp_check_for_changed_dates` at
  12 keep `_wp_old_slug` and `_wp_old_date`. Probe row
  `wp_after_insert_post arguments`.
- **A plugin's own meta types work.** WooCommerce keeps order item meta in
  `woocommerce_order_itemmeta`, named on `$wpdb` as `order_itemmeta`; the
  metadata API knew only post, user, term and comment meta, so every
  order item lost its product, quantity and totals. Any type whose table
  is set on `$wpdb` as `{$type}meta` is now read and written with
  `{$type}_id` and `meta_id` (`Runtime\MetaTypes`).
- **A multi-type schema takes the first listed type the value fits.**
  WooCommerce declares a line item's `product_id` as `mixed`, which the
  REST server spells out as every type, null first and array last. The
  engine tried array before anything else, so `92048` became `["92048"]`,
  WooCommerce cast it to product 1, and every order line lost its
  product. The schema's own order now decides, and an empty string is a
  string whenever that is allowed. Probe row `a multi-type schema takes
  the first listed type the value fits` (150 combinations matched).
- **`get_default_comment_status()`**: a page starts closed; another type
  follows the site default when it supports comments (trackbacks, for
  pings); then the `get_default_comment_status` filter.
- **An object that holds itself** is encoded by PHP's own serializer
  (back-references and all) instead of recursing until the worker dies.

## admin-ajax.php and the sign-in chain (2026-10-05)

Two things nearly every site in the Anchor fleet leans on, found open by
the round trip: plugins answer their own front-end requests on
`admin-ajax.php` (Gravity Forms submits forms and runs its background
feeds there, CleanTalk checks for spam, WooCommerce refreshes the cart,
Elementor Pro sends its forms), and plugins may refuse a sign-in
(Passwords Evolved on 808 fleet sites, Wordfence, Jetpack). Captured from
the reference with the fixture plugin `tests/fixtures/runtime/minn-test-ajax`
and the hook trace; suite `ajax` (69 checks, both stacks).

**The endpoint** (`Runtime\AjaxController`, route `Method::Any`):

- It is an admin request. The runtime boots with `isAdmin`, so `is_admin()`
  and `WP_ADMIN` are true while plugins load (a plugin may register its
  handlers only then), `is_blog_admin()` and `is_network_admin()` are
  false (no admin screen), `$pagenow` is `admin-ajax.php`, and
  `$_SERVER['SCRIPT_FILENAME' | 'SCRIPT_NAME' | 'PHP_SELF']` name
  `wp-admin/admin-ajax.php` (WooCommerce exempts the endpoint from its
  admin guard by `SCRIPT_FILENAME`; without it every signed-out request
  was sent to `/my-account/`). `$pagenow` is `index.php` on every other
  path except `/wp-login.php` and `/wp-cron.php`, where it stays unset
  (`_minn_script_globals`): with `wp-login.php` there, the CaptainCore
  helper takes a one-time link itself (to `/wp-admin/`, refusing with a
  500 page) and hide-login plugins start guarding the path, which wants a
  round trip on a hide-login site before it changes. A plugin that sets
  `$pagenow` first keeps it.
- Hooks fired: `plugins_loaded`, `init`, `wp_loaded`, `admin_init`, the
  handler. Never `parse_request`, `wp`, `template_redirect`,
  `send_headers`, `admin_menu`, `current_screen`.
- `OPTIONS` is answered first: 200 and empty with the two cross-origin
  headers when `Origin` is the site's own, 403 and empty otherwise.
- The action is `$_REQUEST['action']` (the posted field wins over the
  query's). Missing, empty, or an array: 400 `0`, with `Content-Type:
  text/html; charset=UTF-8`, `X-Robots-Tag: noindex` and the no-cache
  headers, before `admin_init`.
- Then the response's headers go out (`Response::sendHead()`), because a
  handler usually ends the request itself, then `admin_init` fires (its
  defaults send `Referrer-Policy` through `admin_referrer_policy` and
  `X-Frame-Options: SAMEORIGIN`), then the reference's own actions are
  registered at priority 1, only those for who is asking:
  `wp_ajax_nopriv_heartbeat` signed out, `wp_ajax_rest-nonce` signed in.
  A plugin cannot see them at `admin_init`.
- `wp_ajax_{action}` for a signed-in visitor, `wp_ajax_nopriv_{action}`
  otherwise (a signed-in visitor never reaches a nopriv handler). No
  handler: 400 `0`. A handler that returns is followed by `0` and a 200,
  whatever status it set; `wp_send_json*` keeps the status it was given
  (`wp_die(..., ['response' => null])`); `wp_die()` in a handler answers
  200 unless given a status, prints the message bare (a `WP_Error`'s
  message, an int as its digits), and nothing more; `check_ajax_referer`
  looks in the named field, then `_ajax_nonce`, then `_wpnonce`, and
  refuses 403 `-1`.
- Cross-origin headers (`Access-Control-Allow-Origin: <origin>` and
  `Access-Control-Allow-Credentials: true`) only for an `Origin` that is
  exactly the http or https form of the home or site host: a trailing
  slash, a port, or upper-case scheme is refused.
- The signed-out heartbeat: `data` (cast to an array) goes through
  `heartbeat_nopriv_received` only when it is not empty, then
  `heartbeat_nopriv_send` (where `wp_auth_check` adds `wp-auth-check`),
  `heartbeat_nopriv_tick`, and `server_time` last, as JSON. `screen_id`
  is `sanitize_key`'d, `front` when absent.
- The engine's own hardening sends `X-Content-Type-Options: nosniff` on
  every response; the reference starts sending it after the action check.
  Kept on purpose.
- Not done: the signed-in heartbeat (post locks, nonce refresh: wp-admin
  plumbing), the reference's other core actions (all wp-admin screens,
  Mute), `admin-post.php`, and a plugin replacing `wp_die_ajax_handler`
  for the closing `0` (the engine writes it itself).
- With `is_admin()` true, every plugin's admin code loads on this
  endpoint. Modula extends `WP_Posts_List_Table` behind a `class_exists`
  guard and a `require` of a file the site skeleton leaves empty (the
  gate's known blind spot), so the core list tables are now Mute
  placeholders (`WP_Posts_List_Table`, the comments, users, media,
  plugins, themes, links, application-password and privacy-request
  tables).
- On shop-dogfood the engine's answers to `gform_get_config`,
  `woocommerce_get_refreshed_fragments` and an unknown action are byte
  for byte the oracle's.

**The sign-in chain** (`Login\LoginHooks`): with plugins loaded, the
engine's sign-in runs `wp_authenticate` (the action, with the login and
password by reference) and then `wp_authenticate()`, as the reference's
sign-in does. What the reference's `wp_authenticate()` does, and the
facade now does:

- The username goes through `sanitize_user`, the password is trimmed.
- The chain: `wp_authenticate_username_password` and
  `wp_authenticate_email_password` at 20 (each runs
  `wp_authenticate_user`), `wp_authenticate_spam_check` at 99 (single
  site: passes the user through). The reference also has
  `wp_authenticate_application_password` at 20; it acts only on API
  requests, which the engine authenticates itself.
- A chain that returns `null` or `false` is `authentication_failed`
  (`<strong>Error:</strong> Invalid username, email address or incorrect
  password.`).
- `wp_login_failed($sanitized_login, WP_Error)` fires for every refusal
  whose FIRST code is not `empty_username` or `empty_password`.
- The unknown-email message has no `Error:` prefix in the reference.

On the page: a plugin's refusal is shown in its own words (through
`login_errors`, tags stripped, since the engine's form prints text). The
chain's own refusals (`empty_*`, `invalid_username`, `invalid_email`,
`incorrect_password`, `authentication_failed`) stay the engine's one
sentence, which does not say whether the username exists. A plugin that
returns a `WP_User` signs that user in, as on the reference.

## Writes tell plugins (2026-10-06)

Minn's own REST controllers (which Minn Admin uses) wrote rows and told
plugins nothing, so nothing reacted to an edit made on Minn: notification
plugins, Smush, newsletters, WooCommerce's stamps. They now fire the
reference's actions, in its order, with its arguments. Suite `hook-trace`
records every action each REST write fires on both stacks (fixture
mu-plugin `tests/fixtures/mu-plugins/minn-test-trace.php`, recording only
for a request naming a run the suite opened) and compares the sequences
from `rest_api_init` to `shutdown`, query bookkeeping aside. All twenty
writes (posts, pages, media, terms, users, comments, settings) agree
action for action; its shrink-only `DIVERGENT` list is empty. A quick tracer for any core
function on the reference: a `wp eval-file` that hangs a recorder on `all`
and keeps actions only (an action is counted before `all` runs, a filter
is not).

How it is built: the facade owns the lifecycle (`_minn_post_before_save`,
`_minn_post_saved`, `wp_after_insert_post`, `wp_trash_post`,
`wp_untrash_post`, `wp_delete_post`, the comment functions); the engine's
controllers call it through `Runtime\PostEvents` and
`Runtime\CommentEvents`, which write exactly as before when no runtime is
booted. Trash, untrash and force delete of posts, and every comment
change, go through the facade functions themselves. What the reference
does that the facade now does too:

- **A post save**: `pre_post_insert` (new) or `pre_post_update`; the
  categories set again through `wp_set_post_categories` (so
  `set_object_terms` fires even when nothing changes); `clean_post_cache`
  with the copy the cache held (an update reports the old status);
  `clean_page_cache` for a page; the transition actions; `edit_post_{type}`,
  `edit_post`, `post_updated` for an update; `save_post_{type}`,
  `save_post`, `wp_insert_post`; through REST `rest_insert_{type}`, the
  request's terms and fields, `rest_after_insert_{type}`; and last
  `wp_after_insert_post`, from which the revision is saved (its own
  `pre_post_insert`, transition `new` to `inherit`, save actions and
  `_wp_put_post_revision(revision id, post id)`). The old slug and date
  come from the `post_updated` defaults, so a site that unhooks them keeps
  none, as on the reference.
- **Trash**: `wp_trash_post`, the three trash metas through
  `add_post_meta` (with their actions), the save above,
  `trash_post_comments` (every comment becomes `post-trashed`, statuses kept
  in `_wp_trash_meta_comments_status`, `trashed_post_comments`), then
  `trashed_post`, then over REST `rest_delete_{type}`. `wp_trash_post`
  answers with the post as it was. Untrash mirrors it.
- **Delete**: `before_delete_post`, the trash metas, the term
  relationships, the revisions (each with `before_delete_post`,
  `delete_post_revision`, `delete_post`, `deleted_post_revision`,
  `deleted_post`, `after_delete_post`, `wp_delete_post_revision`), the
  post's comments (deleted for good, each with its own actions: the
  engine used to leave them behind), the remaining meta row by row
  (`delete_metadata_by_mid`), then `delete_post_{type}`, `delete_post`,
  `deleted_post_{type}`, `deleted_post`, `clean_post_cache`,
  `after_delete_post`.
- **Terms on a post**: `wp_set_object_terms` adds each new relationship
  between `add_term_relationship` and `added_term_relationship`, counts
  them, then removes the rest between `delete_term_relationships` and
  `deleted_term_relationships` (one call, every id) and counts those, then
  `set_object_terms`. Counting runs the taxonomy's `update_count_callback`
  (default `_update_post_term_count`: `update_term_count(tt id, taxonomy,
  count)`, `edit_term_taxonomy(tt id, taxonomy, [])`, the write,
  `edited_term_taxonomy`), then `clean_term_cache(term ids, taxonomy,
  false)`.
- **Meta**: post meta also fires the older `update_postmeta`,
  `updated_postmeta`, `delete_postmeta`, `deleted_postmeta`.
  `get_metadata_by_mid`, `update_metadata_by_mid` and
  `delete_metadata_by_mid` are real (they were placeholders; Yoast and
  Rank Math call them): the row under its own column names (`umeta_id` and
  `user_id` for users), the value decoded.
- **Options**: an `update_option` to an unchanged value writes nothing and
  tells nobody; `delete_option` of a missing option tells nobody.
- **Comments**: `wp_transition_comment_status` (`transition_comment_status`
  and `comment_{old}_to_{new}` when the status moves,
  `comment_{status}_{type}` always), `clean_comment_cache`,
  `wp_update_comment_count_now` (`clean_post_cache`,
  `wp_update_comment_count`, `edit_post_{type}`, `edit_post`).
  `wp_update_comment` writes and tells plugins even when nothing changed.
  `wp_set_comment_status` and `wp_delete_comment` follow the reference's
  order (delete: `delete_comment`, `deleted_comment`, `clean_comment_cache`,
  `wp_set_comment_status(id, 'delete')`, the transition to `delete`, and a
  recount only for an approved comment). `wp_allow_comment` fires
  `check_comment_flood`. Over REST: create is `wp_insert_comment` (no
  notice to the moderator: the reference sends none through REST, so the
  engine stopped sending one), update is `wp_update_comment` then the
  status in words (`approve`, `hold`) or `wp_spam_comment` /
  `wp_trash_comment`, then `rest_insert_comment` and
  `rest_after_insert_comment`; delete ends with `rest_delete_comment`.
- **Settings** go through `update_option` with the typed value.
- **Media**: an upload is `pre_post_insert`, the row, `_wp_attached_file`
  through `add_post_meta`, `clean_post_cache`, `add_attachment`, the REST
  pair, `wp_after_insert_post`, and last the metadata through the
  `wp_generate_attachment_metadata` filter (where an optimiser such as
  Smush works) and `wp_update_attachment_metadata`. The engine still cuts
  the sizes before the row exists (`Media\Writer::prepare`); the stored
  blob is byte for byte what PHP's serializer writes. An edit is
  `pre_post_update`, the row (always stamped modified, as the reference's
  update is), `edit_attachment`, `attachment_updated`, `rest_insert_attachment`,
  the alt text through `update_post_meta`, `rest_after_insert_attachment`,
  `wp_after_insert_post`. `wp_delete_attachment` is `delete_attachment`,
  the terms, the comments, the meta row by row, then the row between
  `delete_post` and `deleted_post`, `clean_post_cache`, and the files.
- **Terms**: `wp_insert_term` cleans the term cache between
  `create_{taxonomy}` and `created_term`; `clean_term_cache` with its
  taxonomy flag runs `clean_taxonomy_cache`, which drops and rebuilds
  `{taxonomy}_children` (a hierarchical taxonomy only) and fires
  `clean_taxonomy_cache`. `wp_update_term` fires `edit_term_taxonomy` /
  `edited_term_taxonomy` and cleans before `edited_term`. `wp_delete_term`
  of a hierarchical term tells plugins its children move
  (`edit_term_taxonomies`, a clean, `edited_term_taxonomies`, even with no
  children), then `delete_term_taxonomy`, `deleted_term_taxonomy`, a clean,
  `delete_term`, `delete_{taxonomy}`. The REST controller keeps its own
  refusals and writes through these (`Runtime\TermEvents`).
- **Users**: `wp_insert_user` writes the row, fires `wp_set_password`,
  adds each profile meta through `add_user_meta`, sets the role
  (`add_user_role`, `set_user_role`), `clean_user_cache`, `user_register`,
  whose default `wp_maybe_update_user_counts` keeps `user_count` through
  `update_site_option` (which on a single site fires
  `update_site_option_{name}` and `update_site_option` after the option's
  own). Over REST the account is inserted with `role => false`
  (`set_user_role(id, false, [])`, empty capabilities), then
  `rest_insert_user`, then the role added (`add_user_role`), then
  `rest_after_insert_user`. `WP_User::set_role` fires `remove_user_role`
  and `add_user_role` around `set_user_role`. An update fires
  `wp_set_password` for a new password, `clean_user_cache` before and after
  the meta, `profile_update`, then `wp_update_user`. `wp_delete_user`
  removes each meta row by id (`delete_user_meta(ids, id, key, value)`),
  the row, `clean_user_cache`, `deleted_user` (and the count). Not yet:
  the password-change and email-change notices WordPress mails the user.

**The reference's default callbacks**, registered in its order (each was a
data difference: the facade registered none): `_transition_post_status`
(5, a post leaving `future` leaves the cron calendar),
`_update_term_count_on_transition_post_status` (only a move into or out of
`publish`), `_wp_auto_add_pages_to_menu` (a top-level page published
joins menus set to auto-add), `__clear_multi_author_cache`, the calendar
block's `wp_calendar_block_has_published_posts` (posts only, publish
moves only; on delete only for a published post); `_delete_option_fresh_site`
(`fresh_site` becomes `'0'` on publish_post and publish_page);
`_future_post_hook` (a scheduled post gets its `publish_future_post`
event: **without it a post scheduled on Minn never published after a
swap back**) and `check_and_publish_future_post` (publishes when due,
schedules again when early); `delete_get_calendar_cache`;
`_reset_front_page_settings_for_post` (trash or delete of the front page
sets `show_on_front` to `posts` and `page_on_front` to 0, of the posts
page `page_for_posts` to 0); `_reset_privacy_policy_page_for_post`
(delete only); `_wp_delete_post_menu_item` (menu items pointing at a
deleted post go with it); `_delete_attachment_theme_mod` (`custom_logo`);
`_clear_modified_cache_on_transition_comment_status`;
`default_password_nag_edit_user`. `wp_publish_post` fires the save
actions without `post_updated`, as the reference does.

Kept different on purpose: pingbacks, trackbacks and enclosure checks are
Mute (`contracts/lexicon.md`), so a published save queues no `_pingme`,
`_encloseme` or `do_pings`; the suite drops those from both sides.

Still open: the engine's own cron
publishes due posts by row without these actions; `apply_filters(
'the_content')` called by a plugin has none of the reference's defaults
behind it, so a newsletter renders raw block markup (round trip on
shop-dogfood); `wp_new_comment` and `wp_check_comment_flood` are still
missing (placeholder / absent).

## The content filters (2026-10-06)

A plugin that renders text itself runs the reference's filters:
`apply_filters('the_content', $raw)` for a newsletter, a builder's text
widget, a REST field. On Minn those filters had almost nothing behind them
(the_content held only the embed callbacks), so a newsletter mailed raw
block markup, and four of the functions they call (`convert_smilies`,
`capital_P_dangit`, `force_balance_tags`, `wp_staticize_emoji`) returned
their input unchanged. Probe `content-filters` (57 rows, api suite) pins
each function and the chains.

**Registered as the reference registers them**, names and priorities, so
a plugin's `remove_filter('the_content', 'wpautop')` finds what it removes:
the_content (block hooks, both embed callbacks at 8; `do_blocks` 9;
`wptexturize`, `wpautop`, `shortcode_unautop`, `prepend_attachment`,
`wp_replace_insecure_home_url` 10; `capital_P_dangit`, `do_shortcode` 11;
`wp_filter_content_tags` 12; `convert_smilies` 20), the_title (+
`capital_P_dangit` 11), the_excerpt (+ `shortcode_unautop`,
`wp_replace_insecure_home_url`, `wp_filter_content_tags` 12),
comment_text (+ `capital_P_dangit` 31), term_description,
the_post_thumbnail_caption, the_content_feed (`wp_staticize_emoji`,
`_oembed_filter_feed_content`), the_excerpt_rss, widget_text_content and
widget_block_content (the embed callbacks as array callables, not
closures, so they can be removed).

**The engine's own rendering** (block and classic themes, feeds) still
renders through its own pipeline, then runs the_content through
`Runtime::contentFilter()`, which skips the defaults that pipeline has
already done (`Hooks::filterWithout`: block hooks, `do_blocks`, texturize,
paragraphs, shortcodes, `prepend_attachment`, image tags) and runs the
rest with the plugins' callbacks. So smilies, the capital P and insecure
home addresses now apply to Minn's own pages too, as they always did on
the reference. Not reproduced there: do_blocks moving `wpautop` (below)
when the engine renders block content itself.

**What the oracle showed** (each by probing, no source):

- `convert_smilies` (only with `use_smilies`): the table is the
  reference's own (`data/smilies.json`, read from it), through the
  `smilies` filter. A code counts only as a whole whitespace-separated
  word in a text run; nothing in a tag, and nothing inside lowercase
  `code`, `pre`, `script`, `style` or `textarea` (an uppercase `<CODE>`
  is not skipped). Emoji codes become the character; `:mrgreen:` becomes
  `<img src="{includes}/images/smilies/mrgreen.png" alt=":mrgreen:"
  class="wp-smiley" style="height: 1em; max-height: 1em;" />` through
  `smilies_src`.
- `capital_P_dangit`: "Wordpress" after a space, `(`, `>`, `&#8216;` or
  `&#8220;` only (not at the start, not after a newline, a tab, a plain
  quote or `&#8217;`); in the_title every occurrence.
- `force_balance_tags` (`Content\TagBalancer`): names lowercased; void
  elements self-closed (`<br />` bare, `<img src="x"/>` with attributes,
  as given when already closed); reopening the innermost open element
  closes it first except for article, aside, blockquote, details, div,
  figure, object, q, section and span; a closer shuts what was opened
  inside it, a stray one goes; the rest close in reverse at the end;
  script and style text untouched; a comment's insides are markup like
  any other.
- `wp_encode_emoji` and `wp_staticize_emoji` (`Content\Emoji`, list
  `data/emoji.json` read from `_wp_emoji_list`): text holding any `&#x`
  is not encoded; the longest listed sequence wins; images go only into
  text runs outside code and pre; once anything matched, leftover
  `&#xfe0f;` selectors are dropped, while a sequence that lists its
  selector keeps it in file name and alt. CDN
  `https://s.w.org/images/core/emoji/17.0.2/72x72/` (`emoji_url`), `.png`
  (`emoji_ext`).
- `wp_replace_insecure_home_url` acts only when
  `wp_should_replace_insecure_home_url()` (https in use, the
  `https_migration_required` option, home and site on one host).
- `wp_filter_content_tags` (`Blocks\ImageTags::content`): an attachment's
  image as the block renderer fits it, a loading it already names kept
  (an eager large one also `fetchpriority="high"`); any other image
  `decoding="async"`, plus loading when its width and height are known;
  an iframe with both `loading="lazy"`. Running it twice changes nothing.
- `do_blocks` over block content inside the_content takes `wpautop` off
  for the rest of that run and adds `_restore_wpautop_hook` one priority
  later, which puts `wpautop` back at the END of its priority: after a
  block post has been rendered, later classic content in the same request
  is autop'd after `shortcode_unautop` (a caption shortcode stays inside
  its paragraph). The probe pins that order.
- `make_clickable` links get `rel="nofollow ugc"` while comment_text runs,
  `nofollow` elsewhere.


## The REST server's envelope (2026-10-06)

The reference runs every route, core or plugin, inside the same filters;
Minn answered its own routes (everything under `/wp/v2` it serves) beside
them, so a plugin could refuse a plugin route but not a core one. A
security plugin blocking user listing, a REST cache answering from its
store, a role editor taking `edit_posts` away, a lock plugin refusing a
deletion through `map_meta_cap`: each did nothing on Minn. Suite
`rest-envelope` sends both stacks the same requests with the fixture plugin
`tests/fixtures/runtime/minn-test-rest-envelope` hooked per request (header
`X-Minn-Envelope`) and compares status, the plugin's headers and the body,
including what each filter was handed.

The order, as captured: `rest_request_before_callbacks` is handed the
argument check's error or null, with the handler (`methods`, `accept_json`,
`accept_raw`, `show_in_index`, `args`, `callback`, `permission_callback`)
and the request; an error it returns is the answer, and anything else is
not (the route still runs; null even clears an argument error). The
permission comes next: its refusal is the answer and nothing else runs, so
`rest_dispatch_request` never answers a caller the route refuses. Then
`rest_dispatch_request` (handed null, the request, the route as the index
names it, the handler) may answer in place of the route; then
`rest_request_after_callbacks` sees whatever came of it, error or response,
and may change it. A core write leaves `context=edit` on the request.
Serving: `rest_post_dispatch` may change the response (status, headers);
`rest_pre_serve_request` may serve it itself, what it prints being the
body; `rest_pre_echo_response` may rewrite the data echoed. A response's
links live on the response object, not in its data: a plugin replacing the
data keeps them, and a `self` link's `targetHints` follow its `href`.

How it is built: `Http\Router` judges the argument check and the policy
first and hands both refusals, unthrown, to an `Http\Envelope`
(`Rest\RuntimeEnvelope`); a policy that declines the route (a `{base}`
naming no declared type) throws before any plugin hears of the request.
The API is built before the runtime boots, so the envelope decides per
request; unbooted, or with none of the three filters hooked, the route
answers exactly as before, unconverted. `Rest\RuntimeRoutes::serve()`
runs the serving filters over every REST answer, engine or plugin route.
Converting: the JSON is decoded with an empty object kept an object, the
`_links` expanded back into response links, and a response or error a
filter hands back untouched is the engine's own answer byte for byte. One
`WP_REST_Request` serves a request throughout, filters and the
`rest_insert_*` actions alike. With plugins loaded, `Rest\Caller::can()`
asks `user_can()`: the engine's mapping, then `map_meta_cap` and
`user_has_cap`.

Route names: the engine's patterns now spell the reference's, so the index
keys and the route plugins are handed agree (`(?P<id>[\d]+)`, a revision's
post as `parent`, `user_id` for application passwords, `type` and
`taxonomy`, the templates' and global styles' own expressions). A
snake_case capture binds to its camelCase parameter. 190 of the 190 core
routes Minn serves are named as the reference names them.

Still apart (shrink-only `DIVERGENT` in the suite): the handler's `args`
for an edit route is empty, because Minn's index publishes one endpoint per
route with its query arguments where the reference publishes one per method
group with the schema's; and a `context` no schema names (reachable only
when a plugin clears the argument error) leaves no fields on the reference.
About sixty core routes Minn does not serve at all (`/wp/v2/statuses`,
`block-types`, `themes`, `sidebars`, `widgets`, `oembed/1.0`,
`font-families`, `wp-site-health/v1`, `block-patterns`, `view-config` and
others) answer `rest_no_route`; that is the next REST gap.

## The comment form (2026-10-06)

`wp-comments-post.php` was Minn's own: it checked and stored a comment
correctly but told plugins nothing, so a spam plugin (CleanTalk, on most of
the fleet; Akismet) never saw a front-end comment, notifications went
around `wp_mail` and its SMTP plugin, and the commenter cookies were
Minn's. With plugins loaded it now runs WordPress's submission, captured
three ways: suite `hook-trace` step `comment-form` (actions, signed out,
the filter window opened at `wp_loaded`), probe `comment-fields` (each
field's chain, 33 rows), and suite `comment-form` (the same form posted to
both stacks: status, redirect, cookies, the refusal's words, the stored
comment; with fixture plugin `minn-test-comment-guard` judging comments as
spam plugins do).

The order (`Runtime\CommentForm` and the facade): a reply to a held comment
is refused (403); the post's refusals each fire their action
(`comment_id_not_found` and `comment_on_trash`, `comment_on_draft` for a
reader who may not see it, `comment_on_password_protected` answer a blank
200; `comment_closed` 403), else `pre_comment_on_post`; a signed-in user's
own name and addresses replace the form's (an administrator's comment is
kses'd after all unless the form carried its unfiltered-html nonce), or
`comment_registration` refuses (403); the required fields, an empty comment
(`allow_empty_comment`) and the column lengths answer 200 with the error;
then `wp_new_comment` on slashed data: `preprocess_comment` (where a plugin
may `wp_die`), the ids, parent, address, agent and dates filled in,
`wp_allow_comment` (`duplicate_comment_id` is handed null or the duplicate's
id and refuses with 409; `check_comment_flood`, whose legacy callback
`check_comment_flood_db` attaches `wp_check_comment_flood` to
`wp_is_comment_flood`, refuses a comment within fifteen seconds of the last
from the same address or email, through `comment_flood_filter` and
`comment_flood_trigger`, with 429; then the approval: the post's author
and a moderator approved, anyone else through `check_comment`, which counts
links in the comment as `comment_text` shows it, the disallowed words to
the trash, `pre_comment_approved` last, an error refusing), the
`pre_comment_*` filters (user, agent, name, content, address, url, email),
the approval judged a second time on what they left, `wp_insert_comment`,
`comment_post`, whose two callbacks tell the moderator of a held comment
and the post's author of an approved one. Then `set_comment_cookies`
(`wp_set_comment_cookies`: a year, `path=/`, `secure` on https, no
SameSite; blank values to forget a commenter who did not consent), the
comment's link or `redirect_to`, the held comment's id and
`wp_hash(comment_date_gmt)` for a commenter not remembered,
`comment_post_redirect`, `wp_safe_redirect`.

The field chains, as the reference registers them: author name
`sanitize_text_field`, `wp_filter_kses`, `_wp_specialchars` at 30; url
`wp_strip_all_tags`, `sanitize_url`, `wp_filter_kses`; email `trim`,
`sanitize_email`, `wp_filter_kses`; content `convert_invalid_entities`, kses
(from `kses_init`, now hooked on `init` and `set_current_user` as on the
reference), `_wp_kses_sanitize_note_mention_classes` at 11 (a span loses
its class), `wp_rel_ugc` at 15 (rel gains "nofollow ugc" after what it held,
"ugc" alone for the site's own host, and moves last), `balanceTags` at 50.
The chains work on slashed text, as the reference's do.

Mail: the moderator's notice keeps Minn's wording (it points to Minn Admin)
and goes through `comment_moderation_recipients`, `_headers`, `_text`,
`_subject` and `wp_mail`, so a mail plugin delivers it; the post author's
through the `comment_notification_*` filters. Also: `do_action_ref_array` and
`apply_filters_ref_array` hand an `all` callback the arguments as the one
array they came in (`phpmailer_init`'s mailer, for one).

Without plugins loaded, the engine's own path answers as before; it still
differs where the reference does not apply (its refusal page is Minn's).

A held comment is shown to its author, as the reference shows it: to the
signed-in account that wrote it, to the email the commenter cookie
remembers, or to whoever follows the redirect's `?unapproved=` link with a
`moderation-hash` that is `wp_hash` of its GMT date, for ten minutes after
it was posted (`wp_get_unapproved_comment_author_email`). It renders with
`<p><em class="comment-awaiting-moderation">Your comment is awaiting
moderation.</em></p>` ahead of its text. Every second comment carries
`odd alt` and `thread-odd thread-alt`, as the reference counts from zero
(Minn had left out `alt`). Suite `comment-form` checks both ways in and the
expiry.

The form itself, with plugins loaded, is `comment_form()`'s, as the
reference's block renders it (the block theme's button in
`comment_form_defaults`, the block's classes on the respond wrapper), so
every hook a plugin adds its fields through runs: `comment_form_defaults`,
`comment_form_default_fields`, `comment_form_fields`, the
`comment_form_field_*` filters, `comment_form_top`,
`comment_form_before_fields` and `_after_fields`, `comment_form_logged_in`,
`comment_form_submit_field` and `comment_form`. A remembered commenter's
name, email and site are filled in and the consent box is checked; a
signed-out reader's fields always offer the consent box (a plugin's own
fields included) while `wp_set_comment_cookies` is hooked; a signed-in
user sees "Logged in as {name}. Edit your profile. Log out?" with the
required-fields note and no fields; a user who may post unfiltered HTML
gets `wp_comment_form_unfiltered_html_nonce` on `comment_form` (the nonce,
renamed by an inline script only outside a frame). The suite compares the
form for all three readers (97).

## Saves plugins can change (2026-10-06)

A post saved through Minn's REST routes, or through `wp_insert_post` by a
plugin, now passes the filters the reference's save runs before it writes,
in its order (probe `post-insert-filters`, every filter's arguments
compared; suite `rest-envelope`'s save cases): `rest_pre_insert_{type}`
over the prepared post (the REST fields the request named, its ID on an
edit, its type on a create; an error refuses with its status); every
column through its db context, on slashed text, as `sanitize_post` runs it
(`pre_post_*` then `*_save_pre`, a column named without the prefix
`pre_post_*` then `*_pre`); `wp_insert_post_empty_content`, which refuses
a post of a type with an editor, a title and an excerpt that has none of
them (400 `empty_content`); `wp_insert_post_parent` (default
`wp_check_post_hierarchy_for_loops`: no parent that would make the post
its own ancestor); for a post that is not a draft, pending or an
auto-draft, the slug, made from the title the filters left when the
request gave none, through `pre_wp_unique_post_slug`, the bad-slug filter
(the next free number) and `wp_unique_post_slug`; then
`wp_insert_post_data` over the 21 columns, handed the sanitized array, what
was given (a create's own fields and type; an update's whole merged post,
its categories among them) and whether it updates. What any of them
changes is what is written (`Runtime\PostSave`, shared by the REST
controller and the facade).

The reference's defaults are registered with it: `content_save_pre`
`convert_invalid_entities` and `balanceTags` at 50 (and, for a user who may
not post unfiltered HTML, kses and `wp_strip_custom_css_from_blocks` at 8,
which takes a block's `style.css` out), `title_save_pre` `trim`,
`excerpt_save_pre` entities and `balanceTags`, `pre_post_status`
`sanitize_key`, `pre_post_guid` tags out, `sanitize_url` and kses,
`pre_post_mime_type` `sanitize_mime_type`, and the template and changeset
callbacks, which hand Minn's values back (it keeps template slugs itself and
has no changesets).

Each item a response carries passes the filter the reference runs as it
prepares one: `rest_prepare_{post type}` for posts, pages and declared
types, `rest_prepare_attachment`, `rest_prepare_user`,
`rest_prepare_comment` and `rest_prepare_{taxonomy}`, in the view and edit
contexts, on reads, lists and writes alike (`Rest\RuntimePrepare`). The
filter is handed the item as a response object with its links on it, the
`WP_Post`, `WP_User`, `WP_Comment` or `WP_Term` it describes, and the
request; a link it adds is compacted with its CURIE as the reference's are.
With nothing hooked, or the response handed back untouched, the item is
Minn's own, byte for byte. Suite `rest-envelope` (42) checks each.

## Capabilities (2026-10-06)

`map_meta_cap` answered with the capability's own name for 32 meta
capabilities the reference maps, so no role held them (an administrator
could not `delete_user`, `promote_user`, `edit_comment`, `customize`,
`activate_plugin` or create their own application password) or the role's
own primitive decided where the reference refuses (`unfiltered_upload`,
`manage_links`). Probe `meta-caps` (402 rows: `map_meta_cap` and
`user_can` for an administrator, an editor and an author) and probe
`post-caps` (144 rows: `edit_post`, `delete_post` and `read_post` over
posts of every status by two authors) now agree throughout.

The rules, as captured: `unfiltered_upload` is refused unless
`ALLOW_UNFILTERED_UPLOADS` is set; the plugin, theme, language, user,
privacy, customizer and HTTPS capabilities name their primitive
(`upload_plugins` → `install_plugins`, `delete_user` → `delete_users`,
`customize` → `edit_theme_options`, `update_https` → `manage_options` and
`update_core`, and so on); `delete_site` and `edit_block_binding` (without
a block context) are refused; `manage_links` needs the link manager on;
one's own application passwords need nothing more and anyone else's need
`edit_users`; `edit_comment` and the post-meta capabilities map through
their post. A post's own capabilities: `read_post` needs `read` for a
published post or one's own, the private read for a private one, and what
editing needs otherwise (future, draft, pending, trash: a trashed post is no
longer readable by anyone who may only read); editing or deleting one's own
post needs the published capability while it is published or scheduled (or
was, before the trash) and the plain one otherwise; someone else's needs the
others capability, plus the published one while published or scheduled or
the private one while private; the privacy policy page needs
`manage_options` as well. `user_has_cap` carries the reference's three
defaults at priority 1: `install_languages` for whoever may update core or
install plugins or themes, `resume_plugins` and `resume_themes` for whoever
may activate plugins or switch themes, `view_site_health_checks` for whoever
may install plugins.

## Uploads (2026-10-06)

`wp_handle_upload` and `wp_handle_sideload`, which plugins call for their own
upload forms and downloads, stored any file under its own name: no
prefilter, no content check, no clean name (a plugin's refusal, a text file
named `.png` and `x.php.png` all went through), and, with `unfiltered_upload`
granted to administrators (see Capabilities), an administrator's `.php`
went in too. They now take a file as the reference takes one
(`Runtime\FileUpload`; probe `upload-filters`, 38 rows, every filter and its
arguments compared): `{action}_prefilter`, where a plugin sanitizes a file
or refuses it with an error; `{action}_overrides`; PHP's upload error, an
empty file, and for a form upload the form's action and the upload itself;
the type from the content (`wp_check_filetype_and_ext`, `Runtime\FileTypeCheck`):
an image the server can measure is what its bytes say, its name corrected
when the extension is another image's (`a.png` holding a JPEG is `a.jpg`),
anything claiming to be such an image and not being one has no type, any
other type must match the content (plain text standing for txt, csv and a
few more), and the type must be allowed; refused unless the user may
upload anything; `wp_upload_dir`; `wp_unique_filename` (the name through
`sanitize_file_name`, the extension lowercased, `-1`, `-2`... for a name
taken, the filter with the number); `pre_move_uploaded_file`; the move;
`wp_handle_upload`.

`sanitize_file_name` transliterates accents, gives an inner part that looks
like an extension and is no allowed type an underscore (`x.php_.png`,
`shell.phtml_.jpg`), and names a file that is only an extension
`unnamed-file.{ext}`. `get_allowed_mime_types` adds web pages and scripts
for a user who may post unfiltered HTML, as the reference does.

With plugins loaded, Minn's REST media route stores the file through the
same path (`wp_handle_sideload` for a request body, `wp_handle_upload` for a
form field), so an SVG plugin's `upload_mimes` and prefilter, a security
plugin's refusal, an optimizer's `wp_handle_upload` all work on uploads made
in Minn Admin; a refusal answers the route's 500 with the plugin's words.
Unbooted, the engine's own check answers, as strict (suite `rest-envelope`).

## Accounts plugins can change (2026-10-06)

`wp_insert_user` ran `sanitize_user` and nothing else: no `pre_user_*`
sanitisers (a description kept its `<script>`, a display name its tags, a
site address its missing scheme), no `illegal_user_logins` (a login the site
forbids was created), no `wp_pre_insert_user_data`, `insert_user_meta` or
`insert_custom_user_meta`. It now runs them in the reference's order (probe
`user-insert-filters`, 7 rows, every filter and its arguments compared;
`Runtime\UserSave`): the login, sanitized strictly then `pre_user_login`,
looked up as `sanitize_user` leaves it, refused when
`illegal_user_logins` names it; the nicename, made from the login for a new
account; the email, then the URL; the nickname (the login when none), first
and last names; the display name (an update's merged one, else first and
last name, either, or the login); the description; then
`wp_pre_insert_user_data` over the row (the password hashed), handed whether
it updates, the account's id and what the caller gave, and after the row
`insert_user_meta` and `insert_custom_user_meta`. `wp_update_user` merges the
stored account and its profile under the given fields, as the reference
does; the stored hash it carries is handed to the filters and is never taken
for a new password.

On REST, `rest_pre_insert_user` runs over the prepared account first, and a
new login is a parameter error when it does not survive strict sanitizing
or `illegal_user_logins` names it (`rest_invalid_param`, with
`rest_user_invalid_username` in its details), as the reference's username
argument refuses it. A refusal from the save is the route's 400; Minn no
longer writes the account itself when the runtime's save refuses.
