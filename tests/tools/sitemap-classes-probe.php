<?php
/**
 * The sitemap classes as plugins use them (probe sitemap-classes): the
 * server and its registry, a provider added and refused, the posts, terms
 * and users providers over the probe's own post type, taxonomy and users
 * (narrowed with the sitemap filters, two URLs a page), the index, the
 * renderer's XML and the stylesheets. The probe's posts, terms and users
 * are removed.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'users' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_term($id, 'zz_sm_tax');
        }
    }
    if (!function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
});
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use ($host, &$ids, &$mask) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    foreach ($ids as $id => $name) {
        $value = (string) preg_replace('/(?<![0-9])' . $id . '(?![0-9])/', '{' . $name . '}', $value);
    }
    return (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '//{host}', $value);
};

register_post_type('zz_sm', ['public' => true, 'label' => 'Zz Sm', 'rewrite' => ['slug' => 'zz-sm']]);
register_taxonomy('zz_sm_tax', ['zz_sm'], ['public' => true, 'label' => 'Zz Sm Tax', 'rewrite' => ['slug' => 'zz-sm-tax']]);
foreach (['a' => '2020-01-01 10:00:00', 'b' => '2021-02-02 11:00:00', 'c' => '2022-03-03 12:00:00'] as $slug => $date) {
    $made['posts'][] = $id = (int) wp_insert_post(['post_type' => 'zz_sm', 'post_status' => 'publish', 'post_title' => 'Zz Sm ' . $slug, 'post_name' => 'zz-sm-' . $slug, 'post_date' => $date, 'post_modified' => $date, 'post_content' => 'x']);
    $ids[$id] = 'post-' . $slug;
}
foreach (['one', 'two', 'three'] as $slug) {
    $term = wp_insert_term('Zz Sm ' . $slug, 'zz_sm_tax', ['slug' => 'zz-sm-' . $slug]);
    $made['terms'][] = $id = (int) ($term['term_id'] ?? 0);
    $ids[$id] = 'term-' . $slug;
    wp_set_object_terms($made['posts'][0], [$id], 'zz_sm_tax', true);
}
foreach (['ann', 'bob'] as $login) {
    $made['users'][] = $id = (int) wp_insert_user(['user_login' => 'zz_sm_' . $login, 'user_pass' => wp_generate_password(), 'user_email' => "zz-sm-{$login}@zz.example", 'user_nicename' => 'zz-sm-' . $login, 'role' => 'author']);
    $ids[$id] = 'user-' . $login;
    $made['posts'][] = (int) wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Zz Sm by ' . $login, 'post_author' => $id, 'post_content' => 'x']);
}
// Narrowed to the probe's own objects, two URLs a page.
add_filter('wp_sitemaps_max_urls', static fn () => 2);
add_filter('wp_sitemaps_post_types', static fn ($types) => array_intersect_key($types, ['zz_sm' => true]));
add_filter('wp_sitemaps_taxonomies', static fn ($taxonomies) => array_intersect_key($taxonomies, ['zz_sm_tax' => true]));
add_filter('wp_sitemaps_users_query_args', static fn ($args) => ['include' => $made['users']] + $args);

$server = wp_sitemaps_get_server();
$say('the server', [get_class($server), $server->sitemaps_enabled(), array_map('get_class', $server->registry->get_providers()), get_class($server->index), get_class($server->renderer)]);

$say('the registry', (static function () {
    $registry = new WP_Sitemaps_Registry();
    $posts = new WP_Sitemaps_Posts();
    $out = [$registry->add_provider('zz', $posts), $registry->add_provider('zz', new WP_Sitemaps_Users()), array_map('get_class', $registry->get_providers()), get_class($registry->get_provider('zz')), $registry->get_provider('none')];
    $refuse = static fn () => 'not a provider';
    add_filter('wp_sitemaps_add_provider', $refuse);
    $out[] = $registry->add_provider('zz-refused', $posts);
    remove_filter('wp_sitemaps_add_provider', $refuse);
    $heard = [];
    $listen = static function ($provider, $name) use (&$heard) {
        $heard[] = [get_class($provider), $name];
        return $provider;
    };
    add_filter('wp_sitemaps_add_provider', $listen, 10, 2);
    $out[] = [$registry->add_provider('zz-heard', $posts), $heard];
    remove_filter('wp_sitemaps_add_provider', $listen, 10);
    return $out;
})());

$provider = static fn (WP_Sitemaps_Provider $p, string $subtype) => $mask([
    array_keys($p->get_object_subtypes()),
    $p->get_max_num_pages($subtype), $p->get_url_list(1, $subtype), $p->get_url_list(2, $subtype), $p->get_url_list(9, $subtype),
    $p->get_sitemap_type_data(), $p->get_sitemap_entries(), $p->get_sitemap_url($subtype, 2), $p->get_sitemap_url('', 1),
]);
$say('the posts provider', [$provider(new WP_Sitemaps_Posts(), 'zz_sm'), $mask([(new WP_Sitemaps_Posts())->get_url_list(1, 'zz_missing'), (new WP_Sitemaps_Posts())->get_max_num_pages('zz_missing')])]);
$say('the taxonomies provider', [$provider(new WP_Sitemaps_Taxonomies(), 'zz_sm_tax'), $mask([(new WP_Sitemaps_Taxonomies())->get_url_list(1, 'zz_missing'), (new WP_Sitemaps_Taxonomies())->get_max_num_pages('zz_missing')])]);
$say('the users provider', $provider(new WP_Sitemaps_Users(), ''));

$say('the index', $mask((static function () {
    $registry = new WP_Sitemaps_Registry();
    $registry->add_provider('posts', new WP_Sitemaps_Posts());
    $registry->add_provider('users', new WP_Sitemaps_Users());
    $index = new WP_Sitemaps_Index($registry);
    return [$index->get_sitemap_list(), $index->get_index_url()];
})()));

$say('the renderer', $mask((static function () {
    $renderer = new WP_Sitemaps_Renderer();
    return [
        $renderer->get_sitemap_stylesheet_url(),
        $renderer->get_sitemap_index_stylesheet_url(),
        $renderer->get_sitemap_xml([['loc' => 'https://zz.example/a?x=1&y=2', 'lastmod' => '2020-01-01T00:00:00+00:00'], ['loc' => 'https://zz.example/b']]),
        $renderer->get_sitemap_index_xml([['loc' => 'https://zz.example/s-1.xml'], ['loc' => 'https://zz.example/s-2.xml', 'lastmod' => '2021-01-01']]),
        $renderer->get_sitemap_xml([]),
        $renderer->get_sitemap_index_xml([]),
        $renderer->get_sitemap_xml([[]]),
        $renderer->get_sitemap_xml([['loc' => ''], ['loc' => 'https://zz.example/c', 'lastmod' => '']]),
        $renderer->get_sitemap_index_xml([[], ['loc' => '']]),
    ];
})()));

// The stylesheets are left out: the engine serves its own, not the reference's text.

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
