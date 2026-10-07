<?php
/**
 * Hooks plugins attach to that sit in small corners, as the reference
 * fires them (probe small-hooks): the head's feed links with their two
 * filters and arguments; wp_roles_init as the roles load; the TLS check a
 * request to the site itself asks (https_local_ssl_verify) next to the one
 * every HTTPS request asks; and the actions a template part fires as it
 * renders from the theme's file, from a saved part (the probe's own,
 * removed at the end), or not at all. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$heard = [];
$describe = static function ($value) {
    if (is_object($value)) {
        return $value instanceof WP_Post ? 'post:' . $value->post_type . ':' . $value->post_name : 'object:' . get_class($value);
    }
    if (is_string($value) && str_contains($value, '/parts/')) {
        // A theme file's path differs by install; its name does not.
        return 'parts/' . basename($value);
    }
    return is_string($value) && strlen($value) > 80 ? 'text:' . strlen($value) : $value;
};
add_action('all', static function (string $hook, ...$args) use (&$heard, $describe): void {
    if (preg_match('/^(wp_roles_init|https_local_ssl_verify|https_ssl_verify|render_block_core_template_part_(post|file|none)|feed_links_show_(posts|comments)_feed)$/', $hook)) {
        if ($hook === 'https_ssl_verify') {
            // The certificate bundle's path is the install's own; whether it verifies is what counts.
            $args[0] = $args[0] === false ? false : 'verifying';
        }
        $heard[] = [$hook, array_map(static fn ($arg) => is_string($arg) && str_contains($arg, '://') && isset($GLOBALS['zz_home']) ? str_replace($GLOBALS['zz_home'], '{home}', $arg) : $describe($arg), $args)];
    }
});
$call = static function (string $label, callable $fn) use ($say, &$heard, $describe): void {
    $heard = [];
    ob_start();
    $returned = $fn();
    $say($label, ['printed' => ob_get_clean(), 'returned' => $describe($returned), 'heard' => $heard]);
};

add_theme_support('automatic-feed-links');
$call('the feed links', static fn () => feed_links());
add_filter('feed_links_show_comments_feed', '__return_false');
$call('the feed links without the comments feed', static fn () => feed_links());
remove_filter('feed_links_show_comments_feed', '__return_false');
add_filter('feed_links_show_posts_feed', '__return_false');
$call('the feed links without the posts feed', static fn () => feed_links());
remove_filter('feed_links_show_posts_feed', '__return_false');
$call('the feed links in words of the caller\'s', static fn () => feed_links(['separator' => '|', 'feedtitle' => '%1$s %2$s Zz', 'comstitle' => 'Zz %1$s']));

$call('the roles loading', static fn () => new WP_Roles());
$call('the roles for a site', static fn () => wp_roles()->for_site());

$home = (string) wp_parse_url(home_url(), PHP_URL_HOST);
$GLOBALS['zz_home'] = $home;
$call('a request to the site\'s own host', static fn () => is_wp_error(wp_remote_get("https://{$home}:9/zz", ['timeout' => 2])));
$call('a request to localhost', static fn () => is_wp_error(wp_remote_get('https://localhost:9/zz', ['timeout' => 2])));
$call('a request to localhost without verification', static fn () => is_wp_error(wp_remote_get('https://localhost:9/zz', ['timeout' => 2, 'sslverify' => false])));

$theme = get_stylesheet();
$call('a template part from the theme', static fn () => '' !== render_block(['blockName' => 'core/template-part', 'attrs' => ['slug' => 'header', 'theme' => $theme], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$call('a template part that is not there', static fn () => render_block(['blockName' => 'core/template-part', 'attrs' => ['slug' => 'zz-no-part', 'theme' => $theme], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
$part = (int) wp_insert_post(['post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => 'zz-saved-part', 'post_title' => 'Zz Saved Part', 'post_content' => '<!-- wp:paragraph --><p>Zz saved part</p><!-- /wp:paragraph -->', 'tax_input' => ['wp_theme' => [$theme]]]);
wp_set_object_terms($part, $theme, 'wp_theme');
$call('a template part saved in the site', static fn () => '' !== render_block(['blockName' => 'core/template-part', 'attrs' => ['slug' => 'zz-saved-part', 'theme' => $theme], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]));
wp_delete_post($part, true);
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
