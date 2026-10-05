<?php

use Minn\Front\SitemapXml;

/** The sitemaps server: registry, renderer, index. URLs come from the engine's own sitemap routes. */
#[AllowDynamicProperties]
class WP_Sitemaps
{
    public $index;
    public $registry;
    public $renderer;

    public function __construct()
    {
        $this->registry = new WP_Sitemaps_Registry();
        $this->renderer = new WP_Sitemaps_Renderer();
        $this->index = new WP_Sitemaps_Index($this->registry);
        $this->register_sitemaps();
    }

    public function init()
    {
    }

    public function sitemaps_enabled()
    {
        return (bool) apply_filters('wp_sitemaps_enabled', (bool) get_option('blog_public'));
    }

    public function register_sitemaps()
    {
        $providers = ['posts' => new WP_Sitemaps_Posts(), 'taxonomies' => new WP_Sitemaps_Taxonomies(), 'users' => new WP_Sitemaps_Users()];
        foreach ($providers as $name => $provider) {
            $this->registry->add_provider($name, $provider);
        }
    }

    public function register_rewrites()
    {
    }

    public function render_sitemaps()
    {
    }

    public function redirect_sitemapxml($bypass, $query)
    {
        return $bypass;
    }

    public function add_robots($output, $public)
    {
        if (!$public) {
            return $output;
        }
        return $output . "\nSitemap: " . esc_url($this->index->get_index_url()) . "\n";
    }
}

#[AllowDynamicProperties]
class WP_Sitemaps_Registry
{
    private $providers = [];

    public function add_provider($name, WP_Sitemaps_Provider $provider)
    {
        if (isset($this->providers[$name])) {
            return false;
        }
        $provider = apply_filters('wp_sitemaps_add_provider', $provider, $name);
        if (!$provider instanceof WP_Sitemaps_Provider) {
            return false;
        }
        $this->providers[$name] = $provider;
        return true;
    }

    public function get_provider($name)
    {
        return $this->providers[$name] ?? null;
    }

    public function get_providers()
    {
        return $this->providers;
    }
}

#[AllowDynamicProperties]
class WP_Sitemaps_Index
{
    private $registry;
    private const MAX_SITEMAPS = 50000;

    public function __construct(WP_Sitemaps_Registry $registry)
    {
        $this->registry = $registry;
    }

    public function get_sitemap_list()
    {
        $list = [];
        foreach ($this->registry->get_providers() as $provider) {
            foreach ($provider->get_sitemap_entries() as $entry) {
                $list[] = $entry;
                if (count($list) >= self::MAX_SITEMAPS) {
                    return $list;
                }
            }
        }
        return $list;
    }

    public function get_index_url()
    {
        if (get_option('permalink_structure') === '') {
            return home_url('/?sitemap=index');
        }
        return home_url('/wp-sitemap.xml');
    }
}

#[AllowDynamicProperties]
class WP_Sitemaps_Renderer
{
    protected $stylesheet = '';
    protected $stylesheet_index = '';

    public function __construct()
    {
        $this->stylesheet = (string) $this->get_sitemap_stylesheet_url();
        $this->stylesheet_index = (string) $this->get_sitemap_index_stylesheet_url();
    }

    public function get_sitemap_stylesheet_url()
    {
        $url = get_option('permalink_structure') === '' ? home_url('/?sitemap-stylesheet=sitemap') : home_url('/wp-sitemap.xsl');
        return apply_filters('wp_sitemaps_stylesheet_url', $url);
    }

    public function get_sitemap_index_stylesheet_url()
    {
        $url = get_option('permalink_structure') === '' ? home_url('/?sitemap-stylesheet=index') : home_url('/wp-sitemap-index.xsl');
        return apply_filters('wp_sitemaps_stylesheet_index_url', $url);
    }

    public function render_index($sitemaps)
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo $this->get_sitemap_index_xml($sitemaps);
    }

    public function get_sitemap_index_xml($sitemaps)
    {
        return SitemapXml::index(array_map(static fn ($entry) => array_map('strval', (array) $entry), (array) $sitemaps), $this->stylesheet_index !== '' ? esc_url($this->stylesheet_index) : null);
    }

    public function render_sitemap($url_list)
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo $this->get_sitemap_xml($url_list);
    }

    public function get_sitemap_xml($url_list)
    {
        return SitemapXml::urlset(array_map(static fn ($entry) => array_map('strval', (array) $entry), (array) $url_list), $this->stylesheet !== '' ? esc_url($this->stylesheet) : null);
    }
}

#[AllowDynamicProperties]
abstract class WP_Sitemaps_Provider
{
    protected $name = '';
    protected $object_type = '';

    abstract public function get_url_list($page_num, $object_subtype = '');

    abstract public function get_max_num_pages($object_subtype = '');

    public function get_sitemap_type_data()
    {
        $data = [];
        $subtypes = $this->get_object_subtypes();
        if (empty($subtypes)) {
            $subtypes = [''];
        }
        foreach ($subtypes as $subtype) {
            $name = is_object($subtype) ? $subtype->name : (string) $subtype;
            $data[] = ['name' => $name, 'pages' => $this->get_max_num_pages($name)];
        }
        return $data;
    }

    public function get_sitemap_entries()
    {
        $entries = [];
        foreach ($this->get_sitemap_type_data() as $type) {
            for ($page = 1; $page <= $type['pages']; $page++) {
                $entry = apply_filters('wp_sitemaps_index_entry', ['loc' => $this->get_sitemap_url($type['name'], $page)], $this->object_type, $type['name'], $page);
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    public function get_sitemap_url($name, $page)
    {
        $page = absint($page);
        if (get_option('permalink_structure') === '') {
            $args = ['sitemap' => $this->name];
            if ($name !== '') {
                $args['sitemap-subtype'] = $name;
            }
            $args['paged'] = $page;
            return add_query_arg($args, home_url('/'));
        }
        $basename = sprintf('/wp-sitemap-%1$s.xml', implode('-', array_filter([$this->name, $name, (string) $page])));
        return home_url($basename);
    }

    public function get_object_subtypes()
    {
        return [];
    }
}

class WP_Sitemaps_Posts extends WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'posts';
        $this->object_type = 'post';
    }

    public function get_object_subtypes()
    {
        $types = get_post_types(['public' => true], 'objects');
        unset($types['attachment']);
        $types = array_filter($types, 'is_post_type_viewable');
        return apply_filters('wp_sitemaps_post_types', $types);
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        $post_type = $object_subtype;
        $urls = apply_filters('wp_sitemaps_posts_pre_url_list', null, $post_type, $page_num);
        if (is_array($urls)) {
            return $urls;
        }
        $query = new WP_Query(['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => wp_sitemaps_get_max_urls($this->object_type), 'paged' => $page_num, 'orderby' => 'ID', 'order' => 'ASC', 'has_password' => false, 'no_found_rows' => true, 'ignore_sticky_posts' => true]);
        $urls = [];
        if ($post_type === 'page' && $page_num === 1 && get_option('show_on_front') === 'posts') {
            $urls[] = apply_filters('wp_sitemaps_posts_show_on_front_entry', ['loc' => home_url('/')]);
        }
        foreach ($query->posts as $post) {
            $urls[] = apply_filters('wp_sitemaps_posts_entry', ['loc' => get_permalink($post)], $post, $post_type);
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        if ($object_subtype === '') {
            return 0;
        }
        $max = apply_filters('wp_sitemaps_posts_pre_max_num_pages', null, $object_subtype);
        if ($max !== null) {
            return $max;
        }
        $query = new WP_Query(['fields' => 'ids', 'post_type' => $object_subtype, 'post_status' => 'publish', 'posts_per_page' => wp_sitemaps_get_max_urls($this->object_type), 'paged' => 1, 'has_password' => false, 'ignore_sticky_posts' => true]);
        $pages = (int) $query->max_num_pages;
        if ($object_subtype === 'page' && get_option('show_on_front') === 'posts' && $pages === 0) {
            $pages = 1;
        }
        return $pages;
    }
}

class WP_Sitemaps_Taxonomies extends WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'taxonomies';
        $this->object_type = 'term';
    }

    public function get_object_subtypes()
    {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $taxonomies = array_filter($taxonomies, 'is_taxonomy_viewable');
        return apply_filters('wp_sitemaps_taxonomies', $taxonomies);
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        $urls = apply_filters('wp_sitemaps_taxonomies_pre_url_list', null, $object_subtype, $page_num);
        if (is_array($urls)) {
            return $urls;
        }
        $terms = get_terms(['taxonomy' => $object_subtype, 'hide_empty' => true, 'number' => wp_sitemaps_get_max_urls($this->object_type), 'offset' => ($page_num - 1) * wp_sitemaps_get_max_urls($this->object_type), 'orderby' => 'term_order', 'fields' => 'ids', 'update_term_meta_cache' => false]);
        $urls = [];
        foreach (is_array($terms) ? $terms : [] as $term_id) {
            $link = get_term_link((int) $term_id, $object_subtype);
            if (is_wp_error($link)) {
                continue;
            }
            $urls[] = apply_filters('wp_sitemaps_taxonomies_entry', ['loc' => $link], $term_id, $object_subtype, get_term($term_id, $object_subtype));
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        if ($object_subtype === '') {
            return 0;
        }
        $max = apply_filters('wp_sitemaps_taxonomies_pre_max_num_pages', null, $object_subtype);
        if ($max !== null) {
            return $max;
        }
        $count = wp_count_terms(['taxonomy' => $object_subtype, 'hide_empty' => true]);
        return (int) ceil((int) $count / wp_sitemaps_get_max_urls($this->object_type));
    }
}

class WP_Sitemaps_Users extends WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'users';
        $this->object_type = 'user';
    }

    public function get_url_list($page_num, $object_subtype = '')
    {
        $urls = apply_filters('wp_sitemaps_users_pre_url_list', null, $page_num);
        if (is_array($urls)) {
            return $urls;
        }
        $urls = [];
        foreach ($this->get_users_query($page_num) as $user) {
            $urls[] = apply_filters('wp_sitemaps_users_entry', ['loc' => get_author_posts_url($user->ID)], $user);
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        $max = apply_filters('wp_sitemaps_users_pre_max_num_pages', null);
        if ($max !== null) {
            return $max;
        }
        return (int) ceil(count($this->get_users_query(0)) / wp_sitemaps_get_max_urls($this->object_type));
    }

    private function get_users_query($page_num)
    {
        $public_post_types = array_values(get_post_types(['public' => true]));
        $public_post_types = array_filter($public_post_types, static fn ($t) => $t !== 'attachment' && post_type_supports($t, 'author'));
        $users = get_users(['has_published_posts' => $public_post_types, 'orderby' => 'ID', 'order' => 'ASC', 'number' => $page_num > 0 ? wp_sitemaps_get_max_urls($this->object_type) : -1, 'paged' => max(1, (int) $page_num)]);
        return $users;
    }
}
