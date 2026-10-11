<?php
/**
 * Small helpers left (probe misc-helpers): the sitemap providers and a
 * provider registered; a feed's self link for the address asked; whether a
 * user agent is a phone's; whether the site is on https and whether its
 * old http address should be replaced in content; a plugin file's real path
 * noted; script module translations set; and the font hooks that take a
 * family's faces and a face's file with them. The posts, file and providers
 * the probe makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}

$providers = static fn () => array_map(static fn ($provider) => get_class($provider), wp_get_sitemap_providers());
$before = $providers();
$provider = new class () extends WP_Sitemaps_Provider {
    public function __construct()
    {
        $this->name = 'zzmh';
        $this->object_type = 'zzmh';
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        return [];
    }

    public function get_max_num_pages($object_subtype = '')
    {
        return 0;
    }
};
$say('sitemap providers', [
    'before' => $before,
    'one added' => wp_register_sitemap_provider('zzmh', $provider),
    'the same name again' => wp_register_sitemap_provider('zzmh', $provider),
    'after' => array_map(static fn ($class) => str_contains($class, '@anonymous') ? 'anonymous' : $class, $providers()),
]);

$host = (string) parse_url(home_url(), PHP_URL_HOST);
$self = static function (string $uri) use ($host) {
    $_SERVER['REQUEST_URI'] = $uri;
    return str_replace($host, '{host}', get_self_link());
};
$say('self link', [
    'the feed' => $self('/feed/'),
    'a query' => $self('/category/x/feed/?paged=2&zz=a b'),
    'markup in it' => $self('/feed/?q=<b>"x"</b>'),
    'slashes' => $self('/feed/?q=a\\\'b'),
    'over https' => (static function () use ($self) {
        $_SERVER['HTTPS'] = 'on';
        $out = $self('/feed/atom/');
        unset($_SERVER['HTTPS']);
        return $out;
    })(),
    'filtered' => (static function () use ($self) {
        $filter = static fn ($link) => $link . '#zz';
        add_filter('self_link', $filter);
        $out = $self('/feed/rss2/');
        remove_filter('self_link', $filter);
        return $out;
    })(),
]);
// The tag's echo, filtered and escaped.
$printed = static function (string $uri, ?Closure $filter = null) use ($host) {
    $_SERVER['REQUEST_URI'] = $uri;
    if ($filter) {
        add_filter('self_link', $filter);
    }
    ob_start();
    self_link();
    $out = (string) ob_get_clean();
    if ($filter) {
        remove_filter('self_link', $filter);
    }
    return str_replace($host, '{host}', $out);
};
$say('self link printed', [
    'the feed' => $printed('/feed/'),
    'markup in it' => $printed('/feed/?q=<b>"x"</b>&a=1'),
    'over https' => (static function () use ($printed) {
        $_SERVER['HTTPS'] = 'on';
        $out = $printed('/feed/atom/');
        unset($_SERVER['HTTPS']);
        return $out;
    })(),
    'filtered' => $printed('/feed/rss2/', static fn ($link) => $link . '#zz'),
]);
$_SERVER['REQUEST_URI'] = '/';

$agents = [
    'an iPhone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    'an Android phone' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
    'an Android tablet' => 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    'a desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    'a Kindle' => 'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/528.5+) Version/4.0 Kindle/3.0',
    'Silk' => 'Mozilla/5.0 (Linux; Android 9; KFTRWI) AppleWebKit/537.36 (KHTML, like Gecko) Silk/120.0 like Chrome/120.0 Safari/537.36',
    'BlackBerry' => 'BlackBerry9700/5.0.0.351 Profile/MIDP-2.1 Configuration/CLDC-1.1',
    'Opera Mini' => 'Opera/9.80 (J2ME/MIDP; Opera Mini/9.80) Presto/2.5.25 Version/10.54',
    'Opera Mobi' => 'Opera/9.80 (Android 2.3.3; Linux; Opera Mobi/ADR-1111101157) Presto/2.9.201 Version/11.50',
    'an iPad' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    'none' => null,
    'empty' => '',
];
$mobile = [];
foreach ($agents as $label => $agent) {
    if ($agent === null) {
        unset($_SERVER['HTTP_USER_AGENT']);
    } else {
        $_SERVER['HTTP_USER_AGENT'] = $agent;
    }
    $mobile[$label] = wp_is_mobile();
}
$_SERVER['HTTP_USER_AGENT'] = $agents['a desktop'];
$filter = static fn () => true;
add_filter('wp_is_mobile', $filter);
$mobile['a desktop, filtered'] = wp_is_mobile();
remove_filter('wp_is_mobile', $filter);
unset($_SERVER['HTTP_USER_AGENT']);
$say('mobile', $mobile);

// The site's addresses given for each case through the options' pre filters, so nothing is stored.
$addresses = static function (string $home, string $site, $migration, Closure $run) {
    $filters = ['pre_option_home' => static fn () => $home, 'pre_option_siteurl' => static fn () => $site, 'pre_option_https_migration_required' => static fn () => $migration];
    foreach ($filters as $hook => $filter) {
        add_filter($hook, $filter);
    }
    $out = $run();
    foreach ($filters as $hook => $filter) {
        remove_filter($hook, $filter);
    }
    return $out;
};
$https = static fn () => ['using https' => wp_is_using_https(), 'replace the old address' => wp_should_replace_insecure_home_url()];
$say('https', [
    'both https, migrated' => $addresses('https://zz.example', 'https://zz.example', '1', $https),
    'both https, no migration' => $addresses('https://zz.example', 'https://zz.example', '0', $https),
    'home on http' => $addresses('http://zz.example', 'https://zz.example', '1', $https),
    'site on http' => $addresses('https://zz.example', 'http://zz.example', '1', $https),
    'different hosts' => $addresses('https://zz.example', 'https://wp.zz.example', '1', $https),
    'filtered off' => $addresses('https://zz.example', 'https://zz.example', '1', static function () use ($https) {
        add_filter('wp_should_replace_insecure_home_url', '__return_false');
        $out = $https();
        remove_filter('wp_should_replace_insecure_home_url', '__return_false');
        return $out;
    }),
]);

// What the map holds for the folders asked about (Minn Admin's may be noted already, when it is active).
global $wp_plugin_paths;
$real = (string) realpath(WP_PLUGIN_DIR . '/minn-admin');
$asked = array_map(static fn ($folder) => wp_normalize_path(WP_PLUGIN_DIR . $folder), ['/minn-admin', '/zz-none', '']);
$say('plugin real paths', [
    'a plugin folder that is a link' => is_link(WP_PLUGIN_DIR . '/minn-admin') ? wp_register_plugin_realpath(WP_PLUGIN_DIR . '/minn-admin/minn-admin.php') : 'not a link here',
    'a single-file plugin' => wp_register_plugin_realpath(WP_PLUGIN_DIR . '/hello.php'),
    'a missing file' => wp_register_plugin_realpath(WP_PLUGIN_DIR . '/zz-none/zz.php'),
    'noted' => json_decode(str_replace([wp_normalize_path(WP_PLUGIN_DIR), wp_normalize_path($real)], ['{plugins}', '{real}'], (string) json_encode(array_intersect_key((array) $wp_plugin_paths, array_flip($asked)), JSON_UNESCAPED_SLASHES)), true),
]);

wp_register_script_module('zz/mh', 'https://example.com/zz-mh.js');
$say('script module translations', [
    'a module' => wp_set_script_module_translations('zz/mh', 'zz-mh', '/zz/languages'),
    'one not registered' => wp_set_script_module_translations('zz/none'),
]);

// A font family with two faces, one with a file of its own in the fonts folder.
wp_set_current_user(1);
$family = wp_insert_post(['post_type' => 'wp_font_family', 'post_title' => 'ZZ mh family', 'post_name' => 'zz-mh-family', 'post_status' => 'publish', 'post_content' => '{}']);
$faces = [];
foreach (['zz-mh-a.woff2', ''] as $file) {
    $face = wp_insert_post(['post_type' => 'wp_font_face', 'post_parent' => $family, 'post_title' => 'ZZ mh face', 'post_status' => 'publish', 'post_content' => '{}']);
    if ($file !== '' && is_int($face)) {
        update_post_meta($face, '_wp_font_face_file', $file);
    }
    $faces[] = $face;
}
wp_set_current_user(0);
$fonts = wp_get_font_dir()['path'];
register_shutdown_function(static function () use ($family, $faces, $fonts): void {
    foreach ([...$faces, $family] as $id) {
        if (is_int($id) && $id > 1 && get_post($id)) {
            wp_delete_post($id, true);
        }
    }
    if (is_file($fonts . '/zz-mh-a.woff2')) {
        unlink($fonts . '/zz-mh-a.woff2');
    }
});
if (!is_int($family) || $family <= 1 || !is_int($faces[0]) || !is_int($faces[1])) {
    fwrite(STDERR, "no fixture\n");
    exit(1);
}
wp_mkdir_p($fonts);
file_put_contents($fonts . '/zz-mh-a.woff2', 'zz');
$children = static fn () => count(get_posts(['post_type' => 'wp_font_face', 'post_parent' => $family, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1]));
$say('font hooks', [
    'a face\'s file before' => is_file($fonts . '/zz-mh-a.woff2'),
    'a face, its file taken' => [_wp_before_delete_font_face($faces[0], get_post($faces[0])), is_file($fonts . '/zz-mh-a.woff2')],
    'a face without a file' => _wp_before_delete_font_face($faces[1], get_post($faces[1])),
    'another post type' => _wp_before_delete_font_face($family, get_post($family)),
    'a face given as a family' => [_wp_after_delete_font_family($faces[1], get_post($faces[1])), $children()],
    'faces before' => $children(),
    'a family, its faces taken' => [_wp_after_delete_font_family($family, get_post($family)), $children()],
]);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
