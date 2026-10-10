<?php
/**
 * The theme.json resolver's helpers (probe theme-json-resolver):
 * WP_Theme_JSON_Resolver's support check, the fields it translates and
 * translating them, a theme file's path (the theme's own, its parent's, a
 * missing one), reading a JSON file (the theme's, a missing one, broken
 * JSON, a scalar), whether the registered blocks changed since an origin
 * last looked, and the theme file URIs a theme.json names ("file:./...")
 * found and resolved, at the top, in a block, in an element and in a
 * block's variation. The probe's files and block live for the request
 * only.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$files = [];
register_shutdown_function(static function () use (&$files): void {
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
});
add_filter('doing_it_wrong_trigger_error', '__return_false');
add_filter('deprecated_function_trigger_error', '__return_false');
$heard = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$heard): void {
    $heard[] = ['deprecated', $function, $replacement, $version];
}, 10, 3);
$stylesheet = get_stylesheet_directory();
$template = get_template_directory();
$stylesheetUri = get_stylesheet_directory_uri();
$templateUri = get_template_directory_uri();
$upload = wp_get_upload_dir();
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, $stylesheet, $template, $stylesheetUri, $templateUri, $upload, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    // Paths as given and as resolved (a reported path is the real one, through any link).
    $real = static fn (string $path) => wp_normalize_path((string) realpath($path));
    return str_replace([$stylesheetUri, $templateUri, $stylesheet, $template, $real($stylesheet), $real($template), $upload['basedir'], $real($upload['basedir']), $host], ['{stylesheet uri}', '{template uri}', '{stylesheet}', '{template}', '{stylesheet}', '{template}', '{uploads}', '{uploads}', '{host}'], $value);
};
$try = static function (Closure $run) use ($mask) {
    try {
        $value = $run();
        return $mask($value instanceof WP_Theme_JSON ? ['a theme.json object' => $value->get_raw_data()] : $value);
    } catch (Throwable $e) {
        return ['threw' => get_class($e)];
    }
};
// A protected static method, called from inside its class.
$reach = static fn (string $method, ...$args) => Closure::bind(static fn (...$given) => static::{$method}(...$given), null, WP_Theme_JSON_Resolver::class)(...$args);

$say('the theme', [get_stylesheet(), get_template(), WP_Theme_JSON_Resolver::theme_has_support()]);
$say('the fields it translates', WP_Theme_JSON_Resolver::get_fields_to_translate());
$say('deprecations announced', $heard);
// What reading a file reports, from here on.
$heard = [];
add_action('wp_trigger_error_run', static function ($function, $message) use (&$heard, $mask): void {
    $heard[] = [$function, $mask($message)];
}, 10, 2);

$say('a theme file\'s path', [
    'theme.json' => $try(static fn () => $reach('get_file_path_from_theme', 'theme.json')),
    'theme.json, from the parent' => $try(static fn () => $reach('get_file_path_from_theme', 'theme.json', true)),
    'a style variation' => $try(static fn () => $reach('get_file_path_from_theme', 'styles/01-evening.json')),
    'a missing file' => $try(static fn () => $reach('get_file_path_from_theme', 'zz-missing.json')),
    'an empty name' => $try(static fn () => $reach('get_file_path_from_theme', '')),
]);

$write = static function (string $name, string $body) use ($upload, &$files): string {
    $file = $upload['basedir'] . '/' . $name;
    file_put_contents($file, $body);
    $files[] = $file;
    return $file;
};
$read = static function (string $file) use ($reach, $try) {
    return $try(static function () use ($reach, $file) {
        $data = $reach('read_json_file', $file);
        return is_array($data) && isset($data['$schema']) ? ['keys' => array_keys($data), 'version' => $data['version'] ?? null, 'palette' => count($data['settings']['color']['palette'] ?? [])] : $data;
    });
};
$say('reading a JSON file', [
    'the theme\'s' => $read($stylesheet . '/theme.json'),
    'a missing one' => $read($upload['basedir'] . '/zz-missing.json'),
    'broken JSON' => $read($write('zz-broken.json', '{"version": 3, "settings": ')),
    'a scalar' => $read($write('zz-scalar.json', '"just a string"')),
    'an empty object' => $read($write('zz-empty.json', '{}')),
    'a small one' => $read($write('zz-small.json', '{"version": 3, "title": "Zz Small", "settings": {"color": {"palette": [{"slug": "zz", "color": "#123456", "name": "Zz"}]}}}')),
    'the small one again, changed on disk' => $read($write('zz-small.json', '{"version": 3, "title": "Zz Changed"}')),
]);

$translatable = [
    'version' => 3,
    'title' => 'Zz Title',
    'settings' => [
        'color' => ['palette' => [['name' => 'Zz Red', 'slug' => 'zz-red', 'color' => '#ff0000']], 'gradients' => [['name' => 'Zz Fade', 'slug' => 'zz-fade', 'gradient' => 'linear-gradient(#fff,#000)']]],
        'typography' => ['fontSizes' => [['name' => 'Zz Big', 'slug' => 'zz-big', 'size' => '3rem']]],
        'blocks' => ['core/paragraph' => ['color' => ['palette' => [['name' => 'Zz Block Blue', 'slug' => 'zz-blue', 'color' => '#00f']]]]],
    ],
    'customTemplates' => [['name' => 'zz-template', 'title' => 'Zz Template']],
    'templateParts' => [['name' => 'zz-part', 'title' => 'Zz Part', 'area' => 'header']],
];
// Every translation is tagged with its context and domain, so what is translated shows.
$tag = static fn ($translation, $text, $context, $domain) => "[{$context}|{$domain}] {$translation}";
add_filter('gettext_with_context', $tag, 10, 4);
$say('translating', [
    'in the default domain' => $try(static fn () => $reach('translate', $translatable)),
    'in a theme\'s domain' => $try(static fn () => $reach('translate', $translatable, 'zz-domain')),
    'nothing to translate' => $try(static fn () => $reach('translate', ['version' => 3])),
    'not an array' => $try(static fn () => $reach('translate', 'zz-text')),
]);
// A schema as the file decodes: objects for keyed parts, a one-item list for a list's items, "*" for any key.
$schema = (object) ['title' => 'Zz title context', 'items' => [(object) ['label' => 'Zz item context']], 'groups' => (object) ['*' => (object) ['name' => 'Zz group context']], 'note' => 'Zz note context'];
$say('translating by a schema', [
    'strings, lists, wildcards and keyed parts' => $try(static fn () => translate_settings_using_i18n_schema($schema, ['title' => 'A', 'items' => [['label' => 'B', 'other' => 'C'], ['label' => 'D']], 'groups' => ['one' => ['name' => 'E'], 'two' => ['name' => 'F', 'x' => 'G']], 'note' => 'H', 'untouched' => 'I'], 'zz-domain')),
    'a string by a string' => $try(static fn () => translate_settings_using_i18n_schema('Zz context', 'J', 'zz-domain')),
    'a list where keys are wanted' => $try(static fn () => translate_settings_using_i18n_schema((object) ['title' => 'Zz'], ['K', 'L'], 'zz-domain')),
    'a string where keys are wanted' => $try(static fn () => translate_settings_using_i18n_schema((object) ['title' => 'Zz'], 'M', 'zz-domain')),
    'a string where a list is wanted' => $try(static fn () => translate_settings_using_i18n_schema([(object) ['label' => 'Zz']], 'N', 'zz-domain')),
    'a number' => $try(static fn () => translate_settings_using_i18n_schema('Zz context', 42, 'zz-domain')),
    'a key the settings lack' => $try(static fn () => translate_settings_using_i18n_schema((object) ['missing' => 'Zz'], ['title' => 'O'], 'zz-domain')),
    'an empty keyed schema' => $try(static fn () => translate_settings_using_i18n_schema((object) [], ['title' => 'P'], 'zz-domain')),
]);
remove_filter('gettext_with_context', $tag, 10);

$say('what reading reported', $heard);

// Each origin remembers the blocks it last saw; clearing the cache forgets them all.
WP_Theme_JSON_Resolver::clean_cached_data();
$looks = [];
foreach (['core', 'blocks', 'theme', 'user', 'custom', 'zzorigin'] as $origin) {
    $looks[$origin] = [$try(static fn () => $reach('has_same_registered_blocks', $origin)), $try(static fn () => $reach('has_same_registered_blocks', $origin))];
}
$say('the registered blocks since an origin last looked', [
    'each origin, twice' => $looks,
    'after a block is registered' => $try(static function () use ($reach) {
        register_block_type('zz/resolver-probe', ['title' => 'Zz Resolver Probe']);
        return [$reach('has_same_registered_blocks', 'theme'), $reach('has_same_registered_blocks', 'theme'), $reach('has_same_registered_blocks', 'core')];
    }),
    'after it is unregistered' => $try(static function () use ($reach) {
        unregister_block_type('zz/resolver-probe');
        return [$reach('has_same_registered_blocks', 'theme'), $reach('has_same_registered_blocks', 'theme')];
    }),
    'after the cache is cleared' => $try(static function () use ($reach) {
        WP_Theme_JSON_Resolver::clean_cached_data();
        return [$reach('has_same_registered_blocks', 'blocks'), $reach('has_same_registered_blocks', 'blocks')];
    }),
]);
$say('a file read again once the cache is cleared', $read($upload['basedir'] . '/zz-small.json'));

// A variation the block has, so the theme.json object keeps it.
register_block_style('core/group', ['name' => 'zz-variation', 'label' => 'Zz Variation']);
$withFiles = [
    'version' => 3,
    'styles' => [
        'background' => ['backgroundImage' => ['url' => 'file:./assets/zz-top.png'], 'backgroundSize' => 'cover'],
        'blocks' => [
            'core/group' => [
                'background' => ['backgroundImage' => ['url' => 'file:./assets/zz-group.jpg']],
                'variations' => ['zz-variation' => ['background' => ['backgroundImage' => ['url' => 'file:./zz-variation.webp']]]],
            ],
            'core/cover' => ['background' => ['backgroundImage' => ['url' => 'https://example.com/zz-remote.png']]],
        ],
        'elements' => ['link' => ['background' => ['backgroundImage' => ['url' => 'file:./assets/zz-link.gif']]]],
    ],
];
$say('theme file URIs', [
    'found' => $try(static fn () => WP_Theme_JSON_Resolver::get_resolved_theme_uris(new WP_Theme_JSON($withFiles))),
    'found in the theme' => $try(static fn () => WP_Theme_JSON_Resolver::get_resolved_theme_uris(WP_Theme_JSON_Resolver::get_theme_data())),
    'none' => $try(static fn () => WP_Theme_JSON_Resolver::get_resolved_theme_uris(new WP_Theme_JSON(['version' => 3]))),
    'resolved' => $try(static fn () => WP_Theme_JSON_Resolver::resolve_theme_file_uris(new WP_Theme_JSON($withFiles))),
    'resolved, none' => $try(static fn () => WP_Theme_JSON_Resolver::resolve_theme_file_uris(new WP_Theme_JSON(['version' => 3, 'styles' => ['color' => ['text' => '#111']]]))),
]);

unregister_block_style('core/group', 'zz-variation');

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
