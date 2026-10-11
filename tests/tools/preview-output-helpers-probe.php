<?php
/**
 * Theme previews and two small output helpers (probe
 * preview-output-helpers): the theme a preview names for nobody, a
 * subscriber and an administrator (a theme that is there, one that is
 * not, none), through the stylesheet and template filters too; the hooks a
 * preview request sets up; the middleware script a preview adds; the
 * activation nonce script; the block template skip link while a template
 * renders, and asked again; and the deprecated SVG filters printer. $_GET, scripts, styles and filters are put back; the
 * probe's user is removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['users' => []];
$getBefore = $_GET;
register_shutdown_function(static function () use (&$made, $getBefore): void {
    $_GET = $getBefore;
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
add_filter('deprecated_function_trigger_error', '__return_false');
$deprecated = [];
add_action('deprecated_function_run', static function ($function, $replacement, $version) use (&$deprecated): void {
    $deprecated[] = [$function, $replacement, $version];
}, 10, 3);
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static fn ($value) => is_string($value) ? (string) preg_replace(['/ver=[0-9a-z.\-]+/i', '/"[0-9a-f]{10}"/'], ['ver={ver}', '"{nonce}"'], str_replace([$host, 'http://{host}'], ['{host}', 'https://{host}'], $value)) : $value;
$output = static function (Closure $run) use ($mask) {
    ob_start();
    try {
        $returned = $run();
    } catch (Throwable $e) {
        ob_end_clean();
        return ['threw' => get_class($e)];
    }
    return ['returned' => $mask($returned), 'printed' => $mask((string) ob_get_clean())];
};
$subscriber = wp_insert_user(['user_login' => 'zzpreview', 'user_email' => 'zzpreview@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
if (!is_int($subscriber) || $subscriber < 2) {
    fwrite(STDERR, "preview-output-helpers: the subscriber could not be made\n");
    exit(1);
}
$made['users'][] = $subscriber;
$themes = array_keys(wp_get_themes());
sort($themes);
$other = current(array_diff($themes, [get_stylesheet()])) ?: get_stylesheet();
$say('the themes to preview', ['active' => get_stylesheet(), 'another' => $other]);

$previews = [];
foreach ([0 => 'nobody', $subscriber => 'a subscriber', 1 => 'an administrator'] as $user => $who) {
    wp_set_current_user($user);
    $row = [];
    foreach (['none' => null, 'another theme' => $other, 'a missing theme' => 'zz-no-such-theme', 'an empty name' => '', 'with markup' => '<b>' . $other . '</b>'] as $label => $value) {
        $_GET = $getBefore;
        if ($value !== null) {
            $_GET['wp_theme_preview'] = $value;
        }
        $row[$label] = [wp_get_theme_preview_path(), wp_get_theme_preview_path('zz-current')];
    }
    $previews[$who] = $row;
}
$say('the theme a preview names', $previews);

wp_set_current_user(1);
$_GET = $getBefore;
$_GET['wp_theme_preview'] = $other;
$filters = static fn () => ['stylesheet' => has_filter('stylesheet', 'wp_get_theme_preview_path'), 'template' => has_filter('template', 'wp_get_theme_preview_path'), 'init' => has_action('init', 'wp_attach_theme_preview_middleware'), 'admin_head' => has_action('admin_head', 'wp_block_theme_activate_nonce')];
$hooks = ['before' => $filters()];
wp_initialize_theme_preview_hooks();
$hooks['after'] = $filters();
$hooks['through the filters'] = [apply_filters('stylesheet', 'zz-current'), apply_filters('template', 'zz-current')];
remove_filter('stylesheet', 'wp_get_theme_preview_path');
remove_filter('template', 'wp_get_theme_preview_path');
remove_action('init', 'wp_attach_theme_preview_middleware');
remove_action('admin_head', 'wp_block_theme_activate_nonce');
$_GET = $getBefore;
wp_initialize_theme_preview_hooks();
$hooks['without a preview'] = $filters();
$say('the hooks a preview sets up', $hooks);

$_GET['wp_theme_preview'] = $other;
$afterBefore = wp_scripts()->get_data('wp-api-fetch', 'after');
wp_attach_theme_preview_middleware();
$after = wp_scripts()->get_data('wp-api-fetch', 'after');
$say('the preview middleware', [$mask(is_array($after) ? array_values(array_diff($after, (array) $afterBefore)) : $after)]);
wp_scripts()->add_data('wp-api-fetch', 'after', $afterBefore);
$say('the activation nonce script', $output(static fn () => wp_block_theme_activate_nonce()));
$_GET = $getBefore;

$skipLink = static fn () => $output(static function () {
    $styles = wp_styles();
    $scripts = wp_scripts();
    $queued = [$styles->queue, $scripts->queue];
    wp_enqueue_block_template_skip_link();
    $handles = static fn ($registry, $before) => array_values(array_diff($registry->queue, $before));
    $out = [];
    // What is queued (the default handles' own sources are the asset registry's business).
    foreach ($handles($styles, $queued[0]) as $h) {
        $out[] = 'style ' . $h;
    }
    foreach ($handles($scripts, $queued[1]) as $h) {
        $out[] = 'script ' . $h;
    }
    return $out;
});
global $_wp_current_template_content;
$templateBefore = $_wp_current_template_content;
// The first call in a request decides: the style is enqueued while a template renders; later calls do nothing.
$_wp_current_template_content = '<!-- wp:group {"tagName":"main"} --><main class="wp-block-group"><p>Zz</p></main><!-- /wp:group -->';
$skip = ['while a template renders' => $skipLink()];
wp_dequeue_style('wp-block-template-skip-link');
$skip['asked again'] = $skipLink();
$_wp_current_template_content = $templateBefore;
$say('the block template skip link', $skip);
$deprecated = [];
$say('the SVG filters, deprecated', [$output(static fn () => wp_global_styles_render_svg_filters()), $deprecated]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
