<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Subject;
use Minn\Db;

/**
 * Whether the record a route capture names exists, for the policy gate to
 * ask before it judges the caller. A post kind reads its type from the
 * {base} capture when the route has one, a term kind its taxonomy; the
 * fixed kinds name their own. Status is not consulted: a trashed post
 * exists, and what the caller may do with it is the policy's question.
 */
final readonly class Subjects
{
    private const POST_TYPES = ['posts' => 'post', 'pages' => 'page', 'blocks' => 'wp_block', 'media' => 'attachment', 'navigation' => 'wp_navigation', 'menu-items' => 'nav_menu_item'];
    private const TAXONOMIES = ['categories' => 'category', 'tags' => 'post_tag', 'wp_pattern_category' => 'wp_pattern_category'];

    public function __construct(private Db $db)
    {
    }

    /**
     * Whether the record exists.
     *
     * @param array<string, string> $captures the route's captures, for the {base} a kind reads
     */
    public function exists(Subject $subject, int $id, array $captures): bool
    {
        $base = (string) ($captures['base'] ?? '');
        return match ($subject) {
            Subject::Post, Subject::PostParent => $this->post($id, self::POST_TYPES[$base] ?? 'post'),
            Subject::Attachment => $this->post($id, 'attachment'),
            Subject::Block => $this->post($id, 'wp_block'),
            Subject::Navigation => $this->post($id, 'wp_navigation'),
            Subject::MenuItem => $this->post($id, 'nav_menu_item'),
            Subject::GlobalStyles, Subject::GlobalStylesParent => $this->post($id, 'wp_global_styles'),
            Subject::Term => $this->term($id, self::TAXONOMIES[$base] ?? 'category'),
            Subject::Menu => $this->term($id, 'nav_menu'),
            Subject::User => $this->db->value("SELECT ID FROM {$this->db->table('users')} WHERE ID = ?", [$id]) !== null,
            Subject::Comment => $this->db->value("SELECT comment_ID FROM {$this->db->table('comments')} WHERE comment_ID = ?", [$id]) !== null,
        };
    }

    /** Whether a post of the type exists. */
    public function postOfType(int $id, string $type): bool
    {
        return $this->post($id, $type);
    }

    /** Whether a term of the taxonomy exists. */
    public function termOf(int $id, string $taxonomy): bool
    {
        return $this->term($id, $taxonomy);
    }

    private function post(int $id, string $type): bool
    {
        return $id > 0 && $this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = ?", [$id, $type]) !== null;
    }

    private function term(int $id, string $taxonomy): bool
    {
        return $id > 0 && $this->db->value("SELECT term_id FROM {$this->db->table('term_taxonomy')} WHERE term_id = ? AND taxonomy = ?", [$id, $taxonomy]) !== null;
    }
}
