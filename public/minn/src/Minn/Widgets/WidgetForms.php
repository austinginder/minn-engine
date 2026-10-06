<?php

declare(strict_types=1);

namespace Minn\Widgets;

/**
 * The core widgets' settings forms, the markup a widget's form() prints and
 * wp/v2/widgets returns as rendered_form, character for character as the
 * reference prints it (probe rest-widgets): field ids and names from the
 * widget's number, values escaped, defaults filled in. The whitespace is
 * the reference's own and is part of the answer.
 */
final class WidgetForms
{
    /** The title field most widgets open with. */
    public static function title(\WP_Widget $widget, array $instance): string
    {
        return sprintf(
            "\t\t<p>\n\t\t\t<label for=\"%1\$s\">Title:</label>\n\t\t\t<input class=\"widefat\" id=\"%1\$s\" name=\"%2\$s\" type=\"text\" value=\"%3\$s\" />\n\t\t</p>\n",
            \esc_attr($widget->get_field_id('title')),
            \esc_attr($widget->get_field_name('title')),
            \esc_attr((string) ($instance['title'] ?? '')),
        );
    }

    /** search, meta, calendar: the title alone. */
    public static function titleOnly(\WP_Widget $widget, array $instance): string
    {
        return self::title($widget, $instance) . "\t\t";
    }

    /** The pages widget: title, sort order, pages to leave out. */
    public static function pages(\WP_Widget $widget, array $instance): string
    {
        $sortby = (string) ($instance['sortby'] ?? 'post_title');
        $options = '';
        foreach (['post_title' => 'Page title', 'menu_order' => 'Page order', 'ID' => 'Page ID'] as $value => $label) {
            $options .= "\t\t\t\t<option value=\"{$value}\"" . \selected($sortby, $value, false) . ">{$label}</option>\n";
        }
        return self::title($widget, $instance)
            . sprintf("\n\t\t<p>\n\t\t\t<label for=\"%1\$s\">Sort by:</label>\n\t\t\t<select name=\"%2\$s\" id=\"%1\$s\" class=\"widefat\">\n%3\$s\t\t\t</select>\n\t\t</p>\n", self::id($widget, 'sortby'), self::name($widget, 'sortby'), $options)
            . sprintf("\n\t\t<p>\n\t\t\t<label for=\"%1\$s\">Exclude:</label>\n\t\t\t<input type=\"text\" value=\"%3\$s\" name=\"%2\$s\" id=\"%1\$s\" class=\"widefat\" />\n\t\t\t<br />\n\t\t\t<small>Page IDs, separated by commas.</small>\n\t\t</p>\n\t\t", self::id($widget, 'exclude'), self::name($widget, 'exclude'), \esc_attr((string) ($instance['exclude'] ?? '')));
    }

    /** The archives widget: title, dropdown and counts boxes. */
    public static function archives(\WP_Widget $widget, array $instance): string
    {
        $box = static fn (string $key, string $label): string => sprintf("\t\t\t<input class=\"checkbox\" type=\"checkbox\"%3\$s id=\"%1\$s\" name=\"%2\$s\" />\n\t\t\t<label for=\"%1\$s\">%4\$s</label>\n", self::id($widget, $key), self::name($widget, $key), \checked(!empty($instance[$key]), true, false), $label);
        return self::title($widget, $instance) . "\t\t<p>\n" . $box('dropdown', 'Display as dropdown') . "\t\t\t<br />\n" . $box('count', 'Show post counts') . "\t\t</p>\n\t\t";
    }

    /** The categories widget: title, dropdown, counts and hierarchy boxes. */
    public static function categories(\WP_Widget $widget, array $instance): string
    {
        $box = static fn (string $key, string $label): string => sprintf("\t\t\t<input type=\"checkbox\" class=\"checkbox\" id=\"%1\$s\" name=\"%2\$s\"%3\$s />\n\t\t\t<label for=\"%1\$s\">%4\$s</label>\n", self::id($widget, $key), self::name($widget, $key), \checked(!empty($instance[$key]), true, false), $label);
        return self::title($widget, $instance)
            . "\n\t\t<p>\n" . $box('dropdown', 'Display as dropdown') . "\t\t\t<br />\n\n" . $box('count', 'Show post counts') . "\t\t\t<br />\n\n" . $box('hierarchical', 'Show hierarchy') . "\t\t</p>\n\t\t";
    }

    /** The recent posts widget: title, how many, whether to show dates. */
    public static function recentPosts(\WP_Widget $widget, array $instance): string
    {
        return self::title($widget, $instance)
            . self::number($widget, (int) ($instance['number'] ?? 5) ?: 5, 'Number of posts to show:')
            . sprintf("\n\t\t<p>\n\t\t\t<input class=\"checkbox\" type=\"checkbox\"%3\$s id=\"%1\$s\" name=\"%2\$s\" />\n\t\t\t<label for=\"%1\$s\">Display post date?</label>\n\t\t</p>\n\t\t", self::id($widget, 'show_date'), self::name($widget, 'show_date'), \checked(!empty($instance['show_date']), true, false));
    }

    /** The recent comments widget: title and how many. */
    public static function recentComments(\WP_Widget $widget, array $instance): string
    {
        return self::title($widget, $instance) . self::number($widget, (int) ($instance['number'] ?? 5) ?: 5, 'Number of comments to show:') . "\t\t";
    }

    /** The custom HTML widget: hidden title and content fields its editor syncs. */
    public static function customHtml(\WP_Widget $widget, array $instance): string
    {
        return sprintf("\t\t<input id=\"%1\$s\" name=\"%2\$s\" class=\"title sync-input\" type=\"hidden\" value=\"%3\$s\" />\n", self::id($widget, 'title'), self::name($widget, 'title'), \esc_attr((string) ($instance['title'] ?? '')))
            . sprintf("\t\t<textarea id=\"%1\$s\" name=\"%2\$s\" class=\"content sync-input\" hidden>%3\$s</textarea>\n\t\t", self::id($widget, 'content'), self::name($widget, 'content'), \esc_textarea((string) ($instance['content'] ?? '')));
    }

    /** The block widget: its block markup in a textarea. */
    public static function block(\WP_Widget $widget, array $instance): string
    {
        return sprintf("\t\t<p>\n\t\t\t<label for=\"%1\$s\">\n\t\t\t\tBlock HTML:\t\t\t</label>\n\t\t\t<textarea id=\"%1\$s\" name=\"%2\$s\" rows=\"6\" cols=\"50\" class=\"widefat code\">%3\$s</textarea>\n\t\t</p>\n\t\t", self::id($widget, 'content'), self::name($widget, 'content'), \esc_textarea((string) ($instance['content'] ?? '')));
    }

    /** The text widget in its visual mode: hidden fields the editor syncs. */
    public static function text(\WP_Widget $widget, array $instance): string
    {
        return sprintf("\t\t\t\t\t\t\t\t<input id=\"%1\$s\" name=\"%2\$s\" class=\"title sync-input\" type=\"hidden\" value=\"%3\$s\">\n", self::id($widget, 'title'), self::name($widget, 'title'), \esc_attr((string) ($instance['title'] ?? '')))
            // The text goes into its hidden field as it is: the reference does not escape it here.
            . sprintf("\t\t\t<textarea id=\"%1\$s\" name=\"%2\$s\" class=\"text sync-input\" hidden>%3\$s</textarea>\n", self::id($widget, 'text'), self::name($widget, 'text'), (string) ($instance['text'] ?? ''))
            . sprintf("\t\t\t<input id=\"%1\$s\" name=\"%2\$s\" class=\"filter sync-input\" type=\"hidden\" value=\"on\">\n", self::id($widget, 'filter'), self::name($widget, 'filter'))
            . sprintf("\t\t\t<input id=\"%1\$s\" name=\"%2\$s\" class=\"visual sync-input\" type=\"hidden\" value=\"on\">\n\t\t", self::id($widget, 'visual'), self::name($widget, 'visual'));
    }

    /** The tag cloud: its title, the taxonomies that show a cloud (tags unless one is chosen), and the counts box. */
    public static function tagCloud(\WP_Widget $widget, array $instance): string
    {
        $taxonomies = \get_taxonomies(['show_tagcloud' => true], 'object');
        $current = isset($instance['taxonomy']) && \taxonomy_exists((string) $instance['taxonomy']) ? (string) $instance['taxonomy'] : 'post_tag';
        $out = sprintf("\t\t<p>\n\t\t\t<label for=\"%1\$s\">Title:</label>\n\t\t\t<input type=\"text\" class=\"widefat\" id=\"%1\$s\" name=\"%2\$s\" value=\"%3\$s\" />\n\t\t</p>\n", self::id($widget, 'title'), self::name($widget, 'title'), \esc_attr((string) ($instance['title'] ?? '')));
        $options = '';
        foreach ($taxonomies as $taxonomy) {
            $options .= "\t\t\t\t\t\t\t\t\t\t\t<option value=\"" . \esc_attr($taxonomy->name) . '" ' . \selected($taxonomy->name, $current, false) . ">\n\t\t\t\t\t\t\t" . \esc_html($taxonomy->labels->name) . "\t\t\t\t\t\t</option>\n";
        }
        $out .= sprintf("\t\t\t\t\t\t<p>\n\t\t\t\t\t<label for=\"%1\$s\">Taxonomy:</label>\n\t\t\t\t\t<select class=\"widefat\" id=\"%1\$s\" name=\"%2\$s\">\n%3\$s\t\t\t\t\t\t\t\t\t\t</select>\n\t\t\t\t</p>\n", self::id($widget, 'taxonomy'), self::name($widget, 'taxonomy'), $options);
        return $out . sprintf("\t\t\t\t\t\t\t<p>\n\t\t\t\t<input type=\"checkbox\" class=\"checkbox\" id=\"%1\$s\" name=\"%2\$s\" %3\$s />\n\t\t\t\t<label for=\"%1\$s\">Show tag counts</label>\n\t\t\t</p>\n\t\t\t", self::id($widget, 'count'), self::name($widget, 'count'), \checked(!empty($instance['count']), true, false));
    }

    /** The navigation menu widget: a note when there are no menus, else the title and the menu to show. */
    public static function navMenu(\WP_Widget $widget, array $instance): string
    {
        $menus = \wp_get_nav_menus();
        $chosen = (int) ($instance['nav_menu'] ?? 0);
        $out = "\t\t<p class=\"nav-menu-widget-no-menus-message\" " . ($menus === [] ? '' : ' style="display:none" ') . ">\n\t\t\tNo menus have been created yet. <a href=\"" . \esc_attr(\admin_url('nav-menus.php')) . "\">Create some</a>.\t\t</p>\n";
        $out .= "\t\t<div class=\"nav-menu-widget-form-controls\" " . ($menus === [] ? 'style="display:none"' : '') . ">\n";
        $out .= sprintf("\t\t\t<p>\n\t\t\t\t<label for=\"%1\$s\">Title:</label>\n\t\t\t\t<input type=\"text\" class=\"widefat\" id=\"%1\$s\" name=\"%2\$s\" value=\"%3\$s\" />\n\t\t\t</p>\n", self::id($widget, 'title'), self::name($widget, 'title'), \esc_attr((string) ($instance['title'] ?? '')));
        $options = '';
        foreach ($menus as $menu) {
            $options .= "\t\t\t\t\t\t\t\t\t\t\t<option value=\"" . \esc_attr((string) $menu->term_id) . '" ' . \selected($chosen, $menu->term_id, false) . ">\n\t\t\t\t\t\t\t" . \esc_html($menu->name) . "\t\t\t\t\t\t</option>\n";
        }
        $out .= sprintf("\t\t\t<p>\n\t\t\t\t<label for=\"%1\$s\">Select Menu:</label>\n\t\t\t\t<select id=\"%1\$s\" name=\"%2\$s\">\n\t\t\t\t\t<option value=\"0\">&mdash; Select &mdash;</option>\n%3\$s\t\t\t\t\t\t\t\t\t</select>\n\t\t\t</p>\n", self::id($widget, 'nav_menu'), self::name($widget, 'nav_menu'), $options);
        return $out . "\t\t\t\t\t</div>\n\t\t";
    }

    /**
     * wp_widget_rss_form: the feed's address, title, item count (1 to 20,
     * 10 by default) and the three display boxes, each shown unless the
     * caller hides it, and ticked when the settings say so (or, saying
     * nothing, when it is shown).
     *
     * @param array<string, mixed> $args
     * @param array<string, bool> $inputs
     */
    public static function rss(array $args, array $inputs): string
    {
        $inputs = array_merge(['url' => true, 'title' => true, 'items' => true, 'show_summary' => true, 'show_author' => true, 'show_date' => true], $inputs);
        // A display box the settings do not mention is on when its input is shown.
        $args = array_merge(['title' => '', 'url' => '', 'items' => 0, 'error' => false], $args);
        foreach (['show_summary', 'show_author', 'show_date'] as $key) {
            $args[$key] = isset($args[$key]) ? (int) $args[$key] : (int) $inputs[$key];
        }
        $number = \esc_attr((string) ($args['number'] ?? ''));
        $items = (int) $args['items'] < 1 || (int) $args['items'] > 20 ? 10 : (int) $args['items'];
        $out = '';
        if ($inputs['url']) {
            $out .= "\t<p><label for=\"rss-url-{$number}\">Enter the RSS feed URL here:</label>\n\t<input class=\"widefat\" id=\"rss-url-{$number}\" name=\"widget-rss[{$number}][url]\" type=\"text\" value=\"" . \esc_url((string) $args['url']) . "\" /></p>\n";
        }
        if ($inputs['title']) {
            $out .= "\t<p><label for=\"rss-title-{$number}\">Give the feed a title (optional):</label>\n\t<input class=\"widefat\" id=\"rss-title-{$number}\" name=\"widget-rss[{$number}][title]\" type=\"text\" value=\"" . \esc_attr((string) $args['title']) . "\" /></p>\n";
        }
        if ($inputs['items']) {
            $options = '';
            for ($i = 1; $i <= 20; $i++) {
                $options .= "<option value='{$i}' " . \selected($items, $i, false) . ">{$i}</option>";
            }
            $out .= "\t<p><label for=\"rss-items-{$number}\">How many items would you like to display?</label>\n\t<select id=\"rss-items-{$number}\" name=\"widget-rss[{$number}][items]\">\n\t{$options}\t</select></p>\n";
        }
        $out .= "\t<p>\n";
        foreach (['show_summary' => ['summary', 'Display item content?'], 'show_author' => ['author', 'Display item author if available?'], 'show_date' => ['date', 'Display item date?']] as $key => [$slug, $label]) {
            if ($inputs[$key]) {
                $out .= "\t\t\t<input id=\"rss-show-{$slug}-{$number}\" name=\"widget-rss[{$number}][{$key}]\" type=\"checkbox\" value=\"1\" " . \checked((bool) $args[$key], true, false) . " />\n\t\t<label for=\"rss-show-{$slug}-{$number}\">{$label}</label><br />\n";
            }
        }
        return $out . "\t\t</p>\n\t";
    }

    private static function number(\WP_Widget $widget, int $number, string $label): string
    {
        return sprintf("\n\t\t<p>\n\t\t\t<label for=\"%1\$s\">%3\$s</label>\n\t\t\t<input class=\"tiny-text\" id=\"%1\$s\" name=\"%2\$s\" type=\"number\" step=\"1\" min=\"1\" value=\"%4\$d\" size=\"3\" />\n\t\t</p>\n", self::id($widget, 'number'), self::name($widget, 'number'), $label, $number);
    }

    private static function id(\WP_Widget $widget, string $field): string
    {
        return \esc_attr($widget->get_field_id($field));
    }

    private static function name(\WP_Widget $widget, string $field): string
    {
        return \esc_attr($widget->get_field_name($field));
    }
}
