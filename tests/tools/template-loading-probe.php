<?php
/**
 * Template loading helpers (probe template-loading): whether the template
 * is buffered for enhancement, the buffer started and finalized through
 * its filter and actions, a file required when the theme supports a
 * feature, a theme's WordPress and PHP requirements checked, and the
 * preview script a post's preview prints. Scratch themes and files are
 * removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$root = get_theme_root();
$scratch = [];
register_shutdown_function(static function () use (&$scratch): void {
    foreach (array_reverse($scratch) as $path) {
        is_dir($path) ? @rmdir($path) : @unlink($path);
    }
});
$heard = [];
add_action('all', static function (...$args) use (&$heard): void {
    $hook = current_filter();
    if (str_contains($hook, 'template_enhancement')) {
        $heard[] = [$hook, array_map(static fn ($v) => is_string($v) || is_bool($v) ? $v : gettype($v), $args)];
    }
});

$say('the hooks', [has_action('wp_before_include_template', 'wp_start_template_enhancement_output_buffer'), has_action('wp_head', 'wp_post_preview_js')]);

$upper = static fn ($output) => strtoupper((string) $output);
$say('buffered for enhancement', [
    wp_should_output_buffer_template_for_enhancement(),
    (static function () use (&$heard) {
        $heard = [];
        $level = ob_get_level();
        $out = [wp_start_template_enhancement_output_buffer(), ob_get_level() - $level, $heard];
        return $out;
    })(),
    (static function () use ($upper) {
        add_filter('wp_template_enhancement_output_buffer', $upper);
        $out = [wp_should_output_buffer_template_for_enhancement()];
        add_filter('wp_should_output_buffer_template_for_enhancement', '__return_false');
        $out[] = wp_should_output_buffer_template_for_enhancement();
        remove_filter('wp_should_output_buffer_template_for_enhancement', '__return_false');
        return $out;
    })(),
    (static function () use (&$heard) {
        $heard = [];
        ob_start();
        $level = ob_get_level();
        $started = wp_start_template_enhancement_output_buffer();
        $out = [$started, ob_get_level() - $level, ob_list_handlers()[ob_get_level() - 1] ?? null];
        echo 'inner <b>page</b>';
        ob_end_flush();
        $out[] = ob_get_clean();
        $out[] = $heard;
        return $out;
    })(),
]);

$say('the buffer finalized', array_map(static function (int $phase) use (&$heard) {
    $heard = [];
    return [wp_finalize_template_enhancement_output_buffer('abc <i>x</i>', $phase), $heard];
}, [PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FINAL, PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_WRITE, PHP_OUTPUT_HANDLER_FLUSH, PHP_OUTPUT_HANDLER_CLEAN, PHP_OUTPUT_HANDLER_CLEAN | PHP_OUTPUT_HANDLER_FINAL, PHP_OUTPUT_HANDLER_FINAL]));
remove_filter('wp_template_enhancement_output_buffer', $upper);
$say('finalized with no filter', [wp_finalize_template_enhancement_output_buffer('plain', PHP_OUTPUT_HANDLER_FINAL), wp_finalize_template_enhancement_output_buffer('', PHP_OUTPUT_HANDLER_FINAL)]);

$file = (string) tempnam(sys_get_temp_dir(), 'zzreq');
$scratch[] = $file;
file_put_contents($file, '<?php $GLOBALS["zz_required"] = ($GLOBALS["zz_required"] ?? 0) + 1; return "ret";');
$say('required where supported', [
    [require_if_theme_supports('zz-feature', $file), $GLOBALS['zz_required'] ?? 0],
    (static function () use ($file) {
        add_theme_support('zz-feature');
        return [require_if_theme_supports('zz-feature', $file), $GLOBALS['zz_required'] ?? 0, require_if_theme_supports('zz-feature', $file), $GLOBALS['zz_required'] ?? 0];
    })(),
]);

foreach (['zz-req-wp' => "Requires at least: 99.0\n", 'zz-req-php' => "Requires PHP: 99.0\n", 'zz-req-both' => "Requires at least: 99.0\nRequires PHP: 99.0\n", 'zz-req-ok' => "Requires at least: 5.0\nRequires PHP: 7.0\n", 'zz-req-markup' => "Requires PHP: 99.0\n"] as $dir => $headers) {
    $name = $dir === 'zz-req-markup' ? 'Zz <em>Marked</em> & Co' : ucwords(str_replace('-', ' ', $dir));
    @mkdir("{$root}/{$dir}");
    $scratch[] = "{$root}/{$dir}";
    file_put_contents("{$root}/{$dir}/style.css", "/*\nTheme Name: {$name}\n{$headers}*/\n");
    file_put_contents("{$root}/{$dir}/index.php", "<?php\n");
    array_push($scratch, "{$root}/{$dir}/style.css", "{$root}/{$dir}/index.php");
}
wp_clean_themes_cache();
$say('a theme\'s requirements', array_map(static function (string $stylesheet) {
    $result = validate_theme_requirements($stylesheet);
    return is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message(), $result->get_error_data()] : $result;
}, ['twentytwentyfive', 'zz-req-wp', 'zz-req-php', 'zz-req-both', 'zz-req-ok', 'zz-req-markup', 'zz-never-theme']));
foreach (array_reverse($scratch) as $path) {
    is_dir($path) ? @rmdir($path) : @unlink($path);
}
$scratch = [];
wp_clean_themes_cache();

$say('the preview script', (static function () {
    global $wp_query, $post;
    $saved = [$wp_query->is_preview, $post];
    $printed = static function () {
        ob_start();
        wp_post_preview_js();
        return (string) ob_get_clean();
    };
    $wp_query->is_preview = false;
    $post = get_post((int) get_posts(['numberposts' => 1, 'fields' => 'ids'])[0]);
    $out = [$printed()];
    $wp_query->is_preview = true;
    $out[] = str_replace((string) $post->ID, '{id}', $printed());
    $post = null;
    $out[] = $printed();
    [$wp_query->is_preview, $post] = $saved;
    return $out;
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
