<?php
/**
 * The theme class (probe wp-theme): WP_Theme for the installed themes and a
 * missing one. display('Tags') is left out: the reference names feature tags only
 * where wp-admin's feature list is loaded (WP-CLI loads it), which the rest-themes
 * probe covers. Its headers read raw, translated and marked up, the legacy
 * properties and array keys, errors and parents, its directories, URIs and
 * files, its templates (post and page, from theme.json for a block theme),
 * whether it is allowed, its text domain, the template folders, and the
 * static helpers. Paths are masked to the theme root and the host. Same
 * protocol as api-probe.php; it writes nothing.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$roots = [get_theme_root(), realpath(get_theme_root()) ?: get_theme_root()];
$mask = static function ($value) use (&$mask, $roots) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if ($value instanceof WP_Error) {
        return ['error', $value->get_error_codes(), $value->get_error_messages()];
    }
    if ($value instanceof WP_Theme) {
        return ['theme', $value->get_stylesheet()];
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace($roots, '{root}', $value);
    return (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value);
};

$five = new WP_Theme('twentytwentyfive', get_theme_root());
$four = wp_get_theme('twentytwentyfour');
$site = wp_get_theme('twentytwentythree');
$missing = new WP_Theme('zz-no-such-theme', get_theme_root());
$themes = ['twentytwentyfive' => $five, 'twentytwentyfour' => $four, 'twentytwentythree' => $site, 'missing' => $missing];

$headers = [];
foreach ($themes as $label => $theme) {
    $headers[$label] = [
        (string) $theme,
        $theme->exists(),
        $mask($theme->errors()),
        $mask($theme->parent()),
        array_map([$theme, 'get'], ['Name', 'ThemeURI', 'Description', 'Author', 'AuthorURI', 'Version', 'Template', 'Status', 'Tags', 'TextDomain', 'DomainPath', 'RequiresWP', 'RequiresPHP', 'UpdateURI', 'nonsense']),
        $theme->display('Name'),
        $theme->display('Author'),
        $theme->display('Author', false),
        $theme->display('Description', true, false),
        $theme->display('ThemeURI'),
        $theme->display('nonsense'),
    ];
}
$say('headers', $headers);

$legacy = [];
foreach (['name', 'title', 'version', 'parent_theme', 'template', 'stylesheet', 'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri', 'template_dir', 'stylesheet_dir', 'nonsense'] as $property) {
    $legacy[$property] = [isset($five->$property), $mask($five->$property)];
}
$keys = [];
foreach (['Name', 'Title', 'Version', 'Parent Theme', 'Template Dir', 'Stylesheet Dir', 'Template', 'Stylesheet', 'Screenshot', 'Description', 'Author', 'Author Name', 'Author URI', 'Tags', 'Theme Root', 'Theme Root URI', 'Status', 'Template Files', 'Stylesheet Files', 'nonsense'] as $key) {
    $keys[$key] = [isset($five[$key]), is_array($five[$key] ?? null) ? count($five[$key]) : $mask($five[$key] ?? null)];
}
$five['Name'] = 'changed';
unset($five['Version']);
$say('legacy properties and keys', [$legacy, $keys, $five['Name'], $five['Version']]);

$places = [];
foreach ($themes as $label => $theme) {
    $places[$label] = $mask([
        $theme->get_stylesheet(), $theme->get_template(),
        $theme->get_stylesheet_directory(), $theme->get_template_directory(),
        $theme->get_stylesheet_directory_uri(), $theme->get_template_directory_uri(),
        $theme->get_theme_root(), $theme->get_theme_root_uri(),
        $theme->get_screenshot(), $theme->get_screenshot('relative'),
        $theme->get_file_path(), $theme->get_file_path('style.css'), $theme->get_file_path('/theme.json'),
        $theme->is_block_theme(),
        $theme->get_block_template_folders(),
    ]);
}
$say('places', $places);

$files = [];
foreach (['twentytwentyfive' => $five, 'missing' => $missing] as $label => $theme) {
    $php = $theme->get_files('php', 1);
    $json = $theme->get_files('json', -1);
    $any = $theme->get_files(null, 0, true);
    ksort($php);
    ksort($json);
    ksort($any);
    $files[$label] = $mask([$php, array_keys($json), array_keys($any), $theme->get_files(['php', 'html'], 0)]);
}
$say('files', $files);

$say('templates', [
    $five->get_post_templates(),
    $five->get_page_templates(),
    $five->get_page_templates(null, 'post'),
    $five->get_page_templates(get_post(1)),
    $four->get_page_templates(),
    $site->get_post_templates(),
    $missing->get_page_templates(),
]);

$say('allowed and text domain', [
    $five->is_allowed(), $five->is_allowed('site'), $five->is_allowed('network'), $five->is_allowed('both'),
    $missing->is_allowed(),
    WP_Theme::get_allowed(), WP_Theme::get_allowed('site'),
    $five->load_textdomain(), $missing->load_textdomain(),
]);

$patterns = $five->get_block_patterns();
$say('statics and caches', [
    WP_Theme::get_allowed_on_network(),
    WP_Theme::get_allowed_on_site(),
    WP_Theme::get_allowed_on_site(1),
    WP_Theme::get_core_default_theme() instanceof WP_Theme ? WP_Theme::get_core_default_theme()->get_stylesheet() : WP_Theme::get_core_default_theme(),
    is_array($patterns), count($patterns) > 10, array_slice(array_keys($patterns), 0, 3), reset($patterns), $patterns['hidden-404.php'] ?? null, $patterns['header.php'] ?? null, $patterns['template-home-text-blog.php'] ?? null,
    $five->delete_pattern_cache(),
    $five->cache_delete(),
    (static function () use ($four) {
        $copy = unserialize(serialize($four));
        return [get_class($copy), $copy->get_stylesheet(), $copy->get('Name'), $copy->exists()];
    })(),
    $missing->get_block_patterns(),
]);

$sorted = [$four, $site, $five];
WP_Theme::sort_by_name($sorted);
$say('sorted by name', array_map(static fn ($t) => $t->get_stylesheet(), $sorted));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
