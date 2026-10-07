<?php
/**
 * Plugin Name: Minn test sitemap
 * Description: Fixture for the sitemap-hooks suite, loaded by the engine and the reference alike. A request that carries X-Minn-Sitemap naming a run the suite opened (wp-content/minn-sitemap/<run>.open exists) gets a plugin on the core sitemaps and robots.txt, as SEO plugins are; X-Minn-Sitemap-Mode says how: "markers" hooks every seam (the query arguments, the entries, the subtypes, the page size, the stylesheets, a provider and a public post type of its own, robots.txt's lines), "off" turns the sitemaps off, "replace" answers the lists and page counts itself, "redirect" sends the index elsewhere from template_redirect. What it hears goes to <run>.log. Without such a run the header does nothing.
 * License: MIT
 */

$minnSitemapRun = (string) ($_SERVER['HTTP_X_MINN_SITEMAP'] ?? '');
$minnSitemapDir = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : dirname(__DIR__)) . '/minn-sitemap';
if (preg_match('/^[a-z0-9-]{1,64}$/', $minnSitemapRun) !== 1 || !is_file("{$minnSitemapDir}/{$minnSitemapRun}.open")) {
    return;
}
$minnSitemapHeard = static function (string $what) use ($minnSitemapDir, $minnSitemapRun): void {
    file_put_contents("{$minnSitemapDir}/{$minnSitemapRun}.log", str_replace(home_url(), '{site}', $what) . "\n", FILE_APPEND);
};
$minnSitemapMode = (string) ($_SERVER['HTTP_X_MINN_SITEMAP_MODE'] ?? 'markers');
$minnSitemapJson = static fn ($value): string => (string) json_encode($value, JSON_UNESCAPED_SLASHES);

add_action('wp_sitemaps_init', static fn ($server) => $minnSitemapHeard('wp_sitemaps_init ' . get_class($server)));
add_filter('wp_sitemaps_add_provider', static function ($provider, $name) use ($minnSitemapHeard) {
    $minnSitemapHeard("wp_sitemaps_add_provider {$name} " . (is_object($provider) ? get_parent_class($provider) : gettype($provider)));
    return $provider;
}, 10, 2);
add_filter('wp_sitemaps_enabled', static function ($enabled) use ($minnSitemapHeard, $minnSitemapMode, $minnSitemapJson) {
    $minnSitemapHeard('wp_sitemaps_enabled ' . $minnSitemapJson($enabled));
    return $minnSitemapMode === 'off' ? false : $enabled;
});
add_action('template_redirect', static fn () => $minnSitemapHeard('template_redirect ' . $minnSitemapJson([get_query_var('sitemap'), get_query_var('sitemap-subtype'), get_query_var('sitemap-stylesheet'), get_query_var('paged'), is_robots(), is_404()])), 9);
add_action('do_robotstxt', static fn () => $minnSitemapHeard('do_robotstxt'));
add_filter('robots_txt', static function ($output, $public) use ($minnSitemapHeard, $minnSitemapJson) {
    $minnSitemapHeard('robots_txt ' . $minnSitemapJson([$output, $public]));
    return $output . "Disallow: /zz-private/\n";
}, 10, 2);

if ($minnSitemapMode === 'markers') {
    add_action('init', static function (): void {
        register_post_type('zz_sitemap_book', ['public' => true, 'label' => 'Zz Books']);
        wp_register_sitemap_provider('zzextra', new class extends WP_Sitemaps_Provider {
            public function __construct()
            {
                $this->name = 'zzextra';
                $this->object_type = 'zzextra';
            }

            public function get_url_list($page_num, $object_subtype = '')
            {
                return (int) $page_num === 1 ? [['loc' => home_url('/zz-extra/')], ['loc' => home_url('/zz-extra-two/'), 'lastmod' => '2026-09-01T10:00:00+00:00']] : [];
            }

            public function get_max_num_pages($object_subtype = '')
            {
                return 1;
            }
        });
    });
    foreach (['wp_sitemaps_posts_query_args' => 'title', 'wp_sitemaps_taxonomies_query_args' => 'name', 'wp_sitemaps_users_query_args' => 'login'] as $minnSitemapFilter => $minnSitemapOrder) {
        add_filter($minnSitemapFilter, static function ($args, $subtype = '') use ($minnSitemapHeard, $minnSitemapJson, $minnSitemapFilter, $minnSitemapOrder) {
            $shown = $args;
            ksort($shown);
            $minnSitemapHeard("{$minnSitemapFilter} {$subtype} " . $minnSitemapJson($shown));
            return ['orderby' => $minnSitemapOrder, 'order' => 'DESC'] + $args;
        }, 10, 2);
    }
    add_filter('wp_sitemaps_posts_entry', static function ($entry, $post, $type) use ($minnSitemapHeard, $minnSitemapJson) {
        $minnSitemapHeard("wp_sitemaps_posts_entry {$type} " . get_class($post) . ' ' . $minnSitemapJson($entry));
        return $entry + ['priority' => '0.8'];
    }, 10, 3);
    add_filter('wp_sitemaps_posts_show_on_front_entry', static function ($entry) use ($minnSitemapHeard, $minnSitemapJson) {
        $minnSitemapHeard('wp_sitemaps_posts_show_on_front_entry ' . $minnSitemapJson($entry));
        return $entry + ['changefreq' => 'daily'];
    });
    add_filter('wp_sitemaps_taxonomies_entry', static function ($entry, $id, $taxonomy, $term) use ($minnSitemapHeard, $minnSitemapJson) {
        $minnSitemapHeard("wp_sitemaps_taxonomies_entry {$taxonomy} " . gettype($id) . ' ' . get_class($term) . ' ' . $minnSitemapJson($entry));
        return $entry + ['priority' => '0.3'];
    }, 10, 4);
    add_filter('wp_sitemaps_users_entry', static function ($entry, $user) use ($minnSitemapHeard, $minnSitemapJson) {
        $minnSitemapHeard('wp_sitemaps_users_entry ' . get_class($user) . ' ' . $minnSitemapJson($entry));
        return $entry + ['changefreq' => 'monthly'];
    }, 10, 2);
    add_filter('wp_sitemaps_index_entry', static function ($entry, $type, $subtype, $page) use ($minnSitemapHeard, $minnSitemapJson) {
        $minnSitemapHeard("wp_sitemaps_index_entry {$type} {$subtype} {$page} " . $minnSitemapJson($entry));
        return $entry + ['lastmod' => '2026-01-01T00:00:00+00:00'];
    }, 10, 4);
    add_filter('wp_sitemaps_post_types', static function ($types) use ($minnSitemapHeard) {
        $minnSitemapHeard('wp_sitemaps_post_types ' . implode(',', array_keys($types)));
        unset($types['page']);
        return $types;
    });
    add_filter('wp_sitemaps_taxonomies', static function ($taxonomies) use ($minnSitemapHeard) {
        $minnSitemapHeard('wp_sitemaps_taxonomies ' . implode(',', array_keys($taxonomies)));
        return $taxonomies;
    });
    add_filter('wp_sitemaps_max_urls', static fn ($max, $type) => $type === 'term' ? 1 : $max, 10, 2);
    add_filter('wp_sitemaps_stylesheet_url', static function ($url) use ($minnSitemapHeard) {
        $minnSitemapHeard("wp_sitemaps_stylesheet_url {$url}");
        return '';
    });
    add_filter('wp_sitemaps_stylesheet_index_url', static function ($url) use ($minnSitemapHeard) {
        $minnSitemapHeard("wp_sitemaps_stylesheet_index_url {$url}");
        return $url . '?zz=1';
    });
    add_filter('wp_sitemaps_stylesheet_index_content', static fn ($xsl) => str_replace('</xsl:stylesheet>', '<!-- zz index stylesheet --></xsl:stylesheet>', $xsl));
}
if ($minnSitemapMode === 'replace') {
    add_filter('wp_sitemaps_posts_pre_url_list', static fn ($list, $type, $page) => $type === 'post' ? [['loc' => home_url("/zz-replaced-{$page}/")]] : $list, 10, 3);
    add_filter('wp_sitemaps_posts_pre_max_num_pages', static fn ($max, $type) => $type === 'post' ? 3 : $max, 10, 2);
    add_filter('wp_sitemaps_taxonomies_pre_url_list', static fn ($list, $taxonomy) => $taxonomy === 'post_tag' ? [] : $list, 10, 2);
    add_filter('wp_sitemaps_taxonomies_pre_max_num_pages', static fn ($max, $taxonomy) => $taxonomy === 'post_tag' ? 0 : $max, 10, 2);
    add_filter('wp_sitemaps_users_pre_url_list', static fn () => [['loc' => home_url('/zz-people/')]]);
    add_filter('wp_sitemaps_users_pre_max_num_pages', static fn () => 1);
}
if ($minnSitemapMode === 'redirect') {
    add_action('template_redirect', static function (): void {
        if (get_query_var('sitemap') === 'index') {
            wp_redirect(home_url('/zz-sitemap-index.xml'), 301);
            exit;
        }
    }, 5);
}
