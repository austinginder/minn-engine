# `Minn\Runtime`

the WordPress runtime plugins load against

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Abilities`](#abilities) | final class | 151 | The abilities registry behind the wp_*_ability facade: categories and |
| [`AjaxController`](#ajaxcontroller) | final readonly class | 72 | admin-ajax.php, the endpoint plugins post their front-end work to: a form |
| [`AllowedOptions`](#allowedoptions) | final class | 26 | The settings-page allowlist plugins extend: option group => the option |
| [`Assets`](#assets) | final class | 328 | The registry behind wp_register_/wp_enqueue_ for scripts and styles: |
| [`Avatar`](#avatar) | final class | 62 | Avatars the way get_avatar_data and get_avatar decide them: the argument |
| [`BlockFilters`](#blockfilters) | final class | 82 | The block-level filters plugin code hooks (pre_render_block, |
| [`BlockHooks`](#blockhooks) | final class | 69 | The Block Hooks API on the engine's own front end: a plugin asks for its |
| [`BlockMetadata`](#blockmetadata) | final class | 95 | block.json to the settings a block type registers with: the property |
| [`BlockTemplates`](#blocktemplates) | final class | 66 | Block templates plugins register at runtime, by their namespaced name |
| [`BlockWidget`](#blockwidget) | final class | 30 | A block widget's legacy class name. Every widget the block editor saves |
| [`CommentCloser`](#commentcloser) | final readonly class | 22 | The Discussion setting that closes comments on old posts. Observed on the |
| [`CommentEvents`](#commentevents) | final readonly class | 110 | What the reference's REST comments controller tells plugins, for the |
| [`CommentForm`](#commentform) | final class | 107 | The comment form's submission with plugins loaded |
| [`CommentQuery`](#commentquery) | final readonly class | 117 | Comment reads in the get_comments() shape: arguments to rows or a count, and the approval breakdown wp_count_comments reports. |
| [`Connectors`](#connectors) | final class | 212 | The connectors registry: the external services a site talks to (AI |
| [`Constants`](#constants) | final class | 66 | The constants plugin code expects: the fixed set from data/constants.json |
| [`CronTable`](#crontable) | final class | 131 | The cron option's shape, operated on as data: timestamp => hook => key => |
| [`DbDelta`](#dbdelta) | final readonly class | 125 | dbDelta as the reference does it: a CREATE TABLE statement creates the |
| [`EarlyFilters`](#earlyfilters) | final class | 20 | Filters that run before the runtime exists, over the hooks added that |
| [`Hooks`](#hooks) | final class | 311 | The hook registry plugin code registers into and the engine fires. |
| [`Interactivity`](#interactivity) | final class | 509 | Server-side directive processing for the Interactivity API: the state and |
| [`MainQuery`](#mainquery) | final class | 34 | The query variables the reference's main query would carry for a URL the |
| [`Meta`](#meta) | final readonly class | 175 | The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need. |
| [`MetaClause`](#metaclause) | final class | 120 | The meta side of a post query: meta_key and its friends as one clause, |
| [`MetaTypes`](#metatypes) | final class | 21 | Meta types a plugin brought, by the table it named on $wpdb as |
| [`NavMenu`](#navmenu) | final class | 303 | Nav-menu item decoration for wp_nav_menu(): the reference's class tokens |
| [`OEmbed`](#oembed) | final class | 92 | oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes. |
| [`ObjectCache`](#objectcache) | final class | 52 | The per-request object cache behind wp_cache_*: groups of keys, nothing persistent. |
| [`Options`](#options) | final class | 224 | Options as plugin code sees them: PHP values, decoded from the stored |
| [`PackageDownload`](#packagedownload) | final class | 32 | The publisher's say over its own download. Before fetching an update |
| [`PageMenu`](#pagemenu) | final class | 40 | The page-list menu a classic theme falls back to when no menu is |
| [`Pages`](#pages) | final class | 113 | get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result. |
| [`Patterns`](#patterns) | final class | 161 | The block pattern, pattern category, and block style registries as data. |
| [`PlaceholderTrace`](#placeholdertrace) | final class | 27 | Records every call into a generated placeholder while a site opts in by |
| [`Placeholders`](#placeholders) | final class | 49 | The printf placeholders plugin code hands wpdb::prepare, filled the way |
| [`PluginUpdates`](#pluginupdates) | final class | 65 | The update offers the site's own plugins publish. A plugin that hosts |
| [`Plugins`](#plugins) | final class | 213 | Loads the site's plugins into the runtime the way the reference does: |
| [`PostEvents`](#postevents) | final readonly class | 146 | What the reference's REST controllers tell plugins about a post they |
| [`PostInsert`](#postinsert) | final readonly class | 166 | The decisions behind wp_insert_post: which columns a postarr fills, when |
| [`PostLookup`](#postlookup) | final readonly class | 85 | The post reads plugin code asks for by shape: a page by title, revisions, counts. |
| [`PostQuery`](#postquery) | final class | 383 | The query WP_Query runs: its variables become one SELECT over the posts |
| [`PostSave`](#postsave) | final class | 182 | A REST save's columns through the filters the reference's save runs |
| [`QueriedObject`](#queriedobject) | final readonly class | 70 | Which object a query is "about", read from its flags and variables: a term |
| [`QueryFlags`](#queryflags) | final readonly class | 101 | The conditional flags a set of query variables implies (is_single, is_archive, |
| [`Recovery`](#recovery) | final readonly class | 214 | Recovery from a fatal in someone else's code. When a plugin or theme |
| [`Refusal`](#refusal) | final readonly class | 6 | A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error. |
| [`Registry`](#registry) | final class | 382 | Post types, taxonomies, and statuses as plugin code registers and reads |
| [`Runtime`](#runtime) | final class | 357 | The WordPress runtime the engine offers plugin code: the procedural |
| [`ScriptModules`](#scriptmodules) | final class | 308 | The script modules registry: registrations with typed dependencies, the |
| [`ScriptPack`](#scriptpack) | final class | 146 | The site-supplied script pack: the `wp-*` JavaScript packages the engine |
| [`Shortcodes`](#shortcodes) | final class | 143 | The shortcode registry plugin code fills with add_shortcode, and the |
| [`StoredObjects`](#storedobjects) | final class | 64 | The classes a stored blob may name and come back as. The serialized |
| [`SymbolGap`](#symbolgap) | final readonly class | 94 | The part of the reference's interface the runtime does not answer: names in |
| [`SymbolTable`](#symboltable) | final class | 69 | What a folder's PHP names, collected while its tokens are read: the |
| [`Symbols`](#symbols) | final class | 275 | A static read of what a plugin's PHP calls: global functions and classes |
| [`TagEditor`](#tageditor) | final class | 149 | Edits one start tag's attributes in place the way the reference's tag |
| [`TaxonomyClause`](#taxonomyclause) | final class | 178 | The taxonomy side of a post query: every query var the reference reads |
| [`TermEvents`](#termevents) | final readonly class | 62 | What the reference's REST terms controller tells plugins, for the |
| [`TermQuery`](#termquery) | final readonly class | 393 | Term reads in the shapes plugin code asks for: get_terms() arguments to |
| [`TermWriter`](#termwriter) | final readonly class | 192 | The decisions behind wp_insert_term, wp_update_term, wp_delete_term, and |
| [`TreeWalk`](#treewalk) | final class | 74 | The Walker contract's traversal: elements keyed by the walker's |
| [`UserEvents`](#userevents) | final readonly class | 72 | What the reference's REST users controller tells plugins, for the |
| [`UserInsert`](#userinsert) | final readonly class | 112 | The decisions behind wp_insert_user: what a new account needs, which email |
| [`UserQuery`](#userquery) | final readonly class | 49 | The user listing behind WP_User_Query: role filtering through the |

## Abilities

`final class Minn\Runtime\Abilities` · `public/minn/src/Minn/Runtime/Abilities.php`

The abilities registry behind the wp_*_ability facade: categories and
abilities recorded per request. The reference initialises the API
lazily, firing wp_abilities_api_init once on first access so plugin
registrations land before any lookup.

- const `STATE` = `'abilities'`

Used by: `Minn\Rest\AbilitiesController`

### static `initialize(): void`

Fires the init action once, then answers every later call from the recorded state.

### static `registerCategory(string $slug, array $args): bool`

Registers an ability category.

### static `register(string $name, array $args): ?array`

Registers an ability, or null when the name is taken or malformed.

### static `unregister(string $name): bool`

Removes an ability or a category.

### static `unregisterCategory(string $slug): bool`

Removes a category.

### static `find(string $name): ?array`

One ability or category, or null.

- `@return array<string, mixed>|null`

### static `findCategory(string $slug): ?array`

One category, or null.

### static `all(): array`

Every ability, or every category.

- `@return array<string, array>`

### static `permits(string $name): bool`

Whether the caller may run an ability: its own permission callback
decides, and an ability without one is open to any signed-in caller,
as the reference treats it.

### static `execute(string $name, mixed $input = NULL): mixed`

Runs an ability and returns what it produced. The caller checks
permits() first; this only executes.

### static `isReadOnly(string $name): bool`

Whether an ability is marked read-only, which decides the method its run endpoint takes.

### static `allCategories(): array`

Every category.

Internals: `state()` (private, line 18), `save()` (private, line 24), `forget()` (private, line 77)


## AjaxController

`final readonly class Minn\Runtime\AjaxController` · `public/minn/src/Minn/Runtime/AjaxController.php`

admin-ajax.php, the endpoint plugins post their front-end work to: a form
submitted without a reload, a spam check, a background job. The action a
request names runs the handlers registered as wp_ajax_{action} for a
signed-in visitor and wp_ajax_nopriv_{action} for anyone else, answered as
the reference answers them (contracts/runtime.md "admin-ajax.php").

It is an admin request: is_admin() is true while the plugins load and
admin_init fires before the handler. A handler usually ends the request
itself (wp_send_json, wp_die, exit), so the endpoint's headers go out
before it runs; one that returns is followed by the reference's "0".

- const `PATH` = `'/wp-admin/admin-ajax.php'`
- const `CORE` = `array (   'wp_ajax_nopriv_' =>    array (     'heartbeat' => 'wp_ajax_nopriv_heartbeat',   ),   'wp_ajax_' =>    array (     'rest-nonce' => 'wp_ajax_rest_nonce',   ), )` — The core actions the endpoint answers itself, under the prefix for who is asking: the action => the handler.

Used by: `Minn\Engine`, `Minn\Runtime\Constants`

### static `claims(Minn\Http\Request $request): bool`

Whether a request is for the endpoint, which the runtime boots as an admin request.

### `dispatch(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-admin/admin-ajax.php (public)`

Runs the handlers registered for the action the request names.

Internals: `action()` (private, line 74), `allowedOrigin()` (private, line 81), `registerCore()` (private, line 91)


## AllowedOptions

`final class Minn\Runtime\AllowedOptions` · `public/minn/src/Minn/Runtime/AllowedOptions.php`

The settings-page allowlist plugins extend: option group => the option
names a settings form in that group may save. The engine renders no
settings pages, so the list is only recorded, never enforced.

### static `merge(array $add, array $into): array`

The allowlist with each new name appended to its group once, groups
created as needed. A group handed as anything but a list adds nothing.

- `@param array<string, mixed> $add`
- `@param array<string, list<string>> $into`
- `@return array<string, list<string>>`


## Assets

`final class Minn\Runtime\Assets` · `public/minn/src/Minn/Runtime/Assets.php`

The registry behind wp_register_/wp_enqueue_ for scripts and styles:
handles, sources, dependencies, versions, inline additions, localized
data, and the head/footer split. Printing follows the reference's tag
shapes as far as captured; see contracts/runtime.md.

```php
__construct(string $kind)
```


### `watch(Closure $listener): void`

A listener called after every change, so a plugin-facing view can stay current.

### `setQueue(array $queue): void`

The queue as a plugin left it after editing the view directly.

### `register(string $handle, string|false $src, array $deps, string|bool|null $ver, mixed $extra): bool`

Registers an asset under a handle.

### `externalHosts(string $ownHost): array`

The hosts the queued assets load from, other than the site's own.

- `@return list<string> hosts of enqueued sources (dependencies first) away from the given host, for dns-prefetch hints`

### `deregister(string $handle): void`

Forgets an asset.

### `enqueue(string $handle): void`

Queues an asset for printing.

### `dequeue(string $handle): void`

Removes an asset from the queue.

### `registered(string $handle): bool`

Whether a handle is registered.

### `enqueued(string $handle): bool`

Queued directly, or pulled in as the dependency of something queued,
however deep. The reference answers the same way, and plugin code
leans on it: WooCommerce only attaches its settings blob when it
finds `wc-settings` "enqueued", and nothing queues that handle by
name, it only ever rides in as a dependency.

### `done(string $handle): bool`

Whether a handle has been printed.

### `addInline(string $handle, string $code, string $position): bool`

Attaches inline code to an asset.

### `addData(string $handle, string $key, mixed $value): bool`

Attaches a data key to an asset.

### `setTranslations(string $handle, string $domain, string $path): bool`

Names the text domain and folder a script's translations come from, and makes the script depend on wp-i18n.

### `data(string $handle, string $key): mixed`

A data key of an asset, or false.

### `localize(string $handle, string $name, array $data): bool`

Attaches a localized object to a script.

### `toPrint(?bool $footer = NULL): array`

Every queued handle not yet printed, dependencies first, filtered to the group (footer or not).

### `toPrintHandles(array $handles): array`

Named handles and everything they depend on, in printing order, whether
or not they were queued; what already printed is left out.

- `@param list<string> $handles`
- `@return list<string>`

### `withPath(): array`

Enqueued handles that name a file on disk, mapped to that path. A style
registered with a `path` datum is saying it can be inlined; whether it
is small enough to be worth inlining is the caller's decision.

- `@return array<string, string> handle => path`

### `unsource(string $handle): void`

Drops a handle's source so it prints as markup rather than a link.

### `markDone(string $handle): void`

Records a handle as printed.

### `item(string $handle): ?array`

One registered asset, or null.

### `items(): array`

Every registered asset.

- `@return array<string, array<string, mixed>>`

### `queue(): array`

The handles queued.

- `@return list<string>`

### `kind(): string`

Whether these are scripts or styles.

Internals: `changed()` (private, line 41), `ordered()` (private, line 249)


## Avatar

`final class Minn\Runtime\Avatar` · `public/minn/src/Minn/Runtime/Avatar.php`

Avatars the way get_avatar_data and get_avatar decide them: the argument
defaults, the email an id, user, post, or comment names, the Gravatar URL,
and the <img> attributes. Behaviour pinned by contracts/fixtures/api/functions.json.

### static `dataArgs(array $args): array`

The data arguments normalised: sizes to integers, the default token, the rating lowercased.

### static `hash(string $email): string`

The Gravatar hash an email yields; '' for no email.

### static `hashFromAddress(string $address): ?string`

A hash given as an md5.gravatar.com address, else null.

### static `urlArgs(array $args): array`

The Gravatar URL's query arguments for the data arguments. @return array<string, string|int|false>

- `@return array<string, string|int|false>`

### static `classes(int $size, bool $isDefault, mixed $extra): array`

The <img> classes: the size class, a default marker, the caller's own. @return list<string>

- `@return list<string>`

### static `extraAttributes(string $extra, mixed $loading, mixed $decoding): string`

The extra attribute string with loading and decoding added when the caller did not set them.


## BlockFilters

`final class Minn\Runtime\BlockFilters` · `public/minn/src/Minn/Runtime/BlockFilters.php`

The block-level filters plugin code hooks (pre_render_block,
render_block_data, render_block, render_block_{name}), applied around the
engine's own renderer so a plugin sees every block the page renders, not
only the ones it registered.

Used by: `Minn\Blocks\Renderer`


### static `active(): bool`

Whether any block filter is registered.

### static `toArray(Minn\Blocks\Block $block): array`

A block as the parsed array plugins receive.

- `@return array<string, mixed> the parsed-array shape plugin code reads`

### static `fromArray(array $parsed): Minn\Blocks\Block`

A block from the parsed array plugins hand back.

### static `before(Minn\Blocks\Block $block): Minn\Blocks\Block|string`

A short-circuit from pre_render_block, or the block as render_block_data left it.

### static `after(Minn\Blocks\Block $block, string $html): string`

A rendered block through render_block and its per-name filter.

Internals: `context()` (private, line 88)


## BlockHooks

`final class Minn\Runtime\BlockHooks` · `public/minn/src/Minn/Runtime/BlockHooks.php`

The Block Hooks API on the engine's own front end: a plugin asks for its
block to be inserted next to an anchor block in a template part or a
pattern (WooCommerce puts the mini-cart after the navigation in a
header), and the markup the renderer reads carries those insertions.

The facade owns the traversal (wp-api/blocks.php); this is the seam the
theme blocks call, and it stays inert until a plugin actually hooks
something, so a site without such a plugin parses nothing extra.

Used by: `Minn\Blocks\Dynamic\Theme\Structure`

### static `active(): bool`

Whether block hooks are registered and the runtime is up.

### static `forPart(string $markup, string $slug, string $area): string`

A template part's markup with its hooked blocks inserted. The context
is the part itself: a plugin reads its area to tell a header from a
footer, and its content to see whether its block is already there.

### static `registeredPattern(string $slug): ?string`

A plugin-registered pattern's content, hooks already applied by the
registry, for a slug the theme does not carry. Null when the runtime
is not up or nothing registered that name.

### static `forPattern(string $markup, string $slug, array $blockTypes, array $categories): string`

A theme pattern's markup with its hooked blocks inserted, with the
pattern array as the context (blockTypes and categories are how a
plugin recognises a header pattern).

- `@param list<string> $blockTypes`
- `@param list<string> $categories`


## BlockMetadata

`final class Minn\Runtime\BlockMetadata` · `public/minn/src/Minn/Runtime/BlockMetadata.php`

block.json to the settings a block type registers with: the property
renames, the script and style handles (registered through the closures,
one per entry), the view script modules, the block hooks positions, and
the render template as a callback. Behaviour pinned by contracts/fixtures/api/blocks.json.

- const `PROPERTIES` = `array (   'apiVersion' => 'api_version',   'name' => 'name',   'title' => 'title',   'category' => 'category',   'parent' => 'parent',   'ancestor' => 'ancestor',   'icon' => 'icon',   'description' => 'description',   'keywords' => 'keywords',   'attributes' => 'attributes',   'providesContext' => 'provides_context',   'usesContext' => 'uses_context',   'selectors' => 'selectors',   'supports' => 'supports',   'styles' => 'styles',   'variations' => 'variations',   'example' => 'example',   'allowedBlocks' => 'allowed_blocks', )`
- const `SCRIPTS` = `array (   'editorScript' => 'editor_script_handles',   'script' => 'script_handles',   'viewScript' => 'view_script_handles', )`
- const `STYLES` = `array (   'editorStyle' => 'editor_style_handles',   'style' => 'style_handles',   'viewStyle' => 'view_style_handles', )`
- const `POSITIONS` = `array (   'before' => 'before',   'after' => 'after',   'firstChild' => 'first_child',   'lastChild' => 'last_child', )`
- const `FIELD_HANDLES` = `array (   'editorScript' => 'editor-script',   'editorStyle' => 'editor-style',   'script' => 'script',   'style' => 'style',   'viewScript' => 'view-script',   'viewScriptModule' => 'view-script-module',   'viewStyle' => 'view-style', )`

### static `settings(array $metadata, Closure $scriptHandle, Closure $styleHandle, Closure $moduleId, Closure $render): array`

Block settings from a block.json, the way the reference maps its properties.

- `@param Closure(array, string, int): (string|false) $scriptHandle registers one script entry, answering its handle`
- `@param Closure(array, string, int): (string|false) $styleHandle registers one style entry`
- `@param Closure(array, string, int): (string|false) $moduleId registers one view script module entry`
- `@param Closure(string): ?Closure $render the render callback for a template path, or null when the file is missing`
- `@return array<string, mixed>`

### static `assetHandle(string $block, string $field, int $index = 0): string`

The script or style handle a block.json field registers under; core blocks keep the `wp-block-` spelling.

Internals: `handles()` (private, line 78)


## BlockTemplates

`final class Minn\Runtime\BlockTemplates` · `public/minn/src/Minn/Runtime/BlockTemplates.php`

Block templates plugins register at runtime, by their namespaced name
("plugin//slug"). A theme file or a saved template of the same slug
wins; otherwise the registered content renders for that slug.

Used by: `Minn\Runtime\Runtime`, `Minn\Theme\TemplateIndex`, `Minn\Theme\Templates`


### `register(string $name, array $args): array|string`

The registered row, or the refusal code the reference reports.

### `unregister(string $name): ?array`

Forgets a registered template; the row, or null.

### `all(): array`

Every registered template.

- `@return array<string, array<string, mixed>> by registered name`

### `get(string $name): ?array`

One registered template, or null.

### `bySlug(string $slug): ?array`

A registered template by slug, or null.


## BlockWidget

`final class Minn\Runtime\BlockWidget` · `public/minn/src/Minn/Runtime/BlockWidget.php`

A block widget's legacy class name. Every widget the block editor saves
is one block of markup, and a classic theme styles it by the widget
class the equivalent legacy widget used, so the wrapper carries a
second class named after the FIRST block in the content. A block with
no legacy equivalent adds nothing.

- const `BASE_CLASS` = `'widget_block'`
- const `LEGACY_CLASSES` = `array (   'core/paragraph' => 'widget_text',   'core/search' => 'widget_search',   'core/html' => 'widget_custom_html',   'core/archives' => 'widget_archive',   'core/latest-posts' => 'widget_recent_entries',   'core/latest-comments' => 'widget_recent_comments',   'core/tag-cloud' => 'widget_tag_cloud',   'core/categories' => 'widget_categories',   'core/calendar' => 'widget_calendar',   'core/rss' => 'widget_rss', )` — First block name => the legacy widget class a theme styles.

### static `classNameFor(array $blocks): string`

The legacy widget class a block widget maps to.

- `@param list<array<string, mixed>> $blocks the parsed content`


## CommentCloser

`final readonly class Minn\Runtime\CommentCloser` · `public/minn/src/Minn/Runtime/CommentCloser.php`

The Discussion setting that closes comments on old posts. Observed on the
reference: only the "post" type closes (the types filter changes nothing),
status is not consulted, the age is whole days on post_date_gmt and must
exceed the setting (a fifteen-day-old post stays open at fifteen), and zero
days switches the rule off.

```php
__construct(bool $enabled, int $days)
```


### `open(bool $open, Minn\Content\PostRecord|array|null $post, int $now): bool`

Whether comments stay open on a post under the close-after-days setting.


## CommentEvents

`final readonly class Minn\Runtime\CommentEvents` · `public/minn/src/Minn/Runtime/CommentEvents.php`

What the reference's REST comments controller tells plugins, for the
engine's own: with plugins loaded every change goes through the
runtime's comment functions (wp_insert_comment, wp_update_comment,
wp_set_comment_status, wp_trash_comment, wp_delete_comment), which fire
the reference's actions in its order, and the REST actions follow.
Without a booted runtime the rows are written as before and nothing is
told.

Used by: `Minn\Rest\CommentsController`

```php
__construct(Minn\Content\Comments $comments)
```


### `live(): bool`

Whether plugins are loaded to be told anything.

### `allow(string $address, string $email, string $dateGmt): void`

The check a new comment passes on the reference before it is written: check_comment_flood, with the address, the email and the GMT date.

### `insert(array $columns): int`

Writes a new comment and returns its id; an approved one is counted on its post. @param array<string, mixed> $columns

- `@param array<string, mixed> $columns`

### `update(Minn\Content\CommentRecord $comment, array $columns, ?string $status): void`

An edit through REST: the fields, as wp_update_comment writes them
(which tells plugins even when nothing changed), then the status
through wp_set_comment_status when it moves.

- `@param array<string, string> $columns`

### `trash(Minn\Content\CommentRecord $comment): void`

Moves a comment to the trash, keeping its status and the time for the way back.

### `delete(Minn\Content\CommentRecord $comment): void`

Removes a comment for good.

### `restSaved(int $id, Minn\Http\Request $request, ?Minn\Content\CommentRecord $before): void`

rest_insert_comment, then rest_after_insert_comment, with the comment as it stands and the request; no $before is a new comment.

### `restDeleted(Minn\Content\CommentRecord $comment, array $data, Minn\Http\Request $request): void`

rest_delete_comment, after a trash or a delete, with the comment as it was and the response. @param array<string, mixed> $data

- `@param array<string, mixed> $data`


## CommentForm

`final class Minn\Runtime\CommentForm` · `public/minn/src/Minn/Runtime/CommentForm.php`

The comment form's submission with plugins loaded
(wp_handle_comment_submission), in the order the reference runs it
(contracts/runtime.md "The comment form"): a reply to a comment still
held is refused; the post is looked at, each refusal with its action
(comment_id_not_found, comment_closed, comment_on_trash, comment_on_draft,
comment_on_password_protected) and pre_comment_on_post when it takes
comments; the signed-in user's own name and addresses replace the form's,
or a site that requires sign-in refuses; the required fields, an empty
comment (allow_empty_comment) and the column lengths are checked; then
wp_new_comment stores it, where preprocess_comment, the duplicate and
flood checks and pre_comment_approved have their say. A refusal is a
WP_Error whose data is the status the form answers with; none means a
blank page.

### static `submit(array $form): WP_Comment|WP_Error`

The stored comment, or the refusal the form answers with.

- `@param array<string, mixed> $form the posted fields, unslashed`

Internals: `store()` (private, line 71), `postRefusal()` (private, line 89), `fieldRefusal()` (private, line 118)


## CommentQuery

`final readonly class Minn\Runtime\CommentQuery` · `public/minn/src/Minn/Runtime/CommentQuery.php`

Comment reads in the get_comments() shape: arguments to rows or a count, and the approval breakdown wp_count_comments reports.

- const `DEFAULTS` = `array (   'post_id' => 0,   'post__in' =>    array (   ),   'status' => 'all',   'number' => '',   'offset' => 0,   'orderby' => 'comment_date_gmt',   'order' => 'DESC',   'fields' => '',   'count' => false,   'parent' => '',   'type' => '',   'author_email' => '',   'user_id' => '',   'search' => '',   'include_unapproved' =>    array (   ),   'comment__in' =>    array (   ),   'comment__not_in' =>    array (   ),   'post_status' => '',   'post_type' => '',   'author__in' =>    array (   ),   'date_query' => NULL,   'hierarchical' => false, )`

```php
__construct(Minn\Db $db)
```


### `count(array $args): int`

How many comments match the query args.

### `rows(array $args): array`

The comment rows matching the query args, ordered as asked.

- `@return list<array<string, mixed>>`

### `breakdown(int $postId): array`

The counts wp_count_comments reports, for one post or the site. @return array<string, int>

- `@return array<string, int>`

Internals: `where()` (private, line 53)


## Connectors

`final class Minn\Runtime\Connectors` · `public/minn/src/Minn/Runtime/Connectors.php`

The connectors registry: the external services a site talks to (AI
providers, spam filters, cloud services) and how each authenticates.
Rows are normalised on the way in, the way the reference keeps them;
the facade class hands them back to plugin code.

- const `Methods` = `array (   0 => 'api_key',   1 => 'application_password',   2 => 'none', )`
- const `AuthenticationKeys` = `array (   0 => 'method',   1 => 'credentials_url',   2 => 'setting_name',   3 => 'constant_name',   4 => 'env_var_name', )`
- const `MaskCap` = `16` — The reference stops adding bullets after sixteen, whatever the key's length.


### `register(string $id, array $args): ?Minn\Runtime\Refusal`

Registers a connector, or the refusal.

### `unregister(string $id): ?array`

Forgets a connector; its row, or null.

- `@return array<string, mixed>|null the row that was registered, null when there was none`

### `all(): array`

Every connector.

- `@return array<string, array<string, mixed>>`

### `get(string $id): ?array`

One connector, or null.

### `has(string $id): bool`

Whether a connector is registered.

### `registerDefaults(Closure $pluginActive): void`

The reference's three AI providers and Akismet. Activation is
checked through the caller's closure, so the plugin list is read
when asked.

- `@param Closure(string): bool $pluginActive`

### static `mask(string $key): string`

Keys of four characters or fewer are shown whole; longer ones keep their last four behind at most sixteen bullets.

### static `keySource(string $setting, string $envVar, string $constant, Closure $option): string`

Where a key comes from, in the reference's precedence: the environment,
then a constant, then the stored option; empty values do not count.

- `@param Closure(string): mixed $option`

### static `parseCredentials(string $value): array`

"user:password" split at the first colon, both halves trimmed; anything else is empty credentials.

### static `sanitizeCredentials(mixed $value, Closure $clean): array`

Stored credentials are an array of two text fields; a string, even a
"user:password" one, is not accepted from storage.

- `@param Closure(string): string $clean`

### static `credentials(array $auth, Closure $option, Closure $clean): array`

The credentials a connector authenticates with, from the same three
sources as a key, plus where they came from.

- `@param Closure(string): mixed $option`
- `@param Closure(string): string $clean`

Internals: `normalise()` (private, line 56)


## Constants

`final class Minn\Runtime\Constants` · `public/minn/src/Minn/Runtime/Constants.php`

The constants plugin code expects: the fixed set from data/constants.json
(captured from the reference) and the per-site ones computed here. Nothing
already defined is touched, so wp-config.php keeps the last word.

Used by: `Minn\Runtime\Runtime`

### static `define(Minn\Runtime\Runtime $runtime): void`

Defines the constants the reference defines at boot.


## CronTable

`final class Minn\Runtime\CronTable` · `public/minn/src/Minn/Runtime/CronTable.php`

The cron option's shape, operated on as data: timestamp => hook => key =>
entry, kept in natural timestamp order. The key is the reference's own
(a hash of the serialized argument list), so both stacks read one table.

Used by: `Minn\Cron\Cron`, `Minn\Ops\Diagnostics`

### static `key(array $args): string`

The key an event's arguments hash to.

- `@param array<int, array<string, array<string, array<string, mixed>>>> $crons`

### static `fromBlob(?string $blob): array`

The table read from the option's stored blob: the version marker
dropped, timestamps in order, an unreadable or absent blob empty.

- `@return array<int, array<string, array<string, array<string, mixed>>>>`

### static `hasNear(array $crons, int $timestamp, string $hook, string $key, int $window): bool`

Whether the same hook and arguments are already scheduled within the window around the timestamp.

### static `insert(array $crons, int $timestamp, string $hook, string $key, array $entry): array`

The table with an event added at a timestamp.

### static `remove(array $crons, int $timestamp, string $hook, string $key): array`

The table with one event removed.

### static `removeHook(array $crons, string $hook): array`

The table with every event of a hook removed.

- `@return array{0: array, 1: int} the table without the hook, and how many entries went`

### static `timestampsFor(array $crons, string $hook, string $key): array`

When a hook and key are scheduled.

- `@return list<int> every timestamp the hook and arguments are scheduled at`

### static `find(array $crons, string $hook, string $key, ?int $timestamp): ?array`

The entry for the hook and arguments at a timestamp, or at the next one when no timestamp is given, as [timestamp, entry].

### static `nextRun(int $timestamp, int $interval, int $now): int`

The next run of a recurring event: one interval from now, aligned to the original timestamp when it is in the past.

### static `due(array $crons, int $now): array`

The timestamps at or before now, in order.


## DbDelta

`final readonly class Minn\Runtime\DbDelta` · `public/minn/src/Minn/Runtime/DbDelta.php`

dbDelta as the reference does it: a CREATE TABLE statement creates the
table when it is missing, otherwise the columns and keys the statement
has and the table lacks are added. Nothing is ever dropped or altered.

```php
__construct(Minn\Db $db)
```


### static `creates(array $queries): array`

The CREATE TABLE statements in a batch, keyed by table name. @param list<string> $queries @return array<string, string>

- `@param list<string> $queries @return array<string, string>`

### `apply(array $creates, bool $execute): array`

Applies CREATE TABLE statements as dbDelta does; the statements run.

- `@param array<string, string> $creates @return array<string, string> what was (or would be) done, by table or table.column`

### `tables(): array`

Every table in the database.

- `@return list<string>`

### `columns(string $table): array`

A table's columns with their definitions.

- `@return array<string, array<string, mixed>> by column name`

### `indexNames(string $table): array`

A table's index names.

- `@return list<string> lowercase key names`

### `run(string $ddl): void`

Runs one DDL statement.

Internals: `definitions()` (private, line 80)


## EarlyFilters

`final class Minn\Runtime\EarlyFilters` · `public/minn/src/Minn/Runtime/EarlyFilters.php`

Filters that run before the runtime exists, over the hooks added that
early: WP-CLI's add_wp_hook writes them into $wp_filter as plain arrays
(tag => priority => id => function and accepted_args). The reference has
its hook API by then; the engine reads the same arrays directly.

Used by: `Minn\Front\Maintenance`

### static `apply(string $tag, mixed $value, mixed ...$args): mixed`

The value through every early callback on the tag, lowest priority first; as given when there are none.


## Hooks

`final class Minn\Runtime\Hooks` · `public/minn/src/Minn/Runtime/Hooks.php`

The hook registry plugin code registers into and the engine fires.
Semantics come from contracts/fixtures/api/hooks.json: callbacks run
by ascending priority then insertion order; a callback added at a
higher priority during a run takes part in that run, one added at the
current or a lower priority waits for the next; a callback removed
before its turn is skipped; the "all" hook sees every firing with the
hook name first and every argument regardless of its accepted count.

Used by: `Minn\Runtime\Runtime`


### `onNew(Closure $observer): void`

Called with each hook name the first time a callback registers under it.

### `storage(): array`

The live registry, for a hook object to share by reference.

### `actionCounters(): array`

How often each action, or each filter, has run, by hook.

### `filterCounters(): array`

How often each filter has run, by hook, by reference for the facade's global.

### `stackRef(): array`

The hooks running now, outermost first, by reference for the facade\'s globals.

### `currentPriority(string $hook): int|false`

The priority a running hook is at, or false when it is idle.

### `add(string $hook, callable|array|string $callback, string|int $priority = 10, int $accepted = 1): bool`

Adds a callback to a hook at a priority.

### `remove(string $hook, callable|array|string $callback, string|int $priority = 10): bool`

Removes a callback from a hook.

### `removeAll(string $hook, string|int|false $priority = false): bool`

Every callback of a hook, or only those at one priority. Always true, as observed.

### `has(string $hook, callable|array|string|false $callback = false): int|bool`

With a callback: its lowest priority, or false; without: whether anything is registered.

### `filter(string $hook, array $args): mixed`

Runs a filter and returns the value.

- `@param list<mixed> $args the value first`

### `filterWithout(string $hook, array $args, array $done): mixed`

Runs a filter without the callbacks a caller has already done the work
of, named function => priority: the engine renders post content through
its own pipeline, then runs the_content for everything else hooked
there. A callback a plugin removed is simply not there to skip.

- `@param list<mixed> $args the value first`
- `@param array<string, int> $done`

### `action(string $hook, array $args): void`

Runs an action. With no arguments its callbacks still get one, an
empty string, as do_action() gives them in the reference (a callback
that requires a parameter is not short of one).

- `@param list<mixed> $args`

### `actionRef(string $hook, array $args): void`

Runs an action with exactly the arguments given, none included
(do_action_ref_array).

- `@param list<mixed> $args`

### `actionRefArray(string $hook, array $args): void`

do_action_ref_array: the action's callbacks get the arguments, and an
'all' callback gets them as the one array they were passed in, as the
reference hands them on.

- `@param list<mixed> $args`

### `filterRefArray(string $hook, array $args): mixed`

apply_filters_ref_array: as filter(), with an 'all' callback handed
the arguments as one array.

- `@param list<mixed> $args`

### `actionsDone(string $hook): int`

How often an action has run.

### `filtersDone(string $hook): int`

How often a filter has run.

### `current(): string|false`

The hook running now, or false.

### `doing(?string $hook): bool`

Whether a hook, or any hook, is running.

### `registered(): array`

Every hook with callbacks, by name.

- `@return array<string, array<int, list<callable>>> a read-only view for diagnostics`

Internals: `run()` (private, line 252), `nextPriority()` (private, line 291), `fireAll()` (private, line 303), `id()` (private, line 317)


## Interactivity

`final class Minn\Runtime\Interactivity` · `public/minn/src/Minn/Runtime/Interactivity.php`

Server-side directive processing for the Interactivity API: the state and
config stores, and the pass that resolves data-wp-bind, data-wp-class,
data-wp-style, data-wp-text, and data-wp-each against state and context
before the markup leaves the server. Behaviour pinned by the interactivity
probe fixture.

- const `VOID` = `array (   0 => 'area',   1 => 'base',   2 => 'br',   3 => 'col',   4 => 'embed',   5 => 'hr',   6 => 'img',   7 => 'input',   8 => 'link',   9 => 'meta',   10 => 'source',   11 => 'track',   12 => 'wbr', )`
- const `RAW` = `array (   0 => 'script',   1 => 'style',   2 => 'textarea',   3 => 'title', )`
- const `TAG` = `'/<(\\/?)([a-zA-Z][^\\s\\/>]*)((?:\\s+[^\\s=\\/>]+(?:\\s*=\\s*(?:"[^"]*"|\'[^\']*\'|[^\\s"\'>]+))?)*)\\s*(\\/?)>/'`
- const `UNRESOLVED` = `'' . "\0" . 'unresolved'`
- const `ATTR` = `'/\\s+([^\\s=\\/>]+)(?:\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s"\'>]+))?/'`

Used by: `Minn\Runtime\Runtime`


### `state(?string $namespace, array $state = array ( )): array`

Reads or extends a namespace's state.

- `@return array<string, mixed>`

### `allState(): array`

Every namespace's state, for the client.

- `@return array<string, array<string, mixed>> every namespace's state, for the client`

### `allConfig(): array`

Every namespace's config, for the client.

- `@return array<string, array<string, mixed>>`

### `config(string $namespace, array $config = array ( )): array`

Reads or extends a namespace's config.

- `@return array<string, mixed>`

### `context(?string $namespace = NULL): array`

The current directive context of a namespace.

- `@return array<string, mixed>`

### `element(): ?array`

The element whose directives are being evaluated.

- `@return array<string, mixed>|null`

### `process(string $html): string`

HTML with its directives resolved on the server.

Internals: `namespace()` (private, line 133), `tokenize()` (private, line 144), `walk()` (private, line 203), `applyDirectives()` (private, line 265), `bind()` (private, line 323), `interactiveNamespace()` (private, line 349), `pushContext()` (private, line 362), `evaluate()` (private, line 379), `expandEach()` (private, line 429), `markChildren()` (private, line 463), `attributeMap()` (private, line 495), `relative()` (private, line 508), `escape()` (private, line 513), `camel()` (private, line 518)


## MainQuery

`final class Minn\Runtime\MainQuery` · `public/minn/src/Minn/Runtime/MainQuery.php`

The query variables the reference's main query would carry for a URL the
engine has resolved, so plugin code reading is_page(), get_queried_object(),
or get_search_query() during a front-end render sees the same page.

Used by: `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`

### static `vars(Minn\Front\Resolution $resolution): array`

The query vars a resolution amounts to.

- `@return array<string, mixed>`


## Meta

`final readonly class Minn\Runtime\Meta` · `public/minn/src/Minn/Runtime/Meta.php`

The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need.

- const `TABLES` = `array (   'post' =>    array (     0 => 'postmeta',     1 => 'post_id',     2 => 'meta_id',   ),   'user' =>    array (     0 => 'usermeta',     1 => 'user_id',     2 => 'umeta_id',   ),   'term' =>    array (     0 => 'termmeta',     1 => 'term_id',     2 => 'meta_id',   ),   'comment' =>    array (     0 => 'commentmeta',     1 => 'comment_id',     2 => 'meta_id',   ), )`

```php
__construct(Minn\Db $db)
```


### static `knows(string $type): bool`

Whether an object type has a meta table.

### static `clausesFromQueryVars(array $queryVars): array`

The meta clauses hidden in flat query vars (meta_key, meta_value, the
compare and type variants), merged ahead of an explicit meta_query,
the reference's precedence for WP_Meta_Query::parse_query_vars.

- `@param array<string, mixed> $queryVars`
- `@return list<mixed>`

### `all(string $type, int $objectId): array`

Every row of an object's meta, values as stored, grouped by key in id order. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `rowsOf(string $type, int $objectId): array`

Every row of an object's meta in id order, for removing them one by one. @return list<array{meta_id: int, meta_key: string, meta_value: string}>

- `@return list<array{meta_id: int, meta_key: string, meta_value: string}>`

### `byId(string $type, int $metaId): ?array`

One meta row by its id, under the table's own column names, values as stored; null when there is none. @return array<string, string>|null

- `@return array<string, string>|null`

### `columns(string $type): array`

The column holding the object's id, and the one holding the row's: post_id and meta_id, user_id and umeta_id. @return array{0: string, 1: string}

- `@return array{0: string, 1: string}`

### `rewrite(string $type, int $metaId, string $key, string $stored): void`

Sets one row's key and stored value.

### `add(string $type, int $objectId, string $key, string $stored): int`

Inserts a meta row and returns its id.

### `matching(string $type, int $objectId, string $key): array`

The rows of one key on one object, in id order. @return list<array{meta_id: int, meta_value: string}>

- `@return list<array{meta_id: int, meta_value: string}>`

### static `idsToUpdate(array $rows, string $stored, ?string $previous): array`

Which of a key's rows an update touches: none when no previous value was
named and the first row already holds the value, all of them when no
previous value was named, otherwise only the rows holding it.

- `@param list<array<string, mixed>> $rows`
- `@return list<int>`

### `updateRows(string $type, array $ids, string $stored): void`

Sets the value of the given meta rows.

### `find(string $type, ?int $objectId, string $key, ?string $stored): array`

The rows a delete would take: by key, for one object or all, optionally only a stored value. @return list<array{meta_id: int, object_id: int}>

- `@return list<array{meta_id: int, object_id: int}>`

### `deleteRows(string $type, array $ids): void`

Deletes the given meta rows.

- `@param list<int> $ids`

Internals: `spec()` (private, line 19)


## MetaClause

`final class Minn\Runtime\MetaClause` · `public/minn/src/Minn/Runtime/MetaClause.php`

The meta side of a post query: meta_key and its friends as one clause,
then an explicit meta_query, each clause an EXISTS on postmeta with the
reference's compare and type rules, as one WHERE fragment on p.ID.

Used by: `Minn\Runtime\PostQuery`

```php
__construct(Minn\Db $db)
```


### `where(array $q): ?array`

The WHERE fragment and its parameters for a query's meta conditions,
or null when the query has none.

- `@return array{string, list<mixed>}|null`

Internals: `metaClauses()` (private, line 45), `metaSql()` (private, line 79)


## MetaTypes

`final class Minn\Runtime\MetaTypes` · `public/minn/src/Minn/Runtime/MetaTypes.php`

Meta types a plugin brought, by the table it named on $wpdb as
"{$type}meta" (WooCommerce's order items keep theirs in
woocommerce_order_itemmeta). The reference reads any such table with
"{$type}_id" for the object and meta_id for the row; Meta does the same.

Used by: `Minn\Runtime\Meta`


### static `register(string $type, string $table): bool`

Registers a meta type by its full table name; refused unless both are plain identifiers.

### static `table(string $type): ?string`

The full table name of a registered meta type, or null.


## NavMenu

`final class Minn\Runtime\NavMenu` · `public/minn/src/Minn/Runtime/NavMenu.php`

Nav-menu item decoration for wp_nav_menu(): the reference's class tokens
(menu-item, the type and object tokens, menu-item-home) and the current
markers (current-menu-item with its page compat tokens, the parent and
ancestor chain). The facade's wp_nav_menu() fetches and sorts the items;
this marks them against the standing main query.


### static `build(object $args): string|false|null`

The whole menu for wp_nav_menu(): resolve, fetch, decorate, walk,
wrap, filter. Null when there is no menu (the caller's fallback
runs); false when the items filtered away to nothing.

### static `decorate(array $items): array`

Menu items with the classes and flags the reference adds for the current page.

- `@param list<object> $items`
- `@return list<object>`

Internals: `menuForArgs()` (private, line 52), `wrapId()` (private, line 70), `container()` (private, line 85), `singularContext()` (private, line 156), `markQueriedAncestry()` (private, line 199), `isCurrent()` (private, line 222), `markAncestors()` (private, line 258), `currentUrl()` (private, line 308)


## OEmbed

`final class Minn\Runtime\OEmbed` · `public/minn/src/Minn/Runtime/OEmbed.php`

oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes.

### static `providerFor(array $providers, string $url): ?string`

The endpoint of the provider whose mask matches a URL, or null.

- `@param array<string, array{0: string, 1: bool}> $providers mask => [endpoint with {format}, mask is a regex]`

### static `parseJson(string $body): ?array`

An oEmbed JSON body as an array, or null.

- `@return array<string, mixed>|null`

### static `parseXml(string $body): ?array`

An oEmbed XML body as an array, or null.

- `@return array<string, mixed>|null`

### static `html(array $data, string $url, Closure $escUrl, Closure $escAttr, Closure $escHtml): ?string`

The embed HTML for an oEmbed response, or null.

- `@param array<string, mixed> $data the oEmbed payload`
- `@param Closure(string): string $escUrl @param Closure(string): string $escAttr @param Closure(string): string $escHtml`

### static `stripNewlines(string $html): string`

Drops newlines from embed markup while leaving the inside of <pre> blocks untouched.


## ObjectCache

`final class Minn\Runtime\ObjectCache` · `public/minn/src/Minn/Runtime/ObjectCache.php`

The per-request object cache behind wp_cache_*: groups of keys, nothing persistent.

Used by: `Minn\Runtime\Runtime`


### `get(string $key, string $group, ?bool $found = NULL): mixed`

A cached value, or false.

### `set(string $key, mixed $value, string $group): bool`

Stores a value.

### `add(string $key, mixed $value, string $group): bool`

Stores a value only when the key is empty.

### `delete(string $key, string $group): bool`

Removes a key.

### `flush(): bool`

Empties the cache.

### `flushGroup(string $group): bool`

Empties one group.


## Options

`final class Minn\Runtime\Options` · `public/minn/src/Minn/Runtime/Options.php`

Options as plugin code sees them: PHP values, decoded from the stored
blob by the engine's own reader, cached for the request so a value written
and read again in one request keeps its PHP type (an int stays an int,
false stays false) exactly as the reference shows. A value holding an
object is kept as its stored text and decoded afresh on every read, as
the reference does: a plugin that changes the object it was handed and
saves it must find the stored copy still different, or its change is
never written.

- const `GUARDED` = `array (   0 => 'minn_runtime_symbols',   1 => 'minn_recovery_strikes',   2 => 'minn_cron_lock', )` — The options the engine keeps for itself: the symbol-gate cache a plugin
could otherwise rewrite to pass its own gate, the recovery strikes, the
cron lock, and the sign-in throttle rows. The facade refuses to write
them; the engine writes them through this class directly.
- const `GUARDED_PREFIX` = `'minn_login_throttle_'`
- const `AUTOLOAD_VALUES` = `array (   0 => 'yes',   1 => 'on',   2 => 'auto-on',   3 => 'auto', )` — The autoload column values that load an option with every request, in the reference's order.

Used by: `Minn\Cli\OptionCommand`, `Minn\Runtime\AjaxController`, `Minn\Runtime\Runtime`, `Minn\Runtime\Symbols`

```php
__construct(Minn\Db $db)
```


### static `guarded(string $name): bool`

Whether plugin code is refused a write to this option.

### `autoloaded(): array`

Every autoloaded option as stored. @return array<string, string>

- `@return array<string, string>`

### `get(string $name): mixed`

An option's value, decoded, or null when unset.

### `exists(string $name): bool`

Whether an option exists.

### `add(string $name, mixed $value, string $autoload = 'auto'): bool`

Adds an option only when it is unset.

### `upsert(string $name, string $stored, string $autoload): bool`

Writes an option row whether or not one exists, as the reference does
for every add: two requests that both find a transient missing and
both add it must not fail on the unique name, so the second write lands
over the first. True when the row was inserted or changed, false when
the same value was already there.

### `update(string $name, mixed $value, ?string $autoload = NULL): bool`

False when the value is unchanged, as the reference reports.

### `markAutoload(string $name): bool`

Flips the autoload column; false when the option is missing or already so.

### `unmarkAutoload(string $name): bool`

Stops an option loading on every request; false when it already did not, or is unset.

### `delete(string $name): bool`

Removes an option.

### `expiredTransientNames(int $now, string $prefix = '_transient_timeout_'): array`

The names of transients whose expiry has passed. The timeout row is the
one that knows, so the sweep reads those and hands back the bare names.

- `@return list<string>`

### `forget(string $name): void`

Drops an option from the cache.

### static `toStorage(mixed $value): string`

What the reference stores: arrays and objects serialized, scalars as their string form.

### static `fromStorage(string $raw): mixed`

A stored option value decoded the way the reference reads it.

Internals: `remember()` (private, line 139), `holdsObject()` (private, line 152), `switchAutoload()` (private, line 170)


## PackageDownload

`final class Minn\Runtime\PackageDownload` · `public/minn/src/Minn/Runtime/PackageDownload.php`

The publisher's say over its own download. Before fetching an update
package, the reference asks `upgrader_pre_download`: a plugin that hosts
itself answers there, and answering is how it proves the archive is the
one it published. Three answers, which are the reference's:

a file path  the publisher fetched and verified the package itself
a refusal    the publisher rejects this download, with its reason
nothing      nobody vouched for it

The engine asks the same question, and installs a package from outside
the wordpress.org directory only on the first answer. That is stricter
than the reference, which downloads whatever the offer names; here an
archive nobody vouched for is never unpacked over a plugin folder.

- const `HOOK` = `'upgrader_pre_download'`

Used by: `Minn\Ops\Updates`

### static `verified(string $package, array $hookExtra): Minn\Runtime\Refusal|string|null`

The publisher's verified copy of the package, its refusal, or null
when nothing answered.

- `@param array<string, string> $hookExtra what is being updated, as the reference passes it ('plugin' or 'theme')`

Internals: `refusal()` (private, line 45)


## PageMenu

`final class Minn\Runtime\PageMenu` · `public/minn/src/Minn/Runtime/PageMenu.php`

The page-list menu a classic theme falls back to when no menu is
assigned to a location. Storefront's header is one of these, so its
markup is what the theme's CSS binds to.

Shapes captured from the reference: the optional home item carries no
class (the attribute is written but left empty), a page item carries
`page_item page-item-{id}` plus `current_page_item` for the page being
viewed, and the whole list is wrapped in the caller's before/after.

### static `items(array $pages, ?array $home, string $linkBefore = '', string $linkAfter = ''): string`

The page menu's list items.

- `@param list<array{id: int, title: string, url: string, current: bool}> $pages`
- `@param array{label: string, url: string, current: bool}|null $home`

### static `home(mixed $showHome, string $url, bool $current, string $defaultLabel = 'Home'): ?array`

The home item a `show_home` argument asks for: true (or 1) means the
default label, a string is the label itself, anything falsy means no
item at all.

- `@return array{label: string, url: string, current: bool}|null`


## Pages

`final class Minn\Runtime\Pages` · `public/minn/src/Minn/Runtime/Pages.php`

get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result.

- const `DEFAULTS` = `array (   'child_of' => 0,   'sort_order' => 'ASC',   'sort_column' => 'post_title',   'hierarchical' => 1,   'exclude' =>    array (   ),   'include' =>    array (   ),   'meta_key' => '',   'meta_value' => '',   'authors' => '',   'parent' => -1,   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => 0,   'post_type' => 'page',   'post_status' => 'publish', )`
- const `COLUMNS` = `array (   'post_title' => 'title',   'menu_order' => 'menu_order',   'post_date' => 'date',   'post_modified' => 'modified',   'ID' => 'ID',   'post_author' => 'author',   'post_name' => 'name',   'post_parent' => 'parent', )`

### static `queryArgs(array $parsed, array $include, array $exclude): array`

The post query arguments the get_pages() arguments amount to. @param list<int> $include @param list<int> $exclude

- `@param list<int> $include @param list<int> $exclude`

### static `arrange(array $pages, array $parsed): array`

Parents first, each followed by its own subtree, then the child_of,
exclude_tree, and offset/number cuts, over objects with ID and post_parent.

- `@param list<object> $pages`
- `@return list<object>`

### static `descendants(array $pages, int $parent): array`

Every page under one ancestor, in list order. @param list<object> $pages @return list<object>

- `@param list<object> $pages @return list<object>`

Internals: `treeOrder()` (private, line 74)


## Patterns

`final class Minn\Runtime\Patterns` · `public/minn/src/Minn/Runtime/Patterns.php`

The block pattern, pattern category, and block style registries as data.
Entries registered after init are remembered separately, because the
editor asks for those on their own.


### `registerPattern(mixed $name, mixed $properties): ?Minn\Runtime\Refusal`

Registers a block pattern, or the refusal.

### `unregisterPattern(string $name): bool`

Forgets a pattern.

### `pattern(string $name): ?array`

One pattern, or null.

### `patterns(): array`

Every pattern.

- `@return list<array<string, mixed>>`

### `patternsAfterInit(): array`

The patterns registered after init, which the editor lists separately.

### `registerCategory(mixed $name, mixed $properties): ?Minn\Runtime\Refusal`

Registers a pattern category, or the refusal.

### `unregisterCategory(string $name): bool`

Forgets a category.

### `category(string $name): ?array`

One category, or null.

### `categories(): array`

Every category.

- `@return list<array<string, mixed>>`

### `categoriesAfterInit(): array`

The categories registered after init.

### `registerStyle(mixed $blocks, mixed $properties): ?Minn\Runtime\Refusal`

Registers a block style, or the refusal.

- `@param string|list<string> $blocks`

### `unregisterStyle(string $block, string $style): bool`

Forgets a block style.

### `style(string $block, string $style): ?array`

One block style, or null.

### `styles(?string $block = NULL): array`

Every block style, or one block's.

- `@return array<string, array<string, array<string, mixed>>>`


## PlaceholderTrace

`final class Minn\Runtime\PlaceholderTrace` · `public/minn/src/Minn/Runtime/PlaceholderTrace.php`

Records every call into a generated placeholder while a site opts in by
having wp-content/minn-placeholder-trace.log on disk: one line per call
with the symbol, the plugin file that called it, and the request. The
log says which placeholders deserve behaviour; an absent file costs one
stat per request.


### static `hit(string $symbol): void`

Logs a placeholder symbol being called, when the trace file exists.


## Placeholders

`final class Minn\Runtime\Placeholders` · `public/minn/src/Minn/Runtime/Placeholders.php`

The printf placeholders plugin code hands wpdb::prepare, filled the way
the reference fills them: a bare %s is escaped and quoted; a numbered
(%1$s), padded (%5s) or precise (%.2f) one is formatted and escaped but
never quoted, so a plugin can name a table with it; %d and %f cast; %i
backticks an identifier; %% is a literal. Numbered placeholders address
the argument list directly while unnumbered ones count from the first
on their own, as vsprintf does. A placeholder with no argument behind it
empties the whole query, as the reference does.

- const `SPEC` = `'/%(?:(\\d+)\\$)?([-+ 0]*)(\\d*)(?:\\.(\\d+))?([sdfFi%])/'`

### static `fill(string $query, array $args, callable $escape): ?string`

Fills a query's placeholders from the arguments; null when one has no argument.

- `@param list<mixed> $args`
- `@param callable(string): string $escape the connection's string escape`

Internals: `quoted()` (private, line 56), `text()` (private, line 61)


## PluginUpdates

`final class Minn\Runtime\PluginUpdates` · `public/minn/src/Minn/Runtime/PluginUpdates.php`

The update offers the site's own plugins publish. A plugin that hosts
itself, or sells itself, is not in the wordpress.org directory and never
appears in the directory's answer: it publishes its offer by filtering
the update transient WordPress reads, which is the only place anyone
learns of it. The engine asks the same question of the runtime, in the
shape the reference asks it, so a self-hosted plugin is offered its
update here exactly as it would be on WordPress.

The transient carries `checked` (every installed plugin and its version),
and a plugin's updater returns early when that is empty, so the installed
versions are what make the question answerable.

- const `HOOK` = `'site_transient_update_plugins'`

Used by: `Minn\Ops\Updates`

### static `supplied(array $installed): array`

What the site's plugins offer for themselves, empty when no runtime
is booted or nothing filters the transient.

- `@param array<string, string> $installed plugin file => installed version`
- `@return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}`

### static `fromFiltered(mixed $filtered, array $installed): array`

The filtered transient as the state's two buckets: offers under
`plugins`, plugins that answered "current" under `no_update`, both
as arrays, and only for files this site actually has.

- `@param array<string, string> $installed`
- `@return array{plugins: array<string, array<string, mixed>>, no_update: array<string, array<string, mixed>>}`

Internals: `bucket()` (private, line 68)


## Plugins

`final class Minn\Runtime\Plugins` · `public/minn/src/Minn/Runtime/Plugins.php`

Loads the site's plugins into the runtime the way the reference does:
mu-plugins first, then active_plugins in stored order, each file
included once. A plugin loads only when the static symbol read finds
nothing the runtime lacks; otherwise it is reported and skipped so the
site keeps rendering. The lifecycle actions fire between the phases.

- const `MINN_ADMIN` = `'minn-admin/minn-admin.php'` — Minn Admin loads as code for its adapters (surfaces, licenses,
connectors, custom CSS, the plugin routes the engine has no
controller for), but the engine owns the shell, the front bar, the
sign-in flow, and maintenance: those hooks come off right after the
include so the two never print twice or disagree.
- const `MINN_ADMIN_HOOKS` = `array (   0 =>    array (     0 => 'template_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maybe_render_app',     ),     2 => 0,   ),   1 =>    array (     0 => 'template_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maybe_maintenance_mode',     ),     2 => 1,   ),   2 =>    array (     0 => 'rest_authentication_errors',     1 =>      array (       0 => 'Minn_Admin',       1 => 'maintenance_rest',     ),     2 => 20,   ),   3 =>    array (     0 => 'login_redirect',     1 =>      array (       0 => 'Minn_Admin',       1 => 'login_redirect',     ),     2 => 20,   ),   4 =>    array (     0 => 'show_admin_bar',     1 =>      array (       0 => 'Minn_Admin',       1 => 'enforce_toolbar_policy',     ),     2 => 99,   ),   5 =>    array (     0 => 'show_admin_bar',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'suppress_core_bar',     ),     2 => 100,   ),   6 =>    array (     0 => 'wp_enqueue_scripts',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'enqueue',     ),     2 => 10,   ),   7 =>    array (     0 => 'wp_footer',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'render',     ),     2 => 10,   ),   8 =>    array (     0 => 'body_class',     1 =>      array (       0 => 'Minn_Admin_Bar',       1 => 'body_class',     ),     2 => 10,   ), )`

Used by: `Minn\Cli\Runtime`, `Minn\Engine`, `Minn\Extension\Loader`, `Minn\Theme\ClassicRenderer`


### static `load(Minn\Runtime\Runtime $runtime): void`

Loads the active plugins as code and fires the boot hooks. Recovery
is armed for exactly this window: a failure here is one every
visitor would hit, so it may be recorded against its extension.

### static `loaded(): array`

The plugins that loaded.

- `@return list<string>`

### static `skipped(): array`

The plugins the symbol gate refused, with what they lacked.

- `@return array<string, array<string, mixed>> plugin file => why it did not load`

### static `isLoaded(string $plugin): bool`

True when the named plugin file is running as code this request.

Internals: `boot()` (private, line 67), `loadThemeFunctions()` (private, line 123), `rememberThemeDomain()` (private, line 146), `includeFile()` (private, line 180), `registerRealpath()` (private, line 211), `isolatedInclude()` (private, line 226)


## PostEvents

`final readonly class Minn\Runtime\PostEvents` · `public/minn/src/Minn/Runtime/PostEvents.php`

What the reference's REST controllers tell plugins about a post they
write, for the engine's own controllers: the same actions in the same
order with the same arguments, fired through the facade's lifecycle
functions so the facade's own writes say the same (contracts/runtime.md
"Writes tell plugins"). Without a booted runtime every method does
nothing, so a write with no plugins loaded is exactly what it was.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\PostsWriteController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `beforeSave(array $columns, ?Minn\Content\PostRecord $existing): void`

Before the row is written: pre_post_insert for a new post, pre_post_update for one that exists. @param array<string, mixed> $columns

- `@param array<string, mixed> $columns`

### `saved(int $id, ?Minn\Content\PostRecord $before): void`

Once the row is written: the caches, the status transition, the edit actions of an update, the save actions.

### `ensureCategory(Minn\Content\PostWriter $writer, int $id): void`

A post keeps a category through every save, the default when it has
none: through wp_set_post_categories with plugins loaded (which tells
them, set_object_terms included), quietly without.

### `applyTerms(Minn\Content\PostWriter $writer, int $id, array $body): void`

The terms a REST body names, set through wp_set_object_terms with plugins loaded, quietly without. @param array<string, mixed> $body

- `@param array<string, mixed> $body`

### `attachedFile(Minn\Media\Writer $library, int $id, string $relative): void`

The file a new attachment holds: through add_post_meta with plugins loaded, written directly without.

### `attachmentAdded(int $id): void`

add_attachment, once a new attachment's row and file are in.

### `attachmentEdited(int $id, Minn\Content\PostRecord $before): void`

edit_attachment and attachment_updated, after an attachment's row changed.

### `attachmentMetadata(Minn\Media\Writer $library, int $id, array $metadata): void`

A new attachment's metadata: with plugins loaded it passes through
wp_generate_attachment_metadata (where an image optimiser works) and
is stored by wp_update_attachment_metadata; without, as built.

- `@param array<string, mixed> $metadata`

### `altText(Minn\Media\Writer $library, int $id, string $alt): void`

An attachment's alt text: through update_post_meta with plugins loaded, written directly without.

### `restInserted(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_insert_{type}, before the request's own terms and fields are applied; a post with no $before is a new one.

### `restAfterInsert(int $id, Minn\Http\Request $request, ?Minn\Content\PostRecord $before): void`

rest_after_insert_{type}, once the request's terms and fields are in; a post with no $before is a new one.

### `afterInsert(int $id, ?Minn\Content\PostRecord $before): void`

wp_after_insert_post, last; the revision of an update is saved from it.

### `restDeleted(Minn\Content\PostRecord $post, array $data, Minn\Http\Request $request): void`

rest_delete_{type}, after a trash or a delete, with the post as it was answered and the response. @param array<string, mixed> $data

- `@param array<string, mixed> $data`

Internals: `rest()` (private, line 150), `wpPost()` (private, line 162)


## PostInsert

`final readonly class Minn\Runtime\PostInsert` · `public/minn/src/Minn/Runtime/PostInsert.php`

The decisions behind wp_insert_post: which columns a postarr fills, when
the post counts as empty, the status a publish request lands in, the dates
and the slug, the categories a new post gets. The rows are written by
Content\PostWriter; the facade fires the hooks around each step.

- const `COLUMNS` = `array (   0 => 'post_author',   1 => 'post_date',   2 => 'post_date_gmt',   3 => 'post_content',   4 => 'post_title',   5 => 'post_excerpt',   6 => 'post_status',   7 => 'comment_status',   8 => 'ping_status',   9 => 'post_password',   10 => 'post_name',   11 => 'to_ping',   12 => 'pinged',   13 => 'post_modified',   14 => 'post_modified_gmt',   15 => 'post_content_filtered',   16 => 'post_parent',   17 => 'guid',   18 => 'menu_order',   19 => 'post_type',   20 => 'post_mime_type', )`

```php
__construct(Minn\Content\PostWriter $writer, int $userId, Closure $option, Closure $supports, Closure $canPublish, Closure $gmtFromDate, Closure $now)
```
- `@param Closure(string): mixed $option an option read`
- `@param Closure(string, string): bool $supports whether a post type supports a feature`
- `@param Closure(string): bool $canPublish whether the current user may publish the type`
- `@param Closure(string): string $gmtFromDate the site-local date as GMT`
- `@param Closure(bool): string $now the current local (or GMT) MySQL time`


### `columns(array $postarr, ?array $existing): array`

The columns the posts table takes, filled from a postarr, with a new post's defaults. @param array<string, mixed>|null $existing @return array<string, string>

- `@param array<string, mixed>|null $existing @return array<string, string>`

### `isEmpty(array $columns, ?array $existing): bool`

A post with nothing in title, content, and excerpt is empty when its type supports all three. @param array<string, mixed>|null $existing

- `@param array<string, mixed>|null $existing`

### `resolve(array $columns, ?array $existing): array`

The columns as they will be written: the status a request lands in
(attachments inherit, unprivileged publishes pend, a future date
schedules), the dates, the slug, and the integer columns.

- `@param array<string, mixed>|null $existing`
- `@return array<string, string>`

### `persist(array $columns, ?int $existingId, Closure $guid): int`

Writes the resolved columns; a new post without a guid gets the ?p= form. @return int the post id

### static `categories(array $postarr, string $type, string $status, bool $update, array $taxonomies, int $default): ?array`

The categories a saved post should carry: the ones given, or the
default for a new post of a type that has categories; null when
nothing should change.

- `@param list<string> $taxonomies the type's taxonomies`
- `@return list<int>|null`

Internals: `type()` (private, line 178)


## PostLookup

`final readonly class Minn\Runtime\PostLookup` · `public/minn/src/Minn/Runtime/PostLookup.php`

The post reads plugin code asks for by shape: a page by title, revisions, counts.

```php
__construct(Minn\Db $db)
```


### `idByTitle(string $title, array $types): ?int`

The id of a post with this title among the types, or null.

- `@param list<string> $types`

### `revisionsOf(int $postId): array`

Revision rows newest first. @return list<array<string, mixed>>

- `@return list<array<string, mixed>>`

### `countByStatus(string $type): array`

How many posts of a type there are per status.

- `@return array<string, int> status => count`

### `countAttachments(): array`

How many attachments there are per mime type.

- `@return array<string, int> mime type => count, plus 'trash'`

### `countByAuthor(int $userId, array $types, array $statuses): int`

How many posts an author has among the types and statuses.

- `@param list<string> $types @param list<string> $statuses`

### `idsByAuthor(int $userId): array`

Every post id of an author.

- `@return list<int>`

### `attachmentIdByFile(string $path): ?int`

The attachment whose stored file path is the given one.


## PostQuery

`final class Minn\Runtime\PostQuery` · `public/minn/src/Minn/Runtime/PostQuery.php`

The query WP_Query runs: its variables become one SELECT over the posts
table with the joins the taxonomy, meta, and author conditions need.
Shapes and defaults follow contracts/fixtures/api/content.json.

Used by: `Minn\Runtime\Runtime`

```php
__construct(Minn\Db $db, Minn\Runtime\Registry $registry)
```


### `run(array $q, bool $isHome): array`

Runs a WP_Query-shaped args array and returns its rows and totals.

- `@param array<string, mixed> $q`
- `@return array{rows: list<array>, found: int, sticky: list<array>}`

Internals: `perPage()` (private, line 88), `types()` (private, line 100), `statuses()` (private, line 124), `singular()` (private, line 152), `authors()` (private, line 186), `parents()` (private, line 218), `ids()` (private, line 236), `search()` (private, line 269), `dates()` (private, line 284), `order()` (private, line 342)


## PostSave

`final class Minn\Runtime\PostSave` · `public/minn/src/Minn/Runtime/PostSave.php`

A REST save's columns through the filters the reference's save runs
before it writes, in its order (probe post-insert-filters,
contracts/runtime.md "Saves plugins can change"): rest_pre_insert_{type}
over the prepared post; every column through its db context
(pre_post_* and *_save_pre, where kses and the custom-CSS strip sit) on
slashed text; wp_insert_post_empty_content, which refuses a post with no
content, title or excerpt; wp_insert_post_parent; for a post that is live,
the slug, made again from the filtered title when the request gave none,
through pre_wp_unique_post_slug, the bad-slug check and
wp_unique_post_slug; and wp_insert_post_data over the row. What a filter
changes is written. Without plugins loaded the columns are as given.

- const `DATA` = `array (   0 => 'post_author',   1 => 'post_date',   2 => 'post_date_gmt',   3 => 'post_content',   4 => 'post_content_filtered',   5 => 'post_title',   6 => 'post_excerpt',   7 => 'post_status',   8 => 'post_type',   9 => 'comment_status',   10 => 'ping_status',   11 => 'post_password',   12 => 'post_name',   13 => 'to_ping',   14 => 'pinged',   15 => 'post_modified',   16 => 'post_modified_gmt',   17 => 'post_parent',   18 => 'menu_order',   19 => 'post_mime_type',   20 => 'guid', )` — The row wp_insert_post_data is handed, in its keys.
- const `FIELDS` = `array (   'title' => 'post_title',   'content' => 'post_content',   'excerpt' => 'post_excerpt',   'status' => 'post_status',   'date' => 'post_date',   'date_gmt' => 'post_date_gmt',   'slug' => 'post_name',   'password' => 'post_password',   'author' => 'post_author',   'parent' => 'post_parent',   'menu_order' => 'menu_order',   'comment_status' => 'comment_status',   'ping_status' => 'ping_status', )` — A REST field and the column it fills in the prepared post.
- const `UNSLUGGED` = `array (   0 => 'draft',   1 => 'pending',   2 => 'auto-draft', )`
- const `PARENT` = `'post_parent'`
- const `NEW_POSTARR` = `array (   0 => 'ID',   1 => 'post_author',   2 => 'post_date',   3 => 'post_date_gmt',   4 => 'post_content',   5 => 'post_content_filtered',   6 => 'post_title',   7 => 'post_excerpt',   8 => 'post_status',   9 => 'post_type',   10 => 'comment_status',   11 => 'ping_status',   12 => 'post_password',   13 => 'to_ping',   14 => 'pinged',   15 => 'post_parent',   16 => 'menu_order',   17 => 'guid',   18 => 'import_id', )` — The new post array wp_insert_post_parent is handed beside the whole one.
- const `DEFAULTS` = `array (   'post_author' => 0,   'post_content' => '',   'post_content_filtered' => '',   'post_title' => '',   'post_excerpt' => '',   'post_status' => 'draft',   'post_type' => 'post',   'comment_status' => '',   'ping_status' => '',   'post_password' => '',   'to_ping' => '',   'pinged' => '',   'post_parent' => 0,   'menu_order' => 0,   'guid' => '',   'import_id' => 0,   'context' => '',   'post_date' => '',   'post_date_gmt' => '', )` — wp_insert_post's defaults, in its order (the order its filters run in).

Used by: `Minn\Rest\PostsWriteController`

### static `filter(array $columns, ?Minn\Content\PostRecord $before, array $body, Minn\Http\Request $request, string $type, Closure $unique): array`

The columns to write after the filters.

- `@param array<string, mixed> $columns what the engine settled: a new post's whole row, or an update's changes`
- `@param array<string, mixed> $body the request's fields`
- `@param Closure(string $desired, int $excludeId): string $unique the engine's unique slug for a desired one`
- `@return array<string, mixed>`

### static `sanitized(array $postarr): array`

wp_insert_post's array with its defaults filled in (ID 0 for a new
post), through sanitize_post's db context: slashed in, slashed out.

- `@param array<string, mixed> $postarr`
- `@return array<string, mixed>`

### static `refusesEmpty(array $sanitized, string $type): bool`

wp_insert_post_empty_content over whether a type with an editor, a title and an excerpt has none of them. @param array<string, mixed> $sanitized

- `@param array<string, mixed> $sanitized`

### static `parent(array $sanitized, int $id): int`

The parent through wp_insert_post_parent (whose default refuses a loop). @param array<string, mixed> $sanitized

- `@param array<string, mixed> $sanitized`

### static `slugFilters(string $slug, int $id, string $status, string $type, int $parent, Closure $unique): string`

A live post's slug through the reference's three filters:
pre_wp_unique_post_slug may settle it, a slug the bad-slug filter
names takes the next free number, and wp_unique_post_slug has the
last word.

- `@param Closure(string, int): string $unique`

### static `data(array $row, array $sanitized, array $unsanitized, int $postId): array`

The row through wp_insert_post_data, handed slashed as the reference
hands it and unslashed back.

- `@param array<string, mixed> $row the row, unslashed`
- `@param array<string, mixed> $sanitized`
- `@param array<string, mixed> $unsanitized`
- `@return array<string, mixed>`

Internals: `prepared()` (private, line 159), `changed()` (private, line 194)


## QueriedObject

`final readonly class Minn\Runtime\QueriedObject` · `public/minn/src/Minn/Runtime/QueriedObject.php`

Which object a query is "about", read from its flags and variables: a term
(by id or slug), a post type, the posts page, the current post, or an
author. The caller materialises the record; this only decides where to look.

- readonly `string $kind`
- readonly `string $field`
- readonly `string|int $value`
- readonly `string $taxonomy`

### static `locate(array $vars, array $flags, Minn\Runtime\Registry $registry, callable $option): self`

The queried object the query vars and flags point at.

- `@param array<string, mixed> $vars`
- `@param array<string, bool> $flags the query's is_* flags`
- `@param callable(string): mixed $option a filtered option read`

Internals: `taxonomyTerm()` (private, line 62)


## QueryFlags

`final readonly class Minn\Runtime\QueryFlags` · `public/minn/src/Minn/Runtime/QueryFlags.php`

The conditional flags a set of query variables implies (is_single, is_archive,
is_home, ...), derived the way the reference's parse step derives them, plus
the variables after the integer casts that step applies.

- const `INTEGER_VARS` = `array (   0 => 'p',   1 => 'page_id',   2 => 'attachment_id',   3 => 'year',   4 => 'monthnum',   5 => 'day',   6 => 'w',   7 => 'paged', )`

Used by: `Minn\Runtime\QueriedObject`

- readonly `array $vars`
- readonly `array $flags`

### static `derive(array $vars, Minn\Runtime\Registry $registry, callable $option): self`

The is_* flags the query vars amount to.

- `@param array<string, mixed> $vars the filled query variables`
- `@param callable(string): mixed $option a filtered option read`

### static `customTaxonomyVar(array $vars, Minn\Runtime\Registry $registry): ?array`

The first registered taxonomy (other than the two built-in ones) whose
query variable carries a value, as [taxonomy name, query var].

- `@return array{0: string, 1: string}|null`

Internals: `archiveFlags()` (private, line 73)


## Recovery

`final readonly class Minn\Runtime\Recovery` · `public/minn/src/Minn/Runtime/Recovery.php`

Recovery from a fatal in someone else's code. When a plugin or theme
kills the boot of a request, the file it died in names it; a second
such failure inside ten minutes records it as paused, and the next
request loads without it: the site comes back on its own instead of
staying down until a human reads the log. One failure is only noted:
a pause is a site-wide decision, and a single request can be a
crafted one. Failures after boot (a route, a template) never pause
anything; see Failure::armRecovery().

The paused list is stored where WordPress stores it, in the same shape
(`paused_plugins` keyed by plugin file, `paused_themes` by stylesheet,
each holding type, file, line and message), so a site that ejects back
to WordPress finds the pause it left with. The strikes live in the
engine's own `minn_recovery_strikes` option.

- const `PLUGINS_OPTION` = `'paused_plugins'`
- const `THEMES_OPTION` = `'paused_themes'`
- const `STRIKES_OPTION` = `'minn_recovery_strikes'`
- const `WINDOW` = `600` — Seconds inside which a second boot failure pauses the extension.

Used by: `Minn\Cli\MinnCommand`, `Minn\Engine`, `Minn\Runtime\Plugins`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```


### `blame(string $file, array $realpaths = array ( ), ?array $active = NULL): ?array`

The extension a file belongs to: a plugin as the active `folder/file.php`
whose folder holds the file (or the bare file of a single-file plugin),
a theme as its slug. The name is the one the loader checks against the
paused list, so a fatal deep inside a plugin's subfolders pauses the
plugin and not a folder nothing is called by. Anything outside the
plugin and theme folders belongs to nobody, and is never paused: a
fatal in the engine or in core is not a plugin's fault and pausing
something would not fix it. A symlinked plugin reports its real
location; the realpath map (real folder to the folder under plugins/)
brings it home first.

- `@param array<string, string> $realpaths`
- `@param list<string>|null $active the active plugin files; read from the site when omitted`
- `@return array{kind: 'plugin'|'theme', name: string}|null`

### `pause(array $blamed, array $error): bool`

Records an extension as paused. Returns false when it was already
paused, so a caller can tell a fresh failure from a repeat and only
notify once.

- `@param array{kind: string, name: string} $blamed`
- `@param array{type: int, file: string, line: int, message: string} $error`

### `strike(array $blamed): bool`

Notes a boot failure against an extension. True when it is the
second inside the window, which is when the caller pauses it; the
first is only remembered. Strikes older than the window are dropped
as they are read, so the option never grows.

- `@param array{kind: string, name: string} $blamed`

### `pausedPlugins(): array`

The plugins recovery mode has paused.

- `@return array<string, array<string, mixed>>`

### `pausedThemes(): array`

The themes recovery mode has paused.

- `@return array<string, array<string, mixed>>`

### `resume(string $kind, string $name): bool`

Lets an extension load again. Returns false when it was not paused.

### `resumeAll(): int`

Lets everything load again. Returns how many were released.

Internals: `pluginFile()` (private, line 90), `activePlugins()` (private, line 104), `strikes()` (private, line 161), `forgetStrikes()` (private, line 168), `read()` (private, line 223), `write()` (private, line 231)


## Refusal

`final readonly class Minn\Runtime\Refusal` · `public/minn/src/Minn/Runtime/Refusal.php`

A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error.

Used by: `Minn\Blocks\BlockName`, `Minn\Content\Menus`, `Minn\Ops\Updates`, `Minn\Rest\ArgCheck`, `Minn\Rest\MenusController`, `Minn\Rest\ParamCheck`, `Minn\Rest\RouteMatch`, `Minn\Rest\Schema`, `Minn\Runtime\Connectors`, `Minn\Runtime\PackageDownload`, `Minn\Runtime\Patterns`, `Minn\Runtime\TermWriter`, `Minn\Runtime\UserInsert`

```php
__construct(string $code, string $message, mixed $data = NULL)
```

- readonly `string $code`
- readonly `string $message`
- readonly `mixed $data`


## Registry

`final class Minn\Runtime\Registry` · `public/minn/src/Minn/Runtime/Registry.php`

Post types, taxonomies, and statuses as plugin code registers and reads
them. The built-in set is data/registry.json, captured from the
reference; registrations derive their defaults the way the content
probe observed (contracts/fixtures/api/content.json).

Used by: `Minn\Front\Permalinks`, `Minn\Rest\AdditionalFields`, `Minn\Runtime\PostQuery`, `Minn\Runtime\QueriedObject`, `Minn\Runtime\QueryFlags`, `Minn\Runtime\Runtime`, `Minn\Runtime\TaxonomyClause`

```php
__construct(string $engineDir)
```

- readonly `array $queryVars`
- readonly `array $publicQueryVars`

### `postTypes(): array`

Every post type.

- `@return array<string, array<string, mixed>>`

### `postType(string $name): ?array`

One post type, or null.

### `taxonomies(): array`

Every taxonomy.

- `@return array<string, array<string, mixed>>`

### `taxonomy(string $name): ?array`

One taxonomy, or null.

### `statuses(): array`

Every post status.

- `@return array<string, array<string, mixed>>`

### `status(string $name): ?array`

One post status, or null.

### `registerPostType(string $name, array $args): array`

Registers a post type with the reference's defaults filled in.

- `@param array<string, mixed> $args`

### `unregisterPostType(string $name): bool`

Forgets a non-builtin post type.

### `addSupport(string $type, string $feature, array $args): void`

Support declared before the type registers waits aside (the reference
keeps features apart from the type objects, so declaring one does not
make the type exist) and merges in when register_post_type arrives.

### `removeSupport(string $type, string $feature): void`

Removes a feature from a post type.

### `supports(string $type): array`

The features a post type supports.

- `@return array<string, mixed> the features a type supports, registered or declared ahead`

### `registerTaxonomy(string $name, array $objectTypes, array $args): array`

Registers a taxonomy with the reference's defaults filled in.

- `@param list<string> $objectTypes @param array<string, mixed> $args`

### `unregisterTaxonomy(string $name): bool`

Forgets a non-builtin taxonomy.

### `addObjectType(string $taxonomy, string $type): bool`

Attaches a taxonomy to a post type.

### `removeObjectType(string $taxonomy, string $type): bool`

Detaches a taxonomy from a post type.

### `registerStatus(string $name, array $args): array`

Registers a post status.

- `@param array<string, mixed> $args`

Internals: `supportsFrom()` (private, line 177), `capabilities()` (private, line 191)


## Runtime

`final class Minn\Runtime\Runtime` · `public/minn/src/Minn/Runtime/Runtime.php`

The WordPress runtime the engine offers plugin code: the procedural
facade under minn/wp-api/ plus the services it delegates to. One per
request; the facade reaches it through these statics.

- const `CONTENT_DONE` = `array (   'apply_block_hooks_to_content_from_post_object' => 8,   'do_blocks' => 9,   'wptexturize' => 10,   'wpautop' => 10,   'shortcode_unautop' => 10,   'prepend_attachment' => 10,   'do_shortcode' => 11,   'wp_filter_content_tags' => 12, )` — The the_content defaults the engine's own rendering has already done:
blocks, texturize, paragraphs, shortcodes, block hooks, and the image
attributes. What it has not (smilies, the capital P, insecure home
addresses) runs with the plugins' own callbacks.

Used by: `Minn\Admin\BootPayload`, `Minn\Auth\Authenticator`, `Minn\Blocks\Dynamic\Theme\Comments`, `Minn\Blocks\Dynamic\Theme\Navigation`, `Minn\Blocks\Dynamic\Theme\PostBlocks`, `Minn\Blocks\Dynamic\Theme\QueryBlocks`, `Minn\Blocks\ImageTags`, `Minn\Blocks\RenderState`, `Minn\Cli\Runtime`, `Minn\Content\Blocks`, `Minn\Content\Reader`, `Minn\Content\Site`, `Minn\Content\Terms`, `Minn\Cron\Cron`, `Minn\Db`, `Minn\Engine`, `Minn\Extension\Extensions`, `Minn\Front\CommentPostController`, `Minn\Front\Feeds`, `Minn\Front\Permalinks`, `Minn\Front\PluginRules`, `Minn\Front\Resolver`, `Minn\Login\LoginHooks`, `Minn\Mail\Mailer`, `Minn\Media\Images`, `Minn\Rest\AbilitiesController`, `Minn\Rest\Api`, `Minn\Rest\Caller`, `Minn\Rest\RuntimeEnvelope`, `Minn\Rest\RuntimePrepare`, `Minn\Rest\Services`, `Minn\Rest\SettingsController`, `Minn\Runtime\Abilities`, `Minn\Runtime\AjaxController`, `Minn\Runtime\BlockFilters`, `Minn\Runtime\BlockHooks`, `Minn\Runtime\CommentEvents`, `Minn\Runtime\Constants`, `Minn\Runtime\Interactivity`, `Minn\Runtime\NavMenu`, `Minn\Runtime\PackageDownload`, `Minn\Runtime\Patterns`, `Minn\Runtime\PlaceholderTrace`, `Minn\Runtime\PluginUpdates`, `Minn\Runtime\Plugins`, `Minn\Runtime\PostEvents`, `Minn\Runtime\PostQuery`, `Minn\Runtime\PostSave`, `Minn\Runtime\ScriptModules`, `Minn\Runtime\TermEvents`, `Minn\Runtime\TermWriter`, `Minn\Runtime\UserEvents`, `Minn\Theme\ArchiveTitle`, `Minn\Theme\ClassicContent`, `Minn\Theme\ClassicRenderer`, `Minn\Theme\MainQueryBridge`, `Minn\Theme\PageRenderer`, `Minn\Theme\Templates`

```php
__construct(Minn\Context $context, bool $isAdmin = false)
```
The runtime for one request. Everything about the request itself
comes from the context; the fields below it are the same values,
kept as properties because plugin code reaches for them by name.

- readonly `Minn\Db $db` — The database door this request answers through.
- readonly `Minn\Content\Site $site` — The site's options.
- readonly `?Minn\Http\Request $request` — The request being answered, absent on the command line.
- readonly `Minn\Content\Reader $reader` — Who is reading this request.
- readonly `Minn\Auth\Capabilities $capabilities` — The capability engine.
- readonly `string $engineDir` — The minn/ folder: the engine's own files.
- readonly `string $absPath` — The site root with a trailing slash.
- readonly `string $version` — The WordPress release whose contracts the runtime speaks.
- readonly `Minn\Context $context`
- readonly `bool $isAdmin`

### `useSeams(Minn\Extension\SeamRunner $seams): void`

Holds the extension seams this request registered, so nothing static has to.

### `seams(): ?Minn\Extension\SeamRunner`

The extension seams, or null before the front has registered any (REST and the CLI never do).

### `renderState(): Minn\Blocks\RenderState`

The render state for this request, made on first use: one set of counters for everything rendered.

### `useRenderState(Minn\Blocks\RenderState $state): void`

Makes a render state this request's, so a renderer that brought its own is the one the leaves read.

### `blockRenderer(): Minn\Blocks\Renderer`

The block renderer for this request, made on first use over this request's own database door.

### static `boot(self $runtime): self`

Makes this request's runtime the one the facade sees and defines the
facade.

Deliberately does NOT start the facade's registries empty. A process
answering a second request would then have a fresh hook table that no
plugin can fill again: plugin files register their hooks as they are
included, and an include happens once per process. Until a plugin's
registrations can be replayed, a second boot inherits the first's
registries on purpose, which is why a worker runtime is not yet
something the engine claims (see contracts/runtime.md).

### static `current(): self`

The booted runtime; throws when there is none.

### static `booted(): bool`

Whether the runtime is up.

### static `hooks(): Minn\Runtime\Hooks`

The hook registry.

### static `options(): Minn\Runtime\Options`

The options store.

### static `capture(string $action, array $args = array ( )): string`

Runs an action and returns what it printed. Plugin callbacks may open
output buffers of their own during the action (a page post-processor
started in wp_head) or close one they think is theirs (the same plugin
in wp_footer); a sentinel buffer under the capture keeps the output
either way: extra buffers are flushed through their handlers into the
capture, and a capture closed early lands in the sentinel.

### static `contentFilter(string $content): string`

Content the engine rendered itself, through the_content for everything else hooked there.

### static `cache(): Minn\Runtime\ObjectCache`

The object cache.

### static `textDomains(): Minn\I18n\TextDomains`

The text domains loaded for this request.

### static `locales(): Minn\I18n\LocaleStack`

The locales this request switched into.

### static `shortcodes(): Minn\Runtime\Shortcodes`

The shortcode registry.

### static `scriptModules(): Minn\Runtime\ScriptModules`

The script modules registry.

### static `interactivity(): Minn\Runtime\Interactivity`

The interactivity API.

### static `blockTemplates(): Minn\Runtime\BlockTemplates`

The registered block templates.

### static `registry(): Minn\Runtime\Registry`

The post types, taxonomies, and statuses.

### static `postQuery(): Minn\Runtime\PostQuery`

A fresh post query over the runtime's registry.

### `get(string $key, mixed $default = NULL): mixed`

A per-request state value.

### `set(string $key, mixed $value): void`

Sets a per-request state value.

### `isSecure(): bool`

Whether the request is over HTTPS.

### `contentDir(): string`

wp-content under the site root.

### static `loadFacade(string $engineDir): void`

Defines the facade functions once; safe to call again.

### static `loadPluggables(): void`

The pluggable functions, once the plugins have had their chance to
define their own: each is defined only where no plugin did. The plugin
loader calls this before plugins_loaded; a boot that loads no plugins
calls it straight away.

### static `reset(): void`

Fresh per-request state, for suites.

Internals: `loadObjectCacheDropin()` (private, line 339)


## ScriptModules

`final class Minn\Runtime\ScriptModules` · `public/minn/src/Minn/Runtime/ScriptModules.php`

The script modules registry: registrations with typed dependencies, the
queue, and the four things a page prints from it (the import map, module
preloads, the module tags split head/footer, and per-module JSON data).
Behaviour pinned by the script-modules probe fixture.

- const `PRIORITIES` = `array (   0 => 'high',   1 => 'low',   2 => 'auto', )`

Used by: `Minn\Runtime\Runtime`

```php
__construct(Closure $url, Closure $data)
```
- `@param Closure(string, string|false|null): string $url turns a src and version into the printed URL`
- `@param Closure(string, array<string, mixed>): array<string, mixed> $data applies the module data filter`


### `register(string $id, string $src, array $deps, string|false|null $version, array $args): void`

Registers a module by id.

- `@param list<string|array{id: string, import?: string}> $deps`

### `enqueue(string $id, string $src, array $deps, string|false|null $version, array $args): void`

Queues a module, registering it when a source is given.

### `dequeue(string $id): void`

Removes a module from the queue.

### `deregister(string $id): void`

Forgets a module.

### `setFetchpriority(string $id, string $priority): bool`

Sets a module's fetch priority.

### `moveToFooter(string $id): bool`

Moves a module to the footer or the head.

### `moveToHead(string $id): bool`

Prints a module in the head; false when it is not registered.

### `queue(): array`

The module ids queued.

- `@return list<string>`

### `registered(string $id): ?array`

One registered module, or null.

- `@return array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}|null`

### `printImportMap(): string`

The import map script tag.

### `printPreloads(): string`

The modulepreload links.

### `printHead(): string`

The head's module tags.

### `printFooter(): string`

The footer's module tags.

### `printData(): string`

The script-module-data tags.

### `printA11y(): string`

The a11y module's tag, once.

Internals: `placeIn()` (private, line 127), `printTags()` (private, line 226), `marked()` (private, line 256), `complete()` (private, line 268), `dependencies()` (private, line 290), `urlOf()` (private, line 308), `attr()` (private, line 313), `wrong()` (private, line 318)


## ScriptPack

`final class Minn\Runtime\ScriptPack` · `public/minn/src/Minn/Runtime/ScriptPack.php`

The site-supplied script pack: the `wp-*` JavaScript packages the engine
does not reimplement, which a few plugins' front ends need (WooCommerce's
block cart and checkout are the reason it exists).

These packages are GPL, so the engine never ships them: the SITE installs
them into its own `wp-content`, the way it installs a language pack, and
the engine registers the handles only when they are actually there. A
site owner adding GPL files to a site that already runs GPL plugins is
not the engine distributing GPL; nothing under `minn/` changes.

The engine's own MIT packages under `minn/assets/wp` always win: the pack
fills the gaps around them, it does not replace them.

- const `RELATIVE_DIR` = `'minn-packages/wp-scripts'` — Under the site's wp-content, so backups and migrations carry it.
- const `MANIFEST` = `'script-loader-packages.php'`
- const `VENDOR` = `array (   'lodash' =>    array (   ),   'moment' =>    array (   ),   'react' =>    array (   ),   'react-dom' =>    array (     0 => 'react',   ),   'react-jsx-runtime' =>    array (     0 => 'react',   ),   'regenerator-runtime' =>    array (   ),   'wp-polyfill' =>    array (   ),   'wp-polyfill-dom-rect' =>    array (   ),   'wp-polyfill-element-closest' =>    array (   ),   'wp-polyfill-fetch' =>    array (   ),   'wp-polyfill-formdata' =>    array (   ),   'wp-polyfill-inert' =>    array (   ),   'wp-polyfill-node-contains' =>    array (   ),   'wp-polyfill-object-fit' =>    array (   ),   'wp-polyfill-url' =>    array (   ), )` — The vendor handles WordPress registers outside the packages manifest
(captured from the reference). `moment` matters as much as `react`:
one unregistered handle anywhere in a dependency tree drops every
script above it, which is how a missing moment silently cost the
whole WooCommerce cart bundle.

Used by: `Minn\Cli\MinnCommand`

### static `dir(string $contentDir): string`

Where the script pack lives under wp-content.

### static `installed(string $contentDir): bool`

Whether the script pack is on disk.

### static `handles(string $contentDir): array`

Every handle the pack can register: the packages manifest keyed by
`name.js` becomes `wp-name`, plus the three vendor handles. A handle
whose file is missing from the pack is skipped rather than
registered against a 404.

- `@return array<string, array{file: string, deps: list<string>, ver: string}>`

### static `installFromTree(string $contentDir, string $tree): array`

Copies the packages out of a WordPress tree (or an unpacked core
download) into the site's pack directory. Only the built JavaScript
and the manifest are taken, and only from the paths they live at, so
a wrong source folder copies nothing rather than something odd.

- `@return array{files: int, dir: string}`

### static `remove(string $contentDir): bool`

Removes the pack; the engine's own packages keep working without it.

Internals: `stamp()` (private, line 162)


## Shortcodes

`final class Minn\Runtime\Shortcodes` · `public/minn/src/Minn/Runtime/Shortcodes.php`

The shortcode registry plugin code fills with add_shortcode, and the
expansion do_shortcode performs: [tag attrs], [tag attrs/], [tag]…[/tag]
(the first closing tag wins; content is not expanded again), [[tag]] as
the literal, unregistered tags left as written.

Used by: `Minn\Runtime\Runtime`


### `tags(): array`

The registry itself, by reference, so the $shortcode_tags global plugin
code reads and copies is this array and not a snapshot of it.

- `@return array<string, callable>`

### `add(string $tag, callable $callback): void`

Registers a shortcode.

### `remove(string $tag): void`

Forgets a shortcode.

### `removeAll(): void`

Forgets every shortcode.

### `has(string $tag): bool`

Whether a shortcode is registered.

### `all(): array`

Every shortcode with its callback.

- `@return array<string, callable> every registered tag and its handler, to restore after a narrowed run`

### `restore(array $tags): void`

Replaces the registry, after a save-and-restore.

- `@param array<string, callable> $tags`

### `names(): array`

Every shortcode name.

- `@return list<string>`

### `pattern(?array $tags = NULL): ?string`

The regex matching the registered shortcodes, or null for none.

### `apply(string $content): string`

Content with the shortcodes run.

### `strip(string $content): string`

Content with the shortcodes removed.

### static `parse(string $text): array`

Shortcode attribute text as an array.

- `@return array<int|string, string> named attributes; bare words and quoted values keyed by position`


## StoredObjects

`final class Minn\Runtime\StoredObjects` · `public/minn/src/Minn/Runtime/StoredObjects.php`

The classes a stored blob may name and come back as. The serialized
reader never instantiates; the facade registers one factory per value
class it owns (WP_Post, WP_Term, WP_Comment) and a record naming any
other class stays a stdClass of its properties. That is what lets a
transient the reference wrote, holding post objects, read back typed.

- const `WRAPPERS` = `array (   'arrayobject' => 'ArrayObject',   'arrayiterator' => 'ArrayIterator',   'recursivearrayiterator' => 'RecursiveArrayIterator', )` — PHP's array wrappers a stored record may name, by lower-cased class name.

Used by: `Minn\Runtime\Options`


### static `register(string $class, Closure $factory): void`

Registers what a record naming this class becomes.

### static `reviver(): Closure`

The reviver the serialized reader takes: PHP's array wrappers rebuilt, a factory's object, or the properties as they are.

### static `knows(string $class): bool`

Whether a factory is registered for the class.

### static `reset(): void`

Forgets every factory, for suites.

Internals: `arrayWrapper()` (private, line 52)


## SymbolGap

`final readonly class Minn\Runtime\SymbolGap` · `public/minn/src/Minn/Runtime/SymbolGap.php`

The part of the reference's interface the runtime does not answer: names in
data/api-names.json that no facade file defines. It is the whole input the
symbol gate needs, so it can be exported to JSON and carried to a machine
that holds plugin source but no engine, which is how the catalogue-wide
compatibility scan runs.

Used by: `Minn\Runtime\Symbols`

- readonly `array $functions`
- readonly `array $classes`
- readonly `array $known`
- readonly `array $pluggable`

### static `ofLoadedFacade(string $engineDir): self`

Reads the reference's interface and subtracts everything the loaded facade
defines. Recomputed on every call because a plugin may define a name as it
loads; only the file read is cached.

### static `fromFile(string $path): self`

The gap read from its JSON file.

### `lacksFunction(string $name): bool`

Whether the runtime lacks a function.

### `defines(string $name): bool`

Whether the runtime already defines a function of the reference's
interface, so a plugin declaring it again without a guard would fail
to compile. The pluggable functions are not counted: the runtime
defines those after the plugins load, each only where no plugin did.

### `lacksClass(string $name): bool`

Whether the runtime lacks a class.

### `json(): string`

The gap as JSON.


## SymbolTable

`final class Minn\Runtime\SymbolTable` · `public/minn/src/Minn/Runtime/SymbolTable.php`

What a folder's PHP names, collected while its tokens are read: the
functions it calls, the classes it references, and what it declares or
guards itself, so the gate can subtract those before judging it.

Used by: `Minn\Runtime\Symbols`


### `call(string $name): void`

Notes a function called.

### `classRef(string $name): void`

Notes a class referenced.

### `declare(string $function): void`

Notes a function the folder declares, a method included: a call by that name is not a need.

### `declareGlobal(string $function): void`

Notes a function declared in the global scope, which the runtime must not already define.

### `declareClass(string $class): void`

Notes a class the folder declares.

### `guard(string $name): void`

A name an existence check protects: function_exists, class_exists, defined, and the rest.

### `toArray(bool $truncated): array`

The table as the gate reads it.

- `@return array{calls: list<string>, classes: list<string>, declared: array<string, true>, declaredGlobal: array<string, true>, declaredClasses: array<string, true>, guarded: array<string, true>, truncated: bool}`


## Symbols

`final class Minn\Runtime\Symbols` · `public/minn/src/Minn/Runtime/Symbols.php`

A static read of what a plugin's PHP calls: global functions and classes
it uses but does not itself declare, minus the ones it guards with
function_exists() or class_exists(). Checked against what the runtime
provides, this decides whether a plugin loads at all. The read is cached
in the minn_runtime_symbols option keyed by the plugin folder's newest
modification time.

- const `MAX_FILES` = `6000`
- const `SKIP_DIRS` = `array (   0 => 'node_modules',   1 => 'tests',   2 => 'test',   3 => '.git', )`
- const `READER` = `3` — Bumped whenever the token reader changes, so every cached scan is made again.

Used by: `Minn\Runtime\Plugins`

### static `missing(string $dir, Minn\Runtime\Options $options): array`

What a plugin folder needs that the runtime lacks, cached by mtime.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

### static `redeclaresIn(string $file): array`

The functions one file declares in the global scope, unguarded, that the
loaded runtime already defines: including that file would not compile.

- `@return list<string>`

### static `missingAgainst(string $dir, Minn\Runtime\SymbolGap $gap): array`

The same read against an exported gap instead of the running engine, so a
folder can be judged with no database, no options, and no facade loaded.

- `@return array{functions: list<string>, classes: list<string>, redeclares: list<string>, files: int, truncated: bool}`

Internals: `verdict()` (private, line 80), `phpFiles()` (private, line 114), `scan()` (private, line 148), `scanTokens()` (private, line 167), `noteName()` (private, line 242), `significant()` (private, line 277)


## TagEditor

`final class Minn\Runtime\TagEditor` · `public/minn/src/Minn/Runtime/TagEditor.php`

Edits one start tag's attributes in place the way the reference's tag
processor does: a replaced value keeps its position, a new attribute goes
right after the tag name (after any earlier insertion), and a removal takes
only the attribute's own text.

Used by: `Minn\Runtime\Interactivity`

```php
__construct(string $tag, array $attrs)
```
- `@param list<array{name: string, value: ?string, start: int, end: int}> $attrs offsets relative to the tag`


### `get(string $name): ?string`

An attribute's value, or null.

### `has(string $name): bool`

Whether the tag has an attribute.

### `set(string $name, string|bool $value): void`

true sets a bare boolean attribute.

### `remove(string $name): void`

Removes an attribute.

### `addClass(string $class): void`

Adds or removes a class.

### `removeClass(string $class): void`

Removes a class, and the attribute when it was the last one.

### `setStyle(string $property, ?string $value): void`

Sets or removes one inline style property.

### `html(): string`

The tag as edited.

Internals: `classes()` (private, line 99), `setClasses()` (private, line 106), `splice()` (private, line 147)


## TaxonomyClause

`final class Minn\Runtime\TaxonomyClause` · `public/minn/src/Minn/Runtime/TaxonomyClause.php`

The taxonomy side of a post query: every query var the reference reads
(cat, category_name, tag, the __in and __and pairs, each taxonomy's own
var) and an explicit tax_query, resolved to term_taxonomy ids with
children included where the taxonomy is hierarchical, as one WHERE
fragment on p.ID.

Used by: `Minn\Runtime\PostQuery`

```php
__construct(Minn\Db $db, Minn\Runtime\Registry $registry)
```


### `where(array $q): ?array`

The WHERE fragment and its parameters for a query's taxonomy conditions,
or null when the query has none.

- `@return array{string, list<mixed>}|null`

Internals: `taxonomyClauses()` (private, line 50), `taxonomySql()` (private, line 119), `termTaxonomyIds()` (private, line 159)


## TermEvents

`final readonly class Minn\Runtime\TermEvents` · `public/minn/src/Minn/Runtime/TermEvents.php`

What the reference's REST terms controller tells plugins, for the
engine's own: with plugins loaded a term is written through the
runtime's wp_insert_term, wp_update_term and wp_delete_term (create_term,
the cache cleaning that rebuilds a taxonomy's children map, created_term,
saved_term and their families), and the REST actions follow. Without a
booted runtime each write is the engine's own, given as a closure.

Used by: `Minn\Rest\TermsController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `create(string $name, string $taxonomy, array $args, Closure $quietly): int`

Creates a term and returns its id.

- `@param array{slug: string, description: string, parent: int} $args`
- `@param Closure(): int $quietly the engine's own write`

### `update(int $termId, string $taxonomy, array $args, Closure $quietly): void`

Updates a term's name, slug, description or parent.

- `@param array<string, mixed> $args the fields the request changes`
- `@param Closure(): void $quietly the engine's own write`

### `delete(int $termId, string $taxonomy, array $data, Minn\Http\Request $request, Closure $quietly): void`

Deletes a term, then tells plugins over REST with the term as it was and the response.

- `@param array<string, mixed> $data the response`
- `@param Closure(): void $quietly the engine's own delete`

### `restSaved(int $termId, string $taxonomy, Minn\Http\Request $request, string $verb): void`

rest_insert_{taxonomy}, then rest_after_insert_{taxonomy}, with the term as it stands and the request.


## TermQuery

`final readonly class Minn\Runtime\TermQuery` · `public/minn/src/Minn/Runtime/TermQuery.php`

Term reads in the shapes plugin code asks for: get_terms() arguments to
rows, the tree filters (child_of, exclude_tree), the fields shapes, and
the single-term lookups. Behaviour pinned by contracts/fixtures/api/content.json.

- const `DEFAULTS` = `array (   'taxonomy' => NULL,   'object_ids' => NULL,   'orderby' => 'name',   'order' => 'ASC',   'hide_empty' => true,   'include' =>    array (   ),   'exclude' =>    array (   ),   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => '',   'fields' => 'all',   'count' => false,   'name' => '',   'slug' => '',   'term_taxonomy_id' => '',   'hierarchical' => true,   'search' => '',   'name__like' => '',   'description__like' => '',   'pad_counts' => false,   'get' => '',   'child_of' => 0,   'parent' => '',   'childless' => false,   'cache_domain' => 'core',   'update_term_meta_cache' => true,   'meta_query' => '',   'meta_key' => '',   'meta_value' => '', )`
- const `COLUMNS` = `'t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count'`

Used by: `Minn\Runtime\TermWriter`

```php
__construct(Minn\Db $db, Closure $slug)
```
- `@param Closure(string): string $slug the slug sanitiser, so the reference's filters apply`


### `row(int $termId, ?string $taxonomy): ?array`

A term row joined with its taxonomy row, by id (and taxonomy when known). @return array<string, mixed>|null

- `@return array<string, mixed>|null`

### `find(string $field, mixed $value, ?string $taxonomy): ?array`

The term id and taxonomy a field value names; null when nothing matches
or the field is not one a term can be found by.

- `@return array{term_id: int, taxonomy: string}|null`

### `exists(string|int $term, ?string $taxonomy, ?int $parent): ?array`

Whether a term (by id, or by slug or name) exists, optionally under a
taxonomy and parent; the ids when it does.

- `@return array{term_id: int, term_taxonomy_id: int}|null`

### `normalise(array $args): array`

get_terms() arguments normalised: the "get all" shortcut, integer lists, sanitised slugs.

### `count(array $args, ?array $taxonomies): int`

How many terms the arguments match.

### `rows(array $args, ?array $taxonomies): array`

The term rows the arguments match, ordered and paged, with the tree
filters applied.

- `@param list<string>|null $taxonomies`
- `@return list<array<string, mixed>>`

### `hierarchy(string $taxonomy): array`

Every parent in a taxonomy that has children, mapped to its children's
ids. A taxonomy nobody nested reads as an empty map.

- `@return array<int, list<int>>`

### `children(int $termId, string $taxonomy): array`

Every term id under a term, however deep. @return list<int>

- `@return list<int>`

### `objectsIn(array $termIds, array $taxonomies, string $order): array`

The object ids attached to any of the terms in any of the taxonomies. @param list<int> $termIds @param list<string> $taxonomies @return list<int>

- `@param list<int> $termIds @param list<string> $taxonomies @return list<int>`

### static `shape(array $terms, string $fields): array`

The fields shapes get_terms() and wp_get_object_terms() share, over
term objects with the reference's public properties.

- `@param list<object> $terms`

### static `coerce(array $query, Closure $idList): array`

The typed shape of term query variables: counts as absolute integers,
lists as lists, flags as booleans, the ways plugin code spells them
tolerated on the way in.

- `@param Closure(mixed): list<int> $idList the caller's id-list parser`

Internals: `idList()` (private, line 173), `descendants()` (private, line 180), `where()` (private, line 199), `metaClauses()` (private, line 273), `ids()` (private, line 358), `like()` (private, line 367)


## TermWriter

`final readonly class Minn\Runtime\TermWriter` · `public/minn/src/Minn/Runtime/TermWriter.php`

The decisions behind wp_insert_term, wp_update_term, wp_delete_term, and
the object-term relationships: duplicate rules, slug uniqueness, parent
checks, which relationships to add and remove. The rows themselves come
from Content\Terms; the lifecycle actions fire from here in the
reference's order. Behaviour pinned by contracts/fixtures/api/content.json.

```php
__construct(Minn\Db $db, Minn\Content\Terms $terms, Minn\Runtime\TermQuery $query, Minn\Content\PostWriter $posts, Closure $slug)
```
- `@param Closure(string): string $slug the slug sanitiser, so the reference's filters apply`


### `insert(string $name, string $taxonomy, array $args, bool $hierarchical): Minn\Runtime\Refusal|array`

Inserts a term, or the refusal.

- `@param array{slug?: string, description?: string, parent?: int|string, alias_of?: string} $args`
- `@return array{term_id: int, term_taxonomy_id: int}|Refusal`

### `update(array $current, string $taxonomy, array $args): Minn\Runtime\Refusal|array`

Updates a term, or the refusal.

- `@param array<string, mixed> $current the term's row`
- `@param array<string, mixed> $args`
- `@return array{name: string, slug: string, description: string, parent: int}|Refusal what to write`

### `apply(int $termId, string $taxonomy, array $change): void`

Writes an update() decision. @param array{name: string, slug: string, description: string, parent: int} $change

- `@param array{name: string, slug: string, description: string, parent: int} $change`

### `delete(array $row, string $taxonomy, bool $hierarchical, int $default): array`

Deletes a term; objects left without a category fall back to the
default one. Returns the object ids the term was attached to.

- `@param array<string, mixed> $row`
- `@return list<int>`

### `relate(int $objectId, array $keep, array $old, string $taxonomy, ?Closure $count = NULL): void`

Makes an object's relationships in a taxonomy exactly $keep (or $old
plus $keep when appending), in the reference's order: each new
relationship between add_term_relationship and
added_term_relationship, the counts of those terms, then the ones
that went, between delete_term_relationships and
deleted_term_relationships, and their counts. $count recounts a list
of term_taxonomy ids and tells plugins (wp_update_term_count); without
it the taxonomy is recounted quietly.

- `@param list<int> $keep term_taxonomy ids`
- `@param list<int> $old the object's current term_taxonomy ids in the taxonomy`
- `@param (Closure(list<int>): void)|null $count`

### `unrelate(int $objectId, array $ttIds, string $taxonomy, ?Closure $count = NULL): bool`

Removes the given relationships between delete_term_relationships and
deleted_term_relationships, then recounts those terms; true when any
row went.

- `@param list<int> $ttIds`
- `@param (Closure(list<int>): void)|null $count`

### `publishedCount(int $ttId): int`

How many published objects a term holds, as the stored count keeps it.

### `storeCount(int $ttId, int $count): void`

Stores a term's count.

### `termIdsOf(array $ttIds): array`

The term ids behind term_taxonomy ids, as stored (strings), in the order given. @param list<int> $ttIds @return list<string>

- `@param list<int> $ttIds @return list<string>`

Internals: `ttIdOf()` (private, line 207)


## TreeWalk

`final class Minn\Runtime\TreeWalk` · `public/minn/src/Minn/Runtime/TreeWalk.php`

The Walker contract's traversal: elements keyed by the walker's
db_fields parent/id pair, displayed depth-first through the walker's
four element methods. The facade Walker delegates here; subclasses
override the element methods (or display_element itself) and the
dispatch stays virtual.

### static `walk(object $walker, array $elements, int $maxDepth, array $args): string`

Walks a tree with a Walker the way the reference does.

- `@param list<object> $elements`

### static `element(object $walker, mixed $element, array $children, int $maxDepth, int $depth, array $args, string $output): void`

Walks one element and its children.

- `@param array<int|string, mixed> $children`


## UserEvents

`final readonly class Minn\Runtime\UserEvents` · `public/minn/src/Minn/Runtime/UserEvents.php`

What the reference's REST users controller tells plugins, for the
engine's own: with plugins loaded an account is written through the
runtime's wp_insert_user, wp_update_user and wp_delete_user
(wp_set_password, each profile meta, the role, clean_user_cache,
user_register and the count, profile_update, delete_user and
deleted_user), and the REST actions follow. Without a booted runtime each
write is the engine's own, given as a closure.

Used by: `Minn\Rest\UsersController`

### `live(): bool`

Whether plugins are loaded to be told anything.

### `create(array $userdata, string $role, Minn\Http\Request $request, Closure $quietly): int`

Creates an account the way the REST controller does: wp_insert_user
with no role, rest_insert_user, then the role added, then
rest_after_insert_user. Returns the new id.

- `@param array<string, mixed> $userdata wp_insert_user's fields`
- `@param Closure(): int $quietly the engine's own write`

### `update(int $id, array $userdata, ?string $role, Minn\Http\Request $request, Closure $quietly): void`

Updates an account: wp_update_user with the changed fields, the role
when one is given, then the two REST actions.

- `@param array<string, mixed> $userdata the fields the request changes`
- `@param Closure(): void $quietly the engine's own write`

### `delete(int $id, ?int $reassign, array $data, Minn\Http\Request $request, Closure $quietly): void`

Deletes an account, its posts going to $reassign (or with it when
none), then rest_delete_user with the account as it was.

- `@param array<string, mixed> $data the response`
- `@param Closure(): void $quietly the engine's own delete`


## UserInsert

`final readonly class Minn\Runtime\UserInsert` · `public/minn/src/Minn/Runtime/UserInsert.php`

The decisions behind wp_insert_user: what a new account needs, which email
and login collide, the resolved profile fields, and which columns an
update changes. The sanitisers and lookups arrive as closures so the
reference's filters apply. Behaviour pinned by contracts/fixtures/api/functions.json.

- const `META_KEYS` = `array (   0 => 'nickname',   1 => 'first_name',   2 => 'last_name',   3 => 'description',   4 => 'rich_editing',   5 => 'syntax_highlighting',   6 => 'comment_shortcuts',   7 => 'admin_color',   8 => 'use_ssl',   9 => 'show_admin_bar_front',   10 => 'locale', )`

```php
__construct(Closure $sanitizeLogin, Closure $sanitizeSlug, Closure $isEmail, Closure $loginTaken, Closure $emailOwner, Closure $roleExists)
```
- `@param Closure(string): string $sanitizeLogin`
- `@param Closure(string): string $sanitizeSlug`
- `@param Closure(string): bool $isEmail`
- `@param Closure(string): bool $loginTaken`
- `@param Closure(string): int $emailOwner 0 when nobody has it`
- `@param Closure(string): bool $roleExists`


### `resolve(array $userdata, ?array $existing): Minn\Runtime\Refusal|array`

The validated, resolved fields, or the refusal the reference gives.

- `@param array<string, mixed> $userdata`
- `@param array<string, mixed>|null $existing the current row on an update`
- `@return array{login: string, email: string, nicename: ?string, display_name: string, role: ?string}|Refusal`

### static `account(array $userdata, array $resolved, string $defaultRole): array`

The fields a new account is created from. @param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved @return array<string, mixed>

- `@param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved @return array<string, mixed>`

### static `changes(array $userdata, array $existing, array $resolved, Closure $url, Closure $hash): array`

The columns an update actually changes.

- `@param array<string, mixed> $existing`
- `@param array{login: string, email: string, nicename: ?string, display_name: string, role: ?string} $resolved`
- `@param Closure(string): string $url the URL sanitiser`
- `@param Closure(string): string $hash the password hasher`
- `@return array<string, string>`


## UserQuery

`final readonly class Minn\Runtime\UserQuery` · `public/minn/src/Minn/Runtime/UserQuery.php`

The user listing behind WP_User_Query: role filtering through the
capabilities meta, include/exclude, column search with * wildcards,
ordering and paging. Returns raw user rows; the facade shapes them into
WP_User objects, string ids, or column records the reference's way.

- const `COLUMNS` = `array (   0 => 'ID',   1 => 'user_login',   2 => 'user_nicename',   3 => 'user_email',   4 => 'user_url',   5 => 'user_registered',   6 => 'display_name', )`

```php
__construct(Minn\Db $db)
```


### `run(array $args): array`

Runs a WP_User_Query-shaped args array and returns its rows and total.

- `@param array<string, mixed> $args`
- `@return array{rows: list<array>, total: int}`

