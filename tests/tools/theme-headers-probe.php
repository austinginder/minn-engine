<?php
/**
 * A theme's headers as WP_Theme reads and shows them (probe theme-headers):
 * read sanitized once a theme object (the name and status through kses,
 * the description and author too with links, the addresses as URLs, the
 * version, requirements and each tag without tags, the rest as written);
 * in the theme's language unless asked not to be (every header with a
 * value, through its text domain, which is loaded for each theme object
 * from its Domain Path, the languages folder when it names none: the
 * active theme's file named by the locale alone, any other's by domain and
 * locale), with markup the author linked to their address, the addresses
 * escaped and the description texturized; folders that are not whole
 * themes (no stylesheet, no template file, a missing parent, themselves as
 * their parent) with the reference's errors, existing all the same. The
 * themes the probe makes are removed at the end. Same protocol as
 * api-probe.php.
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

// Each header as get() reads it, sanitized once a theme object, from a theme whose headers carry markup.
$hostile = $root . '/zz-theme-get';
if (file_exists($hostile)) {
    fwrite(STDERR, "theme-headers: {$hostile} is already there\n");
    exit(1);
}
register_shutdown_function(static function () use ($hostile): void {
    @unlink($hostile . '/style.css');
    @rmdir($hostile);
});
mkdir($hostile);
file_put_contents($hostile . '/style.css', "/*\nTheme Name: Zz <em>Get</em> <script>x</script> <a href=\"https://e.com\" title=\"t\">n</a> <code>c</code> <abbr title=\"t\">ab</abbr> <strong>s</strong>\nTheme URI: javascript:alert(1)\nDescription: A \"d\" <b>bold</b> <img src=x onerror=y> <a href=\"https://e.com\" onclick=\"z\">l</a>\nAuthor: Zz <strong>A</strong> <i>i</i>\nAuthor URI: https://example.com/a b?x=<1>\nVersion: 1.0 <b>v</b> &amp; <em>e</em>\nTemplate: twentytwentyfive<b>\nStatus: <em>pub</em><a href=\"https://e.com\">lish</a> <strong>s</strong>\nTags: one, <b>two</b>, three\nText Domain: zz-<b>tg\nDomain Path: /lang<b>\nRequires at least: 6.0<b>\nRequires PHP: 7.4<b>\nUpdate URI: javascript:void(0)\n*/\n");
$sanitizing = [];
$watch = static function ($hook) use (&$sanitizing) {
    if (in_array($hook, ['pre_kses', 'clean_url'], true)) {
        $sanitizing[] = $hook;
    }
};
foreach (['Name', 'ThemeURI', 'Description', 'Author', 'AuthorURI', 'Version', 'Template', 'Status', 'Tags', 'TextDomain', 'DomainPath', 'RequiresWP', 'RequiresPHP', 'UpdateURI'] as $header) {
    $theme = new WP_Theme('zz-theme-get', $root);
    add_filter('all', $watch);
    $sanitizing = [];
    $read = $theme->get($header);
    $first = $sanitizing;
    $sanitizing = [];
    $theme->get($header);
    remove_filter('all', $watch);
    $say("get {$header}", ['read' => $read, 'heard' => $first, 'heard again' => $sanitizing]);
}
$theme = new WP_Theme('zz-theme-get', $root);
$say('the name as a property, by offset, shown plain', [$theme->name, $theme['Name'], $theme->display('Name', false, false)]);

// Folders that are not whole themes: each one's error.
$broken = [
    'zz-broken-empty' => [null, []],
    'zz-broken-no-index' => ["Theme Name: Zz No Index", []],
    'zz-broken-classic' => ["Theme Name: Zz Classic", ['index.php' => '<?php']],
    'zz-broken-block' => ["Theme Name: Zz Block", ['templates/index.html' => '']],
    'zz-broken-orphan' => ["Theme Name: Zz Orphan\nTemplate: zz-no-such-parent", []],
    'zz-broken-child' => ["Theme Name: Zz Child\nTemplate: twentytwentyfive", []],
    'zz-broken-self' => ["Theme Name: Zz Self\nTemplate: zz-broken-self", []],
    'zz-broken-nameless' => ["Description: no name", ['index.php' => '<?php']],
    'zz-broken' => [null, []],
];
foreach (array_keys($broken) as $slug) {
    if (file_exists("{$root}/{$slug}")) {
        fwrite(STDERR, "theme-headers: {$root}/{$slug} is already there\n");
        exit(1);
    }
}
register_shutdown_function(static function () use ($broken, $root): void {
    foreach ($broken as $slug => [$css, $files]) {
        foreach (array_keys($files) as $file) {
            @unlink("{$root}/{$slug}/{$file}");
        }
        @rmdir("{$root}/{$slug}/templates");
        @unlink("{$root}/{$slug}/style.css");
        @rmdir("{$root}/{$slug}");
    }
});
foreach ($broken as $slug => [$css, $files]) {
    mkdir("{$root}/{$slug}");
    if ($css !== null) {
        file_put_contents("{$root}/{$slug}/style.css", "/*\n{$css}\n*/\n");
    }
    foreach ($files as $file => $content) {
        if (!is_dir(dirname("{$root}/{$slug}/{$file}"))) {
            mkdir(dirname("{$root}/{$slug}/{$file}"));
        }
        file_put_contents("{$root}/{$slug}/{$file}", $content);
    }
}
foreach (array_keys($broken) as $slug) {
    $theme = new WP_Theme($slug, $root);
    $error = $theme->errors();
    $say("a folder: {$slug}", ['exists' => $theme->exists(), 'error' => $error ? [$error->get_error_code(), $error->get_error_message()] : false, 'name' => $theme->get('Name'), 'parent' => $theme->parent() ? $theme->parent()->get_stylesheet() : false]);
}
$missing = new WP_Theme('zz-no-such-theme', $root);
$say('no folder', ['exists' => $missing->exists(), 'error' => [$missing->errors()->get_error_code(), $missing->errors()->get_error_message()], 'name' => $missing->get('Name')]);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
