<?php

use Minn\Query\PostOrder;
use Minn\Query\PostSearch;
use Minn\Runtime\PostQueryTax;
use Minn\Runtime\QueriedObject;
use Minn\Runtime\QueryFlags;
use Minn\Runtime\Runtime;

/**
 * The query object plugin code builds and loops over. Variables are filled
 * from the reference's own template (data/registry.json), the conditional
 * flags derive from them, and Minn\Runtime\PostQuery runs get_posts as the
 * reference does: its clauses, its filters, its request.
 */
#[AllowDynamicProperties]
class WP_Query
{
    public $query;
    public $query_vars = [];
    public $tax_query;
    public $meta_query = false;
    public $date_query = false;
    public $queried_object;
    public $queried_object_id;
    public $request;
    public $posts;
    public $post_count = 0;
    public $current_post = -1;
    public $before_loop = true;
    public $in_the_loop = false;
    public $post;
    public $comments;
    public $comment_count = 0;
    public $current_comment = -1;
    public $comment;
    public $found_posts = 0;
    public $max_num_pages = 0;
    public $max_num_comment_pages = 0;
    public $is_single = false;
    public $is_preview = false;
    public $is_page = false;
    public $is_archive = false;
    public $is_date = false;
    public $is_year = false;
    public $is_month = false;
    public $is_day = false;
    public $is_time = false;
    public $is_author = false;
    public $is_category = false;
    public $is_tag = false;
    public $is_tax = false;
    public $is_search = false;
    public $is_feed = false;
    public $is_comment_feed = false;
    public $is_trackback = false;
    public $is_home = false;
    public $is_privacy_policy = false;
    public $is_404 = false;
    public $is_embed = false;
    public $is_paged = false;
    public $is_admin = false;
    public $is_attachment = false;
    public $is_singular = false;
    public $is_robots = false;
    public $is_favicon = false;
    public $is_sitemap = false;
    public $is_posts_page = false;
    public $is_post_type_archive = false;
    public $query_vars_hash = false;
    public $query_vars_changed = true;
    public $thumbnails_cached = false;
    public $allow_query_attachment_by_filename = false;

    public function __construct($query = '')
    {
        if (!empty($query)) {
            $this->query($query);
        }
    }

    public function init()
    {
        $this->query = null;
        $this->query_vars = [];
        $this->queried_object = null;
        $this->queried_object_id = null;
        $this->post_count = 0;
        $this->current_post = -1;
        $this->in_the_loop = false;
        $this->before_loop = true;
        $this->post = null;
        $this->posts = null;
        $this->found_posts = 0;
        $this->max_num_pages = 0;
        $this->init_query_flags();
    }

    private function init_query_flags()
    {
        foreach (get_object_vars($this) as $key => $value) {
            if (str_starts_with($key, 'is_')) {
                $this->{$key} = false;
            }
        }
    }

    public function fill_query_vars($query_vars)
    {
        return QueryFlags::fill((array) $query_vars, Runtime::registry());
    }

    public function parse_query_vars()
    {
        $this->parse_query();
    }

    public function parse_query($query = '')
    {
        if (!empty($query)) {
            $this->init();
            $this->query = wp_parse_args($query);
            $this->query_vars = $this->query;
        } elseif (!isset($this->query)) {
            $this->query = $this->query_vars;
        }
        $this->query_vars_changed = true;
        $this->init_query_flags();
        $derived = QueryFlags::derive($this->fill_query_vars($this->query_vars), Runtime::registry(), static fn (string $name) => get_option($name));
        $this->query_vars = $derived->vars;
        foreach ($derived->flags as $flag => $on) {
            $this->{$flag} = $on;
        }
        $this->is_admin = is_admin();
        if (!$this->is_singular) {
            $this->parse_tax_query($this->query_vars);
        }
        if ($this->query_vars['pagename'] !== '') {
            $this->locate_page_path();
        }
        $this->query_vars_hash = md5(serialize($this->query_vars));
        $this->query_vars_changed = false;
        do_action_ref_array('parse_query', [&$this]);
    }

    /** A page path's page is the queried object; the posts page makes the query the home listing. */
    private function locate_page_path()
    {
        $page = get_page_by_path($this->query_vars['pagename']);
        if ($page instanceof WP_Post) {
            $this->queried_object = $page;
            $this->queried_object_id = (int) $page->ID;
        }
        if ($this->queried_object_id !== null && get_option('show_on_front') === 'page' && (int) get_option('page_for_posts') === $this->queried_object_id) {
            // The posts page is the blog: no longer singular, so its feed is the posts' unless comments were asked for.
            $this->is_page = false;
            $this->is_home = true;
            $this->is_posts_page = true;
            $this->is_singular = false;
            $this->is_comment_feed = $this->is_feed && !empty($this->query_vars['withcomments']);
        }
        if ($this->queried_object_id !== null && (int) get_option('wp_page_for_privacy_policy') === $this->queried_object_id) {
            $this->is_privacy_policy = true;
        }
    }

    public function parse_tax_query(&$q)
    {
        $this->tax_query = new WP_Tax_Query(PostQueryTax::clauses($this, $q));
        do_action('parse_tax_query', $this);
    }

    protected function parse_search(&$q)
    {
        return Runtime::postQuery()->search($this, $q, $_GET['s'] ?? null);
    }

    protected function parse_search_terms($terms)
    {
        return PostSearch::checked((array) $terms, Runtime::postQuery()->stopwords());
    }

    protected function get_search_stopwords()
    {
        return Runtime::postQuery()->stopwords();
    }

    protected function parse_search_order(&$q)
    {
        return PostSearch::order($GLOBALS['wpdb']->posts, (string) $q['s'], (int) ($q['search_terms_count'] ?? 1), (array) ($q['search_orderby_title'] ?? []));
    }

    protected function parse_orderby($orderby)
    {
        return PostOrder::clause((string) $orderby, $GLOBALS['wpdb']->posts, $this->meta_query ? (array) $this->meta_query->get_clauses() : [], $this->query_vars);
    }

    protected function parse_order($order)
    {
        return PostOrder::direction($order);
    }

    public function query($query)
    {
        $this->init();
        $this->query = wp_parse_args($query);
        $this->query_vars = $this->query;
        return $this->get_posts();
    }

    public function get($query_var, $default_value = '')
    {
        return $this->query_vars[$query_var] ?? $default_value;
    }

    public function set($query_var, $value)
    {
        $this->query_vars[$query_var] = $value;
    }

    public function set_404()
    {
        [$feed, $embed] = [$this->is_feed, $this->is_embed];
        $this->init_query_flags();
        $this->is_404 = true;
        $this->is_feed = $feed;
        $this->is_embed = $embed;
        do_action_ref_array('set_404', [$this]);
    }

    public function get_posts()
    {
        global $wpdb;
        return Runtime::postQuery()->run($this, $wpdb, $_GET['s'] ?? null);
    }

    public function have_posts()
    {
        if ($this->current_post + 1 < $this->post_count) {
            return true;
        }
        if ($this->current_post + 1 === $this->post_count && $this->post_count > 0) {
            do_action_ref_array('loop_end', [&$this]);
            $this->rewind_posts();
        } elseif ($this->post_count === 0) {
            $this->before_loop = false;
            do_action_ref_array('loop_no_results', [&$this]);
        }
        $this->in_the_loop = false;
        return false;
    }

    public function next_post()
    {
        $this->current_post++;
        $this->before_loop = false;
        $this->post = $this->posts[$this->current_post];
        return $this->post;
    }

    public function the_post()
    {
        $this->in_the_loop = true;
        $this->before_loop = false;
        if ($this->current_post === -1) {
            do_action_ref_array('loop_start', [&$this]);
        }
        $post = $this->next_post();
        $this->setup_postdata($post);
    }

    public function rewind_posts()
    {
        $this->current_post = -1;
        if ($this->post_count > 0) {
            $this->post = $this->posts[0];
        }
    }

    public function setup_postdata($post)
    {
        $post = get_post($post);
        if ($post === null) {
            return false;
        }
        $GLOBALS['post'] = $post;
        Runtime::current()->set('post', $post);
        _minn_postdata_globals($post);
        do_action_ref_array('the_post', [&$post, &$this]);
        return true;
    }

    public function reset_postdata()
    {
        if (!empty($this->post)) {
            $GLOBALS['post'] = $this->post;
            $this->setup_postdata($this->post);
        }
    }

    public function get_queried_object()
    {
        if ($this->queried_object !== null) {
            return $this->queried_object;
        }
        $this->queried_object = null;
        $this->queried_object_id = null;
        $flags = array_filter(get_object_vars($this), static fn ($value, string $key) => str_starts_with($key, 'is_') && $value, ARRAY_FILTER_USE_BOTH);
        $where = QueriedObject::locate($this->query_vars, $flags, Runtime::registry(), static fn (string $name) => get_option($name));
        $object = match ($where->kind) {
            'term' => $where->field === 'id' ? get_term((int) $where->value, $where->taxonomy) : get_term_by($where->field, (string) $where->value, $where->taxonomy),
            'post_type' => get_post_type_object((string) $where->value),
            'posts_page' => get_post((int) $where->value),
            'post' => !empty($this->post) ? $this->post : null,
            'author' => $where->field === 'id' ? get_userdata((int) $where->value) : get_user_by('slug', (string) $where->value),
            default => null,
        };
        if ($where->kind === 'term' && !$object instanceof WP_Term) {
            $object = null;
        }
        if ($object instanceof WP_Term) {
            $this->queried_object_id = $object->term_id;
        } elseif ($object instanceof WP_Post || $object instanceof WP_User) {
            $this->queried_object_id = (int) $object->ID;
        }
        if ($where->kind === 'posts_page') {
            $this->queried_object_id = (int) $where->value;
        }
        $this->queried_object = $object ?: null;
        return $this->queried_object;
    }

    public function get_queried_object_id()
    {
        $this->get_queried_object();
        return $this->queried_object_id === null ? 0 : (int) $this->queried_object_id;
    }

    public function is_main_query()
    {
        return ($GLOBALS['wp_the_query'] ?? null) === $this;
    }

    public function is_archive()
    {
        return (bool) $this->is_archive;
    }

    public function is_post_type_archive($post_types = '')
    {
        if (!$this->is_post_type_archive) {
            return false;
        }
        if ($post_types === '') {
            return true;
        }
        return in_array($this->get('post_type'), (array) $post_types, true);
    }

    public function is_attachment($attachment = '')
    {
        if (!$this->is_attachment) {
            return false;
        }
        if ($attachment === '') {
            return true;
        }
        $post = $this->get_queried_object();
        return $post && in_array((string) $post->ID, array_map('strval', (array) $attachment), true) || in_array($post->post_name ?? '', (array) $attachment, true);
    }

    public function is_author($author = '')
    {
        if (!$this->is_author) {
            return false;
        }
        if ($author === '') {
            return true;
        }
        $user = $this->get_queried_object();
        return $user && (in_array((string) $user->ID, array_map('strval', (array) $author), true) || in_array($user->user_nicename, (array) $author, true) || in_array($user->display_name, (array) $author, true));
    }

    public function is_category($category = '')
    {
        return $this->is_term_query($this->is_category, $category);
    }

    public function is_tag($tag = '')
    {
        return $this->is_term_query($this->is_tag, $tag);
    }

    public function is_tax($taxonomy = '', $term = '')
    {
        if (!$this->is_tax) {
            return false;
        }
        if ($taxonomy === '' && $term === '') {
            return true;
        }
        $object = $this->get_queried_object();
        return $object instanceof WP_Term && in_array($object->taxonomy, (array) $taxonomy, true) && ($term === '' || in_array((string) $object->term_id, array_map('strval', (array) $term), true) || in_array($object->slug, (array) $term, true) || in_array($object->name, (array) $term, true));
    }

    private function is_term_query($flag, $value)
    {
        if (!$flag) {
            return false;
        }
        if ($value === '') {
            return true;
        }
        $term = $this->get_queried_object();
        if (!$term instanceof WP_Term) {
            return false;
        }
        $values = array_map('strval', (array) $value);
        return in_array((string) $term->term_id, $values, true) || in_array($term->slug, $values, true) || in_array($term->name, $values, true);
    }

    public function is_date()
    {
        return (bool) $this->is_date;
    }

    public function is_day()
    {
        return (bool) $this->is_day;
    }

    public function is_feed($feeds = '')
    {
        if (!$this->is_feed) {
            return false;
        }
        return $feeds === '' || in_array($this->get('feed'), (array) $feeds, true);
    }

    public function is_comment_feed()
    {
        return (bool) $this->is_comment_feed;
    }

    public function is_front_page()
    {
        if (get_option('show_on_front') === 'posts' && $this->is_home()) {
            return true;
        }
        return get_option('show_on_front') === 'page' && (int) get_option('page_on_front') > 0 && $this->is_page((int) get_option('page_on_front'));
    }

    public function is_home()
    {
        return (bool) $this->is_home;
    }

    public function is_privacy_policy()
    {
        return (bool) $this->is_privacy_policy;
    }

    public function is_month()
    {
        return (bool) $this->is_month;
    }

    public function is_page($page = '')
    {
        if (!$this->is_page) {
            return false;
        }
        if ($page === '') {
            return true;
        }
        $post = $this->get_queried_object();
        if (!$post instanceof WP_Post) {
            return false;
        }
        $values = array_map('strval', (array) $page);
        if (in_array((string) $post->ID, $values, true) || in_array($post->post_title, $values, true) || in_array($post->post_name, $values, true)) {
            return true;
        }
        foreach ($values as $value) {
            if (str_contains($value, '/') && get_page_uri($post) === trim($value, '/')) {
                return true;
            }
        }
        return false;
    }

    public function is_paged()
    {
        return (bool) $this->is_paged;
    }

    public function is_preview()
    {
        return (bool) $this->is_preview;
    }

    public function is_robots()
    {
        return (bool) $this->is_robots;
    }

    public function is_favicon()
    {
        return (bool) $this->is_favicon;
    }

    public function is_search()
    {
        return (bool) $this->is_search;
    }

    public function is_single($post = '')
    {
        if (!$this->is_single) {
            return false;
        }
        if ($post === '') {
            return true;
        }
        $object = $this->get_queried_object();
        if (!$object instanceof WP_Post) {
            return false;
        }
        $values = array_map('strval', (array) $post);
        return in_array((string) $object->ID, $values, true) || in_array($object->post_title, $values, true) || in_array($object->post_name, $values, true);
    }

    public function is_singular($post_types = '')
    {
        if (!$this->is_singular) {
            return false;
        }
        if ($post_types === '') {
            return true;
        }
        $object = $this->get_queried_object();
        return $object instanceof WP_Post && in_array($object->post_type, (array) $post_types, true);
    }

    public function is_time()
    {
        return (bool) $this->is_time;
    }

    public function is_trackback()
    {
        return (bool) $this->is_trackback;
    }

    public function is_year()
    {
        return (bool) $this->is_year;
    }

    public function is_404()
    {
        return (bool) $this->is_404;
    }

    public function is_embed()
    {
        return (bool) $this->is_embed;
    }

    public function is_sitemap()
    {
        return (bool) $this->is_sitemap;
    }

    public function have_comments()
    {
        if ($this->current_comment + 1 < $this->comment_count) {
            return true;
        }
        if ($this->current_comment + 1 === $this->comment_count) {
            $this->rewind_comments();
        }
        return false;
    }

    public function the_comment()
    {
        $GLOBALS['comment'] = $this->next_comment();
        if ($this->current_comment === 0) {
            do_action('comment_loop_start');
        }
    }

    public function next_comment()
    {
        ++$this->current_comment;
        $this->comment = $this->comments[$this->current_comment] ?? null;
        return $this->comment;
    }

    public function rewind_comments()
    {
        $this->current_comment = -1;
    }

    public function generate_cache_key(array $args, $sql)
    {
        return md5(serialize($args) . $sql);
    }

    public function lazyload_term_meta($check, $term_id)
    {
        return $check;
    }

    public function lazyload_comment_meta($check, $comment_id)
    {
        return $check;
    }
}
