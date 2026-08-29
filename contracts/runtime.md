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
The read is cached in the `minn_runtime_symbols` option, keyed by the
folder's newest modification time. This is what keeps a site rendering when
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
stylesheets (blocks.css, the global styles, the theme's style.css) print
from a callback at `wp_head` priority 8 registered after `wp_print_styles`,
so plugin styles come first and a plugin's `wp_head` output at a lower
priority precedes them, as on the reference.

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
  engine registers the same ids against `/minn-engine/*.js`.
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

## E3 as placeholders: the admin host that is not there

Minn has no `/wp-admin/` and will not grow one; Minn Admin is the admin. What
plugins need from the admin host is for its symbols to exist so they load
and stay quiet. Three generated layers do that:

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
- **The file skeleton**: `tests/tools/site-skeleton.php <site root>` writes
  every `wp-includes/*.php` and `wp-admin/includes/*.php` the reference has
  (`data/reference-files.json`, 1,139 files) as one-line placeholders under
  the site's `public/`, so `require ABSPATH . 'wp-admin/includes/image.php'`
  gets a file that does nothing. Nothing outside those two trees is written,
  so no engine route is shadowed. The files are gitignored; run the tool
  for each site (run-all does for the two local ones).
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

## What a plugin cannot do yet

All twenty-five of the dogfood site's plugins load as code now
(`runtime-report.php`), and the nine dogfood pages render at parity with
them running, Jetpack included. What the rest ask for, in order: the admin host
(`WP_List_Table`, screens and screen options, `iframe_header`, the
`WP_Filesystem` family and the upgraders); `WP_HTML_Processor` (the tag
processor exists; the tree-aware one does not); `WP_Site`/multisite shims;
`WP_Term_Query`/`WP_User_Query` objects; `.mo` translations; `fetch_feed`;
the customizer and widget screens (the classes exist so plugins load;
nothing is served); a front-end main query fed from the engine's own
resolution (the conditional tags answer on the command line only).

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

Known gaps after this pass: the engine registers no core shortcodes
(`[gallery]`, `[caption]`, `[audio]`, `[video]`, `[playlist]`, `[embed]`)
where the reference registers seven; `wp_list_comments` prints only the
html5 format; `logged_in_as` markup is unverified; pretty URLs for a
plugin's post types and taxonomies (`/product/hoodie/`,
`/product-category/clothing/`) do not resolve yet, which is the next step
on the WooCommerce lab.
