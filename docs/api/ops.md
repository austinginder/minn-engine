# `Minn\Ops`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Archive`](#archive) | final class | 96 | A zip unpacked the safe way, for the plugin and theme installer and for |
| [`AutoUpdates`](#autoupdates) | final readonly class | 29 | Whether per-item auto-updates apply to plugins or themes, as the |
| [`Changelog`](#changelog) | final class | 67 | A changelog read from its repository rather than shipped: a Minn release |
| [`CoreStatus`](#corestatus) | final readonly class | 48 | The core the app's update banner and chip speak of, which on Minn is |
| [`Diagnostics`](#diagnostics) | final readonly class | 408 | The System view's facts about this install: the engine, PHP, the |
| [`Directory`](#directory) | final class | 35 | The Minn update service, https://updates.minn.run: the one place the |
| [`EngineUpdate`](#engineupdate) | final readonly class | 129 | Replaces the running engine with a published release. The release's |
| [`InstalledSoftware`](#installedsoftware) | final readonly class | 59 | What is installed, as the System view lists it: every extension and |
| [`Logs`](#logs) | final readonly class | 150 | The log files the System view can read and clear: the debug log the |
| [`Packages`](#packages) | final readonly class | 477 | Putting plugins, themes and extensions on disk. Plugins and themes come |
| [`PluginsApi`](#pluginsapi) | final readonly class | 35 | plugins_api() as plugins call it and answer it (probe plugins-api): the |
| [`Release`](#release) | final readonly class | 58 | One published Minn release as the update service describes it (and as |
| [`Releases`](#releases) | final class | 86 | Whether a newer Minn is out, asked of the Minn update service at most |
| [`Unzip`](#unzip) | final readonly class | 101 | An archive unpacked as unzip_file() unpacks it (probe unzip-file): into |
| [`Updates`](#updates) | final class | 435 | Update offers for the site's plugins and themes from the directory, asked |
| [`UpgraderRun`](#upgraderrun) | final readonly class | 113 | The engine's own installs and updates, run through the reference's |

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

Used by: `Minn\Ops\EngineUpdate`, `Minn\Ops\Packages`, `Minn\Ops\UpgraderRun`

### static `unpackFolder(string $file, string $stage): string`

Unpacks a zip file into a new folder $stage; the path of the one folder the archive held.

### static `inspect(string $file): string`

Checks every entry of a zip file without writing anything; the one folder it holds.

### static `holds(string $file, string $entry): bool`

Whether a zip file holds an entry by that name.

### static `isFolderName(string $name): bool`

A plain folder name: no separators, never "." or "..".

Internals: `isSymlinkEntry()` (private, line 103)


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

A changelog read from its repository rather than shipped: a Minn release
carries no notes, its own or Minn Admin's, for anyone to find on the
sites that run it. The update service reads changelog.md from the
repository's default branch on GitHub; the engine asks the service at most
once a day and keeps the copy in an option (JSON) with the time it came;
sections still marked Unreleased are left out, so a site only reads about
releases that exist. A fetch GitHub does not answer keeps the last copy; a
404 (no such file, or a private repository) keeps nothing.

- const `TTL` = `86400`
- const `ENGINE_SOURCE` = `'https://updates.minn.run/v1/minn/changelog'`
- const `ADMIN_SOURCE` = `'https://updates.minn.run/v1/minn-admin/changelog'`

Used by: `Minn\Admin\BundleController`, `Minn\Rest\Services`

```php
__construct(Closure $load, Closure $save, string $source)
```
- `@param Closure(): ?string $load the stored copy, as JSON`
- `@param Closure(string): void $save keeps the copy, as JSON`


### static `engine(Minn\Content\Site $site): self`

Minn's changelog for a site, kept in its minn_changelog option.

### static `admin(Minn\Content\Site $site): self`

Minn Admin's changelog for a site running Minn, kept in its minn_admin_changelog option.

### `markdown(): string`

The released sections as Markdown, fetched first when the copy is a day old; '' when there is none.

### static `released(string $markdown): string`

The changelog without its Unreleased sections.

Internals: `kept()` (private, line 50)


## CoreStatus

`final readonly class Minn\Ops\CoreStatus` · `public/minn/src/Minn/Ops/CoreStatus.php`

The core the app's update banner and chip speak of, which on Minn is
Minn: the running engine's version, a newer release on offer
(Ops\Releases, asked of the update service once a day), and installing it
(Ops\EngineUpdate). WordPress's own offer (the update_core transient a
parked copy may write) is not Minn's to act on and is not shown.
dbUpgrade is false: the database is WordPress's, and a Minn release
never migrates it.

Used by: `Minn\Admin\UpdatesController`, `Minn\Admin\V1Controller`, `Minn\Rest\Services`

```php
__construct(Minn\Ops\Releases $releases, Minn\Ops\EngineUpdate $engine, string $version)
```


### `data(): array`

Minn's version, any offer, and when the update service was last asked.

### `due(): bool`

Whether the update service was last asked a day ago or more.

### `refresh(): void`

Asks the update service now.

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


## Directory

`final class Minn\Ops\Directory` · `public/minn/src/Minn/Ops/Directory.php`

The Minn update service, https://updates.minn.run: the one place the
engine asks about anything it does not have yet, so a site running Minn
talks to Minn and never to wordpress.org. The service speaks
wordpress.org's own endpoints under /v1/ (plugin and theme update checks,
directory search and information, translations) with every address a site
would fetch pointed back at itself: packages under /v1/download/, icons,
screenshots and emoji under /v1/assets/. It also serves Minn's own
releases and changelogs. Every request names the engine and the WordPress
version it is compatible with (which judges a plugin's "Requires at
least"), never the site's address.

- const `ORIGIN` = `'https://updates.minn.run/'`
- const `BASE` = `'https://updates.minn.run/v1/'`
- const `PACKAGES` = `'https://updates.minn.run/v1/download/'` — Where plugin, theme and translation packages download from.

Used by: `Minn\Cli\PackageInstaller`, `Minn\Ops\Changelog`, `Minn\Ops\EngineUpdate`, `Minn\Ops\Packages`, `Minn\Ops\Release`, `Minn\Ops\Releases`, `Minn\Ops\Updates`, `Minn\Ops\UpgraderRun`

### static `url(string $path): string`

An address on the service: a path under /v1/, such as "plugins/info/1.2/".

### static `userAgent(): string`

How the engine introduces itself: its version, and nothing about the site.

### static `post(string $path, array $form): array`

A form POST to the service (the update checks), its answer decoded.

- `@param array<string, string> $form`
- `@return array<string, mixed>`


## EngineUpdate

`final readonly class Minn\Ops\EngineUpdate` · `public/minn/src/Minn/Ops/EngineUpdate.php`

Replaces the running engine with a published release. The release's
minn.zip comes from the Minn update service and nowhere else (every
redirect hop is judged), must match the sha256 its release records, and
must carry an Ed25519 signature made with a key this engine was built to
trust (KEYS; the secret half never leaves the release maintainer's
machine, so neither GitHub nor the service can stand in a build of their
own). It is unpacked beside the engine into a folder of its own, where it
is checked again: its bootstrap names the version on offer and bin/minn is
there. Then the swap, two renames in the folder the engine lives in: the
running engine moves aside, the new one moves in, and the old copy is
removed; when the second rename fails the first is undone. The legacy
install record (.install.json, from installs that kept it inside the
engine) comes along. One update runs at a time. A development checkout (a
symbolic link, or a folder under git) is refused: git owns those. The
site keeps answering throughout.

- const `KEYS` = `array (   0 => '+Usjnr1ZTudBbWToqYLkZ1Oy2yKN07d9+u62AIJ2FG8=', )` — The public halves of the keys that sign Minn releases (base64 Ed25519;
scripts/release-key.php). A new key ships here in a release signed by
an old one before it signs anything.
- const `MAX_DOWNLOAD` = `67108864`

Used by: `Minn\Cli\Installer`, `Minn\Ops\CoreStatus`, `Minn\Rest\Services`

```php
__construct(string $engineDir, array $keys = self::KEYS)
```
- `@param list<string> $keys the release keys to trust (tests pass their own)`


### `apply(Minn\Ops\Release $release): string`

Downloads, checks and installs a release; the version now in place.

### `install(string $zip, string $sha256, string $signature, string $version): string`

Installs an archive in hand once it matches its checksum and signature and holds the version named; the version now in place.

### static `versionOf(string $engineDir): string`

The version a bootstrap.php names; "0.0.0" when it names none.

Internals: `swap()` (private, line 82), `signed()` (private, line 120), `refuseCheckout()` (private, line 146)


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

Putting plugins, themes and extensions on disk. Plugins and themes come
from the directory (wordpress.org's, asked through the Minn update
service, Ops\Directory) or an uploaded zip, and are installed through
WordPress's upgraders (UpgraderRun), so plugins hear every upgrader hook;
a Minn extension (a folder with a minn.json) is placed by the engine.
Every archive passes Archive's checks first: exactly one top-level
folder that is a plain name (never "." or ".."), no absolute or dotted
paths, no symbolic links, bounded entry count and size. Removal proves
the folder is a direct child of its kind's directory before anything is
deleted.

- const `THEMES_INFO` = `'https://updates.minn.run/v1/themes/info/1.2/'` — The largest archive fetched or unpacked, in bytes.
- const `PLUGINS_INFO` = `'https://updates.minn.run/v1/plugins/info/1.2/'`
- const `INFO_OPTION` = `'minn_plugin_info'`
- const `INFO_TTL` = `43200`

Used by: `Minn\Admin\PackagesController`, `Minn\Cli\DirectorySearch`, `Minn\Cli\PackageInstaller`, `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`, `Minn\Ops\PluginsApi`, `Minn\Ops\Updates`, `Minn\Ops\UpgraderRun`, `Minn\Rest\PluginsController`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, string $contentDir)
```


### `searchThemes(string $query): array`

Directory theme search, or the popular list for an empty query. @return list<array>

- `@return list<array>`

### `searchPlugins(string $query, int $page): array`

Directory plugin search: twelve per page with icons, short
descriptions, install counts, and ratings, plus which results are
already installed (by folder). @return array{plugins: list<array>, page: int, pages: int, total: int}

- `@return array{plugins: list<array>, page: int, pages: int, total: int}`

### `pluginInfo(string $slug): array`

The slim card for one directory plugin, cached twelve hours per slug.

### `installPlugin(string $slug, string $version = ''): string`

Installs a directory plugin by slug, through the upgrader; returns its folder.

### `replacePlugin(string $slug, string $version = ''): string`

Installs a directory plugin over the folder already there.

### `directoryPlugin(string $slug): ?array`

One directory plugin record, or null when the slug is unknown.

- `@return array<string, mixed>|null`

### `pluginsAction(string $action, array $request): ?array`

The directory's answer to a plugins_api() action, its request passed
as given (Ops\PluginsApi); null when it did not answer. One of the
directory calls Track H moves behind the Minn update service.

- `@param array<string, mixed> $request`
- `@return array<string, mixed>|null`

### `queryThemes(string $search, int $page, int $perPage): array`

A page of directory themes for `wp theme search`.

- `@return array{items: list<array<string, mixed>>, total: int}`

### `queryPlugins(string $search, int $page, int $perPage): array`

A page of directory plugins for `wp plugin search`.

- `@return array{items: list<array<string, mixed>>, total: int}`

### `directoryTheme(string $slug): ?array`

One directory theme record, or null when the slug is unknown.

- `@return array<string, mixed>|null`

### `installTheme(string $slug, string $version = ''): string`

Installs a directory theme by slug, through the upgrader; returns its stylesheet folder.

### `replaceTheme(string $slug, string $version = ''): string`

Installs a directory theme over the folder already there.

### `unpack(string $zip, string $kind): array`

Unpacks an uploaded or downloaded archive into wp-content/themes or
wp-content/plugins: a theme or a WordPress plugin through the upgrader,
a Minn extension (minn.json) placed by the engine; refused when its
folder is taken. @return array{folder: string, name: string, version: string, kind: string}

- `@return array{folder: string, name: string, version: string, kind: string}`

### `unpackReplacing(string $zip, string $kind): array`

Unpacks a zip over a folder already there, replacing it whole.

### `remove(string $kind, string $folder): void`

Removes a theme or plugin folder that is not in use.

### `identity(string $dir, string $kind): array`

What a folder holds by its headers; kind "unknown" when nothing identifies it. @return array{name: string, version: string, kind: string}

- `@return array{name: string, version: string, kind: string}`

### `fetch(string $url, string ...$hostPrefixes): string`

A package over https, every redirect hop included, refusing anything
else; when host prefixes are given, every hop must start with one.
The request names the engine, never the site's address.

Internals: `pluginPackage()` (private, line 158), `plain()` (private, line 217), `themePackage()` (private, line 311), `upload()` (private, line 364), `place()` (private, line 387), `contained()` (private, line 434), `identify()` (private, line 449), `ask()` (private, line 483)


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

One published Minn release as the update service describes it (and as
the minn_release option keeps it, the same shape): the version, its notes
and page, when it went out, and its minn.zip with that file's sha256 and
its Ed25519 signature. Anything else is not a release Minn offers: a
version that is not major.minor.patch, a package anywhere but Minn's own
downloads, a missing checksum, or a missing signature.

- const `ASSET` = `'minn.zip'` — The archive every release ships: the public/minn/ tree with Minn Admin bundled at minn/admin.
- const `DOWNLOADS` = `'https://updates.minn.run/v1/minn/download/'` — Where release packages download from; EngineUpdate fetches nothing else.

Used by: `Minn\Ops\EngineUpdate`, `Minn\Ops\Releases`

```php
__construct(string $version, string $url, string $published, string $notes, string $package, string $sha256, string $signature)
```

- readonly `string $version`
- readonly `string $url`
- readonly `string $published`
- readonly `string $notes`
- readonly `string $package`
- readonly `string $sha256`
- readonly `string $signature`

### static `fromArray(array $row): ?self`

A release from the service's answer or the stored option; null when it is not one Minn offers.

- `@param array<string, mixed> $row`

### `toArray(): array`

The release as it is kept in the minn_release option.

- `@return array{version: string, url: string, published: string, notes: string, package: string, sha256: string, signature: string}`

### `newerThan(string $installed): bool`

Whether this release is newer than a version that is installed.


## Releases

`final class Minn\Ops\Releases` · `public/minn/src/Minn/Ops/Releases.php`

Whether a newer Minn is out, asked of the Minn update service at most
once a day: its latest installable release (published on GitHub, read
by the service), kept in the minn_release option (JSON) with the time it
was asked. A check the service does not answer (offline, GitHub rate
limiting it) keeps the last answer and waits a day like any other; no
installable release (a 404) offers nothing. This is Minn asking Minn,
never wordpress.org, and never GitHub directly.

- const `OPTION` = `'minn_release'`
- const `TTL` = `86400`
- const `SOURCE` = `'https://updates.minn.run/v1/minn/releases/latest'`

Used by: `Minn\Admin\Notifications`, `Minn\Cli\Installer`, `Minn\Ops\CoreStatus`, `Minn\Rest\Services`

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

Asks the service now and keeps the answer; `answered` says whether it
did (when it did not, `latest` is the last answer kept).

- `@return array{checked: int, latest: ?array<string, string>, answered: bool}`

### `offer(): ?Minn\Ops\Release`

The release on offer: the latest one, when it is newer than the running engine.

Internals: `origin()` (private, line 100)


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

Update offers for the site's plugins and themes from the directory, asked
through the Minn update service (Ops\Directory), which answers with
wordpress.org's own offers and serves their packages: the update-check
endpoints asked with the installed headers (each plugin's Name, Version
and Update URI, so a plugin that updates from elsewhere is never offered
the directory's plugin of the same folder name), the answer kept in the
minn_updates option (JSON) for twelve hours, and the offers applied by
downloading the release archive through the one package unpacker. A
plugin or theme the directory does not know keeps its folder untouched
and is never offered anything. The per-item
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

Used by: `Minn\Admin\Notifications`, `Minn\Admin\ThemesController`, `Minn\Admin\UpdatesController`, `Minn\Cli\AssetUpdate`, `Minn\Cron\Cron`, `Minn\Rest\Services`

```php
__construct(Minn\Content\Site $site, Minn\Content\Inventory $inventory, Minn\Ops\Packages $packages, string $contentDir)
```


### static `forSite(Minn\Content\Site $site, string $contentDir): self`

The updater over a site's wp-content.

### `checkForWordPress(string $transient, array $fresh): void`

wp_update_plugins and wp_update_themes: the service asked when the
stored answer is old (or a fresh one is wanted), and the offers left
in the update transients when they are not there already.

### `state(): array`

The stored answer, refreshed when older than the TTL or absent.

### `refresh(): array`

Asks the directory now, whatever the cache says, and keeps the answer.

### `check(): array`

Asks the directory now and stores the answer.

### `publish(): void`

The offers in WordPress's update transients, update_plugins and
update_themes, in the shape wp_update_plugins and wp_update_themes
leave there: where plugins, the facade's update helpers and the
upgraders read them. Written after every check and after an update.

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

Whether the directory knows this theme.

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

Internals: `supplied()` (private, line 187), `saveAuto()` (private, line 322), `pluginHeaders()` (private, line 379), `apply()` (private, line 423), `consume()` (private, line 444), `map()` (private, line 457), `safeUrl()` (private, line 465)


## UpgraderRun

`final readonly class Minn\Ops\UpgraderRun` · `public/minn/src/Minn/Ops/UpgraderRun.php`

The engine's own installs and updates, run through the reference's
upgraders (Plugin_Upgrader, Theme_Upgrader) so plugins hear every hook a
WordPress install or update tells them: the REST plugin route, Minn
Admin's install, upload and update routes, the CLI verbs and the
automatic updates all come here. The Minn update service's packages are
fetched over the engine's own client (Packages::fetch: https, the
service's host, a size cap) and handed to the upgrader as a file through
upgrader_pre_download, so where Minn gets code is never the
plugin-filterable HTTP API's to change. A package from anywhere else is
installed only when its publisher answered upgrader_pre_download with a
copy it checked (or refused it): nobody's word, no install. A package
from the service passes Archive's checks before the upgrader unpacks it;
a local file (an upload, checked by Packages) is the upgrader's to read. The upgrader's refusals come back
as the engine's REST errors, in the words its routes already use.

Used by: `Minn\Ops\Packages`, `Minn\Ops\Updates`

```php
__construct(Minn\Ops\Packages $packages)
```


### `install(string $kind, string $package, array $args = array ( )): array`

Installs a package (or, with overwrite_package, replaces the folder it
lands on): its folder and the sha256 of a package fetched from the
service, '' otherwise.

- `@param array<string, mixed> $args the upgrader's install arguments`
- `@return array{folder: string, sha256: string}`

### `update(string $kind, string $item, string $package): string`

Applies the offer the update transient holds for one plugin (file) or
theme (folder), as Minn Admin does on WordPress: a plugin through
bulk_upgrade, a theme through upgrade. The sha256 of the package, ''
when it did not come from the service.

Internals: `guarded()` (private, line 75), `refusal()` (private, line 106), `taken()` (private, line 126)

