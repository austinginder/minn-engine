<?php
/**
 * robots.txt (probe robots): do_robots() printing it for a public site and
 * for one that asks search engines to stay away, the hooks it fires on the
 * way (do_robotstxt, robots_txt with whether the site is public), and the
 * text a robots_txt filter puts in its place. The site's address is masked;
 * nothing is written.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
$host = static fn (string $text) => (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{home}', $text);
$robots = static function () use ($host): array {
    $hooks = [];
    $listen = static function (...$args) use (&$hooks) {
        $hook = current_filter();
        if (in_array($hook, ['do_robotstxt', 'robots_txt'], true)) {
            $hooks[] = [$hook, ...array_map(static fn ($value) => is_string($value) ? 'text' : $value, array_slice($args, 2))];
        }
        return $args[1] ?? null;
    };
    add_filter('all', $listen);
    ob_start();
    $returned = do_robots();
    $printed = (string) ob_get_clean();
    remove_filter('all', $listen);
    return ['returned' => $returned, 'printed' => $host($printed), 'hooks' => $hooks];
};
$public = static fn () => '1';
add_filter('pre_option_blog_public', $public);
$say('a public site', $robots());
remove_filter('pre_option_blog_public', $public);
$hidden = static fn () => '0';
add_filter('pre_option_blog_public', $hidden);
$say('a site hidden from search engines', $robots());
remove_filter('pre_option_blog_public', $hidden);
$replace = static fn ($output, $isPublic) => "User-agent: zz\nDisallow: /zz/\n# public: " . var_export($isPublic, true) . "\n";
add_filter('robots_txt', $replace, 999, 2);
add_filter('pre_option_blog_public', $public);
$say('a robots_txt filter\'s own text', $robots());
remove_filter('pre_option_blog_public', $public);
remove_filter('robots_txt', $replace, 999);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
