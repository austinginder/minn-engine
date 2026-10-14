<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Terms;
use Minn\I18n\Gettext;
use Minn\Objects\Term;
use Minn\Support\Lists;
use Minn\Support\Sanitize;

/**
 * Terms written as WordPress's taxonomy functions write them: a term made,
 * changed and deleted (its children moved up and its default kept), a slug
 * no other term holds, an object's terms set, and the terms' counts kept
 * through the taxonomy's own callback; each step with the actions the
 * reference fires in its order.
 */
final class TermWrites
{
    /** A new term (wp_insert_term), its fields through their filters in the reference's order (Runtime\TermSave). */
    public static function insert(mixed $term, mixed $taxonomy, mixed $args = []): mixed
    {
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        return TermSave::insert($term, (string) $taxonomy, (array) $args);
    }

    /** A term changed (wp_update_term), its fields through their filters in the reference's order (Runtime\TermSave). */
    public static function update(mixed $termId, mixed $taxonomy, mixed $args = []): mixed
    {
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        return TermSave::update((int) $termId, (string) $taxonomy, (array) $args);
    }

    /**
     * A term deleted (wp_delete_term): a taxonomy's default term stays (0);
     * its children move up to its parent, its meta goes row by row, objects
     * left without a category fall back to the default one; false for an
     * unknown term or taxonomy.
     */
    public static function delete(mixed $term, mixed $taxonomy, mixed $args = []): mixed
    {
        if (!Registered::taxonomyExists($taxonomy)) {
            return false;
        }
        $object = Records::term((int) $term, $taxonomy);
        if (!$object instanceof Term) {
            return false;
        }
        $options = Runtime::options();
        $default = (int) $options->filtered('default_category');
        if (($taxonomy === 'category' && $object->term_id === $default) || $object->term_id === (int) $options->filtered("default_term_{$taxonomy}")) {
            return 0;
        }
        $args = Lists::args($args, ['default' => null, 'force_default' => false]);
        $row = self::query()->row($object->term_id, (string) $taxonomy) ?? $object->to_array();
        $hooks = Runtime::hooks();
        $hooks->action('pre_delete_term', [$object->term_id, $taxonomy]);
        $hierarchical = Registered::hierarchicalTaxonomy($taxonomy);
        if ($hierarchical) {
            self::moveChildren($object, (string) $taxonomy);
        }
        foreach ((new Meta(Runtime::current()->db))->rowsOf('term', $object->term_id) as $meta) {
            MetaData::deleteByMid('term', $meta['meta_id']);
        }
        $objects = array_map('strval', self::writer()->delete($row, (string) $taxonomy, $hierarchical, $taxonomy === 'category' ? $default : (int) ($args['default'] ?? 0)));
        $hooks->action('deleted_term_taxonomy', [$object->term_taxonomy_id]);
        TermLookup::cleanCache($object->term_id, $taxonomy);
        $hooks->action('delete_term', [$object->term_id, $object->term_taxonomy_id, $taxonomy, $object, $objects]);
        $hooks->action("delete_{$taxonomy}", [$object->term_id, $object->term_taxonomy_id, $object, $objects]);
        return true;
    }

    /**
     * A slug no other term of the taxonomy holds (wp_unique_term_slug): one
     * taken in the taxonomy gets its parents' slugs added, nearest first,
     * until it is free (wp_unique_term_slug_is_bad_slug decides whether it
     * was taken), then "-2", "-3" past any term holding it in any taxonomy.
     */
    public static function uniqueSlug(mixed $slug, mixed $term): mixed
    {
        $term = (object) $term;
        $original = (string) $slug;
        $taxonomy = (string) ($term->taxonomy ?? '');
        $taken = TermLookup::exists($original) !== null && TermLookup::by('slug', $original, $taxonomy) !== false;
        $slug = $taken ? self::slugUnderParents($original, $term) : $original;
        if (Runtime::hooks()->filter('wp_unique_term_slug_is_bad_slug', [$taken, $original, $term])) {
            $query = self::query();
            $except = (int) ($term->term_id ?? 0);
            if ($query->slugTaken($slug, $except)) {
                $base = $slug;
                for ($n = 2; $query->slugTaken("{$base}-{$n}", $except); $n++) {
                }
                $slug = "{$base}-{$n}";
            }
        } else {
            $slug = $original;
        }
        return Runtime::hooks()->filter('wp_unique_term_slug', [$slug, $term, $original]);
    }

    /**
     * Terms counted (wp_update_term_count): put off while counting is
     * deferred; asked with $deferred, what was put off is counted now.
     */
    public static function updateCount(mixed $terms, mixed $taxonomy, mixed $deferred = false): bool
    {
        if ($deferred) {
            return Deferrals::terms(false);
        }
        $terms = array_values(array_filter(array_map('intval', (array) $terms)));
        if ($terms === []) {
            return false;
        }
        return Deferrals::putOffTerms($terms, (string) $taxonomy) || self::updateCountNow($terms, $taxonomy);
    }

    /**
     * Terms counted now by term_taxonomy id (wp_update_term_count_now)
     * through the taxonomy's count callback, then their caches let go. With
     * no callback of its own, a taxonomy of post types counts published
     * posts; one of other objects counts them all.
     */
    public static function updateCountNow(mixed $terms, mixed $taxonomy): bool
    {
        $terms = array_values(array_unique(array_map('intval', (array) $terms)));
        $tax = Registered::taxonomy($taxonomy);
        if (!$tax) {
            return false;
        }
        $callback = $tax->update_count_callback ?? '';
        if (is_callable($callback)) {
            $callback($terms, $tax);
        } elseif (array_filter((array) $tax->object_type, static fn (mixed $type): bool => Registered::postTypeExists(explode(':', (string) $type)[0])) !== []) {
            self::countPublished($terms, $tax);
        } else {
            self::countAll($terms, $tax);
        }
        TermLookup::cleanCache(self::writer()->termIdsOf($terms), (string) $taxonomy, false);
        return true;
    }

    /** The published objects behind each term_taxonomy id counted (_update_post_term_count); which statuses count is the plugins' to widen. */
    public static function countPublished(mixed $terms, mixed $taxonomy): void
    {
        $taxonomy = is_object($taxonomy) ? $taxonomy : Registered::taxonomy((string) $taxonomy);
        $name = $taxonomy ? (string) $taxonomy->name : '';
        if ($name === '') {
            return;
        }
        $hooks = Runtime::hooks();
        $statuses = array_values(array_map(static fn (mixed $status): string => (string) $status, (array) $hooks->filter('update_post_term_count_statuses', [['publish'], $taxonomy])));
        $writer = self::writer();
        foreach (array_map('intval', (array) $terms) as $ttId) {
            $count = $writer->publishedCount($ttId, $statuses);
            $hooks->action('update_term_count', [$ttId, $name, $count]);
            $hooks->action('edit_term_taxonomy', [$ttId, $name, []]);
            $writer->storeCount($ttId, $count);
            $hooks->action('edited_term_taxonomy', [$ttId, $name, []]);
        }
    }

    /** Every object behind each term_taxonomy id counted, posts or not (_update_generic_term_count). */
    public static function countAll(mixed $terms, mixed $taxonomy): void
    {
        $name = is_object($taxonomy) ? (string) $taxonomy->name : (string) $taxonomy;
        $hooks = Runtime::hooks();
        $writer = self::writer();
        foreach (array_map('intval', (array) $terms) as $ttId) {
            $count = $writer->relationshipCount($ttId);
            $hooks->action('edit_term_taxonomy', [$ttId, $name]);
            $writer->storeCount($ttId, $count);
            $hooks->action('edited_term_taxonomy', [$ttId, $name]);
        }
    }

    /**
     * An object's terms set (wp_set_object_terms), a term named but not
     * there yet made; added to the ones it had when asked to append. The
     * term_taxonomy ids, or the error making one gave.
     */
    public static function setObjectTerms(mixed $objectId, mixed $terms, mixed $taxonomy, mixed $append = false): mixed
    {
        $objectId = (int) $objectId;
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        $terms = array_values(array_filter(array_map(static fn (mixed $t): mixed => is_string($t) ? trim($t) : $t, (array) $terms), static fn (mixed $t): bool => $t !== '' && $t !== null));
        $ttIds = [];
        foreach ($terms as $term) {
            $info = TermLookup::exists($term, $taxonomy);
            if (!$info) {
                if (is_int($term)) {
                    continue;
                }
                $info = self::insert((string) $term, $taxonomy);
                if (Records::isError($info)) {
                    return $info;
                }
            }
            $ttIds[] = $info['term_taxonomy_id'];
        }
        $old = TermLookup::objectTerms($objectId, $taxonomy, ['fields' => 'tt_ids', 'orderby' => 'none']);
        $old = Records::isError($old) ? [] : array_map('intval', $old);
        $keep = $append ? array_values(array_unique([...$old, ...array_map('intval', $ttIds)])) : array_map('intval', $ttIds);
        self::writer()->relate($objectId, $keep, $old, (string) $taxonomy, static fn (array $ids) => self::updateCount($ids, (string) $taxonomy));
        Handoff::cacheDelete($objectId, 'post_meta');
        Runtime::hooks()->action('set_object_terms', [$objectId, $terms, $ttIds, $taxonomy, $append, $old]);
        return $ttIds;
    }

    /** Terms taken off an object (wp_remove_object_terms), the ones left counted again; the error for an unknown taxonomy. */
    public static function removeObjectTerms(mixed $objectId, mixed $terms, mixed $taxonomy): mixed
    {
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        $ttIds = [];
        foreach ((array) $terms as $term) {
            $info = TermLookup::exists($term, $taxonomy);
            if ($info) {
                $ttIds[] = (int) $info['term_taxonomy_id'];
            }
        }
        return self::writer()->unrelate((int) $objectId, $ttIds, (string) $taxonomy, static fn (array $ids) => self::updateCount($ids, (string) $taxonomy));
    }

    /** Every term of the taxonomies taken off an object (wp_delete_object_term_relationships). */
    public static function deleteObjectRelationships(mixed $objectId, mixed $taxonomies): void
    {
        $objectId = (int) $objectId;
        foreach ((array) $taxonomies as $taxonomy) {
            $termIds = TermLookup::objectTerms($objectId, $taxonomy, ['fields' => 'ids']);
            if (Records::isError($termIds) || $termIds === []) {
                continue;
            }
            self::removeObjectTerms($objectId, array_map('intval', $termIds), $taxonomy);
        }
    }

    /** A taken slug with the term's parents' slugs added, nearest first, until no term holds it. */
    private static function slugUnderParents(string $slug, object $term): string
    {
        $taxonomy = (string) ($term->taxonomy ?? '');
        $parentId = Registered::hierarchicalTaxonomy($taxonomy) ? (int) ($term->parent ?? 0) : 0;
        while ($parentId > 0) {
            $parent = Records::term($parentId, $taxonomy);
            if (!$parent instanceof Term) {
                break;
            }
            $slug .= '-' . $parent->slug;
            if (TermLookup::exists($slug) === null) {
                break;
            }
            $parentId = (int) $parent->parent;
        }
        return $slug;
    }

    /** What plugins are told as a deleted term's children move up to its parent (the move itself is the term writer's). */
    private static function moveChildren(Term $term, string $taxonomy): void
    {
        $children = TermLookup::terms(['taxonomy' => $taxonomy, 'parent' => $term->term_id, 'hide_empty' => false, 'fields' => 'all']);
        $children = is_array($children) ? $children : [];
        $ttIds = array_map(static fn (mixed $child): int => (int) $child->term_taxonomy_id, $children);
        Runtime::hooks()->action('edit_term_taxonomies', [$ttIds]);
        TermLookup::cleanCache(array_map(static fn (mixed $child): int => (int) $child->term_id, $children), $taxonomy);
        Runtime::hooks()->action('edited_term_taxonomies', [$ttIds]);
    }

    private static function query(): TermQuery
    {
        return new TermQuery(Runtime::current()->db, static fn (string $s): string => Sanitize::title($s));
    }

    private static function writer(): TermWriter
    {
        $db = Runtime::current()->db;
        $posts = new Posts($db);
        return new TermWriter($db, new Terms($db), self::query(), new PostWriter($db, $posts, Runtime::current()->site));
    }
}
