<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Content\RowCache;
use Minn\Content\TermRecord;
use Minn\I18n\Gettext;
use Minn\Objects\Term;
use Minn\Support\Lists;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;

/**
 * Terms looked up as WordPress's taxonomy functions find them: a list by
 * query arguments, one by a field, whether one exists, a taxonomy's
 * hierarchy, an object's terms, a term's address and one of its fields, its
 * children and ancestors, how many there are, and the caches cleaned after
 * a change; each with the filters plugins hook there.
 */
final class TermLookup
{
    /**
     * Terms by query arguments (get_terms), or the old ($taxonomy, $args)
     * shape; an error for an unknown taxonomy; get_terms filters the list
     * unless suppressed (a count never). The engine may hand an answer for
     * the query it writes (QueryAnswers).
     */
    public static function terms(mixed $args = [], mixed $deprecated = '', ?Closure $answer = null): mixed
    {
        $query = Handoff::newTermQuery();
        if ($answer !== null) {
            QueryAnswers::expect($query, $answer);
        }
        // The legacy shape ($taxonomy, $args): a second argument, or a first that shares no key with the defaults.
        $parsed = Lists::args($args);
        $legacy = $deprecated || array_intersect_key($query->query_var_defaults, (array) $parsed) === [];
        if ($legacy) {
            $taxonomies = (array) $args;
            $args = Lists::args($deprecated, ['suppress_filter' => false]);
            $args['taxonomy'] = $taxonomies;
        } else {
            $args = Lists::args($args, ['suppress_filter' => false]);
            $args['taxonomy'] = isset($args['taxonomy']) ? (array) $args['taxonomy'] : null;
        }
        foreach ((array) $args['taxonomy'] as $taxonomy) {
            if (!Registered::taxonomyExists($taxonomy)) {
                return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
            }
        }
        $suppress = $args['suppress_filter'];
        unset($args['suppress_filter']);
        $terms = $query->query($args);
        // A count is not filtered, as the reference has never filtered it.
        if (!is_array($terms) || $suppress) {
            return $terms;
        }
        return Runtime::hooks()->filter('get_terms', [$terms, $query->query_vars['taxonomy'], $query->query_vars, $query]);
    }

    /**
     * A term by slug, name, id or term_taxonomy_id (get_term_by), or false.
     * As the reference finds one (probe term-lookup): by id through get_term;
     * by the other three through a terms query of one result in no order
     * (the taxonomy left out for a term_taxonomy_id), and the term it finds
     * through get_term again; a slug or name needs a taxonomy that exists and
     * a value. The theme's own term, once its templates are read, and a slug
     * a taxonomy query has just found are answered without the database.
     */
    public static function by(mixed $field, mixed $value, mixed $taxonomy = '', mixed $output = 'OBJECT', mixed $filter = 'raw'): mixed
    {
        $field = (string) $field;
        if (in_array($field, ['id', 'ID', 'term_id'], true)) {
            $term = Records::term((int) $value, $taxonomy, $output, $filter);
            return $term === null || Records::isError($term) ? false : $term;
        }
        if (!in_array($field, ['slug', 'name', 'term_taxonomy_id'], true) || ($field !== 'term_taxonomy_id' && !Registered::taxonomyExists($taxonomy)) || empty($value)) {
            return false;
        }
        $args = ['get' => 'all', 'number' => 1, 'update_term_meta_cache' => false, 'orderby' => 'none', $field => $field === 'term_taxonomy_id' ? (int) $value : $value];
        if ($field !== 'term_taxonomy_id') {
            $args['taxonomy'] = (string) $taxonomy;
        }
        $query = Handoff::newTermQuery();
        if (($field === 'name' || $field === 'slug') && is_string($value)) {
            $key = "{$taxonomy}|{$field}|{$value}";
            QueryAnswers::expect($query, static fn (): ?array => RowCache::peek('term_lookups', $key));
        }
        $terms = $query->query($args);
        if (!is_array($terms) || $terms === []) {
            return false;
        }
        $term = reset($terms);
        $term = Records::term($term, $field === 'term_taxonomy_id' ? (string) $term->taxonomy : $taxonomy, $output, $filter);
        return $term === null || Records::isError($term) ? false : $term;
    }

    /**
     * A taxonomy's term by name as the engine has read it elsewhere (its row,
     * or none when there is no such term), kept so get_term_by answers its
     * lookup for that name without the database.
     *
     * @param array<string, mixed>|null $row
     */
    public static function knownByName(string $taxonomy, string $name, ?array $row): void
    {
        if ($row !== null) {
            TermQuery::keep($row);
            RowCache::put('term_rows', 'row:' . (int) $row['term_id'] . ':', $row);
        }
        RowCache::put('term_lookups', "{$taxonomy}|name|{$name}", $row === null ? [] : [(object) ['term_id' => (string) $row['term_id']]]);
    }

    /**
     * Whether a term exists (term_exists): its id (and term_taxonomy_id when a
     * taxonomy is named), or null. Lookup arguments a plugin changes through
     * term_exists_default_query_args are run as a terms query, by slug then
     * by name.
     */
    public static function exists(mixed $term, mixed $taxonomy = '', mixed $parent = null): mixed
    {
        if ($term === null || $term === '' || $term === 0) {
            return null;
        }
        $tax = $taxonomy === '' || $taxonomy === null ? null : (string) $taxonomy;
        $defaults = ['get' => 'all', 'fields' => 'all', 'number' => 1, 'update_term_meta_cache' => false, 'order' => 'ASC', 'orderby' => 'term_id', 'suppress_filter' => true]
            + ($tax === null ? [] : ['taxonomy' => $tax] + ($parent === null ? [] : ['parent' => (int) $parent]));
        $query = (array) Runtime::hooks()->filter('term_exists_default_query_args', [$defaults, $term, $taxonomy, $parent]);
        if ($query !== $defaults) {
            $terms = is_int($term) ? self::terms(['include' => [$term]] + $query) : self::terms(['slug' => Sanitize::title((string) $term)] + $query);
            if (!is_int($term) && (empty($terms) || Records::isError($terms))) {
                $terms = self::terms(['name' => trim((string) Slashes::strip((string) $term))] + $query);
            }
            $first = is_array($terms) ? (array_values($terms)[0] ?? null) : null;
            if (!$first instanceof Term) {
                return null;
            }
            return $tax === null ? (string) $first->term_id : ['term_id' => (string) $first->term_id, 'term_taxonomy_id' => (string) $first->term_taxonomy_id];
        }
        $found = is_int($term)
            ? self::query()->exists($term, $tax, null)
            : self::lookup(trim((string) Slashes::strip((string) $term)), $tax, $parent === null || $tax === null ? null : (int) $parent);
        if ($found === null) {
            return null;
        }
        if ($tax !== null) {
            // The reference reads the term it found as a term, so get_term and get_{taxonomy} hear it.
            Records::term((int) $found['term_id'], $tax);
        }
        return $tax === null ? (string) $found['term_id'] : ['term_id' => (string) $found['term_id'], 'term_taxonomy_id' => (string) $found['term_taxonomy_id']];
    }

    /**
     * A hierarchical taxonomy's parents and their children (_get_term_hierarchy),
     * built from the terms and kept in {taxonomy}_children when not kept yet.
     *
     * @return array<int, list<int>>
     */
    public static function hierarchy(mixed $taxonomy): array
    {
        if (!Registered::hierarchicalTaxonomy($taxonomy)) {
            return [];
        }
        $children = Runtime::options()->filtered("{$taxonomy}_children");
        if (is_array($children)) {
            return $children;
        }
        $children = self::query()->hierarchy((string) $taxonomy);
        OptionWrites::update("{$taxonomy}_children", $children);
        return $children;
    }

    /**
     * Terms below a term among those given (_get_term_children), following
     * only the terms the stored hierarchy says have children; ids for ids.
     */
    public static function descendants(mixed $termId, mixed $terms, mixed $taxonomy, mixed &$ancestors = []): mixed
    {
        if (empty($terms)) {
            return [];
        }
        $termId = (int) $termId;
        $hasChildren = self::hierarchy((string) $taxonomy);
        if ($termId !== 0 && !isset($hasChildren[$termId])) {
            return [];
        }
        if ($ancestors === []) {
            $ancestors[$termId] = 1;
        }
        $list = [];
        foreach ((array) $terms as $term) {
            $givenId = !is_object($term);
            $term = $givenId ? Records::term($term, (string) $taxonomy) : $term;
            if (Records::isError($term)) {
                return $term;
            }
            if (!is_object($term) || isset($ancestors[(int) $term->term_id]) || (int) $term->parent !== $termId) {
                continue;
            }
            $list[] = $givenId ? (int) $term->term_id : $term;
            if (isset($hasChildren[(int) $term->term_id])) {
                $ancestors[(int) $term->term_id] = 1;
                $list = array_merge($list, (array) self::descendants((int) $term->term_id, $terms, $taxonomy, $ancestors));
            }
        }
        return $list;
    }

    /**
     * Every term below a term (get_term_children), an error for an unknown
     * taxonomy. As the reference walks the stored hierarchy (probe
     * tax-terms): a term's own children, then those of each child that has
     * any, the hierarchy read again for each term walked.
     */
    public static function children(mixed $termId, mixed $taxonomy): mixed
    {
        if (!Registered::taxonomyExists($taxonomy)) {
            return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
        }
        return self::below((int) $termId, (string) $taxonomy, []);
    }

    /**
     * The terms below a term in the stored hierarchy, a term already walked
     * not walked again.
     *
     * @param array<int, true> $walked
     * @return list<int>
     */
    private static function below(int $termId, string $taxonomy, array $walked): array
    {
        $hierarchy = self::hierarchy($taxonomy);
        if (!isset($hierarchy[$termId])) {
            return [];
        }
        $walked[$termId] = true;
        $children = array_map('intval', $hierarchy[$termId]);
        foreach ($children as $child) {
            if (!isset($walked[$child]) && isset($hierarchy[$child])) {
                $children = array_merge($children, self::below($child, $taxonomy, $walked));
            }
        }
        return $children;
    }

    /**
     * The terms of objects in taxonomies (wp_get_object_terms), as
     * wp_get_object_terms_args shapes the query and get_object_terms and
     * wp_get_object_terms leave the list; the engine may hand an answer for
     * its terms query.
     */
    public static function objectTerms(mixed $objectIds, mixed $taxonomies, mixed $args = [], ?Closure $answer = null): mixed
    {
        if (empty($objectIds) || empty($taxonomies)) {
            return [];
        }
        $taxonomies = (array) $taxonomies;
        foreach ($taxonomies as $taxonomy) {
            if (!Registered::taxonomyExists($taxonomy)) {
                return Handoff::newError('invalid_taxonomy', Gettext::text('Invalid taxonomy.'));
            }
        }
        $objectIds = array_map('intval', (array) $objectIds);
        $args = Runtime::hooks()->filter('wp_get_object_terms_args', [Lists::args($args, ['update_term_meta_cache' => false]), $objectIds, $taxonomies]);
        [$terms, $taxonomies, $args] = ObjectTerms::byTaxonomyArgs($objectIds, $taxonomies, $args);
        if ($taxonomies !== []) {
            $rest = self::terms($args, '', $answer);
            $terms = !empty($args['fields']) && str_starts_with((string) $args['fields'], 'id=>') ? $terms + (array) $rest : array_merge($terms, (array) $rest);
        }
        $terms = Runtime::hooks()->filter('get_object_terms', [$terms, $objectIds, $taxonomies, $args]);
        return Runtime::hooks()->filter('wp_get_object_terms', [$terms, implode(',', $objectIds), "'" . implode("', '", array_map(static fn (mixed $taxonomy): mixed => Handoff::escSql($taxonomy), $taxonomies)) . "'", $args]);
    }

    /** One of a term's fields in a context (get_term_field): empty when the term has no such field. */
    public static function field(mixed $field, mixed $term, mixed $taxonomy = '', mixed $context = 'display'): mixed
    {
        $term = Records::term($term, $taxonomy);
        if (!$term instanceof Term) {
            return $term ?? '';
        }
        if (!isset($term->{$field})) {
            return '';
        }
        return TermFields::field((string) $field, $term->{$field}, $term->term_id, $term->taxonomy, (string) $context);
    }

    /**
     * How many terms match (wp_count_terms), the old ($taxonomy, $args) shape
     * read too; empty terms counted.
     */
    public static function count(mixed $args = [], mixed $deprecated = ''): mixed
    {
        if (!is_array($args) || (!isset($args['taxonomy']) && !empty($deprecated))) {
            $legacy = is_array($deprecated) ? $deprecated : [];
            $legacy['taxonomy'] = $args;
            $args = $legacy;
        }
        $args = Lists::args($args, ['hide_empty' => false]);
        $args['fields'] = 'count';
        return self::terms($args);
    }

    /**
     * A term's address (get_term_link): by its taxonomy's link structure
     * through pre_term_link, a changed one filled with its slug (its
     * ancestors' first when the taxonomy nests), by query without one; then
     * tag_link or category_link, and term_link. An error for no term.
     */
    public static function link(mixed $term, mixed $taxonomy = ''): mixed
    {
        if (!$term instanceof Term) {
            $term = is_int($term) ? Records::term($term, $taxonomy) : self::by('slug', $term, $taxonomy);
        }
        if (!$term instanceof Term) {
            return Handoff::newError('invalid_term', 'Empty Term.');
        }
        $permalinks = Runtime::current()->get('permalinks');
        $row = TermRecord::fromRow(self::query()->row($term->term_id, $term->taxonomy) ?? $term->to_array());
        $link = $permalinks === null ? SiteUrls::home('/?' . $term->taxonomy . '=' . $term->slug) : $permalinks->forTerm($row);
        // The taxonomy's link structure through pre_term_link; one a plugin changed is filled
        // with the term's slug, its ancestors' first when the taxonomy nests.
        $rewrite = $GLOBALS['wp_rewrite'] ?? null;
        $structure = $rewrite !== null ? (string) $rewrite->get_extra_permastruct($term->taxonomy) : '';
        $changed = (string) Runtime::hooks()->filter('pre_term_link', [$structure, $term]);
        if ($changed !== $structure && $changed !== '') {
            $slug = $term->slug;
            if (Registered::hierarchicalTaxonomy($term->taxonomy)) {
                foreach (self::ancestors($term->term_id, $term->taxonomy, 'taxonomy') as $ancestor) {
                    $parent = Records::term($ancestor, $term->taxonomy);
                    $slug = $parent instanceof Term ? $parent->slug . '/' . $slug : $slug;
                }
            }
            $link = SiteUrls::home(SiteUrls::trailing(str_replace("%{$term->taxonomy}%", $slug, $changed), 'category'));
        } elseif ($rewrite !== null && ($changed === '' || !$rewrite->using_permalinks())) {
            // A taxonomy with no link structure (one removed at runtime) links by query, as plain permalinks do (probe taxonomy-blocks-user-doubts).
            $link = self::queryLink($term);
        }
        if ($term->taxonomy === 'post_tag') {
            $link = Runtime::hooks()->filter('tag_link', [$link, $term->term_id]);
        } elseif ($term->taxonomy === 'category') {
            $link = Runtime::hooks()->filter('category_link', [$link, $term->term_id]);
        }
        return Runtime::hooks()->filter('term_link', [$link, $term, $term->taxonomy]);
    }

    /**
     * An object's ancestors (get_ancestors): a term's parents up the
     * taxonomy, or a post's up its type; as get_ancestors leaves them.
     *
     * @return array<int, int>
     */
    public static function ancestors(mixed $objectId = 0, mixed $objectType = '', mixed $resourceType = ''): mixed
    {
        $objectId = (int) $objectId;
        if ($objectId === 0) {
            return Runtime::hooks()->filter('get_ancestors', [[], $objectId, $objectType, $resourceType]);
        }
        if ($resourceType === '') {
            $resourceType = Registered::taxonomyExists($objectType) ? 'taxonomy' : (Registered::postTypeExists($objectType) ? 'post_type' : '');
        }
        $ancestors = [];
        if ($resourceType === 'taxonomy') {
            $term = Records::term($objectId, $objectType);
            while ($term instanceof Term && $term->parent > 0 && !in_array($term->parent, $ancestors, true)) {
                $ancestors[] = $term->parent;
                $term = Records::term($term->parent, $objectType);
            }
        } elseif ($resourceType === 'post_type') {
            $ancestors = self::postAncestors($objectId);
        }
        return Runtime::hooks()->filter('get_ancestors', [$ancestors, $objectId, $objectType, $resourceType]);
    }

    /**
     * A post's parents up to the top (get_post_ancestors), a loop broken.
     *
     * @return list<int>
     */
    public static function postAncestors(mixed $post): array
    {
        $post = Records::post($post);
        if ($post === null || (int) $post->post_parent === 0 || (int) $post->post_parent === (int) $post->ID) {
            return [];
        }
        $ancestors = [];
        $id = (int) $post->post_parent;
        while ($id > 0 && !in_array($id, $ancestors, true)) {
            $ancestors[] = $id;
            $parent = Records::post($id);
            $id = $parent === null ? 0 : (int) $parent->post_parent;
        }
        return $ancestors;
    }

    /**
     * Terms forgotten after a change (clean_term_cache): each term's cache,
     * its taxonomy's (unless asked not to), then clean_term_cache; with no
     * taxonomy named, each term's own.
     */
    public static function cleanCache(mixed $ids, mixed $taxonomy = '', mixed $cleanTaxonomy = true): void
    {
        RowCache::forget('post_terms');
        $ids = (array) $ids;
        foreach ($ids as $id) {
            Handoff::cacheDelete((int) $id, 'terms');
            Handoff::cacheDelete((int) $id, 'term_meta');
        }
        if ($taxonomy === '') {
            foreach (array_unique(array_filter(array_map(static fn (mixed $id): mixed => Records::term((int) $id)->taxonomy ?? null, $ids))) as $owner) {
                self::cleanCache($ids, $owner, $cleanTaxonomy);
            }
            return;
        }
        if ($cleanTaxonomy) {
            self::cleanTaxonomy($taxonomy);
        }
        Runtime::hooks()->action('clean_term_cache', [$ids, $taxonomy, $cleanTaxonomy]);
    }

    /** A taxonomy's caches cleaned (clean_taxonomy_cache): its hierarchy rebuilt, the terms' last change moved. */
    public static function cleanTaxonomy(mixed $taxonomy): void
    {
        Handoff::cacheDelete('all_ids', $taxonomy);
        Handoff::cacheDelete('get', $taxonomy);
        OptionWrites::delete("{$taxonomy}_children");
        self::hierarchy($taxonomy);
        Runtime::hooks()->action('clean_taxonomy_cache', [$taxonomy]);
        Handoff::cacheSet('last_changed', microtime(), 'terms');
    }

    /** A term's address by query: ?cat= for a category, the taxonomy's query var, else taxonomy and term. */
    public static function queryLink(Term $term): string
    {
        if ($term->taxonomy === 'category') {
            return SiteUrls::home('?cat=' . $term->term_id);
        }
        $object = Registered::taxonomy($term->taxonomy);
        $var = is_object($object) ? (string) $object->query_var : '';
        return SiteUrls::home($var !== '' ? "?{$var}={$term->slug}" : "?taxonomy={$term->taxonomy}&term={$term->slug}");
    }

    /** A term by slug, then by name, under a parent when one is named. @return array<string, mixed>|null */
    private static function lookup(string $term, ?string $taxonomy, ?int $parent): ?array
    {
        $query = self::query();
        if ($term === '' || ($parent !== null && $parent > 0 && !$query->hasChildren($parent, [$taxonomy]))) {
            return null;
        }
        $slug = Sanitize::title($term);
        $found = $slug === '' ? null : $query->lookup('slug', $slug, $taxonomy, $parent);
        return $found ?? $query->lookup('name', (string) Slashes::strip((string) TermFields::field('name', $term, 0, $taxonomy ?? false, 'db')), $taxonomy, $parent);
    }

    private static function query(): TermQuery
    {
        return new TermQuery(Runtime::current()->db, static fn (string $s): string => Sanitize::title($s));
    }
}
