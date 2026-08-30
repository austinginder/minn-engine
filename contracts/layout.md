# File layout

What sits at the webroot, what reads it, and what is deliberately absent.
Suite: `tests/layout.test.php`.

## Shape files

Four files at the webroot are the engine's side of the file-layout contract. Their
templates live in `public/minn/layout/` (the suite asserts the copies match), and
`minn/bin/minn install` is the command that parks WordPress, drops `minn/` in, and
copies them over; `minn eject` reverses it (`docs/install.md`, `tests/install.test.php`).

| File | Who reads it | What the engine ships |
|---|---|---|
| `index.php` | Web server, WP-CLI (`extract_subdir_path`) | The stock front controller shape: requires `wp-config.php`. |
| `wp-config.php` | Every backup/migration tool (regex), WP-CLI (`config` and `db` commands eval it with the trailing require stripped) | Never shipped, never edited: the file WordPress generated stays. |
| `wp-settings.php` | `wp-config.php` (its last line), WP-CLI when loading WordPress | Two lines: `require minn/bootstrap.php`. Under WP-CLI the boot stops after the autoloader; reached for a command the engine does not answer it prints the engine's refusal (below). |
| `wp-cli.yml` | WP-CLI (project config) | `require: minn/cli.php`, the engine's verbs. |
| `wp-includes/version.php` | WP-CLI (`wp_exists()` is `file_exists` on it; `check_wp_version()` includes it and wants `$wp_version >= 3.7`; `core version` parses `$wp_version`, `$wp_db_version`, `$tinymce_version`, `$wp_local_package` by string search), hosting panels, backup tools | The release whose contracts the engine speaks (`7.1`, db revision `61833`), as plain assignments in the shape readers expect. No WordPress code. |

## WP-CLI phases and what they need

| Phase | Needs | On the engine |
|---|---|---|
| `before_wp_load` | nothing on disk | The engine's verbs (`contracts/cli.md`); WP-CLI's own `config get/list/path`, `core version [--extra]`, `cli …`. |
| `after_wp_config_load` | `wp-includes/version.php` + `wp-config.php` | WP-CLI's `db query/export/import/check/optimize/repair/reset/drop/create`, straight to MySQL with the config's credentials. |
| `after_wp_load` | WordPress | Refused: `Error: This command needs WordPress itself, which Minn Engine does not contain.` followed by the list of verbs that do work. Covers leftover bundle leaves (`plugin update`, `theme update`/`search`, `post`, `core is-installed/update`, `db tables/size`), not the engine's own plugin/theme/rewrite/cache verbs. |

## Deliberately absent

- `wp-load.php`, `wp-blog-header.php`, `wp-admin/`, `xmlrpc.php` files: nothing at the
  webroot runs WordPress. WP-CLI finds the root through `index.php` or the working
  directory instead; `/wp-admin/` and `/xmlrpc.php` are answered by the router
  (`contracts/front/probes.md`).
- `wp-includes/` holds only `version.php`. A tool that lists or checksums core files
  (`wp core verify-checksums`, CaptainCore's `core_file_hashes`) sees an install with
  one file; that is the truthful signal, and `minn preflight` (Track C) is where it is
  explained to an operator.

## What the fleet tooling was found to read

Inventory taken 2026-08-28 from CaptainCore (Go CLI and remote scripts), the
CaptainCore Manager plugin, and Disembark:

- CaptainCore runs `wp option get home` (with `--skip-plugins --skip-themes`, and with
  `--url=`) on client sites over SSH; `wp option set`, `wp user create`, `wp plugin
  install`, `wp theme install`, `wp plugin verify-checksums` in specific commands.
- The WordPress version, plugin and theme inventories, and file hashes in quicksave come
  from the Manager's remote scripts (`fetch-site-data`), which run `wp plugin list`,
  `wp theme list`, `wp core version`, and `wp eval-file` probes inside WordPress.
  On the engine the inventory verbs and `wp minn probe` answer the same shapes
  (`contracts/cli.md`). File hashes (`component-hashes`, `quicksave-fingerprint`)
  are already bash over the filesystem and do not need WordPress. Remaining:
  `core verify-checksums` / `plugin verify-checksums` (honest: the engine does
  not ship those files) and the eval-file extras (error logs, session signal).
- Disembark's phar reads none of the shape files by name; it works from the database
  credentials it is given.
