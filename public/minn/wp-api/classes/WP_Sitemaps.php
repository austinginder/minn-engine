<?php

use Minn\Front\SitemapRequest;
use Minn\Front\Sitemaps;
use Minn\Front\SitemapXml;

/**
 * The sitemaps server: registry, renderer, index. Built on init (the
 * stylesheet addresses asked for, then whether sitemaps are on: only then
 * are the providers registered and robots.txt told), it answers sitemap
 * requests at template_redirect.
 */
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
    }

    public function init()
    {
        $this->register_rewrites();
        add_action('template_redirect', [$this, 'render_sitemaps']);
        if (!$this->sitemaps_enabled()) {
            return;
        }
        $this->register_sitemaps();
        add_filter('robots_txt', [$this, 'add_robots'], 0, 2);
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
        SitemapRequest::serve($this);
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

/** The two stylesheets a browser opening a sitemap is handed: the engine's own, through the reference's filters. */
#[AllowDynamicProperties]
class WP_Sitemaps_Stylesheet
{
    public function render_stylesheet($type)
    {
        header('Content-Type: application/xml; charset=UTF-8');
        if ($type === 'sitemap') {
            echo $this->get_sitemap_stylesheet();
        }
        if ($type === 'index') {
            echo $this->get_sitemap_index_stylesheet();
        }
    }

    public function get_sitemap_stylesheet()
    {
        return apply_filters('wp_sitemaps_stylesheet_content', Sitemaps::stylesheet($this->get_stylesheet_css()));
    }

    public function get_sitemap_index_stylesheet()
    {
        return apply_filters('wp_sitemaps_stylesheet_index_content', Sitemaps::indexStylesheet($this->get_stylesheet_css()));
    }

    public function get_stylesheet_css()
    {
        return apply_filters('wp_sitemaps_stylesheet_css', Sitemaps::CSS);
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

/** Published posts of each public type, by id, each dated; the pages lead with the front page when it lists posts. */
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
        $urls = apply_filters('wp_sitemaps_posts_pre_url_list', null, $object_subtype, $page_num);
        if ($urls !== null) {
            return $urls;
        }
        $args = $this->get_posts_query_args($object_subtype);
        $args['paged'] = $page_num;
        $urls = $object_subtype === 'page' && (int) $page_num === 1 && get_option('show_on_front') === 'posts'
            ? [apply_filters('wp_sitemaps_posts_show_on_front_entry', ['loc' => home_url('/'), 'lastmod' => Sitemaps::w3c((string) get_lastpostmodified('gmt'))])]
            : [];
        foreach ((new WP_Query($args))->posts as $post) {
            $urls[] = apply_filters('wp_sitemaps_posts_entry', ['loc' => get_permalink($post), 'lastmod' => Sitemaps::w3c((string) $post->post_modified_gmt)], $post, $object_subtype);
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        if (empty($object_subtype)) {
            return 0;
        }
        $max = apply_filters('wp_sitemaps_posts_pre_max_num_pages', null, $object_subtype);
        if ($max !== null) {
            return $max;
        }
        $args = ['fields' => 'ids', 'no_found_rows' => false] + $this->get_posts_query_args($object_subtype);
        $pages = (int) (new WP_Query($args))->max_num_pages;
        return $object_subtype === 'page' && get_option('show_on_front') === 'posts' ? max(1, $pages) : $pages;
    }

    protected function get_posts_query_args($post_type)
    {
        $args = ['orderby' => 'ID', 'order' => 'ASC', 'post_type' => $post_type, 'posts_per_page' => wp_sitemaps_get_max_urls($this->object_type), 'post_status' => ['publish'], 'no_found_rows' => true, 'update_post_term_cache' => false, 'update_post_meta_cache' => false, 'ignore_sticky_posts' => true];
        return apply_filters('wp_sitemaps_posts_query_args', $args, $post_type);
    }
}

/** The terms of each public taxonomy that have posts, a page of them at a time. */
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
        if ($urls !== null) {
            return $urls;
        }
        $args = ['fields' => 'ids', 'offset' => ((int) $page_num - 1) * wp_sitemaps_get_max_urls($this->object_type)] + $this->get_taxonomies_query_args($object_subtype);
        $urls = [];
        foreach ((array) (new WP_Term_Query($args))->terms as $id) {
            $link = get_term_link((int) $id, $object_subtype);
            if (!is_wp_error($link)) {
                $urls[] = apply_filters('wp_sitemaps_taxonomies_entry', ['loc' => $link], (int) $id, $object_subtype, get_term((int) $id, $object_subtype));
            }
        }
        return $urls;
    }

    public function get_max_num_pages($object_subtype = '')
    {
        if (empty($object_subtype)) {
            return 0;
        }
        $max = apply_filters('wp_sitemaps_taxonomies_pre_max_num_pages', null, $object_subtype);
        if ($max !== null) {
            return $max;
        }
        return (int) ceil((int) wp_count_terms($this->get_taxonomies_query_args($object_subtype)) / wp_sitemaps_get_max_urls($this->object_type));
    }

    protected function get_taxonomies_query_args($taxonomy)
    {
        $args = ['taxonomy' => $taxonomy, 'orderby' => 'term_order', 'number' => wp_sitemaps_get_max_urls($this->object_type), 'hide_empty' => true, 'hierarchical' => false, 'update_term_meta_cache' => false];
        return apply_filters('wp_sitemaps_taxonomies_query_args', $args, $taxonomy);
    }
}

/** The people with published posts of a public type other than pages, by login. */
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
        if ($urls !== null) {
            return $urls;
        }
        $urls = [];
        foreach ((new WP_User_Query(['paged' => $page_num] + $this->get_users_query_args()))->get_results() as $user) {
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
        return (int) ceil((new WP_User_Query($this->get_users_query_args()))->get_total() / wp_sitemaps_get_max_urls($this->object_type));
    }

    protected function get_users_query_args()
    {
        $types = get_post_types(['public' => true]);
        unset($types['attachment'], $types['page']);
        return apply_filters('wp_sitemaps_users_query_args', ['has_published_posts' => array_keys($types), 'number' => wp_sitemaps_get_max_urls($this->object_type)]);
    }
}
