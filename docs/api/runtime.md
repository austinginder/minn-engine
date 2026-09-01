# `Minn\Runtime`

the WordPress runtime plugins load against

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Abilities`](#abilities) | final class | 77 | The abilities registry behind the wp_*_ability facade: categories and |
| [`Assets`](#assets) | final class | 267 | The registry behind wp_register_/wp_enqueue_ for scripts and styles: |
| [`Avatar`](#avatar) | final class | 62 | Avatars the way get_avatar_data and get_avatar decide them: the argument |
| [`BlockFilters`](#blockfilters) | final class | 75 | The block-level filters plugin code hooks (pre_render_block, |
| [`BlockHooks`](#blockhooks) | final class | 68 | The Block Hooks API on the engine's own front end: a plugin asks for its |
| [`BlockMetadata`](#blockmetadata) | final class | 93 | block.json to the settings a block type registers with: the property |
| [`BlockTemplates`](#blocktemplates) | final class | 59 | Block templates plugins register at runtime, by their namespaced name |
| [`BlockWidget`](#blockwidget) | final class | 26 | A block widget's legacy class name. Every widget the block editor saves |
| [`CommentCloser`](#commentcloser) | final readonly class | 21 | The Discussion setting that closes comments on old posts. Observed on the |
| [`CommentQuery`](#commentquery) | final readonly class | 112 | Comment reads in the get_comments() shape: arguments to rows or a count, and the approval breakdown wp_count_comments reports. |
| [`Connectors`](#connectors) | final class | 201 | The connectors registry: the external services a site talks to (AI |
| [`Constants`](#constants) | final class | 51 | The constants plugin code expects: the fixed set from data/constants.json |
| [`CronTable`](#crontable) | final class | 99 | The cron option's shape, operated on as data: timestamp => hook => key => |
| [`DbDelta`](#dbdelta) | final readonly class | 108 | dbDelta as the reference does it: a CREATE TABLE statement creates the |
| [`Hooks`](#hooks) | final class | 228 | The hook registry plugin code registers into and the engine fires. |
| [`Interactivity`](#interactivity) | final class | 484 | Server-side directive processing for the Interactivity API: the state and |
| [`MainQuery`](#mainquery) | final class | 30 | The query variables the reference's main query would carry for a URL the |
| [`Meta`](#meta) | final readonly class | 126 | The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need. |
| [`NavMenu`](#navmenu) | final class | 301 | Nav-menu item decoration for wp_nav_menu(): the reference's class tokens |
| [`OEmbed`](#oembed) | final class | 80 | oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes. |
| [`ObjectCache`](#objectcache) | final class | 46 | The per-request object cache behind wp_cache_*: groups of keys, nothing persistent. |
| [`Options`](#options) | final class | 144 | Options as plugin code sees them: PHP values, decoded from the stored |
| [`PageMenu`](#pagemenu) | final class | 38 | The page-list menu a classic theme falls back to when no menu is |
| [`Pages`](#pages) | final class | 113 | get_pages() as the reference shapes it: its arguments as a post query, and the tree order of the result. |
| [`Patterns`](#patterns) | final class | 123 | The block pattern, pattern category, and block style registries as data. |
| [`PlaceholderTrace`](#placeholdertrace) | final class | 26 | Records every call into a generated placeholder while a site opts in by |
| [`Plugins`](#plugins) | final class | 157 | Loads the site's plugins into the runtime the way the reference does: |
| [`PostInsert`](#postinsert) | final readonly class | 160 | The decisions behind wp_insert_post: which columns a postarr fills, when |
| [`PostLookup`](#postlookup) | final readonly class | 65 | The post reads plugin code asks for by shape: a page by title, revisions, counts. |
| [`PostQuery`](#postquery) | final class | 607 | The query WP_Query runs: its variables become one SELECT over the posts |
| [`QueriedObject`](#queriedobject) | final readonly class | 68 | Which object a query is "about", read from its flags and variables: a term |
| [`QueryFlags`](#queryflags) | final readonly class | 99 | The conditional flags a set of query variables implies (is_single, is_archive, |
| [`Recovery`](#recovery) | final readonly class | 116 | Recovery from a fatal in someone else's code. When a plugin or theme |
| [`Refusal`](#refusal) | final readonly class | 6 | A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error. |
| [`Registry`](#registry) | final class | 346 | Post types, taxonomies, and statuses as plugin code registers and reads |
| [`Runtime`](#runtime) | final class | 195 | The WordPress runtime the engine offers plugin code: the procedural |
| [`ScriptModules`](#scriptmodules) | final class | 274 | The script modules registry: registrations with typed dependencies, the |
| [`ScriptPack`](#scriptpack) | final class | 144 | The site-supplied script pack: the `wp-*` JavaScript packages the engine |
| [`Shortcodes`](#shortcodes) | final class | 109 | The shortcode registry plugin code fills with add_shortcode, and the |
| [`SymbolGap`](#symbolgap) | final readonly class | 65 | The part of the reference's interface the runtime does not answer: names in |
| [`Symbols`](#symbols) | final class | 202 | A static read of what a plugin's PHP calls: global functions and classes |
| [`TagEditor`](#tageditor) | final class | 128 | Edits one start tag's attributes in place the way the reference's tag |
| [`TermQuery`](#termquery) | final readonly class | 393 | Term reads in the shapes plugin code asks for: get_terms() arguments to |
| [`TermWriter`](#termwriter) | final readonly class | 142 | The decisions behind wp_insert_term, wp_update_term, wp_delete_term, and |
| [`TreeWalk`](#treewalk) | final class | 66 | The Walker contract's traversal: elements keyed by the walker's |
| [`UserInsert`](#userinsert) | final readonly class | 111 | The decisions behind wp_insert_user: what a new account needs, which email |
| [`UserQuery`](#userquery) | final readonly class | 47 | The user listing behind WP_User_Query: role filtering through the |

## Abilities

`final class Minn\Runtime\Abilities` · `public/minn/src/Minn/Runtime/Abilities.php`

The abilities registry behind the wp_*_ability facade: categories and
abilities recorded per request. The reference initialises the API
lazily, firing wp_abilities_api_init once on first access so plugin
registrations land before any lookup.

### static `initialize(): void`

Fires the init action once, then answers every later call from the recorded state.

### static `registerCategory(string $slug, array $args): bool`

### static `register(string $name, array $args): ?array`

### static `unregister(string $name, bool $category = false): bool`

### static `find(string $name, bool $category = false): ?array`

- `@return array<string, mixed>|null`

### static `all(bool $categories = false): array`

- `@return array<string, array>`


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

### `externalHosts(string $ownHost): array`

- `@return list<string> hosts of enqueued sources (dependencies first) away from the given host, for dns-prefetch hints`

### `deregister(string $handle): void`

### `enqueue(string $handle): void`

### `dequeue(string $handle): void`

### `registered(string $handle): bool`

### `enqueued(string $handle): bool`

Queued directly, or pulled in as the dependency of something queued,
however deep. The reference answers the same way, and plugin code
leans on it: WooCommerce only attaches its settings blob when it
finds `wc-settings` "enqueued", and nothing queues that handle by
name, it only ever rides in as a dependency.

### `done(string $handle): bool`

### `addInline(string $handle, string $code, string $position): bool`

### `addData(string $handle, string $key, mixed $value): bool`

### `data(string $handle, string $key): mixed`

### `localize(string $handle, string $name, array $data): bool`

### `toPrint(?bool $footer = NULL): array`

Every queued handle not yet printed, dependencies first, filtered to the group (footer or not).

### `withPath(): array`

Enqueued handles that name a file on disk, mapped to that path. A style
registered with a `path` datum is saying it can be inlined; whether it
is small enough to be worth inlining is the caller's decision.

- `@return array<string, string> handle => path`

### `unsource(string $handle): void`

Drops a handle's source so it prints as markup rather than a link.

### `markDone(string $handle): void`

### `item(string $handle): ?array`

### `items(): array`

- `@return array<string, array<string, mixed>>`

### `queue(): array`

- `@return list<string>`

### `kind(): string`


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

### static `active(): bool`

### static `toArray(Minn\Blocks\Block $block): array`

- `@return array<string, mixed> the parsed-array shape plugin code reads`

### static `fromArray(array $parsed): Minn\Blocks\Block`

### static `before(Minn\Blocks\Block $block): Minn\Blocks\Block|string`

A short-circuit from pre_render_block, or the block as render_block_data left it.

### static `after(Minn\Blocks\Block $block, string $html): string`


## BlockHooks

`final class Minn\Runtime\BlockHooks` · `public/minn/src/Minn/Runtime/BlockHooks.php`

The Block Hooks API on the engine's own front end: a plugin asks for its
block to be inserted next to an anchor block in a template part or a
pattern (WooCommerce puts the mini-cart after the navigation in a
header), and the markup the renderer reads carries those insertions.

The facade owns the traversal (wp-api/blocks.php); this is the seam the
theme blocks call, and it stays inert until a plugin actually hooks
something, so a site without such a plugin parses nothing extra.

### static `active(): bool`

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

### static `settings(array $metadata, Closure $scriptHandle, Closure $styleHandle, Closure $moduleId, Closure $render): array`

- `@param Closure(array, string, int): (string|false) $scriptHandle registers one script entry, answering its handle`
- `@param Closure(array, string, int): (string|false) $styleHandle registers one style entry`
- `@param Closure(array, string, int): (string|false) $moduleId registers one view script module entry`
- `@param Closure(string): ?Closure $render the render callback for a template path, or null when the file is missing`
- `@return array<string, mixed>`

### static `assetHandle(string $block, string $field, int $index = 0): string`

The script or style handle a block.json field registers under; core blocks keep the `wp-block-` spelling.


## BlockTemplates

`final class Minn\Runtime\BlockTemplates` · `public/minn/src/Minn/Runtime/BlockTemplates.php`

Block templates plugins register at runtime, by their namespaced name
("plugin//slug"). A theme file or a saved template of the same slug
wins; otherwise the registered content renders for that slug.

### `register(string $name, array $args): array|string`

The registered row, or the refusal code the reference reports.

### `unregister(string $name): ?array`

### `all(): array`

- `@return array<string, array<string, mixed>> by registered name`

### `get(string $name): ?array`

### `bySlug(string $slug): ?array`


## BlockWidget

`final class Minn\Runtime\BlockWidget` · `public/minn/src/Minn/Runtime/BlockWidget.php`

A block widget's legacy class name. Every widget the block editor saves
is one block of markup, and a classic theme styles it by the widget
class the equivalent legacy widget used, so the wrapper carries a
second class named after the FIRST block in the content. A block with
no legacy equivalent adds nothing.

- const `BASE_CLASS` = `'widget_block'`

### static `classNameFor(array $blocks): string`

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


## CommentQuery

`final readonly class Minn\Runtime\CommentQuery` · `public/minn/src/Minn/Runtime/CommentQuery.php`

Comment reads in the get_comments() shape: arguments to rows or a count, and the approval breakdown wp_count_comments reports.

- const `DEFAULTS` = `array (   'post_id' => 0,   'post__in' =>    array (   ),   'status' => 'all',   'number' => '',   'offset' => 0,   'orderby' => 'comment_date_gmt',   'order' => 'DESC',   'fields' => '',   'count' => false,   'parent' => '',   'type' => '',   'author_email' => '',   'user_id' => '',   'search' => '',   'include_unapproved' =>    array (   ),   'comment__in' =>    array (   ),   'comment__not_in' =>    array (   ),   'post_status' => '',   'post_type' => '',   'author__in' =>    array (   ),   'date_query' => NULL,   'hierarchical' => false, )`

```php
__construct(Minn\Db $db)
```

### `count(array $args): int`

### `rows(array $args): array`

- `@return list<array<string, mixed>>`

### `breakdown(int $postId): array`

The counts wp_count_comments reports, for one post or the site. @return array<string, int>

- `@return array<string, int>`


## Connectors

`final class Minn\Runtime\Connectors` · `public/minn/src/Minn/Runtime/Connectors.php`

The connectors registry: the external services a site talks to (AI
providers, spam filters, cloud services) and how each authenticates.
Rows are normalised on the way in, the way the reference keeps them;
the facade class hands them back to plugin code.

### `register(string $id, array $args): ?Minn\Runtime\Refusal`

### `unregister(string $id): ?array`

- `@return array<string, mixed>|null the row that was registered, null when there was none`

### `all(): array`

- `@return array<string, array<string, mixed>>`

### `get(string $id): ?array`

### `has(string $id): bool`

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


## Constants

`final class Minn\Runtime\Constants` · `public/minn/src/Minn/Runtime/Constants.php`

The constants plugin code expects: the fixed set from data/constants.json
(captured from the reference) and the per-site ones computed here. Nothing
already defined is touched, so wp-config.php keeps the last word.

### static `define(Minn\Runtime\Runtime $runtime): void`


## CronTable

`final class Minn\Runtime\CronTable` · `public/minn/src/Minn/Runtime/CronTable.php`

The cron option's shape, operated on as data: timestamp => hook => key =>
entry, kept in natural timestamp order. The key is the reference's own
(a hash of the serialized argument list), so both stacks read one table.

### static `key(array $args): string`

- `@param array<int, array<string, array<string, array<string, mixed>>>> $crons`

### static `hasNear(array $crons, int $timestamp, string $hook, string $key, int $window): bool`

Whether the same hook and arguments are already scheduled within the window around the timestamp.

### static `insert(array $crons, int $timestamp, string $hook, string $key, array $entry): array`

### static `remove(array $crons, int $timestamp, string $hook, string $key): array`

### static `removeHook(array $crons, string $hook): array`

- `@return array{0: array, 1: int} the table without the hook, and how many entries went`

### static `timestampsFor(array $crons, string $hook, string $key): array`

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

- `@param array<string, string> $creates @return array<string, string> what was (or would be) done, by table or table.column`

### `tables(): array`

- `@return list<string>`

### `columns(string $table): array`

- `@return array<string, array<string, mixed>> by column name`

### `indexNames(string $table): array`

- `@return list<string> lowercase key names`

### `run(string $ddl): void`


## Hooks

`final class Minn\Runtime\Hooks` · `public/minn/src/Minn/Runtime/Hooks.php`

The hook registry plugin code registers into and the engine fires.
Semantics come from contracts/fixtures/api/hooks.json: callbacks run
by ascending priority then insertion order; a callback added at a
higher priority during a run takes part in that run, one added at the
current or a lower priority waits for the next; a callback removed
before its turn is skipped; the "all" hook sees every firing with the
hook name first and every argument regardless of its accepted count.

### `onNew(Closure $observer): void`

Called with each hook name the first time a callback registers under it.

### `storage(): array`

The live registry, for a hook object to share by reference.

### `counters(bool $actions): array`

- `@return array<string, int>`

### `stackRef(): array`

- `@return list<string>`

### `currentPriority(string $hook): int|false`

The priority a running hook is at, or false when it is idle.

### `add(string $hook, callable|array|string $callback, string|int $priority = 10, int $accepted = 1): bool`

### `remove(string $hook, callable|array|string $callback, string|int $priority = 10): bool`

### `removeAll(string $hook, string|int|false $priority = false): bool`

Every callback of a hook, or only those at one priority. Always true, as observed.

### `has(string $hook, callable|array|string|false $callback = false): int|bool`

With a callback: its lowest priority, or false; without: whether anything is registered.

### `filter(string $hook, array $args): mixed`

- `@param list<mixed> $args the value first`

### `action(string $hook, array $args): void`

- `@param list<mixed> $args`

### `actionsDone(string $hook): int`

### `filtersDone(string $hook): int`

### `current(): string|false`

### `doing(?string $hook): bool`

### `registered(): array`

- `@return array<string, array<int, list<callable>>> a read-only view for diagnostics`


## Interactivity

`final class Minn\Runtime\Interactivity` · `public/minn/src/Minn/Runtime/Interactivity.php`

Server-side directive processing for the Interactivity API: the state and
config stores, and the pass that resolves data-wp-bind, data-wp-class,
data-wp-style, data-wp-text, and data-wp-each against state and context
before the markup leaves the server. Behaviour pinned by the interactivity
probe fixture.

### `state(?string $namespace, array $state = array ( )): array`

- `@return array<string, mixed>`

### `allState(): array`

- `@return array<string, array<string, mixed>> every namespace's state, for the client`

### `allConfig(): array`

- `@return array<string, array<string, mixed>>`

### `config(string $namespace, array $config = array ( )): array`

- `@return array<string, mixed>`

### `context(?string $namespace = NULL): array`

- `@return array<string, mixed>`

### `element(): ?array`

- `@return array<string, mixed>|null`

### `process(string $html): string`


## MainQuery

`final class Minn\Runtime\MainQuery` · `public/minn/src/Minn/Runtime/MainQuery.php`

The query variables the reference's main query would carry for a URL the
engine has resolved, so plugin code reading is_page(), get_queried_object(),
or get_search_query() during a front-end render sees the same page.

### static `vars(Minn\Front\Resolution $resolution): array`

- `@return array<string, mixed>`


## Meta

`final readonly class Minn\Runtime\Meta` · `public/minn/src/Minn/Runtime/Meta.php`

The four meta tables behind get_metadata and friends: reads by object, and the row-level writes the update and delete rules need.

```php
__construct(Minn\Db $db)
```

### static `knows(string $type): bool`

### static `clausesFromQueryVars(array $queryVars): array`

The meta clauses hidden in flat query vars (meta_key, meta_value, the
compare and type variants), merged ahead of an explicit meta_query,
the reference's precedence for WP_Meta_Query::parse_query_vars.

- `@param array<string, mixed> $queryVars`
- `@return list<mixed>`

### `all(string $type, int $objectId): array`

Every row of an object's meta, values as stored, grouped by key in id order. @return array<string, list<string>>

- `@return array<string, list<string>>`

### `add(string $type, int $objectId, string $key, string $stored): int`

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

### `find(string $type, ?int $objectId, string $key, ?string $stored): array`

The rows a delete would take: by key, for one object or all, optionally only a stored value. @return list<array{meta_id: int, object_id: int}>

- `@return list<array{meta_id: int, object_id: int}>`

### `deleteRows(string $type, array $ids): void`

- `@param list<int> $ids`


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

- `@param list<object> $items`
- `@return list<object>`


## OEmbed

`final class Minn\Runtime\OEmbed` · `public/minn/src/Minn/Runtime/OEmbed.php`

oEmbed as data: provider matching against the wildcard table, response parsing, and the markup an oEmbed payload becomes.

### static `providerFor(array $providers, string $url): ?string`

- `@param array<string, array{0: string, 1: bool}> $providers mask => [endpoint with {format}, mask is a regex]`

### static `parseJson(string $body): ?array`

- `@return array<string, mixed>|null`

### static `parseXml(string $body): ?array`

- `@return array<string, mixed>|null`

### static `html(array $data, string $url, Closure $escUrl, Closure $escAttr, Closure $escHtml): ?string`

- `@param array<string, mixed> $data the oEmbed payload`
- `@param Closure(string): string $escUrl @param Closure(string): string $escAttr @param Closure(string): string $escHtml`

### static `stripNewlines(string $html): string`

Drops newlines from embed markup while leaving the inside of <pre> blocks untouched.


## ObjectCache

`final class Minn\Runtime\ObjectCache` · `public/minn/src/Minn/Runtime/ObjectCache.php`

The per-request object cache behind wp_cache_*: groups of keys, nothing persistent.

### `get(string $key, string $group, ?bool $found = NULL): mixed`

### `set(string $key, mixed $value, string $group): bool`

### `add(string $key, mixed $value, string $group): bool`

### `delete(string $key, string $group): bool`

### `flush(): bool`

### `flushGroup(string $group): bool`


## Options

`final class Minn\Runtime\Options` · `public/minn/src/Minn/Runtime/Options.php`

Options as plugin code sees them: PHP values, decoded from the stored
blob by the engine's own reader, cached for the request so a value written
and read again in one request keeps its PHP type (an int stays an int,
false stays false) exactly as the reference shows.

```php
__construct(Minn\Db $db)
```

### `autoloaded(): array`

Every autoloaded option as stored. @return array<string, string>

- `@return array<string, string>`

### `get(string $name): mixed`

### `exists(string $name): bool`

### `add(string $name, mixed $value, string $autoload = 'auto'): bool`

### `update(string $name, mixed $value, ?string $autoload = NULL): bool`

False when the value is unchanged, as the reference reports.

### `setAutoload(string $name, bool $on): bool`

Flips the autoload column; false when the option is missing or already so.

### `delete(string $name): bool`

### `expiredTransientNames(int $now, string $prefix = '_transient_timeout_'): array`

The names of transients whose expiry has passed. The timeout row is the
one that knows, so the sweep reads those and hands back the bare names.

- `@return list<string>`

### `forget(string $name): void`

### static `toStorage(mixed $value): string`

What the reference stores: arrays serialized, scalars as their string form.

### static `fromStorage(string $raw): mixed`


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


## Patterns

`final class Minn\Runtime\Patterns` · `public/minn/src/Minn/Runtime/Patterns.php`

The block pattern, pattern category, and block style registries as data.
Entries registered after init are remembered separately, because the
editor asks for those on their own.

### `registerPattern(mixed $name, mixed $properties, bool $afterInit): ?Minn\Runtime\Refusal`

### `unregisterPattern(string $name): bool`

### `pattern(string $name, bool $afterInitOnly = false): ?array`

### `patterns(bool $afterInitOnly = false): array`

- `@return list<array<string, mixed>>`

### `registerCategory(mixed $name, mixed $properties, bool $afterInit): ?Minn\Runtime\Refusal`

### `unregisterCategory(string $name): bool`

### `category(string $name): ?array`

### `categories(bool $afterInitOnly = false): array`

- `@return list<array<string, mixed>>`

### `registerStyle(mixed $blocks, mixed $properties): ?Minn\Runtime\Refusal`

- `@param string|list<string> $blocks`

### `unregisterStyle(string $block, string $style): bool`

### `style(string $block, string $style): ?array`

### `styles(?string $block = NULL): array`

- `@return array<string, array<string, array<string, mixed>>>`


## PlaceholderTrace

`final class Minn\Runtime\PlaceholderTrace` · `public/minn/src/Minn/Runtime/PlaceholderTrace.php`

Records every call into a generated placeholder while a site opts in by
having wp-content/minn-placeholder-trace.log on disk: one line per call
with the symbol, the plugin file that called it, and the request. The
log says which placeholders deserve behaviour; an absent file costs one
stat per request.

### static `hit(string $symbol): void`


## Plugins

`final class Minn\Runtime\Plugins` · `public/minn/src/Minn/Runtime/Plugins.php`

Loads the site's plugins into the runtime the way the reference does:
mu-plugins first, then active_plugins in stored order, each file
included once. A plugin loads only when the static symbol read finds
nothing the runtime lacks; otherwise it is reported and skipped so the
site keeps rendering. The lifecycle actions fire between the phases.

### static `load(Minn\Runtime\Runtime $runtime): void`

### static `loaded(): array`

- `@return list<string>`

### static `skipped(): array`

- `@return array<string, array<string, mixed>> plugin file => why it did not load`

### static `isLoaded(string $plugin): bool`

True when the named plugin file is running as code this request.


## PostInsert

`final readonly class Minn\Runtime\PostInsert` · `public/minn/src/Minn/Runtime/PostInsert.php`

The decisions behind wp_insert_post: which columns a postarr fills, when
the post counts as empty, the status a publish request lands in, the dates
and the slug, the categories a new post gets. The rows are written by
Content\PostWriter; the facade fires the hooks around each step.

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


## PostLookup

`final readonly class Minn\Runtime\PostLookup` · `public/minn/src/Minn/Runtime/PostLookup.php`

The post reads plugin code asks for by shape: a page by title, revisions, counts.

```php
__construct(Minn\Db $db)
```

### `idByTitle(string $title, array $types): ?int`

- `@param list<string> $types`

### `revisionsOf(int $postId): array`

Revision rows newest first. @return list<array<string, mixed>>

- `@return list<array<string, mixed>>`

### `countByStatus(string $type): array`

- `@return array<string, int> status => count`

### `countAttachments(): array`

- `@return array<string, int> mime type => count, plus 'trash'`

### `countByAuthor(int $userId, array $types, array $statuses): int`

- `@param list<string> $types @param list<string> $statuses`

### `idsByAuthor(int $userId): array`

- `@return list<int>`

### `attachmentIdByFile(string $path): ?int`

The attachment whose stored file path is the given one.


## PostQuery

`final class Minn\Runtime\PostQuery` · `public/minn/src/Minn/Runtime/PostQuery.php`

The query WP_Query runs: its variables become one SELECT over the posts
table with the joins the taxonomy, meta, and author conditions need.
Shapes and defaults follow contracts/fixtures/api/content.json.

```php
__construct(Minn\Db $db, Minn\Runtime\Registry $registry)
```

### `run(array $q, bool $isHome): array`

- `@param array<string, mixed> $q`
- `@return array{rows: list<array>, found: int, sticky: list<array>}`


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

- `@param array<string, mixed> $vars`
- `@param array<string, bool> $flags the query's is_* flags`
- `@param callable(string): mixed $option a filtered option read`


## QueryFlags

`final readonly class Minn\Runtime\QueryFlags` · `public/minn/src/Minn/Runtime/QueryFlags.php`

The conditional flags a set of query variables implies (is_single, is_archive,
is_home, ...), derived the way the reference's parse step derives them, plus
the variables after the integer casts that step applies.

- readonly `array $vars`
- readonly `array $flags`

### static `derive(array $vars, Minn\Runtime\Registry $registry, callable $option): self`

- `@param array<string, mixed> $vars the filled query variables`
- `@param callable(string): mixed $option a filtered option read`

### static `customTaxonomyVar(array $vars, Minn\Runtime\Registry $registry): ?array`

The first registered taxonomy (other than the two built-in ones) whose
query variable carries a value, as [taxonomy name, query var].

- `@return array{0: string, 1: string}|null`


## Recovery

`final readonly class Minn\Runtime\Recovery` · `public/minn/src/Minn/Runtime/Recovery.php`

Recovery from a fatal in someone else's code. When a plugin or theme
kills a request, the file it died in names it, the engine records it as
paused, and the next request loads without it: the site comes back on
its own instead of staying down until a human reads the log.

The paused list is stored where WordPress stores it, in the same shape
(`paused_plugins` keyed by plugin file, `paused_themes` by stylesheet,
each holding type, file, line and message), so a site that ejects back
to WordPress finds the pause it left with.

- const `PLUGINS_OPTION` = `'paused_plugins'`
- const `THEMES_OPTION` = `'paused_themes'`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```

### `blame(string $file): ?array`

The extension a file belongs to: a plugin as its `folder/file.php`
(or bare file for a single-file plugin), a theme as its slug.
Anything outside the plugin and theme folders belongs to nobody, and
is never paused: a fatal in the engine or in core is not a plugin's
fault and pausing something would not fix it.

- `@return array{kind: 'plugin'|'theme', name: string}|null`

### `pause(array $blamed, array $error): bool`

Records an extension as paused. Returns false when it was already
paused, so a caller can tell a fresh failure from a repeat and only
notify once.

- `@param array{kind: string, name: string} $blamed`
- `@param array{type: int, file: string, line: int, message: string} $error`

### `pausedPlugins(): array`

- `@return array<string, array<string, mixed>>`

### `pausedThemes(): array`

- `@return array<string, array<string, mixed>>`

### `resume(string $kind, string $name): bool`

Lets an extension load again. Returns false when it was not paused.

### `resumeAll(): int`

Lets everything load again. Returns how many were released.


## Refusal

`final readonly class Minn\Runtime\Refusal` · `public/minn/src/Minn/Runtime/Refusal.php`

A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error.

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

```php
__construct(string $engineDir)
```

- readonly `array $queryVars`
- readonly `array $publicQueryVars`

### `postTypes(): array`

- `@return array<string, array<string, mixed>>`

### `postType(string $name): ?array`

### `taxonomies(): array`

- `@return array<string, array<string, mixed>>`

### `taxonomy(string $name): ?array`

### `statuses(): array`

- `@return array<string, array<string, mixed>>`

### `status(string $name): ?array`

### `registerPostType(string $name, array $args): array`

- `@param array<string, mixed> $args`

### `unregisterPostType(string $name): bool`

### `addSupport(string $type, string $feature, array $args): void`

Support declared before the type registers waits aside (the reference
keeps features apart from the type objects, so declaring one does not
make the type exist) and merges in when register_post_type arrives.

### `removeSupport(string $type, string $feature): void`

### `supports(string $type): array`

- `@return array<string, mixed> the features a type supports, registered or declared ahead`

### `registerTaxonomy(string $name, array $objectTypes, array $args): array`

- `@param list<string> $objectTypes @param array<string, mixed> $args`

### `unregisterTaxonomy(string $name): bool`

### `addObjectType(string $taxonomy, string $type): bool`

### `removeObjectType(string $taxonomy, string $type): bool`

### `registerStatus(string $name, array $args): array`

- `@param array<string, mixed> $args`


## Runtime

`final class Minn\Runtime\Runtime` · `public/minn/src/Minn/Runtime/Runtime.php`

The WordPress runtime the engine offers plugin code: the procedural
facade under minn/wp-api/ plus the services it delegates to. One per
request; the facade reaches it through these statics.

```php
__construct(Minn\Db $db, Minn\Content\Site $site, ?Minn\Http\Request $request, Minn\Content\Reader $reader, Minn\Auth\Capabilities $capabilities, string $engineDir, string $absPath, string $version, bool $isAdmin = false)
```

- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `?Minn\Http\Request $request`
- readonly `Minn\Content\Reader $reader`
- readonly `Minn\Auth\Capabilities $capabilities`
- readonly `string $engineDir`
- readonly `string $absPath`
- readonly `string $version`
- readonly `bool $isAdmin`

### static `boot(self $runtime): self`

Makes this request's runtime the one the facade sees and defines the facade.

### static `current(): self`

### static `booted(): bool`

### static `hooks(): Minn\Runtime\Hooks`

### static `options(): Minn\Runtime\Options`

### static `capture(string $action, array $args = array ( )): string`

Runs an action and returns what it printed. Plugin callbacks may open
output buffers of their own during the action (a page post-processor
started in wp_head) or close one they think is theirs (the same plugin
in wp_footer); a sentinel buffer under the capture keeps the output
either way: extra buffers are flushed through their handlers into the
capture, and a capture closed early lands in the sentinel.

### static `cache(): Minn\Runtime\ObjectCache`

### static `shortcodes(): Minn\Runtime\Shortcodes`

### static `scriptModules(): Minn\Runtime\ScriptModules`

### static `interactivity(): Minn\Runtime\Interactivity`

### static `blockTemplates(): Minn\Runtime\BlockTemplates`

### static `registry(): Minn\Runtime\Registry`

### static `postQuery(): Minn\Runtime\PostQuery`

### `get(string $key, mixed $default = NULL): mixed`

### `set(string $key, mixed $value): void`

### `isSecure(): bool`

### `contentDir(): string`

### static `loadFacade(string $engineDir): void`

Defines the facade functions once; safe to call again.

### static `reset(): void`

Fresh per-request state, for suites.


## ScriptModules

`final class Minn\Runtime\ScriptModules` · `public/minn/src/Minn/Runtime/ScriptModules.php`

The script modules registry: registrations with typed dependencies, the
queue, and the four things a page prints from it (the import map, module
preloads, the module tags split head/footer, and per-module JSON data).
Behaviour pinned by the script-modules probe fixture.

```php
__construct(Closure $url, Closure $data)
```
- `@param Closure(string, string|false|null): string $url turns a src and version into the printed URL`
- `@param Closure(string, array<string, mixed>): array<string, mixed> $data applies the module data filter`

### `register(string $id, string $src, array $deps, string|false|null $version, array $args): void`

- `@param list<string|array{id: string, import?: string}> $deps`

### `enqueue(string $id, string $src, array $deps, string|false|null $version, array $args): void`

### `dequeue(string $id): void`

### `deregister(string $id): void`

### `setFetchpriority(string $id, string $priority): bool`

### `setInFooter(string $id, bool $inFooter): bool`

### `queue(): array`

- `@return list<string>`

### `registered(string $id): ?array`

- `@return array{src: string, version: string|false|null, dependencies: list<array{id: string, import: string}>, in_footer: bool, fetchpriority: string}|null`

### `printImportMap(): string`

### `printPreloads(): string`

### `printHead(): string`

### `printFooter(): string`

### `printData(): string`

### `printA11y(): string`


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

### static `dir(string $contentDir): string`

### static `installed(string $contentDir): bool`

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


## Shortcodes

`final class Minn\Runtime\Shortcodes` · `public/minn/src/Minn/Runtime/Shortcodes.php`

The shortcode registry plugin code fills with add_shortcode, and the
expansion do_shortcode performs: [tag attrs], [tag attrs/], [tag]…[/tag]
(the first closing tag wins; content is not expanded again), [[tag]] as
the literal, unregistered tags left as written.

### `add(string $tag, callable $callback): void`

### `remove(string $tag): void`

### `removeAll(): void`

### `has(string $tag): bool`

### `all(): array`

- `@return array<string, callable> every registered tag and its handler, to restore after a narrowed run`

### `restore(array $tags): void`

- `@param array<string, callable> $tags`

### `names(): array`

- `@return list<string>`

### `pattern(?array $tags = NULL): ?string`

### `apply(string $content): string`

### `strip(string $content): string`

### static `parse(string $text): array`

- `@return array<int|string, string> named attributes; bare words and quoted values keyed by position`


## SymbolGap

`final readonly class Minn\Runtime\SymbolGap` · `public/minn/src/Minn/Runtime/SymbolGap.php`

The part of the reference's interface the runtime does not answer: names in
data/api-names.json that no facade file defines. It is the whole input the
symbol gate needs, so it can be exported to JSON and carried to a machine
that holds plugin source but no engine, which is how the catalogue-wide
compatibility scan runs.

- readonly `array $functions`
- readonly `array $classes`

### static `ofLoadedFacade(string $engineDir): self`

Reads the reference's interface and subtracts everything the loaded facade
defines. Recomputed on every call because a plugin may define a name as it
loads; only the file read is cached.

### static `fromFile(string $path): self`

### `lacksFunction(string $name): bool`

### `lacksClass(string $name): bool`

### `json(): string`


## Symbols

`final class Minn\Runtime\Symbols` · `public/minn/src/Minn/Runtime/Symbols.php`

A static read of what a plugin's PHP calls: global functions and classes
it uses but does not itself declare, minus the ones it guards with
function_exists() or class_exists(). Checked against what the runtime
provides, this decides whether a plugin loads at all. The read is cached
in the minn_runtime_symbols option keyed by the plugin folder's newest
modification time.

### static `missing(string $dir, Minn\Runtime\Options $options): array`

- `@return array{functions: list<string>, classes: list<string>, files: int, truncated: bool}`

### static `missingAgainst(string $dir, Minn\Runtime\SymbolGap $gap): array`

The same read against an exported gap instead of the running engine, so a
folder can be judged with no database, no options, and no facade loaded.

- `@return array{functions: list<string>, classes: list<string>, files: int, truncated: bool}`


## TagEditor

`final class Minn\Runtime\TagEditor` · `public/minn/src/Minn/Runtime/TagEditor.php`

Edits one start tag's attributes in place the way the reference's tag
processor does: a replaced value keeps its position, a new attribute goes
right after the tag name (after any earlier insertion), and a removal takes
only the attribute's own text.

```php
__construct(string $tag, array $attrs)
```
- `@param list<array{name: string, value: ?string, start: int, end: int}> $attrs offsets relative to the tag`

### `get(string $name): ?string`

### `has(string $name): bool`

### `set(string $name, string|bool $value): void`

true sets a bare boolean attribute.

### `remove(string $name): void`

### `toggleClass(string $class, bool $on): void`

### `setStyle(string $property, ?string $value): void`

### `html(): string`


## TermQuery

`final readonly class Minn\Runtime\TermQuery` · `public/minn/src/Minn/Runtime/TermQuery.php`

Term reads in the shapes plugin code asks for: get_terms() arguments to
rows, the tree filters (child_of, exclude_tree), the fields shapes, and
the single-term lookups. Behaviour pinned by contracts/fixtures/api/content.json.

- const `DEFAULTS` = `array (   'taxonomy' => NULL,   'object_ids' => NULL,   'orderby' => 'name',   'order' => 'ASC',   'hide_empty' => true,   'include' =>    array (   ),   'exclude' =>    array (   ),   'exclude_tree' =>    array (   ),   'number' => '',   'offset' => '',   'fields' => 'all',   'count' => false,   'name' => '',   'slug' => '',   'term_taxonomy_id' => '',   'hierarchical' => true,   'search' => '',   'name__like' => '',   'description__like' => '',   'pad_counts' => false,   'get' => '',   'child_of' => 0,   'parent' => '',   'childless' => false,   'cache_domain' => 'core',   'update_term_meta_cache' => true,   'meta_query' => '',   'meta_key' => '',   'meta_value' => '', )`

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

- `@param array{slug?: string, description?: string, parent?: int|string, alias_of?: string} $args`
- `@return array{term_id: int, term_taxonomy_id: int}|Refusal`

### `update(array $current, string $taxonomy, array $args): Minn\Runtime\Refusal|array`

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

### `relate(int $objectId, array $keep, array $old, string $taxonomy): void`

Makes an object's relationships in a taxonomy exactly $keep (or $old
plus $keep when appending), firing the relationship actions the way the
reference does, and recounts.

- `@param list<int> $keep term_taxonomy ids`
- `@param list<int> $old the object's current term_taxonomy ids in the taxonomy`

### `unrelate(int $objectId, array $ttIds, string $taxonomy): bool`

Removes the given relationships; true when any row went. @param list<int> $ttIds

- `@param list<int> $ttIds`


## TreeWalk

`final class Minn\Runtime\TreeWalk` · `public/minn/src/Minn/Runtime/TreeWalk.php`

The Walker contract's traversal: elements keyed by the walker's
db_fields parent/id pair, displayed depth-first through the walker's
four element methods. The facade Walker delegates here; subclasses
override the element methods (or display_element itself) and the
dispatch stays virtual.

### static `walk(object $walker, array $elements, int $maxDepth, array $args): string`

- `@param list<object> $elements`

### static `element(object $walker, mixed $element, array $children, int $maxDepth, int $depth, array $args, string $output): void`

- `@param array<int|string, mixed> $children`


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

```php
__construct(Minn\Db $db)
```

### `run(array $args): array`

- `@param array<string, mixed> $args`
- `@return array{rows: list<array>, total: int}`

