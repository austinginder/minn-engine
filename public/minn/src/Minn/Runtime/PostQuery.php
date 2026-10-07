<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;
use Minn\Query\PostOrder;
use Minn\Query\PostSearch;

/**
 * WP_Query::get_posts as the reference runs it (probe wp-query-sql): the
 * variables parsed and handed to pre_get_posts, the clauses written piece by
 * piece, every filter a plugin may change them through in the reference's
 * order (suppress_filters keeps the ones it keeps), the request split into
 * an id query when that is cheaper, the count, a single post's status check
 * and preview, sticky posts on the home listing, and the results filters.
 */
final class PostQuery
{
    private \WP_Query $query;
    private object $wpdb;
    private PostQueryParts $parts;

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Runs a query object's variables and fills it in: the posts (or ids, or
     * id => parent), the request, the counts. Returns what get_posts returns.
     *
     * @param object $wpdb the database object plugins see, so the request runs where their filters expect it
     * @param mixed $urlSearch the search string the URL carries, if any (a search from elsewhere is url-decoded)
     */
    public function run(\WP_Query $query, object $wpdb, mixed $urlSearch): mixed
    {
        $this->query = $query;
        $this->wpdb = $wpdb;
        $this->parts = new PostQueryParts($this->db->table('posts'));
        $this->prepare();
        $q = &$query->query_vars;
        $this->defaults($q);
        $this->clauses($q, $urlSearch);
        $this->filtered($q);
        return $this->execute($q);
    }

    /** Parses the variables, hands them to pre_get_posts, and reads the meta query from them. */
    private function prepare(): void
    {
        $query = $this->query;
        $query->parse_query();
        \do_action_ref_array('pre_get_posts', [&$query]);
        $query->query_vars = $query->fill_query_vars($query->query_vars);
        $query->allow_query_attachment_by_filename = \apply_filters('wp_allow_query_attachment_by_filename', false);
        \remove_all_filters('wp_allow_query_attachment_by_filename');
        $query->meta_query = new \WP_Meta_Query();
        $query->meta_query->parse_query_vars($query->query_vars);
        $hash = md5(serialize($query->query_vars));
        if ($hash !== $query->query_vars_hash) {
            $query->query_vars_changed = true;
            $query->query_vars_hash = $hash;
        }
    }

    /** The defaults get_posts settles before it writes a clause. @param array<string, mixed> $q */
    private function defaults(array &$q): void
    {
        $query = $this->query;
        if (isset($q['caller_get_posts'])) {
            \_deprecated_argument('WP_Query', '3.1.0', sprintf('%1$s is deprecated. Use %2$s instead.', '<code>caller_get_posts</code>', '<code>ignore_sticky_posts</code>'));
            $q['ignore_sticky_posts'] ??= $q['caller_get_posts'];
        }
        $q += ['ignore_sticky_posts' => false, 'suppress_filters' => false, 'cache_results' => true, 'update_post_term_cache' => true, 'update_menu_item_cache' => false];
        if (!isset($q['lazy_load_term_meta'])) {
            $q['lazy_load_term_meta'] = $q['update_post_term_cache'];
        } elseif ($q['lazy_load_term_meta']) {
            $q['update_post_term_cache'] = true;
        }
        $q += ['update_post_meta_cache' => true, 'post_type' => $query->is_search ? 'any' : ''];
        ['post_type' => $type] = $q;
        if (is_array($type)) {
            sort($type);
            $q = array_replace($q, ['post_type' => $type]);
        }
        $this->parts->postType = $type;
        $this->pageSize($q);
        if (!isset($q['comments_per_page']) || $q['comments_per_page'] == 0) {
            $q['comments_per_page'] = \get_option('comments_per_page');
        }
        if ($query->is_home && (empty($query->query) || ($q['preview'] ?? '') === 'true') && \get_option('show_on_front') === 'page' && \get_option('page_on_front')) {
            $query->is_page = true;
            $query->is_home = false;
            $q['page_id'] = \get_option('page_on_front');
        }
        if (isset($q['page'])) {
            $q['page'] = is_scalar($q['page']) ? \absint(trim((string) $q['page'], '/')) : 0;
        }
        $q['no_found_rows'] = isset($q['no_found_rows']) && $q['no_found_rows'];
        $t = $this->parts->table;
        $this->parts->fields = match ($q['fields']) {
            'ids' => "{$t}.ID",
            'id=>parent' => "{$t}.ID, {$t}.post_parent",
            default => "{$t}.*",
        };
    }

    /** posts_per_page from the option, showposts, an archive's own size or the feed's, and nopaging. @param array<string, mixed> $q */
    private function pageSize(array &$q): void
    {
        $query = $this->query;
        if (empty($q['posts_per_page'])) {
            $q['posts_per_page'] = \get_option('posts_per_page');
        }
        if (!empty($q['showposts'])) {
            $q['showposts'] = (int) $q['showposts'];
            $q['posts_per_page'] = $q['showposts'];
        }
        if (isset($q['posts_per_archive_page']) && $q['posts_per_archive_page'] != 0 && ($query->is_archive || $query->is_search)) {
            $q['posts_per_page'] = $q['posts_per_archive_page'];
        }
        if (!isset($q['nopaging'])) {
            $q['nopaging'] = $q['posts_per_page'] == -1;
        }
        if ($query->is_feed) {
            $q['posts_per_page'] = !empty($q['posts_per_rss']) ? $q['posts_per_rss'] : \get_option('posts_per_rss');
            $q['nopaging'] = false;
        }
        $q['posts_per_page'] = (int) $q['posts_per_page'];
        if ($q['posts_per_page'] < -1) {
            $q['posts_per_page'] = abs($q['posts_per_page']);
        } elseif ($q['posts_per_page'] === 0) {
            $q['posts_per_page'] = 1;
        }
    }

    /** Writes the clauses, in the reference's order. @param array<string, mixed> $q */
    private function clauses(array &$q, mixed $urlSearch): void
    {
        $query = $this->query;
        $parts = $this->parts;
        $t = $parts->table;
        $where = new PostQueryWhere($query, $parts, $t);
        $where->dates($q);
        $where->names($q);
        if (strlen((string) $q['s'])) {
            $parts->search = $this->search($query, $q, $urlSearch);
        }
        if (!$q['suppress_filters']) {
            $parts->search = \apply_filters_ref_array('posts_search', [$parts->search, &$query]);
        }
        $this->taxonomies($q);
        $where->authors($q);
        $parts->where .= $parts->search . $parts->whichauthor . $parts->whichmimetype;
        if (!empty($query->allow_query_attachment_by_filename)) {
            $parts->join .= " LEFT JOIN {$this->db->table('postmeta')} AS sq1 ON ( {$t}.ID = sq1.post_id AND sq1.meta_key = '_wp_attached_file' )";
        }
        if (!empty($query->meta_query->queries)) {
            $sql = $query->meta_query->get_sql('post', $t, 'ID', $query);
            $parts->join .= $sql['join'] ?? '';
            $parts->where .= $sql['where'] ?? '';
        }
        $parts->orderby = PostOrder::build($q, $t, (array) $query->meta_query->get_clauses());
        $this->searchOrder($q);
        $status = new PostQueryStatus($query, $parts);
        $status->settleType();
        $where->access($q);
        $status->append($q);
    }

    /** The tax query's clauses (not for a single post), a taxonomy archive's types, and the compatibility variables. @param array<string, mixed> $q */
    private function taxonomies(array &$q): void
    {
        $query = $this->query;
        $parts = $this->parts;
        if (!$query->is_singular) {
            $query->parse_tax_query($q);
            $sql = $query->tax_query->get_sql($parts->table, 'ID');
            $parts->join .= $sql['join'];
            $parts->where .= $sql['where'];
        }
        if ($query->is_tax && empty($parts->postType)) {
            $parts->postType = PostQueryTax::postTypes(array_keys((array) $query->tax_query->queried_terms));
            $parts->statusJoin = true;
        } elseif ($query->is_tax && in_array('attachment', (array) $parts->postType, true)) {
            $parts->statusJoin = true;
        }
        if (!empty($query->tax_query->queried_terms)) {
            PostQueryTax::compat($query, $q, $query->tax_query->queried_terms);
        }
        if (!empty($query->tax_query->queries) || !empty($query->meta_query->queries) || !empty($query->allow_query_attachment_by_filename)) {
            $parts->groupby = "{$parts->table}.ID";
        }
    }

    /**
     * A search's WHERE fragment, settling the search variables (the terms,
     * how many the string split into, the title matches its order ranks by).
     *
     * @param array<string, mixed> $q
     */
    public function search(\WP_Query $query, array &$q, mixed $urlSearch): string
    {
        $this->query = $query;
        if (!isset($this->parts)) {
            $this->parts = new PostQueryParts($this->db->table('posts'));
        }
        $q['s'] = stripslashes((string) $q['s']);
        if (empty($urlSearch) && $this->query->is_main_query()) {
            $q['s'] = urldecode($q['s']);
        }
        $q['s'] = str_replace(["\r", "\n"], '', $q['s']);
        $split = !empty($q['sentence']) ? ['terms' => [$q['s']], 'count' => 1] : PostSearch::terms($q['s'], fn (): array => $this->stopwords());
        $q['search_terms_count'] = $split['count'];
        $q['search_terms'] = $split['terms'];
        $given = !empty($q['search_columns']) ? (array) $q['search_columns'] : PostSearch::COLUMNS;
        $t = $this->parts->table;
        $columns = array_map(static fn (string $column) => "{$t}.{$column}", PostSearch::columns((array) \apply_filters('post_search_columns', $given, $q['s'], $this->query)));
        if (!empty($this->query->allow_query_attachment_by_filename)) {
            $columns[] = 'sq1.meta_value';
        }
        $prefix = \apply_filters('wp_query_search_exclusion_prefix', '-');
        $built = PostSearch::where($t, $q['search_terms'], $columns, is_scalar($prefix) && $prefix ? (string) $prefix : '', !empty($q['exact']) ? '' : '%');
        $q['search_orderby_title'] = $built['titles'];
        // A visitor's search leaves out password-protected posts.
        return $built['where'] !== '' && !\is_user_logged_in() ? $built['where'] . " AND ({$t}.post_password = '') " : $built['where'];
    }

    /** The search stopwords, translated and filtered. @return list<string> */
    public function stopwords(): array
    {
        $list = \_x(PostSearch::STOPWORDS, 'Comma-separated list of search stopwords in your language');
        return (array) \apply_filters('wp_search_stopwords', PostSearch::stopwords((string) $list));
    }

    /** A search's relevance order, ahead of the order asked for, when no other order is asked for. @param array<string, mixed> $q */
    private function searchOrder(array $q): void
    {
        if (empty($q['s'])) {
            return;
        }
        $order = '';
        if ((!empty($q['search_orderby_title']) && empty($q['orderby']) && !$this->query->is_feed) || (isset($q['orderby']) && $q['orderby'] === 'relevance')) {
            $order = PostSearch::order($this->parts->table, (string) $q['s'], (int) $q['search_terms_count'], (array) ($q['search_orderby_title'] ?? []));
        }
        if (!$q['suppress_filters']) {
            $order = \apply_filters('posts_search_orderby', $order, $this->query);
        }
        if ($order) {
            $this->parts->orderby = $this->parts->orderby ? $order . ', ' . $this->parts->orderby : $order;
        }
    }

    /** The clause filters, paging between the first two and the rest, and posts_selection. @param array<string, mixed> $q */
    private function filtered(array &$q): void
    {
        $query = $this->query;
        $parts = $this->parts;
        $filter = !$q['suppress_filters'];
        if ($filter) {
            $parts->where = \apply_filters_ref_array('posts_where', [$parts->where, &$query]);
            $parts->join = \apply_filters_ref_array('posts_join', [$parts->join, &$query]);
        }
        $this->paging($q);
        if ($filter) {
            $this->through(['posts_where_paged' => 'where', 'posts_groupby' => 'groupby', 'posts_join_paged' => 'join', 'posts_orderby' => 'orderby', 'posts_distinct' => 'distinct', 'post_limits' => 'limits', 'posts_fields' => 'fields'], 'posts_clauses');
        }
        \do_action('posts_selection', $parts->where . $parts->groupby . $parts->orderby . $parts->limits . $parts->join);
        if ($filter) {
            $this->through(['posts_where_request' => 'where', 'posts_groupby_request' => 'groupby', 'posts_join_request' => 'join', 'posts_orderby_request' => 'orderby', 'posts_distinct_request' => 'distinct', 'posts_fields_request' => 'fields', 'post_limits_request' => 'limits'], 'posts_clauses_request');
        }
    }

    /** Each piece through its filter, then all seven through the clauses filter. @param array<string, string> $hooks */
    private function through(array $hooks, string $clausesHook): void
    {
        $query = $this->query;
        foreach ($hooks as $hook => $piece) {
            $this->parts->{$piece} = \apply_filters_ref_array($hook, [$this->parts->{$piece}, &$query]);
        }
        $this->parts->take((array) \apply_filters_ref_array($clausesHook, [$this->parts->pieces(), &$query]));
    }

    /** LIMIT for a listing that pages: the offset when one is given, the page's start otherwise. @param array<string, mixed> $q */
    private function paging(array &$q): void
    {
        if (!empty($q['nopaging']) || $this->query->is_singular) {
            return;
        }
        $this->parts->page = \absint($q['paged']) ?: 1;
        if (isset($q['offset']) && is_numeric($q['offset'])) {
            $q['offset'] = \absint($q['offset']);
            $start = $q['offset'] . ', ';
        } else {
            $start = \absint(($this->parts->page - 1) * $q['posts_per_page']) . ', ';
        }
        $this->parts->limits = 'LIMIT ' . $start . $q['posts_per_page'];
    }

    /** Writes the request, runs it (or takes posts_pre_query's answer), and finishes the results. @param array<string, mixed> $q */
    private function execute(array &$q): mixed
    {
        $query = $this->query;
        $foundRows = !$q['no_found_rows'] && !empty($this->parts->limits) ? 'SQL_CALC_FOUND_ROWS' : '';
        $old = $this->parts->request($foundRows, $this->parts->fields);
        $query->request = $old;
        if (!$q['suppress_filters']) {
            $query->request = \apply_filters_ref_array('posts_request', [$query->request, &$query]);
        }
        $query->posts = \apply_filters_ref_array('posts_pre_query', [null, &$query]);
        if ($q['fields'] === 'ids' || $q['fields'] === 'id=>parent') {
            return $this->idsOnly($q);
        }
        if ($query->posts === null) {
            $this->select($q, $old, $foundRows);
        }
        if ($query->posts) {
            $query->posts = array_map('get_post', $query->posts);
        }
        if (!$q['suppress_filters']) {
            $query->posts = \apply_filters_ref_array('posts_results', [$query->posts, &$query]);
        }
        (new PostQueryResults($query, $this->parts))->settle($q);
        return $query->posts;
    }

    /** The ids (or id => parent) a query for them returns. @param array<string, mixed> $q */
    private function idsOnly(array $q): array
    {
        $query = $this->query;
        if ($q['fields'] === 'ids') {
            $query->posts = array_map('intval', $query->posts ?? $this->wpdb->get_col($query->request));
            $query->post_count = count($query->posts);
            $this->foundPosts($q);
            return $query->posts;
        }
        $query->posts ??= $this->wpdb->get_results($query->request);
        $query->post_count = count($query->posts);
        $this->foundPosts($q);
        $parents = [];
        foreach ($query->posts as $key => $post) {
            $query->posts[$key]->ID = (int) $post->ID;
            $query->posts[$key]->post_parent = (int) $post->post_parent;
            $parents[(int) $post->ID] = (int) $post->post_parent;
        }
        return $parents;
    }

    /** Runs the request: ids first, then their posts, when the query can be split that way. @param array<string, mixed> $q */
    private function select(array $q, string $old, string $foundRows): void
    {
        $query = $this->query;
        $t = $this->parts->table;
        $split = $old === $query->request && $this->parts->fields === "{$t}.*" && !empty($this->parts->limits) && $q['posts_per_page'] < 500;
        $split = \apply_filters('split_the_query', $split, $query, $old, $this->parts->pieces());
        if (!$split) {
            $query->posts = $this->wpdb->get_results($query->request);
            $this->foundPosts($q);
            return;
        }
        $query->request = \apply_filters('posts_request_ids', $this->parts->request($foundRows, "{$t}.ID"), $query);
        $ids = $this->wpdb->get_col($query->request);
        $query->posts = $ids ?: [];
        if ($ids) {
            $this->foundPosts($q);
            \_prime_post_caches($ids, $q['update_post_term_cache'], $q['update_post_meta_cache']);
        }
    }

    /** found_posts (from FOUND_ROWS() when the query paged, else the posts' count) and max_num_pages. @param array<string, mixed> $q */
    private function foundPosts(array $q): void
    {
        $query = $this->query;
        if ($q['no_found_rows'] || (is_array($query->posts) && !$query->posts)) {
            return;
        }
        $paged = !empty($this->parts->limits);
        if ($paged) {
            $query->found_posts = (int) $this->wpdb->get_var(\apply_filters_ref_array('found_posts_query', ['SELECT FOUND_ROWS()', &$query]));
        } else {
            $query->found_posts = is_array($query->posts) ? count($query->posts) : ($query->posts === null ? 0 : 1);
        }
        $query->found_posts = (int) \apply_filters_ref_array('found_posts', [$query->found_posts, &$query]);
        if ($paged && (int) $q['posts_per_page'] !== 0) {
            $query->max_num_pages = (int) ceil($query->found_posts / $q['posts_per_page']);
        }
    }
}
