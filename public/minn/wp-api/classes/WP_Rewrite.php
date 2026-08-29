<?php

use Minn\Runtime\Runtime;

/**
 * The rewrite registry plugin code adds rules and tags to. The engine
 * resolves URLs from its own structure, so these are recorded for the
 * plugins that read them back; flushing writes nothing.
 */
#[AllowDynamicProperties]
class WP_Rewrite
{
    public $permalink_structure;
    public $use_trailing_slashes;
    public $author_base = 'author';
    public $search_base = 'search';
    public $comments_base = 'comments';
    public $pagination_base = 'page';
    public $comments_pagination_base = 'comment-page';
    public $feed_base = 'feed';
    public $front;
    public $root = '';
    public $index = 'index.php';
    public $matches = '';
    public $rules;
    public $extra_rules = [];
    public $extra_rules_top = [];
    public $non_wp_rules = [];
    public $extra_permastructs = [];
    public $endpoints = [];
    public $use_verbose_rules = false;
    public $use_verbose_page_rules = true;
    public $rewritecode = ['%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%', '%postname%', '%post_id%', '%author%', '%pagename%', '%search%'];
    public $rewritereplace = ['([0-9]{4})', '([0-9]{1,2})', '([0-9]{1,2})', '([0-9]{1,2})', '([0-9]{1,2})', '([0-9]{1,2})', '([^/]+)', '([0-9]+)', '([^/]+)', '([^/]+?)', '(.+)'];
    public $queryreplace = ['year=', 'monthnum=', 'day=', 'hour=', 'minute=', 'second=', 'name=', 'p=', 'author_name=', 'pagename=', 's='];
    public $feeds = ['feed', 'rdf', 'rss', 'rss2', 'atom'];

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $this->permalink_structure = (string) get_option('permalink_structure');
        $this->extra_rules_top = [
            '^wp-json/?$' => 'index.php?rest_route=/',
            '^wp-json/(.*)?' => 'index.php?rest_route=/$matches[1]',
            '^index.php/wp-json/?$' => 'index.php?rest_route=/',
            '^index.php/wp-json/(.*)?' => 'index.php?rest_route=/$matches[1]',
            '^wp-sitemap\\.xml$' => 'index.php?sitemap=index',
            '^wp-sitemap\\.xsl$' => 'index.php?sitemap-stylesheet=sitemap',
            '^wp-sitemap-index\\.xsl$' => 'index.php?sitemap-stylesheet=index',
            '^wp-sitemap-([a-z]+?)-([a-z\\d_-]+?)-(\\d+?)\\.xml$' => 'index.php?sitemap=$matches[1]&sitemap-subtype=$matches[2]&paged=$matches[3]',
            '^wp-sitemap-([a-z]+?)-(\\d+?)\\.xml$' => 'index.php?sitemap=$matches[1]&paged=$matches[2]',
            '^minn-admin(/.*)?$' => 'index.php?minn_admin=1',
        ];
        $this->front = substr($this->permalink_structure, 0, (int) strpos($this->permalink_structure, '%'));
        $this->root = '';
        if ($this->using_index_permalinks()) {
            $this->root = $this->index . '/';
        }
        unset($this->author_structure, $this->date_structure, $this->page_structure, $this->search_structure, $this->feed_structure, $this->comment_feed_structure);
        $this->use_trailing_slashes = str_ends_with($this->permalink_structure, '/');
        $this->extra_permastructs = [
            'category' => ['with_front' => true, 'ep_mask' => EP_CATEGORIES, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true, 'struct' => $this->front . 'category/%category%'],
            'post_tag' => ['with_front' => true, 'ep_mask' => EP_TAGS, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true, 'struct' => $this->front . 'tag/%post_tag%'],
            'post_format' => ['with_front' => true, 'ep_mask' => EP_NONE, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true, 'struct' => $this->front . 'type/%post_format%'],
        ];
    }

    public function using_permalinks()
    {
        return !empty($this->permalink_structure);
    }

    public function using_index_permalinks()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return preg_match('#^/*' . $this->index . '#', $this->permalink_structure);
    }

    public function using_mod_rewrite_permalinks()
    {
        return $this->using_permalinks() && !$this->using_index_permalinks();
    }

    public function preg_index($number)
    {
        $match_prefix = '$';
        $match_suffix = '';
        if (!empty($this->matches)) {
            $match_prefix = '$' . $this->matches . '[';
            $match_suffix = ']';
        }
        return "{$match_prefix}{$number}{$match_suffix}";
    }

    public function page_uri_index()
    {
        return [[], []];
    }

    public function page_rewrite_rules()
    {
        return [];
    }

    public function get_date_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        $endians = ['%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%'];
        $date_structure = '';
        foreach ($endians as $endian) {
            if (str_contains($this->permalink_structure, $endian)) {
                $date_structure = $this->front . $endian;
                break;
            }
        }
        if ($date_structure === '') {
            $date_structure = $this->front . '%year%/%monthnum%/%day%';
        }
        return $date_structure;
    }

    public function get_year_permastruct()
    {
        $structure = $this->get_date_permastruct();
        return $structure === false ? false : str_replace(['%monthnum%', '%day%'], '', $structure);
    }

    public function get_month_permastruct()
    {
        $structure = $this->get_date_permastruct();
        return $structure === false ? false : str_replace('%day%', '', $structure);
    }

    public function get_day_permastruct()
    {
        return $this->get_date_permastruct();
    }

    public function get_category_permastruct()
    {
        return $this->get_extra_permastruct('category');
    }

    public function get_tag_permastruct()
    {
        return $this->get_extra_permastruct('post_tag');
    }

    public function get_extra_permastruct($name)
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        if (isset($this->extra_permastructs[$name])) {
            return $this->extra_permastructs[$name]['struct'];
        }
        return false;
    }

    public function get_author_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return $this->front . $this->author_base . '/%author%';
    }

    public function get_search_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return $this->root . $this->search_base . '/%search%';
    }

    public function get_page_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return $this->root . '%pagename%';
    }

    public function get_feed_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return $this->root . $this->feed_base . '/%feed%';
    }

    public function get_comment_feed_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        return $this->root . $this->comments_base . '/' . $this->feed_base . '/%feed%';
    }

    public function add_rewrite_tag($tag, $regex, $query)
    {
        $position = array_search($tag, $this->rewritecode, true);
        if ($position !== false) {
            $this->rewritereplace[$position] = $regex;
            $this->queryreplace[$position] = $query;
        } else {
            $this->rewritecode[] = $tag;
            $this->rewritereplace[] = $regex;
            $this->queryreplace[] = $query;
        }
    }

    public function remove_rewrite_tag($tag)
    {
        $position = array_search($tag, $this->rewritecode, true);
        if ($position !== false) {
            unset($this->rewritecode[$position], $this->rewritereplace[$position], $this->queryreplace[$position]);
            $this->rewritecode = array_values($this->rewritecode);
            $this->rewritereplace = array_values($this->rewritereplace);
            $this->queryreplace = array_values($this->queryreplace);
        }
    }

    public function generate_rewrite_rules($permalink_structure, $ep_mask = EP_NONE, $paged = true, $feed = true, $forcomments = false, $walk_dirs = true, $endpoints = true)
    {
        return [];
    }

    public function generate_rewrite_rule($permalink_structure, $walk_dirs = false)
    {
        return [];
    }

    public function rewrite_rules()
    {
        return array_merge($this->extra_rules_top, $this->extra_rules);
    }

    public function wp_rewrite_rules()
    {
        $this->rules = $this->rewrite_rules();
        return $this->rules;
    }

    public function mod_rewrite_rules()
    {
        return '';
    }

    public function iis7_url_rewrite_rules($add_parent_tag = false)
    {
        return '';
    }

    public function add_rule($regex, $query, $after = 'bottom')
    {
        if (is_array($query)) {
            $external = false;
            $query = add_query_arg($query, 'index.php');
        } else {
            $index = str_contains($query, '?') ? strpos($query, '?') : false;
            $front = $index === false ? $query : substr($query, 0, $index);
            $external = $front !== $this->index;
        }
        if ($external) {
            $this->add_external_rule($regex, $query);
        } elseif ($after === 'bottom') {
            $this->extra_rules = array_merge($this->extra_rules, [$regex => $query]);
        } else {
            $this->extra_rules_top = array_merge($this->extra_rules_top, [$regex => $query]);
        }
    }

    public function add_external_rule($regex, $query)
    {
        $this->non_wp_rules[$regex] = $query;
    }

    public function add_endpoint($name, $places, $query_var = true)
    {
        if ($query_var === true) {
            $query_var = $name;
        }
        $this->endpoints[] = [$places, $name, $query_var];
        if ($query_var) {
            $GLOBALS['wp']->add_query_var($query_var);
        }
    }

    public function add_permastruct($name, $struct, $args = [])
    {
        if (!is_array($args)) {
            $args = ['with_front' => $args];
        }
        $defaults = ['with_front' => true, 'ep_mask' => EP_NONE, 'paged' => true, 'feed' => true, 'forcomments' => false, 'walk_dirs' => true, 'endpoints' => true];
        $args = array_intersect_key($args, $defaults);
        $args = wp_parse_args($args, $defaults);
        if ($args['with_front']) {
            $struct = $this->front . $struct;
        } else {
            $struct = $this->root . $struct;
        }
        $args['struct'] = $struct;
        $this->extra_permastructs[$name] = $args;
    }

    public function remove_permastruct($name)
    {
        unset($this->extra_permastructs[$name]);
    }

    public function flush_rules($hard = true)
    {
        $this->rules = $this->rewrite_rules();
        do_action('flush_rewrite_rules');
    }

    public function set_permalink_structure($permalink_structure)
    {
        if ($permalink_structure !== $this->permalink_structure) {
            $old = $this->permalink_structure;
            update_option('permalink_structure', $permalink_structure);
            $this->init();
            do_action('permalink_structure_changed', $old, $permalink_structure);
        }
    }

    public function set_category_base($category_base)
    {
        update_option('category_base', $category_base);
        $this->init();
    }

    public function set_tag_base($tag_base)
    {
        update_option('tag_base', $tag_base);
        $this->init();
    }
}

/** The request object plugin code reads query vars from. */
#[AllowDynamicProperties]
class WP
{
    public $public_query_vars;
    public $private_query_vars;
    public $extra_query_vars = [];
    public $query_vars = [];
    public $query_string = '';
    public $request = '';
    public $matched_rule = '';
    public $matched_query = '';
    public $did_permalink = false;

    public function __construct()
    {
        $data = json_decode((string) file_get_contents(Runtime::current()->engineDir . '/data/registry.json'), true) ?: [];
        $this->public_query_vars = $data['public_query_vars'] ?? [];
        $this->private_query_vars = $data['private_query_vars'] ?? [];
    }

    public function add_query_var($qv)
    {
        if (!in_array($qv, $this->public_query_vars, true)) {
            $this->public_query_vars[] = $qv;
        }
    }

    public function remove_query_var($name)
    {
        $this->public_query_vars = array_values(array_diff($this->public_query_vars, [$name]));
    }

    public function set_query_var($key, $value)
    {
        $this->query_vars[$key] = $value;
    }

    public function parse_request($extra_query_vars = '')
    {
        return true;
    }

    public function send_headers()
    {
    }

    public function build_query_string()
    {
    }

    public function register_globals()
    {
    }

    public function init()
    {
    }

    public function query_posts()
    {
    }

    public function handle_404()
    {
    }

    public function main($query_args = '')
    {
    }
}
