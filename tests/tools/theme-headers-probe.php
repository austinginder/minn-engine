<?php
/**
 * A theme's headers as WP_Theme::display shows them (probe theme-headers):
 * sanitized (the name, description and author through kses, the addresses
 * as URLs), in the theme's language unless asked not to be (every header
 * with a value, through its text domain, which is loaded for each theme
 * object from its Domain Path, the languages folder when it names none: the
 * active theme's file named by the locale alone, any other's by domain and
 * locale), with markup the author linked to their address, the addresses
 * escaped and the description texturized. A theme the probe makes (a child
 * of the active one, with a domain path of its own) is removed at the end.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$root = get_theme_root();
$dir = $root . '/zz-theme-headers';
if (file_exists($dir)) {
    fwrite(STDERR, "theme-headers: {$dir} is already there\n");
    exit(1);
}
register_shutdown_function(static function () use ($dir): void {
    @unlink($dir . '/style.css');
    @rmdir($dir);
});
mkdir($dir);
file_put_contents($dir . '/style.css', "/*\nTheme Name: Zz Theme <em>Headers</em>\nTemplate: twentytwentyfive\nUpdate URI: https://example.com/zz\nDomain Path: /lang\nText Domain: zz-theme-headers\nVersion: 1.0\nAuthor: Zz <strong>Author</strong>\nAuthor URI: https://example.com/author\nDescription: A \"quoted\" <script>x</script> test\n*/\n");

$heard = [];
$domains = ['twentytwentyfive', 'twentytwentythree', 'zz-theme-headers'];
add_filter('load_textdomain_mofile', static function ($mofile, $domain) use (&$heard, $root) {
    $heard[] = ['mofile', $domain, str_replace($root, '{themes}', (string) $mofile)];
    return $mofile;
}, 10, 2);
foreach ($domains as $domain) {
    add_filter("gettext_{$domain}", static function ($translation, $text) use (&$heard) {
        $heard[] = ['translated', $text];
        return $translation;
    }, 10, 2);
}
add_filter('pre_kses', static function ($content) use (&$heard) {
    $heard[] = ['kses', $content];
    return $content;
});

foreach (['twentytwentyfive' => ['Name', 'ThemeURI', 'Description', 'Author', 'AuthorURI', 'Version', 'Status', 'TextDomain', 'RequiresWP', 'Template', 'UpdateURI'], 'twentytwentythree' => ['Name'], 'zz-theme-headers' => ['Name', 'Description', 'Author', 'Template', 'UpdateURI', 'DomainPath', 'Version']] as $slug => $headers) {
    foreach ($headers as $header) {
        foreach ([[true, true], [false, true], [true, false]] as [$markup, $translate]) {
            $theme = new WP_Theme($slug, $root);
            $heard = [];
            $shown = $theme->display($header, $markup, $translate);
            $say("{$slug} {$header}" . ($markup ? '' : ', no markup') . ($translate ? '' : ', not translated'), [$shown, $heard]);
        }
    }
}

// The text domain is loaded once a theme object: a second header in the same object reads it as loaded.
$theme = new WP_Theme('twentytwentyfive', $root);
$heard = [];
$theme->display('Name');
$theme->display('Version');
$say('two headers of one theme object', $heard);
$heard = [];
$say('load_textdomain', [$theme->load_textdomain(), (new WP_Theme('zz-theme-headers', $root))->load_textdomain(), (new WP_Theme('zz-nothing-here', $root))->load_textdomain()]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
