# `Minn\Widgets`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`WidgetForms`](#widgetforms) | final class | 203 | The core widgets' settings forms, the markup a widget's form() prints and |
| [`WidgetTemplates`](#widgettemplates) | final class | 77 | The Underscore templates the Text and Custom HTML widgets print for their |

## WidgetForms

`final class Minn\Widgets\WidgetForms` · `public/minn/src/Minn/Widgets/WidgetForms.php`

The core widgets' settings forms, the markup a widget's form() prints and
wp/v2/widgets returns as rendered_form, character for character as the
reference prints it (probe rest-widgets): field ids and names from the
widget's number, values escaped, defaults filled in. The whitespace is
the reference's own and is part of the answer.

### static `title(Minn\Objects\Widget $widget, array $instance): string`

The title field most widgets open with.

### static `titleOnly(Minn\Objects\Widget $widget, array $instance): string`

search, meta, calendar: the title alone.

### static `pages(Minn\Objects\Widget $widget, array $instance): string`

The pages widget: title, sort order, pages to leave out.

### static `archives(Minn\Objects\Widget $widget, array $instance): string`

The archives widget: title, dropdown and counts boxes.

### static `categories(Minn\Objects\Widget $widget, array $instance): string`

The categories widget: title, dropdown, counts and hierarchy boxes.

### static `recentPosts(Minn\Objects\Widget $widget, array $instance): string`

The recent posts widget: title, how many, whether to show dates.

### static `recentComments(Minn\Objects\Widget $widget, array $instance): string`

The recent comments widget: title and how many.

### static `customHtml(Minn\Objects\Widget $widget, array $instance): string`

The custom HTML widget: hidden title and content fields its editor syncs.

### static `block(Minn\Objects\Widget $widget, array $instance): string`

The block widget: its block markup in a textarea.

### static `text(Minn\Objects\Widget $widget, array $instance): string`

The text widget in its visual mode: hidden fields the editor syncs (after the indent WP_Widget_Text::form prints first).

### static `tagCloud(Minn\Objects\Widget $widget, array $instance): string`

The tag cloud: its title, the taxonomies that show a cloud (tags unless one is chosen), and the counts box.

### static `navMenu(Minn\Objects\Widget $widget, array $instance): string`

The navigation menu widget: a note when there are no menus, else the title and the menu to show.

### static `rss(array $args, array $inputs): string`

wp_widget_rss_form: the feed's address, title, item count (1 to 20,
10 by default) and the three display boxes, each shown unless the
caller hides it, and ticked when the settings say so (or, saying
nothing, when it is shown).

- `@param array<string, mixed> $args`
- `@param array<string, bool> $inputs`

### static `links(Minn\Objects\Widget $widget, array $instance, array $categories, string $limit): string`

The Links widget: the link category (the categories given as [id, name]),
the sort order, what each link shows, and how many (the limit as the
caller shows it).

Internals: `marked()` (private, line 204), `number()` (private, line 209), `id()` (private, line 214), `name()` (private, line 219)


## WidgetTemplates

`final class Minn\Widgets\WidgetTemplates` · `public/minn/src/Minn/Widgets/WidgetTemplates.php`

The Underscore templates the Text and Custom HTML widgets print for their
controls (render_control_template_scripts), as the reference prints them
(probe widget-methods): a title field and the content area, the Text
widget's two hidden pointers, and, for a user who may not post unfiltered
HTML, the note on the tags the code editor refuses. The whitespace is the
reference's own and is part of the answer.

- const `CLOSE` = `'		</script> 		'`
- const `REFUSED_TAGS` = `'																<# if ( data.codeEditorDisabled ) { #> 						<p> 							Some HTML tags are not permitted, including:							<code>script</code>, <code>iframe</code>, <code>form</code>, <code>input</code>, <code>style</code> 						</p> 					<# } #> 							 '` — The note a user who may not post unfiltered HTML sees, when the code editor is off.

### static `text(): string`

The Text widget's template: title, the two pointers, the content area.

### static `customHtml(): string`

The Custom HTML widget's template for a user who may post unfiltered HTML.

### static `customHtmlRestricted(): string`

The Custom HTML widget's template for a user who may not, with the note on refused tags.

Internals: `customHtmlWith()` (private, line 52), `open()` (private, line 65), `pointer()` (private, line 76)

