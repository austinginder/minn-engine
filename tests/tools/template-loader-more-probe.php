<?php
/**
 * More of template loading (probe template-loader-more): the template path
 * globals, a new template part's slug and theme, the editor's
 * find-template request hooked and unhooked, and the timezones sorted as
 * the timezone menu sorts them. The probe's posts are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});

$say('the hooks', [has_action('save_post_wp_template_part', 'wp_set_unique_slug_on_create_template_part'), has_action('wp_loaded', '_add_template_loader_filters')]);

$say('the template path globals', (static function () {
    unset($GLOBALS['wp_stylesheet_path'], $GLOBALS['wp_template_path']);
    $root = get_theme_root();
    $result = wp_set_template_globals();
    $relative = static fn ($path) => is_string($path) ? str_replace($root, '{themes}', $path) : $path;
    return [$result, $relative($GLOBALS['wp_stylesheet_path'] ?? null), $relative($GLOBALS['wp_template_path'] ?? null)];
})());

$part = static function (array $args, ?Closure $before = null) use (&$made) {
    $made[] = $id = (int) wp_insert_post($args + ['post_type' => 'wp_template_part', 'post_content' => 'x']);
    $post = get_post($id);
    return [(string) preg_replace('/^custom_slug_[0-9a-f]{13}$/', 'custom_slug_{uniqid}', $post->post_name), wp_get_post_terms($id, 'wp_theme', ['fields' => 'names']), $post->post_status];
};
$say('a new template part\'s slug', [
    $part(['post_status' => 'auto-draft', 'post_title' => 'Zz Part']),
    $part(['post_status' => 'auto-draft', 'post_title' => 'Zz Named', 'post_name' => 'zz-named']),
    $part(['post_status' => 'publish', 'post_title' => 'Zz Published']),
    $part(['post_status' => 'draft', 'post_title' => 'Zz Draft']),
    $part(['post_status' => 'auto-draft', 'post_title' => 'Zz Themed', 'tax_input' => ['wp_theme' => ['zz-other']]]),
    (static function () use (&$made) {
        $made[] = $id = (int) wp_insert_post(['post_type' => 'post', 'post_status' => 'auto-draft', 'post_title' => 'Zz Post', 'post_content' => 'x']);
        $before = get_post($id)->post_name;
        $result = wp_set_unique_slug_on_create_template_part($id);
        return [$result, get_post($id)->post_name === $before, wp_get_post_terms($id, 'wp_theme', ['fields' => 'names'])];
    })(),
    wp_set_unique_slug_on_create_template_part(999999),
]);

$say('the find-template request', (static function () {
    $saved = $_GET;
    $hooked = static fn () => has_action('pre_get_posts', '_resolve_template_for_new_post');
    $out = [];
    foreach ([['_wp-find-template' => 'true'], ['_wp-find-template' => ''], ['_wp-find-template' => 'false'], []] as $get) {
        $_GET = $get;
        $out[] = [_add_template_loader_filters(), $hooked()];
        remove_action('pre_get_posts', '_resolve_template_for_new_post');
    }
    $_GET = $saved;
    return $out;
})());

$say('a new post\'s template query', (static function () use (&$made) {
    global $wp_the_query;
    wp_set_current_user(1);
    $made[] = $draft = (int) wp_insert_post(['post_type' => 'post', 'post_status' => 'auto-draft', 'post_title' => 'Zz Auto', 'post_content' => 'x']);
    $out = [];
    foreach ([true, false] as $main) {
        $saved = [$wp_the_query, $_GET];
        $query = new WP_Query();
        $query->set('p', $draft);
        $_GET = ['p' => $draft, '_wp-find-template' => 'true'];
        if ($main) {
            $wp_the_query = $query;
        }
        add_action('pre_get_posts', '_resolve_template_for_new_post');
        $before = get_object_vars($query);
        $result = _resolve_template_for_new_post($query);
        $out[] = [$main, $result, get_object_vars($query) === $before, has_action('pre_get_posts', '_resolve_template_for_new_post')];
        remove_action('pre_get_posts', '_resolve_template_for_new_post');
        [$wp_the_query, $_GET] = $saved;
    }
    wp_set_current_user(0);
    return $out;
})());

$say('tax_input needs the right to assign terms', (static function () use (&$made) {
    $category = wp_insert_term('Zz Ti Cat', 'category');
    $categoryId = is_array($category) ? (int) $category['term_id'] : 0;
    $contributor = (int) wp_insert_user(['user_login' => 'zz_ti_contributor', 'user_pass' => wp_generate_password(), 'user_email' => 'zz-ti@zz.example', 'role' => 'contributor']);
    $out = [];
    foreach (['nobody' => 0, 'contributor' => $contributor, 'administrator' => 1] as $who => $user) {
        wp_set_current_user($user);
        foreach (['post' => ['category' => [$categoryId], 'post_tag' => ['zz-ti-tag']], 'wp_template_part' => ['wp_theme' => ['zz-ti-theme']]] as $type => $taxonomies) {
            $made[] = $id = (int) wp_insert_post(['post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Zz Ti', 'post_content' => 'x', 'tax_input' => $taxonomies]);
            $out[] = [$who, $type, array_map(static fn ($taxonomy) => wp_get_post_terms($id, $taxonomy, ['fields' => 'names']), array_combine(array_keys($taxonomies), array_keys($taxonomies)))];
        }
    }
    wp_set_current_user(0);
    foreach (array_splice($made, -6) as $id) {
        wp_delete_post($id, true);
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    // A failed insert casts to user 1: never delete it.
    if (is_int($contributor) && $contributor > 1) {
        wp_delete_user($contributor);
    }
    foreach ([['zz-ti-tag', 'post_tag'], ['zz-ti-theme', 'wp_theme']] as [$slug, $taxonomy]) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
    }
    if ($categoryId > 0) {
        wp_delete_term($categoryId, 'category');
    }
    return $out;
})());

// The menu's entries are continent, city and subcity, each with its translation; here the translations are the names.
$zone = static function (string $id, array $translated = []): array {
    $parts = explode('/', $id, 3) + ['', '', ''];
    return ['continent' => $parts[0], 'city' => $parts[1], 'subcity' => $parts[2], 't_continent' => $translated[0] ?? $parts[0], 't_city' => $translated[1] ?? $parts[1], 't_subcity' => $translated[2] ?? $parts[2]];
};
$say('the timezones sorted', (static function () use ($zone) {
    // The menu's Etc offsets are not in PHP's default list; they are added, with the other Etc names.
    $offsets = array_merge(array_map(static fn ($n) => 'Etc/GMT+' . $n, range(1, 12)), array_map(static fn ($n) => 'Etc/GMT-' . $n, range(1, 14)));
    $zones = array_map($zone, array_merge(timezone_identifiers_list(), $offsets, ['Etc/GMT', 'Etc/UTC', 'Etc/GMT+0', 'Etc/GMT-0', 'Etc/Universal', 'Etc/Zulu', 'Etc/Greenwich', 'Etc/UCT']));
    usort($zones, '_wp_timezone_choice_usort_callback');
    return array_map(static fn ($entry) => rtrim($entry['continent'] . '/' . $entry['city'] . '/' . $entry['subcity'], '/'), $zones);
})());
$say('timezones compared through their translations', array_map(static fn ($pair) => _wp_timezone_choice_usort_callback($zone(...$pair[0]), $zone(...$pair[1])), [
    [['Europe/Zz', ['Europe', 'Aa']], ['Europe/Aa', ['Europe', 'Zz']]],
    [['America/Zz', ['Aa']], ['Europe/Aa', ['Zz']]],
    [['Etc/GMT+1', ['Aa', 'Zz']], ['Europe/Paris', ['Zz', 'Aa']]],
    [['Europe/Paris', ['Zz']], ['Etc/GMT+1', ['Aa']]],
    [['Etc/GMT+1', ['Etc', 'Zz']], ['Etc/GMT+2', ['Etc', 'Aa']]],
    [['Etc/GMT+1', ['Etc', 'GMT+1x']], ['Etc/GMT+1']],
    [['America/Argentina/Salta', ['America', 'Argentina', 'Aa']], ['America/Argentina/Jujuy', ['America', 'Argentina', 'Zz']]],
    [['America/Indiana/Knox', ['America', 'Zz', 'Knox']], ['America/Iqaluit', ['America', 'Aa']]],
    [['Europe/Paris'], ['Europe/Paris']],
    [['UTC'], ['Etc/UTC']],
    [['Etc/UTC'], ['UTC']],
    [['UTC', ['Aa']], ['Europe/Paris']],
]));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
