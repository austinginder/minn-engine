# `Minn\Cli`

the wp verbs the engine answers itself

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AssetUpdate`](#assetupdate) | final class | 321 | Shared wording for `wp plugin update` and `wp theme update`. Captured |
| [`CacheCommand`](#cachecommand) | final class | 15 | `wp cache flush`: the engine has no object cache, so this is a no-op success. |
| [`Commands`](#commands) | final class | 86 | The verbs the engine answers to. Every one is registered for the |
| [`DirectorySearch`](#directorysearch) | final class | 33 | Shared wording for `wp theme search` and `wp plugin search`. The |
| [`Installer`](#installer) | final class | 577 | The swap, both ways. Install parks WordPress's own files beside the |
| [`MaintenanceCommand`](#maintenancecommand) | final class | 65 | `wp maintenance-mode`: the `.maintenance` marker in the webroot. The |
| [`MinnCommand`](#minncommand) | final class | 302 | Identifies the engine. |
| [`OptionCommand`](#optioncommand) | final class | 191 | Options, read and written straight to the options table. Serialized |
| [`PluginCommand`](#plugincommand) | final class | 474 | `wp plugin list\|install\|update\|activate\|deactivate\|delete`: the inventory and the fleet's install/update/delete. |
| [`RewriteCommand`](#rewritecommand) | final class | 52 | `wp rewrite flush\|structure`: permalink_structure is the engine's |
| [`Runtime`](#runtime) | final class | 37 | The engine, booted for a command: reads the site's wp-config.php (which |
| [`SearchReplaceCommand`](#searchreplacecommand) | final class | 143 | `wp search-replace`: walks every string column, including serialized |
| [`ThemeCommand`](#themecommand) | final class | 425 | `wp theme list\|install\|update\|activate\|delete`: the inventory CaptainCore |
| [`UserCommand`](#usercommand) | final class | 366 | Users: the list and get views, and the one-time login link. |

## AssetUpdate

`final class Minn\Cli\AssetUpdate` · `public/minn/src/Minn/Cli/AssetUpdate.php`

Shared wording for `wp plugin update` and `wp theme update`. Captured
from the reference: missing plugins warn and count as failed; a missing
theme is a fatal Error and stops. json/csv print only the rows (empty
stdout when nothing changed). `--exclude` always prints the skipped
line in table/summary, even when the list is empty.

```php
__construct(string $kind, Minn\Admin\Updates $updates, Minn\Content\Inventory $inventory)
```

### static `boot(string $kind): self`

### `run(array $names, array $assocArgs): void`

- `@param list<string> $names`


## CacheCommand

`final class Minn\Cli\CacheCommand` · `public/minn/src/Minn/Cli/CacheCommand.php`

`wp cache flush`: the engine has no object cache, so this is a no-op success.

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


## DirectorySearch

`final class Minn\Cli\DirectorySearch` · `public/minn/src/Minn/Cli/DirectorySearch.php`

Shared wording for `wp theme search` and `wp plugin search`. The
default table is name/slug/rating; Success prints before the rows and
only for table/yaml. json/csv/count print the Formatter output alone.

### static `run(string $kind, array $args, array $assocArgs): void`

- `@param array<string, mixed> $assocArgs`


## Installer

`final class Minn\Cli\Installer` · `public/minn/src/Minn/Cli/Installer.php`

The swap, both ways. Install parks WordPress's own files beside the
webroot, lays the engine and its shape files down, and leaves
wp-config.php and wp-content untouched; eject puts every parked file
back and removes what install wrote. A manifest in minn/.install.json
is the record eject works from. Preflight says what the site will and
will not get before anything moves.

### static `main(array $argv, string $engineDir): int`

- `@param list<string> $argv`

### static `themeKind(string $dir, string $parentDir): string`

Same line Theme::active / ClassicTheme::active draw: a block template
index (child or parent) is a block theme; otherwise a parent index.php
is a classic theme.

### `status(string $root): int`

### `preflight(string $root): string`

What the site will and will not get; the worst light decides install.

### `install(string $root, array $options): int`

- `@param array<string, string|true> $options`

### `eject(string $root): int`


## MaintenanceCommand

`final class Minn\Cli\MaintenanceCommand` · `public/minn/src/Minn/Cli/MaintenanceCommand.php`

`wp maintenance-mode`: the `.maintenance` marker in the webroot. The
engine does not take the public site down while the file is present;
fleet scripts still need the verb to succeed so an update can bookend
itself with activate/deactivate.

### `activate(array $args, array $assocArgs): void`

Activates maintenance mode.

### `deactivate(array $args, array $assocArgs): void`

Deactivates maintenance mode.

### `status(array $args, array $assocArgs): void`

Displays maintenance mode status.

### `is_active(array $args, array $assocArgs): void`

Detects maintenance mode status. Exit 0 when active, 1 when not.


## MinnCommand

`final class Minn\Cli\MinnCommand` · `public/minn/src/Minn/Cli/MinnCommand.php`

Identifies the engine.

## EXAMPLES

wp minn version
wp minn info
wp minn probe

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


## OptionCommand

`final class Minn\Cli\OptionCommand` · `public/minn/src/Minn/Cli/OptionCommand.php`

Options, read and written straight to the options table. Serialized
values are decoded by the engine's own reader, and arrays written back
in the stored form.

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


## PluginCommand

`final class Minn\Cli\PluginCommand` · `public/minn/src/Minn/Cli/PluginCommand.php`

`wp plugin list|install|update|activate|deactivate|delete`: the inventory and the fleet's install/update/delete.

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


## RewriteCommand

`final class Minn\Cli\RewriteCommand` · `public/minn/src/Minn/Cli/RewriteCommand.php`

`wp rewrite flush|structure`: permalink_structure is the engine's
resolver input; there is no .htaccess rewrite file to regenerate.

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

- readonly `Minn\Db $db`
- readonly `Minn\Content\Site $site`
- readonly `Minn\Content\Users $users`
- readonly `Minn\Auth\Capabilities $capabilities`
- readonly `Minn\Front\Permalinks $permalinks`

### static `boot(): self`


## SearchReplaceCommand

`final class Minn\Cli\SearchReplaceCommand` · `public/minn/src/Minn/Cli/SearchReplaceCommand.php`

`wp search-replace`: walks every string column, including serialized
PHP arrays, and reports replacements the way the reference does.

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


## ThemeCommand

`final class Minn\Cli\ThemeCommand` · `public/minn/src/Minn/Cli/ThemeCommand.php`

`wp theme list|install|update|activate|delete`: the inventory CaptainCore
reads, and the install/update the fleet's `wp theme` verbs run.

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


## UserCommand

`final class Minn\Cli\UserCommand` · `public/minn/src/Minn/Cli/UserCommand.php`

Users: the list and get views, and the one-time login link.

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

