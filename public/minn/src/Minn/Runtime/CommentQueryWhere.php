<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Query\Sql;

/**
 * WP_Comment_Query's WHERE pieces and the posts join, in the reference's
 * order (probe wp-comment-query-sql): the approval statuses (with the
 * readers whose held comments show), the post, the id lists, the author's
 * email and url, karma, the types (notes left out unless asked for), the
 * parent (0 for a threaded or flat query that names none), the user, the
 * search, the post's own fields, the author lists, then the meta and date
 * queries.
 */
final class CommentQueryWhere
{
    private const ID_LISTS = ['comment__in' => '{c}.comment_ID IN', 'comment__not_in' => '{c}.comment_ID NOT IN', 'parent__in' => 'comment_parent IN', 'parent__not_in' => 'comment_parent NOT IN', 'post__in' => 'comment_post_ID IN', 'post__not_in' => 'comment_post_ID NOT IN'];
    private const AUTHOR_LISTS = ['author__in' => 'user_id IN', 'author__not_in' => 'user_id NOT IN', 'post_author__in' => 'post_author IN', 'post_author__not_in' => 'post_author NOT IN'];
    private const POST_FIELDS = ['post_author', 'post_name', 'post_parent', 'post_status', 'post_type'];
    private const SEARCHED = ['comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content'];
    private const ALL = "( comment_approved = '0' OR comment_approved = '1' )";

    public function __construct(private readonly object $wpdb)
    {
    }

    /**
     * The WHERE pieces (joined with AND by the caller) and the posts join
     * ('' when no post field is asked after).
     *
     * @param array{join?: string, where?: string} $meta the meta query's SQL, when it has clauses
     * @return array{0: list<string>, 1: string}
     */
    public function pieces(\WP_Comment_Query $query, array $meta): array
    {
        $qv = $query->query_vars;
        $where = array_values(array_filter([$this->approved($qv), $this->post($qv)]));
        $comments = $this->wpdb->comments;
        foreach (self::ID_LISTS as $var => $test) {
            if (!empty($qv[$var])) {
                $where[] = str_replace('{c}', $comments, $test) . ' ( ' . implode(',', \wp_parse_id_list($qv[$var])) . ' )';
            }
        }
        ['author_email' => $email, 'author_url' => $url, 'karma' => $karma] = $qv + ['author_email' => '', 'author_url' => '', 'karma' => ''];
        foreach (['comment_author_email' => $email, 'comment_author_url' => $url] as $column => $value) {
            if (self::given($value)) {
                $where[] = "{$column} = " . Sql::quote((string) $value);
            }
        }
        if (self::given($karma)) {
            $where[] = 'comment_karma = ' . (int) $karma;
        }
        array_push($where, ...$this->types($qv), ...$this->parentAndUser($qv), ...$this->search($qv));
        [$postWhere, $join] = $this->posts($qv);
        array_push($where, ...$postWhere);
        if (!empty($meta['where'])) {
            $where[] = self::bare($meta['where']);
        }
        if (!empty($qv['date_query']) && is_array($qv['date_query'])) {
            $query->date_query = new \WP_Date_Query($qv['date_query'], 'comment_date');
            $where[] = self::bare((string) $query->date_query->get_sql());
        }
        return [array_values(array_filter($where, static fn ($piece) => $piece !== '')), $join];
    }

    /**
     * The approval test: each status (all is approved or held, any is no
     * test), and the held comments of the readers named by email or user id.
     *
     * @param array<string, mixed> $qv
     */
    private function approved(array $qv): string
    {
        $statuses = self::listOf($qv['status'] ?? 'all') ?: ['all'];
        if (in_array('any', $statuses, true)) {
            return '';
        }
        $tests = array_map(static fn ($status) => match ((string) $status) {
            'hold' => "comment_approved = '0'",
            'approve' => "comment_approved = '1'",
            'all', '' => self::ALL,
            default => 'comment_approved = ' . Sql::quote((string) $status),
        }, $statuses);
        $readers = array_map(static fn ($reader) => is_numeric($reader)
            ? '( user_id = ' . (int) $reader . " AND comment_approved = '0' )"
            : '( comment_author_email = ' . Sql::quote((string) $reader) . " AND comment_approved = '0' )", self::listOf($qv['include_unapproved'] ?? []));
        $approved = '( ' . implode(' OR ', $tests) . ' )';
        return $readers === [] ? $approved : '( ' . implode(' OR ', [$approved, ...$readers]) . ' )';
    }

    /** The one post asked for. @param array<string, mixed> $qv */
    private function post(array $qv): string
    {
        ['post_id' => $post] = $qv + ['post_id' => 0];
        $post = \absint($post);
        return $post > 0 ? "comment_post_ID = {$post}" : '';
    }

    /**
     * The types in (comment stands for itself and the empty type, pings for
     * pingbacks and trackbacks) and out; notes stay out unless asked for,
     * and all asks for no test at all.
     *
     * @param array<string, mixed> $qv
     * @return list<string>
     */
    private function types(array $qv): array
    {
        $in = [...self::listOf($qv['type'] ?? ''), ...self::listOf($qv['type__in'] ?? [])];
        if (in_array('all', $in, true)) {
            return [];
        }
        $out = self::listOf($qv['type__not_in'] ?? []);
        if (!in_array('note', $in, true) && !in_array('note', $out, true)) {
            $out[] = 'note';
        }
        $where = [];
        foreach (['IN' => $in, 'NOT IN' => $out] as $operator => $types) {
            $values = [];
            foreach ($types as $type) {
                array_push($values, ...match ((string) $type) {
                    'comment' => ['', 'comment'],
                    'pings' => ['pingback', 'trackback'],
                    default => [(string) $type],
                });
            }
            if ($values !== []) {
                $where[] = "comment_type {$operator} ('" . implode("', '", array_map('esc_sql', array_unique($values))) . "')";
            }
        }
        return $where;
    }

    /** The parent (top level for a threaded or flat query naming none), then the user. @param array<string, mixed> $qv @return list<string> */
    private function parentAndUser(array $qv): array
    {
        ['parent' => $parent, 'user_id' => $user, 'hierarchical' => $threads] = $qv + ['parent' => '', 'user_id' => '', 'hierarchical' => false];
        if (!self::given($parent) && in_array($threads, ['threaded', 'flat'], true)) {
            $parent = 0;
        }
        return array_values(array_filter([
            self::given($parent) ? 'comment_parent = ' . (int) $parent : '',
            self::given($user) ? 'user_id = ' . (int) $user : '',
        ]));
    }

    /** A LIKE test over the author's name, email, url, IP and the content. @param array<string, mixed> $qv @return list<string> */
    private function search(array $qv): array
    {
        $search = is_scalar($qv['search'] ?? null) ? (string) $qv['search'] : '';
        if ($search === '') {
            return [];
        }
        $like = Sql::quote('%' . Sql::like($search) . '%');
        return ['(' . implode(' OR ', array_map(static fn ($column) => "{$column} LIKE {$like}", self::SEARCHED)) . ')'];
    }

    /**
     * The post's own fields, then the comment and post author lists, and
     * the posts join when a post column is tested.
     *
     * @param array<string, mixed> $qv
     * @return array{0: list<string>, 1: string}
     */
    private function posts(array $qv): array
    {
        $posts = $this->wpdb->posts;
        $where = [];
        foreach (self::POST_FIELDS as $field) {
            if (!empty($qv[$field])) {
                $where[] = " {$posts}.{$field} IN ('" . implode("','", array_map(static fn ($v) => \esc_sql((string) $v), (array) $qv[$field])) . "')";
            }
        }
        $joined = $where !== [];
        foreach (self::AUTHOR_LISTS as $var => $test) {
            if (!empty($qv[$var])) {
                $where[] = "{$test} ( " . implode(',', \wp_parse_id_list($qv[$var])) . ' )';
                $joined = $joined || str_starts_with($test, 'post_');
            }
        }
        return [$where, $joined ? "JOIN {$posts} ON {$posts}.ID = {$this->wpdb->comments}.comment_post_ID" : ''];
    }

    /** A value the query tests: anything but '', null and false. */
    private static function given(mixed $value): bool
    {
        return $value !== '' && $value !== null && $value !== false;
    }

    /** An array as given, or a comma (or space) separated string split. @return list<mixed> */
    private static function listOf(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        return is_scalar($value) && (string) $value !== '' ? array_values(array_filter((array) preg_split('/[\s,]+/', (string) $value), static fn ($v) => $v !== '')) : [];
    }

    /** A meta or date query's SQL without its leading AND. */
    private static function bare(string $sql): string
    {
        return (string) preg_replace('/^\s*AND\s*/', '', $sql);
    }
}
