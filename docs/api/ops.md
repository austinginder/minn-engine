# `Minn\Ops`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Archive`](#archive) | final class | 69 | A zip unpacked the safe way, for the plugin and theme installer and for |
| [`AutoUpdates`](#autoupdates) | final readonly class | 29 | Whether per-item auto-updates apply to plugins or themes, as the |
| [`Changelog`](#changelog) | final class | 55 | Minn's changelog, read from the engine's GitHub repository rather than |
| [`CoreStatus`](#corestatus) | final readonly class | 48 | The core the app's update banner and chip speak of, which on Minn is |
| [`Diagnostics`](#diagnostics) | final readonly class | 408 | The System view's facts about this install: the engine, PHP, the |
| [`EngineUpdate`](#engineupdate) | final readonly class | 105 | Replaces the running engine with a published release. The release's |
| [`InstalledSoftware`](#installedsoftware) | final readonly class | 59 | What is installed, as the System view lists it: every extension and |
| [`Logs`](#logs) | final readonly class | 150 | The log files the System view can read and clear: the debug log the |
| [`Packages`](#packages) | final readonly class | 442 | Putting themes and extensions on disk. Themes come from wordpress.org |
| [`PluginsApi`](#pluginsapi) | final readonly class | 35 | plugins_api() as plugins call it and answer it (probe plugins-api): the |
| [`Release`](#release) | final readonly class | 77 | One published Minn release as GitHub describes it: the version its tag |
| [`Releases`](#releases) | final class | 89 | Whether a newer Minn is out, asked of GitHub at most once a day: the |
| [`Unzip`](#unzip) | final readonly class | 101 | An archive unpacked as unzip_file() unpacks it (probe unzip-file): into |
| [`Updates`](#updates) | final class | 401 | Update offers from wordpress.org for the site's plugins and themes: the |

## Archive

`final class Minn\Ops\Archive` · `public/minn/src/Minn/Ops/Archive.php`

A zip unpacked the safe way, for the plugin and theme installer and for
the engine's own update alike: every entry stays inside its folder (no
absolute path, no "..", no backslash, no NUL), none is a symbolic link,
there are no more than 20,000 of them, they unpack to no more than
512 MB, and they all sit in exactly one top folder (macOS's __MACOSX
noise aside). Nothing is written until every entry has passed.

- const `MAX_BYTES` = `536870912`
- const `MAX_ENTRIES` = `20000`

Used by: `Minn\Ops\EngineUpdate`, `Minn\Ops\Packages`

### static `unpackFolder(string $file, string $stage): string`

Unpacks a zip file into a new folder $stage; the path of the one folder the archive held.

### static `isFolderName(string $name): bool`

A plain folder name: no separators, never "." or "..".

Internals: `isSymlinkEntry()` (private, line 76)


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


## Changelog

`final class Minn\Ops\Changelog` · `public/minn/src/Minn/Ops/Changelog.php`

Minn's changelog, read from the engine's GitHub repository rather than
shipped with it: a release carries no notes for anyone to find on the
sites that run it. The file on the default branch is fetched at most once
a day and kept in the minn_changelog option (JSON) with the time it was
fetched; sections still marked Unreleased are left out, so a site only
reads about releases that exist. A fetch GitHub does not answer keeps the
last copy; a 404 (no such file, or a private repository) keeps nothing.

- const `OPTION` = `'minn_changelog'`
- const `TTL` = `86400`
- const `SOURCE` = `'https://raw.githubusercontent.com/austinginder/minn-engine/main/changelog.md'`

Used by: `Minn\Admin\BundleController`, `Minn\Rest\Services`

```php
__construct(Closure $load, Closure $save, string $source = self::SOURCE)
```
- `@param Closure(): ?string $load the stored copy, as JSON`
- `@param Closure(string): void $save keeps the copy, as JSON`


### static `forSite(Minn\Content\Site $site): self`

The changelog for a site, its copy kept in the site's minn_changelog option.

### `markdown(): string`

The released sections as Markdown, fetched first when the copy is a day old; '' when there is none.

### static `released(string $markdown): string`

The changelog without its Unreleased sections.


## CoreStatus

`final readonly class Minn\Ops\CoreStatus` · `public/minn/src/Minn/Ops/CoreStatus.php`

The core the app's update banner and chip speak of, which on Minn is
Minn: the running engine's version, a newer release on offer
(Ops\Releases, asked of GitHub once a day), and installing it
(Ops\EngineUpdate). WordPress's own offer (the update_core transient a
parked copy may write) is not Minn's to act on and is not shown.
dbUpgrade is false: the database is WordPress's, and a Minn release
never migrates it.

Used by: `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Services`

```php
__construct(Minn\Ops\Releases $releases, Minn\Ops\EngineUpdate $engine, string $version)
```


### `data(): array`

Minn's version, any offer, and when GitHub was last asked.

### `due(): bool`

Whether GitHub was last asked a day ago or more.

### `refresh(): void`

Asks GitHub now.

### `update(): string`

Installs the release on offer; the version now in place.


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


## EngineUpdate

`final readonly class Minn\Ops\EngineUpdate` · `public/minn/src/Minn/Ops/EngineUpdate.php`

Replaces the running engine with a published release. The release's
minn.zip comes from the engine repository's own GitHub release downloads
and nowhere else (every redirect hop is judged), must match the sha256
GitHub publishes for it, and is unpacked beside the engine into a folder
of its own, where it is checked again: its bootstrap names the version on
offer and bin/minn is there. Then the
swap, two renames in the folder the engine lives in: the running engine
moves aside, the new one moves in, and the old copy is removed; when the
second rename fails the first is undone. The legacy install record
(.install.json, from installs that kept it inside the engine) comes
along. One update runs at a time. A development checkout (a symbolic
link, or a folder under git) is refused: git owns those. The site keeps
answering throughout.

- const `HOSTS` = `array (   0 => 'https://github.com/',   1 => 'https://objects.githubusercontent.com/',   2 => 'https://release-assets.githubusercontent.com/', )` — Where a release asset may come from: the release page's link and the hosts it redirects to.
- const `MAX_DOWNLOAD` = `67108864`

Used by: `Minn\Cli\Installer`, `Minn\Ops\CoreStatus`, `Minn\Rest\Services`

```php
__construct(string $engineDir)
```


### `apply(Minn\Ops\Release $release): string`

Downloads, checks and installs a release; the version now in place.

### `install(string $zip, string $sha256, string $version): string`

Installs an archive in hand once it matches its checksum and holds the version named; the version now in place.

### static `versionOf(string $engineDir): string`

The version a bootstrap.php names; "0.0.0" when it names none.

Internals: `swap()` (private, line 75), `refuseCheckout()` (private, line 120)


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

- const `WPORG_THEMES` = `'https://api.wordpress.org/themes/info/1.2/'` — The largest archive fetched or unpacked, in bytes.
- const `WPORG_PLUGINS` = `'https://api.wordpress.org/plugins/info/1.2/'`
- const `INFO_OPTION` = `'minn_plugin_info'`
- const `INFO_TTL` = `43200`

Used by: `Minn\Admin\PackagesController`, `Minn\Cli\AssetUpdate`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PackageInstaller`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Cron\Cron`, `Minn\Ops\PluginsApi`, `Minn\Ops\Updates`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

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

### `pluginsAction(string $action, array $request): ?array`

The directory's answer to a plugins_api() action, its request passed
as given (Ops\PluginsApi); null when it did not answer. One of the
directory calls Track H moves behind the Minn update service.

- `@param array<string, mixed> $request`
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

Internals: `pluginPackage()` (private, line 157), `plain()` (private, line 216), `themePackage()` (private, line 310), `place()` (private, line 359), `contained()` (private, line 406), `identify()` (private, line 421), `describe()` (private, line 435)


## PluginsApi

`final readonly class Minn\Ops\PluginsApi` · `public/minn/src/Minn/Ops/PluginsApi.php`

plugins_api() as plugins call it and answer it (probe plugins-api): the
arguments as an object with the reader's locale and the major.minor
version beside them, through plugins_api_args; then whatever a plugin
answers through plugins_api (a self-hosted plugin's own information, or
an error) stands, and only when nobody answers is the directory asked,
through Ops\Packages (the engine's one door to it, which Track H moves
behind the Minn update service); plugins_api_result is handed what came
back either way.

```php
__construct(Closure $filter, Minn\Ops\Packages $packages, string $locale, string $version)
```
- `@param Closure(string, mixed...): mixed $filter applies a filter, as apply_filters does`


### `ask(string $action, object|array $args): mixed`

The answer for an action (plugin_information, query_plugins, ...): an object, a WP_Error, or what a plugin returned.

Internals: `directory()` (private, line 41)


## Release

`final readonly class Minn\Ops\Release` · `public/minn/src/Minn/Ops/Release.php`

One published Minn release as GitHub describes it: the version its tag
names (v0.1.0 is 0.1.0), its notes and page, when it went out, and the
minn.zip it ships with that file's sha256 (the digest GitHub publishes
for every release asset). A draft, a pre-release, a tag that is not a
version, or a release without minn.zip is not a release Minn offers.

- const `ASSET` = `'minn.zip'` — The archive every release ships: the public/minn/ tree with Minn Admin bundled at minn/admin.

Used by: `Minn\Ops\EngineUpdate`, `Minn\Ops\Releases`

```php
__construct(string $version, string $url, string $published, string $notes, string $package, string $sha256)
```

- readonly `string $version`
- readonly `string $url`
- readonly `string $published`
- readonly `string $notes`
- readonly `string $package`
- readonly `string $sha256`

### static `fromGitHub(array $answer): ?self`

A release from GitHub's answer for releases/latest (or one entry of
releases); null when it is not one Minn offers.

- `@param array<string, mixed> $answer`

### static `fromArray(array $row): ?self`

A release as the minn_release option keeps it; null for anything else.

- `@param array<string, mixed> $row`

### `toArray(): array`

The release as it is kept in the minn_release option.

- `@return array{version: string, url: string, published: string, notes: string, package: string, sha256: string}`

### `newerThan(string $installed): bool`

Whether this release is newer than a version that is installed.


## Releases

`final class Minn\Ops\Releases` · `public/minn/src/Minn/Ops/Releases.php`

Whether a newer Minn is out, asked of GitHub at most once a day: the
latest published release of the engine's own repository, kept in the
minn_release option (JSON) with the time it was asked. A check GitHub
does not answer (offline, rate limited) keeps the last answer and waits
a day like any other; a repository with no published release (a 404,
which is also what a private one answers) offers nothing. This is Minn
asking Minn, never wordpress.org.

- const `OPTION` = `'minn_release'`
- const `TTL` = `86400`
- const `REPOSITORY` = `'austinginder/minn-engine'`
- const `SOURCE` = `'https://api.github.com/repos/austinginder/minn-engine/releases/latest'`
- const `DOWNLOADS` = `'https://github.com/austinginder/minn-engine/releases/download/'` — Where the repository's release assets download from; nothing else is installed.

Used by: `Minn\Admin\Notifications`, `Minn\Cli\Installer`, `Minn\Ops\Changelog`, `Minn\Ops\CoreStatus`, `Minn\Ops\EngineUpdate`, `Minn\Rest\Services`

```php
__construct(Closure $load, Closure $save, string $installed, string $source = self::SOURCE)
```
- `@param Closure(): ?string $load the stored answer, as JSON`
- `@param Closure(string): void $save keeps the answer, as JSON`


### static `forSite(Minn\Content\Site $site, string $installed): self`

The check for a site, its answer kept in the site's minn_release option.

### `stored(): array`

The stored answer: when GitHub was last asked and the latest release then.

- `@return array{checked: int, latest: ?array<string, string>}`

### `due(): bool`

Whether the stored answer is a day old, or there is none.

### `refresh(): array`

Asks GitHub now and keeps the answer; `answered` says whether GitHub
did (when it did not, `latest` is the last answer kept).

- `@return array{checked: int, latest: ?array<string, string>, answered: bool}`

### `offer(): ?Minn\Ops\Release`

The release on offer: the latest one, when it is newer than the running engine.

Internals: `origin()` (private, line 103)


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

Internals: `supplied()` (private, line 137), `saveAuto()` (private, line 272), `install()` (private, line 358), `vouched()` (private, line 381), `consume()` (private, line 399), `post()` (private, line 411), `map()` (private, line 421), `safeUrl()` (private, line 429)

