<?php

use Minn\Runtime\Runtime;

/**
 * The query object plugin code builds and loops over. Variables are filled
 * from the reference's own template (data/registry.json), the conditional
 * flags derive from them, and Minn\Runtime\PostQuery runs the SELECT.
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
        $template = Runtime::registry()->queryVars;
        foreach ($template as $key => $default) {
            if (!isset($query_vars[$key])) {
                $query_vars[$key] = is_array($default) ? [] : ($key === 'p' ? 0 : ($key === 'posts_per_page' ? (int) get_option('posts_per_page') : $default));
            }
        }
        foreach (Runtime::registry()->taxonomies() as $taxonomy) {
            $var = $taxonomy['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !isset($query_vars[$var])) {
                $query_vars[$var] = '';
            }
        }
        return $query_vars;
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
        $this->query_vars = $this->fill_query_vars($this->query_vars);
        $q = &$this->query_vars;
        $this->init_query_flags();
        foreach (['p', 'page_id', 'attachment_id', 'year', 'monthnum', 'day', 'w', 'paged', 'cat'] as $key) {
            if ($key === 'cat') {
                continue;
            }
            if (isset($q[$key]) && $q[$key] !== '' && !is_array($q[$key])) {
                $q[$key] = (int) $q[$key];
            }
        }
        if ((int) $q['p'] < 0 || (int) $q['page_id'] < 0) {
            $this->is_404 = true;
            $q['error'] = '404';
        }
        if (isset($q['error']) && $q['error'] === '404') {
            $this->is_404 = true;
        }
        if (!empty($q['embed'])) {
            $this->is_embed = true;
        }
        if (!empty($q['tb'])) {
            $this->is_trackback = true;
        }
        if (!empty($q['paged']) && (int) $q['paged'] > 1) {
            $this->is_paged = true;
        }
        if (!empty($q['s'])) {
            $this->is_search = true;
        }
        if (!empty($q['feed'])) {
            $this->is_feed = true;
        }
        if (!empty($q['attachment']) || !empty($q['attachment_id'])) {
            $this->is_single = true;
            $this->is_attachment = true;
        } elseif (!empty($q['p']) || !empty($q['name'])) {
            $this->is_single = true;
        } elseif (!empty($q['page_id']) || !empty($q['pagename'])) {
            $this->is_page = true;
        } else {
            if ($q['year'] || $q['monthnum'] || $q['day'] || $q['w'] || !empty($q['m']) || !empty($q['hour']) || !empty($q['minute']) || !empty($q['second'])) {
                $this->is_date = true;
                if ($q['year']) {
                    $this->is_year = true;
                }
                if ($q['monthnum']) {
                    $this->is_month = true;
                }
                if ($q['day']) {
                    $this->is_day = true;
                }
                if (!empty($q['hour']) || !empty($q['minute']) || !empty($q['second'])) {
                    $this->is_time = true;
                }
            }
            $positiveCats = array_filter(array_map('intval', preg_split('/[\s,]+/', (string) ($q['cat'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)), static fn (int $c) => $c > 0);
            if ($positiveCats !== [] || !empty($q['category_name']) || !empty($q['category__in']) || !empty($q['category__and'])) {
                $this->is_category = true;
            }
            if (!empty($q['tag']) || !empty($q['tag_id']) || !empty($q['tag__in']) || !empty($q['tag__and']) || !empty($q['tag_slug__in']) || !empty($q['tag_slug__and'])) {
                $this->is_tag = true;
            }
            if (!empty($q['tax_query']) && is_array($q['tax_query'])) {
                $this->is_tax = true;
            }
            foreach (Runtime::registry()->taxonomies() as $name => $taxonomy) {
                $var = $taxonomy['query_var'] ?? false;
                if (is_string($var) && $var !== '' && !in_array($var, ['category_name', 'tag'], true) && !empty($q[$var])) {
                    $this->is_tax = true;
                }
            }
            if (!empty($q['author']) || !empty($q['author_name']) || !empty($q['author__in'])) {
                $this->is_author = true;
            }
            if (!empty($q['post_type']) && !is_array($q['post_type']) && $q['post_type'] !== 'any') {
                $type = Runtime::registry()->postType((string) $q['post_type']);
                if ($type !== null && !empty($type['has_archive'])) {
                    $this->is_post_type_archive = true;
                }
            }
            if ($this->is_date || $this->is_category || $this->is_tag || $this->is_tax || $this->is_author || $this->is_post_type_archive) {
                $this->is_archive = true;
            }
        }
        if ($this->is_single || $this->is_page) {
            $this->is_singular = true;
        }
        if (!$this->is_singular && !$this->is_archive && !$this->is_search && !$this->is_feed && !$this->is_trackback && !$this->is_404 && !$this->is_embed) {
            $this->is_home = true;
        }
        if ($this->is_page && (int) $q['page_id'] > 0 && (int) $q['page_id'] === (int) get_option('page_for_posts')) {
            $this->is_home = true;
            $this->is_page = false;
            $this->is_singular = false;
            $this->is_posts_page = true;
        }
        if ($this->is_page && (int) get_option('wp_page_for_privacy_policy') > 0 && (int) $q['page_id'] === (int) get_option('wp_page_for_privacy_policy')) {
            $this->is_privacy_policy = true;
        }
        $this->is_admin = is_admin();
        do_action_ref_array('parse_query', [&$this]);
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
        $this->init_query_flags();
        $this->is_404 = true;
        unset($this->posts, $this->post);
        $this->posts = [];
        $this->post_count = 0;
    }

    public function get_posts()
    {
        $this->parse_query();
        do_action_ref_array('pre_get_posts', [&$this]);
        $q = &$this->query_vars;
        $result = Runtime::postQuery()->run($q, $this->is_home);
        $rows = $result['rows'];
        $fields = (string) ($q['fields'] ?? 'all');
        if ($result['sticky'] !== [] && $fields === 'all') {
            $types = $q['post_type'] === '' || $q['post_type'] === null ? ['post'] : ($q['post_type'] === 'any' ? null : (array) $q['post_type']);
            $result['sticky'] = $types === null ? $result['sticky'] : array_values(array_filter($result['sticky'], static fn (array $p) => in_array($p['post_type'], $types, true)));
        }
        if ($result['sticky'] !== [] && $fields === 'all') {
            $stickyIds = array_map(static fn (array $p) => (int) $p['ID'], $result['sticky']);
            $rows = array_values(array_filter($rows, static fn (array $p) => !in_array((int) $p['ID'], $stickyIds, true)));
            $rows = [...$result['sticky'], ...$rows];
        }
        if ($fields === 'ids') {
            $this->posts = array_map(static fn (array $p) => (int) $p['ID'], $rows);
        } elseif ($fields === 'id=>parent') {
            $this->posts = array_map(static fn (array $p) => (object) ['ID' => (int) $p['ID'], 'post_parent' => (int) $p['post_parent']], $rows);
        } else {
            $this->posts = array_map(static fn (array $p) => new WP_Post((object) $p), $rows);
            $this->posts = apply_filters_ref_array('the_posts', [$this->posts, &$this]);
        }
        $this->post_count = count($this->posts);
        $this->found_posts = empty($q['no_found_rows']) ? (int) $result['found'] : 0;
        if ($this->found_posts > 0 && !empty($result['sticky'])) {
            // The reference counts only the queried rows; stickies ride on top of the page.
        }
        $perPage = !empty($q['nopaging']) ? 0 : (int) ($q['posts_per_page'] ?? 10);
        $this->max_num_pages = $perPage > 0 && $this->found_posts > 0 ? (int) ceil($this->found_posts / $perPage) : ($this->found_posts > 0 || $this->post_count > 0 ? 1 : 0);
        if ($this->post_count > 0 && $fields === 'all') {
            $this->post = $this->posts[0];
        }
        return $this->posts;
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
        $GLOBALS['authordata'] = get_userdata((int) $post->post_author) ?: null;
        $GLOBALS['currentday'] = mysql2date('d.m.y', $post->post_date, false);
        $GLOBALS['currentmonth'] = mysql2date('m', $post->post_date, false);
        $GLOBALS['page'] = $this->get('page') ?: 1;
        $GLOBALS['more'] = 1;
        $GLOBALS['numpages'] = 1;
        $GLOBALS['multipage'] = 0;
        $GLOBALS['pages'] = [$post->post_content];
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
        $q = $this->query_vars;
        if ($this->is_category || $this->is_tag || $this->is_tax) {
            $term = null;
            if ($this->is_category) {
                $term = !empty($q['cat']) ? get_term((int) $q['cat'], 'category') : (!empty($q['category_name']) ? get_term_by('slug', basename((string) $q['category_name']), 'category') : null);
            } elseif ($this->is_tag) {
                $term = !empty($q['tag_id']) ? get_term((int) $q['tag_id'], 'post_tag') : (!empty($q['tag']) ? get_term_by('slug', (string) $q['tag'], 'post_tag') : null);
            } else {
                foreach ((array) ($q['tax_query'] ?? []) as $clause) {
                    if (is_array($clause) && isset($clause['taxonomy'], $clause['terms'])) {
                        $terms = (array) $clause['terms'];
                        $field = (string) ($clause['field'] ?? 'term_id');
                        $term = $field === 'term_id' ? get_term((int) reset($terms), (string) $clause['taxonomy']) : get_term_by($field, (string) reset($terms), (string) $clause['taxonomy']);
                        break;
                    }
                }
            }
            if ($term instanceof WP_Term) {
                $this->queried_object = $term;
                $this->queried_object_id = $term->term_id;
            }
        } elseif ($this->is_post_type_archive) {
            $this->queried_object = get_post_type_object((string) $q['post_type']);
        } elseif ($this->is_posts_page) {
            $this->queried_object = get_post((int) get_option('page_for_posts'));
            $this->queried_object_id = (int) get_option('page_for_posts');
        } elseif ($this->is_singular && !empty($this->post)) {
            $this->queried_object = $this->post;
            $this->queried_object_id = (int) $this->post->ID;
        } elseif ($this->is_author) {
            $user = !empty($q['author']) ? get_userdata((int) $q['author']) : (!empty($q['author_name']) ? get_user_by('slug', (string) $q['author_name']) : false);
            if ($user) {
                $this->queried_object = $user;
                $this->queried_object_id = $user->ID;
            }
        }
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
        return false;
    }

    public function the_comment()
    {
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
