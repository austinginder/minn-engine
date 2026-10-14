<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\RowCache;
use Minn\Front\TermLists;
use Minn\Objects\Term;

/**
 * What WordPress's template tags tell about a post's terms: its terms of a
 * taxonomy (as a list primed them, else read), its categories with their
 * old field names, its tags, its format, and a category's address and the
 * chain of its parents.
 */
final class TermTags
{
    /**
     * The terms of a post that a list primed or the object cache holds
     * (get_object_term_cache), each through get_term; false when nothing
     * holds them.
     */
    public static function cached(mixed $id, mixed $taxonomy): mixed
    {
        $cached = Handoff::cacheGet((int) $id, 'minn_object_terms');
        if (is_array($cached) && array_key_exists((string) $taxonomy, $cached)) {
            return $cached[(string) $taxonomy];
        }
        $primed = RowCache::peek('post_terms', (int) $id . ':' . $taxonomy);
        if (!is_array($primed)) {
            return false;
        }
        $terms = [];
        foreach ($primed as [$termId]) {
            $term = Records::term((int) $termId, (string) $taxonomy);
            if ($term instanceof Term) {
                $terms[] = $term;
            }
        }
        return $terms;
    }

    /** A post's terms of a taxonomy (get_the_terms), as get_the_terms leaves them; false for none or no post. */
    public static function ofPost(mixed $post, mixed $taxonomy): mixed
    {
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $terms = self::cached($post->ID, $taxonomy);
        if ($terms === false) {
            $terms = TermLookup::objectTerms($post->ID, $taxonomy);
        }
        if (Records::isError($terms)) {
            return $terms;
        }
        $terms = Runtime::hooks()->filter('get_the_terms', [$terms, $post->ID, $taxonomy]);
        return $terms === [] ? false : $terms;
    }

    /** A post's categories (get_the_category), each with its old field names, as get_the_categories leaves them. */
    public static function categories(mixed $postId = false): mixed
    {
        $terms = self::ofPost($postId, 'category');
        if (!is_array($terms)) {
            $terms = [];
        }
        foreach ($terms as $term) {
            self::categoryFields($term);
        }
        return Runtime::hooks()->filter('get_the_categories', [$terms, $postId]);
    }

    /** A post's tags (get_the_tags), as get_the_tags leaves them. */
    public static function tags(mixed $post = 0): mixed
    {
        return Runtime::hooks()->filter('get_the_tags', [self::ofPost($post, 'post_tag')]);
    }

    /** A post's format (get_post_format): its post_format term's slug without the prefix; false for none or a type without formats. */
    public static function format(mixed $post = null): mixed
    {
        $post = Records::post($post);
        if ($post === null || !Registered::supports($post->post_type, 'post-formats')) {
            return false;
        }
        $format = self::ofPost($post->ID, 'post_format');
        if (empty($format)) {
            return false;
        }
        $format = array_shift($format);
        return str_replace('post-format-', '', $format->slug);
    }

    /** A category's old field names beside its term fields (_make_cat_compat), on a term object or a term array alike. */
    public static function categoryFields(mixed &$category): void
    {
        if ($category instanceof Term) {
            $category->cat_ID = $category->term_id;
            $category->category_count = $category->count;
            $category->category_description = $category->description;
            $category->cat_name = $category->name;
            $category->category_nicename = $category->slug;
            $category->category_parent = $category->parent;
        } elseif (is_array($category) && isset($category['term_id'])) {
            $category['cat_ID'] = &$category['term_id'];
            $category['category_count'] = &$category['count'];
            $category['category_description'] = &$category['description'];
            $category['cat_name'] = &$category['name'];
            $category['category_nicename'] = &$category['slug'];
            $category['category_parent'] = &$category['parent'];
        }
    }

    /** A category's address (get_category_link), empty for none. */
    public static function categoryLink(mixed $category): mixed
    {
        $term = $category instanceof Term ? $category : Records::term((int) $category, 'category');
        return $term instanceof Term ? TermLookup::link($term) : '';
    }

    /**
     * A category's parents and itself, outermost first (get_category_parents),
     * as names or slugs, linked or not, with the separator after each; the
     * error for an unknown category.
     */
    public static function categoryParents(mixed $categoryId, mixed $link = false, mixed $separator = '/', mixed $nicename = false, mixed $deprecated = []): mixed
    {
        $term = Records::term((int) $categoryId, 'category');
        if (!$term || Records::isError($term)) {
            return Records::isError($term) ? $term : '';
        }
        $ids = array_reverse(array_map('intval', (array) TermLookup::ancestors((int) $categoryId, 'category', 'taxonomy')));
        $ids[] = (int) $categoryId;
        $line = [];
        foreach ($ids as $id) {
            $row = Records::term($id, 'category');
            if (!$row || Records::isError($row)) {
                continue;
            }
            $line[] = ['name' => $row->name, 'slug' => $row->slug, 'link' => self::categoryLink($id)];
        }
        return TermLists::parentChain($line, (bool) $link, (string) $separator, (bool) $nicename);
    }
}
