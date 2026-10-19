<?php
/**
 * How the theme.json resolver finds and makes a theme's user styles post
 * (probe global-styles-lookup): the WP_Query it runs as pre_get_posts sees
 * it, what a plugin steering it gets, an inactive theme's lookup, and the
 * post made when asked: for any theme, its wp_theme term given as tax_input
 * (set only for a user who may assign it), its name the stylesheet
 * URL-encoded. Same protocol as api-probe.php; the posts and terms the probe
 * makes are removed at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = [];
$themes = ['twentytwentythree', 'zz theme'];
register_shutdown_function(static function () use (&$made, $themes): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach ($themes as $name) {
        $term = get_term_by('name', $name, 'wp_theme');
        if ($term && (int) $term->term_id > 1) {
            wp_delete_term((int) $term->term_id, 'wp_theme');
        }
    }
});
foreach ($themes as $name) {
    if (get_posts(['post_type' => 'wp_global_styles', 'post_status' => 'any', 'name' => 'wp-global-styles-' . sanitize_title(urlencode($name))])) {
        fwrite(STDERR, "global-styles-lookup: a styles post for {$name} is already there\n");
        exit(1);
    }
}
$existing = array_map('intval', get_posts(['post_type' => 'wp_global_styles', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']));
$vars = [];
$grab = static function ($query) use (&$vars): void {
    if ($query->get('post_type') === 'wp_global_styles') {
        $vars[] = $query->query;
    }
};
$post = static function ($found) use (&$made, $existing): array {
    if (!is_array($found) || $found === []) {
        return [];
    }
    if (!in_array((int) $found['ID'], $existing, true) && (int) $found['ID'] > 1) {
        $made[] = (int) $found['ID'];
    }
    $terms = wp_get_object_terms((int) $found['ID'], 'wp_theme', ['fields' => 'names']);
    return array_intersect_key($found, array_flip(['post_title', 'post_name', 'post_status', 'post_content', 'post_type', 'comment_status', 'ping_status', 'post_author'])) + ['terms' => $terms];
};
add_action('pre_get_posts', $grab);
$active = wp_get_theme();

$vars = [];
$say('the active theme', ['found' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($active)), 'queries' => $vars]);
$vars = [];
$say('drafts too', ['found' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($active, false, ['publish', 'draft'])), 'queries' => $vars]);
$vars = [];
$say('an inactive theme', ['found' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('twentytwentythree'))), 'queries' => $vars]);
$steer = static function ($query): void {
    if ($query->get('post_type') === 'wp_global_styles') {
        $query->set('post_status', ['draft']);
    }
};
add_action('pre_get_posts', $steer);
$say('steered to drafts', ['found' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($active))]);
remove_action('pre_get_posts', $steer);
$say('the active theme\'s post id', WP_Theme_JSON_Resolver::get_user_global_styles_post_id() === (int) (WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles($active)['ID'] ?? -1));

// Made when asked, for an inactive theme: without a user the term is not set, so the post is not found again.
wp_set_current_user(0);
$vars = [];
$made1 = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('twentytwentythree'), true);
$say('made without a user', ['made' => $post($made1), 'queries' => count($vars), 'found after' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('twentytwentythree')))]);
if (!empty($made1['ID']) && (int) $made1['ID'] > 1) {
    wp_delete_post((int) $made1['ID'], true);
}
wp_set_current_user(1);
$made2 = WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('twentytwentythree'), true);
$say('made by an administrator', ['made' => $post($made2), 'found after' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('twentytwentythree')))]);
$say('a stylesheet with a space', ['made' => $post(WP_Theme_JSON_Resolver::get_user_data_from_wp_global_styles(wp_get_theme('zz theme'), true))]);
wp_set_current_user(0);
remove_action('pre_get_posts', $grab);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
