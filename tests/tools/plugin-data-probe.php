<?php
/**
 * What get_plugin_data hands back and what it loads (probe plugin-data):
 * throwaway plugins the probe writes are read with markup and translation
 * on and off. Recorded per call: the fields, the load_textdomain actions it
 * fired (domain, file relative to the content folder) and whether the
 * domain is loaded after. One plugin carries a German translation of its
 * headers as a PHP translation file in its Domain Path, read with the
 * locale filtered to de_DE. Then get_plugins' view of them, and the REST
 * plugin item as an administrator sees it, with the loads it fired. The
 * probe's plugins are removed at the end, whatever happens. Same protocol
 * as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['wp-admin/includes/plugin.php'] as $admin) {
    if (is_file(ABSPATH . $admin)) {
        require_once ABSPATH . $admin;
    }
}
$dir = WP_PLUGIN_DIR;
$folders = ['zz-data-full', 'zz-data-plain', 'zz-data-german', 'zz-data-dotted', 'zz-data-deutsch'];
$sweep = static function () use ($dir, $folders): void {
    foreach ($folders as $folder) {
        foreach (glob("{$dir}/{$folder}/{,*/}*", GLOB_BRACE) ?: [] as $file) {
            is_dir($file) ? null : unlink($file);
        }
        foreach (glob("{$dir}/{$folder}/*", GLOB_ONLYDIR) ?: [] as $sub) {
            rmdir($sub);
        }
        if (is_dir("{$dir}/{$folder}")) {
            rmdir("{$dir}/{$folder}");
        }
    }
    if (is_file("{$dir}/zz-data-single.php")) {
        unlink("{$dir}/zz-data-single.php");
    }
};
$sweep();
register_shutdown_function($sweep);

foreach ($folders as $folder) {
    mkdir("{$dir}/{$folder}", 0755, true);
}
mkdir("{$dir}/zz-data-german/lang", 0755, true);
mkdir("{$dir}/zz-data-deutsch/languages", 0755, true);
file_put_contents("{$dir}/zz-data-full/zz-data-full.php", "<?php\n/**\n * Plugin Name: ZZ Data <em>Full</em> & \"Quotes\" <script>bad()</script>\n * Plugin URI: https://example.com/full?a=1&b=2\n * Version: 1.2.3\n * Description: Does things -- with <strong>markup</strong>, <a href=\"https://example.com/x\" title=\"T\" onclick=\"bad()\">a link</a>, <img src=x>, <abbr title=\"A\">abbr</abbr>, <acronym title=\"B\">acr</acronym>, <code>code</code>, <em>em</em>, <b>b</b>, <i>i</i>, <span class=\"s\">span</span>, <a href=\"javascript:bad()\">js</a> and \"quotes\"...\n * Author: Jane <b>Doe</b> <em>Esq</em>\n * Author URI: https://example.com/jane?x=1&y=2\n * Text Domain: zz-data-td\n * Domain Path: /lang\n * Network: true\n * Requires at least: 6.0\n * Requires PHP: 7.4\n * Update URI: https://example.com/updates\n * Requires Plugins: hello-dolly\n */\n");
file_put_contents("{$dir}/zz-data-plain/zz-data-plain.php", "<?php\n/**\n * Plugin Name: ZZ Data Plain\n * Description: Nothing else.\n */\n");
file_put_contents("{$dir}/zz-data-dotted/zz-data-dotted.php", "<?php\n/**\n * Plugin Name: ZZ Data Dotted\n * Author: Someone\n * Network: TRUE\n */\n");
file_put_contents("{$dir}/zz-data-single.php", "<?php\n/**\n * Plugin Name: ZZ Data Single\n * Author URI: https://example.com/nobody\n * Plugin URI: https://example.com/single\n */\n");
file_put_contents("{$dir}/zz-data-german/zz-data-german.php", "<?php\n/**\n * Plugin Name: ZZ Data German\n * Plugin URI: https://example.com/german\n * Version: 2.0\n * Description: A plugin in English.\n * Author: Hans\n * Author URI: https://example.com/hans\n * Text Domain: zz-data-german\n * Domain Path: /lang\n */\n");
file_put_contents("{$dir}/zz-data-german/lang/zz-data-german-de_DE.l10n.php", "<?php\nreturn ['domain' => 'zz-data-german', 'language' => 'de_DE', 'messages' => ['ZZ Data German' => 'ZZ Daten Deutsch', 'A plugin in English.' => 'Ein Plugin auf Deutsch.', 'Hans' => 'Hans Huber', 'https://example.com/german' => 'https://example.com/de/german', 'https://example.com/hans' => 'https://example.com/de/hans', '2.0' => '2,0', 'zz-data-german' => 'zz-daten', '/lang' => '/sprache']];\n");
file_put_contents("{$dir}/zz-data-deutsch/zz-data-deutsch.php", "<?php\n/**\n * Plugin Name: ZZ Data Deutsch\n * Plugin URI: https://example.com/deutsch\n * Version: 3.0\n * Description: Another plugin in English.\n * Author: Greta\n * Author URI: https://example.com/greta\n * Domain Path: /languages\n */\n");
file_put_contents("{$dir}/zz-data-deutsch/languages/zz-data-deutsch-de_DE.l10n.php", "<?php\nreturn ['domain' => 'zz-data-deutsch', 'language' => 'de_DE', 'messages' => ['ZZ Data Deutsch' => 'ZZ Daten Zwei', 'Another plugin in English.' => 'Noch ein Plugin auf Deutsch.', 'Greta' => 'Greta Graf', 'https://example.com/deutsch' => 'https://example.com/de/deutsch', 'https://example.com/greta' => 'https://example.com/de/greta', '3.0' => '3,0']];\n");
wp_clean_plugins_cache(false);

$heard = [];
add_action('load_textdomain', static function ($domain, $file) use (&$heard): void {
    if (str_starts_with((string) $domain, 'zz-data')) {
        $heard[] = [$domain, str_replace(WP_CONTENT_DIR, 'wp-content', (string) $file)];
    }
}, 10, 2);
$read = static function (string $label, callable $call, string $domain) use ($say, &$heard): void {
    $heard = [];
    $result = $call();
    $say($label, ['result' => $result, 'loads' => $heard, 'loaded' => is_textdomain_loaded($domain)]);
};
$full = "{$dir}/zz-data-full/zz-data-full.php";
$read('full, with markup and translation', static fn () => get_plugin_data($full), 'zz-data-td');
$read('full, again', static fn () => get_plugin_data($full), 'zz-data-td');
$read('full, translated without markup', static fn () => get_plugin_data($full, false, true), 'zz-data-td');
$read('full, markup without translation', static fn () => get_plugin_data($full, true, false), 'zz-data-td');
$read('full, neither', static fn () => get_plugin_data($full, false, false), 'zz-data-td');
$read('plain, a folder plugin naming no text domain', static fn () => get_plugin_data("{$dir}/zz-data-plain/zz-data-plain.php"), 'zz-data-plain');
$read('dotted, an author with no address and Network in capitals', static fn () => get_plugin_data("{$dir}/zz-data-dotted/zz-data-dotted.php"), 'zz-data-dotted');
$read('single, a file plugin with addresses but no author', static fn () => get_plugin_data("{$dir}/zz-data-single.php"), 'zz-data-single');
$german = static fn (): string => 'de_DE';
add_filter('locale', $german);
$read('german, in German', static fn () => get_plugin_data("{$dir}/zz-data-german/zz-data-german.php"), 'zz-data-german');
$read('german, in German, again', static fn () => get_plugin_data("{$dir}/zz-data-german/zz-data-german.php"), 'zz-data-german');
$read('german, in German, without markup', static fn () => get_plugin_data("{$dir}/zz-data-german/zz-data-german.php", false, true), 'zz-data-german');
unload_textdomain('zz-data-german');
$read('german, untranslated', static fn () => get_plugin_data("{$dir}/zz-data-german/zz-data-german.php", true, false), 'zz-data-german');
remove_filter('locale', $german);
unload_textdomain('zz-data-german');
unload_textdomain('zz-data-td');

$mine = static fn (array $plugins): array => array_filter($plugins, static fn ($file): bool => str_starts_with((string) $file, 'zz-data'), ARRAY_FILTER_USE_KEY);
$read('get_plugins, ours', static fn () => $mine(get_plugins()), 'zz-data-td');

// The REST plugin item, as an administrator.
wp_set_current_user(1);
foreach (['zz-data-full/zz-data-full', 'zz-data-plain/zz-data-plain', 'zz-data-single'] as $plugin) {
    $read("REST item {$plugin}", static function () use ($plugin) {
        $response = rest_do_request(new WP_REST_Request('GET', "/wp/v2/plugins/{$plugin}"));
        $data = $response->get_data();
        unset($data['_links']);
        return ['status' => $response->get_status(), 'data' => $data];
    }, 'zz-data-td');
}
add_filter('locale', $german);
unload_textdomain('zz-data-german');
$read('REST item zz-data-german, in German, its domain unloaded before', static function () {
    $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/plugins/zz-data-german/zz-data-german'));
    $data = $response->get_data();
    unset($data['_links']);
    return ['status' => $response->get_status(), 'data' => $data];
}, 'zz-data-german');
$read('REST item zz-data-deutsch, in German, a folder plugin naming no text domain', static function () {
    $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/plugins/zz-data-deutsch/zz-data-deutsch'));
    $data = $response->get_data();
    unset($data['_links']);
    return ['status' => $response->get_status(), 'data' => $data];
}, 'zz-data-deutsch');
$read('REST item zz-data-deutsch, again', static function () {
    $response = rest_do_request(new WP_REST_Request('GET', '/wp/v2/plugins/zz-data-deutsch/zz-data-deutsch'));
    return $response->get_data()['name'];
}, 'zz-data-deutsch');
unload_textdomain('zz-data-deutsch');
remove_filter('locale', $german);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
