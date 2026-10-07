# `Minn\Ops`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AutoUpdates`](#autoupdates) | final readonly class | 29 | Whether per-item auto-updates apply to plugins or themes, as the |
| [`CoreStatus`](#corestatus) | final readonly class | 24 | The installed version comes from the update_core transient's |
| [`Diagnostics`](#diagnostics) | final readonly class | 408 | The System view's facts about this install: the engine, PHP, the |
| [`InstalledSoftware`](#installedsoftware) | final readonly class | 59 | What is installed, as the System view lists it: every extension and |
| [`Logs`](#logs) | final readonly class | 150 | The log files the System view can read and clear: the debug log the |
| [`Packages`](#packages) | final readonly class | 489 | Putting themes and extensions on disk. Themes come from wordpress.org |
| [`Unzip`](#unzip) | final readonly class | 101 | An archive unpacked as unzip_file() unpacks it (probe unzip-file): into |
| [`Updates`](#updates) | final class | 396 | Update offers from wordpress.org for the site's plugins and themes: the |

## AutoUpdates

`final readonly class Minn\Ops\AutoUpdates` · `public/minn/src/Minn/Ops/AutoUpdates.php`

Whether per-item auto-updates apply to plugins or themes, as the
reference decides it (contracts/rest/minn-admin-v1.md "Auto-updates"):
never when file mods are off (DISALLOW_FILE_MODS through file_mod_allowed,
context automatic_updater), never when the updater is disabled
(AUTOMATIC_UPDATER_DISABLED through automatic_updater_disabled, which a
plugin may turn back), and then the type's own filter has the last word.
Only plugins and themes can be on.

Used by: `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Cron\Cron`, `Minn\Ops\Updates`

```php
__construct(Closure $filter)
```
- `@param Closure(string, mixed, mixed...): mixed $filter applies a filter, as apply_filters does`


### static `forSite(): self`

The gate as the site's plugins see it: through their filters once the runtime's hooks exist, as configured otherwise.

### `enabledFor(string $type): bool`

Whether per-item auto-updates apply to a type ("plugin" or "theme"; anything else is never on).

Internals: `updaterOff()` (private, line 41)


## CoreStatus

`final readonly class Minn\Ops\CoreStatus` · `public/minn/src/Minn/Ops/CoreStatus.php`

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

The core version and any offer from the update transient.


## Diagnostics

`final readonly class Minn\Ops\Diagnostics` · `public/minn/src/Minn/Ops/Diagnostics.php`

The System view's facts about this install: the engine, PHP, the
database, and the server, with the health checks a site owner acts on.
Every number is read live; nothing is cached or fetched from outside.

- const `AUTOLOAD_VALUES` = `array (   0 => 'yes',   1 => 'on',   2 => 'auto',   3 => 'auto-on', )`

Used by: `Minn\Admin\SystemController`, `Minn\Rest\Services`

```php
__construct(Minn\Db $db, Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, Minn\Ops\InstalledSoftware $software, Minn\Ops\Logs $logs, string $engineVersion, string $webroot)
```


### `payload(Minn\Http\Request $request): array`

The System view: checks, config, logs, and the four info groups.

### `config(): array`

The wp-config debug constants as they stand; the engine never rewrites the file.

### `cron(): array`

The scheduled events: the cron option's hooks and the engine's own scheduled posts, soonest first.

### `autoload(): array`

The autoloaded options: the summary and the largest rows.

Internals: `checks()` (private, line 65), `engineGroup()` (private, line 102), `phpGroup()` (private, line 123), `serverGroup()` (private, line 146), `opcacheOn()` (private, line 164), `cronOptionEvents()` (private, line 232), `humanInterval()` (private, line 255), `autoloadSummary()` (private, line 295), `cronSummary()` (private, line 310), `futurePosts()` (private, line 328), `databaseGroup()` (private, line 336), `check()` (private, line 374), `rows()` (private, line 380), `bytes()` (private, line 389), `offsetLabel()` (private, line 404), `relative()` (private, line 412)


## InstalledSoftware

`final readonly class Minn\Ops\InstalledSoftware` · `public/minn/src/Minn/Ops/InstalledSoftware.php`

What is installed, as the System view lists it: every extension and
WordPress plugin with whether it runs, the mu-plugins, the themes with
their parents, and the active theme's label.

Used by: `Minn\Ops\Diagnostics`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Extension\Loader $extensions, string $webroot)
```


### `activeExtensionCount(): int`

How many Minn extensions are active.

### `manifest(): array`

Plugins, mu-plugins, and themes with their versions and whether each is active.

### `activeThemeLabel(): string`

The active theme's name and version, naming the parent of a child theme; the slug when the headers are missing.


## Logs

`final readonly class Minn\Ops\Logs` · `public/minn/src/Minn/Ops/Logs.php`

The log files the System view can read and clear: the debug log the
engine's failure handler writes, and PHP's own error log when it is a
separate file inside the site. Anything outside the site is named but
never read; it may be another tenant's.

- const `TAIL_BYTES` = `262144`

Used by: `Minn\Admin\SystemController`, `Minn\Ops\Diagnostics`, `Minn\Rest\Services`

```php
__construct(string $webroot)
```


### `sources(): array`

Every log the engine knows about, with its path and group.

- `@return array<string, array{label: string, group: string, path: string}>`

### `listPayload(): array`

The logs list as the System view shows it.

- `@return list<array> the sources that exist, debug first`

### `read(string $id): array`

One log's tail, by id.

### `clear(string $id): void`

Empties one log.

### `debugLogPath(): string`

Where debug.log lives, from WP_DEBUG_LOG or the default.

### `tail(string $path): array`

The last TAIL_BYTES of a file, the partial first line dropped.

### `owned(string $path): bool`

True when the path resolves inside the webroot.

### static `human(int $bytes): string`

A byte count in KB, MB, or GB.


## Packages

`final readonly class Minn\Ops\Packages` · `public/minn/src/Minn/Ops/Packages.php`

Putting themes and extensions on disk. Themes come from wordpress.org
(block themes render on the engine) or an uploaded zip; extensions come
from an uploaded zip or a URL, and must carry a minn.json: a WordPress
plugin would install but never run, so it is refused with the reason.
Every archive is unpacked through one guarded routine: exactly one
top-level folder that is a plain name (never "." or ".."), no absolute
or dotted paths, no symbolic links, bounded entry count and size, the
folder's identity checked and its destination proven to be a direct
child of the kind's directory before it is moved into place. Removal
proves the same containment before anything is deleted.

- const `MAX_ARCHIVE` = `536870912` — The largest archive fetched or unpacked, in bytes.
- const `MAX_ENTRIES` = `20000`
- const `WPORG_THEMES` = `'https://api.wordpress.org/themes/info/1.2/'`
- const `WPORG_PLUGINS` = `'https://api.wordpress.org/plugins/info/1.2/'`
- const `INFO_OPTION` = `'minn_plugin_info'`
- const `INFO_TTL` = `43200`

Used by: `Minn\Admin\PackagesController`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PackageInstaller`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Cron\Cron`, `Minn\Ops\Updates`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

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

### `installPlugin(string $slug, string $version = ''): string`

Installs a wordpress.org plugin by slug; returns its folder.

### `replacePlugin(string $slug, string $version = ''): string`

Installs a wordpress.org plugin over the folder already there.

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

### `installTheme(string $slug, string $version = ''): string`

Installs a wordpress.org theme by slug; returns its stylesheet folder.

### `replaceTheme(string $slug, string $version = ''): string`

Installs a wordpress.org theme over the folder already there.

### `unpack(string $zip, string $kind): array`

Unpacks an uploaded or downloaded archive into wp-content/themes or
wp-content/plugins. @return array{folder: string, name: string, version: string, kind: string}

- `@return array{folder: string, name: string, version: string, kind: string}`

### `unpackReplacing(string $zip, string $kind): array`

Unpacks a zip over a folder already there, replacing it whole.

### `remove(string $kind, string $folder): void`

Removes a theme or plugin folder that is not in use.

### `fetch(string $url, string ...$hostPrefixes): string`

A package over https, every redirect hop included, refusing anything
else; when host prefixes are given, every hop must start with one.

Internals: `pluginPackage()` (private, line 158), `plain()` (private, line 199), `themePackage()` (private, line 293), `place()` (private, line 342), `isFolderName()` (private, line 426), `contained()` (private, line 432), `isSymlinkEntry()` (private, line 443), `identify()` (private, line 458), `describe()` (private, line 472), `removeTree()` (private, line 507)


## Unzip

`final readonly class Minn\Ops\Unzip` · `public/minn/src/Minn/Ops/Unzip.php`

An archive unpacked as unzip_file() unpacks it (probe unzip-file): into
the destination through the filesystem the site set up, every folder
made first (the destination and its missing parents included), resource-fork entries
(__MACOSX/) and entries whose names climb out or are absolute left out;
refused when the disk cannot take twice the unpacked size and a little
more. pre_unzip_file may answer first, and unzip_file is handed the
result, each with the folders to make and the space needed. An archive
the reader cannot open is refused in the words of the library the
reference falls back to.

```php
__construct(object $filesystem, Closure $filter, int $dirMode, int $fileMode)
```
- `@param Closure(string, mixed...): mixed $filter applies a filter, as apply_filters does`


### `into(string $file, string $to): mixed`

The archive's files under the destination: true, a refusal, or what pre_unzip_file answered instead.

Internals: `read()` (private, line 70), `safe()` (private, line 93), `write()` (private, line 99)


## Updates

`final class Minn\Ops\Updates` · `public/minn/src/Minn/Ops/Updates.php`

Update offers from wordpress.org for the site's plugins and themes: the
directory's update-check endpoints asked with the installed headers, the
answer kept in the minn_updates option (JSON) for twelve hours, and the
offers applied by downloading the release archive through the one
package unpacker. A plugin or theme the directory does not know keeps
its folder untouched and is never offered anything. The per-item
auto-update lists are the site's own auto_update_plugins and
auto_update_themes options, in the shape the app already reads.

The directory is not the only source. A plugin that hosts itself answers
for its own version through the update transient's filter, which is where
WordPress reads it too, so Runtime\PluginUpdates asks the runtime the same
question and its answer is merged in. Applying such an offer goes through
the publisher: a package outside the directory is unpacked only when
`upgrader_pre_download` hands back a copy it verified.

- const `OPTION` = `'minn_updates'`
- const `TTL` = `43200`
- const `PLUGINS_API` = `'https://api.wordpress.org/plugins/update-check/1.1/'`
- const `THEMES_API` = `'https://api.wordpress.org/themes/update-check/1.1/'`
- const `PACKAGE_HOST` = `'https://downloads.wordpress.org/'`

Used by: `Minn\Admin\Notifications`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cron\Cron`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Ops\Packages $packages, string $contentDir, string $home, string $wpVersion)
```


### `state(): array`

The stored answer, refreshed when older than the TTL or absent.

### `refresh(): array`

Asks wordpress.org now, whatever the cache says, and keeps the answer.

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

Whether wordpress.org knows this theme.

### `updatePlugin(string $file): string`

Applies the offer for one plugin file; returns the installed version afterwards.

### `updateTheme(string $stylesheet): string`

Updates one theme to its offer; the new version.

### `auto(string $type): array`

The auto-update list for plugins or themes, trimmed to what is installed. @return list<string>

- `@return list<string>`

### `enableAuto(string $type, string $asset): array`

Turns auto-updates on or off for one plugin or theme.

- `@return list<string> the list after the change`

### `disableAuto(string $type, string $asset): array`

Takes one plugin or theme off the auto-update list; the list after.

### `runAuto(Minn\Ops\AutoUpdates $gate): array`

Applies every offer on the auto-update lists: what was updated, and
what was refused with the reason, by plugin file or theme slug.

- `@return array{done: list<string>, failed: array<string, string>}`

### `pluginVersions(): array`

Plugin file => installed version. @return array<string, string>

- `@return array<string, string>`

### `pluginNames(): array`

Plugin file => Plugin Name. @return array<string, string>

- `@return array<string, string>`

### `themeHeaders(): array`

Stylesheet => style.css headers. @return array<string, array<string, string>>

- `@return array<string, array<string, string>>`

Internals: `supplied()` (private, line 132), `saveAuto()` (private, line 267), `install()` (private, line 353), `vouched()` (private, line 376), `consume()` (private, line 394), `post()` (private, line 406), `map()` (private, line 416), `safeUrl()` (private, line 424)

