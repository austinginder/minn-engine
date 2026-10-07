<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\CommentOrder;

/**
 * WP_Comment_Query as the reference runs it (probe wp-comment-query-sql):
 * the variables filled and handed to pre_get_comments, the meta query's
 * SQL, comments_pre_query (which may answer), the clauses (the date query
 * among them) handed to comments_clauses, the request, found_comments_query
 * when a limited query counts what it found, then the ids, the count, or
 * the comments through the_comments, threaded or flattened when asked.
 * Results are not cached between queries (the reference keeps them in the
 * object cache by last_changed).
 */
final class CommentQueryRunner
{
    public function __construct(private readonly object $wpdb)
    {
    }

    /** Runs the query, as WP_Comment_Query::get_comments() does: a count, ids, or comments. */
    public function run(\WP_Comment_Query $query): mixed
    {
        $query->parse_query();
        \do_action_ref_array('pre_get_comments', [&$query]);
        $query->meta_query = new \WP_Meta_Query();
        $query->meta_query->parse_query_vars($query->query_vars);
        $meta = empty($query->meta_query->queries) ? [] : (array) $query->meta_query->get_sql('comment', $this->wpdb->comments, 'comment_ID', $query);
        $answer = \apply_filters_ref_array('comments_pre_query', [null, &$query]);
        if ($answer !== null) {
            $query->comments = $answer;
            return $answer;
        }
        $qv = $query->query_vars;
        [$request, $found] = $this->request($query, $meta);
        if (!empty($qv['count'])) {
            return (int) $this->wpdb->get_var($request);
        }
        $ids = array_map('intval', (array) $this->wpdb->get_col($request));
        // An empty page counts nothing: the found rows are read only when the query found some.
        if ($found && $ids !== []) {
            $query->found_comments = (int) $this->wpdb->get_var(\apply_filters_ref_array('found_comments_query', ['SELECT FOUND_ROWS()', &$query]));
            $query->max_num_pages = (int) ceil($query->found_comments / max(1, abs((int) ($qv['number']))));
        }
        if (($qv['fields'] ?? '') === 'ids') {
            $query->comments = $ids;
            return $ids;
        }
        $comments = (array) \apply_filters_ref_array('the_comments', [$this->comments($ids, $qv), &$query]);
        $comments = array_values(array_filter(array_map(static fn ($c) => $c instanceof \WP_Comment ? $c : \get_comment($c), $comments)));
        if (in_array($qv['hierarchical'] ?? false, ['threaded', 'flat'], true)) {
            $comments = (new CommentThreads())->fill($query, $comments);
        }
        $query->comments = $comments;
        return $comments;
    }

    /**
     * The clauses through comments_clauses, kept on the query and written
     * as its request, and whether the request counts the rows it found.
     *
     * @param array{join?: string, where?: string} $meta
     * @return array{0: string, 1: bool}
     */
    private function request(\WP_Comment_Query $query, array $meta): array
    {
        $qv = $query->query_vars;
        $counted = !empty($qv['count']);
        $table = $this->wpdb->comments;
        [$where, $join] = (new CommentQueryWhere($this->wpdb))->pieces($query, $meta);
        $limits = CommentOrder::limits($qv);
        $found = $limits !== '' && empty($qv['no_found_rows']) ? 'SQL_CALC_FOUND_ROWS' : '';
        $clauses = (array) \apply_filters_ref_array('comments_clauses', [[
            'fields' => $counted ? 'COUNT(*)' : "{$table}.comment_ID",
            'join' => $join . ($meta['join'] ?? ''),
            'where' => implode(' AND ', $where),
            'orderby' => CommentOrder::build($qv, $table, $this->wpdb->commentmeta, (array) $query->meta_query->get_clauses()),
            'limits' => $limits,
            'groupby' => $meta !== [] && !$counted ? "{$table}.comment_ID" : '',
        ], &$query]);
        ['fields' => $fields, 'join' => $join, 'where' => $where, 'orderby' => $orderby, 'limits' => $limits, 'groupby' => $groupby] = array_map('strval', $clauses + array_fill_keys(['fields', 'join', 'where', 'orderby', 'limits', 'groupby'], ''));
        $sql = ['select' => "SELECT {$found} {$fields}", 'from' => "FROM {$table} {$join}", 'where' => $where === '' ? '' : "WHERE {$where}", 'groupby' => $groupby === '' ? '' : "GROUP BY {$groupby}", 'orderby' => $orderby === '' ? '' : "ORDER BY {$orderby}", 'limits' => $limits];
        // The pieces stay on the query, where a subclass reads them.
        (fn () => $this->sql_clauses = $sql)->call($query);
        $query->request = implode("\n\t\t\t ", $sql);
        return [$query->request, $found !== ''];
    }

    /**
     * The comments for the ids in their order, each through get_comment(),
     * their posts loaded first when asked.
     *
     * @param list<int> $ids
     * @param array<string, mixed> $qv
     * @return list<\WP_Comment>
     */
    private function comments(array $ids, array $qv): array
    {
        if ($ids === []) {
            return [];
        }
        $table = $this->wpdb->comments;
        $rows = [];
        foreach ((array) $this->wpdb->get_results("SELECT {$table}.* FROM {$table} WHERE comment_ID IN (" . implode(',', $ids) . ')') as $row) {
            $rows[(int) $row->comment_ID] = $row;
        }
        $comments = [];
        foreach ($ids as $id) {
            $comment = isset($rows[$id]) ? \get_comment(new \WP_Comment($rows[$id])) : null;
            if ($comment instanceof \WP_Comment) {
                $comments[] = $comment;
            }
        }
        if (!empty($qv['update_comment_post_cache'])) {
            \_prime_post_caches(array_values(array_unique(array_map(static fn ($c) => (int) $c->comment_post_ID, $comments))), false, false);
        }
        return $comments;
    }
}
