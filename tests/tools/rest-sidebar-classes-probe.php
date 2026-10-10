<?php
/**
 * The REST widget-type and sidebar controllers as classes (probe
 * rest-sidebar-classes): WP_REST_Widget_Types_Controller and
 * WP_REST_Sidebars_Controller called directly, as a plugin would. Their
 * schemas and parameters, the permission checks for nobody, an
 * administrator and a contributor, the widget types (a widget the probe
 * registers among them) with their helpers and a settings encode, the
 * sidebars (one the probe registers, the inactive widgets) with their
 * helpers, and a sidebar's widgets saved. The widget options and the
 * probe's user are put back or removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$kept = [];
$stylesheet = (string) get_option('stylesheet');
foreach (['sidebars_widgets', 'widget_search', 'widget_text', 'widget_zz_sidebar_probe', "theme_mods_{$stylesheet}"] as $option) {
    $kept[$option] = get_option($option, null);
}
$made = ['users' => []];
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
$mask = static function ($value) use (&$mask, $host) {
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
$request = static function (string $method, string $route, array $params = []) {
    $request = new WP_REST_Request($method, $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    return $request;
};
// A protected method, called from inside its class.
$reach = static fn (object $controller, string $method, ...$args) => Closure::bind(fn (...$given) => $this->{$method}(...$given), $controller, get_class($controller))(...$args);
// A list keeps only the entries named, so other registrations do not move it.
$only = static function ($response, array $keep) use ($answer) {
    if ($response instanceof WP_REST_Response && is_array($response->get_data())) {
        $response->set_data(array_values(array_filter($response->get_data(), static fn ($item) => in_array($item['id'] ?? null, $keep, true))));
    }
    return $answer($response);
};

if (!class_exists('Zz_Sidebar_Probe_Widget')) {
    class Zz_Sidebar_Probe_Widget extends WP_Widget
    {
        public function __construct()
        {
            parent::__construct('zz_sidebar_probe', 'Zz Sidebar Probe', ['description' => 'A probe widget.', 'classname' => 'zz-probe-widget', 'show_instance_in_rest' => true]);
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
            return ['words' => sanitize_text_field($new_instance['words'] ?? '')];
        }
    }
}
if (!did_action('widgets_init')) {
    wp_widgets_init();
}
register_widget('Zz_Sidebar_Probe_Widget');
register_sidebar(['id' => 'zz-probe-rail', 'name' => 'Zz Probe Rail', 'description' => 'A probe sidebar.', 'class' => 'zz-rail', 'before_widget' => '<div id="%1$s" class="%2$s">', 'after_widget' => '</div>', 'before_title' => '<h3>', 'after_title' => '</h3>']);
update_option('widget_zz_sidebar_probe', [3 => ['words' => 'Third'], 4 => ['words' => 'Fourth'], '_multiwidget' => 1]);
update_option('widget_search', [5 => ['title' => 'Zz Find'], '_multiwidget' => 1]);
// retrieve_widgets moves a sidebar's widgets differently on each stack under a block theme (owed), so the
// probe's widgets start where it leaves them: the rail empty, the probe's widgets first among the inactive.
// A stash of an earlier theme's widgets (a theme switch elsewhere leaves one) is set aside for the run.
remove_theme_mod('sidebars_widgets');
$sidebars = (array) get_option('sidebars_widgets', []);
$sidebars['zz-probe-rail'] = [];
$sidebars['wp_inactive_widgets'] = array_values(array_unique(array_merge(['zz_sidebar_probe-3', 'zz_sidebar_probe-4'], (array) ($sidebars['wp_inactive_widgets'] ?? []))));
update_option('sidebars_widgets', $sidebars);
$GLOBALS['_wp_sidebars_widgets'] = [];
register_sidebar(['id' => 'zz-probe-open', 'name' => 'Zz Probe Open', 'show_in_rest' => true]);
// Widgets registered after widgets_init are not yet registered instances; one of each is registered here, the other kind left unregistered.
$GLOBALS['wp_widget_factory']->get_widget_object('zz_sidebar_probe')->_register();

$contributor = wp_insert_user(['user_login' => 'zzsidebarcontributor', 'user_email' => 'zzsidebarcontributor@example.com', 'user_pass' => wp_generate_password(), 'role' => 'contributor']);
if (!is_int($contributor) || $contributor < 2) {
    fwrite(STDERR, "rest-sidebar-classes: the contributor could not be made\n");
    exit(1);
}
$made['users'][] = $contributor;

$types = new WP_REST_Widget_Types_Controller();
$sidebarsController = new WP_REST_Sidebars_Controller();
$say('the classes', [get_parent_class($types), get_parent_class($sidebarsController)]);
$say('schemas and parameters', [
    'widget types' => [$types->get_item_schema(), $types->get_collection_params()],
    'sidebars' => $sidebarsController->get_item_schema(),
]);

foreach ([0 => 'nobody', 1 => 'an administrator', $contributor => 'a contributor'] as $user => $who) {
    wp_set_current_user($user);
    $say("permissions, {$who}", [
        'widget types' => [
            $try(static fn () => $types->get_items_permissions_check($request('GET', '/wp/v2/widget-types'))),
            $try(static fn () => $types->get_item_permissions_check($request('GET', '/wp/v2/widget-types/search', ['id' => 'search']))),
            $try(static fn () => $reach($types, 'check_read_permission')),
        ],
        'sidebars' => [
            $try(static fn () => $sidebarsController->get_items_permissions_check($request('GET', '/wp/v2/sidebars'))),
            $try(static fn () => $sidebarsController->get_item_permissions_check($request('GET', '/wp/v2/sidebars/zz-probe-rail', ['id' => 'zz-probe-rail']))),
            $try(static fn () => $sidebarsController->get_item_permissions_check($request('GET', '/wp/v2/sidebars/zz-none', ['id' => 'zz-none']))),
            $try(static fn () => $sidebarsController->update_item_permissions_check($request('POST', '/wp/v2/sidebars/zz-probe-rail', ['id' => 'zz-probe-rail']))),
            $try(static fn () => $reach($sidebarsController, 'do_permissions_check')),
            $try(static fn () => $reach($sidebarsController, 'check_read_permission', $reach($sidebarsController, 'get_sidebar', 'zz-probe-rail'))),
            $try(static fn () => $reach($sidebarsController, 'check_read_permission', $reach($sidebarsController, 'get_sidebar', 'zz-probe-open'))),
            $try(static fn () => $sidebarsController->get_item_permissions_check($request('GET', '/wp/v2/sidebars/zz-probe-open', ['id' => 'zz-probe-open']))),
            $only($sidebarsController->get_items($request('GET', '/wp/v2/sidebars')), ['zz-probe-rail', 'zz-probe-open', 'wp_inactive_widgets']),
        ],
    ]);
}

wp_set_current_user(1);
$keepTypes = ['search', 'text', 'zz_sidebar_probe'];
$say('widget types', [
    $only($types->get_items($request('GET', '/wp/v2/widget-types')), $keepTypes),
    $only($types->get_items($request('GET', '/wp/v2/widget-types', ['context' => 'embed'])), $keepTypes),
    $try(static fn () => $types->get_item($request('GET', '/wp/v2/widget-types/zz_sidebar_probe', ['id' => 'zz_sidebar_probe', 'context' => 'edit']))),
    $try(static fn () => $types->get_item($request('GET', '/wp/v2/widget-types/zz-none', ['id' => 'zz-none']))),
    $mask($types->get_widget('zz_sidebar_probe')),
    $try(static fn () => $types->get_widget('zz-none')),
    $mask(array_values(array_filter($reach($types, 'get_widgets'), static fn ($widget) => in_array($widget['id'] ?? null, $keepTypes, true)))),
    $try(static fn () => $types->prepare_item_for_response($types->get_widget('search'), $request('GET', '/wp/v2/widget-types', ['_fields' => 'id,name,is_multi']))),
    $mask($reach($types, 'prepare_links', $types->get_widget('search'))),
]);
$serialized = serialize(['words' => 'Encoded']);
$encode = static function (array $params) use ($types, $request, $try) {
    $out = new WP_REST_Request('POST', '/wp/v2/widget-types/' . $params['id'] . '/encode');
    $out->set_url_params(['id' => $params['id']]);
    $out->set_header('content-type', 'application/json');
    $out->set_body(wp_json_encode(array_diff_key($params, ['id' => 1])));
    return $try(static fn () => $types->encode_form_data($out));
};
$say('settings encoded', [
    'nothing given' => $encode(['id' => 'zz_sidebar_probe']),
    'an instance' => $encode(['id' => 'zz_sidebar_probe', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)]]),
    'a bad hash' => $encode(['id' => 'zz_sidebar_probe', 'instance' => ['encoded' => base64_encode($serialized), 'hash' => 'zz']]),
    'form data' => $encode(['id' => 'zz_sidebar_probe', 'number' => 7, 'form_data' => 'widget-zz_sidebar_probe[7][words]=From+the+form']),
    'form data as the route hands it on' => $encode(['id' => 'zz_sidebar_probe', 'number' => 7, 'form_data' => ['widget-zz_sidebar_probe' => [7 => ['words' => 'From the form']]]]),
    'an unknown type' => $encode(['id' => 'zz-none']),
]);
// A render is a whole preview page; what is compared is its shape and that it holds the widget's own markup.
$render = static function (string $id, array $params) use ($types, $answer) {
    $out = new WP_REST_Request('POST', '/wp/v2/widget-types/' . $id . '/render');
    $out->set_url_params(['id' => $id]);
    foreach ($params as $key => $value) {
        $out->set_param($key, $value);
    }
    ob_start();
    $result = $types->render($out);
    ob_end_clean();
    return is_array($result) ? [array_keys($result), str_contains((string) ($result['preview'] ?? ''), '<div class="widget zz-probe-widget"><p>Encoded</p></div>'), str_starts_with(ltrim((string) ($result['preview'] ?? '')), '<!doctype html>')] : $answer($result);
};
$say('a widget rendered', [
    $render('zz_sidebar_probe', ['instance' => ['encoded' => base64_encode($serialized), 'hash' => wp_hash($serialized)]]),
    $render('zz-none', []),
]);

$keepSidebars = ['zz-probe-rail', 'zz-probe-open', 'wp_inactive_widgets'];
$say('sidebars', [
    $only($sidebarsController->get_items($request('GET', '/wp/v2/sidebars')), $keepSidebars),
    $try(static fn () => $sidebarsController->get_item($request('GET', '/wp/v2/sidebars/zz-probe-rail', ['id' => 'zz-probe-rail']))),
    $try(static fn () => $sidebarsController->get_item($request('GET', '/wp/v2/sidebars/wp_inactive_widgets', ['id' => 'wp_inactive_widgets', 'context' => 'embed']))),
    $try(static fn () => $sidebarsController->get_item($request('GET', '/wp/v2/sidebars/zz-none', ['id' => 'zz-none']))),
    $mask($reach($sidebarsController, 'get_sidebar', 'zz-probe-rail')),
    $mask($reach($sidebarsController, 'get_sidebar', 'wp_inactive_widgets')),
    $reach($sidebarsController, 'get_sidebar', 'zz-none'),
    $try(static fn () => $sidebarsController->prepare_item_for_response($reach($sidebarsController, 'get_sidebar', 'zz-probe-rail'), $request('GET', '/wp/v2/sidebars', ['_fields' => 'id,name,widgets,status']))),
    $mask($reach($sidebarsController, 'prepare_links', $reach($sidebarsController, 'get_sidebar', 'zz-probe-rail'))),
]);
$saved = static function (string $id, array $widgets) use ($sidebarsController, $try) {
    $out = new WP_REST_Request('PUT', '/wp/v2/sidebars/' . $id);
    $out->set_url_params(['id' => $id]);
    $out->set_header('content-type', 'application/json');
    $out->set_body(wp_json_encode(['widgets' => $widgets]));
    return $try(static fn () => $sidebarsController->update_item($out));
};
$say('a sidebar saved', [
    'one unregistered widget' => $saved('zz-probe-rail', ['search-5']),
    'one taken from the inactive widgets' => $saved('zz-probe-rail', ['zz_sidebar_probe-4', 'search-5']),
    'reordered, one dropped' => $saved('zz-probe-rail', ['search-5', 'zz_sidebar_probe-3']),
    'what the sidebars option holds' => (static function () {
        $sidebars = (array) get_option('sidebars_widgets', []);
        return [$sidebars['zz-probe-rail'] ?? null, array_values(array_filter((array) ($sidebars['wp_inactive_widgets'] ?? []), static fn ($id) => str_starts_with((string) $id, 'zz_') || $id === 'search-5'))];
    })(),
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
