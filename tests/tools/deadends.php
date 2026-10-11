<?php

declare(strict_types=1);

/**
 * Sorts the reference's PHP API into the dead ends the engine answers with
 * inert stubs rather than behaviour: wp-admin (its screens and includes,
 * and the admin pages built into wp-includes/build/pages), the block and
 * site editors, the Customizer, and XML-RPC (with pingback and trackback
 * sending), whatever contracts/api/omitted.json leaves behind on purpose, and
 * what contracts/api/different.json answers Minn's own way (real code, area
 * 'different': accounted for, out of the proof)
 * (each with its reason). Everything else is meant to behave like the
 * reference. Writes public/minn/data/deadend-symbols.json, name => category
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
$omitted = json_decode((string) @file_get_contents("{$root}/contracts/api/omitted.json"), true) ?: [];
// Real code whose output is Minn's own by design: accounted for, out of the proof (area 'different').
$different = json_decode((string) @file_get_contents("{$root}/contracts/api/different.json"), true) ?: [];

// Pingbacks, trackbacks and update pings ride on XML-RPC even where their functions live elsewhere.
const PINGS = ['pingback', 'do_all_pings', 'do_all_pingbacks', 'do_all_trackbacks', 'do_all_enclosures', 'generic_ping', 'weblog_ping', 'trackback', 'do_trackbacks', 'trackback_url_list', 'pingback_ping_source_uri', 'discover_pingback_server_uri', 'privacy_ping_filter', 'get_pung', 'get_to_ping', 'add_ping', 'wp_should_disable_pings_for_environment', 'wp_maybe_disable_outgoing_pings_for_environment', 'wp_maybe_disable_trackback_for_environment', 'wp_maybe_disable_xmlrpc_pingback_for_environment', '_publish_post_hook'];

// The editors' own REST controllers and loaders: what only the block or site editor asks for.
const EDITOR_FILES = '#wp-includes/(block-editor\.php|class-wp-block-editor-context\.php|rest-api/endpoints/class-wp-rest-(block-directory|pattern-directory|edit-site-export|global-styles(-revisions)?|template-autosaves|template-revisions|navigation-fallback|font-(collections|faces|families))-controller\.php)#';

// Functions that live in wp-includes but only ever serve the admin or the editors: deprecated ones, and
// media.php's uploader and editor helpers, hooked only on admin_init, the plupload settings and the
// editor screens' load-* actions (probe media-tags, 2026-10-08); functions.php's TinyMCE direction
// (tiny_mce_before_init), the Widgets menu (_admin_menu) and the update-PHP button (probe functions-utils).
const NAMED = ['_mce_set_direction' => 'editor', 'wp_widgets_add_menu' => 'admin', 'wp_direct_php_update_button' => 'admin', 'wp_set_client_side_media_processing_flag' => 'admin', 'wp_show_heic_upload_error' => 'admin', 'wp_set_up_cross_origin_isolation' => 'editor', 'wp_start_cross_origin_isolation_output_buffer' => 'editor', 'wpview_media_sandbox_styles' => 'editor', 'the_editor' => 'admin', 'wp_ajax_press_this_add_category' => 'admin', 'wp_ajax_press_this_save_post' => 'admin', 'remove_option_whitelist' => 'admin', 'wp_add_editor_classic_theme_styles' => 'editor', 'wp_add_iframed_editor_assets_html' => 'editor', 'wp_get_note_mentioned_user_ids' => 'editor', 'wp_notify_note_mentions' => 'editor', '_wp_kses_allow_note_mention_span' => 'editor', 'register_admin_color_schemes' => 'admin', 'wp_admin_css_uri' => 'admin', 'wp_user_settings' => 'admin', '_add_post_type_submenus' => 'admin', 'new_user_email_admin_notice' => 'admin', 'wp_register_font_library_page_routes' => 'editor', 'wp_register_font_library_wp_admin_page_routes' => 'admin', 'enqueue_editor_block_styles_assets' => 'editor', 'wp_default_packages' => 'editor', 'wp_default_packages_inline_scripts' => 'editor', 'wp_default_packages_scripts' => 'editor', 'wp_default_packages_vendor' => 'editor', 'wp_enqueue_command_palette_assets' => 'editor', 'wp_enqueue_editor_block_directory_assets' => 'editor', 'wp_enqueue_editor_format_library_assets' => 'editor', 'wp_localize_community_events' => 'admin', 'wp_enqueue_view_transitions_admin_css' => 'admin', 'wp_get_view_transitions_admin_css' => 'admin', 'wp_register_page_routes' => 'admin', 'wp_register_options_connectors_page_routes' => 'admin', 'wp_register_options_connectors_wp_admin_page_routes' => 'admin', 'wp_generate_block_templates_export_file' => 'editor', 'handle_legacy_widget_preview_iframe' => 'editor', 'wp_admin_css' => 'admin', 'wp_admin_css_color' => 'admin', 'wp_editor' => 'editor', 'wp_plupload_default_settings' => 'editor', 'wp_register_tinymce_scripts' => 'editor', 'wp_tinymce_inline_scripts' => 'editor', 'wp_enqueue_editor' => 'editor', 'wp_enqueue_media' => 'editor', 'wp_auth_check_load' => 'admin', '_wp_editors' => 'editor', 'wp_rest_site_health_controller' => 'admin', 'wp_maybe_auto_update' => 'admin', 'wp_schedule_update_checks' => 'admin', 'wp_delete_all_temp_backups' => 'admin', '_wp_delete_all_temp_backups' => 'admin'];

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
    $category = isset($omitted['functions'][$name]) ? 'omitted' : (isset($different['functions'][$name]) ? 'different' : category($name, (string) ($spec['file'] ?? '')));
    // Pinging is real behaviour the engine keeps (it reads the lists); only sending pings is a dead end.
    if ($category !== null && !in_array($name, ['get_pung', 'get_to_ping'], true)) {
        $out['functions'][$name] = $category;
    }
}
foreach ($classes as $name => $spec) {
    $category = isset($omitted['classes'][$name]) ? 'omitted' : (isset($different['classes'][$name]) ? 'different' : category($name, (string) ($spec['file'] ?? '')));
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
