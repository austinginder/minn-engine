# `Minn\Widgets`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`WidgetForms`](#widgetforms) | final class | 171 | The core widgets' settings forms, the markup a widget's form() prints and |

## WidgetForms

`final class Minn\Widgets\WidgetForms` · `public/minn/src/Minn/Widgets/WidgetForms.php`

The core widgets' settings forms, the markup a widget's form() prints and
wp/v2/widgets returns as rendered_form, character for character as the
reference prints it (probe rest-widgets): field ids and names from the
widget's number, values escaped, defaults filled in. The whitespace is
the reference's own and is part of the answer.

### static `title(WP_Widget $widget, array $instance): string`

The title field most widgets open with.

### static `titleOnly(WP_Widget $widget, array $instance): string`

search, meta, calendar: the title alone.

### static `pages(WP_Widget $widget, array $instance): string`

The pages widget: title, sort order, pages to leave out.

### static `archives(WP_Widget $widget, array $instance): string`

The archives widget: title, dropdown and counts boxes.

### static `categories(WP_Widget $widget, array $instance): string`

The categories widget: title, dropdown, counts and hierarchy boxes.

### static `recentPosts(WP_Widget $widget, array $instance): string`

The recent posts widget: title, how many, whether to show dates.

### static `recentComments(WP_Widget $widget, array $instance): string`

The recent comments widget: title and how many.

### static `customHtml(WP_Widget $widget, array $instance): string`

The custom HTML widget: hidden title and content fields its editor syncs.

### static `block(WP_Widget $widget, array $instance): string`

The block widget: its block markup in a textarea.

### static `text(WP_Widget $widget, array $instance): string`

The text widget in its visual mode: hidden fields the editor syncs.

### static `tagCloud(WP_Widget $widget, array $instance): string`

The tag cloud: its title, the taxonomies that show a cloud (tags unless one is chosen), and the counts box.

### static `navMenu(WP_Widget $widget, array $instance): string`

The navigation menu widget: a note when there are no menus, else the title and the menu to show.

### static `rss(array $args, array $inputs): string`

wp_widget_rss_form: the feed's address, title, item count (1 to 20,
10 by default) and the three display boxes, each shown unless the
caller hides it, and ticked when the settings say so (or, saying
nothing, when it is shown).

- `@param array<string, mixed> $args`
- `@param array<string, bool> $inputs`

Internals: `number()` (private, line 172), `id()` (private, line 177), `name()` (private, line 182)

