<?php
/**
 * The script tags script modules print as, as the reference prints them
 * (probe module-tags): the import map, a module and its static and dynamic
 * dependencies, the preloads, and a module's data (with a closing tag in
 * it); what wp_script_attributes and wp_inline_script_attributes are
 * handed for each, and a plugin's nonce added to all of them. Same
 * protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
add_filter('wp_script_attributes', static function ($attributes) use (&$heard) {
    $heard[] = ['wp_script_attributes', $attributes];
    return $attributes;
});
add_filter('wp_inline_script_attributes', static function ($attributes, $data) use (&$heard) {
    $heard[] = ['wp_inline_script_attributes', $attributes, $data];
    return $attributes;
}, 10, 2);
wp_register_script_module('zz/dep', 'https://zz.example/dep.js', [], '2');
wp_register_script_module('zz/lazy', 'https://zz.example/lazy.js', [], '3');
wp_register_script_module('zz/mod', 'https://zz.example/mod.js', ['zz/dep', ['id' => 'zz/lazy', 'import' => 'dynamic']], '1');
wp_enqueue_script_module('zz/mod');
add_filter('script_module_data_zz/mod', static fn () => ['a' => '</script>', 'b' => '<!--']);
$modules = wp_script_modules();
$print = static function (string $method) use ($modules, &$heard): array {
    $heard = [];
    ob_start();
    $modules->$method();
    return [ob_get_clean(), $heard];
};
foreach (['print_import_map', 'print_enqueued_script_modules', 'print_script_module_preloads', 'print_script_module_data'] as $method) {
    $say($method, $print($method));
}
add_filter('wp_script_attributes', static fn ($attributes) => $attributes + ['nonce' => 'zz-nonce']);
add_filter('wp_inline_script_attributes', static fn ($attributes) => $attributes + ['nonce' => 'zz-nonce']);
foreach (['print_import_map', 'print_enqueued_script_modules', 'print_script_module_data'] as $method) {
    $say("{$method}, with a plugin's nonce", $print($method));
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
