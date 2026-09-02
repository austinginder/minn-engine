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
scan. This is what keeps a site rendering when
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
  timeout row; an expired transient is deleted on read.
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
  `data/kses.json`, captured from `wp_kses_allowed_html('post')`; numeric
  entities normalise to at least three digits (`&#65;` → `&#065;`).
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
  recurring rescheduled, then the action fires).
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
  routes serve `rest_do_request` in-process and `register_rest_field`
  additions are attached to their items.
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
- **Hashes, honestly**: the reference stores its own `$generic$` fast hash
  (BLAKE2b, unsalted, 30 bytes, base64url), whose exact construction did
  not fall out of black-box probing; the engine cannot verify a password
  the reference created. The engine stores phpass `$P$` hashes (a public
  algorithm, `Auth\Phpass`), which the reference verifies too, so a
  password created on Minn keeps working after a switch back to WordPress.
  A site moving to Minn recreates its application passwords.

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
- PHPMailer\PHPMailer\{PHPMailer,SMTP,Exception} are the engine's own:
  state recording in the class, MIME composition in `Mail\Mime`, SMTP
  delivery through `Mail\Smtp::sendRaw` (send() now composes onto it).
  Gravity SMTP assigns the STATIC PHPMailer::$validator before anything
  else, so the property must exist. Its sandbox (test_mode) short-circuits
  before send either way.
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

## What a plugin cannot do yet

All twenty-five of the dogfood site's plugins load as code now
(`runtime-report.php`), and the nine dogfood pages render at parity with
them running, Jetpack included. Remaining Speak / Hear gaps, in order:
`WP_HTML_Processor` (the tag processor exists; the tree-aware one does
not); `.mo` translations; `fetch_feed`; `WP_Term_Query` as a real query
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
| `WP_Http` | `Http\Client`, `Http\Outbound`, `Http\Exchange` | the curl transport: request value in, status + last-hop headers + Set-Cookie values + body out |
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
