# `Minn\Cli`

the wp verbs the engine answers itself

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AssetUpdate`](#assetupdate) | final class | 330 | Shared wording for `wp plugin update` and `wp theme update`. Captured |
| [`CacheCommand`](#cachecommand) | final class | 15 | `wp cache flush`: the engine has no object cache, so this is a no-op success. |
| [`Commands`](#commands) | final class | 94 | The verbs the engine answers to. Every one is registered for the |
| [`CronCommand`](#croncommand) | final class | 277 | `wp cron`: the events, schedules, and spawn test a host and a fleet ask |
| [`DirectorySearch`](#directorysearch) | final class | 37 | Shared wording for `wp theme search` and `wp plugin search`. The |
| [`Installer`](#installer) | final class | 339 | The swap, both ways. Install parks WordPress's own files beside the |
| [`MaintenanceCommand`](#maintenancecommand) | final class | 65 | `wp maintenance-mode`: the `.maintenance` marker in the webroot. The |
| [`MinnCommand`](#minncommand) | final class | 304 | Identifies the engine. |
| [`OptionCommand`](#optioncommand) | final class | 185 | Options, read and written straight to the options table. Serialized |
| [`PackageInstaller`](#packageinstaller) | final readonly class | 138 | Puts a theme or a plugin on disk for `wp theme install` and `wp plugin |
| [`PluginCommand`](#plugincommand) | final class | 386 | `wp plugin list\|install\|update\|activate\|deactivate\|delete`: the inventory and the fleet's install/update/delete. |
| [`Preflight`](#preflight) | final class | 360 | What a site will and will not get from the engine, before anything |
| [`RewriteCommand`](#rewritecommand) | final class | 52 | `wp rewrite flush\|structure`: permalink_structure is the engine's |
| [`Runtime`](#runtime) | final class | 80 | The engine, booted for a command: reads the site's wp-config.php (which |
| [`SearchReplaceCommand`](#searchreplacecommand) | final class | 143 | `wp search-replace`: walks every string column, including serialized |
| [`ThemeCommand`](#themecommand) | final class | 332 | `wp theme list\|install\|update\|activate\|delete`: the inventory CaptainCore |
| [`UserCommand`](#usercommand) | final class | 366 | Users: the list and get views, and the one-time login link. |

## AssetUpdate

`final class Minn\Cli\AssetUpdate` · `public/minn/src/Minn/Cli/AssetUpdate.php`

Shared wording for `wp plugin update` and `wp theme update`. Captured
from the reference: missing plugins warn and count as failed; a missing
theme is a fatal Error and stops. json/csv print only the rows (empty
stdout when nothing changed). `--exclude` always prints the skipped
line in table/summary, even when the list is empty.

Used by: `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`

```php
__construct(string $kind, Minn\Ops\Updates $updates, Minn\Content\Inventory $inventory)
```


### static `boot(string $kind): self`

The updater for one kind of asset, with the runtime up.

### `run(array $names, array $assocArgs): void`

Updates the named assets, or all of them.

- `@param list<string> $names`

Internals: `offers()` (private, line 105), `previewRow()` (private, line 153), `apply()` (private, line 165), `render()` (private, line 198), `emit()` (private, line 248), `skipped()` (private, line 269), `missing()` (private, line 277), `installed()` (private, line 286), `titleFor()` (private, line 299), `statusByName()` (private, line 313), `channel()` (private, line 324), `samePrefix()` (private, line 335), `pluginSlug()` (private, line 347)


## CacheCommand

`final class Minn\Cli\CacheCommand` · `public/minn/src/Minn/Cli/CacheCommand.php`

`wp cache flush`: the engine has no object cache, so this is a no-op success.

Used by: `Minn\Cli\Commands`

### `flush(array $args, array $assocArgs): void`

Flushes the object cache.

The engine does not run an object cache. The verb still succeeds so
fleet scripts that call it after a write do not abort.


## Commands

`final class Minn\Cli\Commands` · `public/minn/src/Minn/Cli/Commands.php`

The verbs the engine answers to. Every one is registered for the
before_wp_load phase, which WP-CLI runs before it looks for WordPress,
and each replaces the bundled subcommand of the same name (a deferred
addition lands after the bundle and overrides its leaf).

### static `register(): void`

Registers every engine verb with WP-CLI in the phase it runs in.

Internals: `leaf()` (private, line 78), `replace()` (private, line 94)


## CronCommand

`final class Minn\Cli\CronCommand` · `public/minn/src/Minn/Cli/CronCommand.php`

`wp cron`: the events, schedules, and spawn test a host and a fleet ask
for. Every verb boots the full runtime first, so a plugin's schedules and
callbacks are registered before an event is listed, scheduled, or run. The
wording and exit codes are the reference's, captured from `wp cron` on the
oracle; the two time columns drift second to second and are never pinned.

- const `EVENT_FIELDS` = `array (   0 => 'hook',   1 => 'next_run_gmt',   2 => 'next_run_relative',   3 => 'recurrence', )`
- const `SCHEDULE_FIELDS` = `array (   0 => 'name',   1 => 'display',   2 => 'interval', )`

Used by: `Minn\Cli\Commands`

### `event_list(array $args, array $assocArgs): void`

Lists the scheduled cron events.

## OPTIONS

[--hook=<name>]
[--field=<field>]
[--fields=<fields>]
[--format=<format>]
[--<field>=<value>]
[--due-now]

### `event_run(array $args, array $assocArgs): void`

Runs the next scheduled cron event for the given hook.

## OPTIONS

[<hook>...]
[--due-now]
[--all]

### `event_schedule(array $args, array $assocArgs): void`

Schedules a new cron event.

## OPTIONS

<hook>
[<next-run>]
[<recurrence>]
[--<field>=<value>]

### `event_delete(array $args, array $assocArgs): void`

Deletes all cron events for the given hook.

## OPTIONS

<hook>...

### `event_unschedule(array $args, array $assocArgs): void`

Unschedules all cron events for a given hook.

## OPTIONS

<hook>

### `schedule_list(array $args, array $assocArgs): void`

Lists available cron schedules.

## OPTIONS

[--field=<field>]
[--fields=<fields>]
[--format=<format>]

### `test(array $args, array $assocArgs): void`

Tests the WP Cron spawning system and reports the results.

Internals: `scheduled()` (private, line 245), `eventArgs()` (private, line 260), `duration()` (private, line 267), `relative()` (private, line 289)


## DirectorySearch

`final class Minn\Cli\DirectorySearch` · `public/minn/src/Minn/Cli/DirectorySearch.php`

Shared wording for `wp theme search` and `wp plugin search`. The
default table is name/slug/rating; Success prints before the rows and
only for table/yaml. json/csv/count print the Formatter output alone.

- const `FIELDS` = `array (   0 => 'name',   1 => 'slug',   2 => 'rating', )`

Used by: `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`

### static `run(string $kind, array $args, array $assocArgs): void`

Searches wordpress.org for one kind of asset and prints the page.

- `@param array<string, mixed> $assocArgs`


## Installer

`final class Minn\Cli\Installer` · `public/minn/src/Minn/Cli/Installer.php`

The swap, both ways. Install parks WordPress's own files beside the
webroot, lays the engine and its shape files down, and leaves
wp-config.php and wp-content untouched; eject puts every parked file
back and removes what install wrote. The install record at the webroot
(.minn-install.php) is what eject works from. Preflight says what the site will and
will not get before anything moves.

- const `RECORD` = `'.minn-install.php'` — The install record sits at the webroot, so every site keeps its own
(a symlinked minn/ is shared between sites) and an engine update
cannot take it. It is PHP that returns first, so a web request for it
prints nothing, and it names the webroot it belongs to.
- const `RECORD_HEAD` = `'<?php return; // Minn Engine\'s install record: what `wp minn eject` puts back. ?> '`
- const `LEGACY_RECORD` = `'.install.json'` — Where installs kept it before: read from a site's own minn/ copy, never through a shared symlink.
- const `CORE_ENTRIES` = `array (   0 => 'wp-admin',   1 => 'wp-includes',   2 => 'index.php',   3 => 'wp-activate.php',   4 => 'wp-blog-header.php',   5 => 'wp-comments-post.php',   6 => 'wp-config-sample.php',   7 => 'wp-cron.php',   8 => 'wp-links-opml.php',   9 => 'wp-load.php',   10 => 'wp-login.php',   11 => 'wp-mail.php',   12 => 'wp-settings.php',   13 => 'wp-signup.php',   14 => 'wp-trackback.php',   15 => 'xmlrpc.php',   16 => 'license.txt',   17 => 'readme.html', )` — The files WordPress keeps at the webroot, moved aside as a set.
- const `LAYOUT` = `array (   0 => 'index.php',   1 => 'wp-login.php',   2 => 'wp-settings.php',   3 => 'wp-cli.yml',   4 => 'wp-includes/version.php',   5 => 'wp-admin/index.php', )`
- const `WRITTEN_TREES` = `array (   0 => 'wp-includes',   1 => 'wp-admin', )` — Webroot trees install writes (shape files + require placeholders) and eject deletes before restoring the park.
- const `LEGACY_PUBLISHED` = `array (   0 => 'minn-admin-asset',   1 => 'minn-engine',   2 => 'wp-includes/js/jquery', )` — Leftover webroot copies from an earlier installer that published
assets outside minn/. Eject still deletes them.
- const `SKIP` = `array (   0 => '.git',   1 => 'node_modules',   2 => 'tests',   3 => 'docs',   4 => '.DS_Store',   5 => '.install.json', )` — Development-only trees inside the engine or the admin bundle that never ship.

Used by: `Minn\Cli\MinnCommand`, `Minn\Cli\Preflight`


### static `main(array $argv, string $engineDir): int`

The bin entry: runs one command against a webroot and returns the exit code.

- `@param list<string> $argv`

### `preflight(string $root): string`

What the site will and will not get; the worst light decides install. The report lands in the output.

### `update(array $options): int`

Replaces this engine with the latest release from the Minn update service; --check only says whether there is one.

### `status(string $root): int`

Prints what the webroot is running.

### `install(string $root, array $options): int`

Installs the engine into a webroot when the preflight allows it.

- `@param array<string, string|true> $options`

### `eject(string $root): int`

Puts WordPress back and removes the engine's files.

### static `state(string $root): string`

What a webroot is running: minn, wordpress, or unknown.

### static `record(string $root): array`

The install record for a webroot: its owner, when, the engine
version, the park, what moved, what was laid down. Empty when there
is none, or when the one found names another webroot (a site that
was moved or copied keeps a record its own eject must not act on).

- `@return array<string, mixed>`

Internals: `help()` (private, line 101), `writePlaceholders()` (private, line 294), `move()` (private, line 315), `copyTree()` (private, line 331), `version()` (private, line 350), `say()` (private, line 355)


## MaintenanceCommand

`final class Minn\Cli\MaintenanceCommand` · `public/minn/src/Minn/Cli/MaintenanceCommand.php`

`wp maintenance-mode`: the `.maintenance` marker in the webroot. The
engine does not take the public site down while the file is present;
fleet scripts still need the verb to succeed so an update can bookend
itself with activate/deactivate.

Used by: `Minn\Cli\Commands`

### `activate(array $args, array $assocArgs): void`

Activates maintenance mode.

### `deactivate(array $args, array $assocArgs): void`

Deactivates maintenance mode.

### `status(array $args, array $assocArgs): void`

Displays maintenance mode status.

### `is_active(array $args, array $assocArgs): void`

Detects maintenance mode status. Exit 0 when active, 1 when not.

Internals: `file()` (private, line 75)


## MinnCommand

`final class Minn\Cli\MinnCommand` · `public/minn/src/Minn/Cli/MinnCommand.php`

Identifies the engine.

## EXAMPLES

wp minn version
wp minn info
wp minn probe

Used by: `Minn\Cli\Commands`

### `version(array $args, array $assocArgs): void`

Prints the engine version.

### `probe(array $args, array $assocArgs): void`

Prints the inventory CaptainCore gathers from inside WordPress:
plugins, themes, must-use plugins, core version, home. One
`key:value` line per field; JSON values have no newlines. Split
on the first colon only.

### `info(array $args, array $assocArgs): void`

Prints the engine's version and where it runs from.

### `cron(array $args, array $assocArgs): void`

Runs the engine's scheduled work: due posts go live, expired rows are swept.

### `mail(array $args, array $assocArgs): void`

Sends a test email through the site's mail settings.

## OPTIONS

<to>
: The address to send to.

### `preflight(array $args, array $assocArgs): void`

Says what a WordPress webroot will and will not get from the engine.

## OPTIONS

[<webroot>]
: The webroot to inspect. Defaults to the current directory.

### `install(array $args, array $assocArgs): void`

Parks WordPress and installs the engine into a webroot.

## OPTIONS

[<webroot>]
: The webroot. Defaults to the current directory.

[--park=<dir>]
: Where WordPress's own files go. Defaults to wp-parked beside the webroot.

[--force]
: Install even when preflight is RED.

### `eject(array $args, array $assocArgs): void`

Removes the engine and puts WordPress's files back.

## OPTIONS

[<webroot>]
: The webroot. Defaults to the current directory.

### `status(array $args, array $assocArgs): void`

Says whether a webroot runs WordPress or the engine.

## OPTIONS

[<webroot>]
: The webroot. Defaults to the current directory.

### `scripts(array $args, array $assocArgs): void`

The site's script pack: the GPL `wp-*` JavaScript packages the engine
does not reimplement, which a few plugin front ends need (the
WooCommerce block cart and checkout). The engine never ships them;
this installs them into the site's own wp-content, from the
WordPress the swap parked beside the webroot, or from any WordPress
tree named with --from.

## OPTIONS

[<action>]
: status (the default), install, or remove.

[--from=<path>]
: A WordPress tree to take the packages from. Defaults to the parked
copy the install recorded.

### `recovery(array $args, array $assocArgs): void`

The extensions recovery paused after they killed a request, and the
way back. A plugin or theme that fatals is paused so the next
request answers without it; nothing loads it again until it is
resumed here.

## OPTIONS

[<action>]
: status (the default), resume, or resume-all.

[<name>]
: For resume: the plugin file or theme slug to let back in.

[--theme]
: Treat the name as a theme rather than a plugin.

Internals: `installer()` (private, line 203), `parkedTree()` (private, line 319), `engineVersion()` (private, line 326)


## OptionCommand

`final class Minn\Cli\OptionCommand` · `public/minn/src/Minn/Cli/OptionCommand.php`

Options, read and written straight to the options table. Serialized
values are decoded by the engine's own reader, and arrays written back
in the stored form.

Used by: `Minn\Cli\Commands`

### `get(array $args, array $assocArgs): void`

Gets the value for an option.

## OPTIONS

<key>
: Key for the option.

[--format=<format>]
: Get value in a particular format.
---
default: var_export
options:
- var_export
- json
- yaml
---

### `add(array $args, array $assocArgs): void`

Adds a new option value.

## OPTIONS

<key>
: The name of the option to add.

[<value>]
: The value of the option to add. If omitted, the value is read from STDIN.

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
- plaintext
- json
---

[--autoload=<autoload>]
: Should this option be automatically loaded.
---
options:
- 'on'
- 'off'
---

### `update(array $args, array $assocArgs): void`

Updates an option value.

## OPTIONS

<key>
: The name of the option to update.

[<value>]
: The new value. If omitted, the value is read from STDIN.

[--autoload=<autoload>]
: Requires WP 4.2. Should this option be automatically loaded.
---
options:
- 'on'
- 'off'
---

[--format=<format>]
: The serialization format for the value.
---
default: plaintext
options:
- plaintext
- json
---

### `delete(array $args, array $assocArgs): void`

Deletes an option.

## OPTIONS

<key>...
: Key for the option.

Internals: `readValue()` (private, line 171), `decode()` (private, line 188), `encode()` (private, line 194)


## PackageInstaller

`final readonly class Minn\Cli\PackageInstaller` · `public/minn/src/Minn/Cli/PackageInstaller.php`

Puts a theme or a plugin on disk for `wp theme install` and `wp plugin
install`: a wordpress.org slug, a local zip, or a zip URL, saying along
the way what WP-CLI says. The two kinds differ only in their words, in
where they live, and in a plugin being allowed to be a single file.

Used by: `Minn\Cli\PluginCommand`, `Minn\Cli\ThemeCommand`


### static `themes(Minn\Ops\Packages $packages): self`

The installer `wp theme install` uses.

### static `plugins(Minn\Ops\Packages $packages): self`

The installer `wp plugin install` uses.

### `install(string $source, bool $force, string $version): array`

One source onto disk: the folder when it is there afterwards (put
there now, or already there), null when it could not be installed,
and whether this call put it there.

- `@return array{0: ?string, 1: bool}`

Internals: `fromDirectory()` (private, line 79), `archive()` (private, line 112), `present()` (private, line 138), `refuse()` (private, line 148)


## PluginCommand

`final class Minn\Cli\PluginCommand` · `public/minn/src/Minn/Cli/PluginCommand.php`

`wp plugin list|install|update|activate|deactivate|delete`: the inventory and the fleet's install/update/delete.

- const `FIELDS` = `array (   0 => 'name',   1 => 'status',   2 => 'update',   3 => 'version',   4 => 'update_version',   5 => 'auto_update', )`

Used by: `Minn\Cli\Commands`

### `list(array $args, array $assocArgs): void`

Lists installed plugins, must-use plugins, and drop-ins.

## OPTIONS

[--status=<status>]
: Filter to one status: active, inactive, must-use, or dropin.

[--field=<field>]
: Prints the value of a single field for each plugin.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- count
- yaml
---

### `activate(array $args, array $assocArgs): void`

Activates one or more plugins: a WordPress plugin joins active_plugins
(its stored state; the engine runs none of its code), a Minn extension
joins the engine's own list.

## OPTIONS

<plugin>...
: One or more plugins to activate.

### `deactivate(array $args, array $assocArgs): void`

Deactivates one or more plugins.

## OPTIONS

<plugin>...
: One or more plugins to deactivate.

### `install(array $args, array $assocArgs): void`

Installs one or more plugins from wordpress.org, a zip, or a URL.

## OPTIONS

<plugin|zip|url>...
: A plugin slug, a local zip path, or a zip URL.

[--version=<version>]
: Install that wordpress.org version instead of the current one.

[--force]
: Overwrite an installed copy of the same folder.

[--activate]
: Activate the plugin after it is installed (or if it is already on disk).

### `update(array $args, array $assocArgs): void`

Updates one or more plugins from wordpress.org.

## OPTIONS

[<plugin>...]
: One or more plugins to update.

[--all]
: If set, all plugins that have updates will be updated.

[--exclude=<name>]
: Comma separated list of plugin names that should be excluded from updating.

[--minor]
: Only perform updates for minor releases.

[--patch]
: Only perform updates for patch releases.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- summary
---

[--version=<version>]
: If set, the plugin will be updated to the specified version.

[--dry-run]
: Preview which plugins would be updated.

### `delete(array $args, array $assocArgs): void`

Deletes plugin files without deactivating.

## OPTIONS

[<plugin>...]
: One or more plugin folders to delete.

### `is_installed(array $args, array $assocArgs): void`

Checks if a given plugin is installed. Exit 0 when it is, 1 when not.

## OPTIONS

<plugin>
: The plugin folder to check.

### `is_active(array $args, array $assocArgs): void`

Checks if a given plugin is active. Exit 0 when it is, 1 when not.

## OPTIONS

<plugin>
: The plugin folder to check.

[--network]
: Ignored on a single site.

### `search(array $args, array $assocArgs): void`

Searches the wordpress.org plugin directory.

## OPTIONS

<search>
: The string to search for.

[--page=<page>]
: Optional page to display.
---
default: 1
---

[--per-page=<per-page>]
: Optional number of results to display.
---
default: 10
---

[--field=<field>]
: Prints the value of a single field for each plugin.

[--fields=<fields>]
: Limit the output to specific object fields. Defaults to name,slug,rating.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- count
- yaml
---

Internals: `switch()` (private, line 326), `pinVersion()` (private, line 360), `activateFolder()` (private, line 384)


## Preflight

`final class Minn\Cli\Preflight` · `public/minn/src/Minn/Cli/Preflight.php`

What a site will and will not get from the engine, before anything
moves: the config it can read, the database it can reach, the theme's
kind, the plugins the extensions cover, and the content survey (the
shortcodes, third-party blocks, menus, extra tables, and extra post
types in the database). Every finding is a light; the worst decides.

Used by: `Minn\Cli\Installer`


### `lines(): array`

The report, one line per finding. @return list<string>

- `@return list<string>`

### `run(string $root): string`

Runs every check and returns the worst light: GREEN, AMBER, or RED.

### static `themeKind(string $dir, string $parentDir): string`

Same line Theme::active / ClassicTheme::active draw: a block template
index (child or parent) is a block theme; otherwise a parent index.php
is a classic theme.

Internals: `readConfig()` (private, line 89), `env()` (private, line 123), `optionReader()` (private, line 134), `preflightTheme()` (private, line 146), `preflightPlugins()` (private, line 185), `surveyContent()` (private, line 248), `surveyMarkup()` (private, line 257), `surveyMenus()` (private, line 298), `surveyTables()` (private, line 325), `surveyTypes()` (private, line 344), `light()` (private, line 366), `say()` (private, line 375)


## RewriteCommand

`final class Minn\Cli\RewriteCommand` · `public/minn/src/Minn/Cli/RewriteCommand.php`

`wp rewrite flush|structure`: permalink_structure is the engine's
resolver input; there is no .htaccess rewrite file to regenerate.

Used by: `Minn\Cli\Commands`

### `flush(array $args, array $assocArgs): void`

Flushes rewrite rules. The engine resolves URLs from options, so
this is a success no-op the way `cache flush` is.

## OPTIONS

[--hard]
: Ignored: the engine does not write .htaccess.

### `structure(array $args, array $assocArgs): void`

Updates the permalink structure.

## OPTIONS

<permastruct>
: The new permalink structure, stored as permalink_structure.

[--category-base=<base>]
: Stored as category_base.

[--tag-base=<base>]
: Stored as tag_base.

[--hard]
: Ignored: the engine does not write .htaccess.


## Runtime

`final class Minn\Cli\Runtime` · `public/minn/src/Minn/Cli/Runtime.php`

The engine, booted for a command: reads the site's wp-config.php (which
ends in wp-settings.php, whose engine boot stops short of serving under
WP-CLI) so the database constants and table prefix are known.

Used by: `Minn\Cli\AssetUpdate`, `Minn\Cli\CronCommand`, `Minn\Cli\DirectorySearch`, `Minn\Cli\MaintenanceCommand`, `Minn\Cli\MinnCommand`, `Minn\Cli\OptionCommand`, `Minn\Cli\PluginCommand`, `Minn\Cli\RewriteCommand`, `Minn\Cli\SearchReplaceCommand`, `Minn\Cli\ThemeCommand`, `Minn\Cli\UserCommand`

- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `Minn\Content\Users $users`
- readonly `Minn\Auth\Capabilities $capabilities`
- readonly `Minn\Front\Permalinks $permalinks`

### static `boot(): self`

The engine's runtime for a CLI process, booted once.

### static `bootEngine(): Minn\Runtime\Runtime`

The full WordPress runtime for a command, booted once: the facade is
defined, the site's plugins load as code, and the lifecycle actions
fire, so a verb can fire a scheduled hook whose callback a plugin
registered. The lighter boot() does none of this; only a command that
needs the runtime (cron) asks for this, since it loads every plugin.

### static `standIn(): void`

The runtime for a command WP-CLI loads WordPress for: WP-CLI has already
run wp-config.php, so the engine boots from the constants it defined,
loads the site's plugins as code (their commands register as they load),
and hands control back for WP-CLI to run the command.

Internals: `loadConfig()` (private, line 96)


## SearchReplaceCommand

`final class Minn\Cli\SearchReplaceCommand` · `public/minn/src/Minn/Cli/SearchReplaceCommand.php`

`wp search-replace`: walks every string column, including serialized
PHP arrays, and reports replacements the way the reference does.

- const `SKIP_TYPES` = `'/^(tinyint|smallint|mediumint|int|bigint|float|double|decimal|bit|date|time|datetime|timestamp|year)/i'`

Used by: `Minn\Cli\Commands`

### `__invoke(array $args, array $assocArgs): void`

Searches/replaces strings in the database.

## OPTIONS

<old>
: A string to search for.

<new>
: Replace instances of the first string with this new string.

[<table>...]
: Restrict the replacement to these tables.

[--dry-run]
: Report without writing.

[--all-tables]
: Search every table in the database, not only those with the site prefix.

[--report-changed-only]
: Only print tables/columns that changed.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- json
---

Internals: `listTables()` (private, line 91), `stringColumns()` (private, line 105), `replaceColumn()` (private, line 119)


## ThemeCommand

`final class Minn\Cli\ThemeCommand` · `public/minn/src/Minn/Cli/ThemeCommand.php`

`wp theme list|install|update|activate|delete`: the inventory CaptainCore
reads, and the install/update the fleet's `wp theme` verbs run.

- const `FIELDS` = `array (   0 => 'name',   1 => 'status',   2 => 'update',   3 => 'version',   4 => 'update_version',   5 => 'auto_update', )`

Used by: `Minn\Cli\Commands`

### `list(array $args, array $assocArgs): void`

Lists installed themes.

## OPTIONS

[--status=<status>]
: Filter to one status: active, inactive, or parent.

[--field=<field>]
: Prints the value of a single field for each theme.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- count
- yaml
---

### `install(array $args, array $assocArgs): void`

Installs one or more themes from wordpress.org, a zip, or a URL.

## OPTIONS

<theme|zip|url>...
: A theme slug, a local zip path, or a zip URL.

[--version=<version>]
: Install that wordpress.org version instead of the current one.

[--force]
: Overwrite an installed copy of the same folder.

[--activate]
: Activate the theme after it is installed (or if it is already on disk).

### `update(array $args, array $assocArgs): void`

Updates one or more themes from wordpress.org.

## OPTIONS

[<theme>...]
: One or more themes to update.

[--all]
: If set, all themes that have updates will be updated.

[--exclude=<theme-names>]
: Comma separated list of theme names that should be excluded from updating.

[--minor]
: Only perform updates for minor releases.

[--patch]
: Only perform updates for patch releases.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- summary
---

[--version=<version>]
: If set, the theme will be updated to the specified version.

[--dry-run]
: Preview which themes would be updated.

### `activate(array $args, array $assocArgs): void`

Activates a theme.

## OPTIONS

<theme>
: The theme folder to activate.

### `delete(array $args, array $assocArgs): void`

Deletes one or more themes from disk.

## OPTIONS

[<theme>...]
: One or more theme folders to delete.

[--force]
: Allow deleting the active theme.

### `is_installed(array $args, array $assocArgs): void`

Checks if a given theme is installed. Exit 0 when it is, 1 when not.

## OPTIONS

<theme>
: The theme folder to check.

### `is_active(array $args, array $assocArgs): void`

Checks if a given theme is active. Exit 0 when it is, 1 when not.

## OPTIONS

<theme>
: The theme folder to check.

### `search(array $args, array $assocArgs): void`

Searches the wordpress.org theme directory.

## OPTIONS

<search>
: The string to search for.

[--page=<page>]
: Optional page to display.
---
default: 1
---

[--per-page=<per-page>]
: Optional number of results to display. Defaults to 10.

[--field=<field>]
: Prints the value of a single field for each theme.

[--fields=<fields>]
: Limit the output to specific object fields. Defaults to name,slug,rating.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- count
- yaml
---

Internals: `pinVersion()` (private, line 227), `switchTo()` (private, line 249)


## UserCommand

`final class Minn\Cli\UserCommand` · `public/minn/src/Minn/Cli/UserCommand.php`

Users: the list and get views, and the one-time login link.

- const `FIELDS` = `array (   0 => 'ID',   1 => 'user_login',   2 => 'display_name',   3 => 'user_email',   4 => 'user_registered',   5 => 'roles', )`

Used by: `Minn\Cli\Commands`

### `list(array $args, array $assocArgs): void`

Lists users.

## OPTIONS

[--role=<role>]
: Only display users with a certain role.

[--field=<field>]
: Prints the value of a single field for each user.

[--fields=<fields>]
: Limit the output to specific object fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- ids
- json
- count
- yaml
---

### `get(array $args, array $assocArgs): void`

Gets details about a user.

## OPTIONS

<user>
: User ID, user email, or user login.

[--field=<field>]
: Instead of returning the whole user, returns the value of a single field.

[--fields=<fields>]
: Get a specific subset of the user's fields.

[--format=<format>]
: Render output in a particular format.
---
default: table
options:
- table
- csv
- json
- yaml
---

### `login(array $args, array $assocArgs): void`

Prints a one-time login link for a user.

The link signs the user in at wp-login.php once, within fifteen
minutes, and then expires (the contract of the captaincore helper).

## OPTIONS

<user>
: User ID, user email, or user login.

### `create(array $args, array $assocArgs): void`

Creates a new user.

## OPTIONS

<user-login>
: The login of the user to create.

<user-email>
: The email address of the user to create.

[--role=<role>]
: The role of the user to create. Default: the site's default role.

[--user_pass=<password>]
: The user password. Default: randomly generated.

[--display_name=<name>]
: The display name.

[--user_nicename=<nice_name>]
: A URL-friendly name. Default: the login.

[--user_url=<url>]
: The user's URL.

[--nickname=<nickname>]
: The nickname. Default: the login.

[--first_name=<first_name>]
: The first name.

[--last_name=<last_name>]
: The last name.

[--description=<description>]
: A description.

[--send-email]
: Send the new user a password-reset email.

[--porcelain]
: Output just the new user id.

### `update(array $args, array $assocArgs): void`

Updates an existing user.

## OPTIONS

<user>
: User ID, email, or login.

[--user_pass=<password>]
: A new password.

[--user_email=<email>]
: A new email.

[--display_name=<name>]
: A new display name.

[--first_name=<first_name>]
: A new first name.

[--last_name=<last_name>]
: A new last name.

[--role=<role>]
: A new role.

### `delete(array $args, array $assocArgs): void`

Deletes a user.

## OPTIONS

<user>
: User ID, email, or login.

[--reassign=<id>]
: User ID to reassign posts to.

[--yes]
: Answer yes to the confirmation.

Internals: `randomPassword()` (private, line 347), `find()` (private, line 358), `item()` (private, line 369)

