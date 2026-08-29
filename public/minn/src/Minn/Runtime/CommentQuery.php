<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/** Comment reads in the get_comments() shape: arguments to rows or a count, and the approval breakdown wp_count_comments reports. */
final readonly class CommentQuery
{
    public const DEFAULTS = ['post_id' => 0, 'post__in' => [], 'status' => 'all', 'number' => '', 'offset' => 0, 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'fields' => '', 'count' => false, 'parent' => '', 'type' => '', 'author_email' => '', 'user_id' => '', 'search' => '', 'include_unapproved' => [], 'comment__in' => [], 'comment__not_in' => [], 'post_status' => '', 'post_type' => '', 'author__in' => [], 'date_query' => null, 'hierarchical' => false];

    public function __construct(private Db $db)
    {
    }

    public function count(array $args): int
    {
        [$clause, $params] = $this->where($args);
        return (int) $this->db->value("SELECT COUNT(*) FROM {$this->db->table('comments')} c WHERE {$clause}", $params);
    }

    /** @return list<array<string, mixed>> */
    public function rows(array $args): array
    {
        [$clause, $params] = $this->where($args);
        $column = match ((string) $args['orderby']) {
            'comment_date' => 'c.comment_date',
            'comment_ID', 'ID' => 'c.comment_ID',
            'comment_post_ID' => 'c.comment_post_ID',
            'comment_author' => 'c.comment_author',
            'none' => '',
            default => 'c.comment_date_gmt',
        };
        $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $orderClause = $column === '' ? '' : " ORDER BY {$column} {$order}, c.comment_ID {$order}";
        $limit = '';
        $limitParams = [];
        if ($args['number'] !== '' && (int) $args['number'] > 0) {
            $limit = ' LIMIT ? OFFSET ?';
            $limitParams = [(int) $args['number'], (int) $args['offset']];
        }
        return $this->db->rows("SELECT c.* FROM {$this->db->table('comments')} c WHERE {$clause}{$orderClause}{$limit}", [...$params, ...$limitParams]);
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function where(array $args): array
    {
        $where = [];
        $params = [];
        if (!empty($args['post_id'])) {
            $where[] = 'c.comment_post_ID = ?';
            $params[] = (int) $args['post_id'];
        }
        foreach (['post__in' => 'c.comment_post_ID IN', 'comment__in' => 'c.comment_ID IN', 'comment__not_in' => 'c.comment_ID NOT IN'] as $key => $test) {
            if (!empty($args[$key])) {
                $ids = array_map('intval', (array) $args[$key]);
                $where[] = $test . ' (' . self::marks(count($ids)) . ')';
                array_push($params, ...$ids);
            }
        }
        $statuses = array_map(static fn ($one) => match ((string) $one) {
            'hold', '0' => '0',
            'approve', '1' => '1',
            'all', '' => 'all',
            default => (string) $one,
        }, (array) $args['status']);
        if (!in_array('all', $statuses, true) && !in_array('any', $statuses, true)) {
            $where[] = 'c.comment_approved IN (' . self::marks(count($statuses)) . ')';
            array_push($params, ...$statuses);
        } elseif (in_array('all', $statuses, true)) {
            $where[] = "c.comment_approved IN ('0', '1')";
        }
        if ($args['parent'] !== '' && $args['parent'] !== null) {
            $where[] = 'c.comment_parent = ?';
            $params[] = (int) $args['parent'];
        }
        if ($args['type'] !== '') {
            $types = array_map('strval', (array) $args['type']);
            $where[] = 'c.comment_type IN (' . self::marks(count($types)) . ')';
            array_push($params, ...$types);
        }
        if ($args['author_email'] !== '') {
            $where[] = 'c.comment_author_email = ?';
            $params[] = (string) $args['author_email'];
        }
        if ($args['user_id'] !== '' && $args['user_id'] !== null) {
            $where[] = 'c.user_id = ?';
            $params[] = (int) $args['user_id'];
        }
        if ($args['search'] !== '') {
            $needle = '%' . addcslashes((string) $args['search'], '%_\\') . '%';
            $where[] = '(c.comment_author LIKE ? OR c.comment_author_email LIKE ? OR c.comment_author_url LIKE ? OR c.comment_author_IP LIKE ? OR c.comment_content LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        return [$where === [] ? '1=1' : implode(' AND ', $where), $params];
    }

    /** The counts wp_count_comments reports, for one post or the site. @return array<string, int> */
    public function breakdown(int $postId): array
    {
        $sql = "SELECT comment_approved, COUNT(*) AS total FROM {$this->db->table('comments')}";
        $params = [];
        if ($postId > 0) {
            $sql .= ' WHERE comment_post_ID = ?';
            $params[] = $postId;
        }
        $counts = ['approved' => 0, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'all' => 0, 'total_comments' => 0, 'moderated' => 0];
        $keys = ['1' => 'approved', '0' => 'moderated', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed'];
        foreach ($this->db->rows($sql . ' GROUP BY comment_approved', $params) as $row) {
            $key = $keys[(string) $row['comment_approved']] ?? null;
            if ($key !== null) {
                $counts[$key] += (int) $row['total'];
            }
        }
        $counts['all'] = $counts['approved'] + $counts['moderated'];
        $counts['total_comments'] = $counts['all'] + $counts['spam'];
        return $counts;
    }

    private static function marks(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }
}
