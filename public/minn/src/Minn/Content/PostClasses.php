<?php

declare(strict_types=1);

namespace Minn\Content;

/**
 * The class list a post carries on its article element, in the reference's
 * order: caller extras, identity, type, status, format, password state,
 * thumbnail, sticky, hentry, then one class per term of every public
 * taxonomy (post_tag reads as "tag-", post_format is the format above).
 */
final class PostClasses
{
    /**
     * @param list<string> $extra
     * @param list<array{taxonomy: string, slug: string, term_id: int}> $terms
     * @return list<string>
     */
    public static function build(
        array $post,
        array $extra,
        ?string $format,
        bool $thumbnail,
        bool $sticky,
        bool $passwordRequired,
        bool $hasPassword,
        array $terms,
    ): array {
        $type = (string) $post['post_type'];
        $classes = $extra;
        $classes[] = 'post-' . (int) $post['ID'];
        $classes[] = $type;
        $classes[] = 'type-' . $type;
        $classes[] = 'status-' . (string) $post['post_status'];
        if ($format !== null) {
            $classes[] = 'format-' . ($format === '' ? 'standard' : $format);
        }
        if ($hasPassword) {
            $classes[] = $passwordRequired ? 'post-password-required' : 'post-password-protected';
        }
        if ($thumbnail) {
            $classes[] = 'has-post-thumbnail';
        }
        if ($sticky) {
            $classes[] = 'sticky';
        }
        $classes[] = 'hentry';
        foreach ($terms as $term) {
            if ($term['taxonomy'] === 'post_format') {
                continue;
            }
            $prefix = $term['taxonomy'] === 'post_tag' ? 'tag' : $term['taxonomy'];
            $slug = self::htmlClass($term['slug']);
            $classes[] = $prefix . '-' . ($slug === '' || is_numeric($slug) ? (string) $term['term_id'] : $slug);
        }
        return $classes;
    }

    /** The reference keeps letters, digits, hyphens and underscores in a class name. */
    public static function htmlClass(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', strip_tags($value));
    }
}
