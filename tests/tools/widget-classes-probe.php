<?php
/**
 * The widget classes (probe widget-classes): WP_Widget through a small
 * subclass (its names and ids, numbered settings saved and read back, each
 * instance registered, displayed, updated from a posted form and its form
 * drawn, the old PHP 4 constructor) and WP_Widget_Media through the image
 * widget (its instance schema, token lists, attachments by type, media
 * rendered, the default labels and description, the media state). The
 * probe's settings option is removed. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
register_shutdown_function(static function () use (&$made): void {
    delete_option('widget_zz_probe');
    delete_option('widget_zz_probe_old');
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$mask = static function ($value) use (&$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    return is_string($value) ? (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value) : $value;
};
$echoed = static function (Closure $call): array {
    ob_start();
    $returned = $call();
    return [(string) ob_get_clean(), $returned];
};

class Zz_Probe_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct('zz_probe', 'Zz Probe', ['description' => 'A probe widget.', 'classname' => 'zz-probe-widget'], ['width' => 300]);
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'] . $args['before_title'] . esc_html($instance['title'] ?? '') . $args['after_title'] . '[' . $this->id . ']' . $args['after_widget'];
    }

    public function update($new_instance, $old_instance)
    {
        return ['title' => sanitize_text_field($new_instance['title'] ?? ''), 'was' => $old_instance['title'] ?? null];
    }

    public function form($instance)
    {
        echo '<input id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" value="' . esc_attr($instance['title'] ?? '') . '">';
        return 'drawn';
    }
}

$widget = new Zz_Probe_Widget();
$say('the widget', [
    is_subclass_of($widget, WP_Widget::class),
    [$widget->id_base, $widget->name, $widget->option_name, $widget->widget_options, $widget->control_options, $widget->number, $widget->id, $widget->updated],
    $widget->get_field_name('title'), $widget->get_field_name('a[b]'), $widget->get_field_name('a[b][c]'),
    $widget->get_field_id('title'), $widget->get_field_id('a[b]'), $widget->get_field_id('a[b][c]'),
    $widget->is_preview(),
    $widget->get_settings(),
]);

$widget->save_settings([2 => ['title' => 'Two'], 5 => ['title' => 'Five'], 'bad' => 'x']);
$say('settings saved', [get_option('widget_zz_probe'), $widget->get_settings()]);

global $wp_registered_widgets, $wp_registered_widget_controls, $wp_registered_widget_updates;
$widget->_register();
$registered = array_filter($wp_registered_widgets, static fn ($w) => str_starts_with((string) $w['id'], 'zz_probe-'));
$say('registered', [
    array_keys($registered),
    array_map(static fn ($w) => [$w['name'], $w['classname'] ?? null, $w['description'] ?? null, $w['params'], is_callable($w['callback'])], $registered),
    array_map(static fn ($c) => [$c['name'], $c['width'] ?? null, $c['height'] ?? null, $c['id_base'] ?? null, $c['params']], array_filter($wp_registered_widget_controls, static fn ($c) => str_starts_with((string) $c['id'], 'zz_probe-'))),
    isset($wp_registered_widget_updates['zz_probe']),
    is_callable($widget->_get_display_callback()), is_callable($widget->_get_update_callback()), is_callable($widget->_get_form_callback()),
]);

$say('callbacks registered directly', (static function () {
    global $wp_registered_widget_controls, $wp_registered_widget_updates;
    $form = static fn () => 'form';
    _register_widget_update_callback('zz_direct', 'strtoupper', ['width' => 400], ['multi_number' => -1]);
    _register_widget_update_callback('zz_direct', 'strtolower');
    $update = $wp_registered_widget_updates['zz_direct'] ?? null;
    _register_widget_form_callback('ZZ_Direct-3', 'Zz Direct', $form, ['width' => '410', 'height' => 'x'], ['number' => 3]);
    $control = $wp_registered_widget_controls['zz_direct-3'] ?? null;
    // Registering the same form again replaces it only after widgets_init, which the engine's probe runtime has not fired: left out.
    _register_widget_update_callback('zz_direct', '');
    _register_widget_form_callback('zz_direct-3', 'Zz Direct', '');
    return [$update, $control === null ? null : array_diff_key($control, ['callback' => 1]), isset($wp_registered_widget_updates['zz_direct']), isset($wp_registered_widget_controls['zz_direct-3'])];
})());

$args = ['before_widget' => '<section>', 'after_widget' => '</section>', 'before_title' => '<h2>', 'after_title' => '</h2>', 'widget_id' => 'zz_probe-5'];
$say('displayed', [
    $echoed(static fn () => $widget->display_callback($args, ['number' => 5])),
    $echoed(static fn () => $widget->display_callback($args, 2)),
    $echoed(static fn () => $widget->display_callback($args, 9)),
    $widget->number, $widget->id,
]);
$filtered = static fn ($instance, $w, $a) => $instance === false ? false : $instance + ['title' => 'x'];
add_filter('widget_display_callback', static fn ($instance) => isset($instance['title']) && $instance['title'] === 'Five' ? false : $instance);
$say('displayed, a filter can stop it', $echoed(static fn () => $widget->display_callback($args, 5)));
remove_all_filters('widget_display_callback');

$_POST['widget-zz_probe'] = [5 => ['title' => '  <b>Five again</b> '], 7 => ['title' => 'Seven']];
$_POST['multi_number'] = '';
$_POST['widget-id'] = 'zz_probe-5';
$widget->update_callback();
$say('updated from the form', [get_option('widget_zz_probe'), $widget->updated]);
unset($_POST['widget-zz_probe'], $_POST['multi_number'], $_POST['widget-id']);

$say('form drawn', [
    $echoed(static fn () => $widget->form_callback(['number' => 5])),
    $echoed(static fn () => $widget->form_callback(-1)),
]);

$set = new Zz_Probe_Widget();
$set->_set(12);
$say('numbered', [$set->number, $set->id]);

$old = (static function () {
    $heard = [];
    $listen = static function ($class, $version, $parent) use (&$heard): void {
        $heard[] = [$class, $version, $parent];
    };
    add_action('deprecated_constructor_run', $listen, 10, 3);
    add_filter('deprecated_constructor_trigger_error', '__return_false');
    $w = new class () extends WP_Widget {
        public function __construct()
        {
        }
    };
    $w->WP_Widget('zz_probe_old', 'Zz Old', ['description' => 'Old style.']);
    remove_action('deprecated_constructor_run', $listen, 10);
    remove_filter('deprecated_constructor_trigger_error', '__return_false');
    return [$w->id_base, $w->name, $w->option_name, $w->widget_options, count($heard), $heard[0][1] ?? null];
})();
$say('the PHP 4 constructor', $old);

// The media widgets, through the image one.
$image = new WP_Widget_Media_Image();
$inside = static fn (string $method, ...$args) => (new ReflectionMethod($image, $method))->invoke($image, ...$args);
$attachment = (int) wp_insert_attachment(['post_title' => 'zz widget image', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'guid' => home_url('/zz-widget.jpg')]);
$made['posts'][] = $attachment;
$audio = (int) wp_insert_attachment(['post_title' => 'zz widget audio', 'post_mime_type' => 'audio/mpeg', 'post_status' => 'inherit']);
$made['posts'][] = $audio;
$schema = $image->get_instance_schema();
$say('media widget', [
    is_subclass_of($image, WP_Widget_Media::class),
    [$image->id_base, $image->widget_options['mime_type'] ?? null],
    array_keys($schema),
    array_map(static fn ($field) => array_intersect_key((array) $field, array_flip(['type', 'default', 'enum', 'media_prop', 'should_preview_update'])), $schema),
    $image->sanitize_token_list('a b  c, d<e>'),
    $image->sanitize_token_list(['x', 'y z', 'w!']),
    $image->is_attachment_with_mime_type($attachment, 'image'),
    $image->is_attachment_with_mime_type($audio, 'image'),
    $image->is_attachment_with_mime_type($audio, 'audio'),
    $image->is_attachment_with_mime_type(999999999, 'image'),
    $image->is_attachment_with_mime_type('nope', 'image'),
    $inside('has_content', ['attachment_id' => $attachment, 'url' => '']),
    $inside('has_content', ['attachment_id' => 0, 'url' => 'https://img.example/x.png']),
    $inside('has_content', ['attachment_id' => 0, 'url' => '']),
    $inside('has_content', ['attachment_id' => $audio, 'url' => '']),
]);
$say('media widget labels', $mask([
    array_keys($image->l10n),
    $image->l10n,
    (static function () use ($image) {
        $method = new ReflectionMethod($image, 'get_default_description');
        return $method->invoke($image);
    })(),
    (static function () use ($image) {
        $method = new ReflectionMethod($image, 'get_l10n_defaults');
        return array_keys($method->invoke($image));
    })(),
    (static function () use ($image) {
        $image->reset_default_labels();
        return $image->l10n['add_media'] ?? null;
    })(),
]));
$say('media widget rendered', $mask([
    $echoed(static fn () => $image->render_media(['attachment_id' => 0, 'url' => 'https://img.example/x.png', 'alt' => 'An image', 'caption' => '', 'size' => 'full', 'width' => 100, 'height' => 50, 'link_type' => 'none'])),
    $echoed(static fn () => $image->widget(['before_widget' => '<div>', 'after_widget' => '</div>', 'before_title' => '<h3>', 'after_title' => '</h3>', 'widget_id' => 'media_image-3'], ['url' => 'https://img.example/x.png', 'title' => 'Zz Img', 'alt' => 'Alt'])),
    $echoed(static fn () => $image->widget(['before_widget' => '<div>', 'after_widget' => '</div>', 'before_title' => '<h3>', 'after_title' => '</h3>'], ['url' => ''])),
    $echoed(static fn () => $image->render_media(['url' => 'https://img.example/z.png', 'width' => 0, 'height' => 0])),
    $echoed(static fn () => $image->render_media(['url' => 'https://img.example/n.png'])),
    $echoed(static fn () => $image->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], ['url' => 'https://img.example/w.png', 'width' => 30, 'height' => 20, 'link_type' => 'custom', 'link_url' => 'https://link.example/', 'caption' => 'Cap'])),
    $image->update(['title' => ' <b>T</b> ', 'url' => 'javascript:x', 'size' => 'bogus', 'width' => '20', 'link_type' => 'nope', 'extra' => 'gone'], []),
    $image->display_media_state([], get_post($attachment)),
    $echoed(static fn () => $image->form([])),
    $echoed(static fn () => $image->enqueue_preview_scripts()),
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
