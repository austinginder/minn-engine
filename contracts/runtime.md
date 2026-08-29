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

## What a plugin cannot do yet

Twelve of the dogfood site's twenty-five plugins load as code now
(`runtime-report.php`), and the nine dogfood pages render at parity with
them running. What the rest ask for, in order: the admin host
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
