<?php
/**
 * general-template.php's remaining tags (probe general-template-more): the
 * comment form's allowed-tags text and its required-field marks, the
 * tooltip, toggletip and tooltip helper markup, a post type archive's
 * description, the post date as XML and its weekday (once per day in a
 * loop), the external hosts the queued assets come from, and the preload
 * links a filter asks for. The probe's post, type and assets are removed.
 * Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$echoed = static function (Closure $call): string {
    ob_start();
    $call();
    return (string) ob_get_clean();
};

$say('allowed tags', allowed_tags());
$allowed = static fn () => ['b' => [], 'a' => ['href' => true, 'rel' => true], 'span' => ['class' => true, 'data-x' => true]];
global $allowedtags;
$kept = $allowedtags;
$allowedtags = $allowed();
$say('allowed tags, another list', allowed_tags());
$allowedtags = $kept;
$say('required fields', [wp_required_field_indicator(), wp_required_field_message()]);
$star = static fn ($indicator) => str_replace('*', '(required)', $indicator);
$message = static fn ($message) => '<p>' . $message . '</p>';
add_filter('wp_required_field_indicator', $star);
add_filter('wp_required_field_message', $message);
$say('required fields, filtered', [wp_required_field_indicator(), wp_required_field_message()]);
remove_filter('wp_required_field_indicator', $star);
remove_filter('wp_required_field_message', $message);

$say('tooltips', [
    wp_get_tooltip('Tip text'),
    wp_get_tooltip('Tip <b>bold</b> & "quoted"', ['icon' => 'dashicons dashicons-info', 'class' => 'extra one', 'id' => 'zz-tip']),
    wp_get_toggletip('Help text'),
    wp_get_toggletip('Help <i>it</i>', ['label' => 'More "info"', 'icon' => 'my-icon', 'class' => 'c', 'id' => 'zz-toggle']),
    wp_get_tooltip_helper('Helper text'),
    wp_get_tooltip_helper('Helper', ['icon' => 'x', 'class' => 'y', 'id' => 'zz-helper', 'label' => 'ignored?']),
    wp_get_tooltip(''),
    wp_get_tooltip('Unlisted', ['position' => 'top', 'label' => 'L']),
]);

register_post_type('zz_desc', ['public' => true, 'has_archive' => true, 'description' => 'About <b>zz</b> things.', 'label' => 'Zz']);
register_post_type('zz_nodesc', ['public' => true, 'has_archive' => true, 'label' => 'Zz none']);
$archive = static function (string $type): string {
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['post_type' => $type]);
    return (string) get_the_post_type_description();
};
$described = [$archive('zz_desc'), $archive('zz_nodesc')];
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'] = new WP_Query(['posts_per_page' => 1]);
$described[] = get_the_post_type_description();
$typeFilter = static fn ($description, $type) => $description . ' [' . $type->name . ']';
add_filter('get_the_post_type_description', $typeFilter, 10, 2);
$described[] = $archive('zz_desc');
remove_filter('get_the_post_type_description', $typeFilter, 10);
$say('post type descriptions', $described);
unregister_post_type('zz_desc');
unregister_post_type('zz_nodesc');

$first = (int) wp_insert_post(['post_title' => 'zz weekday a', 'post_status' => 'publish', 'post_date' => '1990-03-07 08:00:00', 'post_content' => 'x']);
$same = (int) wp_insert_post(['post_title' => 'zz weekday b', 'post_status' => 'publish', 'post_date' => '1990-03-07 20:00:00', 'post_content' => 'x']);
$next = (int) wp_insert_post(['post_title' => 'zz weekday c', 'post_status' => 'publish', 'post_date' => '1990-03-08 09:00:00', 'post_content' => 'x']);
array_push($made['posts'], $first, $same, $next);
$days = [];
global $previousweekday, $post;
$previousweekday = '';
foreach ([$first, $same, $next] as $id) {
    $post = get_post($id);
    setup_postdata($post);
    $days[] = [$echoed(static fn () => the_date_xml()), $echoed(static fn () => the_weekday()), $echoed(static fn () => the_weekday_date('<b>', '</b>'))];
}
$say('dates and weekdays', $days);
$weekday = static fn ($day) => strtoupper($day);
add_filter('the_weekday', $weekday);
add_filter('the_weekday_date', $weekday);
$previousweekday = '';
$say('weekdays, filtered', [$echoed(static fn () => the_weekday()), $echoed(static fn () => the_weekday_date('[', ']'))]);
remove_filter('the_weekday', $weekday);
remove_filter('the_weekday_date', $weekday);
wp_reset_postdata();

// The site's own host is left out by comparing against the server name a web request carries.
$_SERVER['SERVER_NAME'] = (string) wp_parse_url(home_url(), PHP_URL_HOST);
$say('asset hosts, none external', wp_dependencies_unique_hosts());
wp_enqueue_script('zz-cdn-a', 'https://cdn-a.example/a.js');
wp_enqueue_script('zz-cdn-a2', 'https://cdn-a.example/b.js');
wp_enqueue_style('zz-cdn-b', 'https://cdn-b.example/b.css');
wp_enqueue_style('zz-local', home_url('/local.css'));
wp_enqueue_script('zz-relative', '/relative.js');
wp_register_script('zz-registered-only', 'https://cdn-c.example/c.js');
wp_enqueue_script('zz-protocol', '//cdn-d.example/d.js');
$say('asset hosts', wp_dependencies_unique_hosts());
foreach (['zz-cdn-a', 'zz-cdn-a2', 'zz-relative', 'zz-protocol'] as $handle) {
    wp_dequeue_script($handle);
}
wp_dequeue_style('zz-cdn-b');
wp_dequeue_style('zz-local');

$say('preload links, none asked for', $echoed(static fn () => wp_preload_resources()));
$preload = static fn () => [
    ['href' => 'https://cdn.example/font.woff2', 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous'],
    ['href' => '/hero.jpg', 'as' => 'image', 'imagesrcset' => '/hero-640.jpg 640w, /hero.jpg 1280w', 'imagesizes' => '100vw', 'media' => '(min-width: 600px)'],
    ['href' => 'https://cdn.example/app.js', 'as' => 'script', 'fetchpriority' => 'high', 'bogus' => 'x'],
    ['as' => 'style'],
    ['href' => '/no-as.css'],
    'not an array',
    ['href' => 'https://cdn.example/app.js', 'as' => 'script'],
    ['imagesrcset' => '/only-srcset.jpg 1x', 'as' => 'image'],
    ['href' => '/a.js', 'as' => 'script', 'integrity' => 'sha384-x', 'referrerpolicy' => 'no-referrer', 'title' => 't', 'nonce' => 'n', 'id' => 'i'],
    ['href' => '/b.css', 'as' => 'style', 'crossorigin' => true],
    ['href' => '/d.css', 'AS' => 'style'],
    ['href' => "/e.css?x='y'&z=1", 'as' => 'style', 'media' => 'print and (max-width: "x")'],
];
add_filter('wp_preload_resources', $preload);
$say('preload links', $echoed(static fn () => wp_preload_resources()));
remove_filter('wp_preload_resources', $preload);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
