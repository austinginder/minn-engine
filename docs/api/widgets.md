# `Minn\Widgets`



| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`TextWidgets`](#textwidgets) | final class | 159 | The text and custom HTML widgets' behaviour (WP_Widget_Text, |
| [`WidgetForms`](#widgetforms) | final class | 203 | The core widgets' settings forms, the markup a widget's form() prints and |
| [`WidgetTemplates`](#widgettemplates) | final class | 85 | The Underscore templates the Text and Custom HTML widgets print for their |

## TextWidgets

`final class Minn\Widgets\TextWidgets` · `public/minn/src/Minn/Widgets/TextWidgets.php`

The text and custom HTML widgets' behaviour (WP_Widget_Text,
WP_Widget_Custom_HTML; probes widget-methods, nav-pomo-rest-doubts):
which text instances are legacy ones the visual editor would change, how
an update settles the filter and visual flags, and the two widgets'
output, their filters run with no global post and a gallery inside
belonging to no post.

- const `KEPT` = `array (   'b' =>    array (   ),   'i' =>    array (   ),   'u' =>    array (   ),   's' =>    array (   ),   'em' =>    array (   ),   'strong' =>    array (   ),   'code' =>    array (   ),   'abbr' =>    array (   ),   'ul' =>    array (   ),   'ol' =>    array (   ),   'li' =>    array (   ),   'a' =>    array (     0 => 'href',   ),   'img' =>    array (     0 => 'src',     1 => 'alt',   ),   'hr' =>    array (   ), )` — The elements the visual editor keeps as written, each with the attributes it keeps.
- const `VOID` = `array (   0 => 'img',   1 => 'hr', )` — The kept elements that are empty by nature.

### static `isLegacy(mixed $instance): bool`

Whether a text instance is a legacy one: one with a visual flag is
legacy when the flag is off, one filtered as content never is, nor
blank text. Otherwise its text is legacy when it has a line break
without the paragraph filter, a comment, an element or attribute the
visual editor does not keep, or an element left empty.

### static `settled(array $instance, array $new): array`

An updated text instance's flags settled: filtered when the new one
asks, visual as the new one says (always for content filtering), and a
visual instance always filtered.

- `@param array<array-key, mixed> $instance the instance with its title and text set`
- `@param array<array-key, mixed> $new the settings as submitted`
- `@return array<array-key, mixed>`

### static `text(object $widget, array $args, array $instance): string`

The text widget's markup: a visual instance (visual and filtered, or
filtered as content) through widget_text and widget_text_content; any
other through widget_text, then paragraphs when it asks for them, then
its shortcodes.

- `@param array<array-key, mixed> $args`
- `@param array<array-key, mixed> $instance`

### static `customHtml(object $widget, array $args, array $instance): string`

The custom HTML widget's markup: its content through widget_text (as a
text instance would carry it) and widget_custom_html_content, the text
widget's class first in the wrapper.

- `@param array<array-key, mixed> $args`
- `@param array<array-key, mixed> $instance`

### static `galleryAttributes(array $attrs): array`

Gallery attributes inside a text or HTML widget: off a singular view,
a gallery naming neither an id nor included images belongs to no post (-1).

- `@param array<array-key, mixed> $attrs`
- `@return array<array-key, mixed>`

Internals: `withoutPost()` (private, line 165)


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
- const `UNSAFE_TAGS` = `array (   0 => 'script',   1 => 'iframe',   2 => 'form',   3 => 'input',   4 => 'style', )` — The tags the note on refused tags names, those a post may hold aside.

### static `text(): string`

The Text widget's template: title, the two pointers, the content area.

### static `customHtml(): string`

The Custom HTML widget's template for a user who may post unfiltered HTML.

### static `customHtmlRestricted(): string`

The Custom HTML widget's template for a user who may not, with the
note (shown when the code editor is off) naming the unsafe tags a post
may not hold (wp_kses_allowed_html); no note when it may hold them all.

Internals: `customHtmlWith()` (private, line 62), `open()` (private, line 75), `pointer()` (private, line 86)

