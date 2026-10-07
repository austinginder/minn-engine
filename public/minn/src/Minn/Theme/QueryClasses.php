<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostClasses;
use Minn\Db;

/**
 * The body-class tokens the main query stands for, in the reference's
 * order (captured from the oracles): the view tokens (home, blog,
 * privacy-policy, archive, date, search with its results token, paged,
 * attachment, error404), then the singular's or the archive's own, then
 * the numbered paging tokens. Flags combine as the query's do: a search
 * inside a category carries both sets. The bare paged token follows the
 * listing's page; the numbered ones follow a single's page too, and take
 * the view's prefix in one precedence (single, page, category, tag, date,
 * author, search, post type; the front and a plain taxonomy have none).
 * A 404 carries no paging tokens. The theme renderers seat the numbered
 * tokens after the embed token and add the template and theme tokens.
 */
final readonly class QueryClasses
{
    public function __construct(
        private Db $db,
    ) {
    }

    /**
     * The tokens for a main query.
     *
     * @return list<string>
     */
    public function of(\WP_Query $query): array
    {
        $object = $query->get_queried_object();
        $id = (int) $query->get_queried_object_id();
        $views = [
            'home' => $query->is_front_page(),
            'blog' => $query->is_home(),
            'privacy-policy' => $query->is_privacy_policy(),
            'archive' => $query->is_archive(),
            'date' => $query->is_date(),
            'search' => $query->is_search(),
            $query->post_count > 0 ? 'search-results' : 'search-no-results' => $query->is_search(),
            'paged' => $query->is_paged() && !$query->is_404(),
            'attachment' => $query->is_attachment(),
            'error404' => $query->is_404(),
        ];
        $classes = array_keys(array_filter($views));
        if ($query->is_singular() && $object instanceof \WP_Post) {
            array_push($classes, ...$this->singular($object));
        } elseif ($query->is_archive()) {
            array_push($classes, ...self::archive($query, $object, $id));
        }
        return [...$classes, ...self::paging($query)];
    }

    /** @return list<string> */
    private function singular(\WP_Post $post): array
    {
        if ($post->post_type !== 'page') {
            $classes = ['single', 'single-' . PostClasses::htmlClass($post->post_type), 'postid-' . $post->ID];
            if ($post->post_type === 'post') {
                $classes[] = 'single-format-standard';
            }
            if ($post->post_type === 'attachment') {
                $mime = str_replace(['application/', 'image/', 'text/', 'audio/', 'video/', 'music/'], '', (string) $post->post_mime_type);
                array_push($classes, 'attachmentid-' . $post->ID, 'attachment-' . PostClasses::htmlClass($mime));
            }
            return $classes;
        }
        $classes = ['page', 'page-id-' . $post->ID];
        $children = (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_parent = ? AND post_type = 'page' AND post_status = 'publish'",
            [$post->ID],
        );
        if ($children > 0) {
            $classes[] = 'page-parent';
        }
        if ((int) $post->post_parent > 0) {
            array_push($classes, 'page-child', 'parent-pageid-' . (int) $post->post_parent);
        }
        return $classes;
    }

    /** @return list<string> */
    private static function archive(\WP_Query $query, mixed $object, int $id): array
    {
        if ($query->is_post_type_archive()) {
            $type = $query->get('post_type');
            $type = is_array($type) ? (string) reset($type) : (string) $type;
            return ['post-type-archive', 'post-type-archive-' . PostClasses::htmlClass($type)];
        }
        if ($query->is_author()) {
            return $object instanceof \WP_User ? ['author', 'author-' . self::named((string) $object->user_nicename, $id), 'author-' . $id] : ['author'];
        }
        if (!$object instanceof \WP_Term) {
            return [];
        }
        $slug = self::named((string) $object->slug, $id);
        return match (true) {
            $query->is_category() => ['category', 'category-' . $slug, 'category-' . $id],
            $query->is_tag() => ['tag', 'tag-' . $slug, 'tag-' . $id],
            $query->is_tax() => ['tax-' . PostClasses::htmlClass((string) $object->taxonomy), 'term-' . $slug, 'term-' . $id],
            default => [],
        };
    }

    /**
     * The numbered paging tokens: paged-N and the view's own.
     *
     * @return list<string>
     */
    private static function paging(\WP_Query $query): array
    {
        $page = max((int) $query->get('paged'), (int) $query->get('page'));
        if ($page < 2 || $query->is_404()) {
            return [];
        }
        $prefix = match (true) {
            $query->is_single() => 'single',
            $query->is_page() => 'page',
            $query->is_category() => 'category',
            $query->is_tag() => 'tag',
            $query->is_date() => 'date',
            $query->is_author() => 'author',
            $query->is_search() => 'search',
            $query->is_post_type_archive() => 'post-type',
            default => null,
        };
        return $prefix === null ? ['paged-' . $page] : ['paged-' . $page, $prefix . '-paged-' . $page];
    }

    /** A name as a class token, its id when nothing of it survives. */
    private static function named(string $name, int $id): string
    {
        $class = PostClasses::htmlClass($name);
        return $class === '' ? (string) $id : $class;
    }
}
