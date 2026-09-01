<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Which object a query is "about", read from its flags and variables: a term
 * (by id or slug), a post type, the posts page, the current post, or an
 * author. The caller materialises the record; this only decides where to look.
 */
final readonly class QueriedObject
{
    /**
     * @param 'term'|'post_type'|'posts_page'|'post'|'author'|'none' $kind
     * @param string $field 'id' or 'slug' (terms, authors); 'name' for a post type
     */
    private function __construct(public string $kind, public string $field = '', public int|string $value = '', public string $taxonomy = '')
    {
    }

    /**
     * The queried object the query vars and flags point at.
     *
     * @param array<string, mixed> $vars
     * @param array<string, bool> $flags the query's is_* flags
     * @param callable(string): mixed $option a filtered option read
     */
    public static function locate(array $vars, array $flags, Registry $registry, callable $option): self
    {
        $is = static fn (string $flag): bool => !empty($flags[$flag]);
        if ($is('is_category')) {
            return !empty($vars['cat'])
                ? new self('term', 'id', (int) $vars['cat'], 'category')
                : (!empty($vars['category_name']) ? new self('term', 'slug', basename((string) $vars['category_name']), 'category') : new self('none'));
        }
        if ($is('is_tag')) {
            return !empty($vars['tag_id'])
                ? new self('term', 'id', (int) $vars['tag_id'], 'post_tag')
                : (!empty($vars['tag']) ? new self('term', 'slug', (string) $vars['tag'], 'post_tag') : new self('none'));
        }
        if ($is('is_tax')) {
            return self::taxonomyTerm($vars, $registry);
        }
        if ($is('is_post_type_archive')) {
            return new self('post_type', 'name', (string) $vars['post_type']);
        }
        if ($is('is_posts_page')) {
            return new self('posts_page', 'id', (int) $option('page_for_posts'));
        }
        if ($is('is_singular')) {
            return new self('post');
        }
        if ($is('is_author')) {
            return !empty($vars['author'])
                ? new self('author', 'id', (int) $vars['author'])
                : (!empty($vars['author_name']) ? new self('author', 'slug', (string) $vars['author_name']) : new self('none'));
        }
        return new self('none');
    }

    private static function taxonomyTerm(array $vars, Registry $registry): self
    {
        $custom = QueryFlags::customTaxonomyVar($vars, $registry);
        if ($custom !== null) {
            $slugs = explode('/', (string) $vars[$custom[1]]);
            return new self('term', 'slug', (string) end($slugs), $custom[0]);
        }
        foreach ((array) ($vars['tax_query'] ?? []) as $clause) {
            if (is_array($clause) && isset($clause['taxonomy'], $clause['terms'])) {
                $terms = (array) $clause['terms'];
                $field = (string) ($clause['field'] ?? 'term_id');
                $first = reset($terms);
                return $field === 'term_id'
                    ? new self('term', 'id', (int) $first, (string) $clause['taxonomy'])
                    : new self('term', $field, (string) $first, (string) $clause['taxonomy']);
            }
        }
        return new self('none');
    }
}
