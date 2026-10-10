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
- **The main query stands on the matched rule's vars** before
  `template_redirect` (the Resolution carries them to the request parse and `_minn_seed_main_query`):
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

A plugin that registers its own URLs the reference way works end to end
(CaptainCore Manager's `/account/` SPA was the proving ground): every
request path is matched against the site's rewrite rules as WordPress's
request parse matches them (`Front\RuleTable`, since 2026-10-07 the only
reading of a path; `contracts/front/permalinks.md`). `add_rewrite_rule()`
'top' rules come first in that list (so `/account/` routes to the plugin
even though a page named account exists), 'bottom' ones last, and a rule
a plugin changed through `rewrite_rules_array`, `generate_rewrite_rules`
or a section filter is matched as changed. A matched rule's query string
substitutes `$matches[N]` and parses to vars; the request parse keeps
those that are public (the registry's list plus whatever the `query_vars`
filter admits, which is how a plugin registers its own), and a registered
type's or taxonomy's own var is public as the reference makes it (probe
cpt-rules, which also pins a type's archive rules). Content vars resolve
to that content; anything else runs the home query under the rule, the
reference's shape. Endpoints are the rules their places generate.

The takeover half is `template_include`: the classic runner always applied
it; `PageRenderer` now applies it under block themes too (the engine's
`wp-api/template-canvas.php` path is the incoming value) and a swap to a
real PHP file is loaded as the whole response. Pinned by the fixture
plugin's routed page in `tests/runtime.test.php` (byte-identical on both
stacks; the reference side needs `wp rewrite flush` because it matches
from the flushed option while the engine matches the live registrations).
Not carried: external (non-index.php) rules. A plugin template that calls wp_head() under a BLOCK theme gets
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
- WP_MatchesMapRegex urlencodes every substitution; `Front\RuleTable`
  does the same, so a captured '&x=1' cannot split into extra query
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
| `WP_Http` | `WpOrg\Requests\Requests` (the facade), `Minn\Http->send()` | `WP_Http` maps its arguments onto the Requests library, whose curl transport sends through a `Minn\Http` client's `send()`; a test answers `wp_remote_*()` with WordPress's own `pre_http_request` filter |
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
sanitising (`Minn\Json::repair`), CURIE compaction (`Rest\Links`) and block asset
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

A type's rules and a taxonomy's are among the site's (`Front\RuleTable`)
once plugins have registered: a type's archive answers at its `has_archive`
slug (a string) or its rewrite slug (`true`), and wins over a page of the
same name (its archive rules are top rules: `/shop/` is the product
archive on the reference, not the shop page); a single answers under the
type's rewrite slug; a term under the taxonomy's rewrite slug,
hierarchical paths checked whole. Two resolution
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
`Front\Archives`. The pages are `get_pages()`'s (`Runtime\Pages`, a
WP_Query and the `get_pages` filter), with the list's own arguments, as on
the reference: a page whose parent is not published is left out, and
`child_of` narrows the query rather than the walk. The navigation's
`core/page-list` block takes its pages from the same place.

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
- `wp_remote_*` sends through the Requests library, as on the reference
  (since 2026-10-07; before, `WP_Http` had a curl path of its own beside
  it): the library's hooks fire as `requests-{$hook}` actions in the
  reference's order, `http_api_curl` gets the curl handle, the parsed
  arguments and the URL, `$response['http_response']->get_response_object()`
  is the library's `Response` (redirect count, history, final URL), cookie
  values come back decoded, a header sent twice comes back as a list, a
  transport failure reads `cURL error N: ...`, past the redirection limit
  `Too many redirects`, `redirection` 0 returns the 3xx, a 302 answering a
  POST is followed with a GET (the library alone keeps the POST and turns
  only a 303 into a GET), a body goes in the body for anything but GET and
  HEAD (`data_format`), and a request that does not wait still reaches
  `http_api_debug`. Probe rows in `tests/tools/requests-probe-network.php`.
  Not done: the `_redirection` key in the arguments `http_api_curl` sees,
  and `curl_multi` (`request_multiple` sends one after another; the answers
  come back in input order, the reference's in completion order).

## The main query decides the page (2026-10-07)

The WooCommerce suite (`tests/woo.test.php`) compares whole body-class lists and titles, and they
showed the engine building both from the resolution (one kind per page) while the reference reads
the main query, whose flags combine. Both now come from the main query; the resolution keeps the
template choice, the canonical moves and the record.

- **Title**: `wp_get_document_title()` is the one path (both theme kinds, feeds, embeds), from the
  main query's flags: a date archive is `August 2026`, a listing past its first page adds
  `Page N`, a search inside an archive reads `Search Results for …`. `Front\DocumentTitle` is gone.
- **Body classes**: `Theme\QueryClasses` reads the main query: `home`, `blog`, `privacy-policy`,
  `archive`, `date`, `search` + `search-results`/`search-no-results` (the page's posts),
  `paged`, `attachment`, `error404`, then the singular's or the archive's own tokens, then
  `paged-N` and the view's `{prefix}-paged-N` (single, page, category, tag, date, author, search,
  post-type, in that precedence; none for the front or a plain taxonomy). The bare `paged` is
  the listing's page only (`/multi/2/` is `paged-2 single-paged-2`); a 404 has no paging tokens.
  Embeds read the classes after their own main query stands.
- **Date flags** (probe `date-flags`): only the most specific part named raises its flag (time,
  day, month, year), and `m` raises one more by its digit count (1-5 year, 6-7 month, 8-9 day, 10+
  time); `w` raises only `is_date`.
- **Search flags** (probe `search-flags`): a query is a search when it was given `s` at all (empty,
  `0`, `false`, an array; not null), read from the query as first asked so a re-parse keeps it,
  and never when it names a single.
- **`error` from the query string** is ignored by the request parse; only a path no rule matches
  sets it.
- **404 for an empty listing** (`FrontLifecycle::handle404`): past the first page, or an empty
  date that names nothing else; a date inside an existing term's, author's or type's archive, or
  inside a search, is a 200.
- **Post format terms** (probe `post-format-terms`): `_post_format_get_term` on `get_post_format`,
  `_post_format_get_terms` on `get_terms`, `_post_format_wp_get_object_terms` on
  `wp_get_object_terms`, as defaults plugins can remove. A format term reads by its format's name
  (`Aside`); a names list is read as format slugs, so `get_terms(fields=names)` answers `""` for a
  format term on both stacks (the names arrive already named).
- **`add_query_arg()` without a URL** starts from the request URI with its query string
  (WooCommerce's Store API builds `add_to_cart.url` from it).
- **REST links**: a plugin's `add_links()` keeps every key but `href` as the link's attributes (a
  literal `attributes` key included); `self` links on plugin routes carry `targetHints.allow`.
- **The static front page's query** (probe `front-query`): a home query naming nothing but
  `paged`, `page`, `cpage` or `preview` becomes the front page's (`page_id`, singular, the
  queried object the page); a given `paged` moves into `page` and `paged` reads `""`, while
  `is_paged` keeps the parse's answer. `/page/2/` under a static front is `home paged … page
  page-id-N … paged-2 page-paged-2`, titled `Site – Page 2 – Tagline`. Any other var keeps it
  the home listing.

## plugins_api (2026-10-07)

`plugins_api($action, $args)` (`Ops\DirectoryApi`, probe plugins-api) was
missing, so the plugins that call it (Blocksy Companion, CaptainCore
Manager, CleanTalk and Elementor on the anchor site) fatalled, and the
25 that answer through its filter were never asked. The arguments become
an object with the reader's `locale` and the major.minor `wp_version`
beside them, through `plugins_api_args`; whatever `plugins_api` answers
other than false stands (a self-hosted plugin's own information, or a
`WP_Error`), and only when nobody answers is the directory's info API
asked, through `Ops\Packages::infoAction` (the engine's existing door
to it, which Track H moves behind the Minn update service; no new
wordpress.org call site), the arguments as its `request`, the answer an
object at the top with arrays inside;
`plugins_api_result` is handed what came back either way, errors included.
`themes_api` (probe themes-api, 2026-10-07) is the same class with `themes`
for `plugins`: a theme search's themes come back as objects. The directory
is read whatever the status, so a refusal carries its own words ("Theme
not found", "Plugin not found."). An answer that is not JSON is the
reference's "An unexpected error occurred" message, the body as its data,
with one exception: wordpress.org words an unknown theme action as JSON to
WordPress but as a paragraph to anyone else (the update service), so for
themes that paragraph is read as the refusal WordPress would have had.

## unzip_file (2026-10-07)

`unzip_file($file, $to)` (`Ops\Unzip`, probe unzip-file) refuses with
`fs_unavailable` until `WP_Filesystem()` has set the filesystem up, then
unpacks through it: the destination and its missing parents, every folder
the archive names or implies, then the files, over whatever the
destination already holds (other files are kept). Entries under
`__MACOSX/` are left out (a `.DS_Store` is not), and so, silently, is an
entry whose name climbs out (`../`), is absolute or names a drive.
`pre_unzip_file` and `unzip_file` are handed the folders to make and the
space needed, 2.1 times the unpacked size; less free disk than that is
`disk_full_unzip_file`. An archive the reader cannot open is
`incompatible_archive` with the PclZip text the reference falls back to
(`PCLZIP_ERR_MISSING_FILE (-4) : Missing archive file '…'`,
`PCLZIP_ERR_BAD_FORMAT (-10) : …`).

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
  selector keeps it in file name and alt. Images come from the Minn update
  service, `https://updates.minn.run/v1/assets/s/images/core/emoji/17.0.2/72x72/`
  (`emoji_url`; the reference's are wordpress.org's emoji CDN, and the api suite
  compares through the service's rewrite, `minn_test_via_service`), `.png`
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

## Terms plugins can change (2026-10-06)

`sanitize_term` and `sanitize_term_field` handed back what they were given,
and `wp_insert_term` ran `pre_insert_term` and nothing else: a tag named
`<b>two</b> & co` was stored with its tags and a bare ampersand, no
`pre_term_*` filter ran, and neither did the slug, data, duplicate and id
filters. Now (`Runtime\TermFields`, `Runtime\TermSave`):

- A field in a context is as the reference makes it (probe `term-sanitize`,
  105 rows): the numeric fields are whole numbers, never below zero, in every
  context; `edit` runs `edit_term_{field}` and `edit_{taxonomy}_{field}` and
  escapes text for a form; `db` runs `pre_term_{field}` and
  `pre_{taxonomy}_{field}` (a slug also `pre_category_nicename`); `rss` runs
  the `_rss` pair; any other context the display pair, escaped for an
  attribute or a script when it names one. A lookup with no taxonomy hands
  `false` to the filters. The defaults are the reference's: `pre_term_name`
  is `sanitize_text_field`, `wp_filter_kses`, `_wp_specialchars` at 30;
  `pre_term_slug` `sanitize_title`; `term_name` texturizes, converts and
  escapes; `term_name_rss` converts. `get_term`, `get_term_by`,
  `get_category` and `get_tag` put a term in the context asked for after
  their own filters, on a copy; `get_term_field` answers in `display` by
  default; `WP_Term::filter()` works.
- Looking a term up by name compares the name as saving stores it (probe
  `term-lookup`, 23 rows): `get_terms` with `name` runs each through the
  first taxonomy's name filters, `get_term_by('name')` through the
  taxonomy's, and `term_exists` tries the slug first, then the name, a
  parent narrowing both (0 to the top level). A digit string is a name, not
  an id. Under a parent with no children `get_terms` answers at once (a
  count of 0) without looking at names.
- A save runs in the reference's order (probe `term-insert-filters`, 31
  rows, filters and actions compared): `pre_insert_term`; an empty name or a
  missing parent refused; every field through its `db` filters; a name the
  parent already has refused (in a flat taxonomy, a name the taxonomy has),
  the error carrying the existing id; `wp_unique_term_slug`, which adds the
  parents' slugs to a taken slug before
  `wp_unique_term_slug_is_bad_slug` and numbers past any term holding it in
  any taxonomy; `wp_insert_term_data`; a slug left empty becomes the id;
  `wp_insert_term_duplicate_term_check` (a plugin may name a term to keep
  instead); `create_term`, `term_id_filter`, `created_term`, `saved_term`.
  An update merges the stored term, slashed so a backslash survives, under
  the change, refuses an empty name after the filters, asks
  `wp_update_term_parent` (default `wp_check_term_hierarchy_for_loops`: a
  parent that would put the term under itself becomes 0) and refuses a slug a
  sibling holds; one taken under another parent is made unique.
- Over REST with plugins loaded (probe `rest-term-save`), the controller
  builds the prepared term from the fields the request sends (name and slug
  cleaned by the schema), runs `rest_pre_insert_{taxonomy}`, and hands it to
  the save; a name in use is the 400 with the existing id. A parent that is
  not there is `rest_term_invalid` before anything is saved, on both paths.
  A plugin's in-process `rest_do_request` runs as the current user (one it
  switched to included), and a form-encoded body reads as its fields.

## Images as WordPress makes them (2026-10-06)

An upload's sizes were cut, but nothing else the reference does on the way
happened: a phone photo past 2560 pixels kept its full size as the attached
file, a photo taken turned stayed turned, `wp_read_image_metadata` answered
blank, and no editor filter ran, so a plugin that writes WebP through
`image_editor_output_format` had no effect. Now (probe `image-pipeline`, five
uploads with every image filter and its arguments compared, both stacks held
to GD; probe `image-meta`, 30 crafted photos):

- `wp_read_image_metadata` reads IPTC, then EXIF for what IPTC left empty
  (`Media\PhotoMeta`): headline or object name, caption, credit or byline or
  artist, copyright, keywords, the IPTC date and time (offset applied) or the
  EXIF digitized time, model, aperture to two places, focal length and
  exposure as plain decimals, ISO, orientation. A short description is the
  title and the comment the caption; a description that is not the title
  runs on into the comment; a short caption doubles as the title. Latin-1
  text is converted and everything passes `wp_kses_post`; every value is a
  string. `wp_read_image_metadata_types` and `wp_read_image_metadata` run.
- `wp_create_image_subsizes`, in the reference's order: past
  `big_image_size_threshold` the upload is scaled to it (and turned upright)
  and saved `-scaled`; otherwise one a JPEG's EXIF says is turned is saved
  upright `-rotated`, and one `image_editor_output_format` maps to another
  type is converted; that file becomes the attached file, the upload kept as
  `original_image` (orientation then reads 1). The metadata is stored, then
  each size is cut from the upload (turned upright first), medium and large
  first, and stored as it lands.
- The editor: `wp_get_image_editor` asks `image_editor_output_format` and
  chooses through `wp_image_editors`; quality is set through
  `wp_editor_set_quality` (86 for WebP, 82 otherwise) and, for a JPEG,
  `jpeg_quality`, on load, after each resize and when a save writes another
  type; a save asks `image_editor_output_format` (a size's name is null) and
  `image_save_progressive`; `maybe_exif_rotate` asks
  `wp_image_maybe_exif_rotate`; `make_subsize` cuts one size and puts the
  editor back. `flip()` flips along the horizontal axis for `$horz`, as
  documented.
- Without plugins, Minn's own upload does the same: EXIF and IPTC in
  `image_meta`, a `-scaled` or `-rotated` stand-in, sizes from the upright
  upload, WebP sizes at 86.

## Slugs and accents (2026-10-06)

`remove_accents` folded far more than the reference (Greek and Cyrillic were
transliterated where the reference leaves them to be percent-encoded) and
missed some of what it does fold; Minn's own slugs dropped entities' letters
in and turned a colon into a dash. Now `Support\Accents` is the reference's
table, captured (`data/accents.json`, `tests/tools/accents-capture.php`):
composed form first, the German, Danish, Catalan, Serbian and Bosnian
spellings in those locales, Latin-1 bytes when the text is not UTF-8.
`utf8_uri_encode` cuts at its length without splitting a character and
encodes ASCII when asked; `Content\Slug::sanitize` is the reference's
save-time `sanitize_title` (the times sign reads as an x), and a post saved
through the runtime gets its slug from `sanitize_title` itself, filters
included (probe `slugs`).

## Site Health (2026-10-06)

`WP_Site_Health::get_instance()` was a placeholder answering null, so the
WPMU DEV dashboard recorded no memory limit. The instance is now built while
loading, as on the reference: the memory limit PHP started with, the cron
timeouts (looser under `DISABLE_WP_CRON`), its four hooks, and the weekly
`wp_site_health_scheduled_check` scheduled a day out when missing. The tests
stay placeholders. `WP_MAX_MEMORY_LIMIT` follows the starting limit too.

## Rendering plugins can change (2026-10-06)

A REST response's rendered title, content and excerpt were Minn's own render
even with plugins loaded, so nothing hooked on `the_title`, `the_content` or
`the_excerpt` reached them (a plugin's share buttons, a translation, a
changed title). With plugins loaded they now pass those filters with the
post set up as the reference sets it up (`Rest\RenderedFields`): the stored
title through `the_title`, the engine's render of the content then the rest
of `the_content`, and `get_the_excerpt` then `the_excerpt`.

The loop's view of a post is the reference's (`Runtime\PostData`, probe
`the-content`): `generate_postdata` splits the content into pages at
`<!--nextpage-->` and hands them to `content_pagination`, takes the page from
the query, and shows the whole post ("more") only when the main query is a
single post, a page or a feed; `setup_postdata` sets the loop's globals from
it. `get_the_content` gives that page, cut at the more tag with its link
(`the_content_more_link`; the more block's markers removed; a custom
`<!--more text-->`; `<!--noteaser-->`), or the password form, now the
reference's (`redirect_to`, its wording, a block theme's button classes).

An excerpt is made as the reference makes it (probe `excerpt`):
`get_the_excerpt` hands the stored excerpt to its filters and the default
`wp_trim_excerpt` makes one from the content when there is none: the loop's
content, shortcodes stripped, `excerpt_remove_blocks` (wrappers unwrapped
through `excerpt_allowed_wrapper_blocks`, a block kept only when its children
are plain text blocks, `excerpt_allowed_blocks`), footnotes removed,
`the_content` without block rendering or image tags, then `excerpt_length`,
`excerpt_more` and `wp_trim_words`. A plugin shortening excerpts now
shortens them. `wp_embed_excerpt_more` is the default on `excerpt_more`.

Headings and lists saved without their class get `wp-block-heading` and
`wp-block-list` as they render, as on the reference.

`wp_revisions_to_keep` reads `WP_POST_REVISIONS`, answers 0 for a type
without revision support, and runs its two filters.

## Revisions (2026-10-06)

Revisions were the engine's own: posts, pages and global styles only, never
pruned, and no plugin was asked. `wp_save_post_revision` now runs as the
reference's (`Runtime\PostRevisions`, probe `revisions`, 10 cases with every
hook compared): any type that supports revisions, nothing while
`wp_revisions_to_keep` answers 0; against the latest revision
`wp_save_post_revision_check_for_changes`, the revision fields
(`_wp_post_revision_fields`, footnotes added by default) compared with their
whitespace evened out, and `wp_save_post_revision_post_has_changed`, whose
default also compares the revisioned meta; the revision written through
`wp_insert_post`, then `_wp_put_post_revision`, whose default copies the
revisioned meta (`wp_post_revision_meta_keys`); then the oldest past the
limit removed through `wp_delete_post_revision`, after
`wp_save_post_revision_revisions_before_deletion`. `wp_get_post_revisions`
answers nothing while revisions are off and takes `order`. Without plugins
Minn's own revisions follow `WP_POST_REVISIONS`.

## REST comments plugins can change (2026-10-06)

A comment created over REST with plugins loaded skipped what the reference's
controller runs before the save. It now follows it (probe
`rest-comment-save`, six cases with every hook compared): the request's
fields through `rest_preprocess_comment`, the signed-in author filled in,
`allow_empty_comment`, the date, `wp_get_comment_fields_max_lengths`,
`wp_allow_comment` (a duplicate is a 409 with the id as the database gives
it, a flood a 400, then `pre_comment_approved`), `rest_pre_insert_comment`
(an error refuses with its status), and `wp_insert_comment` over
`wp_filter_comment`. An edit runs `rest_preprocess_comment`, the content
check when it sends content, the length check, `wp_update_comment` (now the
stored comment under the change through `wp_filter_comment` and
`comment_save_pre`, `wp_update_comment_data` handed the data, the stored
comment and the change, `edit_comment` with the columns), then the status as
the controller changes it (approve and hold through `wp_set_comment_status`
with that word). `pre_wp_update_comment_count_now` may supply a post's count.
A REST comment's rendered content passes `comment_text`.

## REST media and permalinks plugins can change (2026-10-06)

With plugins loaded, a REST upload is saved as the reference's controller
saves it (probe `rest-media-save`): the photo's own title and caption
(`wp_read_image_metadata`) unless the request names them,
`rest_pre_insert_attachment` over the prepared attachment (title, type and
template only; caption, description, type and address are added after),
the file's name as the title when none is left, then `wp_insert_attachment`
through the runtime's `wp_insert_post` (the post save filters and
`wp_insert_attachment_data`; the file recorded inside the save; then
`add_attachment`, or `edit_attachment` and `attachment_updated`, and no
`save_post` actions), the REST actions with the alt text between, then
`wp_after_insert_post` and the sizes. An edit runs the same filter, then
`wp_update_post`, which saves an attachment through `wp_insert_attachment`
with its stored fields, and prepares its response twice as the reference
does.

Addresses follow the reference's link functions (`Runtime\PostLinks`, probe
`permalinks`, 31 rows): a post through `pre_post_link` and `post_link` (plain
while a draft, pending or scheduled), a page from `get_page_uri` through
`_get_page_link` and `page_link`, an attachment under its parent's address or
its own, a plugin's type through `post_type_link`; `$leavename` keeps the
slug as `%postname%`, `%pagename%` or `%{type}%`. `get_sample_permalink`
shows a draft as published, its name made unique (`wp_unique_post_slug`, now
with `pre_wp_unique_post_slug` and the bad-slug filters, and leaving drafts'
slugs alone), a page's parents written in through `editable_slug`; a REST
response's `permalink_template` and `generated_slug` come from it.

`tests/tools/coverage.php` measures coverage from the code: the reference's
functions implemented, constant, placeholder or missing (split by
`wp-includes` and `wp-admin`), the same for what a site's plugins call (from
`minn_runtime_symbols`), how many of those a probe verifies, and the
fixed-name hooks Minn fires.

## Options and settings plugins can change (2026-10-06)

`sanitize_option` cleans a core option by the reference's rules
(`Runtime\OptionSanitizer`, probe `sanitize-option`, 18 values through 54
options):
- counts become whole numbers, and page sizes become 1 when empty;
- the site's name and tagline are escaped;
- formats and mail settings are stripped of markup;
- an address that is not an address (admin email, site and home URL, a
  timezone that does not exist, a custom structure with no tag) keeps the
  old value and records a settings error in the reference's wording;
- word and domain lists are split and trimmed.

Every option then goes through `sanitize_option_{$option}`. `add_option` and
`update_option` now call it first, before the old value is read, and an
update that finds no option sanitizes again in the add it falls through to.
An option counts as new while it reads as its default
(`default_option_{$option}` compared with `get_option`), unless a read
already found it unset. The engine's own `pre_add_option_*` filter, which the
reference does not have, is gone.

`register_setting` keeps a setting as the reference does
(`Runtime\RegisteredSettings`, probe `rest-settings`):
- the arguments go through `register_setting_args` and over the defaults;
- the sanitize callback is hooked with the value alone;
- a default answers `get_option` through `filter_default_option`;
- an array shown in REST without an items schema gets a `_doing_it_wrong`
  notice.

`unregister_setting` undoes all of this. Core's settings are registered on
`rest_api_init`.

With plugins loaded, `wp/v2/settings` serves every registered setting shown
in REST (`Rest\LiveSettings`), a plugin's beside core's:
- **read:** `rest_pre_get_setting` first, then `get_option` with the
  schema's default; a value its schema refuses reads as null.
- **write:** the body's values are validated against each setting's schema
  (null always passes), then each goes to `rest_pre_update_setting`, then to
  `update_option`. Null deletes the option, unless what is stored does not
  fit the schema, which is a 500 `rest_invalid_stored_value`.

Without plugins the fixed table in `Rest\Settings` still answers. The route's
OPTIONS schema is not served yet.

## Helpers plugins call (2026-10-06)

Helpers that once returned a constant now do what the reference does (probe
`plugin-helpers`):
- **Switches.** `wp_defer_term_counting`, `wp_defer_comment_counting` and
  `wp_suspend_cache_addition` remember what they are told for the request
  (`Runtime\Deferrals`). A non-boolean only asks. Turning deferral off counts
  what was put off, and `wp_cache_add` is refused while additions are
  suspended.
- **Timer and memory.** `timer_start` sets `$timestart` for `timer_stop`.
  `wp_raise_memory_limit` raises PHP's limit, never lowers it: to what
  `{$context}_memory_limit` asks when that is unlimited or higher than both
  the most WordPress allows and the current limit, otherwise to the most
  WordPress allows.
- **Fetching and embeds.** `wp_remote_fopen` returns the body from
  `wp_safe_remote_get` (ten seconds), whatever the status.
  `wp_oembed_get` goes through `WP_oEmbed` (`pre_oembed_result`, then the
  provider).
- **Icons.** `wp_mime_type_icon` reads the icon folders once a request,
  preferring the first caller's kind of file (`Media\Icons`), then finds the
  first name the type answers to. For an attachment that is its extension
  and the kind of file that is. Otherwise it is the type, either half of it,
  or the type with an underscore for the slash.
- **Meta cache.** `update_meta_cache` reads the uncached objects' meta in one
  query into the cache and answers by id.
- **Custom logo.** `get_custom_logo` (`Front\CustomLogo`) links the
  `custom_logo` image home, `aria-current` on the front page. Its alt is the
  image's own or the site's name; a theme that unlinks the home logo gets a
  span with an empty alt there. An image with `loading` set false now takes
  `fetchpriority="high"` only when it is large enough
  (`wp_min_priority_img_pixels`).
- **REST fields.** `rest_filter_response_fields` cuts an answer to `_fields`
  (each item of a list on its own) and is hooked on `rest_post_dispatch`
  when the server starts. A plugin route's answer now keeps only its
  `_fields`, as the engine's own routes always have; the engine skips the
  filter where it has already done the work (`Hooks::hasBeyond`,
  `filterWithout`).

## Missing core routes: statuses (2026-10-06)

Comparing the engine's REST index with the reference's showed 70 core routes
the engine did not serve. The first is `wp/v2/statuses`
(`Rest\StatusesController`, probe `rest-statuses`):
- Every status that is not internal is listed, a plugin's beside core's, and
  trash comes last.
- A visitor sees the public ones. Someone who can edit a type shown in REST
  sees them all, and only they may ask for the list in the edit context.
- Each status links to its posts and goes through `rest_prepare_status`.
- Like `wp/v2/types`, `_fields` over the keyed list keeps nothing over HTTP.

A registered status's `date_floating` now defaults to false, as on the
reference.

An in-process call (`rest_do_request` reaching an engine route) now passes
its own `WP_REST_Request` through: the server sets the route's URL
parameters, defaults and attributes on that object, as the reference's
dispatch does, and the `rest_prepare_*` filters are handed that request.
Before, plugins got an empty request with no context.

## Themes over REST, and theme supports (2026-10-06)

`wp/v2/themes` (`Rest\InstalledThemesController`, probe `rest-themes`, also
compared over HTTP) lists the installed themes, or those with a given
status. Each theme carries its `style.css` headers, raw and rendered, where
it lives, and whether it is a block theme. The active theme also carries
its supports as the editor reads them, the template types and part areas it
may define, and a link to the theme export. Any theme with user styles links
to them.
- **Permissions.** Anyone who can edit a type shown in REST may read the
  active theme; listing the others takes `switch_themes`.
- **Tags.** Feature tags read as their names only in the admin; REST returns
  them as written, as the reference does when wp-admin's feature list is
  not loaded.
- **Runtime.** The route is served with plugins loaded, since a theme's
  supports come from its own code.

Theme supports now behave as the reference keeps them (`Runtime\ThemeSupports`):
- **Declaring.** A bare feature is true, otherwise its arguments.
  - html5 lists add up, repeats included.
  - Post thumbnails stay on for every type once they are on.
  - Post formats keep only real formats.
  - The custom logo fills in its defaults at once, flexible both ways when
    declared bare.
  - The custom header and background get their defaults on `wp_loaded`, and
    a header without a width or height is flexible that way.
  - `title-tag` declared after loading is refused.
- **Block themes.** A block theme's supports are added on
  `after_setup_theme` (priority 1): thumbnails, responsive embeds, editor
  styles, html5 and feed links, plus block templates and the widgets block
  editor. They were previously implied at read time, and that list wrongly
  included `title-tag`, `wp-block-styles` and `align-wide`.
- **Feature registry.** `register_theme_feature` and
  `get_registered_theme_features` exist, with core's features as data
  (`data/theme-features.json`). REST shapes each feature by its schema, or
  shows the default when the theme does not support it.

`get_default_block_template_types` and `get_allowed_block_template_part_areas`
are implemented, and `WP_Theme::display` links the author as the reference
does.

The api probe runtime now runs the theme's setup as a request's boot does:
`setup_theme`, the theme's `functions.php`, `after_setup_theme`, then
`wp_loaded`. It still skips `init`, whose work the probes compare
separately.

## Block types and the block renderer over REST (2026-10-06)

`wp/v2/block-types` (`Rest\BlockTypesController`, probe `rest-block-types`,
compared over HTTP as well) lists every registered block type, core's and a
plugin's, in registration order, or one namespace's share.
- **Who may read it.** Anyone who can edit a type shown in REST
  (`Caller::editsAnyRestType`, now shared with statuses and themes).
- **What each item carries.** Its registration; its styles (its own, then
  those registered for it, repeats kept); its variations as built when
  asked; and a link to the renderer when it is dynamic.
- **Template-part variations.** These are now built from the active theme
  (`build_template_part_block_variations`, `Blocks\TemplatePartVariations`),
  one per area that has parts and then one per part, replacing a captured
  snapshot of one theme's parts. Building them reads the theme's data, and
  reading the theme's data now registers the theme's block style partials
  (`styles/**/*.json` that name `blockTypes`) as block styles, once a
  request, as the reference does. So, as on the reference, only blocks listed
  after the template part carry those styles in the list.
- **Theme style variations.** `WP_Theme_JSON_Resolver::get_style_variations`
  exists for both the 'theme' and 'block' scopes.

`wp/v2/block-renderer` (`Rest\BlockRendererController`, probe
`rest-block-renderer`), the route the editor's ServerSideRender uses:
- **Rendering.** A dynamic block is rendered from the attributes sent (query
  or body), checked as a closed object against the block's attributes and
  filled with their defaults.
- **A named post.** It becomes the global post, and supplies the block's
  `postId` and `postType` context. The previous global post is put back
  afterwards.
- **Context.** Only the edit context is accepted, and sending none counts as
  the view context, which is refused, as on the reference.
- **Order of checks.** Arguments are judged before the caller.

The archives block now shows post counts (`showPostCounts`). Its other
options (dropdown, type, wrapper attributes) are still not rendered.

Not matched: for an unknown block type the reference omits the `Allow`
header, because its permission check is what fails.

## Template lookups, and order fixes the HTTP comparisons found (2026-10-06)

`get_template_hierarchy` (`Theme\TemplateHierarchy`, probe
`rest-templates-lookup`, 56 slugs) builds the fallback chain for a slug: the
slug, then its family's chain down to index.
- A single entry's slug reaches `single-{type}` and `single` only when the
  type is registered; otherwise it goes straight to `singular`.
- A taxonomy's slug reaches `taxonomy-{tax}` and `taxonomy` only when the
  taxonomy is registered.
- Pages, categories, tags and authors step through their bare name when
  the slug has a suffix.
- A prefix names the next step outright, and a custom template follows a
  page's chain.

`wp/v2/templates/lookup` answers with the first template in that chain that
exists. `wp/v2/template-parts/lookup` searches templates too, as the
reference's does, and shows what it finds as a part would be shown
(`TemplateObject::view` takes the route's type).

Comparing over HTTP found three differences that had been there all along;
PHP's `==` on arrays ignores key order, so the fixtures never caught them.
They are now fixed:
- A post's `sticky` and `format` sit among the shared fields
  (`ping_status`, `sticky`, `template`, `format`, `meta`).
- The edit context's action links follow the reference's insertion order
  (publish, unfiltered-html, sticky, assign-author, then create and assign
  for each taxonomy), not alphabetical order.
- The `Allow` header no longer includes the `{id}` routes on a path that a
  route without captures names exactly (`/templates/lookup`).

Creating a pattern (`POST wp/v2/blocks`) now states its policy
(`publish_posts`, a pattern's `create_posts`), so the list's `Allow` header
offers POST as the reference's does. The layout container hash
(`wp-container-core-*-is-layout-{hash}`) remains engine-defined
(contracts/blocks.md).

## oEmbed (2026-10-06)

The engine already printed oEmbed discovery links pointing at
`oembed/1.0/embed`, a route it did not serve. Now (`Rest\OEmbedController`,
`Front\PostEmbed`, probe `oembed`, compared over HTTP as well):
- **embed.** Answers one of the site's own addresses with its embed data:
  version, the site as provider, the author (or else the site), the title,
  and through the default `oembed_response_data` filter the rich markup.
  That markup is a blockquote, a sandboxed iframe of the post's `/embed/`
  page with a fresh secret, and the sizing script (`assets/wp-embed.min.js`,
  captured). The width is held between 200 and 600 and the height is a 16:9
  share of it, at least 200. Anything else is "Not Found". `format=xml` is
  served as XML on the way out (`RuntimeRoutes::serve`, the reference's
  `_oembed_rest_pre_serve_request`, skipped as a hook the engine does itself).
- **proxy.** Serves the editor, for anyone who can edit posts. The site's own
  addresses are answered locally; anything else is fetched through
  `WP_oEmbed` at the editor's size and kept in a transient for a day under
  the request's arguments. A video or rich provider's iframe gets the title
  its data names (`wp_filter_oembed_iframe_title_attribute` on
  `oembed_dataparse`).
- **Namespace index.** The index route takes dotted namespaces
  (`oembed/1.0`).

`wp/v2/menu-locations` (probe `rest-menu-locations`) lists the locations a
theme or plugin registers (it always answered empty before) with the menu
assigned to each, and `menu-locations/{location}` answers one. Both require
`edit_theme_options` as on the reference: editors had been let through.

## Widgets over REST (2026-10-06)

minn-admin's widgets screen calls `wp/v2/sidebars`, `wp/v2/widgets` and
`wp/v2/widget-types`, which the engine did not serve. Now (probes
`widget-helpers` and `rest-widgets`, minn-admin's own requests compared
over HTTP):
- **Settings forms.** Every core widget's settings form is printed as the
  reference prints it (`Widgets\WidgetForms`; the media widgets' hidden
  instance fields come from their schema), empty and filled. The forms
  were missing before, so `rendered_form` would have been empty.
- **Helper functions.** The widgets helpers now exist (`Runtime\WidgetAreas`):
  `wp_parse_widget_id`, `wp_get_sidebar`, `wp_find_widgets_sidebar`,
  `wp_assign_widget_to_sidebar`, `wp_render_widget`,
  `wp_render_widget_control` and `retrieve_widgets`.
- **sidebars.** A sidebar is a registered one or the inactive widgets. Its
  status is active only when it is registered under a classic theme; under
  a block theme every sidebar is inactive, as on the reference. Saving a
  sidebar's widgets takes them from any other sidebar and sends the ones it
  drops to the inactive widgets.
- **widget-types.** The registered widgets by id base, in id order. Encode
  returns the form, the preview as `the_widget` shows it, and the settings
  re-encoded.
- **widgets.** The registered widgets, sidebar by sidebar. A new widget
  takes its type's next number. Settings are saved through the widget's own
  `update` (raw, encoded with a matching hash, or from its form's fields)
  and the widget is registered at once. Deleting with force removes it,
  answering with the deleted widget's links; without force it goes to the
  inactive widgets.
- **Runtime.** A runtime that stopped before `init` registers the widgets
  when one of these routes is asked.

Not served yet: `widget-types/{id}/render`, which answers with a whole
preview page.

A plugin's route now answers with the `Allow` header, as the reference's
`rest_send_allow_header` sets it: every method of the route whose handler's
permission callback lets the request through. Engine routes always had it;
plugin routes had none.

## Batch requests (2026-10-06)

`batch/v1` (`Rest\BatchController`, probe `rest-batch`, compared over HTTP as
well) carries up to 25 writes in one request, each answered in turn as
`{body, status, headers}`, the whole as 207. Each request is answered as it
would be on its own, as the same caller.
- **Which routes take part.** Posts, pages and the other post types shown in
  REST (not media, templates, global styles or fonts), the taxonomies,
  users, widgets, and a plugin's routes that opt in with `allow_batch`.
- **Refusals.** Any other route answers `rest_batch_not_allowed` with its
  `Allow` header; a path with no route answers `rest_no_route`.
- **require-all-validate.** Judges every request's declared arguments first,
  and one failure answers the whole batch with the failures alone (null for
  the rest), nothing written. Engine routes that check arguments in their
  handler (post writes) are not caught by this check; their requests still
  fail on their own when dispatched.

Hand-written `rest_invalid_param` errors for an enum (post status, plugin
status, search type and subtype) now carry `details`, as
`WP_REST_Request::has_valid_params` reports them (`RestError::invalidParam`).

## What a save fills in, slugs beside each other, and addresses by status (2026-10-06)

The round trips found rows the engine wrote differently from WordPress. The
probes `insert-defaults`, `permalinks`, `registry-rewrites` and
`query-args` capture the rules, and the engine now follows them.
- **Comment and ping status.** A new post that names neither takes its
  type's default (`get_default_comment_status`), not the site's. A page
  starts closed. So does a type without comments (or trackbacks, for
  pings): a revision, an attachment's ping status, a plugin's type without
  that support.
- **Which slugs may stand together** (`Content\PostSlugs`).
  - A flat type's slugs are its own. A post, a page and a plugin's type may
    share one.
  - A hierarchical type's slugs are its own and its attachments', under the
    same parent.
  - An attachment's slugs are every post's.
  - Never free: the feed names and `embed`. For a hierarchical type, also a
    number or a page number (`2`, `page2`).
  - For posts, a number a date archive would answer to is never free. That
    is any number when the structure begins with `%postname%`, below 13
    after `%year%`, and below 32 after `%monthnum%`. A post keeps a number
    it already has.
  - A taken slug gets the first free `-2` form, cut and stripped of a
    trailing hyphen to fit 200 characters.
  - Drafts, pending posts, auto-drafts, revisions and personal data
    requests keep their slug as it is. A pending post from someone who may
    not publish it keeps none.
  - `wp_unique_post_slug` takes the slug as given, without sanitizing it.
  - Before this, slugs were unique across the whole table.
- **Column lengths.** The posts table's short text columns are cut to fit,
  as the reference's database layer cuts them. Before, a 200-character slug
  with a suffix failed the insert.
- **guid.** A new post without one takes its address as it stands at
  insert. That is pretty when live, and `?p=`, `?page_id=` or
  `?post_type=…&p=` when not.
  - An update keeps the guid the post had, in its display form (through
    `post_guid`, whose default is `esc_url`, so `&` reads `&#038;`). A new
    guid asked for in an update is ignored.
  - The engine was missing the `post_guid` and `the_guid` defaults
    (`esc_url`).
- **get_post_field** runs the column through `sanitize_post_field` in the
  context asked for (probe `post-field`), `display` by default. It used to
  return the raw value whatever the context.
- **Plain addresses by status.** A post's address is plain unless its
  status is public, or private and readable by whoever asks.
  - So a private post is plain to a visitor, and so are a trashed post, a
    revision, and a plugin's protected or bare status.
  - A sample (the editor's preview address) is pretty unless its status is
    internal; a trashed post and a revision stay plain.
- **A plugin type's address** follows the address pattern its rewrite
  registered (`get_extra_permastruct`), with the front of the structure
  unless it opts out. A type without a pattern but with a query var
  answers `?{query_var}={slug}`, otherwise `?post_type=…&p=`.
- **Registered rewrites.**
  - A post type's or taxonomy's rewrite settles into its full form whether
    or not it is public or queryable, but only with pretty permalinks or in
    the admin; otherwise it stays as given.
  - A post type's feeds follow `has_archive`.
  - A taxonomy's rewrite is merged over its defaults. Given as `true`, it
    keeps the reference's empty `"0"` entry.
  - A post type's query var is its name unless turned off. A taxonomy's is
    off unless it is queryable.
  - Each registers its pattern on registration and drops it on
    unregistration.
  - The built-in patterns are set once, so a structure set later leaves
    them, and a plugin's, as they were.
- **add_query_arg.**
  - A URI that is only a query, or nothing, keeps its question mark.
  - An empty value leaves its key bare (`?c`), and null leaves it out.
  - Keys are written as given, in `build_query` too.

A response's headers now keep the order they were set in
(`Response::withHeader` appends), as the reference's do. That order shows
in a batch's envelopes: `Location`, then `Allow`.

## OPTIONS requests (2026-10-06)

An OPTIONS request to an engine route is now answered as the reference
answers it: 200 with the route's description, whoever asks, and the
caller's `Allow` header. Before, the engine answered 404. The block
editor's `canUser` reads that header, so permission checks there read
nothing.
- **The description.** It comes from the engine's catalogue of its own
  routes (`Rest\RouteCatalogue`, shared with the index): the first route
  whose pattern takes the path. A path no route takes gets an empty
  description.
  - Not yet the reference's whole description: the endpoints are not split
    per method group, the arguments are the engine's own, and there is no
    `schema`. Those need the reference's route schemas captured.
- **A plugin's route.** Its description (the runtime's
  `rest_handle_options_request`) now carries the `Allow` header too. The
  runtime no longer answers a path it does not know, so the engine's
  routes, such as the namespace index, are described.
- **DELETE `users/me`** is served, deleting the signed-in user as
  `users/{id}` deletes any.
- **`reassign`** is judged as the reference's own sanitizer judges it: empty,
  `false` or a number pass, anything else is refused with "Invalid user
  parameter(s).". The integer type is not checked.

The `allow` suite compares OPTIONS on every read route, for each caller.
Eight of those cases share a divergence with the matching GET (B1o).

## Placeholders plugins call, made real (2026-10-06)

The coverage report's most-used gaps were placeholders. Two probes,
`placeholders-a` and `placeholders-admin`, capture what the reference does,
and the engine now does it.
- **wp_debug_backtrace_summary** (`Support\Backtrace`). Frames are named
  `Class->method`, `Class::method`, the function's name, a hook's function
  with the hook (`do_action('init')`), or an included file by its path with
  the content folder, then the install's path, taken off. Outermost first as
  a string, innermost first as a list.
- **wp_link_pages and _wp_link_page** (`Front\PageLinks`), by number or by
  next and previous. The next and previous links show only where the whole
  post is shown (`$more`). A page's address is `/2/` after the post's,
  `?page=2` without pretty permalinks or for a draft. The filters
  `wp_link_pages_args`, `wp_link_pages_link` and `wp_link_pages` run (the
  last with the arguments as given). The output is echoed and returned.
- **Locale switching** (`WP_Locale_Switcher`, the `$wp_locale_switcher`
  global).
  - Switching to the locale already in force, or one not installed, is
    refused. Switches nest.
  - A switch fires `change_locale` then `switch_locale` with the user whose
    locale it is. A restore fires `change_locale` then
    `restore_previous_locale`.
  - `restore_current_locale` restores from the site's own locale.
  - `get_user_locale` reads the user's own locale (it returned the site's).
  - The probe's capture needs a `de_DE.mo` installed before the reference
    loads (the engine's replay makes its own).
- **Comments to spam, trash and back.** `wp_spam_comment` and
  `wp_trash_comment` keep the status aside (`_wp_trash_meta_status`, with
  the time) between `spam_comment`/`spammed_comment` or
  `trash_comment`/`trashed_comment`. `wp_unspam_comment` and
  `wp_untrash_comment` restore it between their own two actions. Before,
  spam kept nothing aside and no action fired.
- **Small helpers.**
  - `_count_posts_cache_key` gets a per-user suffix for a user who may not
    read private posts.
  - `_get_meta_table`.
  - The social link services (`data/social-link-services.json`, 49 as
    captured).
  - `get_default_block_categories`, and `get_block_categories` with
    `block_categories_all` and a real `WP_Block_Editor_Context`.
  - The PHP 8.4 array functions on older PHP.
- **Page templates.**
  - `WP_Theme::get_post_templates` lists a theme's PHP files one folder deep
    with a Template Name header, for its Template Post Type types (pages by
    default).
  - Where the site's theme makes templates of blocks, the custom templates
    its theme.json names are listed too.
  - `get_page_templates` runs through `theme_templates` and
    `theme_{type}_templates`.
- **Admin helpers.**
  - Lists: `_draft_or_post_title`, `get_post_states` and `_post_states`,
    `_admin_search_query`.
  - Screens and meta boxes: `get_hidden_meta_boxes` (eight defaults for
    posts, pages and attachments, the slug box for another type),
    `postbox_classes`, `get_column_headers`.
  - Importers: `register_importer`, `get_importers` (sorted by name).
  - `wp_add_privacy_policy_content`: only from admin_init in the admin;
    otherwise it is doing it wrong.
  - `get_inline_data`, and `enqueue_comment_hotkeys_js`.
- **Fixes the probes found along the way.**
  - **Admin screens.** `WP_Screen` names a registered post type's editor,
    its list, and a taxonomy's list (`edit-tags`). Before, it knew only post
    and page.
  - **Private posts.** A private post keeps no password.
  - **Database table names.** `$wpdb` names the network tables only on a
    multisite install, and `tables('all')` lists the global tables first.
  - **Early enqueues.** A script or style enqueued before it is registered
    waits, and joins the queue when it is registered. Before, an
    unregistered handle counted as enqueued.
- **Not done.** The style engine (`wp_style_engine_get_styles` and the
  stylesheet by context) is still a placeholder and needs its own unit.

The handles the reference registers itself, 234 scripts and 305 styles
(`data/default-assets.json`), count as registered. Enqueuing one queues it,
and its default dependencies count when asking whether something is
enqueued, even where the engine ships no file for it. Only a handle that is
neither registered nor one of these waits for its registration.
- **Printing.** One of these handles prints nothing itself, but it stands as
  a dependency. So a plugin's script that depends on it prints, as it does on
  the reference. Before, such a script never printed. (Where the engine
  ships no copy of that library, the script prints without it.)

## The style engine (2026-10-06)

`wp_style_engine_get_styles`, `wp_style_engine_get_stylesheet_from_css_rules`
and `wp_style_engine_get_stylesheet_from_context` work as the reference's do
(probe `style-engine`). They were placeholders.
- **Parsing.** A block's style object is parsed (`Blocks\StyleEngine`) by
  the reference's own definitions table,
  `WP_Style_Engine::BLOCK_STYLE_DEFINITIONS_METADATA`, captured with the
  inventory. The groups and properties go in its order.
  - A value goes into the declarations as given.
  - A preset becomes a custom property only where its definition names one
    for that kind. Turning presets into class names drops them from the
    CSS.
  - Sides and corners name their own properties.
  - A colour, a font size or family, a border colour, a gradient and an
    aspect ratio earn their class names.
- **CSS.** Only string values make CSS: a number stays in the declarations
  without CSS. A rule with a selector is kept in a context's store
  (`WP_Style_Engine_CSS_Rules_Store`).
- **Stylesheets.** The processor (`WP_Style_Engine_Processor`) merges a
  selector's rules and writes rules groups around them. Prettified, the
  rules are indented and nested.
- **Fixes.** `WP_Style_Engine_CSS_Declarations` now makes CSS of string
  values only, and a grouped rule's prettified CSS nests a level deeper.

## Editor styles, the global stylesheet, and types in the edit context (2026-10-06)

Functions the engine answered with a constant, now as the reference answers
them (probe `editor-styles`).
- **Privacy policy.** `get_privacy_policy_url`,
  `get_the_privacy_policy_link` and `the_privacy_policy_link`. The link
  needs a published page with a title.
- **Small functions.**
  - `get_post_timestamp`, and `is_rtl` from the locale.
  - `rest_parse_embed_param`.
  - The editor stylesheets: `add_editor_style`, `get_editor_stylesheets`
    (external addresses first, then the theme's files that exist) and
    `remove_editor_styles`.
  - `search_theme_directories`, over the theme folder now registered at
    boot (`$wp_theme_directories`).
- **wp_enqueue_block_style.** Where core blocks load their assets one by
  one, the style is registered and enqueued when the block renders.
- **rest_preload_api_request.** Only a 200 is kept, its data through
  `rest_post_dispatch`, with OPTIONS answers under their own key.
- **In-process engine answers.** A `rest_do_request` to an engine route
  now carries the route's headers (`Allow` among them) and not the
  transport's. The facade server reads the engine's route table off the API
  where no web request handed it over (WP-CLI, probes), so OPTIONS reach the
  engine there too.
- **wp_get_global_stylesheet** writes the variables, the styles and the
  preset classes by type, byte for byte as the reference does for the test
  theme. Before, it returned nothing.
  - The styles carry every block's rules, in the reference's merged order:
    its own theme.json and the blocks' defaults first
    (`data/global-styles.json`), then the theme's.
  - Fixed for the page's stylesheet too:
    - A block's features with a selector of their own in its metadata (the
      avatar's border on `.wp-block-avatar img`) get their own rule.
    - Block and element CSS is split at each ampersand. A nested selector
      starting with a space is scoped to every comma part of the block's
      selector; any other is appended; a pseudo element goes outside
      `:where()`.
    - An element's own CSS is scoped to it.
    - The dimensions (`width`, `height`, `aspectRatio`, `minWidth`) print.
- **An untitled post that goes live** takes its id for a slug, made free in
  its scope (so `{id}-2` where numbers are reserved), in both the facade's
  save and REST's.

wp/v2/types serves the edit context (probe `rest-types-edit`):
- **Edit fields.** Each type adds its capabilities, visibility,
  viewability, labels and supports there.
- **Who may ask.** The list leaves out the types the caller may not edit
  and refuses one who may edit none (`rest_cannot_view`). A single type
  refuses outright (`rest_forbidden_context`, 401 signed out, 403 signed in).
- **Embed context.** A type shows its name, slug, icon, REST base and
  namespace, and template.
- **Plugin types.** The list now includes the types plugin code registers
  to show in REST, which it lacked before (on a WooCommerce site,
  `product`).
- **Fixes along the way.**
  - The post and page `editor` support carries `notes`.
  - A hierarchical plugin type starts from the page labels.
  - A plugin type's labels gain `template_name`.
- **Plugin type routes.** A plugin type's own REST routes (`/wp/v2/product`)
  were not served when this was written; they are now (probe
  `rest-plugin-types`, below).

A plugin's post types and taxonomies are served over REST with the
default controllers' shape (probe `rest-plugin-types`):
- **Routes.** A type or taxonomy registered to show in REST under wp/v2 is
  served at its REST base: list, single, create, update, trash and delete.
  One kept out of REST, or in another namespace, is not served under wp/v2.
- **Fields follow supports.** Title, editor, excerpt, author, thumbnail,
  comments, custom fields, page attributes and revisions each add their
  fields and links. Taxonomies appear under their REST bases, and
  registered meta under `meta`, cast to its type.
- **Edit links.** `assign-author` only with author support. Each taxonomy
  adds its create and assign actions (create needs `edit_terms` when
  hierarchical, `assign_terms` otherwise).
- **Writes.** A create or update sets the terms named under each
  taxonomy's REST base and the registered meta.
- **Lists.** Filter by each taxonomy's REST base; the terms routes filter
  by `post`.
- **Links.** A plugin type's plain link is `?post_type=X&p=ID`. A term link
  nests its parents only for categories, tags, or a hierarchical rewrite.
- **Fixes along the way.**
  - A trashed post's content renders in the edit context, as it does in
    the reference (the engine left it empty).
  - A delete's `previous` leaves out `_links`, for posts, terms, users,
    media and comments.
  - Query values plugin code sets as numbers or booleans on an
    in-process request are read as the text a query string carries.
- **Not done.** A type registered under its own namespace (`zz/v1`) is
  not served there yet.

Fields plugin code adds with register_rest_field are served as the
reference serves them, over HTTP as well as in process (probe
`rest-fields`). Before, they rode only on in-process answers, after
`_links`, and were never written.
- **Where they sit.** After the item's own fields, before its links.
- **What a get callback is handed.** The item so far, without links and
  cut to the fields asked for (id kept; in embed context, the embed
  shape); the field's name; the request, carrying its context; and the
  object type.
- **When a field is left out.** When `_fields` does not name it, or its
  schema's context does not include the request's.
- **The context.** A read answers in the one asked for, or view. A write
  or delete answers in edit, except a term's delete, in view.
- **Writes.** A write runs the update callbacks for the fields its body
  names, with the saved object, before `rest_after_insert_*`. An error one
  returns is the write's answer. This covers posts, media, terms, users
  and comments.
- **Embedded items** are prepared answering their own request, in embed
  context.

A post's latest revision counts its autosave (probe
`rest-latest-revision`). `predecessor-version` names the newest revision by
date, then id, autosave or not, and the revisions route lists autosaves too.
The engine had left autosaves out and ordered by id.

The footnotes meta is registered as the reference registers it (probe
`meta-registry`, its footnotes rows). `register_block_core_footnotes_post_meta`
runs at load and again on init at 20, so a plugin's type gets it too. It
covers every type shown in REST that supports the editor, custom fields and
revisions, with `__return_true` for its auth. Templates, template parts,
navigation and global styles no longer get it.

The meta registry works as the reference's does (probe `meta-api`):
- **Where it lives.** Keys are kept in `$wp_meta_keys`.
- **Defaults.** A key's sanitize and auth callbacks hang on their filters,
  with `_for_{subtype}` for a subtype's key. A key with no auth callback
  gets `__return_true`, or `__return_false` when protected.
- **Refusals.** A registration is refused, with the reference's notice, in
  three cases. The first two are checked before anything is hooked: an
  array shown in REST without its items' schema, and revisions where the
  type or subtype has none. The third is checked after: a default its
  schema does not accept. The old form (callbacks as plain arguments)
  hooks them and returns false.
- **Defaults on read.** A default is served through `filter_default_metadata`.
  `get_metadata_default` wraps a non-single default in a list.
- **New functions.** `register_term_meta`, `unregister_term_meta`,
  `get_registered_metadata` and `get_object_subtype`.
- **Sanitizing.** `sanitize_meta` prefers the subtype's filter, and writes
  pass the object's subtype.
- **Updates.** `update_metadata` adding a new key hands `add_metadata` the
  value as it was given, so it is sanitized once, not twice.
- **Capabilities.** Editing, adding or deleting a post's, comment's,
  term's or user's meta needs what editing the object needs. When the key
  is not allowed (protected, or refused by its auth filter), it needs the
  meta capability itself, which no role has.

Core's own meta is registered on init, as the reference registers it
(probe `meta-registry`): a pattern's sync status, a note's status, and the
editor's persisted preferences, with the footnotes at 20. The probe boot,
which runs no init, registers it directly, with minn-admin's toolbar key.

An object's meta over REST comes from the registered keys (probe
`rest-meta`). Before, it was a fixed list: footnotes, the toolbar key, a
note's status, or nothing.
- **Which keys.** Each key answers under its REST name: those for every
  subtype, then the object's subtype's. A key is left out of a context its
  schema does not name.
- **Reading a value.** A value is checked against its schema: null when it
  does not fit, the type's empty value for '' or when nothing is stored.
  Otherwise it is cast, or handed to the key's prepare callback.
- **Writes.** They go key by key in registration order:
  - null deletes;
  - a value the schema refuses is a 400 naming `meta.{key}`;
  - a key the user may not edit is a 403, after an unchanged value is left
    alone;
  - a list keeps the values already stored once.
- **Errors.** They are gathered: the first answers, the rest ride beside it.
- **Scope.** This covers posts, pages, plugin types (when they support
  custom fields), media, terms, users and comments.
- **A non-object.** A post write whose `meta` is not an object is refused
  before anything is written.
- **In process,** `_fields` narrows the item to whole top-level fields,
  with id kept, as `rest_do_request` does on the reference. Over HTTP the
  nested names still narrow the answer.

A REST request over HTTP now leaves what the reference's request parsing
leaves before `rest_api_init` (suite `hook-trace`, its rest-request
record). `$wp->query_vars['rest_route']` names the route, and
`REST_REQUEST` is defined, so `wp_is_serving_rest_request()` is true.
Plugins that read these (ACF registers its field by them) see a REST
request. Before, they saw neither.

A plugin's types and taxonomies are guarded by their own capabilities
(probe `rest-plugin-caps`). Before, they took the post and category
family's: an author could create, publish and delete posts of a type with
its own capability type (a store's products), and anyone with edit_posts
could add terms to a plugin's taxonomy.
- **Post types.** A type's capability names come from its post type object
  (create_posts, edit_posts, publish_posts...). A type that maps no meta
  capabilities asks for its primitive one.
- **Mapping meta capabilities.** It defaults on only for the post and page
  families, as on the reference.
- **Taxonomies.** edit_term, delete_term and assign_term map through the
  taxonomy's own term capabilities, and a default term is never deleted.
  Creating a term needs edit_terms when the taxonomy is hierarchical,
  assign_terms when flat. Reading in the edit context and updating need
  edit_term; deleting needs delete_term.
- **Allow.** The routes of a plugin's types and taxonomies judge their
  writes in the gate (a missing object 404 first, then the caller). Allow
  now lists only what the caller may do, so a visitor sees GET.
- **wp_insert_post** no longer turns a publish into pending for a user who
  may not publish. The reference publishes; only REST refuses.

Term reads go through the term filters plugins hook (probe
`rest-term-filters`). Each term passes get_term and get_{taxonomy}; a list
also passes get_terms, with the query's arguments. A store's category
counts (WooCommerce counts only visible products there) now match.
- **Class lists.** A post's class list passes post_class
  (WooCommerce drops hentry and adds a product's classes).
- **Not done.** get_terms_args and terms_clauses wait for the term query's
  SQL to be the reference's.

The abilities API works as the reference's does (probe `abilities-registry`):
- **Registration.** Categories register only on
  `wp_abilities_api_categories_init`, abilities only on
  `wp_abilities_api_init`. Each action fires once, on the first lookup of
  its kind, categories first. Before, categories registered on the
  abilities action, so the categories that plugins register on their own
  action never existed.
- **Return values.** A registration returns a `WP_Ability_Category` or a
  `WP_Ability`, and unregistering returns what was removed.
- **Refusals.** These come with the reference's notices: outside the
  action, a duplicate, a malformed slug or name, a missing label,
  description, category or callback, an unknown category.
- **Meta.** An ability's meta gains the three annotations, show_in_rest
  and public.
- **Running.** Input is checked against the input schema (input given to
  an ability without one is refused). Then the permission callback must
  answer true. Then come `wp_before_execute_ability`, the callback, the
  output checked against the output schema, and
  `wp_after_execute_ability`.
- **Over REST.** Only abilities shown in REST are listed or run. No input
  is null, not an empty object. Errors keep their status, invalid input
  is a 400, and the update method message is the reference's.

An image's thumbnail, when no thumbnail size was made, is found as the
reference finds it (probe `image-downsize`).
- **image_downsize.** It falls back to the old `thumb` file beside the
  image, while that file is there, and otherwise to the image itself. The
  image's dimensions are fitted to the thumbnail box, and it is marked not
  intermediate.
- **wp_get_attachment_thumb_url** answers through
  `wp_get_attachment_image_url`.
- **The bug this fixes.** The two had called each other without end for
  any image lacking a thumbnail size. A REST read that touched one, such as
  minn-admin's ACF field on a page linking such images, grew until it hit
  the memory limit.

WP_Meta_Query, WP_Tax_Query and WP_Date_Query hand back the reference's
SQL byte for byte (probe `query-clauses`). Plugins embed these fragments in
their own statements, and some edit them as strings.
- **Layout.** Every group is wrapped at every depth, each clause and
  relation on its own line, indented two spaces a level. Joins are
  separated as the reference separates them.
- **Meta queries.**
  - `queries` keeps each clause as given; a lone clause relates by OR.
  - The builder settles compare and compare_key, and `get_clauses` lists
    each clause as settled, then its alias and cast.
  - `has_or_relation` needs two or more clauses under OR.
- **Tax queries.**
  - NOT IN, AND and EXISTS read the reference's tab-laid subqueries.
  - Term ids are listed in ascending order.
  - `queried_terms` names every clause but a NOT IN; an EXISTS names
    only its field.
- **Date queries.**
  - A date-only (or year, month, minute) string bound reaches the end of
    its unit when exclusive.
  - The week counts from the site's first day of the week
    (`_wp_mysql_week`).
  - The ISO day of the week is `WEEKDAY() + 1`.
  - Hour, minute and second together compare as one
    `DATE_FORMAT( col, '%H.%i%s' )` decimal. An hour with a second and no
    minute adds nothing, and a part that builds to zero is left out.
  - Values out of range, and a year, month and day that make no date, earn
    the reference's notices.

## Plugins and themes switched, installed and updated (2026-10-07)

**Activation** (`Runtime\PluginActivation`, `Runtime\PluginRequirements`,
probe plugin-activation). `activate_plugin` checks the plugin header's
Requires PHP, Requires at least and Requires Plugins (a readme.txt is not
read; slugs that are not lower-case slugs are passed over; the rest taken
sorted), loads the plugin through the runtime's gate before
`activate_plugin` so its activation hook runs (silent mode loads it too),
fires `activate_plugin`, `activate_<file>`, writes `active_plugins` sorted
through `update_option`, then `activated_plugin`; what it printed makes the
answer `unexpected_output` with the plugin left active. A plugin the gate
will not load is refused (`plugin_unsupported`, naming what it lacks)
rather than recorded active with code that cannot run. REST answers a
refusal with 500 and the refusal's data under `additional_data`;
`wp plugin activate` warns in WP-CLI's words and boots the runtime.

**Plugin headers** (probe plugin-data). `get_plugin_data` translates (the
plugin's domain loaded from its folder and Domain Path; name, addresses,
version, description and author through it), cuts text to a, abbr,
acronym, code, em and strong, escapes the addresses, and marks up (title
and author linked, the description texturized with its By line). A plugin
in its own folder that names no text domain uses the folder's name. The
REST plugin item is built from the same passes and reads only the plugin
asked for. A domain with nothing found is looked for again after
`load_plugin_textdomain` names its folder; one unloaded for good stays
closed.

**The upgraders** (`Runtime\Upgrade`, `wp-api/classes/WP_Upgrader.php`,
`WP_Upgrader_Skin.php`, probe upgrader). Plugin_Upgrader and
Theme_Upgrader install, overwrite, update and bulk-update in the
reference's order of hooks, words and checks; a single update leaves the
plugin switched off (the caller switches it back), a bulk one keeps it on
behind maintenance mode. The skins that draw wp-admin's screens draw none
of the screen. An update's old copy is set aside while it runs and removed
after: the reference keeps it a week for a cron event and restores it at
shutdown on failure; Minn keeps nothing and restores at once.

**The engine's own installs and updates** (`Ops\UpgraderRun`). The REST
plugin route, Minn Admin's install, upload and update routes, the CLI verbs
and the automatic updates all run the upgraders, so plugins hear every
hook. Uploads pass `Ops\Archive`'s checks first (one plain top folder, no
symbolic links, bounded size); a Minn extension (minn.json) is placed by
the engine. An update applies the offer as Minn Admin does on WordPress
(`bulk_upgrade` for a plugin, `upgrade` for a theme), with the offers
published first in `update_plugins` and `update_themes` (the shape
`wp_update_plugins` leaves) and put back after.

**Minn's own requests.** The update service's packages are fetched over the
engine's own client (https, the service's host, a size cap) and handed to
the upgrader through `upgrader_pre_download`, never through the
plugin-filterable HTTP API, so no plugin can change where Minn gets code
from. A package from anywhere else is installed only on its publisher's
word (an `upgrader_pre_download` answer). The reference tells plugins
about its own requests to wordpress.org (`requests-*`, `http_api_curl`,
`http_api_debug`); the hook-trace suite drops them (DELIBERATE).

**Theme caches.** The reference keeps the theme roots (`theme_roots`) and
each theme's pattern files (`wp_theme_files_patterns-<hash>`) in site
transients, written and cleared as their state allows; Minn reads both from
disk and keeps neither (DELIBERATE in hook-trace; left out of the upgrader
probe).

**Site options** (probe site-options). On a single site the network layer
is options stored not to autoload, with its own hooks around the option
calls: `pre_site_option_<name>` and `pre_site_option` may answer a read,
`default_site_option_<name>` sets its default, `site_option_<name>` has the
last word; writes are told as `add_`, `update_` and `delete_site_option`.
Site transients go through it, the update transients reading no timeout.
Inside an `all` callback the hook is the current one.

## The comment walker (2026-10-07)

`wp_list_comments` now walks the comments with the theme's walker or
`Walker_Comment` (probe comment-walker, 42 cases), so themes that subclass
it (Twenty Nineteen, Twenty Twenty) load and print their own markup. This
closes the gap noted above (html5 only).

**The list.** The arguments settle in the reference's order. A page size
comes from `per_page`, else the `comments_per_page` query variable while
comments are paged; with none there is no paging. The page comes from
`page`, else `cpage`, else the first. A type picks its group from
`separate_comments`, which files a custom type (a review) under its own
name. The query's comments are already its page: they are not paged again,
and their links name the page shown. `max_num_comment_pages` is not
touched.

**The walker.** `Walker::paged_walk` pages the top-level comments,
reversed top level and children as asked, and sets `max_pages`. With every
level flat (-1), each element stands alone. On the last page the replies
of earlier pages' comments are dropped (`unset_children`); comments whose
parent is not in the list print at the top level after the page. A list
with no top-level element is all orphans, unlike `walk`, where the first
element's siblings are the top.

`Walker_Comment` opens levels as `ol`, `ul` or nothing (div) and sets
`comment_depth`. At the depth limit a reply follows its parent on the
parent's level. Each comment is printed by the callback, a short ping line
(`Pingback:`), or the html5 or xhtml markup, whitespace included.

**Held comments.** A held comment shows as a preview to anyone but its
commenter (the commenter cookie): the author is unlinked, `comment_text`
loses its markup at priority 40, and html5 drops the reply link (xhtml
keeps it). The note reads the same either way.

**Links and avatars.** The reply link opens the comments page being shown
(`cpage`); the default page drops its number when the oldest comments come
first. Edit links print for an editor: ` <span class="edit-link">` in
html5 and pings, ` &nbsp;&nbsp;(Edit)` in xhtml. Pings have no avatar:
`is_avatar_comment_type` allows `comment` and whatever
`get_avatar_comment_types` adds, and `get_avatar_data` answers any other
type with `url` false.

**The post's comments** (probe comments-template, 13 cases).
`comments_template` loads what the reference loads: approved comments plus
the visitor's own held ones (the signed-in user, or the unapproved
commenter's email). The query is threaded as the settings say and handed
to `comments_template_query_args`; replies follow each top-level comment,
depth first. Paged, it is one page of top-level comments. With no page
asked for and the newest first, it counts the top-level comments
(`comments_template_top_level_query_args`) to find the last page, then
sets `cpage` to it and `$overridden_cpage`. `max_num_comment_pages` is the
query's page count, 0 when not paged. A post behind a password still loads
its comments: the template refuses it, and the fallback template prints
the reference's note. `COMMENTS_TEMPLATE` is defined. Called with nothing,
`get_comment_pages_count` answers the query's count; it is 1 when comments
are not paged.

## The head of the plugin queue (2026-10-07)

The Sep 1 catalogue report, re-judged against today's gap, put 1,728 of the
top 2,000 plugins through the gate. The first wave of its queue (probe
plugin-queue, 103 cases) takes that to 1,768.

- `get_lastpostdate` and `get_lastpostmodified` read the public types (or one
  type): 'blog' gives the local column, 'gmt' the GMT one, 'server' the GMT
  one with microseconds. Any other timezone or type answers false, still
  filtered. The modified time is the later of the newest modified stamp
  and the newest date, and `pre_get_lastpostmodified` may answer first.
- `wp_restore_post_revision` puts back the revisioned fields (or those
  named) through `wp_update_post`, which saves a revision of its own. It
  sets `_edit_last` and fires `wp_restore_post_revision`.
- `wp_resolve_numeric_slug_conflicts` reads a date archive's year, month or
  day as a post's numeric slug where %postname% stands in the structure.
  That needs the post's date to match the URL's, and what follows to be one
  of its pages. The front calls it before the `request` filter, so `/1987/`
  is a post slugged 1987 (reader suite), not that year's archive.
- `wp_list_authors` lists by name and leaves out the account displayed as
  "admin" and authors with no posts unless asked. Counts are published
  posts (and private ones the viewer may read).
- `wp_get_canonical_url` is false for anything unpublished. For the queried
  post it adds the page, or uses the comments page link (`#comments`
  included); `rel_canonical` prints it.
- `add_option`, `update_option` and `delete_option` stop on `alloptions`
  and `notoptions` (`wp_protect_special_option`).
- `get_block_theme_folders` reads the old folder names when a theme has
  them; a missing theme gets the defaults.
- Also: `update_post_cache` (only where nothing is cached; `get_post` takes a
  plain object from the cache), `wp_get_term_taxonomy_parent_id`,
  `the_terms`, `get_comment_excerpt` and `comment_excerpt`,
  `wp_is_large_user_count`, `wp_timezone_override_offset`,
  `wp_get_first_block`, `wp_untrash_post_set_previous_status`, and the
  deprecated `get_current_theme`, `get_themes` and `get_theme`.

`wp_parse_args` lives in `Support\Lists::args` now; the engine's own callers
use it, and the WordPress-call ratchet is 1071.

## Shortcodes as the reference runs them, and the queue's second wave (2026-10-07)

**Shortcodes** (probe shortcode-run, 109 cases). Several facade functions
were stubs: `do_shortcode_tag` answered "", `wp_kses_attr_parse` false,
`wp_kses_one_attr` its input, `do_shortcodes_in_html_tags` its input. The
pass in `Runtime\Shortcodes` now runs like the reference:

- Only the registered names the text holds take part.
- Inside HTML tags first:
  - A quoted attribute value's shortcode runs, and the attribute is judged
    again by kses (`KsesAttributes::oneAttribute`). The output comes back
    escaped (`&quot;`).
  - A shortcode standing as an attribute name, or as the tag (`<[name]>`),
    runs as written.
  - Comments, CDATA, and every tag under `ignore_html` are left alone.
  - Brackets left in tags, and the `&#91;` and `&#93;` already in the text
    (which become `&#091;` and `&#093;`), are set aside and restored after
    the text pass.
- Each shortcode goes through `pre_do_shortcode_tag` and `do_shortcode_tag`,
  with the match array.
- A self-closing shortcode's content is `""`, not null.
- Images rendered inside a shortcode see the `do_shortcode` context.

`shortcode_parse_atts`:

- reads no-break and zero-width spaces as spaces;
- needs whitespace (or the end) after each token;
- undoes backslash escapes;
- empties a value holding unbalanced HTML;
- does not straighten curly quotes; the old parser did, because
  `wptexturize` curled the quotes inside shortcodes.

`Texturize` now leaves registered shortcodes as written. It also leaves the
text inside those that `no_texturize_shortcodes` names (`[code]`), on every
path. `wp_html_split` keeps comments and CDATA whole.

**The queue's second wave** (probe plugin-queue2, 89 cases):

- `get_post_galleries` was a placeholder. It now reads [gallery] shortcodes
  (run with the post as their default id) and gallery blocks, old and new
  formats, inside other blocks too. The result is markup or attributes with
  their image sources. `get_post_gallery_images` and
  `get_post_galleries_images` build on it.
- Gallery items join with nothing between them, then `\n\t\t</div>`.
- `wp_image_add_srcset_and_sizes` (also a stub) adds srcset and sizes to one
  tag. So does `wp_img_tag_add_srcset_and_sizes_attr`.
- `rest_send_allow_header` answers from the engine's policies for its own
  routes (`WP_REST_Server::engine_allowed`, `Api::allowedOn`). For a
  plugin's route it uses the handlers' permission callbacks.
- `WP_List_Util` is the stepwise list (filter, pluck and sort on the last
  output). The `wp_list_*` functions share `Support\Lists`; pluck passes
  over anything that is not an array or object, and an empty sort leaves
  the list as it is.
- `create_initial_post_types` registers the built-in types and statuses
  again from their own rows (unchanged; the hooks fire).
- Also:
  - `wp_revoke_user`
  - `wp_get_all_sessions`, `wp_destroy_other_sessions`,
    `wp_destroy_all_sessions`
  - `wp_spaces_regexp` (settled at first use)
  - `wp_get_direct_php_update_url`
  - `_wp_add_global_attributes`
  - `wp_term_is_shared`
  - `get_comment_statuses`
  - `wp_is_site_url_using_https` and `wp_is_home_url_using_https`, behind
    `wp_is_using_https`
  - `backslashit`
  - `get_edit_tag_link`
  - `__ngettext` and `_c`
  - `wp_resolve_post_date`
  - `get_userdatabylogin`
  - `wp_style_loader_src`
  - `wp_zip_file_is_valid` (`Ops\Unzip::valid`)
- The emoji calls (`print_emoji_detection_script`, `wp_enqueue_emoji_styles`,
  `print_emoji_styles`) print nothing. No engine page prints the
  reference's emoji plumbing, and the reference's own second call prints
  nothing either.

Open: the engine reads cookies from the request it received, not from
`$_COOKIE` at the moment of the call. A plugin that writes `$_COOKIE`
mid-request (to make a cookie it just set visible) is not seen by
`wp_get_session_token` or the auth cookie readers. The reference reads
`$_COOKIE` live.

## Facade functions that only answered a constant (2026-10-07)

A sweep of the facade for bodies that only return a constant or their
input, without the placeholder trace, found 130. Most are right as they
stand:

- multisite and network answers
- abstract defaults the reference also gives (`WP_Filesystem_Base`,
  `WP_Image_Editor`)
- deprecated no-ops
- Mute admin screens

The ones themes and plugins read are now real (probe facade-stubs, 27
cases):

- `is_multi_author` counts the authors with published posts and keeps the
  answer in its transient.
- `is_new_day` compares `currentday` and `previousday`. `the_date` prints
  only on a new day, through the `the_date` filter; it used to print on
  every post.
- `WP_Theme::get_files` lists by type and depth, the parent's too when
  asked. `get_page_templates` reads through it.
- `balanceTags` balances when forced or when `use_balanceTags` is on.
- `get_attachment_taxonomies` covers the file extension, the MIME type and
  each half of it.
- `wpdb::get_col_info` reads the last result's fields;
  `wpdb::get_col_length` reads the table's columns (`Db::columnLength`).
- `WP_Rewrite::mod_rewrite_rules` gives the .htaccess block, the
  Authorization line included.
- `wp_widget_description`, `wp_get_attachment_id3_keys`,
  `wp_iso_descrambler` and `_wp_iso_convert`, `wp_targeted_link_rel` and its
  callback (`rel="noopener"`).
- `wp_get_post_revisions_url` and `wp_get_latest_revision_id_and_total_count`.
  A revision's edit link takes no `action=edit`.

Still constant, on purpose:

- `WP_oEmbed::discover`: Minn makes no discovery requests.
- `wp_get_http_headers`
- `do_enclose` and the pings (DELIBERATE).
- `wp_text_diff` and `WP_Block::process_block_bindings`: to look at
  separately.

## Block bindings (2026-10-08)

The engine kept a bare list of bindings sources and never applied one, so
content bound to post meta (ACF's own source works the same way), a synced
pattern's overrides, and Twenty Twenty-Five's `twentytwentyfive/format`
rendered their saved defaults. Now (probe block-bindings, 49 cases):

- `WP_Block_Bindings_Registry` and `WP_Block_Bindings_Source` work as the
  reference's do:
  - one registration per lower-case namespaced name, with a label and a
    callable `get_value_callback`; anything else gets a notice and false;
  - `uses_context` stays null unless given;
  - `get_value` hands the callback's answer to
    `block_bindings_source_value`.
- The core sources register at `init`:
  - `core/pattern-overrides`: the `pattern/overrides` context, by the
    block's metadata name.
  - `core/post-meta`: only on a readable post, not behind a password, and
    only for an unprotected key shown in REST; the registered default when
    no value is stored.
  - `core/post-data`: `date` (ISO 8601), `modified` (only when later than
    the date, otherwise ""), and `link`.
  - `core/term-data`: no case the probe could make gave a value on the
    reference, so none is given here.
- `Blocks\Bindings`:
  - **What can bind:** paragraph and heading content; a button's url,
    text, target and rel; an image's id, url, title, alt and caption; a
    post date's datetime; a navigation link's or submenu's url. Both
    filters (`block_bindings_supported_attributes`, then its per-block form)
    can widen the list. A `__default` pattern-overrides binding stands for
    every supported attribute.
  - **How values land:** rich text is put inside the selected element,
    through `wp_kses_post`; other values are set as the selected element's
    attribute. A dynamic block renders with the values merged in.
- The renderer binds every block it renders, reading the post being
  rendered and the context enclosing blocks provide. A synced pattern
  provides its overrides. A core block rendered through `WP_Block` passes
  its own context along.

`WP_HTML_Tag_Processor::set_attribute` now writes a URL attribute through
`esc_url` (refusing a value it wipes out, such as `javascript:`). New
attributes are written in the order of their text, as the reference sorts
them; the engine used to reverse the order they were set.

`is_admin` and `wp_is_block_theme` were pure delegates to the runtime, so
the engine reads the runtime directly. The WordPress-call ratchet falls
to 1057.

## Personal data requests (2026-10-08)

The family of functions privacy and form plugins call to file and confirm
data requests (WPS Hide Login, All-In-One Security, Ninja Forms, Ultimate
Member, CF7 Redirect) was missing (probe user-requests, 36 cases):

- `wp_create_user_request` records a `user_request` post:
  - the email in the title, the action in the slug, the data as JSON;
  - pending or confirmed, owned by the user with that address.
  - Refusals: a bad address (`invalid_email`); an action other than export
    or erase (`invalid_action`, which 7.1 does refuse); another status
    (`invalid_status`); an unfinished request for the same address and
    action (`duplicate_request`).
- `WP_User_Request` and `wp_get_user_request` read it back.
- `wp_generate_user_request_key` stores a 20-character key with
  `wp_fast_hash` (now in the facade, over `Auth\FastHash`).
  `wp_validate_user_request_key` refuses, in the reference's order: an
  invalid request, one no longer pending (`expired_request`), a missing
  key, a wrong key, then a key older than `user_request_key_expiration`.
- `wp_send_user_request` mails the confirmation link in the requester's
  language. The subject, content and headers each pass their filter.
- On `user_request_action_confirmed`:
  - at 10, `_wp_privacy_account_request_confirmed` notes the time and
    confirms the request;
  - at 12, `_wp_privacy_send_request_confirmation_notification` tells the
    site owner, once.
- `_wp_privacy_account_request_confirmed_message` thanks the requester for
  an export or an erasure (`user_request_action_confirmed_message`).
- The mail's link (`wp-login.php?action=confirmaction`) works on Minn's
  login screen:
  - a good key confirms the request and says so;
  - a missing id or key, or a key that does not open the request, gets an
    error page with status 500, as the reference's `wp_die` gives (reader
    suite, on both stacks).

The capture's first run deleted post 1 from the shared database: an
error-returning request id was cast to an integer (1) and cleaned up. Post 1,
its comment and its old slugs were put back from the fixtures. The probes
now delete only real ids.

## The queue's third wave: fluid type sizes, index helpers and HTTP encoding (2026-10-08)

The third slice of the plugin queue (probe plugin-queue3, 88 cases):

- **Fluid type sizes** (`Theme\Typography`):
  - `wp_get_typography_value_and_unit` splits a px, rem or em length (a bare
    number is px). It converts between them at a 16px root when asked, and
    rounds to three places. Percentages, viewport units, negatives and
    words are null.
  - `wp_get_typography_font_size_value` leaves a size as written unless the
    theme's `typography.fluid` is on. A preset's own `fluid` bounds do not
    switch it on; the old global stylesheet path did, and the unit check now
    states the setting.
  - With fluid on, a size above the floor (14px, or the settings'
    `minFontSize`) runs from a minimum at a 320px viewport to the size at the
    theme's wide size (else 1600px). The minimum is the preset's own, or the
    size times `1 - 0.075 log2(px)` held between 0.25 and 0.75, never under
    the floor. A preset with `fluid: false`, a size in another unit, or two
    equal viewports stay as written. A bool `$settings` (the old signature)
    is deprecated and read as the global settings.
  - `wp_get_computed_fluid_typography_value` writes the clamp(): the slope in
    the minimum's unit, the base in rem. The global stylesheet's font size
    presets now go through the same code.
- **Upgrade helpers**: `drop_index` and `add_clean_index`, `__get_option`,
  `deslash`. `get_core_checksums` answers false: the engine never asks
  wordpress.org (Track H).
- **Directory sizes**: `recurse_dirsize` and `get_dirsize` add up a tree,
  skipping the parts left out, and keep each directory's size in the
  `dirsize_cache` transient as the reference does. `wp_privacy_exports_dir`
  and `wp_privacy_exports_url` point under uploads.
- **Images**: `is_gd_image`, `wp_imagecreatetruecolor`, `wp_load_image` (with
  the reference's "does not exist?" and "is not an image." messages),
  `wp_exif_date2ts`, `wp_exif_frac2dec`, `is_local_attachment`,
  `image_add_caption`. `url_to_postid` now finds an attachment by its page
  address, kept only when the permalink is that address.
- **Blocks**: `get_block_core_post_featured_image_border_attributes`,
  `wp_get_elements_class_name` (no parameter, as 7.1 has it),
  `block_template_part` (out of the placeholders), `block_header_area` and
  `block_footer_area`.
- **Requests**: `wp_fix_server_vars` fills `REQUEST_URI`, `PHP_SELF` and the
  rest as the reference does for a bare server.
  `wp_populate_basic_auth_from_authorization_header` reads Basic
  credentials from the Authorization header.
- **`WP_Http_Encoding`**: compress, decompress, `compatible_gzinflate`,
  `accept_encoding`, `content_encoding`, `should_decode`, `is_available`. The
  gzip header walk (extra field, name, comment, header CRC) lives beside
  the response reader's own inflate, `Http\RawResponse::inflateLoose`.
- Also: `bool_from_yn`, `wp_htmledit_pre`, `wp_richedit_pre`,
  `next_widget_id_number`, `page_template_dropdown`.
- **Placeholders added**: these are wp-admin and installer functions that no
  plugin needs on the front end. They log, as the other placeholders do:
  `_wp_customize_include`, `core_update_footer`, `export_wp`,
  `media_upload_form`, `register_and_do_post_meta_boxes`,
  `update_recently_edited`, `validate_file_to_edit`, `verify_file_md5`,
  `wp_install`, `wp_nav_menu_disabled_check`, `wp_register_tinymce_scripts`,
  `WP_Filesystem_FTPext`, `Walker_Nav_Menu_Checklist`.

The reference's first capture died in `next_widget_id_number`, which lives in
wp-admin/includes/widgets.php. The probe loads that file now. The fatal left
the probe's index test table behind; it was dropped.

## Lists and selects through their walkers, the links manager, and menu marks (2026-10-08)

The fourth slice of the plugin queue (probe plugin-queue4, 142 cases).

**One path for every term and page list.** The engine drew the category
list and select, the page list and select, and the page menu with its own
code (TermLists, PageList, PageMenu). A walker a plugin passed was ignored,
and so were `list_cats`, `list_pages`, `category_css_class`,
`category_list_link_attributes`, `page_css_class` and
`page_menu_link_attributes`. Now each goes through its walker, as the
reference does:

- `wp_list_categories` walks `walk_category_tree` with `Walker_Category`:
  - the feed link (text in parentheses, or an image);
  - the count, and the description as the title;
  - current-cat, current-cat-parent and current-cat-ancestor, with
    aria-current on the current category's links, its feed link too;
  - the separator when the style is not a list;
  - the category in view marked current;
  - the "all" link and the none message;
  - the filter gets the caller's own arguments.
- `wp_dropdown_categories` walks `walk_category_dropdown_tree` with
  `Walker_CategoryDropdown`. The value comes from `value_field`, or the id
  when the term has no such field. Names, and the all and none choices,
  pass through `list_cats` and are not escaped after.
- `wp_list_pages` walks `walk_page_tree`, which now notes
  `pages_with_children`, with `Walker_Page`:
  - current_page_item, and current_page_parent and current_page_ancestor
    by the current page's ancestry; the posts page is current_page_parent
    when no page is current;
  - both filters;
  - the date when asked;
  - "preserve" and "discard" spacing.
- `wp_dropdown_pages` walks `walk_page_dropdown_tree` with
  `Walker_PageDropdown`:
  - the option is selected by page id, whatever the value field;
  - the title runs through `list_pages` and is escaped after;
  - an untitled page reads "#id (no title)".
- `wp_page_menu` is the home item plus `wp_list_pages`, so it nests, with
  the reference's classes; it was flat. With no container it uses a div,
  and an empty list still gets its container.
- `cat_is_ancestor_of`.
- `_minn_link_attributes` writes a link's attributes the same way for the
  category, page and menu walkers.

**Users and roles**:

- `wp_dropdown_users`, with `wp_dropdown_users_args` in the reference's
  key order;
- show by a field or "display name (login)";
- `multi` (no id), the all and none choices, and the selected user added
  when asked;
- empty when there is no one, or only one and the caller hides a lone
  author.

`wp_dropdown_roles` prints the roles lowest first.

**The links manager** (`Content\Links`):

- `get_bookmarks`:
  - included ids set aside every other narrowing;
  - categories are joined, so a link filed under two comes back twice;
  - an unknown category name gives nothing;
  - search covers the address, name and description;
  - order by any column, or by length.
- `get_bookmark` and `get_bookmark_field`; rows read as strings, as
  `$wpdb` gives them.
- `sanitize_bookmark` and `sanitize_bookmark_field`:
  - integer ids and ratings, categories as ids;
  - visibility kept to Y and N, a target to `_blank` and `_top`;
  - then edit_, pre_ or the display filter, with an escape.
  - The pre_ and display chains are registered as the reference registers
    them.
- `wp_insert_link`:
  - nothing is saved without an address, and the name falls back to it;
  - the fields go through the database chain and are unslashed;
  - the default link category is used when none is given;
  - add_link or edit_link fires after.
- `wp_update_link` (0 for a link that is not there; the reference dies on
  one), `wp_delete_link`, `wp_get_link_cats` and `wp_set_link_cats`.
- `wp_list_bookmarks` and `_walk_bookmarks`; `get_link` (deprecated).
- A taxonomy with no post types and no count callback (link categories)
  now counts every relationship (`_update_generic_term_count`), not
  published posts. `term_exists` reads the term it finds, so `get_term`
  and `get_{taxonomy}` hear it.

**Menu marks** (`Runtime\MenuItemMarks`, behind both
`_wp_menu_item_classes_by_context` and `wp_nav_menu`). Two passes, as the
reference marks them:

- An item's own classes now come before menu-item; they came after.
- An item whose object is the current page's parent gets
  current-page-parent, after the menu marks, and a page item the
  current_page_parent and current_page_ancestor compat tokens.
- On a page deeper than the menu reaches, the items above it are only
  current-page-ancestor; they were also marked parent.
- An item for a category above the one in view is
  current-category-ancestor.
- On a single post:
  - the items of its terms in hierarchical taxonomies are its menu parents
    (current-post-ancestor, current-menu-parent, current-post-parent);
  - items for terms above those are current-post-ancestor only; they were
    also current-menu-ancestor;
  - tag items are untouched.
- The posts page is current_page_parent on any view but a page, unless it
  is the current item.
- Also: post type archive items, and menu-item-privacy-policy.
- The view is read from the main query's own flags. The engine's calls into
  WordPress-named functions fall to 1046.

The probe runs the reference's custom-item rules as a web request would:
a request host, https when the site is. Under WP-CLI the reference skips
them, and the home item loses menu-item-home.

## The playlist, link rel, and the stubs themes call (2026-10-08)

The fifth slice of the plugin queue (probe plugin-queue5, 70 cases).

**[playlist]** (`wp_playlist_shortcode`, markup in `Content\Playlist`, the
templates in data/playlist-templates.html):

- `ids` lists attachments (in that order unless another is asked); without
  them, the post's own attachments are listed, but only for someone who may
  read the post. The reference checks `read_post`, which an anonymous
  request does not pass even on a published post, so its own-attachments
  playlists come back empty to visitors.
- Audio, or else video (an unknown type plays as video).
- `post_playlist` may answer first; it gets the instance number.
- The player:
  - 640 wide, or the content width less 22;
  - a video player as tall as the last track that had a size (each
    track's resized height, in proportion).
- Each track:
  - its file, type, title, caption and description;
  - its ID3 fields (`meta`, an empty list when it has no metadata);
  - a video's original and resized dimensions;
  - its featured image, or the type's icon at 48x64.
- The flags in the JSON pass `wp_validate_boolean`, but whether tracks
  carry images follows the raw value: `images="false"` still sends them.
- The JSON keeps slashes unescaped.
- A feed gets a line of links.
- `wp_playlist_scripts` (on the `wp_playlist_scripts` action) queues the
  style and script and prints `wp_underscore_playlist_templates` in the
  footer at 0.

**Link rel** (`Content\TextFilters::rel` and `linkRel`, one writer for
`wp_rel_nofollow`, `wp_rel_ugc`, `wp_rel_callback`,
`wp_rel_nofollow_callback`):

- A link that has a rel keeps its words first and gains the new ones.
  The tag is written again, double-quoted, with bare attributes left bare
  and rel last.
- A link without a rel keeps its attributes as written and takes rel at
  the end.
- A link to the site itself does not take "nofollow". That is
  `wp_is_internal_link`: a known scheme and a host from
  `wp_internal_hosts`, so ftp:// to the site counts and //host does not.
- The targeted-link rel filters are gone in 7.1:
  `wp_init_targeted_link_rel_filters` and
  `wp_remove_targeted_link_rel_filters` only report themselves deprecated.

**Images**: `get_image_tag`; `wp_make_content_images_responsive`
(deprecated). An intermediate size for a requested box is now one of
exactly that box, or a larger one in its proportion (the original's
when a side is 0), else the thumbnail when wide enough, else the full
file. The engine took any larger size; a 300x225 medium served a 100x50
request that the reference serves from the full file.

**Also**:

- `before_last_bar`, `_split_str_by_whitespace`, `wp_specialchars`;
- the comment author email family; `get_the_author_email`,
  `the_author_email`, `get_user_by_email`;
- `posts_nav_link` and `get_posts_nav_link`; `get_meta_sql`;
- the cache primers, which have nothing to fill here:
  `update_post_author_caches`, `update_comment_cache`,
  `update_term_cache`, `wp_prime_option_caches_by_group`;
- `wp_cache_set_posts_last_changed`, `global_terms_enabled`;
- `_flatten_blocks`, breadth first, as references;
- `wp_get_default_extension_for_mime_type`, `_device_can_upload`;
- `array_first` and `array_last` before PHP 8.5;
- `wp_unique_id_from_values` (eight hex of the MD5 of the values' JSON);
- `wp_is_theme_directory_ignored` (prefixes: version control folders,
  .DS_Store, node_modules, vendor);
- `wp_should_load_block_assets_on_demand`.

The deprecated functions report the reference's versions and
replacements.

**Placeholders made real**: `cancel_comment_reply_link`,
`edit_term_link`, `get_post_parent`, `get_all_page_ids` (every page,
whatever its status, through `Content\Posts`), `is_login` (the
wp-login.php script, or a request for it), `_deep_replace`,
`add_magic_quotes`, `addslashes_gpc`, `clean_attachment_cache`,
`wp_scripts_get_suffix`, `get_theme_data`, `has_meta` (by key, then id,
through `Runtime\Meta`).

The probe masks home and site with one token. WP-CLI runs the reference
as https (its configured address is), but the engine's CLI runtime counts
as plain http, so `site_url` differs there and nowhere else.

## Block template files, and small block helpers (2026-10-08)

The sixth slice of the plugin queue (probe plugin-queue6, 44 cases), the
template helpers that kubio, superb-blocks, buddypress and event-tickets
call.

**Template files** (`Theme\TemplateIndex::files`, `fileInfo`,
`fileRecord`, `postRecord`; `Theme::templatePath` beside `partPath`):

- `_get_block_template_file` and `_get_block_templates_files` describe
  the theme's files: slug, path, theme (the parent's for a file only the
  parent has) and type, in the directory's order.
  - A part adds its title (when theme.json gives one) and its area.
  - A custom template adds its title and post types.
  - The query narrows by `slug__in`, `slug__not_in`, `area` and
    `post_type`; a template with no post types never matches a post type.
  - A type that is neither gives null.
- `_build_block_template_result_from_file` builds a template from such a
  description, its markup read from the path with the theme named on every
  template part. The default template types give the title and
  description of the slugs they name, which are the only templates that
  are not custom (parts always are).
- `_build_block_template_result_from_post` builds one from a saved row,
  under the theme its `wp_theme` term names; a row with no theme is
  `template_missing_theme`.
- `get_block_file_template` gives the theme file's template past whatever
  the site saved over it, between its `pre_` and plain filters.
- `resolve_block_template` gives the first template the hierarchy names
  (`.php` and `.html` dropped, `_strip_template_file_suffix`), saved or the
  theme's.
- `locate_block_template` only considers templates at least as specific as
  a PHP template already found (one outside the theme keeps only the first
  candidate). It sets `$_wp_current_template_id` and
  `$_wp_current_template_content` (the empty-template warning for a
  signed-in visitor) and answers the canvas.
- `_inject_theme_attribute_in_block_template_content` and its remover
  (deprecated since 6.4), with `_inject_theme_attribute_in_template_part_block`
  and `_remove_theme_attribute_from_template_part_block`.

The templates the engine hands out now have the reference's shape:

- a saved row's author as stored and its dates as the post keeps them
  (the record still carries the REST form);
- parts always custom;
- theme files with the theme on their template parts and the post types
  their declaration gives;
- no `author_text` or `original_source`, which are REST fields built from
  the records, not properties of the object.

**Block helpers**:

- `wp_should_skip_block_supports_serialization`;
- `wp_has_border_feature_support`;
- `_wp_get_presets_class_name` (the MD5 of the serialized block);
- `get_comments_pagination_arrow` and `get_query_pagination_arrow` (arrow
  or chevron spans, null for any other style);
- `wp_get_block_name_from_theme_json_path`;
- `get_block_asset_url` (wp-includes, the theme or its parent, whose folder
  may be a link, else a plugin's);
- `block_core_navigation_filter_out_empty_blocks` (every freeform block
  goes);
- `get_classic_theme_supports_block_editor_settings`;
- `wp_enqueue_block_support_styles` (the head under a block theme, else
  the footer);
- `wp_render_empty_block_template_warning`.

Not done here: the `wp_apply_*_support` functions and duotone. The
engine's block renderer applies supports through its own
`Blocks\Supports`, and it has no duotone. Making the supports one
registry that both the renderer and those functions use is its own
change.

## The queue's tail: bootstrap steps, moving folders, and admin placeholders (2026-10-08)

The seventh slice of the plugin queue (probe plugin-queue7, 13 cases).

**Real**:

- `is_protected_endpoint`: the login screen, the admin outside Ajax, or a
  protected Ajax action (`is_protected_ajax_action`), else the filter.
- `wp_ssl_constants`: `FORCE_SSL_ADMIN` from the site address when
  wp-config leaves it out.
- `require_wp_db`, `wp_set_wpdb_vars`, `wp_start_object_cache` and
  `rest_api_loaded` do nothing here: the database and the object cache are
  always up, and the engine answers REST before a page loads.
- `move_dir`:
  - refused onto itself or onto a folder that is there, unless
    overwriting (which deletes it first);
  - copied and the source removed when one move cannot do it;
  - `wp_opcache_invalidate_directory` after.
- `copy_dir` now fails (`dirlist_failed_copy_dir`) for a source that is
  not there, as the reference's listing does.
- `image_resize` (deprecated since 3.5) uses the image editor.
- `wp_localize_jquery_ui_datepicker` converts the date format in the
  reference's order (`n` becomes `m` before `m` doubles, `j` becomes `d`
  after `d` doubles).

**Inline script data**:

- `wp_add_inline_script` now keeps the position's data as the reference
  does. The code is added to what was there, which is `false` when
  nothing was, so `get_data('after')` reads `[false, 'code']`; it read
  `['code']`.
- Printing still reads the inline list.
- Open: the engine refuses inline code and data on core script handles it
  ships no file for (`wp-util`, `underscore`, jQuery UI), where the
  reference stores them. So the datepicker's localization attaches nothing
  here, and the probe leaves it out.

**Placeholders** for admin-only names that plugins reference and that
log if called:

- functions: `touch_time`, `wp_edit_attachments_query`,
  `wp_edit_posts_query`, `_media_states`, `find_posts_div`,
  `get_media_items`, `media_upload_form_handler`, the
  `wp_ajax_activate_plugin`, `wp_ajax_wp_link_ajax` and
  `wp_ajax_get_attachment` handlers, `wp_nav_menu_item_post_type_meta_box`,
  `wp_dashboard_cached_rss_widget`, `iis7_save_url_rewrite_rules`,
  `_wp_get_allowed_postdata`, `_wp_translate_postdata`,
  `maybe_convert_table_to_utf8mb4`, `wp_read_audio_metadata`;
- classes: the REST revisions, autosaves, template revisions and
  autosaves, block patterns and site export controllers, the REST meta
  field classes, `WP_Debug_Data` and `WP_Navigation_Fallback`.

`WP_Customize_Custom_CSS_Setting`, `WP_Customize_Filter_Setting` and
`WP_Customize_Background_Position_Control` sit in customize.php beside
their parents, which the generated placeholders load before.

The first engine run of this probe died after it had changed
`date_format` and before it put it back, and its next run "restored" the
changed value. Thirty-six comment-date checks failed until the option was
set back to `F j, Y`.

## The catalogue's last real functions (2026-10-08)

The eighth slice of the plugin queue (probe plugin-queue8, 44 cases). This
finishes the catalogue-driven waves: 1,932 of the 2,000 catalogue plugins
load, 99.0% of installs. What is left is the AI client (a decision for
Austin), the block-supports functions (one registry with the renderer is
its own change) and admin names, which the dead-end pass covers.

**Posts and users**:

- `get_the_modified_author` and `the_modified_author`;
- `get_pung` and `get_to_ping` (cleaned by `sanitize_trackback_urls`,
  which keeps only http and https addresses);
- `_wp_get_post_revision_version`, `has_post_parent`;
- `get_page_hierarchy` (pages whose parent is not among them are left
  out);
- `setup_userdata`, `get_users_of_blog` (deprecated, with its old columns,
  `Content\Users::withCapabilities`);
- `count_many_users_posts` (counts as stored, 0 for none,
  `PostLookup::countsByAuthors`);
- `wp_is_password_reset_allowed_for_user`;
- `wp_get_users_with_no_role` (`Users::withoutRole`);
- `get_currentuserinfo` and `wp_get_user_request_data` (deprecated).

**Text and content**:

- `human_readable_duration` (`[H:]MM:SS`, the hour only when it is not
  0);
- `attribute_escape` and `clean_url` (deprecated, the database context
  reported apart);
- `get_shortcode_tags_in_content` (nested ones too);
- `wp_embed_handler_video`;
- `get_all_category_ids` (deprecated), `get_tax_sql`;
- `_get_plugin_from_callback`;
- `_wp_call_all_hook` (`Hooks::callAll`).

**Theme and blocks**:

- `wp_custom_css_cb`, now on `wp_head` at 101 as the reference hooks it.
  The engine never printed the Additional CSS a classic site stored.
- `get_theme_starter_content`;
- `wp_get_theme_data_custom_templates` (post types default to pages) and
  `wp_get_theme_data_template_parts`;
- `get_theme_feature_list` (always the static list; nothing is asked of
  wordpress.org);
- `_add_block_template_info`, `_add_block_template_part_area_info`,
  `_filter_block_template_part_area`, `_get_block_templates_paths`.

**Files and the rest**:

- `wp_get_font_dir`;
- `wp_get_webp_info` (`Media\WebpInfo`): lossy, lossless, or
  `animated-alpha` for any extended file, unknown under 40 bytes;
- `verify_file_signature`. `wp_trusted_keys` holds no wordpress.org key
  (the engine fetches nothing from it), only what its filter adds.
- `delete_meta`, `get_meta_keys` (`Runtime\Meta::keys`);
- `register_sidebar_widget` and `register_widget_control` (deprecated;
  an uncallable widget callback registers nothing, a control registers
  anyway);
- `script_concat_settings` (off);
- `is_sitemap`, `_prime_comment_caches`;
- `wp_magic_quotes`.

**REST**: `rest_validate_object_value_from_schema`. The schema validator
now enforces `minProperties` and `maxProperties`
(`rest_too_few_properties`, `rest_too_many_properties`), which it had
listed and ignored.

## The whole API resolves or is counted: dead ends and the compat status (2026-10-08)

Austin's goal: every symbol in the reference's PHP API either behaves like
the reference or, for wp-admin, the block and site editors, the Customizer
and XML-RPC, exists as a dead end, so no plugin fatals on it.

**Dead ends**:

- `tests/tools/deadends.php` sorts the reference inventory by source file
  into four areas, written to `public/minn/data/deadend-symbols.json`:
  - admin: `wp-admin/*` and the 7.1 admin pages under
    `wp-includes/build/pages/`;
  - editor: block-editor.php, the editors' own REST controllers, remote
    pattern loading;
  - customizer;
  - xmlrpc: IXR, the XML-RPC server, and pingback, trackback and
    update-ping sending.
- Every name the facade lacks in those areas is stubbed by
  `tests/tools/stub-symbols.php --deadends` into `wp-api/deadends.php` and
  `wp-api/classes/deadends/DeadEnds.php`. A stub returns the neutral value
  of its type and logs the call (`Runtime\PlaceholderTrace`).
- The dead-end classes load after the facade's own files, so they may
  extend any class those declare.
- The Customizer's hand-written classes keep recording what themes
  register. They, `WP_Screen` and `WP_Privacy_Policy_Content` answer any
  method they do not define through `Runtime\DeadEndCalls` (`__call` and
  `__callStatic`, logged, null), about 200 methods in all.
- The placeholder list keeps only behaviour still owed: 99 functions and
  38 classes moved from it to the dead ends.
- `contracts/api/mappings.json` marks every generated stub with kind
  `deadend` or `placeholder`, and any entry in a dead-end area, hand-written
  or not, with its area under `deadend`.

**Status** (`tests/tools/compat-status.php`, written to
`contracts/api/compat-status.json`). Every reference function, class and
public or protected method is real, dead end, placeholder or missing.
Verified means real and called by a probe that tests/api.test.php
compares with the reference. At this commit:

| | total | resolved | real | dead end | placeholder | missing | verified |
|---|---|---|---|---|---|---|---|
| functions | 3,913 | 2,906 | 2,007 | 868 | 31 | 1,007 | 1,299 |
| classes | 380 | 301 | 144 | 128 | 29 | 79 | 80 |
| methods | 4,073 | 3,273 | 1,328 | 1,561 | 384 | 800 | 763 |

The style suite checks that both files are current, and ratchets the
missing counts (they only fall) and the verified counts (they only rise).

One divergence to know: on the reference's front end the wp-admin include
functions do not exist until something requires their file; here they
exist as dead ends. A plugin's `if (!function_exists('get_plugins'))
require_once ABSPATH . 'wp-admin/includes/plugin.php';` therefore skips a
file the engine's sites may not have.

## Deprecated functions (2026-10-08)

The first domain batch toward the whole API: the reference's deprecated
functions outside block supports, 165 of them, in
`wp-api/deprecated.php`. The probe is `tests/tools/deprecated-probe.php`
(fixture `deprecated`, 165 cases). Each case records what a call printed,
what it returned, and every deprecation it reported (function,
replacement, version), so nested reports are checked too, in order.

- **The deprecation report.** Each function reports itself the way the
  reference does, then does what its replacement does. Two functions
  never report: `default_topic_count_text` and `_save_post_hook`.
  `funky_javascript_callback` does not report either.
- **Pluggable.** The deprecated pluggable functions are `set_current_user`,
  `wp_setcookie`, `wp_clearcookie`, `wp_get_cookie_login` and `wp_login`.
  `get_user_by_email` and `get_currentuserinfo` moved from user.php to join
  them. All of them sit in `pluggable.php` behind `function_exists`, so a
  plugin's own definition wins.
- **The old links functions.** `get_links` and its variants share the
  anchor with `_walk_bookmarks` (`_minn_bookmark_anchor`, which now takes
  the title it is given, and `_minn_bookmark_updated`). Their own markup
  differs in three ways:
  - "(Last updated DATE)", without the colon;
  - the `links_recently_updated_prepend` and `_append` options instead of
    `<em>`;
  - the rating through `get_linkrating`.

  `get_links_list` passes `type => link` to `get_categories`, which now
  reports that deprecated argument and reads `link_category`. It also runs
  the `get_categories_taxonomy` filter, which the engine lacked.
- **Quirks kept on purpose:**
  - `wp_get_links(5)`: the reference appends the bare id to itself as a
    query string ("5?category=5"), which mangles the key, so every link
    shows.
  - `dropdown_cats`: its query string's leading "?" swallows
    `show_option_all`, so the "all" option never shows.
  - `wp_list_cats`: any `optionall`, even 0, shows the "all" item.
  - `the_category_head`: compares a `category_id` that categories no longer
    have, so it prints nothing.
  - `get_linkobjects`: its default limit of 0 finds no links.
- **User levels.** The `user_can_*` checks compare the old numeric levels
  (`wp_user_level`): drafts at 1, posts at 2, dates above 4, other people's
  posts at a higher level, everything at 10. They short-circuit the way the
  reference does, so the nested reports match.
- **Live functions they lean on:**
  - `get_boundary_post`;
  - `wp_strict_cross_origin_referrer`;
  - `_wp_register_meta_args_allowed_list`;
  - `wp_lazyload_comment_meta`, a no-op like the term one, since meta loads
    when it is first read;
  - `wp_unregister_widget_control`;
  - `_excerpt_render_inner_blocks`.
- **The meta argument filter.** `_wp_register_meta_args_allowed_list` is a
  default `register_meta_args` filter, ahead of any plugin's. Unknown
  arguments passed to `register_meta` are dropped, but ones a plugin's
  filter adds stay (probe meta-api).
- **Excerpts.** A wrapper block in `excerpt_remove_blocks` now opens
  through `_excerpt_render_inner_blocks`, so only the innermost allowed
  blocks render. For example, a quote in a column gives its paragraphs, not
  the quote.
- **Widget controls.** `wp_register_widget_control` now behaves like the
  reference:
  - the id is lowercased;
  - the update handler is registered once per id base, with the instance
    number made -1;
  - an empty callback removes both;
  - a second registration before `widgets_init` keeps the first.
- **Fixes found on the way:**
  - `wp_mime_type_icon` gives the default icon for a post with no type or
    a post that does not exist; it returned false before.
  - `wp_get_archives` marks the post being shown as current in
    `postbypost` and `alpha` lists.
  - `get_archives_link` double-quotes `aria-current`.
  - `wp_no_robots` prints the robots meta tag (it sent a header) and
    reports its deprecation.
- **Dead ends by name** (`tests/tools/deadends.php` `NAMED`). These
  deprecated functions live in wp-includes but only ever served the admin
  or the editors: `the_editor`, the two Press This AJAX handlers,
  `remove_option_whitelist`, `wp_add_editor_classic_theme_styles` and
  `wp_add_iframed_editor_assets_html`.
- **Left for the block-supports batch:** the deprecated duotone,
  tinycolor, skip-serialization, elements, typography-variable, global
  styles custom CSS and webfonts handler functions (about 25), plus
  `wp_update_https_detection_errors`, which waits on the HTTPS detection
  functions.
- **One WP-CLI artifact** to know when writing probes: the reference's
  WP-CLI loads wp-admin's filters. A user inserted in a probe therefore
  gets a `dismissed_wp_pointers` row that a front-end or REST registration
  on the reference does not get.

| | missing | verified |
|---|---|---|
| functions | 1,007 → 826 | 1,299 → 1,418 |

## Core blocks by name, and every core block rendered anywhere (2026-10-08)

The second whole-API batch: the named functions in `wp-includes/blocks`, in
`wp-api/core-blocks.php`. The probe is `tests/tools/core-blocks-probe.php`
(fixture `core-blocks`, 90 cases).

- **Render callbacks by name.** The core block types carry the reference's
  render callbacks by name (`data/blocks.json` `render_callback`, in place
  of the `dynamic` flag). Most are `render_block_core_*`; eight are not,
  for example `block_core_heading_render` and `block_core_gallery_render`.
  Each callback renders through the engine's own renderer for the block
  (`_minn_render_core_callback`). It uses the attributes given, and the
  instance's other parts and context, or the saved markup when there is no
  instance. A callback the reference declares with fewer parameters still
  passes on what `WP_Block::render` gives it.
- **Direct calls differ in one way.** The reference's callback leaves the
  supports' classes to `render_block`'s filters, and called outside a
  render it has none. The engine's output has them. The probe records a
  direct call's tags and text, and whole renders exactly.
- **Registrations.** Each `register_block_core_*` registers its block type
  again. The registry refuses that while the type is registered: it calls
  `_doing_it_wrong` and returns null, as the reference does. The site logo
  and site icon settings register with the reference's arguments.
- **A plugin's callback that wraps the core one.** The renderer keeps the
  engine's own renderer for each block (`Renderer::registerDynamic`), apart
  from a plugin's callback that took it over (`Renderer::bridge`). A core
  callback renders natively (`Renderer::renderNative`), so a plugin
  callback that calls the one it replaced no longer calls itself. A theme
  family registering later keeps a plugin's bridge in place.
  `WP_Block::render` skips rendering inner blocks first for a core block
  the engine renders itself; doing so had run an inner loop's query twice.
- **Every core block renders wherever block content does.**
  `Renderer::forDb` now registers the post, query, term and comment
  families, and the theme's structure blocks when a block theme is
  active. Before, only theme page renders had them, so a plugin's
  `render_block()` of a post title returned nothing.
  `_minn_render_core_block` provides the instance's context:
  - the post from `postId`, or the global post for a callback given no
    instance;
  - the comment from `commentId`;
  - the rest (a query, a term) as provided context.

  So a post template called by its callback runs the query its context
  names.
- **New renderers in src:**
  - `TermBlocks`: term-name, term-count (bracket types) and
    term-description, taken from QueryBlocks. A term in context gives its
    description as written; a term archive gives it as a paragraph.
  - query-total: results found, or "Displaying a – b of n".
  - The navigation submenu. Inside a navigation it gets the toggle and the
    Interactivity API wiring that `openSubmenusOnClick` and
    `showSubmenuIcon` ask for, read as context the navigation provides.
    On its own it is a plain item. Before, a submenu rendered as its child
    links alone. The submenu and page-list wiring now share constants.
  - The navigation overlay close button.
  - A social link on its own.
  - post-date's `format`.
  - The avatar of the post author.
  - comment-date's `format` and `isLink`.
  - The comment date and reply link look up the comment's own post.
  - comments-title's `showPostTitle` and `showCommentsCount`.
  - post-navigation-link renders nothing off a single post or page.
- **New renderers in the facade.** These blocks are built from WordPress
  functions: get_avatar, comments_open, the login form, get_calendar and
  wp_list_categories. The src code cannot call those (the
  WordPress-call ratchet sits at its ceiling), so the facade registers
  them on the engine's private `minn_block_renderers` action as the
  renderer is built:
  - categories, as a list or a dropdown with the reference's script,
    numbered per block; the src `Categories` class is gone;
  - post-author and post-author-biography;
  - post-comments-count and post-comments-link;
  - post-time-to-read: a range from four fifths to six fifths of
    words / speed (the high end at least one above the low), or one
    rounded value, or the word count;
  - read-more;
  - loginout;
  - shortcode (wpautop of the saved text);
  - widget-group, with the sidebar's title markup through
    `note_sidebar_being_rendered`;
  - calendar;
  - home-link, current when `is_front_page()`.

  Word counts follow `block_core_post_time_to_read_word_count`: HTML and
  comments out; entities, connecting dashes and punctuation (digits among
  it) do not count.
- **Image loading.** An image in a block shares the page's budget of
  eager images only inside the page template (`RenderState::inside
  ('template')`, set by `PageRenderer`) or the main loop. A block a
  plugin renders on its own is lazy, as on the reference.
- **Left for the next batch.** These blocks are probed only once they have
  real markup:
  - the new blocks: accordion, tabs, icon, playlist, breadcrumbs,
    terms-query and term-template;
  - footnotes, legacy widgets and the legacy post-comments block;
  - comment and query pagination;
  - the site logo and the comment edit link.

  The remaining `block_core_*` helpers come with them.

| | missing | verified |
|---|---|---|
| functions | 826 → 649 | 1,418 → 1,571 |

## Block supports (2026-10-08)

The third whole-API batch: `wp-includes/block-supports`, `WP_Block_Supports`,
`WP_Duotone`, and the block supports' deprecated functions, in
`wp-api/block-supports*.php`. The probe is
`tests/tools/block-supports-probe.php` (fixture `block-supports`, 43
sections), with a plugin block type that has every support.

- **Registering and applying.** The supports register with
  `WP_Block_Supports` in the reference's order, which is the order of the
  wrapper's classes and styles. Each `wp_register_*_support` adds the
  attributes it needs to a block type that opts in. Each
  `wp_apply_*_support` returns the classes and styles a block's attributes
  earn, through the style engine. Colors and typography turn presets into
  class names; spacing, shadow and border keep theirs as custom properties.
- **The wrapper.** `get_block_wrapper_attributes` prints style, class, id
  and aria-label first, then the caller's other attributes. The supports'
  styles come before the caller's: split on semicolons, each part trimmed,
  the ends trimmed of semicolons. The caller's classes come first, each once.
  The caller's id and aria-label win.
- **One layout path.** `Minn\Blocks\Layout` gives core and plugin blocks
  their layout classes:
  - the layout used is the block's own over its type's default;
  - `is-vertical`, `is-content-justification-*` and `is-nowrap` come from
    the block's own layout;
  - a `wp-container-*` class appears exactly when the layout writes CSS.

  `Minn\Blocks\LayoutStyle` writes that CSS, as `wp_get_layout_style` does.
  The reference's own rule replaced the engine's earlier exceptions: a
  container for columns always, none for buttons, a hand list of layouts
  with rules. A flex group with only `orientation: horizontal` has no
  container on the reference, and now none here. A child's own size or place
  earns a `wp-container-content-*` class. Classes go on the element that
  holds the inner blocks: the tag the markup last opens before them.
- **One stylesheet.** The engine's renderer hands its layout and element
  rules to the style engine's `block-supports` store, through the private
  `minn_block_support_rules` action. The page prints the store with the
  global styles (`minn_block_supports_css`); the old container and
  element-rule lists in `RenderState` are gone. Position, visibility, states
  and duotone rules land in the same store.
- **Element styles.** A block earns a `wp-elements-N` class for
  colours on these elements:
  - a link's text colour, plain or on hover;
  - a heading's, a heading level's or a button's text, background or
    gradient.

  Caption and cite styles earn nothing. A colour support that skips
  serialization switches a kind off (link, heading or button). The engine
  had excluded core/buttons, social-links and navigation by name; on the
  reference they get the class, and now do here.
  `wp_get_elements_class_name` numbers from the render state's counter, the
  one the renderer uses.
- **One counter.** `wp_unique_id` numbers from the render state's
  per-request counter. The engine's renderer numbers galleries, style
  variations and inputs from the same counter, as the reference numbers
  them from `wp_unique_id`.
- **Style variations.** A block whose className names a variation with
  styles of its own gets `is-style-{name}--N`. The styles are the theme's,
  or a registered style's `style_data`. The variation's CSS goes on the
  `block-style-variation-styles` handle, element rules first. The renderer's
  fixed list of three TT5 variations is gone: `Renderer::numberedStyle` asks
  the facade for the data (`minn_block_style_variation`). The reference
  reads registered `style_data` through its theme.json cache, so a style
  registered after the cache is built numbers only once
  `wp_clean_theme_json_cache()` runs. The engine reads the registry
  directly. `wp_clean_theme_json_cache` now clears the facade's theme.json
  caches, which moved into the request's runtime state.
- **The other render filters.**
  - Background: an image's styles with has-background (cover by default, a
    contained image centred).
  - Dimensions: an explicit aspect ratio unsets the heights; a minimum
    height unsets the ratio.
  - Position: sticky (or fixed, where the theme allows it) gets a numbered
    `wp-container-N`, counted for any position named. The top offset
    clears the admin bar, its value raw (a preset is not resolved, as on
    the reference).
  - Typography: a stored custom font size made fluid, the first tag's style
    rewritten a declaration at a time.
  - Visibility: hidden viewports in name order, or nothing at all.
  - Custom CSS: nested rules unfolded on `wp-block-custom-css`.
  - Block-level presets: only lists keyed by origin count. The class goes
    ahead of the layout classes, the CSS into a `<style>` of its own.
  - States: only for `WP_Theme_JSON::VALID_BLOCK_PSEUDO_SELECTORS` blocks,
    from `style[":hover"]`. Rules are `!important`, split by the block's
    selectors, with the reference's fallbacks.
  - Duotone: a preset's CSS is its custom property; custom colours use the
    filter's url. Rules land when `output_block_styles` runs; the footer SVGs
    follow the order of use.
- **Filters once.** For a core block the engine renders, `render_block()`
  and `WP_Block::render` had applied `pre_render_block` and
  `render_block_data` twice, and a dynamic block's `render_block` twice too.
  A static core block's inner blocks had rendered twice.
  - `WP_Block::render` now has the engine render a native core block whole
    (`Renderer::renderNativeUnfiltered`) and applies its render filters once.
  - Inner blocks of other blocks pass through `pre_render_block` and
    `render_block_data` with their parent, as on the reference.
  - `Runtime\BlockFilters` leaves out the support filters the renderer
    applies itself (layout container, element styles, style variations).
    The child-layout part runs as a stand-in.
- **safecss.** `safecss_filter_attr` follows the reference declaration by
  declaration (58 cases in the safety-filters probe):
  - A bare value is kept.
  - Property names match exactly.
  - The functions a value may use are taken out before the character test:
    calc, var, min, max, minmax, clamp, repeat, and the transform and shape
    functions.
  - `url()` is allowed on background, background-image, cursor, filter,
    mask and clip-path, with allowed schemes. Gradients are allowed on the
    two background properties.
  - A value is rejected if it then holds `\ ( & = }` or `/*`.

  The engine had kept `width:a&b`, `rgb()` and `fit-content()`, and
  dropped `width:javascript`.
- **Caught by the browser and styles suites.** A vertical flex layout
  lines its items up at the start (`align-items:flex-start`) unless it is
  justified otherwise; without that, a stacked group's paragraph sat
  centred. A handle's inline styles print in one tag, a line apart, as the
  reference prints them. A dynamic core block's style variation is numbered
  before it renders (the wrapper takes the pending class), so a block that
  renders nothing still numbers it and prints its CSS, as the reference
  does with post-terms.
- **Smaller fixes on the way.** A style's inline data starts its list
  without a `false` (a script's keeps one). Variation CSS prints a padding
  or margin shorthand. The custom CSS strip filters belong to
  `wp_custom_css_kses_init_filters`, not `kses_init_filters`.
  `WP_Theme_JSON::get_custom_css` and `process_blocks_custom_css` are real
  (`Minn\Theme\CustomCss`).
- **Deprecated.** The tinycolor helpers keep the reference's arithmetic:
  - a channel reads as a whole number, so a percentage is its number and a
    fraction is 0;
  - a grey goes through the channels;
  - colour names other than transparent are not read.

  `WP_Duotone`'s preset methods report their own deprecation, and the
  render path uses private helpers. `_wp_theme_json_webfonts_handler`
  registers its three hooks with no work in them: the engine prints the
  theme's font faces itself.
- **Left for later.**
  - `wp_get_layout_style`'s `$options` and the child rules'
    `$viewport_overrides` are accepted and unused.
  - The native renderer reads a parent's layout only through `parentLayout`
    on the facade path.

| | missing | verified |
|---|---|---|
| functions | 649 → 555 | 1,571 → 1,640 |
| classes | 79 → 78 | 80 → 83 |
| methods | 800 → 789 | 763 → 842 |

## The navigation family's helpers (2026-10-08)

Batch 4a: the `block_core_navigation*`, page-list, home-link and shared item
helpers, in `wp-api/core-blocks-navigation.php`. The probe is
`tests/tools/navigation-blocks-probe.php` (fixture `navigation-blocks`, 33
sections). The engine's own renderer still renders the blocks; these answer
plugins that call the helpers.

- **Colours and sizes.** A named colour is a class, a custom one inline; one
  helper serves the navigation, links, page lists and the home link.
  - A navigation reads its attributes.
  - A link reads its navigation's context, the overlay colours inside a submenu.
  - A page list reads the context alone; its own attributes are ignored, as on
    the reference.
  - The home link takes its custom colours from the context's style.

  A navigation's font size helper gives only the preset's class; a link's
  gives a custom size inline, made fluid.
- **Submenus.**
  - A navigation's `submenuVisibility` turns hover into click when it opens
    submenus on click.
  - A submenu or page list reads always, else click or hover, from context.
  - The directives a submenu item takes come from
    `Navigation::submenuDirectives`, which the engine's renderer prints too.
- **Trees and ids.** The tree checks match block instances only; parsed arrays
  never match. Post ids are collected inner first, through nested navigations,
  from links whose kind is post-type.
- **Menus as blocks.** `Menus::linkAttributes` maps a menu item to a
  navigation link in the reference's shape. The engine's own classic-menu
  fallback uses it too, and the native link reads the `title` attribute (an
  empty one included).
  - Menu items keep an empty stored class (`MenuItemMarks`), as the reference
    does; the walker drops it as it prints.
  - The fallback functions create a navigation post from a classic menu, as
    the reference does.
- **Variations.** A label left at core's default (the label a type starts
  from: a post's or page's, a tag's or category's) gives "{singular} link"
  and a blank description. Post formats have their own label.
  `data/blocks.json` no longer stores the link's variations; the
  `get_block_type_variations` filter supplies them, as on the reference.
- **Kept on purpose.**
  - `block_core_navigation_link_maybe_urldecode` decodes once only when a
    query value is still encoded after parsing.
  - `block_core_page_list_nest_pages` keys children by array position.
  - `block_core_navigation_add_support_classes_to_container` changed nothing
    in any case tried on the reference, so the engine's passes the markup
    through.
- **Owed.** `get_post_type_labels` and `get_taxonomy_labels` return an
  object's labels as they stand; the reference builds them from core's
  defaults.

| | missing | verified |
|---|---|---|
| functions | 555 → 518 | 1,640 → 1,677 |

## The content blocks' helpers (2026-10-08)

Batch 4b: the helpers of the search, social link, gallery, image, details,
paragraph, avatar, featured image, latest posts and comments, post excerpt,
post terms, query, archives and comments blocks, in
`wp-api/core-blocks-content.php`. The probe is
`tests/tools/content-blocks-probe.php` (fixture `content-blocks`, 37
sections). The engine's own renderer still renders these blocks; the helpers
answer plugins that call them.

- **Search.** The block's classes, colour, border and typography classes, and
  the four inline styles.
  - With the button inside, the width and border go on the wrapper, and the
    radius grows by its padding (`calc(R + 4px)`).
  - Otherwise the border goes on the input and the button. The radius is
    whole or per corner.
- **Image lightbox.**
  - `block_core_image_render_lightbox` gives the figure its context and key
    and the image its directives, puts the trigger button after the image,
    records the image's metadata in the `core/image` state, and hooks the
    overlay into `wp_footer`.
  - The trigger and overlay markup are captured reference output in
    `data/lightbox-trigger.html` and `data/lightbox-overlay.html`. The
    overlay takes the theme's text and background colours.
- **Hooks.** These are registered as on the reference:
  - the gallery's image ids and context;
  - older latest-posts categories;
  - the query's data pass;
  - low fetch priority inside details;
  - the paragraph's class.

  The engine already does the paragraph class and the gallery's `data-id`
  for blocks it renders natively. So `BlockFilters::NATIVE_RENDER_DONE` and
  `NATIVE_DATA_DONE` name those two filters, and a native render skips them.
  The done lists now apply to the `render_block_{name}` filters too.
- **Engine fixes the probe found.**
  - `wp_get_global_settings` reads a `block_name` context under
    `blocks.{name}`.
  - `wp_enqueue_block_style` waits on one `render_block` filter per call,
    matched by block name, as on the reference. It used to use
    `render_block_{name}`.
  - A lazy attachment image's `sizes` starts with `auto`, before the
    `wp_get_attachment_image_attributes` filter. `wp_img_tag_add_auto_sizes`
    adds it to the first lazy `img` with a width and a sizes list.
  - `wp_enqueue_img_auto_sizes_contain_css_fix` replaces the engine's private
    copy. It hooks `wp_head` at 0 and runs once a request; the first call
    decides.
  - A caller's own `loading`, anything but `lazy`, is no longer made lazy.
- **Kept on purpose.**
  - `block_core_gallery_resolve_dynamic_source` and
    `block_core_query_disable_enhanced_pagination` changed nothing in any case
    tried on the reference. The engine's return `[]` and the block as given.
  - `register_legacy_post_comments_block` drops any registration and
    registers the old block afresh, without supports attributes, as the
    reference does. Nothing calls it at `init`, because the engine's block
    data already registers the block.
  - `comments_block_form_defaults` is defined but not hooked, because the
    engine's `comment_form` styles a block theme's button itself.
- **Owed.**
  - The engine's renderer has no lightbox, and no archives dropdown, of its
    own.
  - The native latest posts block ignores `categories`.
  - The native search renderer repeats part of the search helpers; that is a
    consolidation item.
  - On the reference, `WP_Block::render` drops the styles a block enqueued
    while rendering when its output is empty. The engine keeps them.

| | missing | verified |
|---|---|---|
| functions | 518 → 480 | 1,677 → 1,715 |

## The REST API's schema helpers (2026-10-08)

Batch 5: seventeen functions of `rest-api.php`, in `wp-api/rest-api.php` over
`Rest\Schema` and `Rest\SchemaValues`. The probe is
`tests/tools/rest-validators-probe.php` (fixture `rest-validators`, 65 rows).

- **The per-type validators.** `rest_validate_{null,boolean,integer,number,string,array}_value_from_schema`
  (and `rest_validate_object_value_from_schema`, which used to run the whole
  validator) are `Schema::validateAs()`: one type's own checks and nothing
  else, so no enum and no format.
  - A number's bounds and `multipleOf` come before an integer's wholeness:
    `5.5` against `maximum: 3` is `rest_out_of_bounds`, not `rest_invalid_type`.
  - Called with no `type` in its schema, the number and integer validators
    say "p is not of type ." (the type name is the schema's own). The
    wholeness refusal always says "integer".
  - The string validator checks length and pattern, never the format.
- **One path for the whole validator.** `rest_validate_value_from_schema` now
  runs `validateAs()` for the type, then the enum, then the format. That is
  the reference's order, and the engine had two of them the other way round
  (the enum before the type checks, wholeness before bounds), so
  `'0'` against `minimum: 1, enum: [1, 2]` is now `rest_out_of_bounds`, not
  `rest_not_in_enum`.
- **Enums compare as their type.** `rest_validate_enum` (and the enum step
  of the whole validator) sanitises the value to the schema's type first:
  `"1"` is in `[1, 2]` as an integer, `"a"` is in `[true]` as a boolean. With
  no type, the comparison is strict.
- **Unique items** sort keys at every level but keep types apart:
  `{a, b}` and `{b, a}` are duplicates; `1`, `1.0`, `"1"` and `true` are not.
  The array validator's `uniqueItems` used to miss the key-order case.
- **Patterns** (`rest_validate_json_schema_pattern`, string `pattern`,
  `patternProperties`) share `SchemaValues::matchesPattern()`: unanchored,
  `#` escaped, UTF-8. An empty pattern matches anything.
- **`_rest_array_intersect_key_recursive`** keeps the first array's entries
  whose keys the second has, recursing only where both hold an array.
- **`rest_format_combining_operation_error`** reads `schema.title`,
  `error_object` and `index`: "p is not a valid T. Reason: ..." when the
  schema has a title (an empty one still counts), otherwise "p does not match
  the expected format. Reason: ...", with `{position: index}` as data.
- **Authentication status.** `rest_cookie_collect_status` (on the five
  `auth_cookie_*` actions the reference hooks, not `bad_session_token`)
  records `true` or the hook's name after its twelve-character prefix.
  `rest_application_password_collect_status` (on the failure and success
  actions) records the result and the password's uuid, null after a refusal;
  `rest_get_authenticated_app_password` returns that uuid.
  `rest_application_password_check_errors` (on `rest_authentication_errors`
  at 90) lets an earlier answer stand, turns a recorded refusal into a 401,
  and answers true after a success.
- **Also hooked as on the reference.** `rest_output_rsd` on
  `xmlrpc_rsd_apis`; `create_initial_rest_routes` on `rest_api_init` at 99.
  It does nothing here: the engine's core routes answer without being added
  to the server, so it is real but not counted as proven.
- **An engine fix the probe found.** `rest_is_boolean('')` is false on the
  reference; the engine said true. That came from the default widgets batch,
  where a media widget's `update()` keeps `''` as `false`. The widget does
  that itself: it reads an empty boolean field as an unticked checkbox, and
  stays as strict as `rest_is_boolean` for anything else (`yes`, `on`, a
  space are skipped). The empty string now goes to `false` in
  `WP_Widget_Media::update()`, and `SchemaValues::isBoolean()` follows the
  reference again.

Left in `rest-api.php`: `rest_handle_deprecated_function`,
`rest_handle_deprecated_argument` and `rest_handle_doing_it_wrong`. They send
their headers with `header()` during a REST request, which a WP-CLI probe
cannot see; they need an HTTP probe against the twin (a route that calls a
deprecated function with debugging on).

## media.php's tag helpers (2026-10-08)

Batch 6: twenty-one functions of `media.php`, in `wp-api/media.php` over
`Media\Metadata`, the new `Media\AvifInfo` and `Blocks\RenderState`. The probe
is `tests/tools/media-tags-probe.php` (fixture `media-tags`, 25 rows); it uses
the block battery's image (607) and sweeps the posts it makes by name.

- **`wp_img_tag_add_loading_optimization_attrs`.** Reads the tag's width,
  height, loading, decoding and fetchpriority, asks
  `wp_get_loading_optimization_attributes`, then adds what the tag lacks, each
  straight after `<img`, so the last added reads first: decoding through
  `wp_img_tag_add_decoding_attr`, then loading through
  `wp_img_tag_add_loading_attr` (only when the tag has a width and a height),
  then `fetchpriority="high"`. The decoding filter is handed the tag as it was,
  the loading filter the tag with decoding added. Nothing is added in the
  `template` context.
- **`wp_img_tag_add_width_and_height_attr`** adds both when the tag lacks
  either, from `wp_image_src_get_dimensions` for the size its source names,
  after `wp_img_tag_add_width_and_height_attr` allows it.
- **`wp_iframe_tag_add_loading_attr`** gives an iframe with a width and a height
  its loading through `wp_iframe_tag_add_loading_attr`, even when it already
  has one (the reference writes `loading="lazy" loading="eager"`). The content
  filter checks for a loading attribute first; the helper does not.
- **`wp_maybe_add_fetchpriority_high_attr`**: only for an `img`; a priority the
  image names stands (`high` closes the flag), a lazy image never gets it, and
  otherwise the first image large enough (`wp_min_priority_img_pixels`, 50000)
  claims it.
- **`wp_increase_content_media_count`** adds to `RenderState`'s image count, the
  same count the loading rules read, so a plugin moving it moves the eager
  budget.
- **Post thumbnails.** `begin_fetch_post_thumbnail_html` and
  `end_fetch_post_thumbnail_html` add and remove
  `_wp_post_thumbnail_class_filter` (appends ` wp-post-image`, even to a missing
  class) and `_wp_post_thumbnail_context_filter` (the context becomes
  `the_post_thumbnail`). `wp_get_attachment_image` now asks
  `wp_get_attachment_image_context` (one argument) and hands the answer to the
  loading rules. Before this, the classic path never gave a thumbnail
  `wp-post-image`.
- **Small helpers.**
  - `wp_sizes_attribute_includes_valid_auto` replaces the engine's private
    `_minn_sizes_say_auto`: `auto`, alone or before a comma, any case.
  - `wp_expand_dimensions` fits the example's proportions to the box, growing
    as well as shrinking.
  - `wp_get_image_encode_quality`: 86 for WebP, 82 otherwise or the default
    given, then `wp_editor_set_quality` (type, size), then `jpeg_quality`
    (`image_resize`) for a JPEG. `WP_Image_Editor::set_quality` now asks it.
  - `wp_mediaelement_fallback`: the escaped address as its own text, then
    `wp_mediaelement_fallback`.
  - `wp_get_avif_info`: the primary item's `ispe` and `pixi` properties, tied
    to it by `ipma`; false in every field for anything not AVIF.
  - `wp_get_chromium_major_version`: the number after `Chrome/`, null for any
    other browser or none.
  - `wp_is_client_side_media_processing_enabled`: false unless
    `wp_client_side_media_processing_enabled` says otherwise.
  - `wp_image_file_matches_image_meta`: the location, query dropped, ends with
    the stored path, or the stored folder plus a size's or the original's
    name; then `wp_image_file_matches_image_meta`.
  - `get_next_image_link` and `get_previous_image_link` return what
    `get_adjacent_image_link` returns.
- **Dead ends.** `wp_set_client_side_media_processing_flag` (hooked only on
  `admin_init`) and `wp_show_heic_upload_error` (the plupload settings) are
  admin; `wp_set_up_cross_origin_isolation` (the editor screens' `load-*`
  actions), `wp_start_cross_origin_isolation_output_buffer` and
  `wpview_media_sandbox_styles` are editor. They are in `deadends.php`'s
  `NAMED` list.

Left in `media.php`: `wp_add_crossorigin_attributes`,
`wp_maybe_generate_attachment_metadata`, and the personal-data exporter pair
(`wp_media_personal_data_exporter`, `wp_register_media_personal_data_exporter`),
which go with the comment and user exporters in a privacy batch.

## functions.php's utilities (2026-10-08)

Batch 7: twenty-two functions of `functions.php`, over `Minn\Json`, the new
`Support\Hierarchy` and `Content\TextFilters`. The probe is
`tests/tools/functions-utils-probe.php` (fixture `functions-utils`, 21 rows);
it puts the `dirsize_cache` transient back at shutdown.

- **WP_HOME and WP_SITEURL.** `_config_wp_home` and `_config_wp_siteurl` answer
  the constant, untrailingslashed, when wp-config.php defines it, and are hooked
  on `option_home` and `option_siteurl` as on the reference. The engine's own
  classes still read the stored option; a site whose constant differs from it
  reads the constant only through the facade.
- **The JSON encoder's fallback.** `Minn\Json::repair` replaces only the
  invalid bytes of a string with "?" and keeps its valid characters
  (`mb_convert_encoding`); the engine used to replace every high byte of a
  string that held one invalid byte. It converts keys too,
  reads an object as a plain object of its public properties, and throws
  "Reached depth limit" past the depth asked for. `wp_json_encode` maps onto
  `Minn\Json::encode` and answers false when it throws (`contracts/json.md`);
  `_minn_json_sanitize` is gone. `_wp_json_prepare_data` is deprecated (5.3.0)
  and returns its value.
- **Cache ids.** `_validate_cache_id` accepts an integer, or a string that
  reads as one exactly (`"7"`, not `"0012"`); anything else is reported as
  `_get_non_cached_ids` called incorrectly ("Object ID must be an integer, %s
  given.", 6.3.0, the PHP type name) and refused.
- **Unique-filename helpers.** `_wp_check_existing_file_names` looks for a
  sub-size of the name in the list: `name-WxH`, `name-scaled` or `name-rotated`
  with the same extension, any case. `_wp_check_alternate_file_names` checks
  each alternate name on disk as `$dir . $name`, with no separator added (the
  reference's callers pass a folder with its trailing slash), then against the
  list. The engine's `wp_unique_filename` does not ask these yet.
- **Hierarchy loops.** `Support\Hierarchy` walks a parent chain with a slow
  walker (one step) and a fast one (two). `wp_find_hierarchy_loop_tortoise_hare`
  returns where they meet, or with `$_return_loop` every node walked in the
  order first reached (the slow walker's node, then the fast one's two steps),
  or false when a parent is empty. `wp_find_hierarchy_loop` overrides the
  start's parent with `$start_parent`, finds a meeting node, then walks again
  from it to collect the loop.
- **Smilies.** `smilies_init` (hooked at `init`, 5) builds `$wpsmiliestrans`
  from the defaults or an earlier table through `smilies`, reverse-sorts it,
  and leaves `$wp_smiliessearch`: one alternative per first character, the
  rests `preg_quote`d, each bounded by whitespace, `\xC2\xA0`, `&nbsp;` or the
  line's ends, with the `m` flag. `convert_smilies` reads the global table
  once it exists.
- **Small helpers.**
  - `clean_dirsize_cache` drops a folder and every folder above it from the
    cache; folders below and beside keep their sizes.
  - `is_lighttpd_before_150`, `is_utf8_charset` (`utf-8`/`utf8`, any case; the
    `blog_charset` option when none is given) and `wp_is_heic_image_mime_type`
    (the four HEIC/HEIF types, exact case) are plain checks.
  - `is_site_meta_supported` is false on a single site.
  - The HTTPS links: the documentation page, `WP_UPDATE_HTTPS_URL` and
    `WP_DIRECT_UPDATE_HTTPS_URL` through their filters.
    `wp_get_update_php_annotation` says the page comes from the host only when
    `wp_update_php_url` changed it.
  - `wp_site_admin_email_change_notification` (on `update_option_admin_email`)
    mails the old address, even when it equals the new one, unless it is
    empty or the installer's `you@example.com`.
  - `wp_maybe_load_widgets` (on `plugins_loaded`, 0) adds the Widgets menu
    unless `load_default_widgets` says no; the widgets themselves register at
    init as before.
- **Dead ends.** `_mce_set_direction` (TinyMCE settings) is editor;
  `wp_widgets_add_menu` (`_admin_menu`) and `wp_direct_php_update_button` are
  admin.

Left in `functions.php`: `dead_db`, `do_favicon` and `_jsonp_wp_die_handler`
(each ends the request, so an in-process probe cannot see them),
`wp_post_preview_js` (prints only on a preview), and the four privacy
clean-up and scheduling functions, which go with the exporters in a privacy
batch.

## Personal data: exporters, the eraser, and the clean-up jobs (2026-10-08)

Probe `privacy` (`tests/tools/privacy-probe.php`, fixture
`contracts/fixtures/api/privacy.json`). Fourteen functions from `comment.php`,
`media.php`, `user.php`, `post.php` and `functions.php`; `Content\PersonalData`
holds the two pure rules (an export item's rows, and which export files are
old).

- **The lists.** `wp_privacy_personal_data_exporters` gets the account
  exporter at priority 1, then comments and media at 10;
  `wp_privacy_personal_data_erasers` gets the comments eraser. Each entry is
  `{exporter|eraser}_friendly_name` plus `callback`, keyed `wordpress-user`,
  `wordpress-comments`, `wordpress-media`. `_wp_privacy_statuses` names the four
  request statuses.
- **An export item.** `group_id`, `group_label`, `group_description` (the
  `User&#8217;s ... data.` entity is in the string), `item_id`, `data`: name and
  value rows in a fixed order, a row with an empty value left out.
  - Comments (`comment-{id}`): every comment with the address, matched without
    case, held ones included, 500 a page in id order. Author, address, link,
    IP, browser, the local `comment_date` as stored, the content as stored,
    and `<a href="{link}" target="_blank">{link}</a>`. A page below 1 holds
    nothing. `done` is a short page.
  - Media (`post-{id}`): the uploads of the account with the address, 50 a
    page, each with its file's address.
  - The account (`user-{id}`): ID, login, nice name, address, link,
    registration date, display name, nickname, first and last name,
    description, the empty ones left out. `wp_privacy_additional_user_profile_data`
    gets `[]`, the user and the eleven reserved names; what it returns is
    appended as given, except a pair reusing a reserved name, which is
    dropped with a `_doing_it_wrong` notice. Then the Community Events
    location (City, Country, Latitude, Longitude, IP) when the user meta
    holds one, and one item per session token (`session-tokens-{id}-{hash}`:
    Expiration, IP, User Agent, Last Login), dated `F j, Y H:i A` in UTC
    whatever the site's timezone.
  - A stranger's address gets `{"data": [], "done": true}`.
- **The eraser.** Nothing for an empty address. Otherwise, for each comment
  with the address (500 a page): author `Anonymous`, address, link and
  browser emptied, the IP anonymised, `user_id` 0, the content kept, written
  straight to the table (`edit_comment` does not fire). `wp_anonymize_comment`
  (true, the comment, the anonymised fields) keeps a comment when it answers
  anything but true: its string is the message, or `Comment {id} contains
  personal data but could not be anonymized.`. `items_removed` and
  `items_retained` say whether any comment went either way.
- **The clean-up jobs.** At init, `wp_privacy_delete_old_export_files` is
  scheduled hourly and `wp_privacy_personal_data_cleanup_requests` daily, each
  once, starting now. The first removes every file in the exports folder
  last written more than three days ago (`wp_privacy_export_expiration`),
  whatever its type, except `index.php` (and, here, hidden files). The second
  marks a pending request whose `post_modified_gmt` is older than
  `user_request_key_expiration` (a day) as failed; a confirmed one stays.
- **The erasure notice.** `_wp_privacy_send_erasure_fulfillment_notification`
  (on `wp_privacy_personal_data_erased`) mails `[{site}] Erasure Request
  Fulfilled` for any completed request, an export one included, once
  (`_wp_user_notified`). A request not completed, or no request, sends
  nothing. The body names the privacy policy's address only when a published
  page is set. Filters: `user_erasure_fulfillment_email_to` (address,
  request), `_subject` (subject, site name, the data: request,
  message_recipient, privacy_policy_url, sitename, siteurl), `_content`
  (the text with its `###SITENAME###`, `###PRIVACY_POLICY_URL###`,
  `###SITEURL###` placeholders, the data) and `_headers` (headers, subject,
  content, request id, data).

The admin screens that drive these (`wp_privacy_process_personal_data_*`,
the export file and its email) were already admin dead ends; the functions
here are what plugins and cron call.

## comment.php's remaining helpers (2026-10-08)

Probe `comment-helpers` (`tests/tools/comment-helpers-probe.php`). Nine real
functions, six dead ends; with the privacy batch, `comment.php` owes nothing.

- **`sanitize_comment_cookies`.** Each of `comment_author_`,
  `comment_author_email_` and `comment_author_url_` + `COOKIEHASH` that the
  visitor sent goes through its `pre_comment_author_*` filter (whose defaults
  strip tags, validate the address, clean the link), then `wp_unslash` and
  `esc_attr`. A cookie not sent stays unset.
- **`wp_check_comment_data`** is the approval the engine already judged
  (`_minn_comment_approval` is gone; `wp_allow_comment` and `wp_new_comment`
  call it): the post's author and a moderator get 1 (disallowed words
  notwithstanding), anyone else `check_comment` or 0, a disallowed word or
  address `trash`, and `pre_comment_approved` (approval, data) has the last
  word. Slashed data is read unslashed.
- **`wp_cache_set_comments_last_changed`** renews the comment group's
  `last_changed`.
- **`_close_comments_for_old_posts`** (on `the_posts`, 2 args): with the
  setting on and a positive day count, a singular query's first post, of a
  type `close_comments_for_post_types` lists (`post` by default; this path
  honours the filter, `comments_open`'s does not), older than the days, gets
  `comment_status` and `ping_status` `closed` on the object handed over.
  Listings, other posts and the stored row are untouched.
  `Runtime\CommentCloser::closes()` is the one age rule for both paths.
- **The comment-type update.** `_wp_batch_update_comment_type` (on
  `wp_update_comment_type_batch`) types the newest untyped comments
  `comment`, `wp_update_comment_type_batch_size` (100) at a time; a batch
  that changed some schedules the next, one that finds none sets
  `finished_updating_comment_type`. It does not read that option itself.
  `_wp_check_for_scheduled_update_comment_type` (on `admin_init`) schedules
  the batch once while the option is unset.
- **Note markers.** `wp_strip_inline_note_markers` (on `render_block`) takes out
  every `mark` whose class list holds `wp-note` (any case of tag and
  attribute, either quote), nested ones too, keeping the content; an
  unclosed one loses its opening tag; other marks stay whole.
  `Content\NoteMarkers` holds it, with a fast path for markup without
  `wp-note`.
- **Notes told.** `wp_new_comment_via_rest_notify_postauthor` (on
  `rest_insert_comment`) passes a note (a `WP_Comment` of type `note`, not an
  id) to `wp_new_comment_notify_postauthor` while `wp_notes_notify` is on;
  the post's author writing it is not told. Its subject is the reference's
  `[{site}] Note: "{title}"`; its body is Minn's own notice (`Notices::newNote`,
  the editor link where notes are read), as the comment notice already is.
  `wp_send_note_notification` (user, note, post) mails `[{site}] You were
  mentioned in a note on "{title}"` with `{author} mentioned you in a note on
  "{title}".`, the note, and `Edit This: {admin}/post.php?post={id}&action=edit`,
  plain text, and returns the mail's result.
- **Dead ends.** `wp_get_note_mentioned_user_ids` and `wp_notify_note_mentions`
  are editor: the mention markup comes from the block editor's notes, and no
  content written by hand is read as a mention. The four
  `wp_*_for_environment` ping switches join the XML-RPC area's pings.

## kses.php's inner helpers (2026-10-08)

Probe `kses-internals` (`tests/tools/kses-internals-probe.php`). Thirteen
functions, one dead end, and three fixes to the kses the engine already had.

- **Code points.** `valid_unicode`: tab, line feed, carriage return, U+0020 to
  U+D7FF, U+E000 to U+FFFD, U+10000 to U+10FFFF; a numeric string counts by
  its integer value. `Kses::validCodePoint` is that rule, and the engine's
  own reference normalising now uses it. **Fixed:** `&#65534;`, `&#xFFFE;`
  and `&#xffff;` used to stay references here; the reference escapes their
  `&`.
- **The entity callbacks.** `_wp_kses_decode_entities_chr` and its hex twin
  are PHP's `chr` (one byte, so 8364 gives 0xAC). `wp_kses_named_entities`
  keeps a name kses knows and escapes any other (case counts: `AMP` is
  unknown). `wp_kses_xml_named_entities` keeps the five XML names and
  decodes any other known name to its character. `wp_kses_normalize_entities2`
  pads a valid decimal reference to three digits, `wp_kses_normalize_entities3`
  drops a hex reference's leading zeros and keeps its case; an invalid one
  is escaped as written; a `0` match gives nothing at all.
- **Schemes.** `wp_kses_bad_protocol_once2` decodes the scheme, drops
  whitespace and controls, lower-cases it, and answers `{scheme}:` when it is
  allowed, else nothing. **Fixed:** `wp_kses_bad_protocol` now lower-cases an
  allowed scheme (`HTTP://x` became `http://x` on the reference, not here).
- **Attributes.** `wp_kses_hair_parse` cuts an attribute string into
  attributes, each with its trailing whitespace (`KsesAttributes::parse`, the
  cut `wp_kses_attr_parse` already used); `false` for leading whitespace, a
  stray quote or an unclosed value, `[]` for nothing. `wp_kses_html_error`
  drops the first malformed run and the whitespace after it.
  `wp_kses_attr` rebuilds an element with the allowlist's attributes, its
  name as written, an XHTML slash kept; an element the allowlist does not
  know keeps none (`<script>`), it is not dropped (`Kses::element`).
- **The split pass.** `wp_kses_split2` judges one run (`Kses::tag`); a run not
  starting with `<` is a lone `&gt;`. `_wp_kses_split_callback` reads the
  rules `wp_kses_split` leaves in `$pass_allowed_html` and
  `$pass_allowed_protocols`. **Fixed:** the tag pass now reads an HTML
  comment whole (to `-->` or the end) and runs its body through the same pass,
  so `<!-- a > b -->` keeps its comment (`<!-- a &gt; b -->`) and an
  unclosed one is closed; before, the comment was cut at its first `>`. A
  comment holding a `<` is still broken up first by the less-than pass, as on
  the reference.
- **Dead end.** `_wp_kses_allow_note_mention_span` (on
  `wp_kses_allowed_html`) changes nothing in any context a probe can set; it
  answers to state the block editor's notes hold, so it is editor with the
  other mention helpers.
- `Support\KsesCss` now holds the style attribute's rules (`style`,
  `styleHooks`), split from `Kses`, which had grown past six hundred lines.

Left in `kses.php`: `wp_filter_global_styles_post`, which waits on
`WP_Theme_JSON::remove_insecure_properties` (still a placeholder).

## compat.php's stand-ins (2026-10-08)

Probe `compat` (`tests/tools/compat-probe.php`), run on the PHP 8.3 the probes
use, where the reference supplies every one of these. All thirteen now live
in `wp-api/compat.php`; the array functions moved there from `functions.php`
and, like `clamp`, are written with `if (!function_exists(...)) :` so a PHP
that has them keeps its own.

- `_is_utf8_charset`: `utf-8` or `utf8`, any case, nothing around it;
  `is_utf8_charset` asks it with the site's charset by default.
- `_wp_can_use_pcre_u` is always true; passing it anything is deprecated (6.9.0).
- The multibyte fallbacks read the encoding given, or the site's charset.
  Under UTF-8, `_mb_strlen` and `_mb_substr` count characters, a malformed byte
  or a sequence that breaks off counting as one (`"\xE2\x82"` is one), with
  `mb_substr`'s negative start and length; any other encoding counts bytes.
  `_mb_chr` takes an integer only and answers false for another encoding, a
  surrogate, a negative code point or one past U+10FFFF (U+0000 is a NUL).
  `_mb_ord` reads the first character, false for another encoding, an empty
  string or a malformed start. `Support\Utf8` holds the character rules.
- `array_find`, `array_find_key`, `array_any`, `array_all` call back with
  (value, key) and take any truthy answer; `array_first` and `array_last`
  follow the array's order, null when empty.
- `clamp` compares as PHP compares (strings, arrays, null too), returns the
  bound when the value passes it (INF to the maximum, a NAN value as it is),
  and throws `ValueError` for a NAN bound or a minimum above the maximum,
  with PHP's own messages.

## formatting.php's callbacks, and make_clickable done properly (2026-10-08)

Probe `formatting-callbacks` (`tests/tools/formatting-callbacks-probe.php`).
Sixteen functions, and the parents they serve rebuilt on them, so each path is
the reference's: `make_clickable`, `links_add_target`, `links_add_base_url`,
`convert_smilies`.

- **make_clickable** (`Content\Clickable`): text only, never tags or comments,
  nothing inside `code`, `pre`, `script` or `style` (any case; `textarea`, `kbd`
  and `tt` are linked). An address starts the text or follows whitespace or
  `(`; `.,;:!?` after it stay outside, and a `)` it does not open goes after
  it. URLs (http, https, ftp) first, then `www.`/`ftp.` hosts (made `http://`,
  and the link text says so), then email addresses (no rel). The link text is
  the cleaned address (`esc_url`: `&` is `&#038;`, the scheme lower-cased). A
  link that ends up the whole text of an existing link is unwrapped into it.
  `_make_clickable_rel_attr`: `nofollow` for http(s) off the site's own host,
  `ugc` while `comment_text` runs (an internal link in a comment gets
  `rel="ugc"`), then `make_clickable_rel` (rel, url); nothing when empty.
  **Fixed:** the engine's old version linked inside `code`, left `www.` text as
  written, gave `ftp://` and the site's own links `nofollow`, and matched
  after `>` or `=`.
- **Links.** `links_add_target` sets `$_links_add_target` and gives each listed
  tag (as a whole name, so `a` is not `abbr`) the target, removing the one it
  had (`_links_add_target`). `links_add_base_url` sets `$_links_add_base` and
  makes each listed attribute's URL absolute unless it names an allowed
  protocol (`_links_add_base`), through `WP_Http::make_absolute_url`, which now
  resolves as RFC 3986 does (`Http\Location::resolve`, which redirects use
  too): `#frag` and `?q` keep the base's path, `//host` its scheme, `.` and `..`
  are resolved. **Fixed:** `#top` against `/dir/page` gave `/dir/#top`, and
  `//cdn` was joined to the base's host.
- **Texturize's pieces** (`Content\Texturize`): `_get_wptexturize_split_regex`
  cuts comments whole (to `-->` or the end) and tags to their first `>`, plus
  the given shortcodes, and the engine's own texturize uses the same pattern;
  `_get_wptexturize_shortcode_regex` is the shortcode fragment it already had.
  Probed by what the patterns match, never by their text.
  `_wptexturize_pushpop_element` pushes an opening tag or shortcode of a
  listed name (case counts; a self-closing one is not pushed) and pops the
  matching closer when it was the last opened. `wptexturize_primes`: after a
  digit a prime; otherwise a double quote is the closing quote, and a single
  one only where a word ends (before the end, whitespace, U+00A0 or `.,;:!?)]}-`).
- **Small ones.** `_autop_newline_preservation_helper` marks newlines
  `<WPPreserveNewline />`. `translate_smiley` gives the emoji, or the image
  (`smilies_src`, `includes_url`) for the few with one, and `convert_smilies`
  builds its images through it. `url_shorten` drops the scheme, `www.` and a
  trailing slash and cuts to `length - 3` with `&hellip;`.
  `wp_pre_kses_less_than_callback` escapes a run with no `>`.
  `iso8601_to_datetime`: basic or extended form, `Z` or an offset; with an
  offset it is that instant shown in the site's zone (`user`) or UTC (`gmt`),
  without one it is read in the site's zone; false otherwise.
  `wp_staticize_emoji_for_email` makes a mail's emoji images when its
  Content-Type header (name in any case, value exactly `text/html`, through
  `wp_mail_content_type`) is HTML; the images come from the Minn update
  service, so the probe masks their address.
- **Fixed with it:** `esc_url` refused an allowed scheme written in capitals
  since the kses batch lower-cased schemes (`HTTPS://x` gave nothing); a
  change of case alone is now accepted and kept lower-cased.

Left in `formatting.php`: `_print_emoji_detection_script`, which prints the
emoji script Minn leaves out on purpose (an omission candidate for Austin).
Under WP-CLI the reference's `site_url()` keeps https through WP-CLI's own
filter; the probe masks the site's address where that shows.

## The database session's SQL modes (2026-10-08)

WordPress opens its session without the modes it cannot run under, and so
does the engine now (`Db::shared`, `Db::INCOMPATIBLE_MODES`): `NO_ZERO_DATE`,
`ONLY_FULL_GROUP_BY`, `STRICT_TRANS_TABLES`, `STRICT_ALL_TABLES`, `TRADITIONAL`
and `ANSI` come off whatever the server sets. `wpdb::set_sql_mode` sets the
modes given (or keeps the session's own) without those, the list passing
`incompatible_sql_modes`. **Fixed:** the engine's session kept
`STRICT_TRANS_TABLES`, so a plugin's `$wpdb->insert` that left out a text
column with no default (`wp_links.link_notes`, found by probe
link-template-tags) failed here and succeeded on the reference, and
`set_sql_mode` did nothing.

## The site's timezone (2026-10-08)

`Site::timezone()` is the site's zone as `wp_timezone` gives it: the zone
`timezone_string` names, else `gmt_offset` as a fixed offset. With a zone set
the stored `gmt_offset` is not read (the reference ignores it then too).
`Site::gmtOffset()` is that zone's offset now, daylight saving included;
`Dates::iso` (the `datetime` of the date blocks) uses the zone's offset at the
date itself, and `Site::localDate` (REST writes) converts by it, so an August
date stays at -04:00 in New York in winter. **Fixed:** the engine read the raw
`gmt_offset`, so a site whose stored offset was 0 while `timezone_string` named
a zone printed `+00:00` (found by the site suite on the marketing site, whose
stored offset had become 0), and dates across a daylight-saving change were
off by an hour.

## link-template.php's remaining template tags (2026-10-08)

Probe `link-template-tags` (`tests/tools/link-template-tags-probe.php`),
run from a single post's query. Thirteen functions; link-template.php owes
nothing now.

- **Adjacent posts.** `get_adjacent_post` (and so every adjacent link) now
  reads the scope the reference reads (`Content\AdjacentScope` on
  `Posts::previous`/`next`): published posts plus private ones a signed-in
  reader may read (every one with the type's `read_private_posts`, else their
  own); with `$in_same_term`, only posts sharing one of the current post's
  terms in `$taxonomy` (none when it has none); never a post in the excluded
  terms (comma list or array, through `get_{previous|next}_post_excluded_terms`).
  **Fixed:** the engine ignored all three arguments and every private post.
  The post navigation block still reads published posts only.
- `adjacent_post_link` prints `get_adjacent_post_link`, whose link text now
  takes `%date` (the post date in `date_format`) as well as `%title`.
- **Rel links.** `get_adjacent_post_rel_link` is
  `<link rel='prev|next' title='…' href='…' />` plus a newline, the title
  being `%title` (the title as `the_title_attribute` gives it, "Private: "
  and texturizing included) and `%date`, escaped; an attachment's previous is
  its parent; nothing when there is no post; `{previous|next}_post_rel_link`
  filters it. `adjacent_posts_rel_link`, `next_post_rel_link` and
  `prev_post_rel_link` print them; `adjacent_posts_rel_link_wp_head` prints
  both on a single post that is not an attachment (the reference does not
  hook it to `wp_head` by default, and neither does the engine).
- `permalink_anchor`: `<a id="post-{id}"></a>`, or for `title` (any case)
  the title as a slug and the id. `the_feed_link`: the feed's link around the
  anchor text as given (`the_feed_link`, with the feed). `the_shortlink`:
  `<a rel="shortlink" href="…">`, "This is the short link." by default, no
  title attribute whatever `$title` says, between before and after.
- **Edit links.** `edit_tag_link` is `edit_term_link` for any term, through
  `edit_tag_link`. `get_edit_bookmark_link`: `{admin}link.php?action=edit&amp;link_id={id}`
  for someone who may `manage_links` (only while the Links manager is on),
  through `get_edit_bookmark_link`; `edit_bookmark_link` prints it, "Edit This"
  by default (`edit_bookmark_link`).
- `wp_force_plain_post_permalink`: false for a viewable status, for a private
  post the reader may read, and for a protected status (draft, pending,
  scheduled) asked for as a sample (`$sample`, or a post whose `filter` is
  `sample`); true otherwise, and for no post.

## general-template.php's remaining tags (2026-10-08)

Probe `general-template-more` (`tests/tools/general-template-more-probe.php`).
Twelve functions (in `wp-api/template.php`, with the other general-template
tags); two admin dead ends.

- `allowed_tags` writes `$allowedtags` as `<tag attr=""> ` runs, escaped with
  `htmlentities`. `wp_required_field_indicator` is `<span class="required">*</span>`
  and `wp_required_field_message` wraps "Required fields are marked {mark}" in
  `required-field-message`, each through its filter.
- **Tooltips.** `wp_get_tooltip` (and `wp_get_tooltip_helper`, the same markup)
  is a help button labelled with the text (tags stripped) and a `popover="hint"`
  bubble holding it escaped; `wp_get_toggletip` a button and a `popover="auto"`
  dialog labelled `label` ("Help"), with a close button. `icon` follows
  "dashicons " (`dashicons-editor-help` by default), `class` is added, `id`
  names the bubble (and `{id}-text`), else `wp-tooltip-{n}`: every tooltip made
  takes a number, given an id or not, except an empty one, which prints nothing.
- `get_the_post_type_description` is the queried post type's description
  through its filter (with the post type object), texturized, `convert_chars`
  and paragraphed by default; nothing off an archive.
- `the_date_xml` prints the post date as `Y-m-d`; `the_weekday` its weekday
  (`the_weekday`); `the_weekday_date` the weekday between before and after only
  when it differs from the last one printed (`$previousweekday`), through
  `the_weekday_date` (text, before, after).
- `wp_dependencies_unique_hosts`: the hosts of the queued scripts, then
  styles, each once, the request's `SERVER_NAME` left out; registered but
  unqueued and relative sources add none.
- `wp_preload_resources` prints `<link rel='preload' … />` for each
  `wp_preload_resources` entry that has an `href` or an `imagesrcset`, once per
  value, keeping only `href` (as `esc_url` with http and https), `as`,
  `crossorigin`, `type`, `media`, `imagesrcset`, `imagesizes` and
  `fetchpriority` (exact keys), in the order given.
- **Dead ends.** `register_admin_color_schemes` and `wp_admin_css_uri` are the
  wp-admin colour schemes and stylesheet: admin.

## option.php's remaining functions (2026-10-08)

Probe `option-more` (`tests/tools/option-more-probe.php`). Ten functions, one
placeholder made real (`set_user_setting`), one admin dead end.

- `get_options` reads several options after one priming read, name => value
  (false for a missing one, a repeated name once). `wp_set_options_autoload`
  gives several one flag; per option, whether its flag changed.
- **The autoload flag.** `wp_determine_option_autoload_value`: `on` for `on`,
  `yes` or `true`, `off` for `off`, `no` or `false` (strictly: 1, 0 and "" are
  undecided); otherwise `wp_default_autoload_value` (null, option, value,
  serialized) decides, a true or false becoming `auto-on` or `auto-off`, any
  other answer leaving `auto`. `wp_filter_default_autoload_value_via_option_size`
  (on that filter, priority 5) answers false for a stored value longer than
  `wp_max_autoloaded_option_size` (150000 bytes). **Fixed:** `add_option`
  without a flag now decides through it, so an option over the size is stored
  `auto-off`, not `auto`.
- **Site options on a single site.** `wp_prime_site_option_caches` and
  `wp_prime_network_option_caches` (any network id) prime the options;
  `wp_load_core_site_options` has nothing to load. The engine keeps option
  reads in its own store, not in `wp_cache`'s `options` group, so a plugin that
  reads that group directly still finds nothing (a known difference).
- **User settings** (the `user-settings` user option and `user-settings-time`):
  `wp_set_all_user_settings` writes the pairs as `name=value&…`, each kept to
  letters, digits, `_` and `-`, a nameless pair dropped, and refreshes the
  request's copy; false with nobody signed in. `set_user_setting` adds one,
  `delete_user_setting` removes one or several (false when none was set),
  `delete_all_user_settings` empties them and expires the `wp-settings-{id}`
  cookie. **Dead end:** `wp_user_settings`, which keeps that cookie in step
  inside wp-admin only.

## post.php's remaining helpers (2026-10-08)

Probe `post-more` (`tests/tools/post-more-probe.php`). Fourteen functions;
`_add_post_type_submenus` is admin, `_publish_post_hook` (which queues
`_pingme`, `_encloseme` and a `do_pings` event: pingbacks and enclosure
fetches the engine never sends) joins the XML-RPC area's pings.

- `_get_custom_object_labels` (`Content\ObjectLabels`): the name from the
  object's label when unset, the singular from the name, the admin bar from
  the singular, menu from name, all items from menu, archives from all items;
  the flat or hierarchical defaults under those, in their order.
- `_get_last_post_time` is the engine's newest-date reader by its reference
  name (`_minn_last_post_time` is gone): `date` or `modified`, `blog`, `gmt` or
  `server` (GMT with `.000000`), among the public types or one public type;
  false for anything else, a non-public type or a list.
- `_page_traverse_name` walks a children map depth first, slug by id.
  `_post_type_meta_capabilities` records a type's names for `read_post`,
  `edit_post` and `delete_post` in `$post_type_meta_caps`.
- **Caches.** `_prime_post_parent_id_caches` caches parent ids as
  `post_parent:{id}` in the `posts` group (an id not in the table is not
  cached); `update_post_parent_caches` caches the parent posts themselves.
- `get_available_post_mime_types`: each non-empty `post_mime_type` of a type
  once (`pre_get_available_post_mime_types` first). `get_enclosed`: the first
  line of each `enclosure` value, empty ones included (`get_enclosed`).
  `get_private_posts_cap_sql` is `get_posts_by_author_sql($type, false)`.
  `is_post_embeddable`: the type is viewable, then `is_post_embeddable`.
- **Trash slugs.** `wp_add_trashed_suffix_to_post_name_for_post` adds
  `__trashed` once, keeping `_wp_desired_post_slug`;
  `wp_add_trashed_suffix_to_post_name_for_trashed_posts` does it to every
  trashed post with the slug but the one named.
- `wp_delete_auto_drafts` deletes auto-drafts dated over a week ago.
  `wp_queue_posts_for_term_meta_lazyload` has nothing to queue: the engine
  reads term meta directly.

## comment-template.php's remaining tags (2026-10-08)

Probe `comment-template-more` (`tests/tools/comment-template-more-probe.php`).
Ten functions, all real. The trackback tags only print addresses and the
discovery block; receiving and sending trackbacks stays in the XML-RPC area.

- **Author URL.** `comment_author_url` prints `get_comment_author_url`
  through `comment_url` (the comment id as a string). `get_comment_author_url_link`
  is `before`, `<a href="URL" rel="external">TEXT</a>`, `after`, all through
  `get_comment_author_url_link`. TEXT is the text given, or the escaped URL
  with every `http://www.` and then every `http://` removed (anywhere in it,
  case-sensitive; `https://` stays) and one trailing slash dropped. Without a
  URL the link is still printed, with an empty href.
- `comment_type` prints the comment, trackback or pingback text (in that
  argument order), `Comment`, `Trackback` and `Pingback` by default; any other
  type is a comment.
- **Post reply link.** `get_post_reply_link` (single-quoted attributes, an
  `addComment.moveForm( "post-ID", "0", "respond", "ID" )` onclick, href the
  permalink plus `#respond`) or, when commenting needs an account and nobody
  is signed in, a `comment-reply-login` link to `wp_login_url()` with no
  redirect. false while comments are closed. `post_comments_link` sees
  before and after and gets the post.
- **`_get_comment_reply_id`.** `?replytocom=` counts only when it is numeric
  (leading space allowed), positive, and names an approved comment on the
  post (a held comment is 0 even for an administrator); 0 otherwise, as an
  int. `get_comment_id_fields`, `comment_form_title` and
  `get_cancel_comment_reply_link` (whose link shows only then) now go through
  it, where they read the query var raw before.
- **Trackback.** `get_trackback_url`: `trackback/` under the permalink with
  pretty permalinks, else the `siteurl` option's `/wp-trackback.php?p=ID`
  (the option itself, not `site_url()`), through `trackback_url`.
  `trackback_url(false)` returns it and reports the argument deprecated
  (2.5.0). `trackback_rdf` prints the RDF block (permalink twice,
  `the_title_attribute`, the trackback URL); nothing when the user agent
  contains `W3C_Validator` in any case; an argument is deprecated (2.5.0).

## taxonomy.php's remaining functions (2026-10-08)

Probe `taxonomy-more` (`tests/tools/taxonomy-more-probe.php`). Five
functions real. The five WordPress 4.2 shared-term splitting routines
(`_wp_batch_split_terms`, `_wp_check_for_scheduled_split_terms`,
`_wp_check_split_default_terms`, `_wp_check_split_nav_menu_terms`,
`_wp_check_split_terms_in_menus`) stay missing, proposed for omission.

- **Term meta support.** `wp_check_term_meta_support_prefilter` answers false
  while `db_version` is below 34370 and passes the check through otherwise. It
  is hooked on the eight term meta short-circuits: `get_`, `add_`, `update_`,
  `delete_term_metadata`, the three `*_term_metadata_by_mid` and
  `update_term_metadata_cache`. So on such a database every term meta call
  refuses (false) and nothing is written. `get_metadata_by_mid` now fires
  `get_{type}_metadata_by_mid` (it did not), and `update_termmeta_cache` is
  `update_meta_cache('term', …)`, so it passes through its filter too.
- `has_term_meta`: the support check first, then the term's rows as strings
  (`meta_key`, `meta_value`, `meta_id`, `term_id`), by key then id.
- **A deleted term takes its meta.** `wp_delete_term` removes each meta row by
  id, ascending, with `delete_term_meta` and `deleted_term_meta` for each. That
  happens after the children move up (`edit_term_taxonomies`) and before
  `delete_term_taxonomy`. The engine left the rows behind before; 554 orphaned
  probe rows were cleared from the test site.
- `is_term_publicly_viewable`: the term's taxonomy is viewable (a
  `publicly_queryable` taxonomy that is not public counts). The reference warns
  for a missing term, so that case is not compared.
- `the_taxonomies`: `before`, `get_the_taxonomies` joined by `sep` (a space),
  `after`, printed even when the post has none; `template` and
  `term_template` pass through. Non-public taxonomies are listed too.
- `wp_get_split_terms`: the `_split_terms` record for the old id (taxonomy to
  new id), `[]` when there is none.

## revision.php's remaining functions (2026-10-08)

Probe `revision-more` (`tests/tools/revision-more-probe.php`). Eight
functions, plus `wp_get_post_autosave` (a placeholder before), all real.

- **Autosaves.** `_wp_put_post_revision` now honours `$autosave` (it wrote a
  plain `{id}-revision-v1` before) and takes post fields as an array as well
  as a post; the row comes from `_wp_post_revision_data`. `wp_get_post_autosave`
  answers the post's newest autosave by `post_date`, then id. With an author
  it answers that author's, and with 0 anyone's. The block renderer's preview
  (`PostBlocks`) reads the same one, so a preview shows the newest autosave
  whoever wrote it, as on the reference, and it carries the excerpt too.
- **The preview link.** `_show_post_preview` is hooked on `init`. With both
  `preview_id` and `preview_nonce`, a nonce that is not the reader's dies with
  403 "Sorry, you are not allowed to preview drafts." (the engine redirected
  such a link before), and a good one hooks `_set_preview` on `the_preview`.
  `_set_preview` puts the autosave's title, content and excerpt, as stored, on
  the object handed in. Autosave or not, it adds the three preview filters
  (`get_the_terms` 3 args, `get_post_metadata` thumbnail 3 and meta 4).
- **The preview filters** act when the global post is the one asked about
  (no `is_preview()` needed). The meta filter reads a revisioned key from the
  newest autosave (`''` or `[]` when it lacks one, whatever came in). The
  thumbnail filter answers the request's `_thumbnail_id` as a string (`''`
  when not positive, untouched when empty or `'0'`) only when `preview_id`
  is the post. The terms filter answers `post_format` from the request
  (`standard` gives none, else the `post-format-{sanitize_key}` term when
  it exists).
- **Revisioned meta.** `_wp_copy_post_meta` adds each value of the key to the
  target, which keeps its own. `wp_restore_post_revision_meta` (hooked on
  `wp_restore_post_revision`) replaces each revisioned key on the post with
  the revision's values, or removes it when the revision has none.
- **Pre-3.6 revisions.** `_wp_upgrade_revisions_of_post` renames every
  revision without `-v1` (`{id}-revision`, `{id}-autosave`) to its `-v1`
  name. When the newest one was old-named and differs from the post in title,
  content or excerpt, or there are no revisions at all, it adds a revision of
  the post. Always true.
- The reader suite's anonymous-preview check now compares both stacks: 403 on
  each.

## UTF-8 helpers (2026-10-08)

Probe `utf8` (`tests/tools/utf8-probe.php`). utf8.php's `wp_scrub_utf8` and
`wp_has_noncharacters`, and compat-utf8.php's eight fallbacks, all real, in
`Support\Utf8`. They read malformed input the way `Utf8::characters` already
cuts it. A malformed run is the longest broken-off start of a sequence, else
one byte, so `"\xE2\x82"` is one run and an overlong `"\xC0\xAF"` two.

- **Scrub** replaces each run with U+FFFD. **Count** counts characters in a
  byte window, each run once. **Span** is how many bytes the first N
  characters take, with the count found. **ISO-8859-1**: decoding gives `?`
  for a run or a character past U+00FF, and encoding maps each byte to its
  character.
- **Noncharacters** are U+FDD0 to U+FDEF and each plane's last two code
  points. `wp_has_noncharacters` steps over malformed bytes and keeps
  looking. `_wp_has_noncharacters_fallback` is deprecated since 7.1.0 and
  reports itself through `_deprecated_function`; nothing else here does.
- **`_wp_scan_utf8`** reads well-formed characters from `$at`. It stops
  `max_bytes` past where it started (a character begun inside is read
  whole), after `max_code_points`, or at a malformed run. `$at` is left
  there and `$invalid_length` is the run's length, cut at the byte limit (0
  when none). The count read is returned. `$has_noncharacters` comes back
  false, and becomes 0 or 1 (OR'd across the scan) once a sequence that
  could hold a noncharacter is read: an `EF` lead or any four-byte one.

## user.php's remaining functions, and sign-in cookies checked by scheme (2026-10-08)

Probes `user-more` and `auth-cookies`. Six user.php functions real;
`new_user_email_admin_notice` (a notice on wp-admin's profile screen) is an
admin dead end.

- **Profile keys.** `_get_additional_user_keys` lists the profile's own meta
  keys (first_name to locale, infinite_scrolling among them) and then the
  user's contact methods. `_wp_get_user_contactmethods` is
  `wp_get_user_contact_methods`.
- **`wp_list_users`**: names by display name (or first and last with
  `show_fullname`), escaped and not linked. The user shown as "admin" is left
  out unless `exclude_admin` is false. It prints `<li>` items, or a
  comma-separated list with `style` none or `html` false, and adds the same
  feed link as `wp_list_authors` (now one helper). It returns null when it
  echoes.
- **`wp_schedule_update_user_counts`** schedules `wp_update_user_counts`
  twice daily from now when none is scheduled. Nothing hooks it on a single
  site.
- **`send_confirmation_on_profile_email`** checks the signed-in user's own
  profile (`$user_id`, else the posted `user_id`; false for anyone else). The
  global `$errors` is made first. The posted email is put back to the
  current one either way, so the profile save never changes it. An unchanged
  address does nothing. A malformed one adds `user_email` ("The email
  address is not correct.", form-field email). One already used adds "is
  already used." and drops the pending request. Otherwise `_new_email`
  holds `{hash, newemail}` and "[site] Email Change Request" goes to the new
  address. It greets the login and links
  `wp-admin/profile.php?newuseremail=hash`, through
  `new_user_email_content`.
- **`get_metadata`** now answers false for an id that is not numeric or is 0
  (a negative one is made absolute), as the reference does. A type without a
  table still falls through to the default.
- **`authenticate`** also carries `wp_authenticate_application_password` at
  20, as on the reference. `wp_authenticate_cookie` is real but, as there,
  not hooked. It keeps a user already found. With no username and no
  password it answers the cookie's user, or `expired_session` ("Please log
  in again.") when a cookie is there but no longer good. Otherwise it
  returns what came in.
- **Sign-in cookies.** `wp_validate_auth_cookie` judges a cookie under its
  scheme (`Auth\Cookie::check`, whose signature `AuthCookies::mint` now
  shares). The checks run in the reference's order, each firing its action:
  `auth_cookie_malformed` (the cookie and scheme as given), `expired` (a
  POST or ajax request gets an hour's grace), `bad_username`, `bad_hash` (a
  scheme without a salt, or none given with the cookie, never matches),
  `bad_session_token`, `valid` (with the user). `wp_parse_auth_cookie`
  keeps the scheme as given, and reads `$_COOKIE` (then the request) for
  the scheme's cookie when none is given. `wp_validate_logged_in_cookie`
  passes a found user through and otherwise checks the `logged_in` cookie.
- **Kept on purpose.** On a served request, a call with no cookie still
  answers with the session the engine checked when it read the request,
  which for a REST call includes its nonce. The reference instead sets the
  cookie's user before init and drops it later, in
  `rest_cookie_check_errors`, when the nonce is missing. Moving to that flow
  (and firing `auth_cookie_*` for the request's own cookies) is open for
  Austin. Outside a served request (WP-CLI, cron) both functions read
  `$_COOKIE` as the reference does. The reference's refusal of the
  logged-in cookie on wp-admin screens is left out, since Minn Admin is not
  served from `/wp-admin/`.

## The HTTP classes (2026-10-08)

Probe `http-classes` (`tests/tools/http-classes-probe.php`). It proves
`WP_Http`, `WP_Http_Cookie`, `WP_HTTP_Response` and
`WP_HTTP_Requests_Response`, and adds WP_Http's ten missing methods. No
request leaves: redirects answer through `pre_http_request`.

- **Legacy readers** (`Http\LegacyHeaders`):
  - `processResponse` splits at the first blank line.
  - `processHeaders` unfolds folded lines and lower-cases names. A repeated
    header becomes a list. Every status line starts the headers over, and a
    line with no colon counts as one (`explode(' ', 3)`). Set-Cookie values
    become `WP_Http_Cookie`s read against the URL.
  - `chunkTransferDecode` keeps the reference's quirk: the CRLF after a
    chunk is never stepped over, so a body decodes only when chunks run
    straight into each other and a final `0` ends it. Any other shape comes
    back unchanged. The engine's own requests use `RawResponse::unchunk`.
- **Cookies.** A Set-Cookie value is URL-decoded. Its domain defaults to the
  URL's host, and its path to the URL's directory (`/page` gives `//`, as
  the reference does), else `/`. An expiry given as a date becomes a
  timestamp. `test()` checks four things:
  - the cookie hasn't expired;
  - the host ends with the domain (dot trimmed, case-sensitive, no label
    boundary, so `notexample.com` passes);
  - the path starts with the cookie's path;
  - the URL's port (or 80/443) is among the cookie's ports.

  `buildCookieHeader` writes one lower-case `cookie` header from raw
  name=value pairs. `normalize_cookies` (the jar `request()` now sends)
  keys a Requests jar by cookie name.
- **Redirects.** `browser_redirect_compatibility` turns any 302 into a GET.
  `request()` now registers it, and also registers `validate_redirects`
  when `reject_unsafe_urls` is set, so a safe request refuses unsafe
  redirect targets. `validate_redirects` throws
  `wp_http.redirect_failed_validation`. `handle_redirects` follows any
  3xx's Location (the last of several, resolved against the URL) with one
  fewer redirect left. A POST answered by 302 or 303 becomes a GET, while
  301 and 307 keep it. It returns false without a Location or a 3xx, and
  "Too many redirects." when none are left.
- `_get_first_available_transport` gives `WP_Http_Curl` (then
  `WP_Http_Streams`) through `http_api_transports`.

## The object cache class (2026-10-08)

Probe `object-cache` (`tests/tools/object-cache-probe.php`) proves
`WP_Object_Cache` through `$wp_object_cache`. `compat-status --list=unproven`
now lists the real symbols no probe names.

- **Bad keys.** A key that is not an integer or a non-empty string is
  refused and reported through `_doing_it_wrong`, naming the method
  (`WP_Object_Cache::get`, `::replace`, `::incr`...). An empty string gets
  "Cache key must not be an empty string."; anything else gets "Cache key
  must be an integer or a non-empty string, {type} given." (6.1.0).
  `replace`, `incr` and `decr` check the key themselves.
- **`incr` / `decr`** read the store directly, so they aren't counted as a
  hit. A value that isn't numeric counts as 0. A numeric string keeps its
  arithmetic (`'7.5'` + 1 is 8.5), and the result never drops below 0. A
  key not stored gives false.
- **`stats()`** prints the hits and misses (each line ending `<br />`), then
  a `<ul>` with each group in the order first written:
  `Group: name - ( N.NNk )`, where N.NN is the serialized group's length
  over 1024 (`ObjectCache::sizes`).

## The database class (2026-10-08)

Probe `wpdb` (`tests/tools/wpdb-probe.php`) proves `wpdb` through `$wpdb` and
adds its 23 missing methods. Writes go to a `TEMPORARY` table, which goes
away with the connection; protected methods are called through reflection.

- **`prepare`** fills placeholders as before (`Runtime\Placeholders`), but
  every literal `%` (from `%%`, a value, or an unknown `%x`) is now left as
  the placeholder escape, as on the reference. `query()` takes those escapes
  out before the `query` filter. `_doing_it_wrong` reports three cases:
  - a query with no placeholder and no arguments ("must have a placeholder");
  - a count that doesn't match the arguments (numbered placeholders each
    count, so `%1$s, %2$d, %1$s` is 3). Too few arguments empty the query;
  - an array or object argument ("Unsupported value type (…)."), which
    fills as `''`.
- **Field processing** follows the reference's chain: formats, then charsets,
  lengths and invalid text.
  - With no format, a field takes `field_types` (now filled by
    `wp_set_wpdb_vars`, 34 integer columns) or `%s`. Value types are no
    longer guessed. A short format list falls back to its first entry.
  - A value its column couldn't keep unchanged (too long, malformed bytes)
    refuses the write. `last_error` then reads "WordPress database error:
    Processing the value for the following field failed: {field}. The
    supplied value may be too long or contains invalid data."
  - An insert or replace with no fields is false.
- **Columns.** `get_col_charset` reads each column's collation: false for a
  column with no text, the table's charset for one it doesn't have.
  `strip_invalid_text_for_column` does what it says (`Utf8::keptBy`: utf8mb4
  drops malformed runs, utf8 four-byte characters too, then the column's
  length). `check_safe_collation` is true for an ASCII query, or a table
  whose text columns are all binary or general.
- **Charset.** `init_charset` (now run by the constructor) settles
  DB_CHARSET and DB_COLLATE through `determine_charset`: utf8 becomes
  utf8mb4, with unicode_ci then unicode_520_ci. So the collation is now
  `utf8mb4_unicode_520_ci`, as the reference reports.
- **The table a query names** (`Support\SqlTable::of`, written from 54
  probed statements):
  - SELECT, EXPLAIN SELECT, INSERT, REPLACE, UPDATE and DELETE with their
    modifiers;
  - DESCRIBE, SHOW COLUMNS, FIELDS or INDEX FROM, and SHOW CREATE TABLE;
  - SHOW TABLES or TABLE STATUS by LIKE (`\_` unescaped, a trailing `%`
    dropped) or by WHERE Name;
  - CREATE, DROP, ALTER, TRUNCATE, OPTIMIZE, REPAIR, CHECK, ANALYZE, RENAME,
    LOCK and LOAD DATA.

  Backticks are dropped. It is false for SHOW KEYS IN, DROP TEMPORARY TABLE,
  SHOW TABLES and SET.
- **The rest.**
  - `parse_db_host` (socket after `:/`, IPv6 in brackets, digits-only port).
  - `quote_identifier`.
  - `escape` and `_weak_escape` are deprecated since 3.6.0 (addslashes);
    `supports_collation` since 3.5.0.
  - `set_blog_id` returns the old id; `select`.
  - `log_query` appends even when `$queries` is null, which is now its
    default.
  - `load_col_info` restores the last query's columns.
  - `timer_start` / `timer_stop` measure.
  - `__get`, `__set`, `__isset` and `__unset` reach protected members.
  - `get_caller` and `check_database_version` (null while the server is new
    enough).

## The theme class (2026-10-08)

Probe `wp-theme` (`tests/tools/wp-theme-probe.php`) proves `WP_Theme` across
Twenty Twenty-Five, Twenty Twenty-Four, Twenty Twenty-Three and a theme that
is not there, and adds its missing methods. Paths are masked to the theme
root and the host. `display('Tags')` is left out: the reference names
feature tags only where wp-admin's feature list is loaded (WP-CLI loads it),
and the rest-themes probe covers the admin and REST sides.

- **Headers.** `Status` reads "publish" when an existing theme leaves it
  out. A theme that is not there has only its `Name` (its folder name) and
  false for every other header. The legacy `author` property and `Author`
  key are the marked-up link, while `Author Name` stays plain. The array
  keys now include `Template Dir`, `Stylesheet Dir`, `Template Files` (its
  PHP files one folder deep, parent's too) and `Stylesheet Files` (its CSS
  files at the top).
- **Templates.** A theme that is not there has none. Any existing theme,
  classic or block, also lists the active block theme's custom templates.
- **Allowed.** `get_allowed()` is `get_allowed_on_network()` (the
  `allowedthemes` site option as a list, so `[false]` on a single site) plus
  `get_allowed_on_site()` (the site's option when it is a list, else `[]`).
  `network_enable_theme` and `network_disable_theme` add to or take from
  that site option.
- **Patterns.** `get_block_patterns()` (`Theme\PatternHeaders`) lists the
  patterns directory by file name: title, slug, description, then whatever
  of viewportWidth, inserter (false for "no"), categories, keywords,
  blockTypes, postTypes and templateTypes the header declares.
  `delete_pattern_cache` and `cache_delete` have nothing to clear.
- **The rest.**
  - `get_core_default_theme` returns WP_DEFAULT_THEME, else the newest
    installed core theme.
  - `load_textdomain` is false for a theme without a Text Domain.
  - `__wakeup` lets a theme be read back from a serialized copy.

## The REST controller base class (2026-10-08)

Probe `rest-controller` (`tests/tools/rest-controller-probe.php`) proves
`WP_REST_Controller` through a subclass with a schema. Protected methods go
through a `call()` proxy on the subclass. The defaults, context filtering,
the response for a collection, the public schema, the parameters and the
slugs already matched; two things changed.

- **`get_fields_for_response`** keeps the schema's properties whose
  `context` includes the request's (all of them when the request names no
  context). An additional field registered without a schema is always
  kept; one with a schema is kept like a property. `_links` comes last.
  `add_additional_fields_to_object` follows that list, so a field with no
  schema is now added.
- **Endpoint arguments** (`rest_get_endpoint_args_for_schema`, so every
  controller's) now carry the property's `description`, after
  `default`/`required` and before the type keywords. That matches the
  reference's arguments in the route index.

## The image editors (2026-10-08)

Probe `image-editor` (`tests/tools/image-editor-probe.php`) proves
`WP_Image_Editor` and `WP_Image_Editor_GD` on a PNG and a JPEG the probe
draws. Files are compared by name and measured size, never by bytes; the
probe's folder is removed. Which editor `wp_get_image_editor()` picks is
left out. The reference prefers Imagick when the extension is loaded, and
the engine's `WP_Image_Editor_Imagick` is still a placeholder, so it always
edits with GD (open).

- **Output format.** A file name given without a mime type names the format
  by its extension (`x.jpg` saves a JPEG, so saving a JPEG to `x.png` writes
  a PNG). Keeping the source's own format keeps the source's extension.
- **Saving moves the editor.** After `save()` the editor stands for the file
  it wrote, in that format, so a later unnamed save is named from it
  (`photo-320x240-160x120.jpg`). `make_subsize` and `multi_resize` put the
  file and format back, so sub-sizes never move it.
- **GD's own steps.**
  - `_resize` returns the resampled GD image, or the dimensions error.
  - `_save` writes a GD image and reports path, file, width, height,
    mime-type and filesize. `resize` and `save` go through them
    (`Media\Canvas::of` and `image()`).
  - `__destruct` has nothing to free.
- **Errors.** A crop with no area is `image_crop_error` "Image crop failed.".
  A missing file reads "File does not exist?".

## The widget classes (2026-10-09)

Probe `widget-classes` (`tests/tools/widget-classes-probe.php`) proves
`WP_Widget` through a small subclass and `WP_Widget_Media` through the image
widget.

- **Registering.** `_register_one` now also registers the type's update
  callback (keyed by the bare `id_base`, `multi_number` -1) and the
  instance's form, through the two new functions:
  - `_register_widget_update_callback` registers once per id base; an empty
    callback unregisters it.
  - `_register_widget_form_callback` registers a control, 250 by 200 unless
    told otherwise, with sizes made integers. Registering it again is
    refused until `widgets_init`. An empty callback unregisters it.

  The probe leaves out re-registering, since the engine's probe runtime
  hasn't fired `widgets_init`.
- **`update_callback`** (an empty stub before) saves the posted form once
  per request. The first instance posted under `widget-{id_base}` goes
  through `update()` with the old instance, then `widget_update_callback`
  (false keeps the old one), and the settings are saved. Naming only the
  type (`id_base` with `multi_number` or `widget_number`) makes an empty
  new instance. The delete path is not done yet.
- **`form_callback(-1)`** draws the template form a new widget copies,
  numbered `__i__`, with no settings.
- **`WP_Widget()`**, the PHP 4 constructor, runs `WP_Widget`'s own
  constructor and reports itself deprecated (4.3.0).
- **Media widgets.**
  - `is_attachment_with_mime_type` goes through `wp_attachment_is`, so an
    attachment with no file is no kind.
  - The image widget sets its own labels ("Add Image", the media-library
    link in `missing_attachment`, a `_n_noop` for the multi state).
  - `reset_default_labels` leaves them, since there is no cache to clear.
  - An image by URL always writes `width` and `height` (empty when zero or
    unknown) and `loading="lazy"`.
- The `wp-query-sql` case "parents in their order" can flake under the api
  suite. Posts tied on `FIELD(post_parent, …)` have no defined order in
  MySQL; it passes alone.

## The translation classes (2026-10-09)

Probe `translation-classes` (`tests/tools/translation-classes-probe.php`)
proves six classes:
- `WP_Translation_Controller`, `WP_Translation_File` (and its MO and PHP
  readers), on a `.mo` and a `.l10n.php` file the probe writes;
- `Translations` and `Gettext_Translations`, in memory;
- `WP_Locale` and `WP_Textdomain_Registry`.

Protected methods go through reflection.

- **Loading.** `load_file` refuses only a file that isn't readable or isn't
  `.mo` or `.php`. One that will not parse reports true but is kept out,
  since the reference drops it the first time it is read.
  `is_textdomain_loaded` answers for the locale the domain was loaded
  under (the current one when none is given). A domain with no locale on
  record answers for any. `get_headers` and `get_entries` take no locale:
  they answer for the current one, so `[]` for a domain loaded only for
  another. `unload_file` is true whether or not the file was loaded.
- **Files.** `error()` returns the stored error without reading the file,
  so null until something reads it. `import` doesn't mark the file read:
  when it is read, the file's own headers and entries are laid over the
  imported ones (so `transform`'s file-less target never reads).
- **Plural rules.** A `Plural-Forms` expression keeps its closing semicolon
  as written (`(n != 1);`) and is parsed without it.
  `parenthesize_plural_exression` writes `?` as ` ? (` and `:` as `) : (`,
  closing the parentheses at the end.
- **Registry.**
  - `has()` is true for a domain never looked up.
  - `get()` falls back to a domain's custom path when no language file is
    found.
  - `get_language_files_from_path` lists `.l10n.php` files with the `.mo`
    ones.

## The POMO stream readers (2026-10-09)

Probe `pomo-streams` (`tests/tools/pomo-streams-probe.php`). The five readers
were generated placeholders and are now real (`classes/POMO_Streams.php`):
- `POMO_Reader` (open to dynamic properties, as on the reference);
- `POMO_StringReader`;
- `POMO_FileReader`;
- `POMO_CachedFileReader` and `POMO_CachedIntFileReader`.

- **Integers.** `readint32` reads four bytes in the reader's byte order
  (`setEndian`, little by default) and is false when fewer are left.
  `readint32array` returns the list keyed from 1 (as `unpack` gives it), or
  false when short.
- **String readers.** A read stops at the end and moves the position no
  further. `seekto` clamps to the length and returns the new position.
  `read_all` returns the rest from where the reader stands. A cached reader
  reads the file into a string up front; a missing file gives length 0.
- **File reader.** It reads from an open handle and doesn't count, so
  `pos()` stays 0. `seekto` returns whether it could move. `read_all` returns
  the rest of the file. After `close()`, `is_resource()` is false.
- Each class keeps its PHP 4 constructor, which runs the class's own
  constructor and reports itself deprecated (5.4.0).

## The embed classes (2026-10-09)

Probe `embed-classes` (`tests/tools/embed-classes-probe.php`) proves
`WP_oEmbed` and `WP_Embed`. Every HTTP request answers through
`pre_http_request`; the probe's post, meta and caches are removed.

- **Providers.** `wp_oembed_add_provider` and `wp_oembed_remove_provider`
  now change the live `WP_oEmbed` table once plugins have loaded, and the
  table it starts with before that (`_add_provider_early`). They wrote to
  a runtime list nothing read before.
- **Discovery** (a stub before). The page is fetched with
  `oembed_remote_get_args` (150 KB limit), and its head's `<link>` tags are
  read by `oembed_linktypes` (`Runtime\OEmbed::discovered`). The first JSON
  endpoint wins, else the first XML one, entity-decoded; false when there
  is none.
- **Markup.** `_strip_newlines` passes anything without a newline through
  unchanged, so a photo with no size stays false rather than `''`.
- **The `[embed]` cache.** The shortcode stores results on the post: the
  global post when no `post_ID` is set. Keys are `_oembed_{md5(url +
  serialized attributes with the embed defaults)}`, the same as the
  reference, so existing caches are found. A result gets an
  `_oembed_time_` stamp; a URL that yields nothing gets `{{unknown}}` and
  then a plain link. The cache answers while `usecache` is on, or while it
  is younger than `oembed_ttl`. `embed_oembed_discover` decides discovery.

## The abilities classes (2026-10-09)

Probe `abilities-classes` (`tests/tools/abilities-classes-probe.php`)
proves `WP_Abilities_Registry`, `WP_Ability_Categories_Registry`,
`WP_Ability` and `WP_Ability_Category`. Registrations live for the request
only; the probe writes nothing.

- **The registries** are new. Each keeps one instance (`get_instance()`),
  and the first call fires its init action. The init actions now pass the
  registry; before, they fired with no arguments. Rows stay in
  `Runtime\Abilities`, and the registries hand them out as objects. The
  `wp_*_ability` functions are thin calls on the registries.
- **Registering outside the action.** The registries accept a registration
  at any time. Only `wp_register_ability` and
  `wp_register_ability_category` refuse one outside the init action. That
  check moved out of `Runtime\Abilities`.
- **Check order.** Name shape and duplicates come first. Then an
  unregistered category is named before any other problem: an ability with
  an empty label and an unknown category is refused for the category.
  After that come the property checks.
- **Property checks** (`prepare_properties`) run in the constructor and
  throw `InvalidArgumentException`. The registry reports the message with
  `_doing_it_wrong`. The ability checks, in order:
  1. label, description and category strings;
  2. a callable `execute_callback`, then `permission_callback`;
  3. array `input_schema` and `output_schema`;
  4. a `meta` array, an `annotations` array, then boolean `show_in_rest`
     and `public`.

  A category checks its label and description strings and a `meta` array.
  Neither class checks the name or slug shape, or whether the category is
  registered.
- **Meta defaults.** `annotations` always comes first, merged over the
  three defaults; extra annotation keys are kept. An unset `show_in_rest`
  takes `public`'s value, and `public` defaults to false.
- **A run.** `execute()` first fills input left out from the input
  schema's top-level `default` (`normalize_input`). It then validates,
  checks permission, and calls `do_execute` (through `invoke_callback`,
  which passes no argument for null input). `validate_output` comes last.
  `AbilityRun` now holds only the checks.
- All four classes refuse `serialize()` and `unserialize()` with a
  `LogicException` naming the class. `has_permission()`, which the
  reference doesn't define, was removed.

## The abilities REST controllers (2026-10-09)

Probe `abilities-rest-controllers`
(`tests/tools/abilities-rest-controllers-probe.php`) calls
`WP_REST_Abilities_V1_List_Controller`,
`WP_REST_Abilities_V1_Categories_Controller` and
`WP_REST_Abilities_V1_Run_Controller` directly, as a plugin would. It also
reads the routes they register.

- **Who serves the namespace.** The three classes now serve
  `wp-abilities/v1` over HTTP. `create_initial_rest_routes()` registers
  them on `rest_api_init`, in the reference's order: categories, run,
  list. The run route has to come before the item route, because the
  item route's name pattern would also match `.../run`.
  `Minn\Rest\AbilitiesController` is gone, and the routes now go through
  the runtime's server like any plugin's. The namespace index and the run
  route's OPTIONS answer are byte-identical to the reference.
- **Schemas** are captured data (`data/rest-schemas.json`, read through
  `Minn\Rest\SchemaData`), and each controller adds its own callbacks. The
  run route's `input` argument is sanitized by
  `sanitize_input_for_ability`.
- **Lists.** Only abilities shown in REST are listed. They narrow by
  `category`, `namespace` (the name's prefix) and `meta`, where every field
  given must match exactly with `===` (`'true'` doesn't match `true`).
  Lists are paged with `X-WP-Total` and `X-WP-TotalPages` (as strings)
  and a `Link` header that rebuilds the request's query with the page
  replaced. A page past the end returns an empty list, still with `prev`
  set to page − 1. A direct call without `per_page` would divide by zero
  on the reference; the engine treats it as 1.
- **Items.** `get_item` refuses an ability hidden from REST
  (`rest_ability_not_found`). An item's links travel on the response, not
  in its data. `_fields` doesn't narrow an item's data; it only drops the
  links unless it names `_links`. Unknown names get no
  `_doing_it_wrong`.
- **Reading** needs a signed-in caller: the permission checks return a
  bare `false` for nobody.
- **A run is judged in the permission check**, in this order:
  1. the ability, which must be shown in REST (404);
  2. the method: GET when read-only, DELETE when destructive and
     idempotent, otherwise POST (405);
  3. the input, normalized and validated (400);
  4. the ability's permission. `false` becomes `rest_ability_cannot_execute`
     and an error is kept, both at `rest_authorization_required_code()`.

  `execute_ability` only finds the ability (shown in REST or not) and runs
  it, returning its error unchanged. Input comes from the query on GET
  and from the JSON body otherwise. `sanitize_input_for_ability` coerces
  input to the schema only when the input validates; otherwise it leaves
  the input as given.
- **Route arguments as registered.** `register_rest_route` no longer
  writes the default `validate_callback` and `sanitize_callback` into the
  route table: the reference's table shows an argument as given. The
  defaults now apply at dispatch (`WP_REST_Request::has_valid_params` and
  `sanitize_params`), where the reference applies them. This affects
  every plugin route.

## The dependency classes (2026-10-09)

Probe `dependencies-classes` (`tests/tools/dependencies-classes-probe.php`)
proves `WP_Dependencies`, `WP_Scripts` and `WP_Styles`, mostly on fresh
instances so the page's queues stay untouched. The classes are now split
into one file each.

- **Printing goes through the classes.** `wp_print_scripts`,
  `wp_print_head_scripts`, `print_footer_scripts`, `wp_print_styles` and
  `print_admin_styles` call `do_items`, `do_head_items` and
  `do_footer_items`. The internal `_minn_print_*` helpers and
  `Assets::toPrintHandles` are gone.
- **`wp_print_scripts()` with no handles** now prints every queued script,
  the footer's too, as the reference does. It used to print the head
  group only.
- **`all_deps`.**
  - It puts each handle's dependencies before it in `$to_do`, and keeps
    `handle?args` as `$args`.
  - A handle is passed over when it is already done, or already lined up
    in a group as low.
  - A handle whose dependency is not registered is skipped, and so is
    anything that depends on it. It still gets its group, and a notice
    names it once (attributed to `WP_Scripts::add` / `WP_Styles::add`,
    6.9.1). Inside recursion the walk returns false; at the top it returns
    true.
  - The engine's own default handles count as registered here
    (`Assets::registered`), because the engine has no items for them.
- **Groups.** A script's group is its own (1 for the footer), lowered to
  the group of whatever depends on it. `set_group` refuses to raise a
  group. In the head pass, `do_item` holds back a footer-group script in
  `$in_footer`, and also a script that asked for `defer` or `async`, has
  inline code after it, and has nothing enqueued that depends on it.
  `reset()` leaves `$in_footer` alone.
- **Loading strategy and fetch priority** (`Runtime\ScriptLoading`).
  - Only enqueued dependents count: queued, or a dependency of something
    queued, however deep.
  - Strategy: a script keeps the one it asked for unless it has inline
    code after it, or one of those dependents loads more strictly
    (blocking, then defer, then async). The tag keeps what was asked for
    in `data-wp-strategy`.
  - Fetch priority rises to the highest among those dependents (low, then
    auto, then high). The tag carries `fetchpriority` (left out for auto)
    and the script's own value in `data-wp-fetchpriority` when it changed.
  - The filter sees the attributes in this order: src, id, the strategy,
    data-wp-strategy, fetchpriority, data-wp-fetchpriority.
  - `add_data` refuses a strategy other than defer or async (6.3.0) and a
    fetch priority other than auto, low or high (6.9.0), each with a
    notice. An empty value is allowed, and an empty priority is stored as
    auto. `wp_register_script` now passes `strategy` and `fetchpriority`
    through `add_data`, so it gets the same refusals.
- **Versions.** A version that is `false` or `''` prints as the WordPress
  version, for scripts and styles alike; `null` prints none.
  `wp_register_script(..., '')` used to print no version. The registries
  are made while the facade loads, before `$wp_version` is set, so the
  default version comes from the runtime.
- **Styles.**
  - An alt stylesheet gets rel `alternate stylesheet`, and a title gets a
    `title` attribute.
  - When the text direction is right to left, an rtl of `replace` prints
    the `-rtl` file in its place (id `{handle}-rtl-css`). An rtl address
    prints as a second link after the stylesheet's own.
- **Etag.** `get_etag` is `W/"md5"` over `WP:{version};` followed by
  `{handle}:{ver};` for each registered handle. A null version counts as
  the WordPress version and `false` as empty.
- **Edits are handed back.** The view now hands a plugin's direct edits to
  `$queue` and `$done` back to the registry before it refreshes. Before,
  `$scripts->done = []` followed by `enqueue()` put the old done list back.

## The streaming block processor (2026-10-09)

Probe `block-processor` (`tests/tools/block-processor-probe.php`) proves
`WP_Block_Processor`. It walks 38 block documents token by token, then
checks the narrower walks, extraction, attributes and the name helpers.
The reading is `Minn\Blocks\BlockStream`; the facade class adds the walks
and extraction.

- **What counts as a delimiter.** An HTML comment of the form: whitespace
  after `<!--`, an optional `/` (the closing flag), `wp:`, then a name.
  - The name is lowercase, starts with a letter, may contain `_` and `-`,
    and has at most one namespace (`a/b`).
  - Whitespace follows the name, then optional JSON-object attributes
    followed by whitespace, then `-->` or `/-->`, with the `/` right
    before `-->`.
  - Anything else is HTML: `<!--wp:x-->`, an uppercase name, `ns/a/b`,
    `[1,2]` as attributes, `-- /-->`.
  - A closer with the void flag is void, with the closing flag set.
  - The comment ends at its first `-->`, so `"} -->"` inside a JSON
    string ends the delimiter there (JSON error 3, and the rest is HTML).
    `<!-->` and `<!--->` are complete comments.
- **HTML tokens.** HTML between delimiters is a token of its own. It
  reports type void and block type null. Its printable type is
  `core/freeform` at the top level and `#innerHTML` inside a block, and it
  pushes `#html` onto the breadcrumbs. It comes out before the delimiter
  that ends it, which has already been read, so `has_closing_flag()` shows
  that delimiter's flag (at the end, the last one's).
- **Quirks kept on purpose:**
  - Trailing HTML after the last delimiter is dropped when it contains a
    comment. A document that is only HTML comes out whole.
  - An unterminated comment is incomplete input only while it could still
    become a delimiter: `<!--`, then whitespace, then a prefix of `wp:` or
    `/wp:`, or a cut-off delimiter. Any other unterminated comment just
    ends the stream, with no error and without the HTML before it.
  - `get_attributes()` throws ("Lazy attribute parsing not yet
    supported"). `allocate_and_return_parsed_attributes()` parses on each
    call, and only it sets `get_last_json_error()`.
  - `opens_block()` with no type is true unless the last delimiter read
    was a closer, even before the first token. HTML opens a block only at
    the top level.
- **Walks.**
  - `next_delimiter()` skips HTML; given `*`, it stops at everything.
  - `next_block()` also skips closers; given `*`, it stops at HTML too,
    inner HTML included.
- **Extraction.** `extract_full_block_and_advance()` returns the block in
  `parse_blocks` shape.
  - HTML (as a freeform block) or a void block moves on to the next token.
  - Anything else gathers tokens until a closer drops below its depth, and
    stops on that closer. This also applies when called on a closer or at
    the end, which gives a block named after the closer or a null block.

## The icons API (2026-10-09)

Probe `icons` (`tests/tools/icons-probe.php`) proves the icon functions,
`WP_Icons_Registry`, `WP_Icon_Collections_Registry`,
`WP_REST_Icons_Controller` and `WP_REST_Icon_Collections_Controller`.

- **The default library is GPL art.** The reference's 88 icons are SVG
  files the engine does not ship. `_wp_register_default_icons()`
  registers nothing, and the probe narrows lists to its own collection.
  The default collection (`core`, "WordPress") is registered, as the
  reference registers it. Each registry registers its defaults when it is
  first made.
- **Collections.**
  - The slug is lowercase letters, digits, `-` and `_`, starting and
    ending with a letter or digit.
  - Refusals come in this order: duplicate, slug, unknown property, label
    not a string, description not a string (7.1.0).
  - Stored as description (default `''`), label, slug.
  - Unregistering a collection drops its icons.
- **Icons.**
  - Named `collection/icon`. The name is refused first: duplicate, no
    namespace, uppercase, then characters, then an unregistered
    collection (7.1.0).
  - Then the properties: unknown, label not a string, not exactly one of
    `content` and `file_path`, content not a string (7.0.0).
  - Content is sanitized when registered. It keeps only `svg` (xmlns,
    width, height, viewbox, role, class, focusable, aria-hidden), `path`
    (d, fill, fill-rule, transform) and `polygon` (points, fill,
    fill-rule, transform); the text of tags it strips stays.
  - A file is read, sanitized and kept the first time it is needed. A
    missing file gets a `wp_trigger_error` notice and no content.
  - Search matches the name or the label, ignoring case.
- **`wp_get_icon`.**
  - A label makes the SVG `role="img"` with an `aria-label`; without one
    it gets `aria-hidden="true" focusable="false"`.
  - A numeric size (24 by default) sets width and height; anything else
    sets neither. A class is added.
  - New attributes come before the SVG's own, sorted.
  - An unknown icon, or one without an SVG, gives `''`.
- **REST.**
  - Both controllers are registered in `create_initial_rest_routes()`
    with the reference's routes, including `/icons/{collection}`.
  - Reading needs `edit_posts`; otherwise `rest_cannot_view` at the
    authorization status.
  - The icon list narrows by `collection`, where an unknown one is
    `rest_icon_collection_not_found` (404), and by `search`. It isn't
    paged.
  - Items carry name, label, content (left out when there is none) and
    collection, narrowed by `_fields` and context. Collections carry
    slug, label and description.
  - The controllers' collection parameters give context a default of
    view. Schemas are captured in `data/rest-schemas.json`.
  - Over HTTP, the OPTIONS answers and the namespace index match the
    reference byte for byte.

## The query clause internals (2026-10-09)

Probe `query-clause-internals`
(`tests/tools/query-clause-internals-probe.php`) calls the steps of
`WP_Date_Query`, `WP_Meta_Query` and `WP_Tax_Query` directly; a subclass
opens the protected ones.

- **Date values checked** (`validate_date_values`).
  - Range notices come in this order: dayofyear, dayofweek, dayofweek_iso,
    month, week, day, hour, minute, second. The function is
    `WP_Date_Query`, version 4.1.0.
  - Week runs up to the given year's ISO week count, 53 when no year is
    given.
  - A month and day are checked as a date (in a leap year when no year is
    given) only once every part is in range. `monthnum` isn't part of
    that check.
  - Before and after given as parts are checked too. An empty clause is
    not valid. These fixes reach `sanitize_query` as well.
- **Values and times** (`build_value`, `build_time_query`).
  - IN takes a list of numbers, a lone number included; anything
    non-numeric is false.
  - BETWEEN takes exactly two values; one alone is false.
  - Other comparisons take one number, cast to an integer; a list is
    false.
  - A clause leaves out a part whose value is false or 0. Before, the
    engine printed a one-value BETWEEN as `n AND n` and a non-numeric IN
    as `(0)`.
  - Time: no part gives false; a list or range is per part; one part uses
    its function; an hour and a second without a minute give `''`.
- **Per query and clause.** `get_sql_for_query` returns join and where
  strings, indented for the depth. `get_sql_for_clause` returns lists of
  where and join pieces and settles the clause in place:
  - meta adds compare, compare_key, alias and cast;
  - tax turns terms into term_taxonomy_ids and adds the alias of an IN
    clause.

  `get_sql_clauses` is the whole query with a leading ` AND `.
- **Aliases.** `find_compatible_table_alias` returns the alias of the
  first sibling under OR that already has one and reads the same join.
  For meta, that means both comparisons are in the compatible set (not
  NOT EXISTS). For tax, both clauses use IN. The builders now write each
  alias into the clause, so siblings find it there, where before they
  kept their own list. Each facade keeps one builder, so aliases go on
  counting across calls, as the reference's do.
- **Meta joins.** A clause's join is LEFT for NOT EXISTS and INNER
  otherwise. Only `get_sql` turns every join LEFT when an OR group holds a
  NOT EXISTS; `get_sql_clauses` alone keeps INNER joins.

## The query classes' compatibility doors (2026-10-09)

Probe `query-compat` (`tests/tools/query-compat-probe.php`) proves the
magic methods and smaller steps of `WP_Query`, `WP_User_Query`,
`WP_Comment_Query` and `WP_Term_Query`. It makes a three-comment thread
and removes it afterwards.

- **`WP_Query`.**
  - `__get` and `__isset` read `query_vars_hash` and
    `query_vars_changed`. They stay public because engine code writes
    them; any other name reads null.
  - `__call` reaches `init_query_flags` and `parse_tax_query` (whose
    by-reference argument fails when called this way, as on the
    reference), and answers false for anything else.
  - `is_comments_popup()` is deprecated (4.5.0) and returns false.
  - `generate_postdata()` returns the postdata globals without setting
    them. Its page comes from its own query. `more` is set on a single, a
    page or a feed (from the main query), or past the first page of a post
    split into pages; `setup_postdata` now gets that last case too.
- **`WP_User_Query`.**
  - The magic methods read, write, check and unset `results` and
    `total_users`. Any other name gets a `wp_trigger_error` deprecation
    and is neither read nor set.
  - `__call` reaches `get_search_sql`.
  - The search matches ID exactly and everything else by LIKE, with
    leading, trailing or both wildcards.
  - The cache key is `get_users:` and the md5 of the SQL alone.
  - The last-changed list is the users' stamp, plus the posts' when the
    query asks for published posts or orders by `post_count`.
- **`WP_Comment_Query`.**
  - `__call` reaches `get_search_sql`, a LIKE over the given columns.
  - `get_comment_ids()` writes the request and returns the IDs (the
    runner's `ids()`, now shared with the full run).
  - `fill_descendants()` fills in the thread, flat or threaded.
- **`WP_Term_Query`.**
  - `parse_orderby_meta()` is `TermOrder::meta`, now public.
  - The cache key takes the vars the query knows, in their own order,
    leaves out the two cache switches, and reads every `fields` value
    except count and all_with_object_id as `all`.

## The font library (2026-10-09)

Probe `fonts` (`tests/tools/fonts-probe.php`) proves the font functions,
`WP_Font_Face`, `WP_Font_Face_Resolver`, `WP_Font_Utils`,
`WP_Font_Collection` and `WP_Font_Library`. It removes the font family,
face, file and collection file it makes.

- **One path for @font-face rules** (`Minn\Theme\FontFaces`).
  - The page prints its theme's fonts through `wp_print_font_faces` on
    `wp_head` at 50, as the reference does. The block and classic
    renderers no longer print their own. The block (`<style
    class="wp-fonts-local">`) is byte-identical to the reference's over
    HTTP.
  - A rule lists family, style, weight, display (fallback unless it is
    auto, block, fallback, swap or optional), the sources, then any other
    known face property in the order given.
  - Sources: data URIs first, then woff2, woff, truetype,
    embedded-opentype, opentype. An unknown extension (svg, `?v=1`, none)
    is dropped.
  - A family name is quoted unless it is one word or already carries
    quotes. `font-variation-settings` given as an array runs together
    (`wght 400wdth 100`), as on the reference.
- **Fonts from theme.json.** `get_fonts_from_theme_json` reads the merged
  settings, and `get_fonts_from_style_variations` reads every variation.
  Each face is its sources (`file:./` through `get_theme_file_uri`), then
  its family's first name unquoted, then its other properties in kebab
  case.
- **Collections.**
  - A slug that sanitizes differently is fixed, with a notice.
  - Data is read when first asked for: the args over a description of
    `''` and no categories, with font families taken from a JSON file or
    address when one is given in their place.
  - The data is kept to the collection schema. A missing file, name or
    font families gives a `WP_Error` with a notice, and the error is kept.
  - Registering a duplicate is a `WP_Error`; unregistering an unknown
    slug is false. Both give notices.
- **The default collection is left out.** The reference registers Google
  Fonts read from a wordpress.org address. The engine contacts no
  wordpress.org address, so `_wp_register_default_font_collections`
  registers nothing (flagged to Austin).
- **The fonts directory** is uploads with `/fonts` as its base and no
  dated subdirectory, through `font_dir`. Deleting a font family deletes
  its faces, and deleting a face removes its file (`deleted_post` and
  `before_delete_post`).
- **Utilities.**
  - `sanitize_font_family` quotes each name of more than one word.
  - The face slug is family list, style, weight (`bold` read as 700),
    stretch and unicode range.
  - `sanitize_from_schema` keeps only the schema's keys, and drops a list
    or object given a single sanitizer.
- `wp_register_font_library_page_routes` (editor) and
  `wp_register_font_library_wp_admin_page_routes` (admin) are dead ends.

## Plugin dependencies (2026-10-09)

Probe `plugin-dependencies` (`tests/tools/plugin-dependencies-probe.php`)
proves `WP_Plugin_Dependencies`.
- **The setup.** The probe writes fourteen probe plugins whose Requires
  Plugins headers name each other: one missing, two loops, a
  three-plugin cycle, a plugin that requires itself, and slugs that are
  not slugs. It removes them afterwards.
- **Isolation.** A filter answers which plugins are active, and another
  answers the plugins API. A subclass resets the class's protected
  statics between steps.

- **Headers.**
  - A header's slugs are lowercase words joined by single hyphens. Each
    goes through `wp_plugin_dependencies_slug`, then they are made
    unique and sorted. `Bad Slug`, `ZZ-Upper`, `zz_under`, `-zz` and
    `zz--two` are dropped.
  - A plugin's slug is its folder, or its file name without `.php`;
    `hello.php` is `hello-dolly`.
- **Both directions.**
  - `has_dependents` and `get_dependents` read the dependency slugs.
  - The dependency file paths follow the order the slugs were first seen
    in, with false for a missing plugin.
  - A dependency is unmet when it is missing or inactive.
  - `get_dependent_names` sorts the dependents' names.
    `get_dependency_names` maps each slug to the installed plugin's
    name, else the API's, else the slug itself.
- **Loops.**
  - From each dependent, the walk follows each dependency's own
    dependencies, leaving out slugs already on the path. It records
    `[dependent, dependency]` where the dependency needs the dependent
    back, and `[slug, slug]` for a slug that requires itself.
  - The pairs repeat as the reference's do; the cycle experiment gives
    the same output byte for byte. Each pair also adds its slugs to the
    circular list that `has_circular_dependency` reads.
  - The admin notice lists each loop once, in first-seen order.
- **The plugins API.** `get_dependency_api_data` asks only on admin
  screens; elsewhere it returns nothing and `get_dependency_data` is
  false. On the engine, Minn's update service would answer, never
  wordpress.org.
- **Notices.** The unmet-dependencies notice links to the plugins screen
  except on that screen.
- **The AJAX check.** `check_plugin_dependencies_during_ajax` answers the
  plugin install screen's JSON check. It sends and exits, so the probe
  leaves it out.

## Speculative loading (2026-10-09)

Probe `speculative-loading` (`tests/tools/speculative-loading-probe.php`)
proves the speculative-loading functions, `WP_Speculation_Rules` and
`WP_URL_Pattern_Prefixer`.

- **Every theme now prints the rules.** Signed-out visitors under pretty
  permalinks get the `<script type="speculationrules">` block on
  `wp_footer`. Before, only classic themes printed it. Over HTTP, the
  block is byte-identical to the reference on the front page, a post, a
  search, an archive and a 404.
- **Configuration.**
  - `wp_speculation_rules_configuration` starts from auto/auto, or from
    null for a signed-in visitor or plain permalinks, and null means off.
  - Auto, unknown values and a non-array settle on prefetch and
    conservative; immediate eagerness becomes conservative.
- **The site's rule.**
  - It is one document rule for every link under home.
  - It leaves out `wp-*.php`, wp-admin, uploads, content, plugins, the
    themes (once when template and stylesheet are the same) and query
    strings. Then come the paths `wp_speculation_rules_href_exclude_paths`
    adds (it starts from an empty list and is given the mode), each under
    home.
  - It also leaves out nofollow links and those marked `no-{mode}`, with
    no-prefetch as well for prerender. `wp_load_speculation_rules` may add
    more rules.
- **The rules object.**
  - A rule needs a known mode and an id that starts with a lowercase
    letter, unused in any mode.
  - It has either `urls` or `where`, not both, and a source that agrees
    with them; an eagerness, when it names one, must be valid. Each
    refusal is a 6.8.0 notice.
  - The object serializes as each mode's rules, without their ids.
- **The prefixer.**
  - A context's path is escaped for URL patterns (`+ * ? : { } ( ) \`).
    It is grouped in braces when it holds `?` or `:`.
  - It is joined to the pattern by one slash. An unknown context gives a
    notice and leaves the pattern as it is.
- **Still owed.** `wp_get_speculative_loading_override` stays missing. No
  query arg, constant, environment variable or option changed its answer
  on the reference, so its source is unknown.

## Category and nav menu helpers (2026-10-09)

Probe `category-menu-helpers`. It covers category-by-path lookup,
category sanitizing and cache cleaning, the tag cloud's sort callbacks and
count scale, and the menu item helpers: validity, associated items, the
item cache, removal with a deleted term, and the two menu template filters.

- **Categories by path.**
  - Each path segment is sanitized as a title, so case does not matter.
    Leading and trailing slashes are dropped.
  - With a full match (the default), the category's whole ancestry must
    spell the path; a child named alone is null. Without one, the last
    segment's slug is enough.
  - A query string in the path matches nothing.
  - The output argument is honoured (`ARRAY_A` gives an array).
- **Sanitizing.**
  - `sanitize_category` and `sanitize_category_field` are the term
    sanitizers with the taxonomy fixed to category. A raw array keeps
    `filter` raw.
  - `clean_category_cache` returns null. It cleans the term's cache only,
    so a value set in the `category` group survives.
- **Tag cloud helpers.**
  - The count callback returns the difference of the counts.
  - The name callback is a natural, case-insensitive comparison.
  - `default_topic_count_scale` is `round(log10(count + 1) * 100)`.
- **Menu items.**
  - `_is_valid_nav_menu_item` is true unless the item is marked invalid.
  - `wp_get_associated_nav_menu_items` lists the items pointing at an
    object, in menu order. The taxonomy argument narrows only taxonomy
    items; for a post type it is ignored.
  - `update_menu_item_cache` primes the posts the items point at.
  - `_wp_delete_tax_menu_item` runs on `delete_term` and deletes every
    item pointing at the term. Their children stay, with their parent meta
    unchanged.
- **Menu template filters.**
  - `_nav_menu_item_id_use_once` (on `nav_menu_item_id`) gives an item's
    id once per request; a second use of the same item gets ''.
  - `wp_nav_menu_remove_menu_item_has_children_class` (on
    `nav_menu_css_class`) drops `menu-item-has-children` when the menu's
    depth limit is set and the item sits at its last level. The other
    classes keep their keys.
- **Still owed.** `_wp_reset_invalid_menu_item_parent` stays missing. No
  menu or post write on the reference showed it changing a parent, so its
  trigger is unknown.

## Script loader helpers (2026-10-09)

Probe `script-loader-helpers`. It covers the dataset name converters,
relative stylesheet links, block node filtering, the polyfill loader,
prototype before jQuery, the concatenation printers, localized data,
just-in-time localization, the style engine's stored rules, block style
variations' assets, every registered block's assets, and the development
scripts.

- **Dataset names.**
  - `wp_js_dataset_name` needs a `data-` prefix in any case. The rest is
    lowercased, and each hyphen before a letter becomes that letter in
    capitals (`data-wp-on--click` is `wpOn-Click`).
  - `wp_html_custom_data_attribute_name` turns each ASCII capital into a
    hyphen and its lowercase, after `data-`.
  - Both give null for a name holding whitespace, `/`, `=` or `>`. Quotes,
    `<`, `&` and non-ASCII letters pass.
- **Relative stylesheet links.**
  - Every `url(` reference is read after optional whitespace, a quote and
    more whitespace, up to a quote or `)`; the match need not close.
  - A reference starting with `/`, `#`, `data:`, `http:` or `https:`
    (case-sensitive) is left alone. One leading `./` is dropped.
  - The rest is joined to the stylesheet URL's `dirname` with a slash, then
    made host-relative, so a stylesheet at `/` turns `url(i.png)` into
    `url()`, as the reference does.
- **Block nodes.** A node is dropped when `blocks` is anywhere in its path.
  Keys are kept.
- **The polyfill loader.**
  - One `( test ) || document.write( '<script src="…"></scr' + 'ipt>' );`
    per registered handle, run together, with the test unescaped.
  - A source not starting with `//` or `http(s)://` gets the registry's
    base URL, with no slash added. A version is added only when the handle
    has one.
  - Then `script_loader_src` runs; a source it empties is skipped. The
    rest goes through `esc_url`.
- **Prototype before jQuery.** Prototype's first copy moves to just before
  jQuery's first copy when it came later. Anything else is unchanged.
- **The concatenation printers.**
  - They print only when the trimmed concat list (`, ` trimmed) is not
    empty; `do_concat` is not consulted. The registry's `print_html`
    prints last, always. Nothing is reset.
  - Scripts print their held code as an inline tag, with
    `//# sourceURL=js-inline-concat-{rawurlencoded list}`. Then comes the
    `load-scripts.php?c={0|1}` tag through `esc_url`, the list in
    128-character `load[chunk_N]` pieces, and `ver` as the registry's
    default version.
  - Styles print a `load-styles.php` link first (through `esc_attr`,
    `dir` from the registry's text direction), then the held CSS with a
    `/*# sourceURL=css-inline-concat-… */` trailer.
  - `c` is 1 when `$compress_scripts` or `$compress_css` is truthy.
- **Localized data** (shared machinery, `Runtime\Assets`).
  - `wp_localize_script` appends its `var` line to the script's `data`
    string, joined by a newline. `add_data('data')` replaces the whole
    string. `extra['data']` and `get_data()` show it, and
    `print_extra_script` prints it.
  - An `extra['data']` set in place on `$wp_scripts->registered` is handed
    to the registry before the next data change, so a later localize
    appends to it.
  - Before this, the engine kept localized objects in a list of its own:
    `get_data` never saw them, and raw data was not printed.
- **Just-in-time localization.**
  - autosave gets `autosaveInterval` (the constant) and `blog_id`.
  - mce-view and word-count get the registered shortcode tags; word-count
    also gets the type `words`.
  - `[embed]` is now registered as `__return_false`, as on the reference,
    so `strip_shortcodes` and `do_shortcode` drop it outside the_content's
    own embed pass. The default shortcodes are registered in the
    reference's order (`playlist` after `gallery`).
- **Stored styles.**
  - Each style-engine store with CSS is registered without a source,
    given the CSS inline and enqueued, on every call.
  - Block supports use `core-block-supports` (headed by a `Core styles:
    block-supports` comment when prettified). Any other context uses
    `wp-style-engine-{context}`.
  - Prettify defaults to `SCRIPT_DEBUG`.
  - **Not hooked**: the reference runs it on `wp_enqueue_scripts` and
    `wp_footer` 1. The engine prints the block-supports store inside
    `global-styles-inline-css` (the styles suite checks the container
    rules there), so hooking it would print those rules twice. Rules a
    plugin stores in another context are therefore not printed for it
    yet.
- **Block style variations** (`enqueue_block_assets` 30).
  - An inline style joins `wp-block-{name without core/}` when that handle
    is registered, otherwise `wp-block-library`. It is added again on
    every call.
  - A `style_handle` is enqueued at once when block assets do not load on
    demand. Otherwise it is enqueued when a block of that name renders,
    whatever its class.
  - The engine registers neither `wp-block-library` nor the per-block core
    handles. A core block's variation CSS (Twenty Twenty-Five's checkmark
    list) therefore has nowhere to print yet.
- **Every registered block's assets** (`enqueue_block_assets` 10). When
  block assets do not load on demand, every block type's style and script
  handles are enqueued; view and editor handles are not.
- **Development scripts.** Nothing is registered. The reference registers
  none outside development mode, and Minn ships no development builds.
- **Still owed.**
  - `wp_default_packages`, `_vendor`, `_scripts` and `_inline_scripts`
    register the GPL script packages, which the engine supplies through
    the site's script pack instead.
  - `wp_hoist_late_printed_styles` and
    `wp_load_classic_theme_block_styles_on_demand` need a classic theme
    to observe.
  - The editor and admin enqueuers.

## Canonical redirect helpers (2026-10-09)

Probe `canonical-helpers`, plus admin paths in the permalinks suite. It
covers fragments stripped, query args checked against a URL, the 404
guess, the old-slug and old-date finders with their cache, salted cache
entries, and the admin and login addresses a 404 redirects.

- **Fragments.**
  - A URL that names a host is rebuilt as scheme (when present), `//`,
    host, port, path, then `?query`. User and password are dropped.
  - Anything without a host (`/rel#f`, `zz.example/a#f`) is returned as
    given, fragment and all.
- **Query args against a URL.** Each arg to check that the URL's own
  query lacks is removed with `remove_query_arg`; names match exactly, and
  an arg with no value counts as present. Removing everything gives `''`.
- **The 404 guess.**
  - `do_redirect_guess_404_permalink` false turns guessing off, and a
    non-null `pre_redirect_guess_404_permalink` answers first. With no
    `name` var the answer is false.
  - The types are the public types not excluded from search, in
    registration order. A named string type must be one of them, and is
    then `post_type = '…'`. A named list is narrowed to them in its own
    order, and is false with no query when nothing is left; an empty list
    means the defaults.
  - The query is `post_name LIKE 'name%'` (`esc_like`), or `post_name =`
    under `strict_redirect_guess_404_permalink`. Then the type clause,
    `YEAR/MONTH/DAYOFMONTH(post_date)` for each date var that is truthy
    (`'abc'` gives 0), and `post_status IN ('publish')`, with no ORDER BY.
    Both stacks issue the same SQL on one database, so they pick the same
    row.
  - The answer is the comments feed link when a `feed` var is set, else the
    permalink plus `page/` when `page > 1` (PHP's comparison, so `'x'`
    counts), else the permalink.
- **Old slugs and dates.**
  - `_find_post_by_old_slug` joins postmeta `_wp_old_slug`, with the date
    clauses on `post_date`.
  - `_find_post_by_old_date` asks only when a date var is set. It looks by
    name with `_wp_old_date`, then by `_wp_old_slug` with `_wp_old_date`,
    the date clauses on `pm_date.meta_value`.
  - Both are ints (0 for none). They are cached in `post-queries` under
    `find_post_by_old_{slug|date}:md5(first query)`, salted with posts'
    `last_changed`. A planted entry is honoured, and a posts change asks
    again.
- **Salted entries.**
  - `wp_cache_set_salted` stores `['data' => …, 'salt' => …]`, a list of
    salts joined by `:`.
  - `wp_cache_get_salted` gives the data only under the same salt (string
    compare) when both keys are set, so null data reads as false; anything
    else is false. The multiple versions map key by key.
- **Admin locations** (`template_redirect` 1000).
  - On a 404, the request URI with its trailing slashes removed (query
    string kept, case-sensitive) is checked against `wp-admin`,
    `dashboard` and `admin`, as home-relative and site-relative paths. A
    match is a 302 to `admin_url()`. `wp-login.php`, `login.php` and
    `login` give a 302 to `wp_login_url()`.
  - The engine 404'd these before. The probe makes WP-CLI's scheme-forced
    relative URLs relative again, and catches the redirect in a
    `wp_redirect` filter that throws.
- **Still owed.** `url_to_postid` is a path heuristic on the engine, not
  the rule table. It misses a page address with a page number
  (`/zz-page/2/`, which the reference finds); its own batch should move it
  onto `Front\RuleTable`.

## JSON schemas for a client (2026-10-09)

Probe `json-schema`, behind `Rest\ClientSchema`.

- **Keywords.**
  - `wp_get_json_schema_allowed_keywords` gives the REST API's keyword
    list (`rest_get_allowed_schema_keywords`) for any profile but
    `draft-04`, which is case-sensitive.
  - `draft-04` adds `$schema`, `id` and `$ref` before that list, and
    `required`, `allOf`, `not`, `definitions`, `dependencies` and
    `additionalItems` after it.
- **Trimming.**
  - `wp_prepare_json_schema_for_client` defaults to the draft-04 profile.
    `_wp_prepare_json_schema_for_client_with_allowed_keywords` takes the
    keywords as keys; a plain list allows nothing.
  - Keys keep their order. Sub-schemas are trimmed where they are objects:
    - each entry of `properties`, `patternProperties`, `definitions` and
      `dependencies`;
    - the value of `items`, `additionalProperties`, `additionalItems`,
      `not`, `anyOf`, `oneOf` and `allOf`, or each object member when the
      value is a list.
  - Nested lists, strings and booleans stay as given. `enum` and `default`
    are never looked into.
- **required.**
  - A boolean `required` is always dropped.
  - When `properties` is allowed, the names whose `required` is `true`
    (strictly) become the object's list. An object's own `required` list
    wins, and a non-list, non-boolean `required` is replaced where it
    stands.
  - Otherwise the list is appended last, even under a profile that drops
    `required` itself (rest-api).
- **Still owed.** `rest_handle_deprecated_argument`,
  `rest_handle_deprecated_function` and `rest_handle_doing_it_wrong`
  answer with response headers, which a CLI probe cannot see. They wait
  for an HTTP check under `WP_DEBUG`.

## More translation helpers (2026-10-09)

Probe `l10n-more`.

- **PHP translation file headers.** `wp_get_l10n_php_file_data` includes
  the file, so a missing file gives two include warnings and anything the
  file prints is printed. It answers `POT-Creation-Date`,
  `PO-Revision-Date`, `Project-Id-Version` and `X-Generator` from the
  file's lowercase keys, values as given (an int stays an int), and `''`
  for each the file does not give or when it returns no array.
- **has_translation.**
  - It is true when the domain was loaded for the locale (the
    controller's current one by default) and its catalog translates the
    string. A context is keyed `context\4text`, and a plural is keyed by
    its singular.
  - `load_textdomain` makes the locale it loaded for current: the one
    given, or `determine_locale()`. The controller's own `load_file` does
    not.
  - A successful `load_textdomain` also records the folder in the
    textdomain registry under that locale, trailing-slashed; a failed one
    or a bare `load_file` records nothing.
  - The engine's catalogs are still per domain, not per locale, so `__()`
    keeps translating after the current locale moves. The reference stops.
- **Script and module translations.**
  - `_load_script_textdomain_from_src` is the old body of
    `load_script_textdomain`. It looks by handle in the folder given, then
    by the md5 of the source's path, then in the languages folder.
  - It gives the relative path filter a third argument, true for a module,
    and takes the source as given (`place` reads a root-relative one).
  - With no folder given, it looks in the folder the domain loaded from
    for the determined locale.
  - `load_script_textdomain` reads a script's root-relative source on the
    site's origin first. `load_script_module_textdomain` uses a registered
    module's source, and is false with no filters run for an id not
    registered.

## More post template tags (2026-10-09)

Probe `post-template-more`. post-template.php owes nothing now.

- **post_custom.** The current post's field: its value, or the list when it
  has several (serialized values stay strings). False when it has none,
  and for an empty key.
- **the_meta** is deprecated in 6.0.2 for `get_post_meta()`.
  - It prints `<ul class='post-meta'>` with one
    `<li><span class='post-meta-key'>key:</span> values</li>` per visible
    key, protected keys skipped. Values are trimmed and joined by `, `;
    key and values go through `esc_html`.
  - Each item passes through `the_meta_key` (HTML, key, the unescaped
    value). Nothing is printed when no key is visible.
- **the_attachment_link.** It prints `wp_get_attachment_link` with `full`
  for any truthy `$fullsize`, `thumbnail` otherwise, and the permalink
  flag. A non-empty `$deprecated` is a 2.5.0 notice with no message.
- **wp_post_revision_title_expanded.**
  - It reads `{avatar 24} {display name}, {human_time_diff} ago ({date})`,
    the date as `F j, Y @ H:i:s`. The date is linked to the edit link
    (`revision.php?revision=` or the post's own edit screen) only for a
    user who can edit it.
  - It appends ` [Current Revision]` for the post itself and
    ` [Autosave]` for an autosave, then runs
    `wp_post_revision_title_expanded` (title, post, link).
  - It is null for no post, and false for any type but post, page and
    revision (a custom type with revision support too).
- **wp_list_post_revisions.**
  - Nothing is printed without a post or revisions. Otherwise it prints the
    `hide-if-js` notice, then `<ul class='post-revisions hide-if-no-js'>`
    with one tab-indented `<li>` per revision the reader can read.
  - `revision` leaves out the autosave and `autosave` keeps only it; any
    other type lists all. An anonymous reader gets the empty list.

## The site logo and the custom logo (2026-10-09)

Probe `site-logo`. It writes `site_logo` and the theme mods option, then
puts both rows back byte for byte, autoload included, updating in place
so the option id holds.

- **The mod read through the option.** `theme_mod_custom_logo` answers
  the `site_logo` option whenever it exists, raw (`''` and `'0'`
  included), otherwise the mod's own value.
- **Setting.** `pre_set_theme_mod_custom_logo` writes the value to
  `site_logo`, or deletes the option for an empty value (`0`, `''`,
  `false`). The value is returned unchanged.
- **Removing.**
  - At `setup_theme` 11, the active theme's
    `update_option_theme_mods_{stylesheet}` gets a hook: when the old
    mods had `custom_logo` (any value, even 0) and the new ones do not,
    `site_logo` is deleted. `delete_option_theme_mods_{stylesheet}` gets
    another: `site_logo` is deleted when the theme supports
    `custom-logo`.
  - Deleting `site_logo` removes the `custom_logo` mod. While it does, it
    holds back the theme-mods update hook, so the option is not deleted
    in turn. Called on its own, it leaves `site_logo` in place.
- **Header text beside a logo** (`wp_head`). When the theme's
  `custom-logo` support names `header-text` classes (cast to a list, each
  through `sanitize_html_class`) and the `header_text` mod is falsy, it
  prints a tab-indented `<style id="custom-logo-css">` that clips them,
  after an HTML comment. It never has a `type` attribute.

## More widget helpers (2026-10-09)

Probe `widgets-more`.

- **wp_sidebar_description.** A registered sidebar's description goes
  through `wp_kses` with the `sidebar_description` context, so comment
  markup is kept (`<a href>`, `<em>`, `<b>`), other tags are stripped and
  `&` is escaped. It is null when the sidebar is unknown or has no
  description key; `register_sidebar`'s default gives `''`.
- **_wp_remove_unregistered_widgets.**
  - Each list keeps only the widget ids registered, or only those in the
    allowed list when one is given (it replaces the registered ones).
  - The comparison is loose, keys are kept, and a value that is not a
    list is left as it is.
- **_wp_block_theme_register_classic_sidebars** (`widgets_init` 1). On a
  block theme, each sidebar in the `wp_classic_sidebars` theme mod is put
  into the registered sidebars under its own `id` (not its key), exactly
  as stored, replacing any registered one.
- **wp_check_widget_editor_deps** (`admin_head`). With `wp-edit-widgets`
  or `wp-customize-widgets` queued as a script, it raises a 5.8.0 notice
  on `wp_enqueue_script()` when the `wp-editor` script is queued too, and
  on `wp_enqueue_style()` when the `wp-edit-post` style is.
- **Still owed.** `wp_map_sidebars_widgets` (the theme-switch mapping).
  - It matches ids first, then common names, and mixes in the stored
    theme mods' inactive widgets under conditions not yet pinned down.
  - It can send an unmatched sidebar's widgets to a free slot even with
    no name in common.
  - It needs its own run of experiments before it can be written.

## Embed helpers (2026-10-09)

Probe `embed-helpers`. No request leaves: `pre_oembed_result` answers
first, and `pre_http_request` refuses anything else. embed.php owes
nothing now.

- **Default handlers** (`plugins_loaded` 0, `wp_maybe_load_embeds`).
  Nothing loads under `load_default_embeds` false. Otherwise it registers:
  - `youtube_embed_url` (`/v/` and `/embed/` addresses) at 10;
  - `audio` and `video` at 9999, by the audio and video extensions, each
    callback through `wp_audio_embed_handler` / `wp_video_embed_handler`.

  `wp_embed_register_handler` now registers on `$wp_embed`, as on the
  reference. Before, it wrote to a runtime list that WP_Embed never read.
- **YouTube embed addresses.** The id (match 2, urlencoded) becomes
  `https://youtube.com/watch?v=…`, run through `$wp_embed->autoembed`,
  then `wp_embed_handler_youtube` (embed, attr, url, rawattr).
- **Audio files.** The result is `[audio src="{esc_url}" /]` through
  `wp_embed_handler_audio`.
- **Format.** `wp_oembed_ensure_format` passes `json` or `xml` as written;
  anything else is `json`.
- **Untrusted answers** (`wp_filter_oembed_result`, `oembed_dataparse`
  10).
  - Only a rich or video answer whose URL no provider claims is touched.
    The HTML goes through kses (a with href; blockquote with no
    attributes; iframe with src, the size and frame attributes, and
    title).
  - It keeps `(<blockquote>.*?</blockquote>)?.*(<iframe.*?></iframe>)`:
    the first blockquote and the last empty, lowercase iframe. It is false
    without one.
  - One 10-character secret is shared. The blockquote becomes
    `class="wp-embedded-content" data-secret`. The iframe gains the
    class, `sandbox="allow-scripts"` and `security="restricted"` first,
    then the hiding style only beside a blockquote, then its kept
    attributes in order.
  - The `src` is decoded, given `#?secret=` (`esc_url_raw`), escaped, and
    followed by `data-secret`. An iframe without `src` gets neither.
- **Iframe titles** (`wp_filter_oembed_iframe_title_attribute`,
  `oembed_dataparse` 5), now as on the reference.
  - The first iframe's own title (as written) beats the data's.
    `oembed_iframe_title_attribute` (title, result, data, url) runs for
    every rich or video answer.
  - A non-empty result is put first on every iframe, and only the first
    iframe loses its own title attributes. The engine used to add a
    missing title only.
- **The site's own posts** (`pre_oembed_result` 10). For an address that
  is one of the site's posts, the oEmbed data is made into markup through
  `data2html`, so both oEmbed filters run and the src carries two secrets.
  Any other address keeps the result given.
- **The route.** `wp_oembed_register_route` (`rest_api_init`) is a no-op:
  the engine serves `oembed/1.0` itself.
- **Still owed then, done next.** Embeds made outside a post were not
  cached in `oembed_cache` posts; see the next section.

## oEmbed answers kept outside a post (2026-10-09)

Probe `oembed-cache`, in `WP_Embed`.

- **Where they live.**
  - An embed made with no post is kept in an `oembed_cache` post named
    by `md5(url + serialized attributes)`, the post meta key without its
    `_oembed_` prefix.
  - The post is published, with an empty title, author 0, parent 0, and
    comments and pings closed. The markup is stored as it came; kses is
    held back while it is written.
- **find_oembed_post_id.** It answers the post's id as an int, remembered
  in the `oembed_cache_post` cache group, or null when there is none.
- **Reuse.**
  - While `usecache` is on, a stored post always answers, however old.
    With it off, only one younger than `oembed_ttl` (url, attributes,
    null post) answers.
  - A stale post is fetched again and brought up to date (its modified
    time moves).
  - Answers go through `embed_oembed_html` with a null post id; the
    engine used to pass 0.
- **Failures.** An address that fails is kept as `{{unknown}}` and answers
  as a link, or the bare address with `linkifunknown` off. It is not asked
  again.
- **Keys.** Different attributes are a different key, so a different post.

## Template loading helpers (2026-10-09)

Probe `template-loading`. It uses scratch themes in the theme root, and
removes them.

- **The enhancement buffer.**
  - `wp_should_output_buffer_template_for_enhancement` is
    `has_filter('wp_template_enhancement_output_buffer')` through
    `wp_should_output_buffer_template_for_enhancement`.
  - `wp_start_template_enhancement_output_buffer`
    (`wp_before_include_template` 1000) answers false when the buffer is
    not wanted. Otherwise it opens a buffer with
    `wp_finalize_template_enhancement_output_buffer` as its handler, fires
    `wp_template_enhancement_output_buffer_started`, and answers true.
  - The handler leaves a CLEAN phase as it is. Any other phase is filtered
    through `wp_template_enhancement_output_buffer` (output, output), and
    the result goes to `wp_finalized_template_enhancement_output_buffer`.
  - The engine builds its pages as strings and fires no
    `wp_before_include_template`, so on the engine the buffer only runs
    when a plugin calls it.
- **require_if_theme_supports.** When the theme supports the feature, the
  file is `require`d (again on every call) and the answer is true;
  otherwise false.
- **validate_theme_requirements.**
  - It is true unless the theme's `Requires at least` or `Requires PHP`
    is unmet. An unknown theme is true.
  - Otherwise it is a WP_Error (`theme_wp_php_incompatible`,
    `theme_php_incompatible` or `theme_wp_incompatible`) whose message
    starts `<strong>Error:</strong>` and names the theme as `display`
    gives it.
- **wp_post_preview_js** (`wp_head` 1). On a preview with a post, it
  prints the inline script that names the window `wp-preview-{ID}` and
  clears the name on `pagehide`, ending in a
  `//# sourceURL=wp_post_preview_js` line. Nothing is printed otherwise.
- **Hooks fix.** An action fired with no arguments still hands its
  callbacks one empty string, but an `all` callback now gets only the
  hook's name, as on the reference.
- **Still owed.**
  - `wp_set_template_globals` changed no global in any CLI experiment,
    even after a main query, so its effect is unknown.
  - `_wp_timezone_choice_usort_callback` sorts `Etc` zones by a rule no
    single comparison explains yet.

## Footnotes kept safe (2026-10-09)

Probe `footnotes`.

- **_wp_filter_post_meta_footnotes** (`sanitize_post_meta_footnotes`).
  - The meta is decoded as JSON. Anything that is not a list (invalid
    JSON, a string) gives `''`.
  - Each note keeps only `id` (through `sanitize_key`, so `7` becomes
    `"7"`) and `content` (through post kses). Notes with an empty id or
    content, or that are not arrays, are dropped; extra keys go.
  - The list is re-encoded with `wp_json_encode`'s defaults, so unicode
    and slashes are escaped. Backslashes in content survive.
- **When it applies.**
  - `_wp_footnotes_kses_init` runs at `init` and `set_current_user`. It
    removes the filter, then adds it back unless the user can
    `unfiltered_html`. An admin's footnotes are stored as given; an
    anonymous or lower user's are cleaned.
  - `_wp_footnotes_force_filtered_html_on_import_filter`
    (`force_filtered_html_on_import` 999) adds it for a truthy argument
    and returns the argument as given.
  - `_wp_footnotes_kses_init_filters` and `_wp_footnotes_remove_filters`
    add and remove it.
  - REST writes with plugins loaded go through the registered meta, so
    they are filtered; the plugin-less fallback writes core meta directly.
- **Revisions.** `wp_get_footnotes_from_revision`
  (`_wp_post_revision_field_footnotes`, 3 arguments) answers the
  revision's own `footnotes` meta (single), `''` when it has none.

## Block template helpers (2026-10-09)

Probe `block-template-helpers`.

- **Head tags.** `_block_template_render_title_tag` prints
  `<title>{wp_get_document_title()}</title>` and a newline.
  `_block_template_viewport_meta_tag` prints the
  `width=device-width, initial-scale=1` viewport meta and a newline.
- **_block_template_add_skip_link** returns the markup as given, with or
  without a `<main>`, and raises no notice.
- **Post context** (`render_block_context` 10). A context whose
  `postType` is `wp_template` loses `postId` and `postType`. Template
  parts and every other type keep theirs.
- **_build_block_template_object_from_post_object.**
  - It is the `template_missing_theme` error without a `wp_theme` term.
    Otherwise it takes the post's fields with `source` `custom` and
    `origin` from the meta.
  - `has_theme_file` holds only when the term is the active theme and that
    theme has the file; `post_types` then comes from the file.
  - `is_custom` is false for a default template type and for truthy
    `is_wp_suggestion` meta; a part is always custom.
  - `area` is the `wp_template_part_area` term, unvalidated, for parts
    only. `plugin` stays null, and `author` is the post's, as stored.
- **Titles for a single post's template.**
  - `{singular name}: {post title}` and `Template for {post title}`
    (titles as stored) for the published post with that slug; true.
  - `Not found: {singular name} ({slug})` otherwise, with the description
    unchanged; false.
  - Posts sharing a title are never told apart: the reference looks for
    duplicates with a one-row query and needs more than one.
- **Titles for a term's template.** `{singular name}: {name}`, followed by
  ` ({slug})` when another term in the taxonomy has the same name, and
  `Template for {name}`; Not found as above.
- **Still owed.** Nothing observable from the command line:
  - `_add_template_loader_filters` added no filters;
  - `wp_set_unique_slug_on_create_template_part` changed no slug;
  - `wp_enqueue_block_template_skip_link` queued nothing. The engine
    prints the skip-link anchor but not the reference's skip-link CSS.

  Not done yet: `_resolve_template_for_new_post`,
  `inject_ignored_hooked_blocks_metadata_attributes` and
  `wp_generate_block_templates_export_file`.

## Load helpers (2026-10-09)

Probe `load-helpers`.

- **wp_is_json_media_type.** It is true when any type in a
  comma-separated list, lowercased, with parameters after `;` dropped and
  trimmed, is `application/json` or `application/{something}+json`.
  `application/+json`, `text/json`, `application/x-json`, `jsonp` and
  `json5` are not.
- **timer_float** is the seconds since `$timestart`, as a float. The
  engine now sets `$timestart` when it binds its globals at boot, as the
  reference's `timer_start()` does; before, it was never set and the
  timer read 0.
- **wp_set_internal_encoding** sets the multibyte encoding to the
  `blog_charset` option (UTF-8 when empty).
- **wp_set_lang_dir** defines `WP_LANG_DIR` and `LANGDIR` only when they
  are missing.
- **wp_get_active_and_valid_themes** answers the active theme's folder,
  after a child theme's own.
- **shutdown_action_hook** fires `shutdown` (its callbacks get one empty
  string), then closes the object cache.
- **Not done, deliberately.**
  - `wp_check_php_mysql_versions`, `wp_favicon_request`, `wp_maintenance`
    and `wp_not_installed` end the request, and the scraping-error pair
    belongs to the admin file editor.
  - The recovery-mode family (`WP_Recovery_Mode`, its services,
    `WP_Paused_Extensions_Storage`, `wp_skip_paused_*`, the error-handler
    functions) waits for a decision. The reference stores pauses only
    inside a recovery-mode session, while the engine pauses on its own
    (Runtime\Recovery).

## Block Hooks around REST (2026-10-09)

Probe `block-hooks-rest`.

- **filter_block_core_template_part_attributes.** A `tagName` survives
  only when it is a key of the allowed HTML given, case-sensitively;
  otherwise it is `''`. Every other attribute is returned as given.
- **inject_ignored_hooked_blocks_metadata_attributes**
  (`rest_pre_insert_wp_template` and `_part`).
  - Changes with no content come back unchanged.
  - An existing template (`ID`) gets its content marked by its saved
    form; nothing else is added.
  - A new one is stamped with `post_modified` (now) and `post_author`
    (the current user's id, as a string), and built from its `wp_theme`
    term. Without that term it answers the `template_missing_theme` error.
  - Hooked blocks are not inserted, only marked in the anchor's
    `metadata.ignoredHookedBlocks`.
- **insert_hooked_blocks_into_rest_response.**
  - An empty or missing raw content leaves the answer alone. Otherwise the
    raw content gets its hooked blocks, with the marks set, and `rendered`
    is `the_content` of the new raw (a self-closing static block renders
    as nothing).
  - For a navigation, the root's ignored list, merged with the stored
    `_wp_ignored_hooked_blocks`, goes into the answer's
    `meta._wp_ignored_hooked_blocks`.
- **update_ignored_hooked_blocks_postmeta.**
  - Nothing changes without both `post_content` and `post_type`.
  - Otherwise the content is marked. For a navigation, the root's list
    goes to `meta_input._wp_ignored_hooked_blocks`, keeping any other
    meta input.
- **Navigations as roots** (shared, `apply_block_hooks_to_content_from_post_object`).
  - A `wp_navigation` post's items are wrapped in a navigation block that
    carries its stored ignored list, so `first_child` and `last_child`
    hooks apply, then unwrapped.
  - The wrapper's final list comes back through
    `$ignored_hooked_blocks_at_root`. The engine used to apply hooks to
    the items as loose blocks.
- **_wp_apply_block_content_filters.**
  - It runs `shortcode_unautop`, `do_shortcode`, `do_blocks`,
    `wptexturize`, `convert_smilies`, then `wp_filter_content_tags` with
    the context given. There is no `the_content`, wpautop, autoembed or
    `]]>` escaping.
  - The id is held in `$seen_ids` while its content is filtered, then
    removed.
- **Still owed.**
  - The engine's `wp_filter_content_tags` ignores the `template` context.
    The reference adds no loading or decoding attributes there; the
    engine adds them.
  - `_wp_enqueue_auto_register_blocks` belongs to the block editor.

## The breadcrumbs block's helpers (2026-10-09)

Probe `breadcrumbs`. The engine has no breadcrumbs renderer yet; these are
the helpers it will be built on.

- **Items.** `block_core_breadcrumbs_create_item` gives
  `['label' => text]`, plus `url` (`get_pagenum_link(1)`) when the listing
  is paged. The label is kept as given, so ints stay ints.
  `block_core_breadcrumbs_create_page_number_item` is `Page {n}` from the
  query var named, cast to an int (`Page 0` when unset).
  `block_core_breadcrumbs_is_paged` is `paged > 1`; `page` is not read.
- **Posts.**
  - Titles come from `get_the_title`, so a private post reads `Private: …`.
    An empty title, or no post, is `(no title)`.
  - A hierarchical post's ancestors run root first, each with its
    permalink (a private one's `?page_id=`) and `allow_html` true. A
    post's own title is not among them.
- **Terms.**
  - A term's ancestors run root first, each with its term link.
  - A post's trail follows one taxonomy: the one
    `block_core_breadcrumbs_post_type_settings` (`[]`, type, id) names
    when it is usable and holds the post's terms. Usable means the type's
    own, viewable, and shown in REST.
  - Otherwise it is the first usable taxonomy, in registration order, that
    does. A type with no usable taxonomy has no trail.
  - The term is the one the setting's `term` slug names, else the first
    by name. The trail is its ancestors, then itself, with links.
- **Archives** (the main query).
  - Dates give the year, the month's name and the day, each linked but
    the last. Read from `m`, the year stays a string and month and day
    become ints.
  - Authors give the display name, and a term archive its ancestors then
    the term. A post type archive gives `post_type_archive_title`.
  - Search, home and an unknown term give nothing.
  - On a paged archive the last crumb links to its first page and
    `Page N` follows.
- **Still owed then, done next.** The block's markup: see the next section.

## The breadcrumbs block rendered (2026-10-09)

Probe `breadcrumbs`, section "the block rendered" (a facade renderer on
`minn_block_renderers`).

- **When it prints.** Nothing on the home page, the blog home or a 404,
  with or without `showOnHomePage`. Nothing either when no item is left to
  print.
- **The trail.**
  - A search gives `Search results for: "{query}"`.
  - A singular post gives its ancestors when its type is hierarchical,
    unless it prefers its taxonomy (`prefersTaxonomy`) and has terms to
    follow. Otherwise it gives its terms trail; its title (as markup) ends
    the trail.
  - Anything else gives the archive's crumbs.
- **Markup.**
  - `<nav {wrapper}><ol>…</ol></nav>`. The wrapper carries
    `--separator: "{separator}"` and `aria-label="Breadcrumbs"`; quotes
    and backslashes in the separator are escaped, so safecss refuses it.
  - Home (`showHomeItem`) comes first as a link. Each crumb is
    `<li><a href>label</a></li>`, labels escaped unless `allow_html`.
  - The last item is `<li><span aria-current="page">`, dropped under
    `showCurrentItem` false.
- **Shared fixes.**
  - `get_block_wrapper_attributes` runs the caller's style through
    `safecss_filter_attr` before merging it after the supports' style. It
    still prints a style attribute, empty, when the caller gave one that
    is refused entirely. Before, the extra style went through unchecked.
  - `safecss_filter_attr` trims the whole style first, passes over empty
    pieces, and keeps a whitespace-only piece as an empty declaration
    (`a; ;b` is `a;;b`, `color:red; ;` keeps its trailing `;`). Before,
    every empty piece was dropped.

## HTTPS detection and migration, and the request host helpers (2026-10-09)

Probe `https-helpers`. The functions are in `wp-api/https.php`, with the
https checks moved there from misc.php and formatting.php. The host helpers
are in http.php.

- **Hooks.** `allowed_http_request_hosts` on `http_request_host_is_external`
  (10, 2) and `wp_update_https_migration_required` on `update_option_home`
  (10, 2). Neither the multisite host check nor the old
  `wp_https_detection` cron is hooked.
- **`_wp_translate_php_url_constant_to_key`.** The constant is looked up as
  an array key: `"1"` and `true` name the host, `false` the scheme, and
  anything unknown is false. `_get_component_from_parsed_url_array` now
  goes through it, so `"1"` gives the host there too, while `"-1"` gives
  null (only the integer -1 means the whole URL).
- **Request hosts.**
  - `allowed_http_request_hosts` keeps any truthy answer as given (0 stays
    0). Otherwise a host passes when `wp_validate_redirect('http://' . $host)`
    does. That includes a port, a path, or `user@` before the home host,
    but not a host in another case.
  - `ms_allowed_http_request_hosts` keeps a truthy answer. Past that, the
    reference asks the network and fails on a single site, because
    `get_network()` is undefined there. The engine answers that no network
    site matched.
- **`wp_validate_redirect`.** It now keeps credentials before an allowed
  host (`http://u:p@{home}/x`, `//u@{home}/` made `http://u@{home}/`), as
  the reference does: the host decides. Before, any user or password was
  refused.
- **`wp_is_local_html_output`.** While wp_head has `rsd_link`, the page must
  contain the RSD address from its `//` on (any scheme, case kept).
  Otherwise, while wp_head has `rest_output_link_wp_head`, it must contain
  the REST root's address. With neither, the answer is null.
- **`wp_get_https_detection_errors`.**
  - A WP_Error from `pre_wp_get_https_detection_errors` (given null) stands
    for the errors, as its `errors` array. Any other answer is ignored.
  - Otherwise there is a GET of `home_url('/', 'https')` with
    `Cache-Control: no-cache` and sslverify. If it fails, whatever the
    error, the GET is repeated without sslverify. A second failure gives
    `https_request_failed` alone; a success gives `ssl_verification_failed`.
  - Then a status other than 200 gives `bad_response_code` (with the
    status message). A page that is not this site's (`=== false`, so a
    null passes) gives `bad_response_source`.
  - `wp_is_https_supported` is true when there are no errors; it neither
    reads nor writes the stored option.
  - The deprecated `wp_update_https_detection_errors` (6.4.0) stores them.
- **`wp_update_https_migration_required`.**
  - It does nothing while installing.
  - The flag is set when the old URL and the new one, with `https://` made
    `http://`, are equal ignoring trailing slashes (case kept). Its value is
    true unless `fresh_site`; false on a missing option adds nothing. Any
    other change deletes the flag.
- **`wp_update_urls_to_https`.** Every `http://` in home and siteurl
  becomes `https://`. Both are put back, and the answer is false, when
  `wp_is_using_https()` still says no (a filtered home_url, or an ftp
  home).
- **Shared fixes.**
  - `rsd_link()` prints from `site_url('xmlrpc.php?rsd', 'rpc')` wherever it
    is called. Before, it printed only during a classic theme's render.
  - `rest_output_link_wp_head()` prints the root link outside a classic
    render, where there is no queried object.
  - `wp_load_alloptions()` holds an option written in this request as it
    was written (`true`, not `"1"`); arrays and objects stay as stored.
- **Left open.**
  - On a block theme the engine prints its head links itself, so wp_head
    has neither `rsd_link` nor `rest_output_link_wp_head`, and the page
    carries no EditURI link. The probe registers both. With the defaults
    on a block theme, `wp_is_local_html_output` answers null and the https
    check never reports `bad_response_source`.
  - While `wp_installing()`, the reference's `get_option()` reads past its
    cache. The engine's does not.

## Bootstrap guards and the pages that stop a request (2026-10-09)

Probe `bootstrap-guards`. A page that would end the request is caught
where it is handed over: at wp_die's handler (which returns) or at
wp_redirect (which throws). The favicon request really does end the run,
so the probe prints its log as the run shuts down.

- **Constants.**
  - `wp_initial_constants` and `wp_functionality_constants` change
    nothing: `Runtime\Constants` defined them at boot. The reference
    redefines some of them unguarded, and its warnings go to stderr.
  - `$blog_id` is now 1 from boot.
- **Guards a normal request passes** (null, nothing printed).
  - `wp_check_php_mysql_versions` (the engine's floor is PHP 8.3 with
    mysqli) and `wp_not_installed` (installed, or installing).
  - `wp_favicon_request`, for any path but exactly `/favicon.ico`, which
    ends the request. `/favicon.ico/`, `/FAVICON.ICO` and `/favicon.ico?x`
    pass.
- **Maintenance.**
  - `wp_maintenance` stops a request while `.maintenance` is fresh (under
    600 seconds): `wp_die("Briefly unavailable for scheduled maintenance.
    Check back in a minute.", "Maintenance", 503)` with `Retry-After: 600`,
    or the site's `maintenance.php` when there is one.
  - `wp_is_maintenance_mode` is false while installing. Its `$upgrading`
    is the global, so a file that sets none keeps the last value read (as
    on the reference; before, it was a local).
  - The probe clears `enable_maintenance_mode` first: WP-CLI switches
    maintenance off for its own commands. The filter is handed
    `(true, $upgrading)`.
  - The front door's own check (`Front\Maintenance`) is unchanged.
- **`dead_db`.** The site's `db-error.php` when there is one. Otherwise,
  while installing, `wp_die($wpdb->error)` first. Then
  `wp_die("<h1>Error establishing a database connection</h1>",
  "Database Error")`.
- **`do_favicon`.** It fires `do_faviconico` (with no argument), then
  redirects 302 to `get_site_icon_url(32, includes_url('images/w-logo-gray-white-bg.png'))`,
  then exits. The `/favicon.ico` route keeps its engine-defined 404
  without a site icon, since the engine ships no WordPress logo.
- **JSONP.**
  - `wp_is_jsonp_request` needs `_jsonp` to pass `wp_check_jsonp_callback`
    (an empty one does) and `rest_jsonp_enabled` to allow it. Before, only
    `isset` was checked.
  - `wp_die` picks `wp_die_jsonp_handler` only on a REST request, after
    the JSON check.
  - `_jsonp_wp_die_handler` prints `/**/{callback}({json})`, with the
    callback raw (empty when missing) and the JSON handler's body.
- **Shared fix.** `_wp_die_process_input` takes the first error's
  `status` as the response unless the caller names one (409 from the
  error, 401 from the args). Before, a WP_Error's status was ignored and
  the response was 500.
- **`wp_get_extension_error_description`.** The type is named by its
  `E_*` constant (`E_ALL` and `E_STRICT` too, `"1"` as `E_ERROR`); a type
  with no name is printed as given. Line, file and message go in `<code>`
  unescaped.
- **`wp_is_fatal_error_handler_enabled`.** True unless
  `WP_DISABLE_FATAL_ERROR_HANDLER`, as `wp_fatal_error_handler_enabled`
  answers (as given).
- **The file editor's scrape.**
  - `wp_start_scraping_edited_file_errors` does nothing without both
    fields, with an empty sanitized key or nonce, or with no
    `scrape_key_{key}` transient.
  - A wrong nonce prints the `scrape_nonce_failure` JSON between the
    markers and stops (captured once by hand, outside the probe, since
    it exits).
  - `wp_finalize_scraping_edited_file_errors` prints `true` between the
    markers, with the key as given. Its fatal-error branch cannot be
    reached from a probe.
- **Not proven by the probe.** The pages that exit: the db-error and
  maintenance drop-ins, the PHP version page and the installer redirect.
  `wp_is_json_request` reads the request's headers as received, not
  `$_SERVER` at call time, so the JSON-over-JSONP precedence is set in
  code but not probed.

## Small output helpers (2026-10-09)

Probe `output-helpers`.

- **The author's link.**
  - With a website, `get_the_author_link` gives
    `<a href="{esc_url}" title="Visit {name}&#8217;s website" rel="author external">{name}</a>`:
    the title goes through esc_attr, the name is raw, and the title is
    left out when asked. Without a website it gives the name alone (a
    `javascript:` URL was emptied on save), and with no author it gives
    `''`.
  - The `the_author_link` filter gets `(link, url, authordata)` and is
    applied on the website path only. Before, the engine returned the
    author posts link.
  - `the_author_link` and `the_author_posts` print what the getters
    return (the post count cast to int, 0 with no post).
- **Shared fix: user rows are written unslashed.** The `pre_user_*`
  filters (`wp_filter_kses`) hand back slashed text. The meta fields were
  unslashed by the meta API, but `display_name` (and email, url, nicename)
  went into the users table with backslashes (`O\'Brien`). Inserts and
  updates now unslash the row before `wp_pre_insert_user_data`, as the
  reference does.
- **`prep_atom_text_construct`.**
  - Text with neither `<` nor `&` is `text`, as given.
  - Otherwise it is parsed as XML inside a div (predefined entities only).
    Well formed with a `<` gives `xhtml`, wrapped in
    `<div xmlns='http://www.w3.org/1999/xhtml'>`; well formed with no `<`
    (`a &amp; b`) gives `text`.
  - Anything else is `html` in CDATA, or htmlspecialchars'd when it holds
    `]]>`.
- **`_post_format_link`** (on `term_link`, 10, 3) leaves other taxonomies
  alone. With the post_format permastruct, every `/post-format-{slug}`
  becomes `/{slug}`. Without it, the `post_format` query argument is
  replaced by the slug, duplicates included.
- **`wp_add_crossorigin_attributes`.**
  - `crossorigin="anonymous"` goes first among a tag's attributes. It is
    added to SCRIPT (src), LINK (href), AUDIO and VIDEO (src) whose address
    is present and starts with neither `/` nor `site_url()` (a plain,
    case-kept prefix: an empty src counts, a port after the site's host
    does not).
  - A cross-origin SOURCE marks the last AUDIO or VIDEO opened, even one
    already closed. With none, the reference raises a notice (seek to an
    unknown bookmark) and changes nothing. An element that already names
    crossorigin is left alone; IMG, IFRAME and TRACK never get one.
- **Redirects.** `wp_sanitize_redirect` now makes spaces `%20` and
  URL-encodes whole UTF-8 sequences (`_wp_sanitize_utf8_in_redirect`,
  urlencode) before dropping characters outside the URL set. Stray bytes
  are dropped. Before, UTF-8 was dropped too (`/é` became `/`).
- **Left open.**
  - `wp_filter_global_styles_post` waits for
    `WP_Theme_JSON::remove_insecure_properties`, still a placeholder.
  - `_print_emoji_detection_script` goes with the engine's decision to
    print no emoji plumbing (for Austin).
  - The engine's `get_term_link` builds format links from its own
    permalinks, so a post_format permastruct removed at runtime does not
    change them.

## Runtime internals (2026-10-09)

Probe `runtime-internals`. The probe saves the cron option and writes it
back in place, removes its attachment and files, and intercepts nothing
over the network: `_wp_cron` is left out (see below).

- **The core bindings sources.** Each has its own function
  (`_register_block_bindings_{pattern_overrides,post_data,post_meta,term_data}_source`),
  hooked on `init` at 10, in place of the engine's one
  `_minn_register_block_bindings_sources`. The probe runner fires no init,
  so the probe registers any source that is missing.
- **`wp_cache_reset`** (deprecated 3.5.0, use `wp_cache_switch_to_blog()`)
  calls `$wp_object_cache->reset()`. That is deprecated too (`reset`,
  `WP_Object_Cache::switch_to_blog()`), and it now empties every group
  that is not global. Before, it only raised the notice.
- **`user_can_for_site`** is `user_can()` on a single site, whatever the
  site id.
- **`WP_Metadata_Lazyloader`** (`wp_metadata_lazyloader()`: one instance,
  kept in a static, not a global).
  - Its settings cover term, comment and blog, each with
    `get_{type}_metadata` and `lazyload_meta_callback`.
  - `queue_objects` adds ids as keys (`[id => 1]`) and hooks the filter at
    10 even for an empty list. It fires
    `metadata_lazyloader_queued_objects(ids, type, lazyloader)` and refuses
    an unknown type (`invalid_object_type`, "Invalid object type.").
  - The callback does nothing for a type with nothing queued. Otherwise it
    primes the queued ids plus the one read (`update_meta_cache`), empties
    the queue, unhooks, and returns the check untouched.
  - `lazyload_term_meta` and `lazyload_comment_meta` are deprecated
    (6.3.0, `WP_Metadata_Lazyloader::lazyload_meta_callback`).
  - `wp_lazyload_term_meta` and `wp_lazyload_comment_meta` now queue.
    Before, they were no-ops.
- **`_wp_reset_invalid_menu_item_parent`.** An item whose ID and
  `menu_item_parent` are both set and equal (as integers) gets parent 0.
  Any other parent stays, whether missing, negative or not a number.
- **Cron arrays from before version 2.**
  - `_upgrade_cron_array` keys each event by `md5(serialize(args))` and
    marks the list version 2 (last, or where an old version key was). It
    saves the result as the cron option. A version 2 list comes back as
    given and is not saved.
  - `_get_cron_array` upgrades a stored list that has no version.
- **Script arguments.**
  - `_wp_scripts_add_args_data` turns `in_footer` into group 1 and passes a
    strategy and a fetch priority on to the registry, which refuses ones it
    does not know with its own notices. Empty values are ignored, and an
    unregistered handle gets nothing.
  - `wp_register_script` and `wp_enqueue_script` share one path
    (`_minn_register_script`).
- **Registered too early.** `_wp_scripts_maybe_doing_it_wrong` warns (3.3.0)
  while neither `init` nor any of the three enqueue actions has fired:
  "Scripts and styles should not be registered or enqueued until the …
  hooks.", plus "This notice was triggered by the `<code>{handle}</code>`
  handle." (raw) when there is a handle.
  - It is called by the register, enqueue, inline, `_is`, dequeue and
    deregister functions for scripts and styles, but not by
    `wp_localize_script` or `wp_*_add_data`.
  - The probe runner fires no init, so the `dependencies-classes` and
    `widgets-more` probes now mark init as done. That changes nothing on
    the reference, so their fixtures stand.
- **`wp_simplepie_autoload`** is deprecated (6.7.0, `SimplePie_Autoloader`)
  and loads nothing.
- **`wp_maybe_generate_attachment_metadata`.** An attachment with no
  metadata whose file exists gets its metadata made and saved. One with
  metadata, or with its file missing, is left alone.
- **`wp_get_speculative_loading_override($name)`.** The constant of that
  name when it holds a string. Otherwise the environment variable, where
  an empty one counts. Otherwise null. A constant that is not a string
  falls through to the environment.
- **`_wp_cron` is left out (Austin, 2026-10-09).** The reference hooks it
  on `shutdown` and, when events are due, spawns a loopback request to
  `wp-cron.php`. The engine's own Cron runs due events after the response
  under its own lock, so hooking `_wp_cron` as well would run them twice.
  It is now an omitted dead end: an unhooked stub that logs the call.

## The tabs block's helpers and the template part variations (2026-10-09)

Probe `tabs-and-parts`.

- **`block_core_tabs_generate_tabs_list`.** One id per `core/tab-panel` in
  the first `core/tab-panels` child; later containers, and panels outside
  one, are ignored. A panel's id is its anchor when that is not empty,
  else `{tabs id}-tab-{n}` (`tab-{n}` with no id). `n` counts panels only,
  skipping any other block between them.
- **`block_core_tabs_provide_context`** (on `render_block_context`, 10, 2).
  A `core/tabs` block adds `core/tabs-list` and `core/tabs-id` to the
  context. The id is its anchor taken as given (an empty anchor stays
  `''`), else a fresh `wp_unique_id('tabs_')` on every call. Other blocks'
  context passes untouched.
- **Template part variations.** The engine's one builder is now the
  reference's two halves.
  - `build_template_part_block_instance_variations` makes one per template
    part.
  - `build_template_part_block_area_variations($instances)` makes one per
    area except `uncategorized` and `navigation-overlay` (by name: an area
    tagged div, or with the overlay icon, still counts). Its scope is `[]`
    when a given part variation belongs to the area, else `['inserter']`.
    Before, an area with no parts got no variation at all.
  - `build_template_part_block_variations` is the areas' variations, then
    the parts'.
- **Left open.** The engine still has no tabs renderer of its own (the
  block goes through the generic core path), so the context these helpers
  provide is not yet used in rendering.

## Looking abilities up, and connector keys kept out of the settings answer (2026-10-09)

Probe `abilities-lookup`. The probe loads the engine's plugin lifecycle
first, as the connectors probe does: the CleanTalk plugin registers its
connector there. It writes every connector option back as it was.

- **`wp_get_abilities($args)`** runs the reference's pipeline every time,
  with no arguments too.
  - The category, namespace and meta conditions apply, each ignored when
    empty. The namespace is a prefix before `/`, and a trailing slash is
    ignored.
  - Then `item_include_callback`, its answer as a boolean (a callback
    that is not callable is ignored).
  - Then `wp_get_abilities_item_include($include, $ability, $args)`. That
    filter also hears a false from the callback.
  - Then `result_callback`, where anything but an array means none, and
    `wp_get_abilities_result($abilities, $args)`.
  - Keys are the ability names. Meta is matched against `get_meta()`,
    defaults included, so `show_in_rest => false` matches an ability that
    never set it. Before, the engine filtered by category only.
- **`_wp_get_abilities_match_meta`.** Every condition key must be present
  (a null counts). An array condition needs an array value and recurses,
  so a list matches position by position and `[]` matches any array.
  Anything else compares strictly.
- **`_wp_connectors_rest_settings_dispatch`** (on `rest_post_dispatch`, 10,
  3) works on the `/wp/v2/settings` route only, exactly as written: a
  trailing slash or capitals do not count.
  - Each connector's string key is masked (`_wp_connectors_mask_api_key`).
    An empty key stays empty.
  - An application-password connector's array value has its `password`
    replaced by sixteen bullets. As a JSON string it is left alone.
  - On POST or PUT, each AI provider key in the answer that does not
    validate (as one whose provider the AI client lacks does not) is
    stored empty with `update_option` and answered empty. Spam-filtering
    keys are only masked.
- **The engine's REST fast path.** `RuntimeRoutes` treats this filter as
  route-scoped: off `/wp/v2/settings` it hands the answer back unchanged,
  so it is left out there. Hooking it does not send every REST answer
  through the plugin path.
- **Left for Austin.** `_wp_connectors_get_connector_script_module_data`
  feeds only wp-admin's connectors screen
  (`script_module_data_options-connectors-wp-admin`), next to the
  build/routes admin pages: an admin dead end?

## More of template loading, the timezone order, and tax_input's capability (2026-10-09)

Probe `template-loader-more`.

- **`wp_set_template_globals`** sets `$wp_stylesheet_path` and
  `$wp_template_path` to the active theme's directories. (Earlier notes
  said it had no observable effect; it does.)
- **`wp_set_unique_slug_on_create_template_part`** (on
  `save_post_wp_template_part`, 10) works on auto-drafts only. One with no
  slug gets `custom_slug_{uniqid}`, and one with no `wp_theme` term gets the
  active stylesheet's. It does not check the post type: called directly on
  a post, it renames that post too. For a missing post it returns nothing
  (the reference warns, reading a null).
- **`_add_template_loader_filters`** (on `wp_loaded`, 10) hooks
  `_resolve_template_for_new_post` on `pre_get_posts` whenever
  `_wp-find-template` is in the query string, whatever its value.
- **`_resolve_template_for_new_post`** does nothing on a query other than
  the main one. On the main one it unhooks itself. No change to the query
  could be observed, with an auto-draft post or page as the id, as an
  administrator or nobody. It serves the site editor's template lookup.
- **`_wp_timezone_choice_usort_callback`**, proven by sorting PHP's whole
  zone list with the Etc names added (453 entries) the same on both stacks:
  - Etc entries compare by untranslated city. Two GMT+N fall in reverse;
    UTC comes after GMT+ and before anything else; the rest compare
    naturally (`GMT`, `GMT+12` … `GMT+0`, `UTC`, `GMT-0` … `GMT-14`,
    `Greenwich`, `UCT`, `Universal`, `Zulu`).
  - Etc goes after every other continent, whatever the translations.
  - Everything else compares translated continent, then city, then
    subcity, naturally and without case.
- **Shared fix: `tax_input` needs the right to assign terms.**
  `wp_insert_post` now sets a taxonomy's terms from `tax_input` only when
  the current user has that taxonomy's `assign_terms` capability. With
  nobody signed in, category and tags are left alone, so the default
  category stands. Before, the engine applied them for anyone.
- **Left open.**
  - `wp_enqueue_block_template_skip_link` would print WordPress's own
    skip-link stylesheet text (for Austin, with the skip-link CSS item).
  - `wp_global_styles_render_svg_filters` needs
    `wp_get_global_styles_svg_filters` to print the duotone filters; the
    engine's answers `''`.
  - `wp_map_sidebars_widgets` goes in a widgets batch.

## Block metadata collections (2026-10-09)

Probe `block-collections`. The probe writes its manifests into a folder
under the plugins directory and removes it, along with the block types it
registers.

- **`WP_Block_Metadata_Registry`**, a new facade class.
  - `register_collection($path, $manifest)` normalizes the path and drops
    a trailing slash. It first refuses (doing it wrong, 6.7.2, "Block
    metadata collections cannot be registered as one of the following
    directories or their parent directories: …", the list comma-separated)
    a path that is one of the roots or a parent of one. The roots come from
    `wp_allowed_block_metadata_collection_roots`, given wp-includes,
    wp-content, mu-plugins, plugins and the theme root. Then it refuses a
    manifest that is not there (6.7.0). Anywhere else is allowed, even
    outside the site. Registering a path again replaces its manifest and
    keeps its place.
  - A path belongs to the first collection whose path it starts with as a
    plain string, the last match tried first. So `build` holds `buildzz/…`,
    and a collection registered inside another is never reached. The block
    is named by the last segment, or the folder of a `block.json`.
  - `get_collection_block_metadata_files` gives `{path}/{name}/block.json`
    for each manifest entry. An unknown path gives `[]` with a notice
    (6.8.0).
- **`register_block_type_from_metadata`** reads a registered collection's
  metadata first, so the block.json need not exist. A block with neither
  gives false.
- **Shared fix.** `wp_register_block_metadata_collection` and
  `wp_register_block_types_from_metadata_collection` were empty, so a
  plugin registering its blocks from a `blocks-manifest.php` (the shape
  create-block scaffolds since WordPress 6.7) had none on the engine. Now
  the collection is registered and each listed block is registered from
  it. With no manifest given, an already-registered collection is used.
- **Omitted.** `Services_JSON_Error` joins `Services_JSON`, the omission
  Austin approved: it is the same library's error class.

## theme.json schema migration, and classic menus as blocks (2026-10-09)

Probe `schema-and-menus`.

- **`WP_Theme_JSON_Schema::migrate($theme_json, $origin)`**, a new facade
  class.
  - Data with no `version` comes back as just `['version' => 3]`.
  - Version 1 (or `'1'`) has four settings renamed, in this order:
    `border.customRadius`, `spacing.customMargin`, `spacing.customPadding`,
    `typography.customLineHeight`. They become `radius`, `margin`,
    `padding` and `lineHeight`, in settings and in each block's settings.
    The new key goes last, or replaces one already there; a null is not
    moved. Then it carries on as version 2.
  - Version 2 becomes 3. For any origin but `custom`, top-level
    `typography.fontSizes` sets `defaultFontSizes` to false and
    `spacing.spacingSizes` or `spacingScale` sets `defaultSpacingSizes` to
    false, overriding a true. Blocks' settings are not touched.
  - Any other version (0, 3, 4) passes as given.
- **`WP_Classic_To_Block_Menu_Converter::convert($menu)`**, a new facade
  class.
  - A menu's published items, nested by parent: one with children is a
    `core/navigation-submenu` and one without a `core/navigation-link`.
  - The attributes, in order: `className` (the item's classes with its
    context classes, a leading space when its own are empty),
    `description`, `id` (the object id as a string, null for a custom
    link), `kind` (the type with `-`), `label`, `opensInNewTab`, `rel`
    (null when empty), `title`, `type` (the object) and `url`.
  - An empty menu gives `''`. Anything else that is not a menu gives
    `invalid_menu`, "The menu provided is not a valid menu."
- **Shared fix: menu text filters.** `nav_menu_attr_title` and
  `nav_menu_description` now have the reference's `wptexturize` and
  `strip_tags` at 10. The engine applied the filters but hooked nothing, so
  a menu item's title attribute and description kept straight quotes and
  tags.

## The sitemap classes proven (2026-10-09)

Probe `sitemap-classes`. The probe uses its own public post type and
taxonomy, its posts and terms, and two authors. It narrows the providers
to them with `wp_sitemaps_post_types`, `wp_sitemaps_taxonomies` and
`wp_sitemaps_users_query_args`, and sets two URLs to a page with
`wp_sitemaps_max_urls`. It removes everything afterwards.

- **What is now proven.**
  - The server's providers (`posts`, `taxonomies`, `users`), its index
    and its renderer.
  - The registry: a second provider under the same name is refused;
    `wp_sitemaps_add_provider` gets `(provider, name)`, and refuses
    anything but a provider.
  - Each provider's subtypes, page count, the URL list for each page (an
    empty one past the end, and for an unknown subtype), its type data,
    sitemap entries and sitemap URLs. An unknown taxonomy counts one page:
    the reference turns the error into 1, with a warning.
  - The index list and URL, and both stylesheet URLs.
- **Shared fix: empty elements close themselves.** `SitemapXml` (the
  engine's routes and the facade's renderer alike) now prints any element
  with nothing inside as `<x/>`, at every level, as the reference's XML
  writer does: an empty `<urlset/>` or `<sitemapindex/>`, an entry with no
  fields, a field with an empty value. Before, they were written open and
  closed.
- **Left out on purpose.** `WP_Sitemaps_Stylesheet`'s text: the engine
  serves its own stylesheet (770 bytes against the reference's 3,449), so
  the class stays unproven rather than copying WordPress's XSL.

## The core object classes used directly (2026-10-09)

Probe `core-objects`. It saves the roles option first and writes it back in
place, and removes its post, comments, terms and menu. The `sitemap-classes`
probe also gained a server of its own (`new WP_Sitemaps`) and a check
against the providers' shared parent, so those two classes count as proven
now.

- **`WP_Term`.**
  - `get_instance` gives false for 0, a missing id, a non-number, or the
    wrong taxonomy.
  - The constructor keeps fields as given: a `term_group` left out stays
    the class default `''`, and extra fields stay. Before, the constructor
    cast five fields to integers. That cast now happens where the
    reference casts, on a fetch: `WP_Term::get_instance` and both of
    `get_term`'s paths, through `_minn_term_numbers`.
  - `->data` is the nine columns plus `filter` `raw`, so a fetched term's
    `to_array()` equals it.
  - `filter('display')` texturizes the name and wraps the description.
- **`WP_Comment`.** The constructor keeps fields as given (77 stays an
  integer). A comment read from the database has its fields as text, the
  record's numbers turned to strings where it becomes a comment
  (`get_comment` and `rest_delete_comment`).
  - Magic `post_*` fields read the comment's post; `__isset` covers them.
  - `get_children` defaults to every status in tree form. `flat` lists
    descendants depth first (keyed 0…), and `hierarchical => false` gives
    direct replies only.
  - `populated_children(false)` re-queries. A built comment's `add_child`
    and `get_child` work without a query.
- **`WP_Role`.** `has_cap` honours `role_has_cap`. `add_cap` and
  `remove_cap` write the role (and return nothing); `add_cap(cap, false)`
  stores a false grant.
- **`Walker_Category`** start/end of level and element, for the list and
  none styles, with count, description titles, a feed link and the
  current category.
- **`Walker_Nav_Menu`.**
  - A link's attributes are now in the reference's order: target, rel,
    href, aria-current, title. A new tab with no XFN gets no
    `rel="noopener"`, which the engine had added before.
  - The same walker prints an item's id only the first time.

## The style engine's classes used directly (2026-10-09)

Probe `style-engine-classes`. `WP_Style_Engine` is final, so its protected
helpers are reached by reflection. The declarations class's are reached
through a subclass.

- **New protected helpers** on `WP_Style_Engine`: `get_classnames`,
  `get_css_declarations`, `get_individual_property_css_declarations` and
  `get_url_or_value_css_declaration`, each backed by `Blocks\StyleEngine`.
  `get_css_var_value` now answers `''` for a preset it cannot place
  (before, null).
- **Shared fix: presets in sides and border sides** (`Blocks\StyleEngine`,
  used by `parse_block_styles` and the block supports).
  - A side (`padding.top`) with a preset becomes the custom property its
    definition names, or is dropped when there is none. When there are no
    custom properties to use, it stays as written, which includes presets
    turned into class names. Before, it was dropped there too.
  - A border side counts only the parts its group defines, named by each
    part's pattern. A preset goes through the side's own custom properties
    (colour only), and an unmatched one is kept as an empty value.
- **Shared fix: `parse_block_styles`** returns `classnames` first, then
  `declarations`, and keeps repeated class names (`has-background` from
  both the background and the gradient). `wp_style_engine_get_styles` makes
  them unique, as before.
- **Shared fix: `WP_Style_Engine_CSS_Declarations`.**
  - `add_declaration($property, $value, $options)` keeps what it is given:
    the property cleaned to `[a-z0-9-]` in lower case, a trimmed non-empty
    string value (a number, boolean, array or blank is not kept), and the
    options when they are an array (`get_declaration_options`, removed with
    the declaration). The kses CSS filter (`filter_declaration`) judges each
    declaration only when the string is printed.
  - Before, declarations were filtered as they were added, so
    `get_declarations()` lost an unknown property that the reference keeps.
  - A non-array given to the constructor adds nothing.

## The block bindings and block templates registries (2026-10-09)

Probe `block-registries`. Everything the probe registers it removes again.

- **`WP_Block_Bindings_Registry`.**
  - A name that is not `namespace/name` (a number, or with no slash) is
    refused with "Block bindings source names must contain a namespace
    prefix. Example: my-plugin/my-custom-source". Capitals are refused
    with "… must not contain uppercase characters.", and an unknown
    property with "The $source_properties array contains invalid
    properties." All are doing it wrong at 6.5.0. Before, the engine had
    one combined name message and listed the unknown properties.
  - The rest is now proven: refusals for a missing label, a missing or
    uncallable callback, and a `uses_context` that is not an array; the
    lookups; unregistering; and `__wakeup` refusing.
- **`WP_Block_Bindings_Source`.** `get_value` gives the callback's answer
  through `block_bindings_source_value(value, name, args, block,
  attribute)`.
- **`WP_Block_Templates_Registry`.**
  - Every refusal now also raises doing it wrong (6.7.0), with the same
    message as its WP_Error. A name that is not a string has its own
    error, `template_name_no_string` ("Template names must be strings.").
  - A template registered with no title takes its name as the title, and
    with no post types has `[]` (before, `''` and null).
  - `get_by_query(['post_type' => …])` finds only templates that list that
    post type. Before, a template listing none was found too.

## The widget factory and two core widgets (2026-10-09)

Probe `widget-factory`. Registering the factory's widgets saves the probe
widget's empty settings; the probe deletes them at the end.

- **Shared fix: widget globals from boot.** `$wp_widget_factory` and the
  widget registries (`$wp_registered_sidebars`, `$wp_registered_widgets`,
  and the widget controls and updates) now exist from boot
  (`_minn_bind_hook_globals`), as the reference creates them before plugins
  load. Before, the factory appeared only with the first registered widget,
  and the registries only with their first entry, so a plugin reading them
  early got null.
- **`WP_Widget_Factory`.** A widget registered as an instance is keyed by
  `spl_object_id` (an integer), as the reference keys it; before,
  `spl_object_hash`. Registering by class twice keeps one. Also proven:
  `get_widget_object`, `get_widget_key`, `unregister` (by class or
  instance), and `_register_widgets` numbering the widget.
- **Shared fix: `widget_title`** has the reference's `wptexturize`,
  `convert_chars` and `esc_html`. Before, a widget's title was printed raw.
- **`WP_Widget_Custom_HTML`.**
  - `widget_text ` goes first in the wrapper's class attribute (after
    `class="`), where the engine had put it before `widget_custom_html`.
  - The content goes through `widget_text`, then
    `widget_custom_html_content`.
  - `update` keeps the old settings, sanitizes the title, and runs kses on
    the content for a user without `unfiltered_html`. Before, it returned
    the new title and content raw.
- **`WP_Widget_Block`.** `update` runs kses on the content for a user
  without `unfiltered_html`. Before, it was raw for everyone. Its display
  is proven too.

## Translation files and lookups (2026-10-09)

Probe `translation-files`. The probe writes its own MO and PHP files to a
temporary folder, removes them at the end, and unloads its text domains.

- **Shared fix: an empty translation is a translation.** A file entry whose
  translation is empty now answers empty, through the controller and
  through `__()` and `_n()`, as on the reference. Before, the engine treated
  it as no translation and printed the original. A plural form the file
  lacks (a Polish count against an entry with two forms) falls back to the
  entry's first form.
- **Shared fix: plural lookups.** `_n()` and `translate_plural` look for the
  plurals joined by NUL bytes (with the context in front) in every loaded
  file first, then for the singular alone. A PHP file keys plural entries
  that way; an MO file keys them by the singular. Before, only the singular
  was looked for, so a PHP file's plurals never matched.
- **`WP_Translation_Controller`.** The first loaded file with an entry
  answers. `get_entries` and `get_headers` merge every file in load order,
  a later file's value replacing an earlier one's; before, the first file
  won. `has_translation` is true for an empty entry too.
- **`WP_Translations`.** `entries` is a list, not keyed by original.
- **`WP_Translation_File`.**
  - The MO header check records "Magic marker does not exist" as the
    file's error when the magic number is wrong.
  - `export` writes the headers and entries the object holds, without
    reading its file first, so a file never read exports empty.
  - The PHP export is PHP's own export without spacing:
    `['key'=>'value',...]` on one line, headers first and `messages` last.
- **`Plural_Forms`** runs on `Minn\I18n\LegacyPluralForms`, POMO's own
  reader, kept apart from the engine's `PluralExpression` because plugins
  see its quirks. Kept on purpose:
  - Only `n`, whole numbers, parentheses, `%`, the comparisons, `&&`,
    `||` and the ternary are read. `+`, `-`, `*`, `/` are "Unknown symbol",
    and `!`, `=`, `&` alone are "Unknown operator".
  - Only spaces and tabs are skipped; a newline is an unknown symbol.
  - Operators of equal strength group to the left; the ternary groups to
    the right.
  - A missing operand is null, so `n ==` is 0 and `n ? 1 :` is 1.
  - `==` and `!=` compare strictly, and results stay booleans until the
    answer is cast, so `(n == 1) == 1` is always 0.
  - `% 0` throws PHP's own `DivisionByZeroError`.
  - Refusals of the expression's shape come from building it; an unmatched
    `?`, values left over, and the empty expression come from evaluating it.

## The rest of functions.php (2026-10-09)

Probe `functions-remaining`. The probe writes files to a temporary folder, a
draft post for the enclosures and the `fresh_site` option, and puts all
three back. HTTP is intercepted. CLI keeps no headers, so the header
helpers are seen through their filters. Left out because nothing is
observable without headers: `send_frame_options_header`,
`send_nosniff_header`, `cache_javascript_headers`, and `wp_auth_check_load`
outside the admin. Also left out: `wp_scheduled_delete` (it deletes every
old trashed post on the shared database) and the `do_feed_*` functions.

- **Shared fix: the error page.** `_default_wp_die_handler` now draws the
  reference's page: its stylesheet word for word (`Minn\Http\DiePage`), the
  language attributes (or `dir='rtl'` and a Tahoma font for a right-to-left
  message), the robots tag with `wp_robots_no_robots` added (it stays added
  for the request, as on the reference), and the title and message as
  given. Several errors become a list, then come the link and the back
  link. It sends its status through `status_header` and its no-cache
  headers through `nocache_headers`. Before, it printed a cut-down page of
  its own.
- **Shared fix: `_wp_die_process_input`.** Of a `WP_Error`'s data, only
  wp_die's own arguments carry into the arguments. Before, `status` and
  `title` were merged in too. The charset is always written the usual way
  (`iso-8859-1` becomes `ISO-8859-1`).
- **The other die handlers.** `_scalar_wp_die_handler` reads a `WP_Error`'s
  message and honours `exit`. Before, it always exited.
  `_xmlrpc_wp_die_handler` prints nothing without an XML-RPC server.
- **`wp_send_json`** sends its status through `status_header` and returns
  after `wp_die` in an AJAX request instead of exiting.
- **`wp_nonce_ays`.** For log-out it asks "You are attempting to log out
  of {site}" with a confirm link. Otherwise it says "The link you followed
  has expired." with a link back to a safe referer, titled "An error
  occurred.". Before, it gave one fixed message.
- **Shared fix: referers.** `wp_get_raw_referer` and
  `wp_get_original_referer` read `$_REQUEST` and `$_SERVER`, as the
  reference does, so a plugin that sets them is heard. Before, they read
  the engine's request object. `wp_original_referer_field` falls back to
  `$_SERVER['REQUEST_URI']`.
- **Shared fix: `is_ssl` with no request** (the command line) reads
  `$_SERVER['HTTPS']` and the port, as WP-CLI sets them for an https site.
  With a request, nothing changed.
- **Shared fix: HEAD requests** follow no redirects unless they ask to
  (`redirection` 0).
- **Shared fix: lists.** `wp_parse_list` hands an array back as given.
  `wp_parse_id_list` keeps the keys where each id first appeared.
- **`wp_extract_urls`** matches as the reference does: an optional scheme
  and one or two slashes, a run with no space, parenthesis or angle
  bracket up to its last dot, then word characters and URL punctuation (a
  colon only before a digit). Each URL is entity-decoded and loses its
  semicolons. So `https://localhost/x` is not a URL but `/to/file.txt` is.
- **`do_enclose`** is real. An enclosure whose address left the content is
  deleted. Each new link, through `enclosure_links` and without its
  fragment, gets a HEAD request and is kept when its content-type is audio
  or video. Kept quirks: the length comes from `Content-Length` as written,
  so a plain array of headers gives 0. The stored type is the one the
  link's extension matched, carried over from the last link that had one,
  and empty before any. An extension that matches no type gives the last
  type in the list. `wp_get_http_headers` returns a HEAD request's headers
  whatever the status, false on failure.
- **Smaller fixes.**
  - `path_is_absolute`: a backslash start or `C:\` counts; `C:/` and URLs
    do not.
  - `validate_file`: 1 for a lone `../`, more than one, or one that is not
    the last segment; 2 for a colon in second place.
  - `wp_unique_filename` hands its callback the whole file name.
  - `iis7_supports_permalinks` has its filter.
  - The status phrase table is the reference's (`Minn\Http\StatusText`).
  - `wp_get_server_protocol` keeps HTTP/1.1, 2, 2.0 and 3, else HTTP/1.0.
  - `wp_get_nocache_headers` adds `Last-Modified` false after its filter.
  - `wp_is_uuid` is lowercase only, and a version other than 4 is done
    wrong.
  - `_http_build_query` honours its prefix, separator and encoding.
  - `wp_get_admin_notice`: its two filters, the id before the class, bare
    true attributes, and done wrong for a type with a space.
  - `force_ssl_admin` keeps a value it is given.
  - `wp_suspend_cache_invalidation` keeps its setting, and
    `clean_post_cache` waits while it is on.
  - `wp_guess_url` guesses from the request.
  - `wp_auth_check_html` matches the reference's whitespace.

## WordPress 7.1.3 (2026-10-09)

The references (all six Cove copies) moved from 7.1 to 7.1.3, and the
engine reports 7.1.3 (`Engine::WP_VERSION`, the `version.php` shape file;
`contracts/layout.md`, "What a host sees", says why it is the latest patch
of the branch and not the newest WordPress). The database revision is still
61833. 7.1.3 adds `_wp_is_template_path_allowed`, four methods
(`WP_Customize_Header_Image_Setting::sanitize` and `sanitize_choice`,
`WP_REST_Comments_Controller::check_target_post_permission`,
`WP_Sitemaps::send_404`) and `WP_Widget_Factory::INSTANCE_KEY_PREFIX`.
Every probe was rerun on 7.1.3: 110 fixtures kept their content, 7 only
moved the version, and 4 changed for real.

- **Sitemaps (suite sitemap-hooks).** The main query no longer makes a
  sitemap request a 404 before template_redirect; the sitemaps server
  judges it there. A page with nothing on it, or a provider nobody
  registered, is a 404; a page a plugin fills past the page count prints.
- **Fixtures that changed:** the widget factory keys instances
  `spl_object_id:N`; the tooltip helpers write their toggle's attributes in
  name order; `get_custom_header` gives whole-number width and height (0,
  not false, without header support); and the version moves the dependency
  ETags and module `ver` strings. The image editor cases now keep to GD:
  whether Imagick passes its test depends on the host's ImageMagick (7.1.3
  accepts this machine's, 7.1 did not). `WP_Image_Editor_Imagick` is still a
  placeholder.

### The security releases, checked against Minn

7.1.1, 7.1.2 and 7.1.3 carry 19 security fixes. Those in wp-admin, the
Customizer, XML-RPC or multisite land in Minn's dead ends. The rest were
probed against the 7.1.3 reference (`patch-security`), and these were Minn's
too, now fixed:

- **Template path traversal (7.1.2, critical).** `locate_template` followed
  `../` out of the theme: a page name under a theme's `page-*` folder could
  reach any PHP file. A name with a dot-only segment now counts only when
  the file it reaches is really inside the folder
  (`_wp_is_template_path_allowed` judges the name).
- **Sticky posts (7.1.3).** Authors and contributors could make posts sticky
  through REST. Changing whether a post is sticky now takes
  `edit_others_posts` (`rest_cannot_assign_sticky`, after parameter
  validation); asking for what it already is does not.
- **Creating a post with an id.** Refused with `rest_post_exists` before the
  caller is judged, even signed out; an id of 0 is `rest_post_invalid_id`.
  The engine had made a new post. Routes can now name a refusal judged
  after their arguments and before their policy (`Route::$refuse`,
  `Rest\ExistingPost`); every post-type create route uses it.
- **HTML API comment text (7.1.1).** `--!>` could break out of a comment;
  both closers are now refused, done wrong.
- **Imgur embeds (7.1.3).** Imgur's oEmbed HTML was trusted and printed raw.
  The provider table is reseeded from 7.1.3 (Imgur, Someecards and the old
  ReverbNation pattern gone). Its VideoPress entry had the development host
  baked in; it now names each site's own address.
- **Custom header (7.1.1).** Width and height are cast to whole numbers,
  the stored data's too, so neither carries markup into an attribute.
- **Status hooks (7.1.3).** `{old}_to_{new}` fires only when the new status
  is registered and the old one is too (or is `new`); `{new}_{type}` only
  when the status and the post type are registered. A stored status could
  forge another action's name (status `wp_insert` fired `wp_insert_post`).

- **`wpautop` (7.1.1 class).** The engine split paragraphs inside a tag's
  attribute values, the break-out 7.1.1 fixed. `Minn\Content\Autop` was
  rebuilt from the reference's output alone: 8,911 fuzzed and real inputs,
  plus two independent fuzz sets of about 20,000 each, all matching in both
  line-break modes (probe `autop` pins a seeded sample). Kept on purpose, as
  observed: an unclosed tag, comment or CDATA section runs to the end and
  keeps its newlines; `<pre>` is cut out and put back untouched; a stray
  `</pre>` is dropped; block names match as prefixes in the cleanup rules
  (`<param>`, `<path/>`, `<thead>` count); `<p><blockquote>` turns inside
  out and the tag comes back lower-cased; whitespace around media, option
  and figcaption tags is squeezed only when the text uses them; newlines
  inside script, style, svg and math never become `<br />`. The rebuild
  holds its own markers (two private-use characters and a `<pre>` stand-in);
  input that already holds them is changed by the restore.

Found along the way and fixed: deleting or trashing a post unsticks it
first, `stick_post`/`unstick_post` fire `post_stuck`/`post_unstuck`, the
REST sticky write drops the request's cached option, and the REST template
lists take `post_type`, `wp_id` and `area` (declared and validated).

Checked and already matching: comments on private and unpublished posts
through REST, comment reparenting, contributors editing or autosaving
others' posts, and draft slugs through REST lists and search.

Owed: `make_absolute_url` resolves some relative paths differently from
7.1.3; and Minn Admin's comment screen should be checked for the stored
cross-site scripting 7.1.3 fixed in WordPress's (a Minn Admin task).

### Update checks and WP-CLI

`wp_version_check` is real: Minn answers `update_core` with one `latest`
offer for its version, asking no one. `Core_Upgrader` is a hand-written dead
end whose `upgrade()` is an error naming `wp minn update`. WP-CLI gets the
engine's `core update`, `core verify-checksums` (against the signed release:
`Ops\EngineChecksums`, which needs the update service's
`/v1/minn/releases/<version>`), a guard on `core download`, and
`wp minn update`. `contracts/cli.md` has the wording.

## rest-api.php's helpers (2026-10-09)

Probe `rest-api-helpers`. It registers one route of its own
(`zz-probe/v1/args`, in memory) and stores nothing.

- **Combined schemas.** `rest_get_combining_operation_error` answers as
  the reference does. One schema's refusal is named; with several, those
  saying only that the value is not their type (`rest_invalid_type` for
  the parameter itself) are set aside, and when one remains it is named:
  "{param} is not a valid {title}. Reason: …", or "{param} does not match
  the expected format. Reason: …", with its `position`. Otherwise "{param}
  does not match any of the expected formats." with no data. Before, the
  engine listed every refusal under a `details` key the reference does not
  have. `rest_find_any_matching_schema` now refuses through it too.
- **`rest_is_field_included`.** A parent named includes its children
  (`title` includes `title.raw`).
- **`rest_preload_api_request`.** An empty path asks for nothing.
- **Shared fix: the default REST filters.** `rest_api_default_filters`
  adds the reference's five: `rest_send_cors_headers`,
  `rest_send_allow_header`, `rest_filter_response_fields`,
  `rest_handle_options_request` and `rest_add_application_passwords_to_index`.
  `_oembed_rest_pre_serve_request` and `rest_cookie_check_errors` (at 100)
  are hooked at boot, as on the reference. The engine already does the
  CORS headers and the Allow header itself, so those two callbacks are
  marked done and never run twice; a plugin can still see them, and remove
  them.
- **`rest_cookie_check_errors`** is real: a result already reached stands;
  a signed-in user who did not sign in by cookie is left alone; otherwise
  no nonce makes the user 0 and lets the request on, a bad nonce is
  `rest_cookie_invalid_nonce` (403), and a good one passes. The engine's own
  sign-in does not set `$wp_rest_auth_cookie`, so for its requests the
  check only meets signed-out callers.
- **`rest_add_application_passwords_to_index`** adds the authorization
  screen's address where application passwords are available.
- **Shared fix: a cookie without a nonce (suite auth).** The engine refused
  a REST request carrying a good sign-in cookie and no nonce with 403
  `rest_cookie_invalid_nonce`. The reference treats it as signed out (401
  `rest_not_logged_in` where a route needs a user, the public answer where
  it does not); only a nonce that fails is refused. A nonce sent with no
  usable cookie is refused (403), as the cookie check above does. The auth
  suite had these two rungs written from the engine's own answers; it now
  asks the reference for every rung of the matrix.

Owed: the engine's own routes answer OPTIONS without a `schema` (the
reference includes each route's JSON schema); seeding those schemas is a
batch of its own. The probe's OPTIONS cases use a plugin route, which the
facade describes.

## The rest of formatting.php (2026-10-09)

Probe `formatting-remaining` proves the 27 formatting.php functions that
were real but unprobed: the text, user, locale and MIME type sanitizers,
`tag_escape`, `sanitize_url`, `convert_chars`, `ent2ncr`,
`normalize_whitespace`, `format_to_edit`, the deep mappers, `wp_parse_str`,
`esc_sql`, the UTF-8 checks, `wp_encode_emoji` and the emoji lists, the HTML
splitter, `get_url_in_content`, the targeted link rel callback and
`antispambot`, which is random and is judged by what it decodes to.

Fixed to match:

- **`tag_escape`** keeps hyphens, so a custom element name like
  `my-element` survives.
- **`sanitize_url` / `esc_url`**: encoded line breaks (`%0d`, `%0a`, any
  case) are removed until none remain. An address with no scheme gets
  `https://` when the allowed protocols include https but not http, and
  `http://` otherwise.
- **`wp_check_invalid_utf8($text, true)`** replaces each bad sequence with
  U+FFFD, as `wp_scrub_utf8` does, instead of dropping it.
- **`get_html_split_regex`** returns the reference's pattern exactly. The
  engine's own split already cut markup the same way.
- **`get_url_in_content`** allows spaces around the `=` after `href`.
- **`wp_targeted_link_rel_callback`** rebuilds a targeted link from its
  attributes: same order, single spaces, double quotes (single-quoted
  values are converted), and rel merged with the filter's value (deduped),
  kept where it was or added last. `rel=""` becomes `rel="noopener"`, and
  `target="_self"` counts as a target. A link with no target comes back as
  it was.
- **Deprecations**: `seems_utf8` (6.9.0, use `wp_is_valid_utf8()`),
  `wp_targeted_link_rel` and `wp_targeted_link_rel_callback` (6.7.0) call
  `_deprecated_function`, as on the reference. `wp_targeted_link_rel` raises
  its notice before any early return.

Kept on purpose: `_wp_iso_convert` with a non-hex escape raises PHP's
"Invalid characters" deprecation on both stacks.

## The rest of link-template.php (2026-10-09)

Probe `link-template-remaining` proves 31 link-template.php functions:
- the site, network, user and dashboard addresses and their filters;
- feed and search links, with pretty and plain permalinks;
- attachment, custom type and delete links;
- comment edit links;
- `get_adjacent_post` and its link tags;
- page number links and the paging tags;
- the posts pagination and post navigation markup;
- the comment paging links;
- short links in the head and the Link header.

Under WP-CLI the reference keeps its own address on https, and makes
`'relative'` absolute, through priority-0 filters the engine's probe runner
does not have. The probe therefore masks the scheme of the site's address and
leaves out the relative cases.

Fixed to match:

- **`user_admin_url`** is `wp-admin/user/` plus the path, with its own
  `user_admin_url` filter ($url, $path, $scheme). It was the admin address.
- **`get_search_link`** keeps a slash in the terms as a slash.
- **`get_pagenum_link`** reads `$_SERVER['REQUEST_URI']`, as the
  reference does. It drops the `paged` query var and rebuilds the rest of
  the query (`a[]` comes back as `a%5B0%5D`; a fragment stays in the path).
  - Pretty permalinks: a leading `/index.php` and a trailing `page/N` go.
    The first page is the path slashed as the structure is. Later pages add
    `page/N`.
  - Plain permalinks: the path stays as it was and the page goes in `paged`.

  `page` is kept. It used to read the engine's request and dropped `page`
  too.
- **`get_adjacent_post`** returns null with no current post or no such
  taxonomy. It returns an empty string when the post's type is not in the
  taxonomy, when it has no terms to stay in, or when no post qualifies.
- **`wp_shortlink_wp_head`** prints the short link of the current query
  (`wp_get_shortlink(0, 'query')`), so it runs `pre_get_shortlink` and
  `get_shortlink` and gives the front page its home address.
  `Theme\HeadLinks::shortlink`, which built the tag from the resolution
  alone, is gone.
- **Shared fix: `get_post()` with no argument** reads only the global
  post. It no longer falls back to the runtime's copy, so once a caller
  clears `$post` there is no current post, as on the reference.

## The rest of post.php (2026-10-09)

Probe `post-remaining` proves the 30 post.php functions that were left:
- page statuses, and which statuses and posts are publicly viewable;
- post type capabilities and labels;
- custom fields and post thumbnails;
- the author SQL;
- page children and hierarchy loops;
- MIME type matching and its SQL;
- relative upload paths, the original image address, and post tags;
- the status transition defaults: scheduling, publishing what is due, the
  front page, posts page, sticky and privacy page resets, term counts, old
  slugs and dates, the after-insert action (it saves a revision on update),
  trashing a post's comments, attachment files, and the initial post meta.

The probe puts back every option it can change, clears its cron events (a
`do_pings` it caused included), and deletes its posts, comments, terms and
files.

Fixed to match:

- **`get_post_type_capabilities`** builds the capability names; it used to
  return the object's `cap`. The order is:
  - the eight primitive names, plus six more for a mapped type;
  - the type's own names merged over them (a new name is added after);
  - `create_posts` last, the merged `edit_posts` unless the type names it.

  A one-name `capability_type` list has no plural, so its plural names end
  in `_`. A mapped type records its meta capabilities. `register_post_type`
  uses the same rule (`Runtime\Registry::capabilities`), so `create_posts`
  now follows an overridden `edit_posts`.
- **`get_post_type_labels`** builds the labels from the type's own
  (`Runtime\Registry::labels`), then runs `post_type_labels_{$name}` on the
  object. It used to return the object's `labels`.
  - The base labels are page's for a hierarchical type, post's otherwise.
  - A missing name comes from `label`, a missing singular name from the
    name, the menu name from the name, all items from the menu name, and
    archives from all items.
  - The admin bar name comes from the singular name, or else the type's
    name.
  - `template_name` is added only when there is a singular name.

  `register_post_type` builds its labels the same way, so custom labels
  keep the reference's order.
- **`wp_match_mime_types`**:
  - Groups come back with the last pattern first. Matching is
    case-sensitive.
  - A type/subtype pattern must match whole, `*` standing for one name
    (`[-._a-z0-9]+`, so not `svg+xml`).
  - A bare type matches from the start, then, in a second pass, anywhere.
    `*` and an empty pattern match everything.
  - A string of real types is split on commas.
- **`_transition_post_status`**:
  - A post becoming published gets its permalink as its guid when it has
    none. The cached post keeps the old value.
  - It runs the deprecated `private_to_published` (2.3.0, use
    `private_to_publish`).
  - Every transition clears the post's `publish_future_post` event, and
    `_future_post_hook` sets it again for a future post.
- **`_reset_front_page_settings_for_post`** also unsticks the post, of any
  type.
- **`wp_trash_post_comments`** returns how many comments changed. On a
  second call that is 0, since comments already set aside with their post
  do not count.
- **`wp_delete_attachment_files`** deletes only inside the uploads folder,
  and returns false when any file is missing or outside it. The main file is
  tried even when it is missing.

Owed: `register_post_type` does not run `post_type_labels_{$name}`, and the
built-in types' labels are never filtered. On the reference a plugin can
rename Posts this way.

## The rest of theme.php (2026-10-09)

Probe `theme-remaining` proves the 27 theme.php functions that were left:
- theme mods, read and removed;
- the custom header and background tags and callbacks: the header image
  and text colour, random default headers, the video address, the header
  markup, and the background style;
- the theme feature registry and the default supports;
- theme roots and directories, stylesheet addresses, and the child theme and
  current theme checks;
- a deleted attachment leaving the theme mods;
- the custom CSS post.

`remove_theme_mods()` deletes the theme's mods option, so the probe puts
back its autoload as well as its value. An earlier run restored only the
value, and the re-created option came back as `auto`. That broke the
theme-switch probe until the option was repaired.

Fixed to match:

- **`_delete_attachment_theme_mod`** also removes `background_image` when it
  is exactly the attachment's address. The header image and its data stay,
  and so does `background_image_thumb`.
- **`wp_get_custom_css_post`** remembers the active theme's lookup in
  `custom_css_post_id`, as the reference does. A post id is trusted as it is
  (its name is not checked), and -1 means there is none, with no lookup. A
  missing value is looked up and recorded, as the found id or -1. Any other
  stylesheet is simply looked up.

Kept: the engine's probe runner has not run `init`. So the header markup's
`wp-custom-header` enqueue raises the "too early" notice there and not under
WP-CLI. That is the runner's doing, not the engine's.

## The rest of media.php (2026-10-09)

Probe `media-remaining` proves 23 media.php functions:
- image size registration, sizes from metadata, width and height strings,
  and ratio matches;
- srcset and sizes for crafted metadata, and srcset added to content images;
- lazy loading defaults, and the audio and video extensions;
- attached media and attachment taxonomies;
- the adjacent image links in an attachment's gallery;
- real image files: sizes read, and an intermediate size made;
- editor support and output formats;
- the upload size limit and the playlist scripts.

The reference picks Imagick and the engine GD (its Imagick editor is still a
placeholder), so the probe compares file sizes by type only.
`wp_enqueue_media` is left out: the engine does not run the media modal, and
its version is empty.

Fixed to match:

- **Srcset (`wp_calculate_image_srcset`, `Media\Sizing::sources`).** The
  source must name the image's folder and file, `2026/10/name-1024x512.jpg`.
  Any host will do, a CDN's included. A source in another folder, or a bare
  file name, gets no srcset. The candidates are addressed from the uploads
  folder, not from the source's own folder.
- **The adjacent image links:**
  - A post that is not among the gallery's images counts as the last of
    them.
  - The image's alt is the neighbour's title (`get_the_title`), even over a
    stored alt.
  - `previous_image_link` and `next_image_link` hear the neighbour's id (0
    when there is none), the size and the text.
- **`wp_image_editor_supports`** asks `_wp_image_editor_choose`, so the MIME
  type and methods count. It used to ask only whether GD was loaded.
- **`upload_size_limit`** hears both PHP limits, not zeros.

Owed: `wp_enqueue_media`, and the Imagick editor (on the reference,
`wp_image_editor_supports` answers for Imagick).

## template.php's template getters (2026-10-09)

Probe `template-getters` proves the 18 `get_*_template` getters. For each
query a theme can be asked to render, it records:
- the hierarchy each getter offers;
- what the getter answers: the block template canvas, a theme file, or
  nothing;
- the block template it picks (`$_wp_current_template_id`).

It covers 404, archives, attachments, authors, categories, dates, embeds, the
front and posts pages, pages, a post type archive, the privacy policy page,
search, singles, tags and terms. The canvas lives in a different folder on
each stack, so the probe names it rather than locating it. The probe removes
the `post-format-gallery` term that `set_post_format` makes, when the term
was new; other probes count format terms.

Fixed to match:

- **`get_query_template`** runs `locate_block_template`, as the reference
  does. A theme's block template for the hierarchy becomes the canvas, and
  its id goes into `$_wp_current_template_id` (`twentytwentyfive//single`,
  `//page`, `//archive`, `//search`, `//404`, `//index`). The engine's
  file-only check (`_minn_block_template_exists`) is gone.
- **Attachments** offer `type-subtype.php`, `subtype.php`, `type.php`, then
  `attachment.php`. It was type first.
- **A custom taxonomy's term** also offers `taxonomy-{taxonomy}-{term id}.php`.
- **`wp_insert_post` with a page template the theme does not offer** (shared
  fix).
  - The post is saved either way, with its terms and `meta_input`.
  - With `$wp_error`, it then answers `invalid_page_template` ("Invalid page
    template."), stores no template, and runs no save hooks.
  - Without `$wp_error`, the post keeps `default`.

  An explicit `default` is stored, not deleted. An empty template stores
  nothing. The engine used to store any template it was given.

## The rest of comment.php (2026-10-09)

Probe `comment-remaining` proves the 23 comment.php functions that were left:
- comment meta, counts per post, and default comment statuses;
- the moderation and disallowed checks, and the flood checks;
- the current commenter, from cookies and from a held comment's link;
- comment cookies;
- new comments: approval, refusals and notifications;
- comment form submissions;
- status changes and their transitions;
- comment counts, deferred and not;
- the last comment date and its cache;
- the initial comment meta.

How the probe works:
- Mail is caught in `pre_wp_mail`. The engine words its notices itself (they
  point to Minn Admin, not wp-admin), so the probe compares who hears and the
  subject, not the text.
- Flood blocking is off while the probe inserts comments, so the two stacks
  see the same thing whatever ran just before.
- It puts back every comment option it changes.
- `wp_set_comment_status` on a comment that does not exist is a fatal error
  on the reference. That skips the probe's cleanup under WP-CLI, so the probe
  leaves that case out.

Fixed to match:

- **`check_comment`**: a pingback or trackback is never previously approved.
  With `comment_previously_approved` on, it is held.
- **`wp_check_comment_disallowed_list`** also reads the comment without its
  tags and entities: `sp<b>am</b>word` and `spam&#119;ord` both match
  `spamword`. The author is read as given.
- **`wp_get_unapproved_comment_author_email`** reads `$_GET`, not the
  engine's request. When the link gives no address (a wrong hash, a comment
  that is not held or is over ten minutes old, or no link), it answers the
  commenter cookie's address.
- **`wp_update_comment_count($post_id, true)`** counts the posts put off and
  answers true.
- **`get_lastcommentmodified('server')`** is the GMT date moved by the
  server's offset in the database, which answers with microseconds
  (`2040-05-05 16:00:00.000000`).
- **`get_comment_count`** lists `all` before `total_comments`.

## The rest of blocks.php (2026-10-09)

Probe `block-helpers-remaining` proves the 18 blocks.php functions that were
left:
- block asset handles and their paths;
- registering a block's script and style handles, from a block folder put in
  the theme (the stacks share the themes folder);
- the metadata i18n schema and the old typography shape;
- serialized parent blocks, and traversal and serialization with callbacks;
- hooked blocks and the ignored-hooked-blocks metadata;
- pattern blocks resolved;
- block content through kses;
- the footnotes taken out of excerpts, and the wpautop hook put back.

The engine's probe runner has not run `init`, so its "too early" notices
for scripts and styles are left out of the notices the probe records.

Fixed to match:

- **Block script and style handles:**
  - A handle already registered is returned as it is.
  - A file that is not there is registered without a source.
  - A `viewScript` is deferred, not moved to the footer, and takes the
    block's version when it has no asset file.
  - Addresses come from the theme (or parent theme) for a file under it,
    from the includes address under wp-includes, and otherwise from the
    plugins address with the file's path under the plugins folder (the
    whole path when it lies elsewhere, as before; probe blocks).
    `_minn_block_asset_url` holds the rule.
- **`wp_migrate_old_typography_shape`** moves the old typography supports
  under `supports.typography`. That is `fontSize`, `lineHeight` and the
  `__experimental` font family, style, weight, letter spacing, decoration
  and transform. It takes them in that sorted order, sets each over a value
  already there, and names each in a 5.8.0 notice from
  `register_block_type_from_metadata()`.
- **`resolve_pattern_blocks`**:
  - A pattern that resolves to one block names itself on it
    (`metadata.patternName`, and `metadata.name` from the pattern's title).
  - A pattern inside itself is dropped.
  - One that does not exist stays as a pattern block.

## The rest of pluggable.php (2026-10-09)

Probe `pluggable-remaining` proves 16 of the 18 pluggable.php functions that
were left:
- users cached in bulk;
- nonce ticks;
- salts, judged against the site's own keys;
- auth cookies parsed, set and cleared, heard through their actions;
- signing out;
- nonce checks;
- redirects and safe redirects, heard through their filters;
- passwords set;
- the new user, password change and comment notifications.

The cookies are set with a given session token, so no session is made.
`auth_redirect` stays unproven because it ends the request. `wp_text_diff`
stays unproven until the engine draws the reference's diff table; it still
answers an empty string. The probe clears a `do_pings` event it caused and
puts back `wp_update_user_counts` and `moderation_notify`.

Fixed to match:

- **`check_admin_referer` and `check_ajax_referer`** read the nonce from
  `$_REQUEST`, not the engine's request. A nonce that was only in
  `$_REQUEST` failed and stopped the request.
- **`wp_logout`** ends the current session, clears the sign-in cookies,
  signs everyone out (`wp_set_current_user(0)`), then fires `wp_logout`. It
  only fired the action.
- **`wp_clear_auth_cookie`** runs `send_auth_cookies` (`true, 0, 0, 0, '',
  ''`) and expires the five sign-in cookies it names. It only fired
  `clear_auth_cookie`.
- **`wp_parse_auth_cookie`** with a scheme it does not know reads the auth
  cookie for the connection.
- **`wp_salt`** runs the `salt` filter.
- **`cache_users`** and **`update_user_caches`** put users' rows in the
  object cache, with login, email and nicename pointing at the id. The
  engine itself still reads users from the database; this is for plugins
  that read the cache, and `clean_user_cache` empties it again.
- **`wp_notify_moderator`** also tells the post's author when they may
  moderate the comment.
- **`wp_set_password`** gives its action the user as they were before the
  change, as a third argument.

## The rest of user.php (2026-10-09)

Probe `user-remaining` proves the 16 user.php functions that were left:
- user counts by role, with both strategies and the `pre_count_users`
  filter;
- the stored user count;
- email and user lookups, and blog membership;
- signing in by username or email, with the reference's refusals;
- the spam check, and application password validation without a request;
- the session token from the sign-in cookie, and ending the current
  session;
- the users cache's last change;
- the persisted preferences meta;
- new user notifications.

Fixed to match:

- **`count_users`** counts users by role. It used to give only the total.
  - The `time` strategy lists the roles that have users, in registered
    order, then `none`: users with no known role, which may be 0.
  - Any other strategy lists `none` first, then the roles as they are met,
    user by user in id order, each user's roles in their stored order.
  - `pre_count_users` may answer first.
- **`get_user_by('email')` and `get_user_by('slug')`** look the value up
  without its surrounding spaces, as a login already was (shared fix,
  `WP_User::get_data_by`).
- **The incorrect-password refusals** for a username and for an email end
  with the "Lost your password?" link to `wp_lostpassword_url()`.
- **`wp_authenticate_email_password`** refuses empty fields before it
  judges the address. An empty email is `empty_username` ("The email field
  is empty."), and an empty password is `empty_password`; both can come
  together. A login that is not an email still passes through.
- **`wp_get_session_token`** reads the token in the signed-in cookie (shared
  fix: nonces are made with it). It used to read the engine's sign-in state,
  which agrees on a real request.
- **`wp_destroy_current_session`** ends the session through the user's
  `WP_Session_Tokens`.
- **`wp_send_new_user_notifications`** passes `$notify` on as given. An
  empty one tells the admin only.

## The rest of load.php (2026-10-09)

Probe `load-remaining` proves 14 of the 15 load.php functions that were left:
- which admin the request is in, from the screen or the constants;
- protected ajax actions;
- the active and valid plugins;
- the server protocol and development modes;
- file modifications and changeable ini values;
- JSON and XML requests;
- basic auth, both detected and read from an Authorization header;
- whether the request uses themes.

`wp_debug_mode` stays unproven: under WP-CLI the reference's error settings
are WP-CLI's own (its filter returning false did not stop it), so the stacks
cannot be compared there.

Fixed to match:

- **`is_blog_admin`, `is_network_admin`, `is_user_admin`** answer from the
  current screen's `in_admin()` when one is set, else from
  `WP_BLOG_ADMIN`, `WP_NETWORK_ADMIN` and `WP_USER_ADMIN`. They always
  answered false. Minn draws no admin screen of its own.
- **`wp_is_ini_value_changeable`** is true only for a setting that may
  change at run time. A per-directory or system setting may not, and
  neither may an unknown one. It always answered true.
- **`wp_is_json_request`** reads Accept and Content-Type from `$_SERVER`,
  through `wp_is_json_media_type` (any case, `+json`, lists).
  **`wp_is_xml_request`** checks both headers, case-sensitively, for
  `text/xml`, the RSS, Atom and RDF types and the two oEmbed XML types; plain
  `application/xml` is not one. Both used to read the engine's request.
- **`wp_is_site_protected_by_basic_auth`** is true when `PHP_AUTH_USER` or
  `PHP_AUTH_PW` is set, in any context, then runs its filter. It always
  answered false.
- **`wp_using_themes`** is `WP_USE_THEMES`, then the filter, and false
  without the constant. It used to default to true. The engine now defines
  `WP_USE_THEMES` as WordPress's index.php does, before plugins load. That
  covers every request except its own scripts: wp-admin, wp-login.php,
  wp-cron.php, xmlrpc.php, wp-signup.php, wp-activate.php,
  wp-trackback.php, wp-comments-post.php, wp-mail.php and wp-links-opml.php,
  and also Minn Admin (`Engine::markThemedRequest`). A live check gave true
  for the front page, REST and feeds, and false for wp-login.php.

## The rest of embed.php (2026-10-09)

Probe `embed-remaining` proves the 17 embed.php functions that were left:
- oEmbed XML, and the REST response served as XML;
- oEmbed data for a site URL, and the rich data for posts and images;
- the post embed HTML;
- the embed template's pieces: site title, excerpt and its more link,
  attachment excerpts, the comments and sharing buttons, the sharing
  dialog;
- discovery links;
- the embed scripts and styles, by shape;
- the host script.

The probe masks the embed secret and the dialog's random ids.
`_oembed_rest_pre_serve_request` with a `WP_Error` result is a fatal error
on the reference, so it is left out.

Fixed to match:

- **`_oembed_rest_pre_serve_request`** serves an oEmbed response asked for
  as XML (`format=xml` on `/oembed/1.0/embed` or `/oembed/1.0/proxy`). It
  sends a `text/xml` content type, prints the document, and counts the
  request as served. It never did.
- **`_oembed_create_xml`** answers false for empty data, and writes under a
  root element given to it.
- **The oEmbed thumbnail of an image** is the size chosen by the width
  alone (`[$width, 0]`). The large size for 500 wide, scaled to fit, rather
  than the full image.
- **Shared fix: `wp_image_matches_ratio`.** The wider of the two sizes,
  fitted into the other's box, must come within a pixel of the other on both
  sides. The old check compared the fitted size with itself, so two sizes of
  the same width always matched. 300×200 counted as matching 300×169, and
  `image_get_intermediate_size(…, [300, 169])` picked the 300×200 size where
  the reference picks none. Every image probe (sizes, downsizing, the
  pipeline, content images, media tags) gave the same answers after the
  fix.

Seen once and not since: the api suite's `wp-user-query-sql` "by url" case
returned users with equal (empty) addresses in a different order. That is
the database's choice for ties, and three reruns matched.

## The rest of taxonomy.php (2026-10-09)

Probe `taxonomy-remaining` proves the 14 taxonomy.php functions that were
left:
- taxonomy labels built from a taxonomy's own;
- viewable taxonomies, and a term to edit;
- terms added to and removed from objects, and whether an object is in a
  term;
- term children and padded counts;
- hierarchy loops;
- term counts: now, deferred, and generic;
- the taxonomy cache;
- a category deleted.

The probe removes every term in its taxonomies at the end (a numeric string
given as a term makes a term of that name), their `_children` options, and
the `do_pings` event it caused. It puts `category_children` back.

Fixed to match:

- **`get_taxonomy_labels`** builds the labels (`Runtime\Registry::taxonomyLabels`),
  then runs `taxonomy_labels_{$name}`. It used to return the stored labels.
  - Derived as a type's are: name, singular name, admin bar name, menu name,
    all items, archives.
  - The base labels are category's for a hierarchical taxonomy, tag's for
    any other.
  - A taxonomy that names no template takes "%s Archives" with the singular
    name it ends up with, so a bare one is "Tag Archives".
  - `register_taxonomy` builds its labels the same way. A taxonomy
    registered without a label takes the base labels ("Tags"), not its own
    name.
- **`get_term_to_edit`** answers the term sanitized for editing (escaped,
  filter `edit`), or `''` for none. It used to return the term as stored.
- **`_get_term_children`** follows the stored hierarchy
  (`_get_term_hierarchy`), not the parent links in the list it is given.
  - A term with no recorded children has none.
  - Term 0 lists its direct children and goes down only through terms the
    hierarchy says have children.
  - An empty list is `[]`, and a taxonomy that does not exist is not an
    error.
- **`clean_taxonomy_cache`** marks the terms cache as changed.

## The rest of kses.php (2026-10-09)

Probe `kses-remaining` proves the 11 kses.php functions that were left:
- the slashed title and post filters, and the post filter applied deep;
- lower-cased allowed lists;
- the attribute check, by reference;
- a bad protocol taken off, once and in full, with spaces;
- entities decoded;
- attributes split into pairs;
- the `pre_kses` hook;
- slashes taken off quotes;
- the version.

Fixed to match:

- **`wp_kses_hair`** reads each value as HTML and writes it back with `&`,
  `<`, `>`, `"` and `'` encoded (`&apos;` for the last). Other characters,
  spaces included, stay as they were. `whole` is always `name="value"`, or
  the bare name for an attribute with no value, so single quotes, unquoted
  values and spaces around `=` all come out the same way. A URI attribute's
  value is checked for its protocol between the two steps. The engine's own
  kses does not use `whole`.
- **`wp_kses_bad_protocol`** (shared fix).
  - An address that already starts with an allowed `https://` or `http://`,
    in lower case, is left exactly as it is, trailing space included.
  - Everything else goes through `wp_kses_bad_protocol_once`, which now
    trims what follows the protocol, whether it keeps the protocol or drops
    it. So ` https://zz.example ` becomes `https://zz.example`, and
    ` javascript:x ` becomes `x`.
  - Every kses probe gave the same answers after the change.
- **`wp_kses_attr_check`** empties the name, value and whole when it refuses
  an attribute. A style keeps only what `safecss_filter_attr` leaves, and is
  refused when nothing is left (`behavior: url(x)`).
- **`wp_kses_array_lc`** turns each element's rules into a list with
  lower-cased keys; `true` becomes `[true]`.

The probe encodes its JSON with invalid UTF-8 substituted, since decoding
`&#99999999;` yields a byte sequence `json_encode` refuses.

## The rest of l10n.php (2026-10-09)

Probe `l10n-remaining` proves the 13 l10n.php functions that were left:
- `translate` and its context form, with their filters heard, and filters
  that translate;
- `_ex` and `esc_attr_x`;
- the no-op plurals and their translation;
- the list separator and the word count type;
- text domains loaded just in time and from a child theme or a must-use
  plugin, which have no files here;
- script translations read from a JSON file, with their three filters.

The engine already matched the reference on every row, so nothing changed.
On 7.1 `load_child_theme_textdomain` and `load_muplugin_textdomain` answer
true without any file, and run no `.mo` filters: the domain is registered
for loading just in time.

## The rest of query.php (2026-10-09)

Probe `query-remaining` proves the 11 query.php functions that were left:
- the date, time, favicon, robots, trackback and privacy policy conditionals,
  for the queries that set them;
- post data generated without moving the loop;
- the loop rewound;
- the old slug redirect.

The probe hears the old slug redirect's address through
`old_slug_redirect_url` and refuses it there, so nothing redirects. It marks
an empty query as a 404 itself, as the main request would; a `WP_Query` alone
does not.

Fixed to match:

- **`wp_old_slug_redirect`** works out the move from the query on a 404 the
  front has not settled. On a front request it makes the move the engine's
  resolver found, as before (`old_slug_settled`, `old_slug_location`).
  Otherwise, for example when a plugin calls it on its own 404, it does
  this:
  - It needs a `name`, and a single post type that is not hierarchical (a
    page or a hierarchical type has no former names).
  - It finds the post by its old date, else by its old name, through
    `old_slug_redirect_post_id` (0 when none).
  - The address is the post's permalink, plus `page/N` when the query is
    paged or `embed` when it is an embed. A feed gets the plain address.

Open: on the front, `/2024/old-hello/` is a 404 on both stacks. Worked out
from the query outside the front, the engine's old-slug lookup would have
found the post there. The front's resolver stays the judge on front
requests, so nothing shows; the difference is in what that request's query
holds.

## The rest of post-template.php (2026-10-09)

Probe `post-template-remaining` proves the 11 post-template.php functions
that were left:
- body classes for the views a theme renders: a post, a page with a
  template, an image, archives, search, a 404, a locked post;
- post classes;
- the title tags;
- the content and excerpt as printed: teasers, more links, pages,
  passwords;
- the password form;
- page links, in a preview too;
- attachments put before their content: image, audio and video.

Fixed to match:

- **`get_body_class`** off the front (no themed page has set the classes)
  works the list out from the main query, through
  `Theme\BodyClasses::forView`, the classic renderer's own rule now taking
  a view rather than a resolution. Classes given as a list are kept as given
  (`zz given two` keeps its spaces); a string is split on spaces.
- **A page with a template** names `page-template-{template}` twice, as the
  reference does. `array_unique` then leaves the gap at that index.
- **Shared fix: `Theme\QueryClasses`** reads a post's format from its
  `post_format` term. It always wrote `single-format-standard`, so an aside
  post's page carried the wrong body class. The attachment tokens
  (`attachmentid-`, `attachment-{mime}`) follow the query's attachment view,
  not the post type. An attached image asked for by `p` is a single, as on
  the reference.
- **Post classes** include the format term's class
  (`post_format-post-format-aside`).
- **`the_title_attribute`** strips tags from before and after, along with
  the title.
- **Shared fix: a query naming `preview` is a preview** (`is_preview`), as
  the reference's query parse makes it. Page links in a preview then keep
  `?preview=true`.
- **`prepend_attachment`** puts the audio or video player before an audio or
  video attachment's content (video takes the stored width and height). Any
  other attachment gets the medium-sized link in `<p class="attachment">`.
  `prepend_attachment` filters the whole piece.

## The remaining template tags for comments, terms and authors (2026-10-10)

Probe `template-tags-remaining` covers the last 19 tags from comment-template.php,
category-template.php and author-template.php. It looks at comment form id fields,
the unfiltered HTML nonce, reply links, comment counts as text, comment dates,
times and text, `pings_open`, term descriptions, parents lists, term lists,
`in_category`, the category and tag lists, the tag cloud from given tags,
`the_author_meta` and the multi-author cache. The comment tags already matched.
The probe found three differences:

- **`the_category`** now prints `get_the_category_list`. It used to build its own
  list, which ignored `$parents` and had no line breaks in the
  `post-categories` list. The category list now handles parents:
  - **"multiple":** the parents' linked chain goes before each category's link.
  - **"single":** the parents' names go inside the category's own link.
    In the list form, the reference's anchor has two spaces before `rel` there,
    and the engine keeps that.
- **`wp_generate_tag_cloud`** follows the reference's rules:
  - **Scale:** sizes come from counts passed through
    `topic_count_scale_callback`, not from raw counts, so they grow with the
    log of the count.
  - **Reversed range:** if largest is below smallest, sizes step by one unit.
  - **Equal range:** if smallest equals largest, the `aria-label` is dropped
    unless counts are shown.
  - **Label:** the text comes from `topic_count_text_callback` or the nooped
    `topic_count_text` ("%s item" by default), with the count formatted for the
    locale, and it is escaped as one attribute.
  - **Link:** escaped as a URL.
  - **Sort:** by name only for `orderby` "name"; any other value sorts by
    count. Only "DESC" in capitals reverses.
  - **`tag-link-` class:** uses the tag's own `id`, or else its key in the
    caller's list. Keys survive the sort, and taking the first N renumbers the
    numeric ones. `term_id` is never used, which is why `wp_tag_cloud` sets `id`.
- **`get_the_author_meta('')`** returns `''`. It used to return every meta value,
  which printed "Array".

Left out:
- A missing comment, because the reference warns when it reads one.
- What `__clear_multi_author_cache` clears, because the reference's object
  cache shows no key for it. The probe only checks that it returns nothing and
  that `is_multi_author` gives the same answer afterwards.

## The rest of general-template.php (2026-10-10)

Probe `general-template-remaining` proves 35 of the 36 general-template.php
functions that were left. It covers:
- the checked/selected helper and `wp_readonly`;
- the calendar's week arithmetic;
- the generator in every form;
- titles for the views a theme renders: `wp_title`, the document title, the
  single post title, and archive titles and descriptions, with their filters;
- modified dates and times, and the search query;
- the rich editor checks;
- the lost password and registration links;
- the referrer meta and resource hints;
- the site icon;
- the extra feed links;
- header, footer and sidebar templates, loaded from files the probe writes
  into the theme;
- the head, body and footer hooks.

Changes:

- **`wp_title`** is real now; it was a stub that only ran its filter.
  - **Parts:** a taxonomy's name then the term; a year, month and day; or
    "Search Results " then the words.
  - **Separator on the left:** the separator joins the parts and also leads the
    title.
  - **Separator on the right:** the parts are reversed and the separator trails.
  - **`wp_title_parts`:** hears the parts in reading order, even when there are
    none.
  - **Dates:** a date archive takes its parts from `m` when given, where a year
    alone counts. From the year and month query vars, a year alone gives no
    title, as on the reference.
- **Shared fix: the reference's default title filters are registered.**
  - `single_post_title`, `single_cat_title`, `single_tag_title` and
    `single_month_title` run `wptexturize` and `strip_tags`.
  - `wp_title` and `document_title` run `wptexturize`, `convert_chars`,
    `esc_html`, and then `capital_P_dangit` at 11.
  - `wp_get_document_title` no longer texturizes and escapes by hand: the
    filter's defaults do it, so removing one works as on the reference.
- **`get_the_archive_title`** works from the main query, not the classic
  renderer's resolution, so it works anywhere.
  - A view that is not an archive (search included) is "Archives" with no
    prefix.
  - The prefix goes through `get_the_archive_title_prefix`; an empty prefix
    leaves the bare name.
  - The block theme's query-title block still builds its title from the
    resolution (`Theme\ArchiveTitle`).
- **`feed_links_extra`** works from the main query, with the reference's
  default title formats and its filters. A singular view's comments feed shows
  while comments or pings are open or it has comments, and both
  comments-feed filters can hide it. Each archive kind and search has its own
  `feed_links_extra_show_{kind}_feed` filter.
- **`wp_site_icon`** prints its four tags through `site_icon_meta_tags`.
- **`calendar_week_mod`** is never negative and never minus zero.
- The front renderers still print their head links through `Theme\HeadLinks`,
  which `feed_links_extra` and `wp_site_icon` now duplicate. Folding those
  into one path is owed.

Left as found:
- **`_wp_render_title_tag`** prints nothing in the probe on both stacks: under
  WP-CLI the reference refuses title-tag support once `wp_loaded` has run.
  The engine's version checks theme support the same way.
- **`wp_enqueue_editor`** is not probed: the engine has no editor scripts.

## The rest of deprecated.php (2026-10-10)

Probe `deprecated-remaining` proves the 67 deprecated.php functions that were
left. For each call it records what the function returns, what it prints, and
the deprecation it announces. The notices are heard through
`deprecated_function_run`, with the errors switched off. The functions covered:
- author fields;
- the old permission checks;
- the old link and rel helpers;
- the robots tags, on a public and a private site;
- page by title;
- the disallowed list check;
- the loading attribute default;
- the small helpers;
- the functions that now do nothing.

Fixes:

- **Missing deprecation notices:** `get_page_by_title` (6.2.0, use `WP_Query`)
  and `like_escape` (4.0.0, use `wpdb::esc_like()`) now announce their
  deprecation.
- **`wp_get_loading_attr_default`** has its own rule, not the optimization
  attributes':
  - A header template part is never lazy.
  - Content images and post thumbnails in the main loop add to the shared
    content-media count, and the first three are not lazy.
  - Anything else, including content outside the loop, is lazy.

  `wp_img_tag_add_loading_attr` reads the same rule.
- **`print_emoji_styles`** prints the emoji stylesheet once per request, as on
  the reference. It used to print nothing.
- **`print_embed_styles`** prints the reference's tab and newline layout around
  the stylesheet. The embed template never calls it:
  `wp_enqueue_embed_styles` takes it off `embed_head` first.

Left out: `user_can_create_post` and `user_can_edit_post` for a user that does
not exist, because the reference warns when it reads one.

## The rest of admin-bar.php (2026-10-10)

Probe `admin-bar-remaining` proves the 16 toolbar menu builders that were
left. It runs each builder alone on a fresh `WP_Admin_Bar` and reads back the
nodes it adds, in order. It does this for the front views an administrator
sees (a post, a page, a category, an author, search, the home page), for a
subscriber, and for a visitor who is signed out. WP-CLI never loads the
toolbar class on the reference, so the probe loads it there; the engine
autoloads it.

- **The comments menu** shows the site's moderation count. The probe masks it
  as `{held}`, but only when it equals the stack's own `wp_count_comments`.
- **Fix: a signed-out visitor's WordPress logo** has only `tabindex`, with no
  `menu_title`, because there is no about page to link to.

Everything else already matched:
- the account item and menu;
- the site, edit site, comments, appearance and new content menus;
- the edit link for each view;
- the search form;
- the secondary groups;
- the menus a single site leaves empty: my sites, updates, recovery mode,
  the command palette and the sidebar toggle.

## The rest of widgets.php (2026-10-10)

Probe `widgets-remaining` proves the 11 widgets.php functions that were left.
It covers:
- sidebars registered in numbers;
- sidebar checks;
- an old-style widget registered by callback;
- widget id bases;
- the stored widget lists set and read back, with active widgets found by
  callback, id and base, and dynamic sidebars;
- settings converted to the multi-widget shape;
- the widget defaults;
- the RSS widget's form;
- widgets retrieved, with the theme the same and after a theme change.

The stored lists, the theme's mods and the probe's widget options are put back
afterwards, and checked byte for byte.

Fixes:

- **`register_sidebars`**: several sidebars take the number into a `%d` in the
  name, and onto the given id from the second one on (`zz-bar`, `zz-bar-2`).
  A single sidebar keeps its name as given. It used to give every sidebar the
  same id.
- **`wp_register_sidebar_widget`**: an empty callback takes the widget away. No
  `description` is added when the options have none.
- **`wp_convert_widget_settings`**: settings already numbered get
  `_multiwidget` too.
- **`wp_get_widget_defaults`**: one empty list per registered sidebar.
- **The RSS form**: a failed fetch is reported first, in
  `<p class="widget-error">`.
- **Shared fix: `retrieve_widgets`** now makes the stored lists fit:
  - The result is the inactive list, then every registered sidebar, holding
    only registered widgets.
  - Sidebars that are no longer registered go.
  - The inactive list takes, in order: registered widgets no list mentions
    (in registration order, skipping the `-1` placeholders); the widgets
    already inactive; then the widgets of the sidebars that went.
  - The result is stored.
  - Before `widgets_init`, every stored widget would look unknown, so the
    lists only gain the missing sidebars and nothing is stored. Theme
    switches in the probe runner happen at that stage.
  - The REST sidebar and widget routes now store through it, as on the
    reference.

Kept on purpose: the reference retrieves from the lists as last read through
`wp_get_sidebars_widgets()`, not from writes made since. A write followed by
`retrieve_widgets()` with no read in between works on stale lists there. The
engine always reads the stored option. The probe reads back after each write,
so both stacks retrieve the same lists. The engine's probe runner never fires
`widgets_init`, so the probe calls `wp_widgets_init()` when it hasn't run.

## robots-template.php (2026-10-10)

Probe `robots-template` proves the seven robots-template.php functions. It
runs the robots meta tag and each of its filters on a public and a private
site, for a post, a search and an embed, with five starting directive lists.
It then prints the tag from directives given as true, false, strings, numbers
and lists, and from pairs that contradict each other.

Fixes:

- **The noindex filters keep a private site out entirely.** This covers
  `wp_robots_noindex` on a private site, `wp_robots_noindex_embeds` on an
  embed, and `wp_robots_noindex_search` on a search. Each now gives what
  `wp_robots_no_robots` gives: `noindex`, plus `nofollow` on a private site or
  `follow` on a public one. They used to give `follow` everywhere, so a
  private site's search page said `noindex, follow`.
- **`wp_robots()`** prints any string as `directive:value`, even an empty one
  (`max-video-preview:`). Any other truthy value prints bare: a number or a
  list counts. Falsy values are left out. Contradictory pairs (`index` with
  `noindex`, `follow` with `nofollow`) both print, in the order given.

## The rest of revision.php (2026-10-10)

Probe `revision-remaining` proves the nine revision.php functions that were
left. It covers:
- the revisioned fields and their filter;
- the data a revision or an autosave is made from, given an array, a post, or
  a partial array;
- whether revisions are enabled, by type and by the keep filters;
- revisions saved by hand: a change, no change, a type without revisions,
  a draft, a missing post;
- the latest revision and the count;
- the revision on insert;
- a revision deleted, with its action;
- meta registered as revisioned: whether it changed, and copied onto a
  revision or another post.

Fixes:

- **`wp_get_post_revisions` with `'fields' => 'ids'`** returns a plain list of
  ids in the requested order. It used to return the post objects keyed by id.
- **`wp_delete_post_revision`** given anything but a revision returns null and
  leaves it alone. It used to hand back the post.

The first engine run of this probe exposed the `fields` bug the hard way. The
probe's cleanup cast each returned revision to an int; each was a post object,
so the cast gave 1 and post 1 (Hello world!) was force-deleted from the shared
database. It was restored from the marketing site's database, which still holds
the install's rows, with the guid set back to the test site's host. The probe
now deletes only positive integer ids of actual revisions.

## The rest of nav-menu.php (2026-10-10)

Probe `nav-menu-remaining` proves the seven nav-menu.php functions that were
left. It covers:
- a menu location registered alone;
- locations assigned and checked;
- menus recognised by id, slug, name and object;
- pages added to menus that take new pages;
- menu items taken away with the post they point at;
- locations carried across a theme switch: by the same name, by a common
  name, and with nothing kept.

The theme's mods, the menu options and the counts of menus and items are put
back afterwards, and checked on both stacks.

- **Fix: `has_nav_menu`** is true only for a registered location with a menu
  assigned. A location assigned but never registered is false. The result goes
  through the `has_nav_menu` filter, with the location.
- Everything else already matched. Removing a page's items renumbers the
  menu's other items. Only a top-level page published from another status is
  added. The theme-switch option is gone afterwards.

## The rest of capabilities.php (2026-10-10)

Probe `capabilities-remaining` proves the seven capabilities.php functions
that were left. The engine already matched; this batch only adds proof. It
covers:
- what a post's author can do: primitive and meta capabilities, for an
  editor, an author's draft, a subscriber, an author who is gone, and a
  missing post;
- the current user's capabilities for a site, as the administrator, an
  editor, a subscriber and nobody;
- super admin granted and revoked on a single site;
- the capabilities granted from the ones that imply them, alone and as
  `user_has_cap` filters on real users.

What the reference does here:
- A post whose author is gone can do nothing.
- On a single site, any site id answers for the current site, so
  `current_user_can_for_site(999, 'read')` is true for a signed-in reader.
- On a single site, `grant_super_admin` and `revoke_super_admin` return
  false and store nothing; the administrator is still a super admin.
- `install_languages` comes from `update_core`, `install_plugins` or
  `install_themes`.
- `resume_plugins` comes from `activate_plugins`, and `resume_themes` from
  `switch_themes`.
- `view_site_health_checks` comes from `install_plugins`.

## The rest of cache.php (2026-10-10)

Probe `cache-remaining` proves the nine cache.php functions that were left.
Everything happens in the probe's own groups, in the run's memory. It covers:
- counters raised and lowered: missing keys, numeric strings, words, values
  pushed below zero, an offset given as a string;
- values replaced only when present;
- several keys deleted at once;
- one group flushed while another keeps its values;
- groups kept out of a persistent cache;
- switching blogs on a single site;
- closing the cache, and starting it fresh.

Fixes:

- **`wp_cache_incr` and `wp_cache_decr`** go through the cache object, which
  already counts anything that isn't numeric as 0. Raising `'apples'` by 2
  stores 2. The facade used to refuse and keep the word, a second path that
  disagreed with the class.
- **`wp_cache_init`** started again starts empty, so nothing cached before
  survives. The engine's cache keeps its data in the runtime's store, so a
  restart empties that store. The first start, at boot, leaves it alone, so
  boot behaves as before.

## The rest of option.php (2026-10-10)

Probe `option-remaining` proves the seven option.php functions that were
left. It covers:
- network options on a single site: added, read, updated and deleted through
  their hooks, with the network id given and not, a pre filter answering, and
  the stored row and its autoload;
- an option's autoload set alone;
- a registered setting's default given back when none is passed;
- the core settings registered.

Fixes:

- **The network option hooks** hear the network id asked for (7 when 7 is
  passed), else the current network's. They used to hear 1 always.
- **Autoload:** only `false`, `'no'` and `'off'` mean off (with `'auto-off'`
  and 0). Anything else means on, including `null` and `'auto'`. The engine
  used to require an explicit yes.
- **`register_initial_settings`** registers only core's own settings. The
  site logo and site icon settings are the block library's, registered on
  `rest_api_init` right after it, so the REST settings keep their order.

## The rest of the block supports (2026-10-10)

The 32 block-supports functions left unproven fall into two groups.

**28 were already compared, but called through variable names.** Probe
`block-supports` called the register, apply and render functions as
`$fn = "wp_register_{$support}_support"`, which the proof count cannot see.
The three loops now call each function by name, through a map of closures.
The labels and order are unchanged, and both stacks still match the existing
fixture byte for byte.

**The last four get probe `block-supports-remaining`:** custom CSS taken out
of saved block markup, the save filters that do it, and the elements
support's styles for a parsed block. Fixes:

- **`wp_strip_custom_css_from_blocks`** leaves content it took nothing out of
  exactly as it came. Classic content that merely mentions `"css"` used to
  come back re-serialized and slashed. Content with CSS taken out comes back
  slashed, as before.
- **`wp_render_elements_support_styles`** leaves a block nobody registered
  alone. A registered block gets its elements class even when its type
  declares no color support, as on the reference.

## Calls the proof count could not see (2026-10-10)

This batch swept every probe for still-unproven functions whose names appear
only as strings. Four probes compared their behaviour with the reference but
called them through a string or a variable, which the proof count does not
see:
- `runtime-internals`: the four core block bindings sources;
- `plugin-queue3`: `block_header_area` and `block_footer_area`;
- `rest-api-helpers`: `rest_preload_api_request`, as `array_reduce`'s callable;
- `deprecated`: `wp_get_cookie_login`.

Each now calls the function by name through a closure. Where the name is also
needed as a hook callback (`has_action('init', …)`), the probe keeps the
string for that check. All four still match their fixtures on both stacks.

The other quoted names the sweep found are hook names, or callbacks checked
with `has_action`/`has_filter`, which are not calls. They stay owed.

## Template tags left across small files (2026-10-10)

Probe `template-family` proves 22 functions left in category.php, meta.php,
post-formats.php, post-thumbnail-template.php, nav-menu-template.php,
shortcodes.php and theme-templates.php. Some of these are common (`get_cat_ID`,
`get_post_thumbnail_id`, `has_post_thumbnail`, `the_post_thumbnail`,
`wp_nav_menu`): earlier suites and probes exercised them, but no probe called
them by name. It covers:
- category lookups by name and slug, a tag fetched, and old category fields
  added;
- registered meta keys and their defaults;
- post formats: the label, the check, the query var;
- post thumbnails: id, check, and the URL and tag printed;
- a classic menu printed five ways, and its tree walked;
- shortcodes inside HTML attributes, escaped shortcodes unescaped, one
  stripped, all removed;
- block templates enabled, and template slugs kept unique in a theme.

The theme's mods and the menu, item and term counts are checked unchanged on
both stacks.

- **Fix: `wp_filter_wp_template_unique_post_slug`** keeps a template's slug
  unique within its theme. If another template or part of the same type and
  theme holds the slug, it moves to -2, -3 and on; its own slug is no clash.
  Other post types, and a slug something has already settled, come back as
  given. It used to hand back the override always. It sits on
  `pre_wp_unique_post_slug`, so templates saved through the engine now get
  the reference's slugs.
- Everything else already matched.

## The rest of http.php (2026-10-10)

Probe `http-remaining` proves the eight http.php functions that were left. It
covers:
- the shared HTTP object;
- the safe request helpers: what they pass on, heard through
  `pre_http_request` and answered there, so nothing leaves the machine;
- cookies read back from a response;
- the allowed origins, and the origin headers' answer for each request
  origin.

- **Fix: `get_allowed_http_origins`** removes repeats before the
  `allowed_http_origins` filter. What the filter adds is kept as given,
  repeats included, in a plain list. The engine used to remove repeats after
  the filter, which also left gaps in the keys.
- **Fix: `get_http_origin`** reads `$_SERVER['HTTP_ORIGIN']`, as the reference
  does. It falls back to the request's own Origin header when `$_SERVER` has
  none.
- The safe helpers run `pre_http_request` before any check of the address, so
  a filter answering there answers for local and malformed addresses too.

Left owed: `send_frame_options_header`, `send_nosniff_header` and
`cache_javascript_headers` only send headers. Under the CLI, PHP keeps no
header list, so neither stack's headers can be compared.

## The rewrite family (2026-10-10)

Probe `rewrite-family` proves eight functions from rewrite.php and
rest-api.php. It covers:
- a feed added;
- permastructs added and removed;
- rewrite tags removed;
- taxonomy bases cleaned;
- the REST API's own rewrites, query var and default filters, each taken
  away first so that what the call adds is what comes back.

Everything happens in memory: no rules are flushed or stored.

Fixes:

- **`add_feed`** returns the action the feed answers on (`do_feed_{name}`),
  not the feed's name.
- **`_wp_filter_taxonomy_base`** takes off a leading `/index.php/` before the
  outer slashes. A base starting `index.php/` with no leading slash keeps it,
  as on the reference.
- **`rest_api_register_rewrites`** adds the four wp-json rules, at the site
  root and after index.php, with and without a route. It used to be empty,
  relying on the rules `WP_Rewrite` starts with.
- **`rest_api_init()`** registers those rewrites and the `rest_route` query
  var. It no longer fires the `rest_api_init` action: on the reference, the
  REST server fires that when it is built.

`create_initial_rest_routes` is left owed. The engine serves most of wp/v2
itself, so comparing its route registration means comparing route tables,
which is its own probe.

## Core block helpers (2026-10-10)

Probe `core-block-helpers` proves nine core block helpers. It covers:
- the calendar's published-posts flag: read, recomputed, and kept current on
  delete and on status changes;
- the sidebar a widget group renders in;
- footnotes added to the revisioned fields;
- whether a post template uses featured images, given `WP_Block` inner blocks
  as the post template hands them over;
- the RSS block, with its fetch answered locally.

- **Fix, a reference quirk kept: `discard_sidebar_being_rendered`** leaves
  the sidebar being rendered in place. On the reference, the global survives
  the discard, and the widget group block reads it. The engine used to unset
  it.

Still owed: the render callbacks the engine does not render yet. These are
the site logo, footnotes, tabs, tab list and tab panel, icon, playlist and
playlist track, query pagination (next, previous and numbers), the legacy
widget, and the term template. Called by name in the probe runner, they come
back empty on the engine where the reference prints markup. Each needs a real
renderer: a batch of its own, compared through the core-blocks probe's
render helper.

## Query pagination's own callbacks (2026-10-10)

Probe `query-pagination` proves the previous, next and numbers render
callbacks. Each is called by name with a block in a query's context, outside
a render, so no block-supports classes apply. The cases:
- the main query on the first, a middle and the last page, and past the end;
- labels given;
- each arrow;
- the label hidden;
- middle sizes for the numbers;
- a single page;
- a custom query paged by `query-{id}-page`, capped by its pages setting.

The callbacks used to hand off to the engine's renderer, which draws the
children only inside the pagination block, so called by name they printed
nothing. They are now real.

- **The main query** reads the global page (set by the main request) and
  `max_num_pages`. Previous shows past the first page, even past the last.
  Next shows before the last page. A link not shown is null.
- **A block's own query** reads `query-{id}-page` from the request, and its
  page count from the block's query, capped by `pages`. Previous shows only
  between the second page and the last; next shows before the last. Their
  links add the parameter to the current address. A link not shown is `''`.
- **The numbers** print nothing for a single page or a page past the end. A
  block's own query numbers its links `?query-{id}-page=N`, adding the
  reference's `cst` argument once past the first page.
- **Labels** are escaped, with "Previous Page" and "Next Page" as defaults.
  The pagination block's arrow or chevron goes before the label going back
  and after it going on. With `showLabel` off, the label moves to an
  `aria-label`.
- **Shared fix: `add_query_arg` with no URL** builds on the raw
  `$_SERVER['REQUEST_URI']`, as the reference does. A runtime built without
  one uses its request's path and query.

Still two paths: front-end pages render pagination inside the engine's
`QueryBlocks`, which already matches the reference pages. Rendering the
children through these callbacks there is owed.

## Site logo, footnotes and legacy widget callbacks (2026-10-10)

Probe `block-renderers` proves three render callbacks the engine used to leave
empty. Each is called by name outside a render, so no block-supports classes
apply.

- **The site logo** builds on `get_custom_logo`, which reads `site_logo`
  through the theme-mod override.
  - The image's width is the block's, with the height in proportion, set
    through `wp_get_attachment_image_src` so the srcset and sizes follow.
  - With no width, the image keeps its own size and the wrapper says
    `is-default-size`.
  - When the block isn't linked, the image stands alone. When it opens in a
    new tab, the link carries `target="_blank"` and an aria-label saying so.
  - With no logo, nothing.
- **Footnotes** prints a post's `footnotes` meta as a list, each note with a
  link back to where it is cited. Nothing for a post behind a password, a
  post without notes, or no post.
- **The legacy widget** renders a registered widget by id, or a widget type by
  its base.
  - The settings may be empty, or encoded as the widget-types route hands
    them out: base64, signed with `wp_hash`.
  - A raw instance is not trusted.
  - An unknown widget, a bad signature, or neither gives nothing.

Owed: the engine's own renderer still draws these blocks as empty on
front-end pages. It has its own entries for them, with its own block-supports
wrapper, so `do_blocks` doesn't reach these callbacks. Wiring them in needs a
probe of rendered pages (a site logo that is set, footnotes in a post, legacy
widgets in a widget area), as its own batch.

## The site logo, footnotes and legacy widget in pages (2026-10-10)

Probe `block-renderers-rendered` runs the three blocks through `do_blocks`,
the way pages render them, so the block supports' wrapper applies on the
reference. The cases:
- the site logo with a logo set: a width, no width, unlinked, aligned with a
  class, a new tab, a border style;
- footnotes in a post's loop, with and without a class and a font size;
- a legacy widget by base, with empty and with signed encoded settings.

The engine's renderer drew all three as empty. They now render through the
facade on `minn_block_renderers`, sharing the markup with the direct
callbacks.

- **The site logo:** the wrapper carries `is-default-size` without a width,
  then the alignment and the custom class, then `wp-block-site-logo`, with no
  style (the reference prints none, even for a border). A page renders the
  block with its type's defaults, so it is linked and opens in the same tab
  unless the block says otherwise. `Theme\Structure` no longer registers an
  empty site logo: it ran after the facade's renderers on some paths and
  undid them.
- **Footnotes** read the post the page is showing. The wrapper puts the style
  before the class.
- **The legacy widget** has no block wrapper; the widget's own markup is the
  block.

## The REST meta field classes (2026-10-10)

Probe `rest-meta-fields` proves `WP_REST_Meta_Fields` and its post, term,
user and comment kinds: five classes and their 31 methods. Meta of every
shape is registered first:
- single and multiple;
- string, integer, boolean, object and array;
- a default, a REST name, an edit-only context;
- hidden, and protected.

The probe then covers:
- each class's meta type, subtype and REST field type;
- its registered fields and field schema;
- the value read for an object;
- values written: changed, unchanged, deleted, refused by the schema, not
  allowed, signed out;
- the static preparer and the array check;
- the protected helpers, reached through a subclass;
- the deprecated field registration.

The classes are adapters over `Minn\Rest\RestMeta`, the rules the engine's
own controllers already used. They were placeholders, and the comment kind
was missing.

- **The kinds:**
  - post: type `post`, subtype the post type;
  - term: type `term`, subtype the taxonomy, and REST field type `tag` for
    `post_tag`;
  - user and comment: their own name for all three.
- **`get_value`** gives every registered key, whatever the request's context
  (an edit-only key too). Filtering by context is the controller's job.
  `RestMeta::value()` is the per-field read both share.
- **`update_value`** gives null, or every refusal merged into one `WP_Error`
  (each code with its message and data, in order). `RestMeta::errors()` is
  the loop; the controllers still throw the gathered refusal.
- **The schema** puts each key's schema under its REST name. The validate
  callback is the instance's `check_meta_is_array`, which returns an array as
  given and anything else as false.
- **`prepare_value`** prepares from the schema alone. A field whose prepare
  callback is `prepare_value` itself no longer recurses.
- **The helpers:**
  - `update_meta_value`: an unchanged value is left alone, then the user must
    be allowed to edit it;
  - `update_multi_meta_value`: needs edit permission, then replaces the list;
  - `delete_meta_value`: needs delete permission.

  Each gives true or a 403 `WP_Error`.
  `default_additional_properties_to_false` announces its 5.6.0 deprecation.
  `register_field` announces its own, then registers the meta field with
  `get_value`, `update_value` and the schema.
- The subclass files require their parent, since the facade loads classes
  alphabetically.

## The REST search handlers (2026-10-10)

Probe `rest-search-handlers` proves `WP_REST_Search_Handler` and its post,
term and post-format kinds: four classes that were missing, about 19
methods. It covers:
- each handler's type and subtypes;
- the ids and total for a search: a word only the probe's content holds,
  subtypes narrowed, paged, include and exclude;
- each item prepared with chosen fields, and its links;
- the post handler's protected title format and its deprecated item route.

They are built in the facade from WordPress functions.

- **Posts:** published posts of the public REST post types, not attachments,
  by relevance. The query arguments go through `rest_post_search_query`, and
  the ids are asked for after it. `WP_Query`'s own search leaves out a post
  behind a password, so the handler adds no condition of its own. Titles show
  without the "Protected:" prefix. An item links to its REST route and its
  type.
- **Terms:** terms of the public REST taxonomies, empty ones too, by name.
  The arguments go through `rest_term_search_query`. The total is
  `wp_count_terms`'s answer, a string. An item's `type` is its taxonomy, and
  it has no `subtype`. It links to its REST route and its taxonomy.
- **Post formats:** the formats whose term exists, empty or not, whose label
  holds the search. The arguments (`search`) go through
  `rest_post_format_search_query`. The list is sliced by page, and the total
  counts every match. An item has its label, its archive link (false without
  a term) and the `post-format` type, and no links.
- `detect_rest_item_route` announces its 5.5.0 deprecation.

Owed: the engine's own `/wp/v2/search` still answers only `type=post`.
Serving `term` and `post-format` through these handlers would complete the
route.

## The legacy HTTP transports (2026-10-10)

Probe `http-transports` proves `WP_Http_Curl`, `WP_Http_Streams` and
`WP_HTTP_Fsockopen`: three classes that were missing, five methods. They
send to the test site itself, so the same server answers both stacks. It
covers:
- a file the probe writes into uploads: GET, HEAD, HTTP/1.1, a byte limit,
  streamed to a file, and not waiting;
- the REST API's refusals for a JSON body and for each method, with and
  without a Content-Length;
- a 404, and the login redirect kept and followed;
- the errors for a refused port, an unknown host, a TLS handshake that
  fails, and a certificate no bundle trusts;
- what each `test()` answers and the filters it asks;
- the hooks a request runs and the arguments `http_api_curl` is handed;
- `WP_Http::handle_redirects` with `_redirection`;
- the streams transport's certificate check.

The classes live in the facade, with helpers in `http.php`.

- **Shared:** both transports merge the caller's arguments over their own
  defaults, in their own order (`decompress` is off by default). Cookies
  become `WP_Http_Cookie` objects and a Cookie header. Certificate checking
  goes through `https_ssl_verify`. A response comes back as
  `WP_Http::processHeaders` reads it, cut to `limit_response_size`. When the
  request streams, the body goes to the file and the answer's body is
  empty; a file that cannot be opened is `Could not open handle for fopen()
  to …`, before anything is sent. A 3xx goes to `handle_redirects`, which
  answers through `WP_Http` (a case-insensitive header dictionary and
  `http_response`). A request that does not wait answers with no status and
  no `filename` key.
- **Curl:** sent through the engine's curl client. It uses HTTP/1.0 unless
  1.1 is asked for. When verifying, it uses `sslcertificates` even when
  empty, so curl reports the empty bundle. `http_api_curl` receives the
  handle, the arguments and the URL. A failure is curl's own words with no
  `cURL error N:` prefix.
- **Streams:** written to a socket and read to the end. It adds no
  Content-Length of its own, so an HTTP/1.0 body without one never reaches
  the server. A request to `localhost` connects to 127.0.0.1. A socket
  that will not open fails as `{errno}: {errstr}`. A secure socket that
  fails with neither, and a certificate whose names miss the host, both
  fail as "The SSL certificate for the host could not be verified."
  `verify_ssl_certificate` reads the captured certificate's alternative
  names, ignoring case. A stream without a captured certificate is false.
- **Fsockopen** is the streams transport under its older name.

Fixed in `WP_Http` along the way, each proven by this probe:
- `buildCookieHeader` turns name => value pairs into `WP_Http_Cookie`
  objects in place.
- `WP_Http_Cookie::getHeaderValue` passes the value through
  `wp_http_cookie_value`.
- `handle_redirects` keeps the 3xx when `_redirection` is 0.
- `WP_Http::request` hands on `_redirection`: the redirects first asked
  for, last among the arguments.
- The Requests transport speaks HTTP/1.1 whatever version is asked for, as
  the reference does. The engine had been negotiating HTTP/2, so servers
  answered differently: a dynamic page came back with a Content-Length.
  The requests suite proves it too.

The requests suite compares again. Since the suites moved to the HTTPS
twins, the reference's bundled certificates had refused the local server.
Its network probe now trusts the server's own authority on both stacks.

Kept differently on purpose:
- Over HTTP/1.1 the streams transport asks the server to close the
  connection. The reference waits on a kept-alive connection until the
  server drops it, so the probe sends `Connection: close` itself.
- The probe leaves out the reference's fatal for an empty bundle on
  streams.
- A HEAD over streams is also left out, because it hangs on the reference.
- An array body is form-encoded by both transports. On streams the
  reference sends the word "Array".

## The REST registry controllers (2026-10-10)

Probe `rest-registry-controllers` proves four classes that were missing,
38 methods: `WP_REST_Post_Types_Controller`,
`WP_REST_Post_Statuses_Controller`, `WP_REST_Taxonomies_Controller` and
`WP_REST_Search_Controller`. They are called directly, as a plugin would,
by nobody, an administrator and a contributor. The contributor may edit
posts and assign categories but manage no terms, so it tells the
capabilities apart. It covers:
- their schemas and parameters;
- each permission check;
- lists and single items in each context, among them a type, statuses
  and taxonomies the probe registers, some hidden from REST;
- items prepared with chosen fields, and their links;
- a search through each handler, and the search controller's subtype and
  handler helpers;
- what registration gives a type's features and REST names.

The engine still answers these routes itself; the classes are for
plugins. They live in the facade, with their schemas captured in
`data/rest-schemas.json` (`type`, `status`, `taxonomy`, `search-result`).

- **Types:** the types shown in REST, keyed by name. Fields come in the
  reference's order and are filtered by the fields asked for and the
  context. Edit context needs the type's `edit_posts`: a list leaves out
  the others and comes back empty (`[]`), while the permission check
  refuses a caller who may edit none. A type hidden from REST refuses with
  `rest_cannot_read_type`. Links (collection, items) come only when
  `_links` is wanted.
- **Statuses:** the statuses that are not internal, with trash last. A
  public one is anyone's to read. The others (trash yes, other internal
  ones never) need `edit_posts` on some REST type. `get_item` reads no
  permission; `get_item_permissions_check` answers 404 for an unknown
  status. Every item links to its posts (`?status=`, none for publish),
  whatever the fields.
- **Taxonomies:** all, or a post type's, keyed by name. An empty list is
  an object. Edit context and the permission checks use `assign_terms`.
  A hidden taxonomy's permission check is false. Its `rest_namespace`
  shows as it is (false), while a type's falls back to `wp/v2`.
- **Search:** one handler per type, chosen by the request's `type`. The
  results are prepared by the handler and linked back to the search. The
  total and page count come as integer headers, and Link prev and next
  are rebuilt from the request's own query. Asking past the last page with
  results is a 400. The schema's type and subtype choices come from the
  handlers. `sanitize_subtypes` answers `any` alone when it is among them,
  and otherwise the handler's own subtypes.

Fixed along the way, each proven by this probe:
- Registering a type with an editor now adds `autosave`. Features
  declared ahead with `add_post_type_support` come first, and
  `supports => false` gives none (it had given a feature named "").
- A type or taxonomy hidden from REST has no `rest_namespace` unless it
  names one.
- A self link to a route the engine answers itself now hints only the
  methods its caller may use, judged by the engine's policies as its
  Allow header is. Before, it hinted every method, because the runtime
  server's stand-ins for those routes let everyone through.
- The engine's own `/wp/v2/taxonomies` route now filters edit context by
  `assign_terms`, as the class does. It had used `manage_terms`, which
  hid every taxonomy from a contributor.

Owed: the engine's `/wp/v2/taxonomies` reads the captured core taxonomies
only, so a plugin's REST taxonomy is not listed there, though the class
lists it.

## The REST block and menu-location controllers (2026-10-10)

Probe `rest-block-controllers` proves four classes that were missing,
34 methods: `WP_REST_Block_Types_Controller`,
`WP_REST_Block_Pattern_Categories_Controller`,
`WP_REST_Menu_Locations_Controller` and `WP_REST_Block_Renderer_Controller`.
They are called directly by nobody, an administrator and a contributor.
It covers:
- their schemas and parameters;
- each permission check;
- lists (narrowed by namespace) and single items, among them a block
  type, pattern categories and menu locations the probe registers;
- items prepared with chosen fields, and their links;
- menu locations assigned a real menu and a missing one;
- `rest_menu_read_access`;
- blocks rendered with attributes, inside a post, unknown, and with no
  renderer of their own.

The engine still answers these routes itself; the classes are for
plugins. Their schemas are captured in `data/rest-schemas.json`.

- **Block types:** reading needs `edit_posts` on some REST type, and
  `get_item_permissions_check` also answers 404 for an unknown block. The
  fields come from `Minn\Rest\BlockTypesController::fields`, which the
  engine's route now shares. A dynamic type links to its renderer
  (`?context=edit`).
- **Pattern categories:** readers as for block types. Each item is its
  name, label and description, the description only when it has one. No
  links.
- **Menu locations:** reading needs `edit_theme_options`, unless
  `rest_menu_read_access` (false, the request, the controller) returns
  true. The menu comes from the theme's locations, not from the item
  passed. A location links to its menu (`https://api.w.org/menu`, as a
  list, embeddable) only when that menu exists.
- **Block renderer:** someone who can edit posts may render. With a post
  named, they need to be able to edit that post: a missing post, or one
  they can't edit, is `block_cannot_read` "of this post". The post becomes
  the global post, so the block's `postId` context is that post. An
  unknown block, or one with no renderer, is `block_invalid`.

Fixed along the way, each proven by this probe:
- A block type registered without `supports` shows `{}` (the schema's
  default) on the engine's route too. It had shown null.
- The engine's menu-locations route no longer links to a menu that
  doesn't exist.

Owed: the engine's `/wp/v2/menus`, menu-items and menu-locations routes
don't yet honour `rest_menu_read_access`.

## The REST widget-type and sidebar controllers (2026-10-10)

Probe `rest-sidebar-classes` proves `WP_REST_Widget_Types_Controller` and
`WP_REST_Sidebars_Controller`: two classes that were missing, 30
methods. They are called directly by nobody, an administrator and a
contributor. It covers:
- their schemas and parameters;
- each permission check;
- the widget types (a widget the probe registers among them) with their
  helpers;
- settings encoded from an instance, a bad hash, and form data, given as
  a string and as the array the route hands on;
- a render;
- the sidebars (one the probe registers, one shown in REST, the inactive
  widgets) with their helpers;
- a sidebar's widgets saved three ways.

The probe puts back the widget options and the theme's mods.

- **Widget types:** the widgets registered so far, one per id base in id
  order. The last instance registered speaks for its base. A widget
  registered after `widgets_init` has no instance yet, so it is not a type
  until one is registered. `encode_form_data` decodes the instance when
  its hash holds, and otherwise refuses it as malformed. It takes form
  data only as an array (the route turns the string into one), and
  answers the form, the `the_widget` preview, and the settings encoded
  again (with `raw` when the widget shows its instance in REST). An
  unknown type "does not extend WP_Widget". `render` answers a whole
  preview page, even for an unknown type. Only its shape, and that it
  holds the widget's markup, are compared.
- **Sidebars:** anyone may read a sidebar registered with `show_in_rest`.
  Everything else needs `edit_theme_options`, and nobody's list keeps only
  the public ones. An item carries every schema property it has a valid
  value for (or the default), whatever fields were asked for. Its widgets
  are those registered, and links come only when `_links` is wanted.
  Saving takes the widgets given from every other sidebar, sends the ones
  dropped to the inactive widgets, and answers in the edit context.

Fixed along the way: the engine's own sidebars route now lets anyone read
a sidebar shown in REST (only those, for someone who cannot edit theme
options). A self link to one therefore hints GET.

Owed: `retrieve_widgets`. Under this block theme the reference returns
every registered sidebar's widgets to the inactive list, lost widgets
first. The engine keeps them in their sidebars. `wp_map_sidebars_widgets`
is still missing, though the reference's maps a sidebar onto the same id
or the matching slug. The probe starts from a state both stacks leave
alone. A theme switch elsewhere in the suites leaves a stash of the old
theme's widgets in the theme's mods, and the probe sets it aside for its
run.

## The REST widgets controller (2026-10-10)

Probe `rest-widgets-class` proves `WP_REST_Widgets_Controller`: the class
was missing, 20 methods. It is called directly by nobody, an administrator
and a contributor, with a sidebar shown in REST among the sidebars. It
covers:
- the schema and parameters;
- each permission check;
- the widgets listed (all, one sidebar's, embedded) and one up close;
- widgets created: raw settings, encoded settings, a bad hash, form data,
  raw settings for a type that keeps them, an unknown type, no type, and
  settings neither raw nor encoded;
- a widget created with no sidebar;
- updates, moves, and updates for unknown widgets;
- deletes with and without force;
- the helpers.

One controller is used throughout, so the widgets are retrieved once, from
a state both stacks leave alone.

- **Reading:** anyone may read the widgets of a sidebar shown in REST, and
  someone who can edit theme options may read every sidebar's. Nobody's
  list keeps only the public sidebars. An item carries its id, base and
  sidebar, and its markup as that sidebar wraps it (none among the
  inactive widgets, and none unless `rendered` is wanted). Its form and
  settings come only in the edit context. Links (itself, its type,
  embeddable, and its sidebar with a trailing slash) come only when
  `_links` is wanted. A widget that isn't registered is a 500
  `rest_invalid_widget`.
- **Saving** (`save_widget`): the type must have registered its update
  callback. A widget registered after `widgets_init` hasn't until it
  registers itself. A new widget takes the next free number for its type.
  Settings are raw when the type shows them in REST, or encoded with a
  matching hash, or the form data for that number. Each refusal has its
  own message. The settings go through the widget's update and
  `widget_update_callback`, and the instance is registered at once.
- **Create:** the widget goes to the sidebar named. With none named it is
  in no sidebar, and the answer says `sidebar: null` and has no markup. The
  answer is in the edit context, with 201.
- **Update:** a WP_Widget that no sidebar holds is a 404. A widget of an
  unknown type is refused when settings are given.
- **Delete:** with force, the settings are removed and the answer is the
  widget's previous form. Without force, the widget goes to the inactive
  widgets.

Kept differently on purpose: forced deletion removes the settings
directly, where the reference runs the type's update callback with a
delete request.

Owed, and a `retrieve_widgets` matter: on the reference, the target hints
for a widget's self link run the route's own permission check, and its
`retrieve_widgets` files a widget in no sidebar among the inactive
widgets. The engine judges target hints by its policies, so that widget
stays where it is. The probe leaves it out of its final count.

## The view configurations (2026-10-10)

Probe `rest-view-config` proves `WP_REST_View_Config_Controller` and
`WP_View_Config_Data`: two classes that were missing, 22 methods. The
engine now serves `/wp/v2/view-config` through the controller, registered
in `create_initial_rest_routes`. Unauthenticated, its answers match the
reference byte for byte: the missing arguments, the refusal, an unknown
entity, and OPTIONS. The probe covers:
- the schema and its nine parts;
- the capability each entity needs, and the permission check for nobody,
  an administrator and a contributor;
- every core post type's configuration, custom types, taxonomies, other
  kinds, and entities that are not shown;
- empty objects cast by the schema;
- the data class's patches;
- the `get_entity_view_config_{kind}_{name}` filter.

- **Entities.** A post type or taxonomy must be shown in REST. Anything
  else is a 404 `rest_view_config_invalid_entity`, decided in the
  permission check (`get_items` itself answers whatever it is asked). The
  capability is the type's `edit_posts`, the taxonomy's `manage_terms`,
  `manage_options` for `root`, and `edit_posts` for any other kind. A
  caller without it is refused with `rest_cannot_read`.
- **Configurations.** These are captured in `data/view-configs.json`:
  - page, wp_block, wp_template and wp_template_part each have their own;
  - every other post type has a generic one, and every other kind
    another.

  The first view of the generic and page configurations takes the type's
  `all_items` label ("All items" for a type that does not exist). The
  patterns list a view per pattern category plus "Uncategorized", sorted
  by title without regard to case. Templates list the active theme's name
  as an author filter. Template parts list their areas, General last. The
  answer is kind, name and version 1, then the configuration as the filter
  left it, with empty objects cast by the schema (`Schema::castEmptyObjects`).
- **Patches** (`Minn\Rest\ViewConfigPatch`). Only version 1 is accepted;
  any other version refuses the whole patch. Only the documented keys are
  patched (`default_view`, `default_layouts`, `view_list`, `form`); an
  undocumented one is refused on its own while the rest applies.
  - `set` replaces each key's value.
  - `merge` merges maps key by key. Lists are joined, and their members
    are matched by identity: a word itself, or a map's id or slug. A
    member without one is appended, and a repeated word is not added
    twice.
  - `replace` merges, but replaces lists whole.
  - `remove` takes keys out of a map, or members out of a list by
    identity, level by level. At the top it ignores a plain list of keys.
  - A null removes what it names.
  - A map over a list (or the reverse) is refused from `merge_properties`
    and leaves that key alone; a list over a word replaces it.
  - Each mistake is a `_doing_it_wrong`.
- **Filter.** `get_entity_view_config_{kind lowercased}_{name}` receives
  the data and `['kind', 'name']`. Its patches show in the answer, which
  carries no version of its own.

Owed:
- `_register_core_block_patterns_and_categories` is still a placeholder,
  so core's pattern categories are missing from the engine. The probe
  compares the patterns' views without them.
- `get_post_type_object` builds a new object on every call, and
  `$wp_post_types` is never set. On the reference both hand out the one
  registered object, so a plugin that relabels a type through either
  (the "rename Posts" snippet) is heard everywhere. On the engine it is
  lost. This shared machinery wants its own batch and a full run.

## Post type and taxonomy objects, shared (2026-10-10)

Probe `type-object-sharing` proves what the reference does: one object
per registered post type and taxonomy.
- `get_post_type_object`, `get_post_types` (objects), `$wp_post_types`,
  `register_post_type` and the `registered_post_type` action all hand out
  the same object.
- So do `get_taxonomy`, `get_taxonomies`, `get_object_taxonomies`,
  `$wp_taxonomies` and `register_taxonomy` for taxonomies.
- Registering again puts a new object in the old one's place, and
  unregistering takes it away.
- A change a plugin makes to an object (its labels, a flag) is seen by the
  lists that filter on it and by REST.

The engine had built a new object on every call and never set either
global. So the snippet that renames Posts through
`$wp_post_types['post']->labels` was lost, and a flag set on an object
filtered nothing.

- **The registry holds the objects** (`Registry::shareObjects`,
  `postTypeObject`, `taxonomyObject`). Every registered type and taxonomy
  gets its object at boot, once the facade classes exist. A later
  registration makes its own, and `$wp_post_types` and `$wp_taxonomies`
  are bound to them by reference. Adding or removing a taxonomy's object
  type updates its object in place, so a plugin's other changes stay.
- **REST reads them.** `/wp/v2/types` and `/wp/v2/taxonomies` are now
  served by `WP_REST_Post_Types_Controller` and
  `WP_REST_Taxonomies_Controller` (proven in "The REST registry
  controllers"). The engine's `TypesController` and `TaxonomiesController`
  are gone: one path. This also serves a plugin's REST taxonomy, which
  the old route, reading the captured core taxonomies, answered with a
  404. The bodies match the reference.

Owed: the engine's REST answers lack `X-Robots-Tag: noindex` and
`Vary: Origin`, and the CORS allow headers when a request sends an
Origin. That holds for every route, the engine's own and the runtime's.
The bodies are unaffected.

## The REST server's own headers (2026-10-10)

Every REST answer the reference serves over HTTP carries `X-Robots-Tag:
noindex`, and `rest_send_cors_headers` adds two more things:
- For a request from an Origin, even `null`, that origin is echoed in
  `Access-Control-Allow-Origin`, with `Access-Control-Allow-Methods:
  OPTIONS, GET, POST, PUT, PATCH, DELETE`,
  `Access-Control-Allow-Credentials: true` and `Vary: Origin`.
- Without an Origin, `Vary: Origin` goes out only for a GET from someone
  not signed in.

A signed-in caller's answer also carries the no-cache headers
(`rest_send_nocache_headers`, then `nocache_headers`). The engine had sent
none of these, on its own routes or the runtime's. A browser on another
origin could not use the API with credentials, and nothing told caches
that the answer varies by origin.

`Api::withServerHeaders` adds them as the answer leaves over HTTP. The
CORS headers depend on `rest_send_cors_headers` staying hooked on
`rest_pre_serve_request`, so a plugin that unhooks it turns CORS off, as
on the reference. The facade's `rest_send_cors_headers` now sends the
same headers when called. Across 40 requests (five routes; signed out and
signed in; with an Origin, with a null one, as a preflight, as a POST) the
headers match the reference exactly. `rest-envelope` pins seven of them.
The http-transports fixture was recaptured, because the REST answers it
requests from the test site now carry these headers on both stacks.

## The application passwords REST controller (2026-10-10)

Probe `rest-app-passwords-class` proves
`WP_REST_Application_Passwords_Controller`: the class was missing, 24
methods. It is called directly, as a plugin would, by nobody, an
administrator and a subscriber, on themselves and on each other, by id and
as "me". It covers:
- the schema and parameters;
- every permission check;
- passwords listed, read (in each context, and with none), created (an
  app id, a taken name, no name), renamed (to a taken name too) and
  deleted one at a time and all at once;
- the current password, outside an application-password sign-in;
- the helpers.

The probe removes its passwords and its user, and puts back the
administrator's own list exactly.

- **Permissions.** The user named is looked up first ("me" means the
  caller, and refuses nobody with `rest_not_logged_in`), so a missing user
  is a 404 for anyone. Each action then asks its own meta capability:
  `list_app_passwords`, `read_app_password`, `create_app_password`,
  `edit_app_password`, `delete_app_passwords` or `delete_app_password`,
  the per-password ones given the uuid. Each refusal uses the
  authorization code: 401 for nobody, 403 for someone signed in.
  Introspection is for the caller alone. The deprecated
  `do_permissions_check` (5.7.0) asks `edit_user`.
- **Answers.** A password shows its uuid, app id ('' when it has none),
  name, created and last-used times (site-local, no offset), and last IP.
  A new password is shown once, chunked, in an edit-context answer with
  201 and a Location header. Fields follow the request's context, and a
  request with no context shows none. Links come only when `_links` is
  wanted. A taken name is allowed, both for a new password and for a
  rename. An empty name is refused by `WP_Application_Passwords`. A delete
  answers the password as it was, and deleting all of them answers the
  count.
- **The database form** is the name, plus the app id for a new password,
  through `rest_pre_insert_application_password`.

The engine's own route still answers over HTTP. The class is for plugins.
