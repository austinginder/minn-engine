<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Terms;
use Minn\Objects\Errors;
use Minn\Objects\Term;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;
use Minn\Support\Lists;

/**
 * wp_insert_term and wp_update_term in the reference's order (probe
 * term-insert-filters, contracts/runtime.md "Terms plugins can change").
 * An insert asks pre_insert_term, refuses an empty name or a missing
 * parent, runs every field through its db filters (sanitize_term), refuses
 * a name the parent (or, for tags, the taxonomy) already has, makes the
 * slug unique, hands the row to wp_insert_term_data, writes it (a slug left
 * empty becomes the term's id), puts it in the taxonomy, lets a plugin
 * name a duplicate to keep instead, and tells plugins. An update merges
 * the stored term (slashed, so a backslash in it survives) under the
 * changes, runs the same filters, asks wp_update_term_parent (where a
 * parent that would put the term under itself becomes 0), and refuses a
 * slug a sibling holds.
 */
final class TermSave
{
    private const DEFAULTS = ['alias_of' => '', 'description' => '', 'parent' => 0, 'slug' => ''];

    /**
     * A new term's ids, or why not.
     *
     * @param array<string, mixed> $args
     * @return array{term_id: int, term_taxonomy_id: int}|Errors
     */
    public static function insert(mixed $term, string $taxonomy, array $args): array|Errors
    {
        $term = Runtime::hooks()->filter('pre_insert_term', [$term, $taxonomy, $args]);
        if (Records::isError($term)) {
            return $term;
        }
        if ($term === 0) {
            return Handoff::newError('invalid_term_id', 'Invalid term ID.');
        }
        if (trim((string) $term) === '') {
            return Handoff::newError('empty_term_name', 'A name is required for this term.');
        }
        $args = Lists::args($args, self::DEFAULTS);
        if ((int) $args['parent'] > 0 && TermLookup::exists((int) $args['parent']) === null) {
            return Handoff::newError('missing_parent', 'Parent term does not exist.');
        }
        $args['name'] = $term;
        $args['taxonomy'] = $taxonomy;
        $args = (array) TermFields::term($args, (string) $taxonomy, 'db');
        $name = (string) Slashes::strip((string) $args['name']);
        $taken = self::nameTaken($name, $taxonomy, $args);
        if ($taken !== null) {
            return $taken;
        }
        $slug = (string) TermWrites::uniqueSlug(empty($args['slug']) ? Sanitize::title($name) : (string) $args['slug'], (object) $args);
        $data = self::row(Runtime::hooks()->filter('wp_insert_term_data', [['name' => $name, 'slug' => $slug, 'term_group' => 0], $taxonomy, $args]));
        $terms = new Terms(Runtime::current()->db);
        $termId = $terms->insertRow($data);
        if ($data['slug'] === '') {
            self::slugFromId($terms, $termId, $taxonomy, $data, $args);
        }
        $ttId = $terms->addToTaxonomy($termId, $taxonomy, (string) Slashes::strip((string) $args['description']), (int) $args['parent']);
        // Nothing else holds this name and slug now; a plugin may still name a term to keep instead.
        $duplicate = Runtime::hooks()->filter('wp_insert_term_duplicate_term_check', [null, $term, $taxonomy, $args, $ttId]);
        if (is_object($duplicate) && isset($duplicate->term_id)) {
            $terms->dropRows($termId, $ttId);
            TermLookup::cleanCache((int) $duplicate->term_id, $taxonomy);
            return ['term_id' => (int) $duplicate->term_id, 'term_taxonomy_id' => (int) ($duplicate->term_taxonomy_id ?? 0)];
        }
        Runtime::hooks()->action('create_term', [$termId, $ttId, $taxonomy, $args]);
        Runtime::hooks()->action("create_{$taxonomy}", [$termId, $ttId, $args]);
        $termId = (int) Runtime::hooks()->filter('term_id_filter', [$termId, $ttId, $args]);
        TermLookup::cleanCache($termId, $taxonomy);
        Runtime::hooks()->action('created_term', [$termId, $ttId, $taxonomy, $args]);
        Runtime::hooks()->action("created_{$taxonomy}", [$termId, $ttId, $args]);
        Runtime::hooks()->action('saved_term', [$termId, $ttId, $taxonomy, false, $args]);
        Runtime::hooks()->action("saved_{$taxonomy}", [$termId, $ttId, false, $args]);
        return ['term_id' => $termId, 'term_taxonomy_id' => $ttId];
    }

    /**
     * A changed term's ids, or why not.
     *
     * @param array<string, mixed> $changes
     * @return array{term_id: int, term_taxonomy_id: int}|Errors
     */
    public static function update(int $termId, string $taxonomy, array $changes): array|Errors
    {
        $term = Records::term($termId, $taxonomy);
        if (Records::isError($term)) {
            return $term;
        }
        if (!$term instanceof Term) {
            return Handoff::newError('invalid_term', 'Empty Term.');
        }
        $parsed = Lists::args(array_merge((array) Slashes::add($term->to_array()), $changes), self::DEFAULTS);
        $args = (array) TermFields::term($parsed, (string) $taxonomy, 'db');
        $name = (string) Slashes::strip((string) $args['name']);
        $description = (string) Slashes::strip((string) $args['description']);
        $parsed['name'] = $name;
        $parsed['description'] = $description;
        if (trim($name) === '') {
            return Handoff::newError('empty_term_name', 'A name is required for this term.');
        }
        if ((int) $parsed['parent'] > 0 && TermLookup::exists((int) $parsed['parent']) === null) {
            return Handoff::newError('missing_parent', 'Parent term does not exist.');
        }
        $asked = empty($args['slug']) ? '' : (string) $args['slug'];
        $parsed['slug'] = $asked === '' ? Sanitize::title($name) : $asked;
        $parent = (int) Runtime::hooks()->filter('wp_update_term_parent', [$args['parent'], $termId, $taxonomy, $parsed, $args]);
        $slug = self::updatedSlug((string) $parsed['slug'], $asked, $parent, $termId, $args);
        if ($slug instanceof Errors) {
            return $slug;
        }
        return self::write($term, ['name' => $name, 'slug' => $slug, 'term_group' => (int) ($parsed['term_group'] ?? 0)], $description, $parent, $args);
    }

    /**
     * The update's writes and their actions, in the reference's order.
     *
     * @param array{name: string, slug: string, term_group: int} $data
     * @param array<string, mixed> $args
     * @return array{term_id: int, term_taxonomy_id: int}
     */
    private static function write(Term $term, array $data, string $description, int $parent, array $args): array
    {
        [$termId, $ttId, $taxonomy] = [(int) $term->term_id, (int) $term->term_taxonomy_id, (string) $term->taxonomy];
        $terms = new Terms(Runtime::current()->db);
        Runtime::hooks()->action('edit_terms', [$termId, $taxonomy, $args]);
        $data = self::row(Runtime::hooks()->filter('wp_update_term_data', [$data, $termId, $taxonomy, $args]));
        $terms->updateRow($termId, $data);
        if ($data['slug'] === '') {
            $terms->updateRow($termId, ['slug' => Sanitize::title($data['name'], (string) $termId)] + $data);
        }
        Runtime::hooks()->action('edited_terms', [$termId, $taxonomy, $args]);
        Runtime::hooks()->action('edit_term_taxonomy', [$ttId, $taxonomy, $args]);
        $terms->describe($termId, $taxonomy, $description, $parent);
        Runtime::hooks()->action('edited_term_taxonomy', [$ttId, $taxonomy, $args]);
        Runtime::hooks()->action('edit_term', [$termId, $ttId, $taxonomy, $args]);
        Runtime::hooks()->action("edit_{$taxonomy}", [$termId, $ttId, $args]);
        $termId = (int) Runtime::hooks()->filter('term_id_filter', [$termId, $ttId, $args]);
        TermLookup::cleanCache($termId, $taxonomy);
        Runtime::hooks()->action('edited_term', [$termId, $ttId, $taxonomy, $args]);
        Runtime::hooks()->action("edited_{$taxonomy}", [$termId, $ttId, $args]);
        Runtime::hooks()->action('saved_term', [$termId, $ttId, $taxonomy, true, $args]);
        Runtime::hooks()->action("saved_{$taxonomy}", [$termId, $ttId, true, $args]);
        return ['term_id' => $termId, 'term_taxonomy_id' => $ttId];
    }

    /**
     * The refusal for a name already in use: under the same parent in a
     * hierarchical taxonomy (unless a different slug was asked for), or
     * anywhere in a flat one. The lookup sanitizes the name again, as the
     * reference's does.
     *
     * @param array<string, mixed> $args
     */
    private static function nameTaken(string $name, string $taxonomy, array $args): ?Errors
    {
        $matches = TermLookup::terms(['taxonomy' => $taxonomy, 'name' => $name, 'hide_empty' => false, 'parent' => $args['parent'], 'update_term_meta_cache' => false]);
        $match = is_array($matches) ? ($matches[0] ?? null) : null;
        if (!$match instanceof Term) {
            return null;
        }
        if (!Registered::hierarchicalTaxonomy($taxonomy)) {
            return Handoff::newError('term_exists', 'A term with the name provided already exists in this taxonomy.', (int) $match->term_id);
        }
        $asked = (string) ($args['slug'] ?? '');
        if ($asked === '' || $asked === $match->slug) {
            return Handoff::newError('term_exists', 'A term with the name provided already exists with this parent.', (int) $match->term_id);
        }
        return null;
    }

    /**
     * An update's slug: as asked when no other term holds it; made unique
     * when it was made from the name (none was asked for) or the holder
     * sits under another parent; refused otherwise.
     *
     * @param array<string, mixed> $args
     */
    private static function updatedSlug(string $slug, string $asked, int $parent, int $termId, array $args): string|Errors
    {
        $holder = TermLookup::by('slug', $slug, (string) $args['taxonomy']);
        if (!$holder instanceof Term || (int) $holder->term_id === $termId) {
            return $slug;
        }
        if ($asked === '' || $parent !== (int) $holder->parent) {
            return (string) TermWrites::uniqueSlug($slug, (object) $args);
        }
        return Handoff::newError('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $slug));
    }

    /**
     * A slug saving left empty (a name with nothing a slug can keep) becomes
     * the term's id, between edit_terms and edited_terms.
     *
     * @param array{name: string, slug: string, term_group: int} $data
     * @param array<string, mixed> $args
     */
    private static function slugFromId(Terms $terms, int $termId, string $taxonomy, array $data, array $args): void
    {
        Runtime::hooks()->action('edit_terms', [$termId, $taxonomy, $args]);
        $terms->updateRow($termId, ['slug' => Sanitize::title('', (string) $termId)] + $data);
        Runtime::hooks()->action('edited_terms', [$termId, $taxonomy, $args]);
    }

    /**
     * The row a plugin's filter handed back, with the columns it dropped
     * put back.
     *
     * @return array{name: string, slug: string, term_group: int}
     */
    private static function row(mixed $data): array
    {
        $data = is_array($data) ? $data : [];
        return ['name' => (string) ($data['name'] ?? ''), 'slug' => (string) ($data['slug'] ?? ''), 'term_group' => (int) ($data['term_group'] ?? 0)];
    }
}
