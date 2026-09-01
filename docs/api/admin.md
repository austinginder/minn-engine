# `Minn\Admin`

the minn-admin/v1 namespace and serving the Minn Admin app

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`ActivityChart`](#activitychart) | final readonly class | 61 | The overview's activity chart: published posts and pages plus every |
| [`ActivityFeed`](#activityfeed) | final readonly class | 210 | What happened lately, as the overview and the bell tell it: the caller's |
| [`AdminTypes`](#admintypes) | final class | 82 | Admin-facing type facts (viewable, labels, supports, the edit gate) live |
| [`App`](#app) | final readonly class | 95 | The Minn Admin app on disk: the symlinked dev copy the engine serves the |
| [`AppController`](#appcontroller) | final readonly class | 129 | Serves Minn Admin from the engine: the path-routed shell (every |
| [`Appearance`](#appearance) | final readonly class | 74 | A person's Minn Admin appearance: the colour scheme and its custom |
| [`BootPayload`](#bootpayload) | final readonly class | 228 | The window.MINN boot payload, assembled from the engine: the keys app.js |
| [`CoreStatus`](#corestatus) | final readonly class | 23 | The installed version comes from the update_core transient's |
| [`Dashboard`](#dashboard) | final readonly class | 289 | The overview payload: stat cards, the activity chart, and the recent |
| [`Diagnostics`](#diagnostics) | final readonly class | 400 | The System view's facts about this install: the engine, PHP, the |
| [`Format`](#format) | final class | 51 | The dashboard's number, size, age, and title formatting. |
| [`HiddenIntegrations`](#hiddenintegrations) | final readonly class | 89 | What a person hid from their own Minn Admin: the app's per-user map |
| [`LanguageChoices`](#languagechoices) | final class | 40 | The language picker's markup. English always leads the list and carries the |
| [`LanguageController`](#languagecontroller) | final readonly class | 114 | Languages: what is installed, what a person reads in, what the site defaults to. |
| [`Logs`](#logs) | final readonly class | 138 | The log files the System view can read and clear: the debug log the |
| [`ManageController`](#managecontroller) | final readonly class | 377 | The Manage half of minn-admin/v1: the Structure view (post types, |
| [`Notifications`](#notifications) | final readonly class | 201 | The bell feed: pending and recent comments, translation and core update |
| [`Packages`](#packages) | final readonly class | 392 | Putting themes and extensions on disk. Themes come from wordpress.org |
| [`PackagesController`](#packagescontroller) | final readonly class | 122 | Adding and removing themes and extensions from the Extensions view. |
| [`RenderController`](#rendercontroller) | final readonly class | 65 | The editor's island previews: block markup rendered by the same |
| [`SessionsController`](#sessionscontroller) | final readonly class | 71 | A person's sign-in sessions, read from the same session_tokens store |
| [`SystemController`](#systemcontroller) | final readonly class | 85 | The System view: diagnostics, the scheduled-post list, autoloaded options, and the logs. |
| [`Translations`](#translations) | final readonly class | 220 | Languages for the admin. A person's locale is their `locale` user meta, |
| [`Updates`](#updates) | final class | 297 | Update offers from wordpress.org for the site's plugins and themes: the |
| [`UpdatesController`](#updatescontroller) | final readonly class | 122 | The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists. |
| [`V1Controller`](#v1controller) | final readonly class | 324 | The minn-admin/v1 namespace: the dashboard burst, the editor helpers, |

## ActivityChart

`final readonly class Minn\Admin\ActivityChart` · `public/minn/src/Minn/Admin/ActivityChart.php`

The overview's activity chart: published posts and pages plus every
comment, counted per bar. A window over 45 days is drawn in weeks,
anything shorter in days; each bar carries the (from, to] GMT bounds
the drill-down asks for.

Used by: `Minn\Admin\Dashboard`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site)
```


### `bars(int $days, int $now): array`

- `@return list<array{label: string, value: int, from: string, to: string}>`

### static `bucket(array $dates, int $days, int $now, int $offset): array`

GMT stamps into bars, oldest first. Labels are site-local: the day
for daily bars, "Week of" the bar's first day for weekly ones.

- `@param list<string> $dates "Y-m-d H:i:s" in GMT`
- `@return list<array{label: string, value: int, from: string, to: string}>`


## ActivityFeed

`final readonly class Minn\Admin\ActivityFeed` · `public/minn/src/Minn/Admin/ActivityFeed.php`

What happened lately, as the overview and the bell tell it: the caller's
own recent posts and the latest comments, and the events behind one
chart bar. Also the visibility rule every comment row is put through.

Used by: `Minn\Admin\Dashboard`, `Minn\Admin\Notifications`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities)
```


### `recent(int $userId, int $now, int $offset): array`

The overview's four most recent items, times already worded ("2 hours ago").

Oracle-caught quirk: the reference's recent-activity query runs with a
multi-type post_type plus perm=editable, and on that combination it
restricts EVERY status to post_author = caller, admins included,
published posts included. Authorless posts appear for nobody. The
engine reproduces the observed behavior, not the intent.

- `@return list<array>`

### `between(int $userId, string $from, string $to, int $now): array`

The events behind one chart bar, (from, to] GMT, newest first. Post
rows decode the RAW stored title; comment rows decode the texturized
one (the reference's asymmetry, kept).

- `@return list<array>`

### `commentVisible(int $userId, int $postId): bool`

May this caller see a comment row that names its post?

### `postTitle(int $postId): string`

### `displayName(int $userId): string`

Internals: `ownPosts()` (private, line 96), `latestComments()` (private, line 141), `publishedBetween()` (private, line 169), `commentsBetween()` (private, line 194)


## AdminTypes

`final class Minn\Admin\AdminTypes` · `public/minn/src/Minn/Admin/AdminTypes.php`

Admin-facing type facts (viewable, labels, supports, the edit gate) live
beside, not inside, the wp/v2 registry, so the types route's payload
stays byte-faithful.

Used by: `Minn\Admin\V1Controller`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Rest\Api`

```php
__construct(Minn\Rest\Types $types, Minn\Auth\Capabilities $capabilities)
```


### `extra(): array`

### `section(int $userId): array`

The boot-status types section: edit-visible types for this user, slimmed.

### `restBaseOf(string $slug): string`

### `singularOf(string $slug): string`

### `editable(): array`

The viewable types a person edits in the app: slug => rest base. @return array<string, string>

- `@return array<string, string>`

### `commentsEnabled(): bool`

Whether a UI post type still supports comments.


## App

`final readonly class Minn\Admin\App` · `public/minn/src/Minn/Admin/App.php`

The Minn Admin app on disk: the symlinked dev copy the engine serves the
shell and assets from. Minn Admin is MIT, so reading its files is fine.

- const `ASSET_TYPES` = `array (   'css' => 'text/css',   'js' => 'application/javascript',   'woff2' => 'font/woff2',   'woff' => 'font/woff',   'svg' => 'image/svg+xml',   'png' => 'image/png',   'json' => 'application/json', )`

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\ManageController`, `Minn\Admin\Translations`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Rest\Services`

```php
__construct(string $dir)
```


### static `switchedOff(string $contentDir, Minn\Content\Site $site): bool`

Whether the site has switched Minn Admin off: its plugin folder is in
wp-content/plugins (the record the reference keeps) and active_plugins
does not name it. A site that carries only the engine's bundle has no
such record and keeps its admin.

### `dir(): string`

### `installed(): bool`

### `version(): string`

### `file(string $relative): ?string`

The absolute path of a readable file inside the app, or null.

### `assetVersion(string $relative): string`

A self-busting asset version: app version plus file mtime.

### `asset(string $relative): ?array`

The absolute path and content type of an asset inside the app, or
null for anything missing or outside it.

- `@return array{0: string, 1: string}|null`


## AppController

`final readonly class Minn\Admin\AppController` · `public/minn/src/Minn/Admin/AppController.php`

Serves Minn Admin from the engine: the path-routed shell (every
sub-path renders the same page), the app's assets, and the one
admin-ajax action app.js uses to refresh its nonce.

Used by: `Minn\Engine`

```php
__construct(Minn\Admin\App $app, Minn\Admin\BootPayload $payload, Minn\Auth\Authenticator $authenticator, Minn\Auth\Capabilities $capabilities, Minn\Front\Permalinks $permalinks, string $engineVersion, bool $off = false)
```


### `shell(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin`

Route: `GET /minn-admin/{rest*}`

Requires a signed-in user who can edit content; otherwise the login form.

### `asset(Minn\Http\Request $request, string $path): Minn\Http\Response`

Route: `GET /minn/admin/assets/{path*}`

### `ajax(Minn\Http\Request $request): Minn\Http\Response`

Route: `* /wp-admin/admin-ajax.php`

Internals: `offPage()` (private, line 61), `render()` (private, line 105)


## Appearance

`final readonly class Minn\Admin\Appearance` · `public/minn/src/Minn/Admin/Appearance.php`

A person's Minn Admin appearance: the colour scheme and its custom
token maps, stored where the app stores them (user meta
minn_admin_appearance, a serialized array) so the choice survives an
eject. The two WordPress-era switches, "Minn is the default admin" and
"Minn admin bar on the site", are always on here: there is no other
admin and no other bar, so the engine reports them true and ignores
writes to them.

- const `META` = `'minn_admin_appearance'`
- const `SCHEMES` = `array (   0 => 'minn',   1 => 'ocean',   2 => 'forest',   3 => 'amber',   4 => 'rose',   5 => 'coral',   6 => 'teal',   7 => 'slate',   8 => 'dusk', )`
- const `SLOTS` = `array (   0 => 'bg',   1 => 'bg2',   2 => 'panel',   3 => 'panel2',   4 => 'hover',   5 => 'border',   6 => 'border2',   7 => 'text',   8 => 'text2',   9 => 'text3',   10 => 'accent',   11 => 'accent2',   12 => 'accentFg', )` — Scheme slots in the order the app lists them.
- const `BASE` = `array (   'dark' =>    array (     'bg' => '#0b0b0d',     'bg2' => '#101013',     'panel' => '#151518',     'panel2' => '#1b1b1f',     'hover' => '#202027',     'border' => '#242429',     'border2' => '#31313a',     'text' => '#ececed',     'text2' => '#9d9da7',     'text3' => '#63636d',     'accent' => '#6e62f5',     'accent2' => '#8a80f8',     'accentFg' => '#ffffff',   ),   'light' =>    array (     'bg' => '#f6f6f7',     'bg2' => '#ffffff',     'panel' => '#ffffff',     'panel2' => '#f4f4f6',     'hover' => '#eeeef1',     'border' => '#e7e7ea',     'border2' => '#dadade',     'text' => '#1a1a1f',     'text2' => '#5e5e69',     'text3' => '#9696a0',     'accent' => '#6a5ef2',     'accent2' => '#5a4ef0',     'accentFg' => '#ffffff',   ), )` — The app's own Minn tokens, the fill for incomplete custom maps.

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\ManageController`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Users $users)
```


### `read(int $userId): array`

- `@return array{scheme: string, custom: array, defaultAdmin: bool, frontBar: bool}`

### `save(int $userId, array $raw): array`

Merges the given keys over the stored map and writes it back; returns the normalised result.

### static `normalise(array $raw): array`

### static `hex(string $value): string`

#rgb or #rrggbb to lowercase #rrggbb, or '' for anything else.


## BootPayload

`final readonly class Minn\Admin\BootPayload` · `public/minn/src/Minn/Admin/BootPayload.php`

The window.MINN boot payload, assembled from the engine: the keys app.js
needs to boot and drive the wp/v2 surface, with capabilities from the
engine's own model.

- const `PLUGIN_KEYS_WITHHELD` = `array (   0 => 'notices', )` — Boot keys the plugin computes that point the app at wp-admin pages the engine does not serve.

Used by: `Minn\Admin\AppController`, `Minn\Engine`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Auth\Capabilities $capabilities, Minn\Admin\App $app, string $engineVersion, Minn\Admin\Appearance $appearance, Minn\Admin\HiddenIntegrations $hidden, Minn\Content\Posts $posts, bool $blockTheme = false, ?Minn\Admin\Translations $translations = NULL)
```


### `siteName(): string`

### `build(Minn\Auth\Authenticated $session): array`

Internals: `userSlice()` (private, line 89), `siteSlice()` (private, line 106), `caps()` (private, line 133), `pluginPayload()` (private, line 180), `standHomeQuery()` (private, line 202), `siteIcon()` (private, line 216), `adapterSlices()` (private, line 228)


## CoreStatus

`final readonly class Minn\Admin\CoreStatus` · `public/minn/src/Minn/Admin/CoreStatus.php`

The installed version comes from the update_core transient's
version_checked (the database's own record of what last phoned home);
the engine never reads WordPress code files and never phones home
itself. dbUpgrade is false by definition: there is no newer core code
on disk for the database to lag behind.

Used by: `Minn\Admin\V1Controller`, `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site)
```


### `data(): array`


## Dashboard

`final readonly class Minn\Admin\Dashboard` · `public/minn/src/Minn/Admin/Dashboard.php`

The overview payload: stat cards, the activity chart, and the recent
activity feed, plus the per-bar activity drill-down.

Used by: `Minn\Admin\V1Controller`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities, Minn\Admin\ActivityChart $chart, Minn\Admin\ActivityFeed $feed, string $uploadsDir)
```


### `overview(int $userId, int $days): array`

The stat cards, the metric catalog and layout, the chart, and the feed.

### `metricLayout(int $userId, array $catalog, array $stats): array`

Which cards show: the user's saved picks over the site default over
the built-in layout, unknown keys dropped and slots refilled.

- `@param list<array> $catalog`
- `@param list<array> $stats`
- `@return array{keys: list<string>, defaults: list<string>, custom: bool}`

### static `cleanMetricKeys(mixed $keys): array`

At most six unique sanitize_key metric ids; anything else drops.

### `activity(int $userId, string $from, string $to): array`

The events behind one chart bar, (from, to] GMT.

Internals: `postsCard()` (private, line 73), `pagesCard()` (private, line 88), `usersCard()` (private, line 93), `commentsCard()` (private, line 99), `mediaCard()` (private, line 113), `metricCatalog()` (private, line 130), `overlayMetricKeys()` (private, line 222), `statusCounts()` (private, line 264), `commentCounts()` (private, line 275), `uploadsSize()` (private, line 289)


## Diagnostics

`final readonly class Minn\Admin\Diagnostics` · `public/minn/src/Minn/Admin/Diagnostics.php`

The System view's facts about this install: the engine, PHP, the
database, and the server, with the health checks a site owner acts on.
Every number is read live; nothing is cached or fetched from outside.

- const `AUTOLOAD_VALUES` = `array (   0 => 'yes',   1 => 'on',   2 => 'auto',   3 => 'auto-on', )`

Used by: `Minn\Admin\SystemController`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions, Minn\Admin\Logs $logs, string $engineVersion, string $webroot)
```


### `payload(Minn\Http\Request $request): array`

### `config(): array`

The wp-config debug constants as they stand; the engine never rewrites the file.

### `cron(): array`

Every scheduled post as a one-off event, soonest first.

### `autoload(): array`

Internals: `checks()` (private, line 67), `engineGroup()` (private, line 104), `phpGroup()` (private, line 125), `serverGroup()` (private, line 148), `opcacheOn()` (private, line 166), `autoloadSummary()` (private, line 248), `cronSummary()` (private, line 263), `futurePosts()` (private, line 281), `databaseGroup()` (private, line 289), `extensionsManifest()` (private, line 327), `activeThemeLabel()` (private, line 353), `check()` (private, line 368), `rows()` (private, line 374), `bytes()` (private, line 383), `offsetLabel()` (private, line 398), `relative()` (private, line 406)


## Format

`final class Minn\Admin\Format` · `public/minn/src/Minn/Admin/Format.php`

The dashboard's number, size, age, and title formatting.

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Dashboard`, `Minn\Admin\Notifications`

### static `number(int|float $value, int $decimals = 0): string`

number_format_i18n for the default locale: comma thousands.

### static `size(int $bytes): string`

size_format with one decimal: 1024-step units, comma thousands.

### static `humanTimeDiff(int $from, ?int $to = NULL): string`

Humanized age, pinned by an oracle capture table: each unit rounds,
floors at 1, and hands off at the next unit boundary.

### static `plainTitle(string $raw): string`

A post title as the feed shows it: texturized, entities decoded, tags gone.


## HiddenIntegrations

`final readonly class Minn\Admin\HiddenIntegrations` · `public/minn/src/Minn/Admin/HiddenIntegrations.php`

What a person hid from their own Minn Admin: the app's per-user map
(user meta minn_admin_hidden_integrations, "kind:id" => hidden-at) read
and written in the app's own shape so a choice made under WordPress
survives the swap and vice versa. The engine registers the core views
as hideable; plugin surfaces, editor panels, design sources, and slash
namespaces have no registry here, so hiding one is refused as
unregistered and a stored hide of one is simply not listed.

- const `META` = `'minn_admin_hidden_integrations'`
- const `CORE` = `array (   'content' =>    array (     0 => 'Content',     1 => 'edit_posts',   ),   'media' =>    array (     0 => 'Media',     1 => 'upload_files',   ),   'comments' =>    array (     0 => 'Comments',     1 => 'moderate_comments',   ),   'orders' =>    array (     0 => 'Orders',     1 => 'edit_shop_orders',   ),   'subscriptions' =>    array (     0 => 'Subscriptions',     1 => 'edit_shop_orders',   ),   'products' =>    array (     0 => 'Products',     1 => 'edit_products',   ),   'coupons' =>    array (     0 => 'Coupons',     1 => 'edit_shop_coupons',   ),   'customers' =>    array (     0 => 'Customers',     1 => 'list_users',   ),   'users' =>    array (     0 => 'Users',     1 => 'list_users',   ),   'terms' =>    array (     0 => 'Terms',     1 => 'manage_categories',   ),   'menus' =>    array (     0 => 'Menus',     1 => 'edit_theme_options',   ),   'widgets' =>    array (     0 => 'Widgets',     1 => 'edit_theme_options',   ),   'posttypes' =>    array (     0 => 'Structure',     1 => 'manage_options',   ),   'extensions' =>    array (     0 => 'Extensions',     1 => 'activate_plugins',   ),   'database' =>    array (     0 => 'Database',     1 => 'manage_options',   ),   'system' =>    array (     0 => 'System',     1 => 'manage_options',   ),   'settings' =>    array (     0 => 'Settings',     1 => 'manage_options',   ), )` — Core view id => [label, the capability that shows the view].
- const `CAP` = `100` — Newest hides kept when the map is capped.

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\ManageController`, `Minn\Engine`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities)
```


### static `sanitize(string $id): string`

The id as the app sends it: lower-case, only word characters, colon, and dash.

### `map(int $userId): array`

- `@return array<string, int> id => hidden-at`

### `listFor(int $userId): array`

The restore list: every hidden core view the person can still see. @return list<array{id: string, kind: string, label: string, sub: string}>

- `@return list<array{id: string, kind: string, label: string, sub: string}>`

### `hide(int $userId, string $id): bool`

False when the id names nothing this person could hide.

### `unhide(int $userId, string $id): void`


## LanguageChoices

`final class Minn\Admin\LanguageChoices` · `public/minn/src/Minn/Admin/LanguageChoices.php`

The language picker's markup. English always leads the list and carries the
empty value, because "no locale" and "American English" are the same choice.
When the picker also offers what could be downloaded, the two halves split
into named groups; otherwise every option sits in one flat list.

The attribute order is not tidy and is not ours to tidy: the English option
carries `data-installed` before `selected`, an installed translation carries
it after, and themes and plugins have been matching on that markup for
years.

- const `ENGLISH` = `'English (United States)'`

### static `dropdown(string $name, string $id, array $installed, array $available, string $selected, bool $offerAvailable): string`

- `@param list<string> $installed locale codes the site already holds`
- `@param array<string, array{language: string, native_name: string, iso: array<int|string, string>}> $available every translation the directory offers, keyed by locale`

Internals: `option()` (private, line 50)


## LanguageController

`final readonly class Minn\Admin\LanguageController` · `public/minn/src/Minn/Admin/LanguageController.php`

Languages: what is installed, what a person reads in, what the site defaults to.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Translations $translations, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Auth\Capabilities $capabilities, Minn\Rest\Caller $caller)
```


### `languages(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/languages`

### `bootLocale(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/boot-locale`

The slice of the boot payload a language switch repaints from.

### `mine(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/me/language`

### `user(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/language`

### `site(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/site/language`

Internals: `setUserLocale()` (private, line 88), `ensure()` (private, line 100), `reply()` (private, line 115), `requireFloor()` (private, line 120), `requireCap()` (private, line 127)


## Logs

`final readonly class Minn\Admin\Logs` · `public/minn/src/Minn/Admin/Logs.php`

The log files the System view can read and clear: the debug log the
engine's failure handler writes, and PHP's own error log when it is a
separate file inside the site. Anything outside the site is named but
never read; it may be another tenant's.

- const `TAIL_BYTES` = `262144`

Used by: `Minn\Admin\Diagnostics`, `Minn\Admin\SystemController`, `Minn\Rest\Services`

```php
__construct(string $webroot)
```


### `sources(): array`

- `@return array<string, array{label: string, group: string, path: string}>`

### `listPayload(): array`

- `@return list<array> the sources that exist, debug first`

### `read(string $id): array`

### `clear(string $id): void`

### `debugLogPath(): string`

### `tail(string $path): array`

The last TAIL_BYTES of a file, the partial first line dropped.

### `owned(string $path): bool`

True when the path resolves inside the webroot.

### static `human(int $bytes): string`


## ManageController

`final readonly class Minn\Admin\ManageController` · `public/minn/src/Minn/Admin/ManageController.php`

The Manage half of minn-admin/v1: the Structure view (post types,
taxonomies, the terms switcher), the Extensions view's theme
inventory, the update slots (always empty: the engine has no update
channel to poll), the bundled changelog and guide, and the person's
appearance. Everything answers from the registries, the theme folders,
and the app bundle on disk; nothing calls out.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Rest\Types $types, Minn\Rest\Taxonomies $taxonomies, Minn\Extension\Loader $extensions, Minn\Content\Inventory $inventory, Minn\Front\Permalinks $permalinks, Minn\Admin\App $app, Minn\Admin\Appearance $appearance, Minn\Admin\HiddenIntegrations $hiddenIntegrations, Minn\Admin\Updates $updates, Minn\Rest\Caller $caller, string $contentDir)
```


### `termTaxonomies(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/term-taxonomies`

### `postTypes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/post-types`

The Structure view's post types: core and site-declared, with their live counts.

### `taxonomies(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/taxonomies`

### `themes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/themes`

### `activateTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/activate`

### `translations(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/translations`

### `changelog(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/changelog`

### `guide(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/guide`

### `myAppearance(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/me/appearance`

### `saveMyAppearance(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/me/appearance`

### `userAppearance(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/users/{id:\d+}/appearance`

### `saveUserAppearance(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/appearance`

### `hidden(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/users/{id:\d+}/hidden`

The target user's restore list, for the user edit page.

### `unhideForUser(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/integrations/unhide`

An administrator restores something another person hid; hiding stays that person's own choice.

### `hide(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/integrations/hide`

### `unhide(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/integrations/unhide`

Internals: `integrationId()` (private, line 288), `integrationState()` (private, line 301), `bundled()` (private, line 316), `appearanceBody()` (private, line 322), `editableUser()` (private, line 328), `themeText()` (private, line 339), `themeFolders()` (private, line 345), `screenshot()` (private, line 361), `publicTypes()` (private, line 372), `termCount()` (private, line 377), `postCount()` (private, line 382), `reply()` (private, line 390), `requireFloor()` (private, line 395), `requireCap()` (private, line 402)


## Notifications

`final readonly class Minn\Admin\Notifications` · `public/minn/src/Minn/Admin/Notifications.php`

The bell feed: pending and recent comments, translation and core update
offers, the core auto-update notice, and new registrations. Plugin and
theme update rows need an extension inventory the engine does not have
(a recorded gap); on the reference database those sections are empty,
so parity holds by construction.

Used by: `Minn\Admin\V1Controller`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities, Minn\Admin\ActivityFeed $feed, Minn\Admin\Updates $updates)
```


### `items(int $userId): array`

### `markRead(int $userId, string $id): void`

An id marks one item read; an empty id marks everything read.

Internals: `commentItems()` (private, line 64), `updateItems()` (private, line 92), `updateItem()` (private, line 123), `coreItems()` (private, line 134), `registrationItems()` (private, line 168), `commentItem()` (private, line 195), `translationCount()` (private, line 211)


## Packages

`final readonly class Minn\Admin\Packages` · `public/minn/src/Minn/Admin/Packages.php`

Putting themes and extensions on disk. Themes come from wordpress.org
(block themes render on the engine) or an uploaded zip; extensions come
from an uploaded zip or a URL, and must carry a minn.json: a WordPress
plugin would install but never run, so it is refused with the reason.
Every archive is unpacked through one guarded routine: exactly one
top-level folder, no absolute or dotted paths, the folder's identity
checked before it is moved into place.

- const `WPORG_THEMES` = `'https://api.wordpress.org/themes/info/1.2/'`
- const `WPORG_PLUGINS` = `'https://api.wordpress.org/plugins/info/1.2/'`
- const `INFO_OPTION` = `'minn_plugin_info'`
- const `INFO_TTL` = `43200`

Used by: `Minn\Admin\PackagesController`, `Minn\Admin\Updates`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Engine`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```


### `searchThemes(string $query): array`

wordpress.org theme search, or the popular list for an empty query. @return list<array>

- `@return list<array>`

### `searchPlugins(string $query, int $page): array`

wordpress.org plugin search: twelve per page with icons, short
descriptions, install counts, and ratings, plus which results are
already installed (by folder). @return array{plugins: list<array>, page: int, pages: int, total: int}

- `@return array{plugins: list<array>, page: int, pages: int, total: int}`

### `pluginInfo(string $slug): array`

The slim card for one directory plugin, cached twelve hours per slug.

### `installPlugin(string $slug, bool $overwrite = false, string $version = ''): string`

Installs a wordpress.org plugin by slug; returns its folder.

### `directoryPlugin(string $slug): ?array`

One wordpress.org plugin record, or null when the slug is unknown.

- `@return array<string, mixed>|null`

### `queryThemes(string $search, int $page, int $perPage): array`

A page of wordpress.org themes for `wp theme search`.

- `@return array{items: list<array<string, mixed>>, total: int}`

### `queryPlugins(string $search, int $page, int $perPage): array`

A page of wordpress.org plugins for `wp plugin search`.

- `@return array{items: list<array<string, mixed>>, total: int}`

### `directoryTheme(string $slug): ?array`

One wordpress.org theme record, or null when the slug is unknown.

- `@return array<string, mixed>|null`

### `installTheme(string $slug, bool $overwrite = false, string $version = ''): string`

Installs a wordpress.org theme by slug; returns its stylesheet folder.

### `unpack(string $zip, string $kind, bool $overwrite): array`

Unpacks an uploaded or downloaded archive into wp-content/themes or
wp-content/plugins. @return array{folder: string, name: string, version: string, kind: string}

- `@return array{folder: string, name: string, version: string, kind: string}`

### `remove(string $kind, string $folder): void`

Removes a theme or plugin folder that is not in use.

### `fetch(string $url): string`

Internals: `plain()` (private, line 179), `identify()` (private, line 367), `removeTree()` (private, line 405)


## PackagesController

`final readonly class Minn\Admin\PackagesController` · `public/minn/src/Minn/Admin/PackagesController.php`

Adding and removing themes and extensions from the Extensions view.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Packages $packages, Minn\Content\Site $site, Minn\Rest\Caller $caller)
```


### `searchThemes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/themes/search`

### `installTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/install`

### `uploadTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/upload`

### `deleteTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/delete`

### `uploadPlugin(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/upload`

A plugin zip: a WordPress plugin or a Minn extension.

### `installFromUrl(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/install-url`

A zip URL, or a GitHub owner/repo whose latest release carries a zip asset.

### `searchPlugins(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugins/search`

### `pluginInfo(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugins/info`

Internals: `uploaded()` (private, line 115), `requireCap()` (private, line 127), `reply()` (private, line 135)


## RenderController

`final readonly class Minn\Admin\RenderController` · `public/minn/src/Minn/Admin/RenderController.php`

The editor's island previews: block markup rendered by the same
renderer the public site uses, with the stylesheets that site loads
(the engine's block stylesheet, the theme's, and the theme.json rules)
so a preview looks like the page will.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\Caller $caller, string $themesDir)
```


### `render(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/render-blocks`

### `editorStyles(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/editor-styles`

The stylesheets previews are scoped under: the engine's own, the theme's, and theme.json inline.

Internals: `styles()` (private, line 66), `requireFloor()` (private, line 82), `reply()` (private, line 90)


## SessionsController

`final readonly class Minn\Admin\SessionsController` · `public/minn/src/Minn/Admin/SessionsController.php`

A person's sign-in sessions, read from the same session_tokens store
the login endpoint writes. Verifiers are the store's keys (the sha256
of each cookie token), so the app can name one without ever seeing
the token. Expired rows are skipped, not garbage-collected here.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Users $users, Minn\Auth\Sessions $sessions, Minn\Rest\Caller $caller)
```


### `list(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/users/{id:\d+}/sessions`

### `destroyAll(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/users/{id:\d+}/sessions`

Signs the person out everywhere; a caller acting on themselves keeps the session they are using.

### `destroy(Minn\Http\Request $request, string $id, string $verifier): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/users/{id:\d+}/sessions/{verifier:[a-f0-9]{40,64}}`

Internals: `target()` (private, line 82)


## SystemController

`final readonly class Minn\Admin\SystemController` · `public/minn/src/Minn/Admin/SystemController.php`

The System view: diagnostics, the scheduled-post list, autoloaded options, and the logs.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Diagnostics $diagnostics, Minn\Admin\Logs $logs, Minn\Rest\Caller $caller)
```


### `system(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system`

### `cron(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/cron`

### `autoload(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/autoload`

### `config(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/system/config`

The engine never rewrites wp-config.php; the file is the site's, edited by hand.

### `logs(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/logs`

### `log(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.-]+}`

### `clearLog(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.-]+}`

### `debugLog(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/debug-log`

### `clearDebugLog(Minn\Http\Request $request): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/system/debug-log`

Internals: `requireOwner()` (private, line 89), `reply()` (private, line 97)


## Translations

`final readonly class Minn\Admin\Translations` · `public/minn/src/Minn/Admin/Translations.php`

Languages for the admin. A person's locale is their `locale` user meta,
else the site's WPLANG, else en_US, exactly as the reference resolves it.
The app translates itself from a map of its source strings, read from the
JED catalogs Minn Admin's language packs install under
wp-content/languages/plugins (the bundle's own languages folder is the
fallback). Installing a pack fetches the release asset the bundle's
manifest names for that locale, checks its hash, and unpacks only that
locale's files, so an eject leaves the files WordPress would have written.

- const `RTL` = `array (   0 => 'ar',   1 => 'ary',   2 => 'azb',   3 => 'ckb',   4 => 'dv',   5 => 'fa_AF',   6 => 'fa_IR',   7 => 'haz',   8 => 'he_IL',   9 => 'ps',   10 => 'skr',   11 => 'ug_CN',   12 => 'ur', )`

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\LanguageController`, `Minn\Engine`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Users $users, Minn\Content\Site $site, Minn\Admin\App $app, string $contentDir)
```


### `localeOf(int $userId): string`

The locale a person reads the admin in.

### `siteLocale(): string`

### static `isRtl(string $locale): bool`

### `catalog(string $locale): array`

The app's translation map for a locale: source string => translation,
or the plural forms as a list. Later folders win, so the installed
pack lands over the bundle's fallback.

- `@return array{0: array<string, string|list<string>>, 1: string} map and the Plural-Forms rule`

### `isInstalled(string $locale): bool`

Whether a locale has any files on this site: a core pack or Minn Admin's own.

### `installed(): array`

- `@return list<array{0: string, 1: string}> the site-default row, en_US, then every installed locale`

### `payload(int $forUser, bool $canInstall): array`

The languages route's payload; `current` is the raw meta of the user asked about.

### `install(string $locale): bool`

Fetches and unpacks Minn Admin's language pack for a locale from the
release the bundle's manifest names. True when files were written;
false when the manifest offers no pack for the locale.

Internals: `catalogFiles()` (private, line 183), `installedCodes()` (private, line 195), `names()` (private, line 216), `download()` (private, line 228)


## Updates

`final class Minn\Admin\Updates` · `public/minn/src/Minn/Admin/Updates.php`

Update offers from wordpress.org for the site's plugins and themes: the
directory's update-check endpoints asked with the installed headers, the
answer kept in the minn_updates option (JSON) for twelve hours, and the
offers applied by downloading the release archive through the one
package unpacker. A plugin or theme the directory does not know keeps
its folder untouched and is never offered anything. The per-item
auto-update lists are the site's own auto_update_plugins and
auto_update_themes options, in the shape the app already reads.

- const `OPTION` = `'minn_updates'`
- const `TTL` = `43200`
- const `PLUGINS_API` = `'https://api.wordpress.org/plugins/update-check/1.1/'`
- const `THEMES_API` = `'https://api.wordpress.org/themes/update-check/1.1/'`
- const `PACKAGE_HOST` = `'https://downloads.wordpress.org/'`

Used by: `Minn\Admin\ManageController`, `Minn\Admin\Notifications`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cron\Cron`, `Minn\Engine`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Admin\Packages $packages, string $contentDir, string $home, string $wpVersion)
```


### `state(bool $fresh = false): array`

The stored answer, refreshed when older than the TTL or absent.

### `check(): array`

Asks the directory now and stores the answer.

### `pluginOffers(): array`

Plugin file => offered version, only where the installed version is older. @return array<string, string>

- `@return array<string, string>`

### `themeOffers(): array`

Stylesheet => offered version. @return array<string, string>

- `@return array<string, string>`

### `pluginMeta(): array`

Plugin file => slug, icon, directory URL, for every plugin the directory knows. @return array<string, array{slug: string, icon: string, url: string}>

- `@return array<string, array{slug: string, icon: string, url: string}>`

### `themeOnDirectory(string $stylesheet): bool`

### `updatePlugin(string $file): string`

Applies the offer for one plugin file; returns the installed version afterwards.

### `updateTheme(string $stylesheet): string`

### `auto(string $type): array`

The auto-update list for plugins or themes, trimmed to what is installed. @return list<string>

- `@return list<string>`

### `setAuto(string $type, string $asset, bool $enabled): array`

- `@return list<string> the list after the change`

### `runAuto(): array`

Applies every offer on the auto-update lists; returns what was updated. @return list<string>

- `@return list<string>`

### `pluginVersions(): array`

Plugin file => installed version. @return array<string, string>

- `@return array<string, string>`

### `pluginNames(): array`

Plugin file => Plugin Name. @return array<string, string>

- `@return array<string, string>`

### `themeHeaders(): array`

Stylesheet => style.css headers. @return array<string, array<string, string>>

- `@return array<string, array<string, string>>`

Internals: `install()` (private, line 259), `consume()` (private, line 271), `post()` (private, line 283), `map()` (private, line 307), `safeUrl()` (private, line 315)


## UpdatesController

`final readonly class Minn\Admin\UpdatesController` · `public/minn/src/Minn/Admin/UpdatesController.php`

The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Updates $updates, Minn\Rest\Caller $caller)
```


### `pluginUpdates(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugin-updates`

### `pluginMeta(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugin-meta`

### `check(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/check-updates`

### `updatePlugin(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/update`

### `updateAll(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/update-all`

### `updateTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/update`

### `autoUpdates(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/auto-updates`

Internals: `requireCap()` (private, line 126), `reply()` (private, line 134)


## V1Controller

`final readonly class Minn\Admin\V1Controller` · `public/minn/src/Minn/Admin/V1Controller.php`

The minn-admin/v1 namespace: the dashboard burst, the editor helpers,
and the small Settings-view routes. Every route sits behind the same
capability floor (edit_posts) the plugin declares.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Content\PostWriter $writer, Minn\Front\Permalinks $permalinks, Minn\Admin\Dashboard $dashboard, Minn\Admin\Notifications $notifications, Minn\Admin\CoreStatus $core, Minn\Admin\AdminTypes $types, Minn\Rest\Caller $caller, Minn\Content\Users $users)
```


### `overview(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/overview`

### `overviewActivity(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/overview/activity`

### `notifications(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/notifications`

### `notificationsRead(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/notifications/read`

Body {id} marks one read; {} marks all read.

### `core(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/core`

### `bootStatus(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/boot-status`

The app's one-round-trip boot burst. Absent sections are the
contract's own fallback: the client loads a missing section
standalone. The engine serves what it can honestly answer and omits
the plugin-inventory sections it has no installation for.

### `lock(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/posts/{id:\d+}/lock`

### `unlock(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/posts/{id:\d+}/unlock`

### `templates(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/templates`

No theme, no page templates: an honest empty set.

### `patterns(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/patterns`

Theme patterns are GPL theme content the engine does not carry.

### `siteLogo(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/site-logo`

### `setOverviewMetrics(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/overview/metrics`

### `setOverviewMetricDefaults(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/overview/metric-defaults`

### `permalinks(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/permalinks`

### `spam(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/spam`

### `mediaMonths(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/media/months`

Internals: `metricKeysFrom()` (private, line 192), `storedMetricLayout()` (private, line 209), `logoUrl()` (private, line 224), `reply()` (private, line 282), `requireFloor()` (private, line 288), `requireCap()` (private, line 295), `days()` (private, line 303), `window()` (private, line 320), `parameterError()` (private, line 346)

