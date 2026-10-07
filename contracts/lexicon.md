# The lexicon

Minn needs to speak the language WordPress software uses with hosts,
plugins, and tools. It does not need to implement every word that
software speaks.

WordPress software is a language with three audiences. The engine answers two of
them. The third belongs to Minn Admin. This file is the policy the
inventory in `contracts/api/` does not have: which families Minn
implements, which it recognizes so plugins can load, and which it will
never host.

Agents read this before adding a runtime symbol. An inventory with no mute
list reads as a to-do. That is how `/wp-admin/` chrome keeps getting pulled
in.

## Three audiences

| Audience | What WordPress says to them | Minn |
|---|---|---|
| **Tooling** | schema, `wp-config.php`, `wp/v2`, WP-CLI, probes | **Speak.** This is Tier 1. |
| **Plugin PHP** | hooks, options, `WP_Query`, `$wpdb`, `register_rest_route` | **Speak** enough that unmodified plugins load. This is Tier 2. |
| **Humans in a browser** | `/wp-admin/` chrome, list tables, meta boxes, dashboard, Customizer, Gutenberg app | **Mute.** Minn Admin is the only UI. |

`/wp-admin/` is not a Minn surface. The path exists so monitors do not 404:
it 302s to `/minn-admin/`. Nothing behind it is rendered.

## Three statuses

| Status | Meaning | Test |
|---|---|---|
| **Speak** | Implement at oracle parity (or a documented subset). Fixture plus live diff. | A host, a backup tool, Minn Admin, or a front-end plugin cannot tell. |
| **Hear** | Recognize, record, stub, redirect, or refuse honestly. Enough that plugin load does not fatal. No product UI. | A plugin that calls it at `init` still loads. A human never sees a WordPress screen. |
| **Mute** | Named refusal. Do not implement. Do not host. An adapter, a 302, a 403, or a 404 is the answer. | The name stays in `data/placeholder-symbols.json`. It does not graduate into real `wp-api/` just to shrink the skip list. |

**The load-bearing split is admin registration (Hear) versus admin UI (Mute).**
Plugins call `add_menu_page` during `admin_menu`. The engine records the
row. It never paints `.wrap`. Minn Admin adapters own the human surface.

"Any plugin just works" means front end and data. It does not mean the
plugin's PHP settings form appears.

## How a name graduates

A placeholder becomes real `wp-api/` only when all three are true:

1. A **front** or **REST** plugin needs non-neutral behaviour.
2. Minn Admin (or CLI / ops) already owns the human UI for that job.
3. The implementation lives in `src/Minn/` with a thin facade, not a
   wp-admin screen.

If the only caller is `wp-admin/includes/*` or a plugin's `admin_menu`
callback, leave the placeholder. `PlaceholderTrace` (the opt-in log at
`wp-content/minn-placeholder-trace.log`) is how to prove that.

The name lists:

- Speak names the symbol gate counts: `public/minn/data/api-names.json`
- Hear names that exist as stubs: `public/minn/data/placeholder-symbols.json`
  (the generator skips any name `wp-api/` now writes by hand)
- Mute names must not leave that stub file without passing the three tests
  above

`contracts/api/*.json` is the dictionary of words WordPress speaks. This
file is the Minn column.

---

## Speak

Families the engine implements. Subset gaps live in the linked contract,
not here.

### Operational contract (Tier 1)

| Family | WordPress words | Minn | Contract |
|---|---|---|---|
| Portable site unit | `wp_*` tables, `wp-content/`, dumps | Read and write the live schema. Tolerate serialized blobs forever. Extra plugin tables unread (preflight AMBER). | (planned `schema/`; used via `Minn\Db`) |
| Boot / file layout | `index.php`, `wp-config.php`, `wp-settings.php`, `wp-includes/version.php` | Shape files only. `wp-config.php` untouched. Engine boots from `wp-settings.php` → `minn/bootstrap.php`. No `wp-load.php` on disk. | `layout.md` |
| REST `wp/v2` | posts, pages, types, statuses, taxonomies, terms, users, comments, media, search, settings, plugins, themes, menus, menu items and locations, navigation, templates, template parts, global styles, blocks, block types, widgets, sidebars, revisions, autosaves, application-passwords; `batch/v1`; `wp-abilities/v1` | Core routes Minn Admin and tooling call. Plugin routes via `register_rest_route`. | `rest/*.md` |
| Auth | cookies, `session_tokens`, `wp_rest` nonce, `$wp$` hashes, capabilities | Cross-accepted both directions. | `rest/auth.md`, `rest/caps.md` |
| Login endpoint | `wp-login.php` | Probe, session, registration and password-reset surface, with plugins' login hooks. Human page is `/minn-admin/login`. Bare GET 302s there. | `rest/auth.md` |
| Permalinks | `permalink_structure`, `?p=`, archives, `_wp_old_slug` | Resolver plus every `link` field. | `front/permalinks.md` |
| Feeds / sitemaps / probes | `/feed/`, `/wp-sitemap.xml`, `robots.txt`, `wp-cron.php` | Byte-level where the contract says so. | `front/probes.md` |
| Block render | `post_content` block markup, `content.rendered` | Engine parser and renderer. Block templates are read as data. | `blocks.md`, `front/theme.md` |
| Block themes | `theme.json`, `templates/`, `parts/`, `wp_template*` | Read as data. | `front/theme.md` |
| Classic PHP themes | `get_header()`, PHP template hierarchy | Hierarchy, `wp_head` defaults, live body parity. | `front/classic.md` |
| WP-CLI ops | `option`, `user`, `plugin list/install/update/activate/delete/search`, `theme list/install/update/activate/delete/search`, `rewrite flush/structure`, `maintenance-mode`, `db`, `search-replace`, `wp minn *` | `before_wp_load` verbs plus WP-CLI's own `config`/`db`. Every other command, a plugin's own included, runs on the engine's runtime. | `cli.md` |
| Cron / mail | due `future` posts, `wp-cron.php`, `wp_mail`, password reset | Engine runner. Due `cron` option events fire on every trigger. `wp_mail` runs on the engine's own PHPMailer. | `cron-mail.md` |
| Install / eject | five-minute install, core files on disk | `minn install` / `eject`. Not `install.php`. | `docs/install.md` |

### Runtime, plugin PHP (Tier 2)

| Family | WordPress words | Minn | Contract |
|---|---|---|---|
| Hooks + lifecycle | `add_action`, `add_filter`, `muplugins_loaded` → `wp_loaded`, `wp_head`, `the_content` | Clean-room hook engine. Observed order. | `runtime.md`, `api/lifecycle.json` |
| Options / transients / cache | `get_option`, `set_transient`, object cache | Decoded without `unserialize()`. | `runtime.md` |
| Posts / query / terms / types | `WP_Query`, `wp_insert_post`, `register_post_type`, `WP_Term` | Facade over `src/Minn/`. | `runtime.md` |
| Users / caps / meta | `WP_User`, `current_user_can`, `get_user_meta` | Same identity stack as REST. | `runtime.md`, `rest/caps.md` |
| `$wpdb` / `dbDelta` | `$wpdb->prepare`, `dbDelta` | Always present (the gate cannot see `$wpdb`). | `runtime.md` |
| Media API | `wp_get_attachment_image_src`, subsizes, srcset | GD. Imagick is not used. | `runtime.md`, `rest/media.md` |
| REST registration | `register_rest_route`, `WP_REST_Server`, `rest_do_request` | Plugin routes after the engine's. In-process `wp/v2` hits the engine. | `runtime.md` |
| Blocks API | `register_block_type`, `parse_blocks`, `block.json`, `render_block` | Two-way bridge with the engine renderer. | `runtime.md`, `blocks.md` |
| Shortcodes / assets / i18n | `add_shortcode`, `wp_enqueue_script`, `__()` | Shortcodes after the engine pipeline. jQuery is MIT, ours. Text domains load from `.mo` and `.l10n.php` files. | `runtime.md` |
| HTTP / cron API | `wp_remote_get`, `wp_schedule_event`, `WpOrg\Requests` | The engine's own HTTP client, the Requests library included; option-shaped cron. | `runtime.md` |
| HTML API | `WP_HTML_Tag_Processor`, `WP_HTML_Processor` | Maps to `Minn\Html\Tags`. `WP_HTML_Processor` builds the reference's tree. | `runtime.md` |
| `admin-ajax.php` | `wp_ajax_{action}`, `wp_ajax_nopriv_{action}`, heartbeat | Runs plugins' handlers as WordPress does. `is_admin()` is true there. | `runtime.md` |
| Widgets | `WP_Widget`, `register_widget`, the default widgets | Render in classic sidebars at the reference's output. | `runtime.md` |
| Rewrite rules | `add_rewrite_rule`, `add_rewrite_endpoint`, `$wp->query_vars` | Plugin rules and endpoints resolve requests. `$wp` holds the reference's request parse. | `runtime.md` |
| Interactivity | directives, script modules | Engine's own JS at `/minn/assets/*.js`. Editor packages are not provided. | `runtime.md` |

### Minn Admin (the human UI)

| Family | Words | Minn | Contract |
|---|---|---|---|
| Admin SPA | `/minn-admin/`, `window.MINN`, `minn-admin/v1` | Engine serves the shell and the namespace. The app is the plugin. | `minn-admin.md`, `rest/minn-admin-v1.md` |
| Front bar | signed-in editor bar on themed pages | Engine's own bar, not the WordPress admin bar. | `minn-admin.md` |

---

## Hear

Recognized so plugins boot. Never a screen.

| Family | WordPress words | What Minn does | What it does not do |
|---|---|---|---|
| Admin menu registration | `add_menu_page`, `add_submenu_page`, `add_*_page`, `$menu` | Records the row. Returns the reference's hook name. | Does not render a menu or host `admin.php?page=`. |
| Settings API (registration) | `register_setting`, `add_settings_section`, `add_settings_field` | Records. Markup helpers are pinned so a callback that builds HTML does not fatal. | Does not serve `options-general.php`. Minn Admin Settings is the UI. |
| Meta boxes / dashboard widgets | `add_meta_box`, `wp_add_dashboard_widget` | Stores `{id, title, callback}`. | Does not paint meta boxes or the dashboard. |
| `is_admin()` | `is_admin()`, `WP_Screen` | A request flag. False on public pages and on `/minn-admin/`; true on `admin-ajax.php`, as on WordPress. | Does not become true just so plugin admin-only code runs. |
| `admin-post.php` | form POST sink | Named stub. | Not a form host. |
| File skeleton | `wp-includes/*.php`, `wp-admin/includes/*.php` | One-line placeholders so `require ABSPATH . 'wp-admin/includes/…'` does not fatal. Gitignored. | Not WordPress source. Not a load path. |
| `admin_url()` | URL builder | Returns `/wp-admin/…` strings plugins write into data. | Those URLs 302 to Minn Admin. They are not screens. |
| Customizer classes | `WP_Customize_Manager` and its controls | Exist so plugins construct them. | Nothing is served. |
| `IXR_Client` | Jetpack connection plumbing | Inert placeholder. | Out of scope (2026-08-30). |

---

## Mute

Never implement as a product. Minn Admin already covers the daily jobs. The
rest is a 302, a 403, a preflight flag, or an adapter.

### `/wp-admin/` as a product

The path 302s. These families are the screens behind it. None of them are
coming.

| Family | WordPress screens | Minn Admin counterpart |
|---|---|---|
| Admin chrome | `admin.php`, `admin-header.php`, `menu.php`, `load-scripts.php`, `load-styles.php` | The SPA shell. No `.wrap`, no Dashicons, no wp-admin CSS. |
| Dashboard | `index.php`, About / Credits / Freedoms | `/minn-admin/overview` |
| Posts / pages / CPTs | `edit.php`, `post.php`, `post-new.php`, `edit-form-advanced.php`, `edit-form-blocks.php`, `revision.php` | `/minn-admin/content` + `/editor/{type}/{id}` |
| Comments | `edit-comments.php`, `comment.php` | `/minn-admin/comments` |
| Terms | `edit-tags.php`, `term.php` | `/minn-admin/terms` |
| Media library chrome | `upload.php`, `media.php`, `media-upload.php`, `async-upload.php`, Thickbox, Plupload | `/minn-admin/media` over `wp/v2/media`. No iframe uploader. |
| Appearance: themes | `themes.php`, `theme-install.php`, `theme-editor.php` | Extensions → Themes. Theme file editor is mute (a footgun). |
| Customizer | `customize.php`, `WP_Customize_*`, `custom-header.php`, `custom-background.php` | Identity, logo, homepage, language, Design (Additional CSS) in Settings. Other theme mods stay a link-out on WordPress; on the engine they are absent. |
| Site Editor (FSE app) | `site-editor.php`, `edit-site` JS | Mute SPA. Saved `wp_template` / `wp_template_part` / `wp_navigation` are front data (Speak). |
| Widgets screen | `widgets.php` | `/minn-admin/widgets` on classic themes only. |
| Nav menus screen | `nav-menus.php` | `/minn-admin/menus` on classic themes; `wp/v2/menus` as data. |
| Plugins screens | `plugins.php`, `plugin-install.php`, `plugin-editor.php` | `/minn-admin/extensions`. On the engine: no wp.org plugin search; zip-URL / GitHub / `minn.json` only. Plugin file editor is mute. |
| Users / profile screens | `users.php`, `user-edit.php`, `profile.php`, `authorize-application.php` | `/minn-admin/users`, `/profile`. Application-password *routes* are Speak; the grant *screen* is mute. |
| Settings screens | `options-general.php`, writing, reading, discussion, media, permalinks, privacy | `/minn-admin/settings` tabs (Site, Visibility, Homepage, Content, Comments, Design). |
| Tools / import / export / GDPR | `tools.php`, `import.php`, `export.php`, `export-personal-data.php`, `erase-personal-data.php` | System Tools card is a link-out on WordPress. **Hidden on the engine.** |
| Site Health screens | `site-health.php`, `site-health-info.php` | `/minn-admin/system` health checks. Full Site Health UI is mute. |
| Updates UI | `update-core.php`, `update.php`, upgrader skins | Extensions + Update everything. No upgrader iframe. |
| Install / repair wizards | `install.php`, `setup-config.php`, `upgrade.php` | `minn install`. |
| Links / blogroll | `link-manager.php` | Dead product. Mute. |
| Multisite | `network.php`, `wp-admin/network/*`, `wp-signup.php`, `wp-activate.php` | Out of scope. Single-site only. |

### Other mute product families

| Family | What WordPress ships | Minn stance |
|---|---|---|
| XML-RPC / pingback / trackback | `xmlrpc.php`, `IXR_*`, `wp-trackback.php` | GET 405, POST 403. No server, no client. |
| Gutenberg editor JS | `edit-post`, `block-editor`, `block-library` JS, `@wordpress/*` editor packages | Minn Admin's own editor. Do not ship those packages. |
| Classic editor / TinyMCE / Quicktags | `wp_editor()`, `js/tinymce/` | `wp_editor` stays a placeholder. |
| Dashicons + wp-admin CSS | `dashicons`, color schemes | No GPL assets. A REST `dashicons-*` *string* on a type is interface, not the font. |
| Heartbeat / pointers / WP admin bar | `heartbeat.js`, `wp-pointer`, default admin bar | People see only the Minn bar. `WP_Admin_Bar` and heartbeat are real for plugins. Pointers are mute. |
| CodeMirror file editors | theme-editor, plugin-editor | Mute. |
| wordpress.org directory / upgrader | `themes_api`, `WP_Upgrader*`, language-pack download UI | Mute as product. Themes search on the engine is a deliberate exception (themes are data the front renders). |
| Filesystem credentials modal | FTP/SSH prompt | `direct` only. No modal. |
| Press This / mail-by-post / OPML | `press-this.php`, `wp-mail.php`, `wp-links-opml.php` | Mute. Files absent. |
| WooCommerce-shaped sites | orders, products, Woo admin | Out of scope for years. Brochure, forms, and content are the tractable slice. |
| Plugin settings pages | `add_options_page` callbacks, `admin.php?page=` | Hear the registration. Mute the host. Adapter, or the page does not exist. |

### Names that stay placeholders

Do not implement these in `wp-api/` to shrink `Plugins::skipped()`. They are
WordPress product chrome.

**List tables / screen chrome:** `WP_List_Table`, `_get_list_table`,
`list_meta`, `post_tags_meta_box`.

**Media upload chrome (not `wp/v2/media`):** `media_upload_header`,
`media_send_to_editor`.

**Classic / iframe editor:** `wp_editor`, `_wp_get_iframed_editor_assets`,
`get_block_editor_server_block_settings`, `get_default_block_editor_settings`.

**Admin CSS / color schemes:** `wp_admin_css`, `wp_admin_css_color`.

**Upgrader / zip / file-mod UI:** `WP_Upgrader`, `WP_Upgrader_Skin`,
`File_Upload_Upgrader`, `PclZip`, `themes_api`, `find_core_update`,
`wp_version_check`, `wp_update_plugins`, `wp_update_themes`, `unzip_file`.

**XML-RPC client:** `IXR_Client`, `IXR_Error`, `IXR_Message`, `IXR_Request`.

**Theme file editor:** `wp_get_theme_file_editable_extensions`.

Some admin helpers plugins call for data were made real once probes captured
what WordPress returns: post states, hidden meta boxes, postbox classes,
column headers, quick-edit data, comment hotkeys, the importer registry,
`delete_plugins`, the admin bar's init and `WP_Block_Editor_Context`. They
return what WordPress returns and still paint no screen.

Most of the placeholders that were *data* have graduated: `WP_User_Query`,
meta-by-mid, `WP_Theme_JSON`, the POMO `Translations` and `MO` classes, and
`WP_HTML_Processor`. The REST controllers plugins subclass and the
`POMO_*Reader` classes are still placeholders and may graduate under the
three tests. They are not mute chrome.

---

## Agent checklist

Before adding code for a WordPress word:

1. Which audience is it for: tooling, plugin PHP, or a human in a browser?
2. If a human in a browser: stop. Point at Minn Admin, or name the gap in
   Minn Admin. Do not build `/wp-admin/`.
3. If plugin PHP that only runs on `admin_menu` / `admin_init` to paint a
   screen: Hear (record / stub). Do not host.
4. If tooling or front-end plugin PHP: Speak. Capture from the oracle,
   implement from the capture, pin a suite.
5. Never graduate a mute name out of `placeholder-symbols.json` to make the
   symbol gate go green.
