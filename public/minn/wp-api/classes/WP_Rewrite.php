<?php

use Minn\Runtime\Runtime;

/**
 * The rewrite registry plugin code adds rules and tags to, and the rules
 * WordPress makes from it (Runtime\RewriteRules), which plugins read,
 * filter and add to. The engine resolves URLs from its own structure and
 * the rules plugins change; flushing writes nothing.
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
        $this->use_verbose_rules = false;
        $this->use_verbose_page_rules = Minn\Runtime\RewriteRules::verbosePages($this->permalink_structure);
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
        $this->add_rewrite_tag('%pagename%', '(.?.+?)', 'pagename=');
        return $this->generate_rewrite_rules($this->get_page_permastruct(), EP_PAGES, true, true, false, false);
    }

    public function get_date_permastruct()
    {
        if (empty($this->permalink_structure)) {
            return false;
        }
        $endians = ['%year%/%monthnum%/%day%', '%day%/%monthnum%/%year%', '%monthnum%/%day%/%year%'];
        $date = '%year%/%monthnum%/%day%';
        foreach ($endians as $endian) {
            if (str_contains($this->permalink_structure, $endian)) {
                $date = $endian;
                break;
            }
        }
        // A post id among the structure's first three tags would read as a date, so dates move under date/ (probe rewrite-generate).
        preg_match_all('/%.+?%/', $this->permalink_structure, $tokens);
        $front = in_array('%post_id%', array_slice($tokens[0], 0, 3), true) ? $this->front . 'date/' : $this->front;
        return $front . $date;
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
        return Minn\Runtime\RewriteRules::generate($this, ['struct' => (string) $permalink_structure, 'ep_mask' => (int) $ep_mask, 'paged' => (bool) $paged, 'feed' => (bool) $feed, 'forcomments' => (bool) $forcomments, 'walk_dirs' => (bool) $walk_dirs, 'endpoints' => (bool) $endpoints]);
    }

    public function generate_rewrite_rule($permalink_structure, $walk_dirs = false)
    {
        return $this->generate_rewrite_rules($permalink_structure, EP_NONE, false, false, false, $walk_dirs);
    }

    public function rewrite_rules()
    {
        return Minn\Runtime\RewriteRules::all($this);
    }

    /** The stored rules; made and stored when there are none (probe rewrite-flush). */
    public function wp_rewrite_rules()
    {
        $this->rules = get_option('rewrite_rules');
        if (empty($this->rules)) {
            $this->refresh_rewrite_rules();
        }
        return $this->rules;
    }

    /** The rules made in their stored form ($matches[N]) and stored, once everything has registered. */
    private function refresh_rewrite_rules()
    {
        $this->rules = '';
        $this->matches = 'matches';
        $this->rewrite_rules();
        if (!did_action('wp_loaded')) {
            add_action('wp_loaded', [$this, 'flush_rules']);
            return;
        }
        update_option('rewrite_rules', $this->rules);
    }

    /**
     * The .htaccess block Apache needs for pretty permalinks (probe
     * facade-stubs): the Authorization header passed through, the home's
     * base, the external rules, then everything that is not a file or a
     * folder sent to index.php; "" without pretty permalinks.
     */
    public function mod_rewrite_rules()
    {
        if (!$this->using_permalinks()) {
            return '';
        }
        $home = (string) (parse_url(home_url(), PHP_URL_PATH) ?? '');
        $home = $home === '' ? '/' : trailingslashit($home);
        $rules = "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\nRewriteBase {$home}\nRewriteRule ^index\\.php$ - [L]\n";
        foreach ((array) $this->non_wp_rules as $match => $query) {
            $rules .= 'RewriteRule ^' . str_replace('.+?', '.+', (string) $match) . ' ' . $home . $query . " [QSA,L]\n";
        }
        $rules .= "RewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . {$home}{$this->index} [L]\n</IfModule>\n";
        $rules = apply_filters('mod_rewrite_rules', $rules);
        return apply_filters_deprecated('rewrite_rules', [$rules], '1.5.0', 'mod_rewrite_rules');
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

    /**
     * The stored rules made again (once everything has registered); a hard
     * flush asks flush_rewrite_rules_hard, and the server's own files are
     * the host's, so none is written (probe rewrite-flush).
     */
    public function flush_rules($hard = true)
    {
        static $hardLater = null;
        if (!did_action('wp_loaded')) {
            add_action('wp_loaded', [$this, 'flush_rules']);
            $hardLater = isset($hardLater) ? $hardLater || $hard : $hard;
            return;
        }
        if (isset($hardLater)) {
            [$hard, $hardLater] = [$hardLater, null];
        }
        delete_option('rewrite_rules');
        $this->wp_rewrite_rules();
        if ($hard) {
            apply_filters('flush_rewrite_rules_hard', true);
        }
    }

    public function set_permalink_structure($permalink_structure)
    {
        if ($permalink_structure !== $this->permalink_structure) {
            $old = $this->permalink_structure;
            update_option('permalink_structure', $permalink_structure);
            $this->init();
            // Links made after the change take the new structure, as the reference's read the option each time.
            if (Runtime::current()->get('permalinks') !== null) {
                Runtime::current()->set('permalinks', Minn\Front\Permalinks::fromDb(Runtime::current()->db));
            }
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
    // A new WP knows the reference's own vars; registration (a taxonomy's, REST, sitemaps) adds to the global one (probe wp-request).
    public $public_query_vars = ['m', 'p', 'posts', 'w', 'cat', 'withcomments', 'withoutcomments', 's', 'search', 'exact', 'sentence', 'calendar', 'page', 'paged', 'more', 'tb', 'pb', 'author', 'order', 'orderby', 'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'name', 'category_name', 'tag', 'feed', 'author_name', 'pagename', 'page_id', 'error', 'attachment', 'attachment_id', 'subpost', 'subpost_id', 'preview', 'robots', 'favicon', 'taxonomy', 'term', 'cpage', 'post_type', 'embed'];
    public $private_query_vars = ['offset', 'posts_per_page', 'posts_per_archive_page', 'showposts', 'nopaging', 'post_type', 'post_status', 'category__in', 'category__not_in', 'category__and', 'tag__in', 'tag__not_in', 'tag__and', 'tag_slug__in', 'tag_slug__and', 'tag_id', 'post_mime_type', 'perm', 'comments_per_page', 'post__in', 'post__not_in', 'post_parent', 'post_parent__in', 'post_parent__not_in', 'title', 'fields'];
    public $extra_query_vars = [];
    public $query_vars = [];
    public $query_string = '';
    public $request = '';
    public $matched_rule = '';
    public $matched_query = '';
    public $did_permalink = false;

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

    /**
     * The request read from its address and query string (probe
     * wp-request): the rule the path fits (none: error 404), the public
     * vars from the extra vars, the query string, the form and the rule in
     * that order, the extra private vars after them; through
     * do_parse_request, query_vars, request and parse_request
     * (Theme\FrontLifecycle). A public var the query string and the form
     * disagree on is refused.
     */
    public function parse_request($extra_query_vars = '')
    {
        $extra = is_array($extra_query_vars) ? $extra_query_vars : [];
        if (is_string($extra_query_vars) && $extra_query_vars !== '') {
            parse_str($extra_query_vars, $extra);
        }
        return Minn\Theme\FrontLifecycle::parseRequest($this, [], [], fn (array $public): array => $this->minn_parse($public, $extra));
    }

    /** @internal the parse itself, once no plugin has taken it over */
    private function minn_parse(array $public, array $extra): array
    {
        $this->extra_query_vars = $extra;
        $match = Minn\Front\RuleTable::match((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $this->request = $match === null ? '' : $match['request'];
        $this->did_permalink = $this->request !== '';
        $this->matched_rule = $this->did_permalink ? $match['rule'] : '';
        $this->matched_query = $this->did_permalink ? $match['query'] : '';
        if (Minn\Front\RequestParse::mismatch($public, $_GET, $_POST)) {
            _minn_die_variable_mismatch();
        }
        $rule = $this->did_permalink ? ($match['rule'] === '' ? ['error' => '404'] : $match['vars']) : [];
        $vars = Minn\Front\RequestParse::vars($rule, $public, $extra + $_GET + $_POST);
        foreach ((array) $this->private_query_vars as $var) {
            if (isset($extra[$var])) {
                $vars[$var] = $extra[$var];
            }
        }
        return $vars;
    }

    /** The headers for the request (Theme\FrontLifecycle::sendHeaders): through wp_headers, then send_headers. */
    public function send_headers()
    {
        Minn\Theme\FrontLifecycle::sendHeaders($this);
    }

    /** The vars with a value, encoded; a query_string filter (deprecated, still honoured) rewrites both. */
    public function build_query_string()
    {
        $this->query_string = Minn\Front\RequestParse::queryString((array) $this->query_vars);
        if (has_filter('query_string')) {
            $this->query_string = (string) apply_filters('query_string', $this->query_string);
            parse_str($this->query_string, $this->query_vars);
        }
    }

    /** The main query's variables, posts and post as globals, as themes of every age read them. */
    public function register_globals()
    {
        $query = $GLOBALS['wp_query'] ?? null;
        if (!$query instanceof WP_Query) {
            return;
        }
        foreach ((array) $query->query_vars as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        $GLOBALS['query_string'] = $this->query_string;
        $GLOBALS['posts'] = &$query->posts;
        $GLOBALS['post'] = $query->post ?? null;
        $GLOBALS['request'] = $query->request;
        if ($query->is_single() || $query->is_page()) {
            $GLOBALS['more'] = 1;
            $GLOBALS['single'] = 1;
        }
        if ($query->is_author()) {
            $GLOBALS['authordata'] = get_userdata(get_queried_object_id());
        }
    }

    /** The current user, set up. */
    public function init()
    {
        wp_get_current_user();
    }

    /** The main query for the parsed vars, with the query string built from them. */
    public function query_posts()
    {
        $this->build_query_string();
        if (!($GLOBALS['wp_the_query'] ?? null) instanceof WP_Query) {
            $GLOBALS['wp_the_query'] = new WP_Query();
        }
        $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
        $GLOBALS['wp_the_query']->query($this->query_vars);
    }

    /**
     * The 404 decision on the main query (Theme\FrontLifecycle::handle404):
     * a single item the query did not find is one.
     */
    public function handle_404()
    {
        $query = $GLOBALS['wp_query'] ?? null;
        if ($query instanceof WP_Query) {
            Minn\Theme\FrontLifecycle::handle404($query, empty($query->posts) && $query->is_singular());
        }
    }

    /** The request whole, in the reference's order: parse, query, 404, globals, headers, then "wp". */
    public function main($query_args = '')
    {
        $this->init();
        if ($this->parse_request($query_args)) {
            $this->query_posts();
            $this->handle_404();
            $this->register_globals();
        }
        $this->send_headers();
        do_action_ref_array('wp', [&$this]);
    }
}
