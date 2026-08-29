# wp/v2/plugins

What sits in `wp-content/plugins`, in the reference's item shape, served
by `Minn\Rest\PluginsController` behind `activate_plugins` (suite
`admin-surfaces`, section 6).

- Two kinds of entry share the list. A **WordPress plugin folder** (a main
  file with a `Plugin Name` header) is listed with its headers (`plugin`
  is `dir/file` without `.php`, `description.rendered` appends the
  reference's `<cite>By <a>author</a>.</cite>`) and the status stored in
  `active_plugins`. The engine never runs it; the status is the site's
  record, the same record `wp plugin list` reports. A **Minn extension**
  (a folder with `minn.json`) is listed as `<slug>/<slug>` with the
  manifest's name and version, a description naming what it stands in for,
  and the status the extension loader computes (own list, `replaces`,
  mu-plugins).
- `PUT|POST|PATCH /wp/v2/plugins/{plugin} {status}` flips the state: a
  WordPress plugin file is added to or removed from `active_plugins`
  (sorted, serialized like the reference writes it); an extension is added
  to or removed from `minn_active_extensions`, and deactivating it also
  releases the plugin files it replaced. Unknown → 404
  `rest_plugin_not_found`; other statuses → 400.
- `DELETE` refuses (403 `rest_cannot_delete_plugin`): the engine does not
  delete files from the plugins folder. Installing (`POST /wp/v2/plugins
  {slug}`) has no route.
- The boot payload sends `pluginAjax: null`, so Minn Admin's toggle falls
  through to this PUT instead of admin-ajax.
