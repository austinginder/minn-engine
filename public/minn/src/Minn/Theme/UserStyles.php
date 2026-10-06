<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Db;

/**
 * The site editor's saved global styles: one wp_global_styles post per
 * theme, tied to it by the wp_theme term, holding a versioned JSON body
 * of the settings and styles the editor wrote. Every changed save leaves
 * a revision behind, as a post's does.
 */
final readonly class UserStyles
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private PostWriter $writer,
        private Terms $terms,
        private Site $site,
    ) {
    }

    /** The newest published global-styles post carrying the theme's term, or null. */
    public function idFor(string $stylesheet): ?int
    {
        $id = $this->db->value(
            "SELECT p.ID FROM {$this->db->table('posts')} p
             JOIN {$this->db->table('term_relationships')} tr ON tr.object_id = p.ID
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'wp_global_styles' AND p.post_status = 'publish'
               AND tt.taxonomy = 'wp_theme' AND t.slug = ? ORDER BY p.ID DESC LIMIT 1",
            [$stylesheet],
        );
        return $id === null ? null : (int) $id;
    }

    /** The theme's post, made the way the reference makes it when there is none yet. */
    public function ensure(string $stylesheet, int $userId): int
    {
        $found = $this->idFor($stylesheet);
        if ($found !== null) {
            return $found;
        }
        $now = $this->site->localNow();
        $nowGmt = gmdate('Y-m-d H:i:s');
        $id = $this->writer->insert([
            'post_author' => $userId, 'post_date' => $now, 'post_date_gmt' => $nowGmt,
            'post_content' => '{"version": 3, "isGlobalStylesUserThemeJSON": true }',
            'post_title' => 'Custom Styles', 'post_excerpt' => '', 'post_status' => 'publish',
            'comment_status' => 'closed', 'ping_status' => 'closed', 'post_password' => '',
            'post_name' => $this->writer->uniqueSlug('wp-global-styles-' . $stylesheet, 0, 'wp_global_styles'),
            'to_ping' => '', 'pinged' => '', 'post_modified' => $now, 'post_modified_gmt' => $nowGmt,
            'post_content_filtered' => '', 'post_parent' => 0, 'guid' => '', 'menu_order' => 0,
            'post_type' => 'wp_global_styles', 'post_mime_type' => '', 'comment_count' => 0,
        ]);
        $this->writer->update($id, ['guid' => rtrim((string) $this->site->option('home'), '/') . '/?p=' . $id]);
        $term = $this->terms->findBySlug('wp_theme', $stylesheet);
        $this->writer->setTerms($id, 'wp_theme', [$term === null ? $this->terms->create($stylesheet, $stylesheet, 'wp_theme', '', 0) : (int) $term['term_id']]);
        return $id;
    }

    /** The post when it is a global-styles post, else null. */
    public function find(int $id): ?PostRecord
    {
        $post = $this->posts->find($id);
        return $post !== null && $post->type === 'wp_global_styles' ? $post : null;
    }

    /**
     * What a global-styles body holds; a missing, malformed, or list-shaped
     * node is empty, as the reference reads it.
     *
     * @return array{settings: array, styles: array}
     */
    public static function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        $node = static function (string $key) use ($decoded): array {
            $value = is_array($decoded) ? ($decoded[$key] ?? null) : null;
            return is_array($value) && !array_is_list($value) ? $value : [];
        };
        return ['settings' => $node('settings'), 'styles' => $node('styles')];
    }

    /** True when the body carries nothing at all under settings or styles, malformed or not. */
    public static function isBlank(string $json): bool
    {
        $decoded = json_decode($json, true);
        return !is_array($decoded) || empty($decoded['settings']) && empty($decoded['styles']);
    }

    /**
     * Replaces what the caller names (null keeps the stored value), stamps
     * the modified time, and snapshots a revision when anything changed.
     */
    public function save(int $id, int $userId, ?string $title, ?array $settings, ?array $styles): void
    {
        $post = $this->find($id);
        if ($post === null) {
            return;
        }
        $current = self::decode($post->content);
        $body = json_encode([
            'styles' => $styles ?? $current['styles'],
            'settings' => $settings ?? $current['settings'],
            'isGlobalStylesUserThemeJSON' => true,
            'version' => 3,
        ]);
        $this->writer->update($id, [
            'post_content' => (string) $body,
            'post_modified' => $this->site->localNow(),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
        ] + ($title === null ? [] : ['post_title' => $title]));
        $this->writer->maybeSaveRevision($id, $userId);
    }
}
