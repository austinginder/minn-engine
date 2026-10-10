<?php
/**
 * The rest of query.php (probe query-remaining): the date, time, favicon,
 * robots, trackback and privacy policy conditionals for the queries that set
 * them, post data generated without moving the loop, the loop rewound, and
 * the old slug redirect (its address heard through old_slug_redirect_url and
 * refused there, so nothing redirects). The probe's posts are removed and the
 * privacy policy page put back at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$privacy = get_option('wp_page_for_privacy_policy', null);
$pings = wp_next_scheduled('do_pings');
register_shutdown_function(static function () use (&$made, $privacy, $pings): void {
    $privacy === null ? delete_option('wp_page_for_privacy_policy') : update_option('wp_page_for_privacy_policy', $privacy);
    foreach ($made as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    if ($pings === false) {
        wp_clear_scheduled_hook('do_pings');
    }
});
$ids = [];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value)) {
        return isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (string $title, array $extra = []) use (&$made, &$ids): int {
    $made[] = $id = (int) wp_insert_post($extra + ['post_title' => $title, 'post_status' => 'publish', 'post_content' => "zz content for {$title}"]);
    $ids[$id] = str_replace(' ', '-', $title);
    return $id;
};
$query = static function (array $args) {
    global $wp_query, $wp_the_query;
    $wp_the_query = $wp_query = new WP_Query();
    $wp_query->query($args);
    return $wp_query;
};
$flags = static fn () => ['year' => is_year(), 'month' => is_month(), 'day' => is_day(), 'time' => is_time(), 'favicon' => is_favicon(), 'robots' => is_robots(), 'trackback' => is_trackback(), 'privacy' => is_privacy_policy()];

$single = $post('zz qr single', ['post_date' => '1985-04-05 06:07:08']);
$page = $post('zz qr policy', ['post_type' => 'page']);
update_option('wp_page_for_privacy_policy', $page);
$conditions = [];
foreach ([
    'a year' => ['year' => 1985], 'a month' => ['year' => 1985, 'monthnum' => 4], 'a day' => ['year' => 1985, 'monthnum' => 4, 'day' => 5], 'an hour' => ['year' => 1985, 'hour' => 6],
    'a minute' => ['minute' => 7], 'a second' => ['second' => 8], 'a week' => ['year' => 1985, 'w' => 14], 'm as a year' => ['m' => '1985'], 'm as a day' => ['m' => '19850405'], 'm as a time' => ['m' => '19850405060708'],
    'the favicon' => ['favicon' => 1], 'robots' => ['robots' => 1], 'a trackback' => ['p' => $single, 'tb' => 1], 'the privacy page' => ['page_id' => $page], 'another page' => ['page_id' => $single, 'post_type' => 'page'], 'the post' => ['p' => $single],
] as $label => $args) {
    $query($args);
    $conditions[$label] = $flags();
}
unset($GLOBALS['wp_query']);
$conditions['no query'] = [is_year(), is_day(), is_robots(), is_privacy_policy()];
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
$say('conditionals', $conditions);

$other = $post('zz qr other', ['post_date' => '1985-04-06 06:07:08', 'post_content' => "first<!--nextpage-->second<!--more-->third"]);
$loop = $query(['post__in' => [$single, $other], 'orderby' => 'post__in']);
$generated = [generate_postdata($other), generate_postdata(get_post($single)), generate_postdata(999999), generate_postdata(0)];
$shape = static fn ($data) => is_array($data) ? array_map(static fn ($value) => $value instanceof WP_User ? 'user:' . $value->ID : (is_array($value) ? count($value) : $value), $data) : $data;
$loopState = [$loop->current_post, $loop->in_the_loop, $GLOBALS['post']->ID ?? null];
$say('post data generated', $mask([array_map($shape, $generated), $loopState]));

$rewound = [];
$loop->the_post();
$loop->the_post();
$rewound[] = [$loop->current_post, $GLOBALS['post']->ID];
rewind_posts();
$rewound[] = [$loop->current_post, $loop->have_posts(), $GLOBALS['post']->ID ?? null];
$loop->the_post();
$rewound[] = [$loop->current_post, $GLOBALS['post']->ID];
$say('the loop rewound', $mask($rewound));

register_post_type('zz_qr_tree', ['public' => true, 'hierarchical' => true]);
register_post_type('zz_qr_flat', ['public' => true]);
$renamed = $post('zz qr renamed', ['post_name' => 'zz-qr-new-name', 'post_date' => '1985-05-05 10:00:00']);
add_post_meta($renamed, '_wp_old_slug', 'zz-qr-old-name');
add_post_meta($renamed, '_wp_old_date', '1985-05-01');
$heard = [];
add_filter('old_slug_redirect_url', $refuse = static function ($link) use (&$heard) {
    $heard[] = $link;
    return false;
});
add_filter('old_slug_redirect_post_id', $postIdHeard = static function ($id) use (&$heard) {
    $heard[] = ['old_slug_redirect_post_id', $id];
    return $id;
});
$redirects = [];
foreach ([
    'the old name' => ['name' => 'zz-qr-old-name'], 'the old name, paged' => ['name' => 'zz-qr-old-name', 'paged' => 2], 'the old name, as a feed' => ['name' => 'zz-qr-old-name', 'feed' => 'rss2'],
    'the old name, embedded' => ['name' => 'zz-qr-old-name', 'embed' => 'true'], 'the old name on its old day' => ['name' => 'zz-qr-old-name', 'year' => 1985, 'monthnum' => 5, 'day' => 1],
    'the new name on the old day' => ['name' => 'zz-qr-new-name', 'year' => 1985, 'monthnum' => 5, 'day' => 1], 'a name never used' => ['name' => 'zz-qr-never'], 'the new name' => ['name' => 'zz-qr-new-name'],
    'the old name as a page' => ['name' => 'zz-qr-old-name', 'post_type' => 'page'],
    'the old name as a tree' => ['name' => 'zz-qr-old-name', 'post_type' => 'zz_qr_tree'], 'the old name as a flat type' => ['name' => 'zz-qr-old-name', 'post_type' => 'zz_qr_flat'],
    'the old name as two types' => ['name' => 'zz-qr-old-name', 'post_type' => ['post', 'zz_qr_flat']], 'the old name as an attachment' => ['name' => 'zz-qr-old-name', 'post_type' => 'attachment'],
] as $label => $args) {
    $q = $query($args);
    // The main request marks a query that found nothing as a 404; a WP_Query alone does not.
    if (!$q->have_posts()) {
        $q->set_404();
    }
    $heard = [];
    wp_old_slug_redirect();
    $redirects[$label] = [is_404(), $heard];
}
remove_filter('old_slug_redirect_url', $refuse);
remove_filter('old_slug_redirect_post_id', $postIdHeard);
$say('the old slug redirect', $mask($redirects));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
