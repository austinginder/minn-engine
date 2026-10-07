<?php
/**
 * Plugin Name: Minn test rules
 * Description: Fixture for the plugin-rules suite, loaded by the engine and the reference alike. While a run the suite opened is open (wp-content/minn-rules/<run>.open exists), it changes the rewrite rules the ways SEO, shop and multilingual plugins do: category archives without their base (each category's own rules in place of category/…, and term links to match), a tag archive at an address of its own (rewrite_rules_array), an author's at another (generate_rewrite_rules), a post by id (post_rewrite_rules), a day's archive (date_rewrite_rules), a post in its category, and a page with a view var of the plugin's. For a request carrying X-Minn-Rules naming the run it notes at template_redirect what the request amounted to: the query vars the parse set, the conditionals and the queried object (appended to <run>.log). The rules stand for every request of the run, so the reference's stored rules can be rebuilt with them.
 * License: MIT
 */

$minnRulesDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-rules';
if ((glob("{$minnRulesDir}/*.open") ?: []) === []) {
    return;
}
add_filter('category_rewrite_rules', static function (array $rules): array {
    $own = [];
    foreach (get_categories(['hide_empty' => false]) as $category) {
        $path = trim((string) get_category_parents($category->term_id, false, '/', true), '/');
        $own['(' . preg_quote($path, '#') . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
        $own['(' . preg_quote($path, '#') . ')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
        $own['(' . preg_quote($path, '#') . ')/?$'] = 'index.php?category_name=$matches[1]';
    }
    return $own;
});
add_filter('term_link', static fn ($link, $term, $taxonomy) => $taxonomy === 'category' ? str_replace('/category/', '/', (string) $link) : $link, 10, 3);
add_filter('rewrite_rules_array', static fn (array $rules): array => [
    'zz-shelf/([^/]+)/?$' => 'index.php?tag=$matches[1]',
    'zz-view/(.+?)/?$' => 'index.php?pagename=$matches[1]&zz_view=1',
    'zz-in/([^/]+)/([^/]+)/?$' => 'index.php?category_name=$matches[1]&name=$matches[2]',
] + $rules);
add_action('generate_rewrite_rules', static function ($rewrite): void {
    $rewrite->rules = ['zz-writer/([^/]+)/?$' => 'index.php?author_name=$matches[1]'] + $rewrite->rules;
});
add_filter('post_rewrite_rules', static fn (array $rules): array => ['zz-go/([0-9]+)/?$' => 'index.php?p=$matches[1]'] + $rules);
add_filter('date_rewrite_rules', static fn (array $rules): array => ['zz-day/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]'] + $rules);
add_filter('query_vars', static fn (array $vars): array => [...$vars, 'zz_view']);

$minnRulesRun = (string) ($_SERVER['HTTP_X_MINN_RULES'] ?? '');
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnRulesRun) !== 1 || !is_file("{$minnRulesDir}/{$minnRulesRun}.open")) {
    return;
}
add_action('template_redirect', static function () use ($minnRulesDir, $minnRulesRun): void {
    global $wp;
    $vars = array_intersect_key((array) $wp->query_vars, array_flip(['category_name', 'tag', 'author_name', 'p', 'name', 'pagename', 'page', 'year', 'monthnum', 'day', 'paged', 'feed', 'attachment', 'zz_view', 'error']));
    ksort($vars);
    file_put_contents("{$minnRulesDir}/{$minnRulesRun}.log", json_encode([
        'vars' => array_map('strval', $vars),
        'is' => array_keys(array_filter(['category' => is_category(), 'tag' => is_tag(), 'author' => is_author(), 'date' => is_date(), 'day' => is_day(), 'single' => is_single(), 'page' => is_page(), 'home' => is_home(), 'archive' => is_archive(), 'feed' => is_feed(), 'paged' => is_paged(), '404' => is_404()])),
        'queried' => get_queried_object_id(),
        'zz_view' => get_query_var('zz_view', '(unset)'),
    ]) . "\n", FILE_APPEND);
}, 1);
