<?php
/**
 * The REST widgets controller as a class (probe rest-widgets-class):
 * WP_REST_Widgets_Controller called directly, as a plugin would. Its
 * schema and parameters, the permission checks for nobody, an
 * administrator and a contributor (a sidebar shown in REST among them),
 * the widgets listed and one up close, widgets created (raw settings,
 * encoded ones, form data, a type that refuses raw settings, an unknown
 * type), updated and moved, deleted with and without force, and the
 * helpers: the sidebar read check, an item prepared, its links. One
 * controller is used throughout, so the widgets are retrieved once, from
 * a state both stacks leave alone (retrieve_widgets is owed). The widget
 * options, the theme's mods and the probe's user are put back or removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$kept = [];
$stylesheet = (string) get_option('stylesheet');
foreach (['sidebars_widgets', 'widget_zz_widgets_probe', 'widget_zz_widgets_plain', "theme_mods_{$stylesheet}"] as $option) {
    $kept[$option] = get_option($option, null);
}
$made = ['users' => [], 'widgets' => []];
register_shutdown_function(static function () use (&$made, $kept): void {
    foreach ($kept as $option => $value) {
        $value === null ? delete_option($option) : update_option($option, $value);
    }
    if ($made['users'] !== [] && !function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_user($id);
        }
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$made, $host) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = $key === 'hash' ? '{hash}' : $mask($item);
        }
        return $out;
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($made['widgets'] as $n => $id) {
        $value = str_replace($id, '{widget' . $n . '}', $value);
    }
    return (string) preg_replace('/"_wpnonce" value="[0-9a-f]+"/', '"_wpnonce" value="{nonce}"', str_replace($host, '{host}', $value));
};
$answer = static function ($response) use ($mask) {
    if (is_wp_error($response)) {
        return ['error', $response->get_error_code(), $response->get_error_message(), $response->get_error_data()];
    }
    if (!$response instanceof WP_REST_Response) {
        return ['not a response', $mask($response)];
    }
    $headers = $response->get_headers();
    ksort($headers);
    return [$response->get_status(), $mask($response->get_data()), $mask($response->get_links()), $mask($headers)];
};
$try = static function (Closure $run) use ($answer) {
    try {
        return $answer($run());
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
$request = static function (string $method, string $route, array $params = [], ?array $body = null) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    if ($body !== null) {
        $request->set_header('content-type', 'application/json');
        $request->set_body(wp_json_encode($body));
    }
    return $request;
};
// A protected method, called from inside its class.
$reach = static fn (object $controller, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $controller, get_class($controller))(...$args);
// A list keeps only the probe's widgets, so other sidebars' widgets do not move it.
$ours = static function ($response) use ($answer) {
    if ($response instanceof WP_REST_Response && is_array($response->get_data())) {
        $response->set_data(array_values(array_filter($response->get_data(), static fn ($item) => str_starts_with((string) ($item['id'] ?? ''), 'zz_widgets_'))));
    }
    return $answer($response);
};

if (!class_exists('Zz_Widgets_Probe_Widget')) {
    class Zz_Widgets_Probe_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('zz_widgets_probe', 'Zz Widgets Probe', ['description' => 'A probe widget.', 'classname' => 'zz-widgets-probe', 'show_instance_in_rest' => true]);
        }

        public function widget($args, $instance)
        {
            echo $args['before_widget'], '<p>', esc_html($instance['words'] ?? 'none'), '</p>', $args['after_widget'];
        }

        public function form($instance)
        {
            echo '<input name="', esc_attr($this->get_field_name('words')), '" value="', esc_attr($instance['words'] ?? ''), '">';
            return '';
        }

        public function update($new_instance, $old_instance)
        {
            return ['words' => sanitize_text_field($new_instance['words'] ?? ($old_instance['words'] ?? ''))];
        }
    }

    class Zz_Widgets_Plain_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('zz_widgets_plain', 'Zz Widgets Plain', ['description' => 'Keeps its settings to itself.']);
        }

        public function widget($args, $instance)
        {
            echo $args['before_widget'], 'plain:', esc_html($instance['words'] ?? ''), $args['after_widget'];
        }

        public function update($new_instance, $old_instance)
        {
            return ['words' => sanitize_text_field($new_instance['words'] ?? '')];
        }
    }
}
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
register_widget('Zz_Widgets_Probe_Widget');
register_widget('Zz_Widgets_Plain_Widget');
// Registered after widgets_init, the types have no update callback until they register themselves, as widgets_init would.
foreach (['zz_widgets_probe', 'zz_widgets_plain'] as $base) {
    $GLOBALS['wp_widget_factory']->get_widget_object($base)->_register();
}
register_sidebar(['id' => 'zz-widgets-rail', 'name' => 'Zz Widgets Rail', 'before_widget' => '<div id="%1$s" class="%2$s">', 'after_widget' => '</div>']);
register_sidebar(['id' => 'zz-widgets-open', 'name' => 'Zz Widgets Open', 'show_in_rest' => true]);
remove_theme_mod('sidebars_widgets');
$sidebars = (array) get_option('sidebars_widgets', []);
$sidebars['zz-widgets-rail'] = [];
$sidebars['zz-widgets-open'] = [];
update_option('sidebars_widgets', $sidebars);
$GLOBALS['_wp_sidebars_widgets'] = [];

$contributor = wp_insert_user(['user_login' => 'zzwidgetscontributor', 'user_email' => 'zzwidgetscontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-widgets-class: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;

$widgets = new WP_REST_Widgets_Controller();
wp_set_current_user(1);
// The one retrieval, before the probe makes any widget.
$reach($widgets, 'retrieve_widgets');
$say('the class', [get_parent_class($widgets), $widgets->get_item_schema(), $widgets->get_collection_params()]);

foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", [
        $try(static fn () => $widgets->get_items_permissions_check($request('GET', '/wp/v2/widgets'))),
        $try(static fn () => $widgets->get_items_permissions_check($request('GET', '/wp/v2/widgets', ['sidebar' => 'zz-widgets-open']))),
        $try(static fn () => $widgets->get_items_permissions_check($request('GET', '/wp/v2/widgets', ['sidebar' => 'zz-widgets-rail']))),
        $try(static fn () => $widgets->create_item_permissions_check($request('POST', '/wp/v2/widgets'))),
        $try(static fn () => $widgets->update_item_permissions_check($request('PUT', '/wp/v2/widgets/zz-none', ['id' => 'zz-none']))),
        $try(static fn () => $widgets->delete_item_permissions_check($request('DELETE', '/wp/v2/widgets/zz-none', ['id' => 'zz-none']))),
        $try(static fn () => $reach($widgets, 'permissions_check', $request('GET', '/wp/v2/widgets'))),
        [$reach($widgets, 'check_read_sidebar_permission', 'zz-widgets-open'), $reach($widgets, 'check_read_sidebar_permission', 'zz-widgets-rail'), $reach($widgets, 'check_read_sidebar_permission', 'zz-none')],
    ]);
}

wp_set_current_user(1);
$created = static function (string $label, array $body) use ($widgets, $request, $try, &$made) {
    $result = $try(static fn () => $widgets->create_item($request('POST', '/wp/v2/widgets', [], $body)));
    if (($result[0] ?? null) === 201 && isset($result[1]['id'])) {
        $made['widgets'][] = (string) $result[1]['id'];
    }
    return $result;
};
$raw = $created('raw', ['id_base' => 'zz_widgets_probe', 'sidebar' => 'zz-widgets-rail', 'instance' => ['raw' => ['words' => 'Raw words']]]);
$serialized = serialize(['words' => 'Encoded words']);
$say('widgets created', [
    'raw settings' => $raw,
    'encoded settings' => $created('encoded', ['id_base' => 'zz_widgets_probe', 'sidebar' => 'zz-widgets-open', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)]]),
    'a bad hash' => $created('bad hash', ['id_base' => 'zz_widgets_probe', 'sidebar' => 'zz-widgets-rail', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => 'zz']]),
    'form data' => $created('form data', ['id_base' => 'zz_widgets_plain', 'sidebar' => 'zz-widgets-rail', 'form_data' => ['widget-zz_widgets_plain' => [2 => ['words' => 'From the form']]]]),
    'raw for a type that keeps them' => $created('refused raw', ['id_base' => 'zz_widgets_plain', 'instance' => ['raw' => ['words' => 'x']]]),
    'an unknown type' => $created('unknown', ['id_base' => 'zz-none']),
    'no sidebar named' => $created('inactive', ['id_base' => 'zz_widgets_probe', 'instance' => ['raw' => ['words' => 'Nowhere']]]),
    'where that one went' => $mask(wp_find_widgets_sidebar((string) end($made['widgets']))),
    'no type named' => $created('no type', ['instance' => ['raw' => ['words' => 'x']]]),
    'settings neither raw nor encoded' => $created('neither', ['id_base' => 'zz_widgets_probe', 'instance' => ['words' => 'x']]),
]);
$first = $made['widgets'][0] ?? 'zz-none';
$second = $made['widgets'][1] ?? 'zz-none';
$say('widgets read', [
    $ours($widgets->get_items($request('GET', '/wp/v2/widgets'))),
    $ours($widgets->get_items($request('GET', '/wp/v2/widgets', ['sidebar' => 'zz-widgets-rail']))),
    $ours($widgets->get_items($request('GET', '/wp/v2/widgets', ['sidebar' => 'zz-widgets-open', 'context' => 'embed']))),
    $try(static fn () => $widgets->get_item($request('GET', '/wp/v2/widgets/' . $first, ['id' => $first, 'context' => 'edit']))),
    $try(static fn () => $widgets->get_item($request('GET', '/wp/v2/widgets/zz-none', ['id' => 'zz-none']))),
    $try(static fn () => $widgets->prepare_item_for_response(['widget_id' => $first, 'sidebar_id' => 'zz-widgets-rail'], $request('GET', '/wp/v2/widgets', ['_fields' => 'id,sidebar,instance']))),
    $try(static fn () => $widgets->prepare_item_for_response(['widget_id' => 'zz-none', 'sidebar_id' => 'zz-widgets-rail'], $request('GET', '/wp/v2/widgets'))),
    $mask($reach($widgets, 'prepare_links', ['id' => $first, 'sidebar' => 'zz-widgets-rail'])),
    $mask($reach($widgets, 'prepare_links', ['id' => $first, 'sidebar' => 'wp_inactive_widgets'])),
]);
$updated = static fn (string $id, array $body) => $try(static fn () => $widgets->update_item($request('PUT', '/wp/v2/widgets/' . $id, ['id' => $id], $body)));
$say('widgets updated', [
    'new raw settings' => $updated($first, ['instance' => ['raw' => ['words' => 'Raw again']]]),
    'moved to another sidebar' => $updated($first, ['sidebar' => 'zz-widgets-open']),
    'moved and changed' => $updated($second, ['sidebar' => 'zz-widgets-rail', 'instance' => ['raw' => ['words' => 'Both']]]),
    'nothing given' => $updated($second, []),
    'an unknown widget' => $updated('zz-none', ['instance' => ['raw' => []]]),
    'an unknown widget of a known type, with a sidebar' => $updated('zz_widgets_probe-999', ['sidebar' => 'zz-widgets-rail', 'instance' => ['raw' => ['words' => 'Born on update']]]),
]);
$deleted = static fn (string $id, bool $force) => $try(static fn () => $widgets->delete_item($request('DELETE', '/wp/v2/widgets/' . $id, ['id' => $id, 'force' => $force])));
$say('widgets deleted', [
    'without force' => $deleted($first, false),
    'with force' => $deleted($second, true),
    'an unknown widget' => $deleted('zz-none', true),
    // The widget made with no sidebar is left out: on the reference, the self links' target hints run the route's own
    // permission check, whose retrieve_widgets files it among the inactive widgets (retrieve_widgets is owed).
    'what is left' => (static function () use ($mask, &$made) {
        $sidebars = wp_get_sidebars_widgets();
        $nowhere = $made['widgets'][3] ?? '';
        $mine = static fn ($ids) => array_values(array_filter((array) $ids, static fn ($id) => str_starts_with((string) $id, 'zz_widgets_') && $id !== $nowhere));
        return $mask([$mine($sidebars['zz-widgets-rail'] ?? []), $mine($sidebars['zz-widgets-open'] ?? []), $mine($sidebars['wp_inactive_widgets'] ?? []), array_keys(array_filter((array) get_option('widget_zz_widgets_probe', []), 'is_array'))]);
    })(),
]);
$say('a widget saved by the helper', $try(static fn () => $mask($reach($widgets, 'save_widget', $request('POST', '/wp/v2/widgets', [], ['id_base' => 'zz_widgets_probe', 'instance' => ['raw' => ['words' => 'Helper']]]), 'zz-widgets-rail'))));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
