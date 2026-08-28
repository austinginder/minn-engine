# WP-CLI on the engine

The `wp` binary itself runs unchanged (WP-CLI is MIT). `public/wp-cli.yml` requires
`minn/cli.php`, which registers the engine's verbs for WP-CLI's `before_wp_load`
phase. WP-CLI runs that phase before it looks for a WordPress install, so the verbs
below work on a webroot with no `wp-load.php` and no `wp-includes/`; anything else
still fails with WP-CLI's own "This does not seem to be a WordPress installation".
Code: `public/minn/src/Minn/Cli/`. Suite: `tests/cli.test.php` runs every verb
against the engine's webroot and the parked reference on the same database and
compares stdout and the exit code.

## How the override works

WP-CLI loads `require:` files before it registers its bundled commands. A leaf
registered under a parent that does not exist yet (`option get` before `option`) is
deferred, added after the bundle, and replaces the bundle's leaf of the same name.
`@when before_wp_load` on the method (and `when` in the registration) puts the verb in
the early phase. WP-CLI also registers `plugin` as a command namespace before the
class command; the namespace would otherwise keep WP-CLI's `list` (whose constructor
requires `wp-admin/includes/plugin.php`). Each leaf is re-added on
`after_add_command:{parent}` so the engine's list is the one that stays. The command boots the engine on first use (`Minn\Cli\Runtime`):
it requires the site's `wp-config.php`, whose trailing `wp-settings.php` require
reaches `minn/bootstrap.php`, which under WP-CLI only registers the autoloader.
`$table_prefix` is copied to the global the engine reads.

## Verbs

| Verb | Behaviour matched |
|---|---|
| `option get <key> [--format=var_export\|json\|yaml]` | Missing key: `Error: Could not get 'x' option. Does it exist?` (exit 1). Serialized values decode through the engine's own reader (`Serialized::decode`: scalars and arrays, objects refused) and print through WP-CLI's formatter, so `sticky_posts` reads `array (\n  0 => 5,\n)` and `[5]` as JSON; a plain string stays a string (`posts_per_page --format=json` is `"10"`). |
| `option add <key> [<value>] [--format] [--autoload]` | `Success: Added 'x' option.`; existing key: `Error: Could not add option 'x'. Does it already exist?`. Stored with `autoload = on`. |
| `option update <key> [<value>] [--format] [--autoload]` | Value from the argument or STDIN; `--format=json` decodes. Same value: `Success: Value passed for 'x' option is unchanged.`; otherwise `Success: Updated 'x' option.`. A key that did not exist is inserted with `autoload = auto`. Arrays are stored serialized. |
| `option delete <key>...` | `Success: Deleted 'x' option.` per key; a missing key is a `Warning: Could not delete 'x' option. Does it exist?` and the exit code stays 0. |
| `user list [--role] [--field] [--fields] [--format]` | Ordered by `user_login`; fields `ID,user_login,display_name,user_email,user_registered,roles`; `roles` joined with `,`; `ids` and `count` formats; `--role` filters. Only roles registered in `wp_user_roles` count (a plugin-granted role the registry does not know is not listed, as on the reference). |
| `user get <id\|email\|login> [--field] [--fields] [--format]` | Record order `ID,user_login,user_email,user_registered,display_name,roles`, `ID` as the stored string, roles joined with `, `. Unknown: `Error: Invalid user ID, email or login: 'x'`. |
| `user login <id\|email\|login>` | Prints `{siteurl}/wp-login.php?user_id=N&cove_login_token=T` (7 hex chars) after storing the token's sha256 in `cove_login_token` and the mint time in `cove_login_token_time`: the contract of the captaincore helper mu-plugin, which the engine honours natively at `wp-login.php` (valid for fifteen minutes, spent on use, 302 to `/wp-admin/` with a fourteen-day session; any failure is the same 403 so ids cannot be probed, and failures count toward the sign-in throttle). Unknown: `Error: User not found: x`. |
| `user create <login> <email> [--role] [--user_pass] [--first_name] [--last_name] [--display_name] [--send-email] [--porcelain]` | Writes a real `$wp$2y$10$` hash and the 14 default meta rows. Duplicate login: `Error: The 'x' username is already registered.`; duplicate email: `Error: Sorry, that email address is already used!`; unknown role: `Error: Role doesn't exist: x`. No `--user_pass` prints a generated `Password:` line. `--porcelain` prints only the id. First+last names become the display name when `--display_name` is omitted. `--send-email` sends the engine's password-reset mail. |
| `user update <user> [--display_name] [--user_email] [--user_pass] [--role] [--first_name] [--last_name]` | Unknown: `Warning: Invalid user ID, email or login: 'x'` then `Error: No valid users found.` Success: `Updated user N.` |
| `user delete <user> [--reassign=<id>] [--yes]` | `--reassign` moves posts; without it the user's posts are deleted. Success: `Removed user N from {home}.` |
| `cache flush` | No object cache: `Success: The cache was flushed.` so fleet scripts that call it after a write do not abort. |
| `option set` | Alias of `option update`. |
| `search-replace <old> <new> [<table>...] [--dry-run] [--all-tables] [--report-changed-only]` | Walks string columns (skips `guid` and numeric types), including serialized-PHP arrays of scalars without unserialize. Report columns Table/Column/Replacements/Type (PHP). Identical strings: `Warning: Replacement value 'x' is identical to search value 'x'. Skipping operation.` CaptainCore's https/launch/migrate scripts pass `--all-tables --report-changed-only`. |
| `wp minn version`, `wp minn info` | The engine's own identity. |
| `plugin list [--status] [--field] [--fields] [--format]` | Installed plugins from disk plus must-use and drop-ins, in that order, each group sorted by name. `name` is the directory (or the drop-in filename); `title` and `version` come from the file headers; `status` is `active` / `inactive` / `must-use` / `dropin`. Drop-ins keep an empty version (the reference does too). `update` is `none` for regular plugins and `false` for must-use and drop-ins: the engine does not call the wordpress.org update API. CaptainCore's `fetch-site-data` reads `--format=json --fields=name,title,status,version`. |
| `theme list [--status] [--field] [--fields] [--format]` | Themes from `style.css` headers, sorted by name. The stylesheet is `active`; its parent (when different) is `parent`; the rest are `inactive`. |
| `wp minn probe` | `key:value` lines for `engine`, `engine_version`, `core`, `home_url`, `php_version`, `plugins`, `themes`, `mu_plugins`. JSON values have no newlines; split on the first colon. This is the engine-native stand-in for the inventory `fetch-site-data` currently gathers via `wp plugin list` / `wp theme list` / `wp core version` inside WordPress. |

## Not yet

- `wp db …` and `wp config …` are WP-CLI's own and read `wp-config.php` directly.
- `wp core update` and `wp core verify-checksums` wait until Minn has a release
  of its own. They become the engine's updater and integrity check, not a
  rewrite of WordPress core commands. Until then the bundle's copies refuse
  because they need WordPress.
- `post` and `rewrite` are absent; the list grows from what the fleet actually invokes.
- `option get` of an option holding a serialized object prints the raw blob.
