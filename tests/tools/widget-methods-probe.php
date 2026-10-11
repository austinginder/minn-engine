<?php
/**
 * The widgets' remaining methods (probe widget-methods): the Text and
 * Custom HTML widgets' gallery attribute filter, the scripts and styles
 * the Text widget's preview loads, and the control templates they print; whether a block widget is wide in the Customizer; the widget
 * factory's old constructor; and the Links widget whole, with a bookmark of
 * its own (removed again).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_constructor_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_constructor_run', static function ($class, $version, $parent) use (&$deprecated) {
    $deprecated[] = ['constructor', $class, $version, $parent];
}, 10, 3);
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated) {
    $deprecated[] = ['function', $function, $replacement, $version];
}, 10, 3);
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
require_once ABSPATH . 'wp-admin/includes/bookmark.php';
$printed = static function (callable $run) {
    ob_start();
    $result = $run();
    return [$result, ob_get_clean()];
};
// What a call queued: the scripts and styles newly enqueued, with their inline data.
$queued = static function (callable $run) {
    $scripts = wp_scripts()->queue;
    $styles = wp_styles()->queue;
    $run();
    $added = static fn (WP_Dependencies $deps, array $before) => array_values(array_diff($deps->queue, $before));
    $describe = static fn (WP_Dependencies $deps, array $handles) => array_map(static fn ($handle) => [$handle, $deps->get_data($handle, 'before') ?: null, $deps->get_data($handle, 'after') ?: null, $deps->get_data($handle, 'data') ?: null], $handles);
    return ['scripts' => $describe(wp_scripts(), $added(wp_scripts(), $scripts)), 'styles' => $describe(wp_styles(), $added(wp_styles(), $styles))];
};

$text = new WP_Widget_Text();
$html = new WP_Widget_Custom_HTML();
$gallery = static fn (WP_Widget $widget) => array_map(static fn ($attrs) => $widget->_filter_gallery_shortcode_attrs($attrs), [
    'nothing' => [],
    'ids' => ['ids' => '1,2'],
    'a link' => ['link' => 'file'],
    'both' => ['ids' => '3', 'link' => 'none', 'columns' => 2],
    'an id' => ['id' => 5, 'ids' => '4'],
]);
$say('gallery attributes', ['text' => $gallery($text), 'custom html' => $gallery($html)]);
$playlist = static fn () => [has_action('wp_footer', 'wp_underscore_playlist_templates'), has_action('admin_footer', 'wp_underscore_playlist_templates')];
$before = $playlist();
$say('scripts', [
    'text preview' => $queued(static fn () => $text->enqueue_preview_scripts()),
    'the playlist templates' => [$before, $playlist()],
]);
// The control templates, for a visitor and for an administrator (who may post unfiltered HTML).
$templates = static fn () => [
    'text' => $printed(static fn () => WP_Widget_Text::render_control_template_scripts()),
    'custom html' => $printed(static fn () => WP_Widget_Custom_HTML::render_control_template_scripts()),
];
wp_set_current_user(0);
$asVisitor = $templates();
wp_set_current_user(1);
$say('templates', ['a visitor' => $asVisitor, 'an administrator' => $templates()]);
wp_set_current_user(0);

$block = new WP_Widget_Block();
$say('wide in the customizer', [
    'a block widget' => $block->set_is_wide_widget_in_customizer(false, 'block-2'),
    'already wide' => $block->set_is_wide_widget_in_customizer(true, 'block-3'),
    'another widget' => $block->set_is_wide_widget_in_customizer(false, 'text-2'),
    'another widget, wide' => $block->set_is_wide_widget_in_customizer(true, 'text-2'),
    'a block id without a number' => $block->set_is_wide_widget_in_customizer(true, 'block'),
    'a block id with a word' => $block->set_is_wide_widget_in_customizer(true, 'block-x'),
    'a longer base' => $block->set_is_wide_widget_in_customizer(true, 'zzblock-2'),
    'a block base with more' => $block->set_is_wide_widget_in_customizer(true, 'block-2-3'),
    'no id' => $block->set_is_wide_widget_in_customizer(false, ''),
]);

$factory = new WP_Widget_Factory();
$say('the factory\'s old constructor', [$printed(static fn () => $factory->WP_Widget_Factory()), $deprecated]);

// The Links widget, with a category and a bookmark of its own.
wp_set_current_user(1);
$category = wp_insert_term('ZZ wml links', 'link_category');
$category = is_array($category) ? (int) $category['term_id'] : 0;
$empty = wp_insert_term('ZZ wml empty', 'link_category');
$empty = is_array($empty) ? (int) $empty['term_id'] : 0;
$bookmark = wp_insert_link(['link_name' => 'ZZ wml bookmark', 'link_url' => 'https://example.com/zz', 'link_description' => 'A ZZ bookmark', 'link_image' => 'https://example.com/zz.png', 'link_rating' => 5, 'link_category' => [$category]]);
register_shutdown_function(static function () use ($category, $empty, $bookmark): void {
    if (is_int($bookmark) && $bookmark > 1) {
        wp_delete_link($bookmark);
    }
    foreach ([$category, $empty] as $term) {
        if (is_int($term) && $term > 1) {
            wp_delete_term($term, 'link_category');
        }
    }
});
if (!is_int($bookmark) || $bookmark <= 0 || $category <= 1 || $empty <= 1) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
$mask = static function ($value) use ($category, $empty, $bookmark) {
    $json = (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    foreach (['category' => $category, 'empty' => $empty, 'bookmark' => $bookmark] as $name => $id) {
        $json = preg_replace('/(?<=[:\[,])' . $id . '(?=[,\]}])/', '"{' . $name . '}"', $json);
        $json = preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $json);
    }
    return json_decode($json, true);
};
$links = new WP_Widget_Links();
// The hooks a call runs whose names speak of links or widgets (options read on the way are another probe's business).
$hooks = static function (callable $run) {
    $heard = [];
    $listen = static function () use (&$heard) {
        $name = current_filter();
        if (preg_match('/link|widget|bookmark/', $name) && !preg_match('/^(pre_|default_)?option_/', $name) && !in_array($name, $heard, true)) {
            $heard[] = $name;
        }
        return func_num_args() ? func_get_arg(0) : null;
    };
    add_filter('all', $listen);
    ob_start();
    $run();
    ob_end_clean();
    remove_filter('all', $listen);
    return $heard;
};
$args = ['before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>', 'widget_id' => 'links-2'];
$say('the links widget', $mask([
    'itself' => [$links->id_base, $links->name, $links->widget_options, $links->control_options, $links->option_name],
    'shown' => $printed(static fn () => $links->widget($args, ['category' => $category, 'images' => true, 'name' => true, 'description' => true, 'rating' => true, 'orderby' => 'name', 'limit' => -1])),
    'shown, names only' => $printed(static fn () => $links->widget($args, ['category' => $category, 'images' => false, 'name' => false, 'description' => false, 'rating' => false])),
    'shown, every category' => $printed(static fn () => $links->widget($args, ['category' => 0, 'limit' => 1])),
    'updated' => $links->update(['category' => (string) $category, 'images' => 'on', 'name' => '', 'description' => 'on', 'rating' => '', 'orderby' => 'rand', 'limit' => '3'], []),
    'updated, an odd order' => $links->update(['orderby' => 'zz', 'limit' => 'x', 'category' => '0'], ['orderby' => 'rating', 'images' => 1, 'zz' => 'kept?']),
    'updated, by id' => $links->update(['orderby' => 'id', 'limit' => '', 'category' => 'x', 'zz' => 'y'], []),
    'updated, by url' => $links->update(['orderby' => 'url', 'limit' => '-4', 'category' => '-2'], []),
    'its form' => $printed(static fn () => $links->form(['category' => $category, 'images' => true, 'orderby' => 'rating', 'limit' => 5])),
    'its form, empty' => $printed(static fn () => $links->form([])),
    'its form, every link' => $printed(static fn () => $links->form(['category' => 0, 'images' => false, 'name' => false, 'description' => true, 'rating' => true, 'orderby' => 'rand', 'limit' => -1])),
    'its form, odd values' => $printed(static fn () => $links->form(['category' => 'x', 'orderby' => 'url', 'limit' => '<b>'])),
    'its form, values as text' => $printed(static fn () => $links->form(['category' => (string) $empty, 'images' => 'on', 'name' => 1, 'description' => 'yes', 'rating' => 0, 'limit' => '-4'])),
    'its form, a number with words' => $printed(static fn () => $links->form(['category' => $category, 'images' => '1', 'name' => '0', 'limit' => '7abc'])),
    'hooks shown' => $hooks(static fn () => $links->widget($args, ['category' => $category, 'images' => true, 'name' => true, 'description' => true, 'rating' => true, 'orderby' => 'name', 'limit' => -1])),
    'hooks of the form' => $hooks(static fn () => $links->form([])),
    'hooks of a field id and name' => $hooks(static fn () => [$links->get_field_id('zz'), $links->get_field_name('zz')]),
    'the arguments it lists with' => (static function () use ($links, $args, $category) {
        $seen = [];
        $listen = static function (...$given) use (&$seen) {
            $seen[] = $given;
            return $given[0];
        };
        add_filter('widget_links_args', $listen, 10, 5);
        ob_start();
        $links->widget($args, ['category' => $category, 'images' => true, 'name' => false, 'description' => true, 'rating' => false, 'orderby' => 'rating', 'limit' => 2]);
        $links->widget($args, ['category' => 0]);
        $links->widget($args + ['id' => 'zz-sidebar'], ['category' => $category, 'orderby' => 'rand']);
        $links->widget(['before_widget' => '<li id="links-2" class="widget widget_links">', 'before_title' => '<h3 class="zz">'] + $args, ['category' => (string) $category, 'images' => 1, 'name' => '', 'description' => 'on', 'rating' => 0, 'orderby' => 'id', 'limit' => '3']);
        ob_end_clean();
        remove_filter('widget_links_args', $listen, 10);
        return $seen;
    })(),
    'a widget wrapper without an id' => $printed(static fn () => $links->widget(['before_widget' => '<div class="zz">', 'after_widget' => '</div>'] + $args, ['category' => $category])),
]));
wp_set_current_user(0);

// Whether the widget set registers the Links widget, with the link manager off and on.
$manager = get_option('link_manager_enabled', null);
// The factory is read as widgets_init begins, before its own pass over the widgets.
$registered = static function () {
    $seen = [];
    $look = static function () use (&$seen) {
        $seen = array_keys($GLOBALS['wp_widget_factory']->widgets);
    };
    add_action('widgets_init', $look, 1);
    wp_widgets_init();
    remove_action('widgets_init', $look, 1);
    return in_array('WP_Widget_Links', $seen, true);
};
$registry = ['as it is' => $registered()];
update_option('link_manager_enabled', 1);
$registry['the link manager on'] = $registered();
$manager === null ? delete_option('link_manager_enabled') : update_option('link_manager_enabled', $manager);
$say('registered', $registry);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
