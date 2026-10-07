<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/** The approval breakdown wp_count_comments reports (comment lists run through WP_Comment_Query and Minn\Runtime\CommentQueryRunner). */
final readonly class CommentQuery
{
    public function __construct(private Db $db)
    {
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
}
