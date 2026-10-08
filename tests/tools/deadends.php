<?php

declare(strict_types=1);

/**
 * Sorts the reference's PHP API into the dead ends the engine answers with
 * inert stubs rather than behaviour: wp-admin (its screens and includes,
 * and the admin pages built into wp-includes/build/pages), the block and
 * site editors, the Customizer, and XML-RPC (with pingback and trackback
 * sending). Everything else is meant to behave like the reference. Writes public/minn/data/deadend-symbols.json, name => category
 * for every function and class of the inventory in a dead end, whether the
 * engine implements it or not; tests/tools/stub-symbols.php --deadends
 * stubs the ones it lacks.
 *
 *   php tests/tools/deadends.php            write the file and print the counts
 *   php tests/tools/deadends.php --check    exit 1 when the file on disk is stale
 */

$root = dirname(__DIR__, 2);
$functions = json_decode((string) file_get_contents("{$root}/contracts/api/functions.json"), true);
$classes = json_decode((string) file_get_contents("{$root}/contracts/api/classes.json"), true);

// Pingbacks, trackbacks and update pings ride on XML-RPC even where their functions live elsewhere.
const PINGS = ['pingback', 'do_all_pings', 'do_all_pingbacks', 'do_all_trackbacks', 'do_all_enclosures', 'generic_ping', 'weblog_ping', 'trackback', 'do_trackbacks', 'trackback_url_list', 'pingback_ping_source_uri', 'discover_pingback_server_uri', 'privacy_ping_filter', 'get_pung', 'get_to_ping', 'add_ping'];

// The editors' own REST controllers and loaders: what only the block or site editor asks for.
const EDITOR_FILES = '#wp-includes/(block-editor\.php|class-wp-block-editor-context\.php|rest-api/endpoints/class-wp-rest-(block-directory|pattern-directory|edit-site-export|global-styles(-revisions)?|template-autosaves|template-revisions|navigation-fallback|font-(collections|faces|families))-controller\.php)#';

// Deprecated functions that live in wp-includes but only ever served the admin or the editors.
const NAMED = ['the_editor' => 'admin', 'wp_ajax_press_this_add_category' => 'admin', 'wp_ajax_press_this_save_post' => 'admin', 'remove_option_whitelist' => 'admin', 'wp_add_editor_classic_theme_styles' => 'editor', 'wp_add_iframed_editor_assets_html' => 'editor'];

/** The dead end a symbol belongs to, or null when it should behave like the reference. */
function category(string $name, string $file): ?string
{
    $lower = strtolower($name);
    return match (true) {
        isset(NAMED[$lower]) => NAMED[$lower],
        str_starts_with($file, 'wp-admin/') || str_starts_with($file, 'wp-includes/build/pages/') => 'admin',
        str_contains($file, 'customize') || str_contains($lower, 'customize') => 'customizer',
        stripos($file, 'xmlrpc') !== false || stripos($file, 'IXR') !== false || str_starts_with($lower, 'xmlrpc_') || in_array($lower, PINGS, true) => 'xmlrpc',
        preg_match(EDITOR_FILES, $file) === 1 || str_contains($lower, 'block_editor') || str_contains($lower, 'site_editor') || str_starts_with($lower, '_load_remote_') => 'editor',
        default => null,
    };
}

$out = ['functions' => [], 'classes' => []];
foreach ($functions as $name => $spec) {
    $category = category($name, (string) ($spec['file'] ?? ''));
    // Pinging is real behaviour the engine keeps (it reads the lists); only sending pings is a dead end.
    if ($category !== null && !in_array($name, ['get_pung', 'get_to_ping'], true)) {
        $out['functions'][$name] = $category;
    }
}
foreach ($classes as $name => $spec) {
    $category = category($name, (string) ($spec['file'] ?? ''));
    if ($category !== null && !str_contains($name, '\\')) {
        $out['classes'][$name] = $category;
    }
}
ksort($out['functions']);
ksort($out['classes']);
$encoded = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$target = "{$root}/public/minn/data/deadend-symbols.json";
if (in_array('--check', $argv, true)) {
    exit((string) @file_get_contents($target) === $encoded ? 0 : 1);
}
file_put_contents($target, $encoded);
$counts = [];
foreach (['functions', 'classes'] as $kind) {
    foreach ($out[$kind] as $category) {
        $counts[$kind][$category] = ($counts[$kind][$category] ?? 0) + 1;
    }
}
echo json_encode($counts), "\n";
