<?php
/**
 * The widget factory and two core widgets used directly (probe
 * widget-factory): widgets registered by class and by instance, found,
 * keyed and removed, the factory registering its widgets, and the Custom
 * HTML and Block widgets displayed, updated and drawn. Nothing is stored:
 * the widgets' settings are never saved.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
// Registering the factory's widgets saves the probe widget's empty settings; they go at the end.
register_shutdown_function(static function (): void {
    delete_option('widget_zz_factory_probe');
});
$echoed = static function (Closure $call): array {
    ob_start();
    $returned = $call();
    return [(string) ob_get_clean(), $returned];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use ($host, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value) : $value;
};

class Zz_Probe_Factory_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('zz_factory_probe', 'Zz Factory Probe', ['description' => 'Probe.']);
    }

    public function widget($args, $instance)
    {
        echo 'zz widget';
    }
}

$say('the factory', (static function () {
    global $wp_widget_factory, $wp_registered_widgets;
    $factory = new WP_Widget_Factory();
    $out = [get_class($wp_widget_factory), $factory->widgets];
    $factory->register('Zz_Probe_Factory_Widget');
    $out[] = [array_keys($factory->widgets), get_class($factory->get_widget_object('zz_factory_probe')), $factory->get_widget_key('zz_factory_probe'), $factory->get_widget_object('zz_none'), $factory->get_widget_key('zz_none')];
    $instance = new Zz_Probe_Factory_Widget();
    $factory->register($instance);
    $keys = array_keys($factory->widgets);
    $out[] = [count($keys), $keys[0], is_string($keys[1] ?? null) && $keys[1] !== 'Zz_Probe_Factory_Widget', $factory->get_widget_object('zz_factory_probe') === $factory->widgets[$keys[0]]];
    $factory->register('Zz_Probe_Factory_Widget');
    $out[] = count($factory->widgets);
    $before = array_filter(array_keys($wp_registered_widgets), static fn ($id) => str_starts_with((string) $id, 'zz_factory_probe'));
    $factory->_register_widgets();
    $after = array_values(array_filter(array_keys($wp_registered_widgets), static fn ($id) => str_starts_with((string) $id, 'zz_factory_probe')));
    $out[] = [array_values($before), $after, $factory->widgets[$keys[0]]->number ?? null];
    foreach ($after as $id) {
        wp_unregister_sidebar_widget($id);
    }
    $factory->unregister($instance);
    $out[] = array_keys($factory->widgets);
    $factory->unregister('Zz_Probe_Factory_Widget');
    $out[] = $factory->widgets;
    $factory->unregister('Zz_None_Class');
    $out[] = $factory->widgets;
    return $out;
})());

$args = ['before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>', 'widget_id' => 'zz-1'];
$say('the Custom HTML widget', $mask((static function () use ($args, $echoed) {
    $widget = new WP_Widget_Custom_HTML();
    $out = [$widget->id_base, $widget->name, $widget->widget_options, $widget->control_options];
    $out[] = $echoed(static fn () => $widget->widget($args, ['title' => 'Zz <b>Title</b>', 'content' => '<p>Hello "world" -- <script>x()</script></p>']));
    $out[] = $echoed(static fn () => $widget->widget($args, ['title' => '', 'content' => 'plain']));
    $out[] = $echoed(static fn () => $widget->widget($args, []));
    $heard = [];
    $listen = static function ($content, ...$rest) use (&$heard) {
        $heard[] = [current_filter(), $content, count($rest)];
        return $content . '|' . current_filter();
    };
    add_filter('widget_custom_html_content', $listen, 10, 3);
    add_filter('widget_text', $listen, 10, 3);
    $out[] = [$echoed(static fn () => $widget->widget($args, ['title' => 'T', 'content' => 'c']))[0], $heard];
    remove_filter('widget_custom_html_content', $listen, 10);
    remove_filter('widget_text', $listen, 10);
    wp_set_current_user(0);
    $out[] = $widget->update(['title' => ' <b>New</b> ', 'content' => '<p onclick="x">c</p><script>y()</script>'], ['title' => 'Old', 'content' => 'old', 'zz' => 'kept']);
    wp_set_current_user(1);
    $out[] = $widget->update(['title' => ' <b>New</b> ', 'content' => '<p onclick="x">c</p><script>y()</script>'], []);
    wp_set_current_user(0);
    $out[] = $echoed(static fn () => $widget->form(['title' => 'Zz', 'content' => '<p>c</p>']))[0] !== '';
    return $out;
})()));

$say('the Block widget', $mask((static function () use ($args, $echoed) {
    $widget = new WP_Widget_Block();
    $out = [$widget->id_base, $widget->name, $widget->widget_options, $widget->control_options];
    $out[] = $echoed(static fn () => $widget->widget($args, ['content' => '<!-- wp:paragraph --><p>Para</p><!-- /wp:paragraph -->']));
    $out[] = $echoed(static fn () => $widget->widget($args, ['content' => '<!-- wp:latest-comments {"commentsToShow":1} /-->']))[0] !== '';
    $out[] = $echoed(static fn () => $widget->widget($args, ['content' => 'plain text']));
    $out[] = $echoed(static fn () => $widget->widget($args, ['content' => '']));
    $out[] = $echoed(static fn () => $widget->widget($args, []));
    $out[] = $echoed(static fn () => $widget->widget(['before_widget' => '<div class="widget %2$s">', 'after_widget' => '</div>'], ['content' => '<!-- wp:heading --><h2>H</h2><!-- /wp:heading -->']));
    wp_set_current_user(0);
    $out[] = $widget->update(['content' => '<!-- wp:paragraph --><p onclick="x">c</p><!-- /wp:paragraph --><script>y()</script>'], ['content' => 'old']);
    wp_set_current_user(1);
    $out[] = $widget->update(['content' => '<script>y()</script>'], []);
    wp_set_current_user(0);
    $out[] = $echoed(static fn () => $widget->form(['content' => '<p>c</p>']))[0] !== '';
    return $out;
})()));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
