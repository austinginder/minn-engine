<?php
/**
 * What the template functions hand their filters (probe filter-inputs): a
 * listener on each filter records the arguments it was given and passes the
 * value on unchanged, while the functions that fire them run. Plugins edit
 * these pieces (a translation plugin narrows the adjacent post's WHERE to its
 * language; a theme sorts the tag cloud), so the pieces must be the
 * reference's. The rows the probe makes are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = rtrim(home_url(), '/');
$ids = [];
/** An argument in words that mean the same on both stacks. */
$describe = static function ($value) use (&$describe, $home, &$ids) {
    if ($value instanceof WP_Post) {
        return "post:{$value->post_type}:{$value->post_name}";
    }
    if ($value instanceof WP_Term) {
        return "term:{$value->taxonomy}:{$value->slug}";
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_array($value)) {
        return array_map($describe, $value);
    }
    if (is_int($value) && in_array($value, $ids, true)) {
        return '{' . array_search($value, $ids, true) . '}';
    }
    if (is_string($value)) {
        $value = str_replace($home, '{home}', $value);
        foreach ($ids as $name => $id) {
            $value = preg_replace('/\b' . $id . '\b/', '{' . $name . '}', $value);
        }
        return (string) preg_replace("/'\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}'/", "'{date}'", $value);
    }
    return $value;
};
$heard = [];
$listen = static function (array $hooks) use (&$heard, $describe): void {
    foreach ($hooks as $hook) {
        add_filter($hook, static function (...$args) use ($hook, &$heard, $describe) {
            $heard[] = [$hook, array_map($describe, $args)];
            return $args[0] ?? null;
        }, 10, 9);
    }
};
$run = static function (string $label, callable $call) use (&$heard, $say): void {
    $heard = [];
    $call();
    $say($label, $heard);
};

// Three posts a day apart, in two tags, and a category of their own.
$made = [];
register_shutdown_function(static function () use (&$made): void {
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach (array_reverse($made) as [$kind, $id]) {
        if (is_int($id) && $id > 1) {
            match ($kind) {
                'term' => wp_delete_term($id, 'post_tag'),
                'user' => wp_delete_user($id),
                default => wp_delete_post($id, true),
            };
        }
    }
});
foreach (['zz-fi-one' => '2001-02-03 10:00:00', 'zz-fi-two' => '2001-02-04 10:00:00', 'zz-fi-three' => '2001-02-05 10:00:00'] as $slug => $date) {
    $id = wp_insert_post(['post_title' => $slug, 'post_name' => $slug, 'post_status' => 'publish', 'post_date' => $date, 'post_content' => 'zz']);
    if (!is_int($id) || $id <= 1) {
        fwrite(STDERR, "could not make the probe's posts\n");
        exit(1);
    }
    $made[] = ['post', $id];
    $ids[$slug] = $id;
}
foreach (['zz-fi-red', 'zz-fi-blue'] as $tag) {
    $term = wp_insert_term($tag, 'post_tag', ['slug' => $tag]);
    if (is_array($term)) {
        $made[] = ['term', (int) $term['term_id']];
        $ids[$tag] = (int) $term['term_id'];
    }
}
wp_set_post_tags($ids['zz-fi-one'], ['zz-fi-red'], true);
wp_set_post_tags($ids['zz-fi-two'], ['zz-fi-red', 'zz-fi-blue'], true);

$listen(['get_previous_post_join', 'get_previous_post_where', 'get_previous_post_sort', 'get_next_post_join', 'get_next_post_where', 'get_next_post_sort', 'get_previous_post_excluded_terms', 'get_next_post_excluded_terms']);
$GLOBALS['post'] = get_post($ids['zz-fi-two']);
setup_postdata($GLOBALS['post']);
$run('adjacent posts', static function (): void {
    get_adjacent_post(false, '', true);
    get_adjacent_post(false, '', false);
});
$run('adjacent posts in the same tag', static function (): void {
    get_adjacent_post(true, '', true, 'post_tag');
    get_adjacent_post(true, '', false, 'post_tag');
});
$run('adjacent posts, a tag excluded', static function () use ($ids): void {
    get_adjacent_post(false, (string) $ids['zz-fi-blue'], true, 'post_tag');
    get_adjacent_post(true, [$ids['zz-fi-blue']], false, 'post_tag');
});
$before = get_current_user_id();
wp_set_current_user(1);
$run('adjacent posts, an administrator', static function (): void {
    get_adjacent_post(false, '', true);
});
$subscriber = wp_insert_user(['user_login' => 'zz_fi_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'zz_fi_sub@example.com', 'role' => 'subscriber']);
if (is_int($subscriber) && $subscriber > 1) {
    $made[] = ['user', $subscriber];
    wp_set_current_user($subscriber);
    $ids['zz_fi_sub'] = $subscriber;
    $run('adjacent posts, a subscriber', static function (): void {
        get_adjacent_post(false, '', false);
    });
}
wp_set_current_user($before);

$listen(['getarchives_where', 'getarchives_join', 'wp_get_archives_args']);
foreach (['monthly', 'yearly', 'daily', 'postbypost'] as $type) {
    $run("archives, {$type}", static function () use ($type): void {
        wp_get_archives(['type' => $type, 'echo' => 0, 'limit' => 2]);
    });
}

$listen(['tag_cloud_sort', 'wp_generate_tag_cloud_data', 'wp_tag_cloud', 'widget_tag_cloud_args']);
$run('tag cloud', static function () use ($ids): void {
    wp_tag_cloud(['echo' => false, 'include' => [$ids['zz-fi-red'] ?? 0, $ids['zz-fi-blue'] ?? 0]]);
});

$listen(['get_calendar_args']);
$run('calendar', static function (): void {
    get_calendar(true, false);
});

$listen(['pre_get_search_form', 'search_form_args', 'search_form_format']);
$run('search form', static function (): void {
    get_search_form(['echo' => false]);
});

$listen(['feed_links_args', 'feed_links_extra_args']);
$run('feed links', static function (): void {
    ob_start();
    feed_links();
    feed_links_extra();
    ob_end_clean();
});

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
