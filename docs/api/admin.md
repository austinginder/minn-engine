# `Minn\Admin`

the minn-admin/v1 namespace and serving the Minn Admin app

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`ActivityChart`](#activitychart) | final readonly class | 65 | The overview's activity chart: published posts and pages plus every |
| [`ActivityFeed`](#activityfeed) | final readonly class | 212 | What happened lately, as the overview and the bell tell it: the caller's |
| [`AdminTypes`](#admintypes) | final class | 85 | Admin-facing type facts (viewable, labels, supports, the edit gate) live |
| [`App`](#app) | final readonly class | 98 | The Minn Admin app on disk: the symlinked dev copy the engine serves the |
| [`AppController`](#appcontroller) | final readonly class | 118 | Serves Minn Admin from the engine: the path-routed shell (every |
| [`Appearance`](#appearance) | final readonly class | 82 | A person's Minn Admin appearance: the colour scheme and its custom |
| [`BootPayload`](#bootpayload) | final readonly class | 234 | The window.MINN boot payload, assembled from the engine: the keys app.js |
| [`BundleController`](#bundlecontroller) | final readonly class | 36 | What the app bundle carries: the changelog, the user guide, and the |
| [`Dashboard`](#dashboard) | final readonly class | 273 | The overview payload: stat cards, the activity chart, and the recent |
| [`EditorController`](#editorcontroller) | final readonly class | 45 | The editor's helpers in minn-admin/v1: the edit lock, and the template |
| [`Format`](#format) | final class | 51 | The dashboard's number, size, age, and title formatting. |
| [`HiddenIntegrations`](#hiddenintegrations) | final readonly class | 94 | What a person hid from their own Minn Admin: the app's per-user map |
| [`LanguageChoices`](#languagechoices) | final class | 42 | The language picker's markup. English always leads the list and carries the |
| [`LanguageController`](#languagecontroller) | final readonly class | 99 | Languages: what is installed, what a person reads in, what the site defaults to. |
| [`Notifications`](#notifications) | final readonly class | 202 | The bell feed: pending and recent comments, translation and core update |
| [`OverviewController`](#overviewcontroller) | final readonly class | 141 | The Overview of minn-admin/v1: the payload, the drill-down behind one |
| [`PackagesController`](#packagescontroller) | final readonly class | 108 | Adding and removing themes and extensions from the Extensions view. |
| [`PostListMarkup`](#postlistmarkup) | final class | 39 | The markup a post list writes beside each post, as the reference writes |
| [`PreferencesController`](#preferencescontroller) | final readonly class | 115 | A person's own settings in minn-admin/v1: their appearance, the views |
| [`RenderController`](#rendercontroller) | final readonly class | 52 | The editor's island previews: block markup rendered by the same |
| [`SessionsController`](#sessionscontroller) | final readonly class | 72 | A person's sign-in sessions, read from the same session_tokens store |
| [`SiteController`](#sitecontroller) | final readonly class | 87 | The small Settings-view routes of minn-admin/v1: the site logo, the |
| [`StructureController`](#structurecontroller) | final readonly class | 131 | The Structure view of minn-admin/v1: post types, taxonomies, and the |
| [`SystemController`](#systemcontroller) | final readonly class | 73 | The System view: diagnostics, the scheduled-post list, autoloaded options, and the logs. |
| [`ThemesController`](#themescontroller) | final readonly class | 95 | The theme inventory of minn-admin/v1: every theme on disk with its |
| [`Translations`](#translations) | final readonly class | 236 | Languages for the admin. A person's locale is their `locale` user meta, |
| [`UpdatesController`](#updatescontroller) | final readonly class | 117 | The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists. |
| [`UploadsSize`](#uploadssize) | final readonly class | 93 | How much the uploads folder holds, as Minn Admin 0.43 works it out and |
| [`V1Controller`](#v1controller) | final readonly class | 59 | The boot burst of minn-admin/v1: the bell feed and its read marker, the |

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

The bars for a window of days, counted from the database.

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

A post's stored title, empty when the post is gone.

### `displayName(int $userId): string`

A user's display name, empty when the user is gone.

Internals: `ownPosts()` (private, line 98), `latestComments()` (private, line 143), `publishedBetween()` (private, line 171), `commentsBetween()` (private, line 196)


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

The types beyond post and page the app may show, with their labels.

### `section(int $userId): array`

The boot-status types section: edit-visible types for this user, slimmed.

### `restBaseOf(string $slug): string`

The rest_base of a type slug.

### `singularOf(string $slug): string`

A type's singular label.

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

Used by: `Minn\Admin\AppController`, `Minn\Admin\BootPayload`, `Minn\Admin\BundleController`, `Minn\Admin\Translations`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Rest\Services`

```php
__construct(string $dir)
```


### static `switchedOff(string $contentDir, Minn\Content\Site $site): bool`

Whether the site has switched Minn Admin off: its plugin folder is in
wp-content/plugins (the record the reference keeps) and active_plugins
does not name it. A site that carries only the engine's bundle has no
such record and keeps its admin.

### `dir(): string`

Where the bundle sits on disk.

### `installed(): bool`

Whether the bundle is on disk.

### `version(): string`

The bundle's version from its plugin header.

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
sub-path renders the same page) and the app's assets. The nonce refresh
app.js asks admin-ajax.php for is the reference's own rest-nonce action
(Runtime\AjaxController).

Used by: `Minn\Engine`

```php
__construct(Minn\Admin\App $app, Minn\Admin\BootPayload $payload, Minn\Auth\Authenticator $authenticator, Minn\Auth\Capabilities $capabilities, Minn\Front\Permalinks $permalinks, string $engineVersion, bool $off = false)
```


### `shell(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin (public)`

Route: `GET /minn-admin/{rest*} (public)`

Requires a signed-in user who can edit content; otherwise the login form.

### `asset(Minn\Http\Request $request, string $path): Minn\Http\Response`

Route: `GET /minn/admin/assets/{path*} (public)`

One file of the app bundle, with its content type and caching headers.

Internals: `offPage()` (private, line 63), `render()` (private, line 96)


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

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\PreferencesController`, `Minn\Engine`, `Minn\Front\AdminBar`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Users $users)
```


### `read(int $userId): array`

A user's saved appearance, normalised.

- `@return array{scheme: string, custom: array, defaultAdmin: bool, frontBar: bool, font: string}`

### `save(int $userId, array $raw): array`

Merges the given keys over the stored map and writes it back; returns the normalised result.

### static `normalise(array $raw): array`

The appearance with every field valid and every missing one defaulted.

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
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Auth\Capabilities $capabilities, Minn\Admin\App $app, string $engineVersion, Minn\Admin\Appearance $appearance, Minn\Admin\HiddenIntegrations $hidden, Minn\Content\SiteIcon $icon, bool $blockTheme = false, ?Minn\Admin\Translations $translations = NULL)
```


### `siteName(): string`

The site's name, or Site when it has none.

### `build(Minn\Auth\Authenticated $session): array`

The window.MINN payload the app boots from, for one signed-in session.

Internals: `userSlice()` (private, line 91), `siteSlice()` (private, line 108), `commerce()` (private, line 134), `caps()` (private, line 146), `pluginPayload()` (private, line 194), `standHomeQuery()` (private, line 216), `adapterSlices()` (private, line 234)


## BundleController

`final readonly class Minn\Admin\BundleController` · `public/minn/src/Minn/Admin/BundleController.php`

What the app bundle carries: the changelog, the user guide, and the
translation offers (none: the engine has no update channel to poll).

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\App $app, Minn\Rest\Caller $caller)
```


### `translations(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/translations (floor edit_posts)`

The translation offers; none on the engine.

### `changelog(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/changelog (floor edit_posts)`

The app's bundled changelog.

### `guide(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/guide (floor edit_posts)`

The app's bundled user guide.

Internals: `bundled()` (private, line 50)


## Dashboard

`final readonly class Minn\Admin\Dashboard` · `public/minn/src/Minn/Admin/Dashboard.php`

The overview payload: stat cards, the activity chart, and the recent
activity feed, plus the per-bar activity drill-down.

Used by: `Minn\Admin\OverviewController`, `Minn\Rest\Services`

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

Internals: `postsCard()` (private, line 68), `pagesCard()` (private, line 85), `usersCard()` (private, line 90), `commentsCard()` (private, line 96), `mediaCard()` (private, line 112), `metricCatalog()` (private, line 130), `overlayMetricKeys()` (private, line 224), `statusCounts()` (private, line 266), `commentCounts()` (private, line 277)


## EditorController

`final readonly class Minn\Admin\EditorController` · `public/minn/src/Minn/Admin/EditorController.php`

The editor's helpers in minn-admin/v1: the edit lock, and the template
and pattern lists (honestly empty: the engine carries no theme content).

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\PostWriter $writer, Minn\Rest\Caller $caller)
```


### `lock(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/posts/{id:\d+}/lock (floor edit_posts)`

Takes the edit lock on a post.

### `unlock(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/posts/{id:\d+}/unlock (floor edit_posts)`

Releases the edit lock on a post.

### `templates(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/templates (floor edit_posts)`

No theme, no page templates: an honest empty set.

### `patterns(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/patterns (floor edit_posts)`

Theme patterns are GPL theme content the engine does not carry.


## Format

`final class Minn\Admin\Format` · `public/minn/src/Minn/Admin/Format.php`

The dashboard's number, size, age, and title formatting.

Used by: `Minn\Admin\ActivityFeed`, `Minn\Admin\Dashboard`, `Minn\Admin\Notifications`, `Minn\Admin\UploadsSize`

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

Used by: `Minn\Admin\BootPayload`, `Minn\Admin\PreferencesController`, `Minn\Engine`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities)
```


### static `sanitize(string $id): string`

The id as the app sends it: lower-case, only word characters, colon, and dash.

### `map(int $userId): array`

The views a user has hidden, by id.

- `@return array<string, int> id => hidden-at`

### `listFor(int $userId): array`

The restore list: every hidden core view the person can still see. @return list<array{id: string, kind: string, label: string, sub: string}>

- `@return list<array{id: string, kind: string, label: string, sub: string}>`

### `hide(int $userId, string $id): bool`

False when the id names nothing this person could hide.

### `unhide(int $userId, string $id): void`

Shows a hidden view again.


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

The language select's HTML, installed languages first.

- `@param list<string> $installed locale codes the site already holds`
- `@param array<string, array{language: string, native_name: string, iso: array<int|string, string>}> $available every translation the directory offers, keyed by locale`

Internals: `option()` (private, line 52)


## LanguageController

`final readonly class Minn\Admin\LanguageController` · `public/minn/src/Minn/Admin/LanguageController.php`

Languages: what is installed, what a person reads in, what the site defaults to.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Translations $translations, Minn\Content\Users $users, Minn\Content\Site $site, Minn\Auth\Capabilities $capabilities, Minn\Rest\Caller $caller)
```


### `languages(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/languages (floor edit_posts)`

The languages a user may pick, installed and available.

### `bootLocale(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/boot-locale (floor edit_posts)`

The slice of the boot payload a language switch repaints from.

### `mine(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/me/language (floor edit_posts)`

Sets the caller's own locale.

### `user(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/language (floor edit_posts)`

Sets another user's locale.

### `site(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/site/language (floor edit_posts)`

Sets the site's locale.

Internals: `setUserLocale()` (private, line 92), `ensure()` (private, line 104)


## Notifications

`final readonly class Minn\Admin\Notifications` · `public/minn/src/Minn/Admin/Notifications.php`

The bell feed: pending and recent comments, translation and core update
offers, the core auto-update notice, and new registrations. Plugin and
theme update rows need an extension inventory the engine does not have
(a recorded gap); on the reference database those sections are empty,
so parity holds by construction.

Used by: `Minn\Admin\V1Controller`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Users $users, Minn\Auth\Capabilities $capabilities, Minn\Admin\ActivityFeed $feed, Minn\Ops\Updates $updates)
```


### `items(int $userId): array`

The bell feed for a user, newest first, grouped and marked read or unread.

### `markRead(int $userId, string $id): void`

An id marks one item read; an empty id marks everything read.

Internals: `commentItems()` (private, line 66), `updateItems()` (private, line 94), `updateItem()` (private, line 125), `coreItems()` (private, line 136), `registrationItems()` (private, line 170), `commentItem()` (private, line 197), `translationCount()` (private, line 213)


## OverviewController

`final readonly class Minn\Admin\OverviewController` · `public/minn/src/Minn/Admin/OverviewController.php`

The Overview of minn-admin/v1: the payload, the drill-down behind one
chart bar, and the pick of cards, the person's own and the site's default.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Admin\Dashboard $dashboard, Minn\Content\Users $users, Minn\Rest\Caller $caller)
```


### `overview(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/overview (floor edit_posts)`

The overview payload.

### `overviewActivity(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/overview/activity (floor edit_posts)`

The events behind one chart bar.

### `setOverviewMetrics(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/overview/metrics (floor edit_posts)`

Saves the caller's pick of overview cards.

### `setOverviewMetricDefaults(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/overview/metric-defaults (floor edit_posts)`

Saves the site's default overview cards.

Internals: `metricKeysFrom()` (private, line 83), `storedMetricLayout()` (private, line 100), `days()` (private, line 115), `window()` (private, line 132), `parameterError()` (private, line 158)


## PackagesController

`final readonly class Minn\Admin\PackagesController` · `public/minn/src/Minn/Admin/PackagesController.php`

Adding and removing themes and extensions from the Extensions view.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Ops\Packages $packages, Minn\Content\Site $site, Minn\Rest\Caller $caller)
```


### `searchThemes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/themes/search (floor edit_posts + install_themes)`

Searches wordpress.org themes.

### `installTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/install (floor edit_posts + install_themes)`

Installs a theme from wordpress.org by slug.

### `uploadTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/upload (floor edit_posts + install_themes)`

Installs a theme from an uploaded zip.

### `deleteTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/delete (floor edit_posts + delete_themes)`

Deletes an inactive theme.

### `uploadPlugin(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/upload (floor edit_posts + install_plugins)`

A plugin zip: a WordPress plugin or a Minn extension.

### `installFromUrl(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/install-url (floor edit_posts + install_plugins)`

A zip URL, or a GitHub owner/repo whose latest release carries a zip asset.

### `searchPlugins(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugins/search (floor edit_posts + install_plugins)`

Searches wordpress.org plugins.

### `pluginInfo(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugins/info (floor edit_posts + install_plugins)`

One wordpress.org plugin's details.

Internals: `uploaded()` (private, line 115)


## PostListMarkup

`final class Minn\Admin\PostListMarkup` · `public/minn/src/Minn/Admin/PostListMarkup.php`

The markup a post list writes beside each post, as the reference writes
it (probe placeholders-admin): the states after a title (an em dash,
then each state in its span, a comma inside every span but the last),
and the hidden fields quick edit reads, one div each, in its order.

### static `states(array $states): string`

The states after a post's title, or nothing when it has none.

- `@param array<string|int, string> $states`

### static `inline(int $id, array $post, string $more): string`

The quick edit fields: the escaped title and slug, the author, the two
discussion statuses, the status, the date in its six parts and the
password, then what the caller adds (parent, template, order, terms,
sticky, format) unbroken, and the closing tag.

- `@param array{title: string, name: string, author: int, comments: string, pings: string, status: string, date: string, password: string} $post`


## PreferencesController

`final readonly class Minn\Admin\PreferencesController` · `public/minn/src/Minn/Admin/PreferencesController.php`

A person's own settings in minn-admin/v1: their appearance, the views
they hid, and an administrator's reach into another person's, for the
user edit page.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Admin\Appearance $appearance, Minn\Admin\HiddenIntegrations $hiddenIntegrations, Minn\Rest\Caller $caller)
```


### `myAppearance(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/me/appearance (floor edit_posts)`

The caller's appearance.

### `saveMyAppearance(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/me/appearance (floor edit_posts)`

Saves the caller's appearance.

### `userAppearance(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/users/{id:\d+}/appearance (cap edit_user on {id})`

A user's appearance, for one who may edit them.

### `saveUserAppearance(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/appearance (cap edit_user on {id})`

Saves a user's appearance.

### `hidden(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/users/{id:\d+}/hidden (cap edit_user on {id})`

The target user's restore list, for the user edit page.

### `unhideForUser(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `POST /minn-admin/v1/users/{id:\d+}/integrations/unhide (cap edit_user on {id})`

An administrator restores something another person hid; hiding stays that person's own choice.

### `hide(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/integrations/hide (floor edit_posts)`

Hides a view for the caller.

### `unhide(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/integrations/unhide (floor edit_posts)`

Shows a view again for the caller.

Internals: `integrationId()` (private, line 99), `integrationState()` (private, line 112), `appearanceBody()` (private, line 126), `editableUser()` (private, line 132)


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

Route: `POST /minn-admin/v1/render-blocks (floor edit_posts)`

Renders blocks for the editor's preview.

### `editorStyles(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/editor-styles (floor edit_posts)`

The stylesheets previews are scoped under: the engine's own, the theme's, and theme.json inline.

Internals: `styles()` (private, line 66)


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

Route: `GET /minn-admin/v1/users/{id:\d+}/sessions (cap edit_user on {id})`

A user's sessions, the caller's own marked.

### `destroyAll(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/users/{id:\d+}/sessions (cap edit_user on {id})`

Signs the person out everywhere; a caller acting on themselves keeps the session they are using.

### `destroy(Minn\Http\Request $request, string $id, string $verifier): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/users/{id:\d+}/sessions/{verifier:[a-f0-9]{40,64}} (cap edit_user on {id})`

Signs one session out.

Internals: `target()` (private, line 86)


## SiteController

`final readonly class Minn\Admin\SiteController` · `public/minn/src/Minn/Admin/SiteController.php`

The small Settings-view routes of minn-admin/v1: the site logo, the
permalink structure, the spam queue, and the months the library spans.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Content\Posts $posts, Minn\Front\Permalinks $permalinks, Minn\Rest\Caller $caller)
```


### `siteLogo(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/site-logo (floor edit_posts)`

The site logo attachment.

### `permalinks(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/permalinks (floor edit_posts)`

The permalink structure.

### `spam(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/spam (floor edit_posts)`

The spam settings.

### `mediaMonths(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/media/months (floor edit_posts)`

The months the library has uploads in.

Internals: `logoUrl()` (private, line 56)


## StructureController

`final readonly class Minn\Admin\StructureController` · `public/minn/src/Minn/Admin/StructureController.php`

The Structure view of minn-admin/v1: post types, taxonomies, and the
terms switcher, each with its live counts, answered from the registries.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Rest\Types $types, Minn\Rest\Taxonomies $taxonomies, Minn\Rest\Caller $caller)
```


### `termTaxonomies(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/term-taxonomies (floor edit_posts)`

The taxonomies of each public type, for the Structure view.

### `postTypes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/post-types (floor edit_posts)`

The Structure view's post types: core and site-declared, with their live counts.

### `taxonomies(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/taxonomies (floor edit_posts)`

Every taxonomy with its counts.

Internals: `publicTypes()` (private, line 136), `termCount()` (private, line 141), `postCount()` (private, line 146)


## SystemController

`final readonly class Minn\Admin\SystemController` · `public/minn/src/Minn/Admin/SystemController.php`

The System view: diagnostics, the scheduled-post list, autoloaded options, and the logs.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Ops\Diagnostics $diagnostics, Minn\Ops\Logs $logs, Minn\Rest\Caller $caller)
```


### `system(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system (cap manage_options)`

The System view's payload.

### `cron(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/cron (cap manage_options)`

The scheduled posts.

### `autoload(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/autoload (cap manage_options)`

The autoloaded options.

### `config(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/system/config (cap manage_options)`

The engine never rewrites wp-config.php; the file is the site's, edited by hand.

### `logs(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/logs (cap manage_options)`

The logs list.

### `log(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.\-]+} (cap manage_options)`

One log's tail.

### `clearLog(Minn\Http\Request $request, string $id): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.\-]+} (cap manage_options)`

Empties one log.

### `debugLog(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/system/debug-log (cap manage_options)`

The debug log's tail.

### `clearDebugLog(Minn\Http\Request $request): Minn\Http\Response`

Route: `DELETE /minn-admin/v1/system/debug-log (cap manage_options)`

Empties the debug log.


## ThemesController

`final readonly class Minn\Admin\ThemesController` · `public/minn/src/Minn/Admin/ThemesController.php`

The theme inventory of minn-admin/v1: every theme on disk with its
headers, screenshot, and update offer, and the switch of the active one.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Ops\Updates $updates, Minn\Rest\Caller $caller, string $contentDir)
```


### `themes(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/themes (floor edit_posts)`

Every theme on disk with the active one marked.

### `activateTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/activate (floor edit_posts)`

Switches the active theme.

Internals: `themeText()` (private, line 88), `themeFolders()` (private, line 94), `screenshot()` (private, line 110)


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

The site's locale from WPLANG, en_US by default.

### static `isRtl(string $locale): bool`

Whether a locale reads right to left.

### `catalog(string $locale): array`

The app's translation map for a locale: source string => translation,
or the plural forms as a list. Later folders win, so the installed
pack lands over the bundle's fallback.

- `@return array{0: array<string, string|list<string>>, 1: string} map and the Plural-Forms rule`

### `isInstalled(string $locale): bool`

Whether a locale has any files on this site: a core pack or Minn Admin's own.

### `installed(): array`

The locales with a pack on disk, the two defaults first.

- `@return list<array{0: string, 1: string}> the site-default row, en_US, then every installed locale`

### `payload(int $forUser): array`

The languages route's payload; `current` is the raw meta of the user asked about.

### `readOnlyPayload(int $forUser): array`

The same payload for a caller who may switch languages but not install one: no available list.

### `install(string $locale): bool`

Fetches and unpacks Minn Admin's language pack for a locale from the
release the bundle's manifest names. True when files were written;
false when the manifest offers no pack for the locale.

Internals: `languages()` (private, line 118), `catalogFiles()` (private, line 203), `installedCodes()` (private, line 215), `names()` (private, line 236), `download()` (private, line 248)


## UpdatesController

`final readonly class Minn\Admin\UpdatesController` · `public/minn/src/Minn/Admin/UpdatesController.php`

The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Ops\Updates $updates, Minn\Rest\Caller $caller)
```


### `pluginUpdates(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugin-updates (signed in)`

The plugin update offers.

### `pluginMeta(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/plugin-meta (signed in)`

Icons and details for the installed plugins.

### `check(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/check-updates (signed in)`

Asks wordpress.org again, now.

### `updatePlugin(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/update (signed in)`

Updates one plugin.

### `updateAll(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/plugins/update-all (signed in)`

Updates every plugin with an offer.

### `updateTheme(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/themes/update (signed in)`

Updates one theme.

### `autoUpdates(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/auto-updates`

Turns auto-updates on or off for one asset.


## UploadsSize

`final readonly class Minn\Admin\UploadsSize` · `public/minn/src/Minn/Admin/UploadsSize.php`

How much the uploads folder holds, as Minn Admin 0.43 works it out and
shares it through the minn_admin_uploads_size transient: the cached
figure in either shape it has had ({bytes, partial}, or a bare count from
before the budget), else a walk only an administrator may start, one at a
time behind a two-minute lock, stopped after four seconds and kept as
partial for an hour instead of twelve.

- const `CACHE` = `'_transient_minn_admin_uploads_size'`
- const `EXPIRES` = `'_transient_timeout_minn_admin_uploads_size'`
- const `LOCK` = `'minn_admin_uploads_size_lock'`
- const `BUDGET` = `4.0`

Used by: `Minn\Admin\Dashboard`

```php
__construct(Minn\Content\Site $site, Minn\Auth\Capabilities $capabilities, string $dir)
```


### `label(int $userId): string`

The Media card's "N used" line, "over N used" for a walk cut short, or "" when the size is not known.

### `measure(int $userId): ?array`

The uploads size, cached or walked now.

- `@return array{bytes: int, partial: bool}|null null when nothing is known and this caller may not find out`

Internals: `cached()` (private, line 62), `lock()` (private, line 77), `walk()` (private, line 88)


## V1Controller

`final readonly class Minn\Admin\V1Controller` · `public/minn/src/Minn/Admin/V1Controller.php`

The boot burst of minn-admin/v1: the bell feed and its read marker, the
core status, and the one-round-trip boot-status the app starts from.
Every route sits behind the same capability floor (edit_posts) the
plugin declares.

Used by: `Minn\Rest\Api`

```php
__construct(Minn\Db $db, Minn\Admin\Notifications $notifications, Minn\Ops\CoreStatus $core, Minn\Admin\AdminTypes $types, Minn\Rest\Caller $caller)
```


### `notifications(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/notifications (floor edit_posts)`

The bell feed.

### `notificationsRead(Minn\Http\Request $request): Minn\Http\Response`

Route: `POST /minn-admin/v1/notifications/read (floor edit_posts)`

Body {id} marks one read; {} marks all read.

### `core(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/core (floor edit_posts)`

The core status.

### `bootStatus(Minn\Http\Request $request): Minn\Http\Response`

Route: `GET /minn-admin/v1/boot-status (floor edit_posts)`

The app's one-round-trip boot burst. Absent sections are the
contract's own fallback: the client loads a missing section
standalone. The engine serves what it can honestly answer and omits
the plugin-inventory sections it has no installation for.

