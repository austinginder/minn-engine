<?php
/**
 * Behaviour probe for the symbols the catalogue scan named as the top of the
 * plugin work queue: term hierarchy helpers, the post and meta helpers, the
 * filesystem copy, the update lists, the language directory, and the odds and
 * ends plugins reach for. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
if (defined('ABSPATH')) {
    foreach (['file', 'plugin', 'theme', 'update', 'misc', 'post', 'translation-install'] as $inc) {
        if (is_file(ABSPATH . 'wp-admin/includes/' . $inc . '.php')) {
            require_once ABSPATH . 'wp-admin/includes/' . $inc . '.php';
        }
    }
}
$home = home_url();
$bare = (string) preg_replace('#^https?://#', '', $home);
// Nonces tick with the clock, so they never belong in a pinned row.
$rel = static fn ($v) => is_string($v)
    ? (string) preg_replace('/_wpnonce=[a-f0-9]+/', '_wpnonce={nonce}', str_replace(['https://' . $bare, 'http://' . $bare, rtrim(ABSPATH, '/')], ['{home}', '{home}', '{abspath}'], $v))
    : $v;
$deep = static function ($v) use (&$deep, $rel) {
    return is_array($v) ? array_map($deep, $v) : $rel($v);
};
wp_set_current_user(1);

// --- Term hierarchy. A three-deep chain, swept at both ends.
foreach (['minn-probe-root', 'minn-probe-mid', 'minn-probe-leaf'] as $slug) {
    $stale = get_term_by('slug', $slug, 'category');
    if ($stale) {
        wp_delete_term($stale->term_id, 'category');
    }
}
$root = wp_insert_term('Minn Probe Root', 'category', ['slug' => 'minn-probe-root']);
$mid = wp_insert_term('Minn Probe Mid', 'category', ['slug' => 'minn-probe-mid', 'parent' => $root['term_id']]);
$leaf = wp_insert_term('Minn Probe Leaf', 'category', ['slug' => 'minn-probe-leaf', 'parent' => $mid['term_id']]);
$say('get_category_parents', $deep([
    get_category_parents($leaf['term_id']),
    get_category_parents($leaf['term_id'], true),
    get_category_parents($leaf['term_id'], false, ' &raquo; '),
    get_category_parents($leaf['term_id'], true, ' | ', true),
    get_category_parents($root['term_id']),
]));
$say('get_category_parents unknown', $deep(is_wp_error(get_category_parents(999999)) ? get_category_parents(999999)->get_error_code() : get_category_parents(999999)));
$say('term_is_ancestor_of', [
    term_is_ancestor_of($root['term_id'], $leaf['term_id'], 'category'),
    term_is_ancestor_of($mid['term_id'], $leaf['term_id'], 'category'),
    term_is_ancestor_of($leaf['term_id'], $root['term_id'], 'category'),
    term_is_ancestor_of($root['term_id'], $root['term_id'], 'category'),
]);
$hierarchy = _get_term_hierarchy('category');
$say('_get_term_hierarchy', [
    is_array($hierarchy),
    isset($hierarchy[$root['term_id']]) ? array_values($hierarchy[$root['term_id']]) === [$mid['term_id']] : 'root missing',
    isset($hierarchy[$mid['term_id']]) ? array_values($hierarchy[$mid['term_id']]) === [$leaf['term_id']] : 'mid missing',
    isset($hierarchy[$leaf['term_id']]),
]);
$say('_get_term_hierarchy flat', _get_term_hierarchy('post_tag'));
$say('tag_description', [tag_description($leaf['term_id']), tag_description(999999)]);
$say('_prime_term_caches', _prime_term_caches([$root['term_id'], $mid['term_id']]));
foreach ([$leaf, $mid, $root] as $term) {
    wp_delete_term($term['term_id'], 'category');
}

// --- Post helpers.
$say('get_extended', [
    get_extended('before<!--more-->after'),
    get_extended('before<!--more Read on-->after'),
    get_extended('no marker here'),
    get_extended("a<!--more-->b<!--noteaser-->c"),
]);
$say('get_page', [get_page(2)?->post_name, get_page(999999)?->post_name, get_page(2, ARRAY_A)['post_name'] ?? null]);
$datetime = get_post_datetime(1);
$say('get_post_datetime', [
    $datetime instanceof DateTimeImmutable,
    $datetime ? $datetime->format('Y-m-d H:i:s') : null,
    get_post_datetime(1, 'modified') instanceof DateTimeImmutable,
    get_post_datetime(1, 'date', 'gmt') instanceof DateTimeImmutable,
    get_post_datetime(999999),
]);
$say('post_exists', [
    post_exists('Hello world!') > 0,
    post_exists('A title no post carries'),
    post_exists('Hello world!', '', '', 'page'),
]);
$say('_truncate_post_slug', [
    _truncate_post_slug('a-very-long-slug-indeed', 10),
    _truncate_post_slug('short', 200),
    _truncate_post_slug('trailing-dash-here', 14),
]);
$say('wp_slash_strings_only', $deep([
    wp_slash_strings_only("a'b"),
    wp_slash_strings_only(['x' => "a'b", 'n' => 5, 'b' => true]),
    wp_slash_strings_only(5),
]));
$say('wp_trim_excerpt', $deep([wp_trim_excerpt('', get_post(8)), wp_trim_excerpt('given text')]));
$say('update_postmeta_cache', update_postmeta_cache([1, 2]) !== false);
$say('update_user_caches', update_user_caches(get_userdata(1)) === null || true);
$say('use_block_editor_for_post', [
    use_block_editor_for_post(1),
    use_block_editor_for_post(999999),
    use_block_editor_for_post(get_post(2)),
]);

// --- Privacy and dates.
$say('wp_privacy_anonymize_ip', [
    wp_privacy_anonymize_ip('192.168.10.44'),
    wp_privacy_anonymize_ip('2001:db8:85a3:8d3:1319:8a2e:370:7348'),
    wp_privacy_anonymize_ip('not an ip'),
    wp_privacy_anonymize_ip(''),
]);
$say('wp_maybe_decline_date', [
    wp_maybe_decline_date('1 January 2026', 'j F Y'),
    wp_maybe_decline_date('January 1, 2026', 'F j, Y'),
]);

// --- Filesystem. A scratch tree under the uploads dir, swept at both ends.
$uploads = wp_upload_dir();
$scratch = trailingslashit($uploads['basedir']) . 'minn-probe-fs';
$remove = static function (string $dir) use (&$remove): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
        $path = $dir . '/' . $entry;
        is_dir($path) ? $remove($path) : @unlink($path);
    }
    @rmdir($dir);
};
$remove($scratch);
mkdir($scratch . '/from/nested', 0777, true);
file_put_contents($scratch . '/from/one.txt', 'one');
file_put_contents($scratch . '/from/nested/two.txt', 'two');
WP_Filesystem();
$say('copy_dir', [
    copy_dir($scratch . '/from', $scratch . '/to') === true,
    is_file($scratch . '/to/one.txt'),
    file_get_contents($scratch . '/to/nested/two.txt'),
]);
$say('copy_dir skips', copy_dir($scratch . '/from', $scratch . '/skipped', ['nested']) === true && !is_dir($scratch . '/skipped/nested'));
$listed = list_files($scratch . '/from');
sort($listed);
$say('list_files', array_map(static fn ($p) => str_replace($scratch . '/', '', $p), $listed));
$say('list_files depth', array_map(static fn ($p) => str_replace($scratch . '/', '', $p), (array) list_files($scratch . '/from', 1)));
$say('win_is_writable', [win_is_writable($scratch . '/from/one.txt'), win_is_writable($scratch . '/nope.txt')]);
$remove($scratch);
$say('wp_get_mu_plugins', is_array(wp_get_mu_plugins()));

// --- Updates and languages. Only the shapes, never wordpress.org's numbers.
$plugin_updates = get_plugin_updates();
$say('get_plugin_updates', [
    is_array($plugin_updates),
    array_reduce($plugin_updates, static fn ($carry, $row) => $carry && isset($row->update) && isset($row->Name), true),
]);
$theme_updates = get_theme_updates();
$say('get_theme_updates', [
    is_array($theme_updates),
    array_reduce($theme_updates, static fn ($carry, $row) => $carry && isset($row->update), true),
]);
$bad = translations_api('nonsense', []);
$say('translations_api bad type', is_wp_error($bad) ? [$bad->get_error_code(), $bad->get_error_message()] : 'no error');
add_filter('translations_api', static fn () => ['translations' => [['language' => 'xx_XX']]]);
$say('translations_api short circuit', translations_api('core', ['version' => '1.0']));
remove_all_filters('translations_api');
$translations = [
    'fr_FR' => ['language' => 'fr_FR', 'native_name' => 'Fran\u{e7}ais', 'iso' => ['1' => 'fr'], 'strings' => []],
    'de_DE' => ['language' => 'de_DE', 'native_name' => 'Deutsch', 'iso' => ['1' => 'de'], 'strings' => []],
];
foreach ([
    'empty' => ['languages' => [], 'translations' => [], 'selected' => '', 'show_available_translations' => false],
    'installed' => ['languages' => ['fr_FR'], 'translations' => $translations, 'selected' => 'fr_FR', 'show_available_translations' => false],
    'available' => ['languages' => ['fr_FR'], 'translations' => $translations, 'selected' => '', 'show_available_translations' => true],
    'en selected' => ['languages' => ['fr_FR'], 'translations' => $translations, 'selected' => 'en_US', 'show_available_translations' => true],
] as $case => $extra) {
    ob_start();
    wp_dropdown_languages(['id' => 'minn-probe-langs', 'name' => 'lang'] + $extra);
    $say('wp_dropdown_languages ' . $case, $deep(ob_get_clean()));
}

// --- Odds and ends.
$say('get_archives_link', $deep([
    get_archives_link('https://example.test/a/', 'Text'),
    get_archives_link('https://example.test/a/', 'Text', 'link'),
    get_archives_link('https://example.test/a/', 'Text', 'option', ' ', '', true),
    get_archives_link('https://example.test/a/', 'Text', 'html', 'B', 'A'),
    get_archives_link('https://example.test/a/', 'Text', 'custom', 'B', 'A'),
]));
$say('wp_loginout', $deep([wp_loginout('', false), wp_loginout('https://example.test/r/', false)]));
$say('wp_register', $deep([wp_register('B', 'A', false)]));
ob_start();
wp_meta();
$say('wp_meta', ob_get_clean());
$say('wp_login_form', $deep(wp_login_form(['echo' => false, 'id_username' => 'u', 'id_password' => 'p', 'value_username' => 'name', 'redirect' => 'https://example.test/r/'])));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
