<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Terms;
use Minn\Support\Slashes;

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
     * @return array{term_id: int, term_taxonomy_id: int}|\WP_Error
     */
    public static function insert(mixed $term, string $taxonomy, array $args): array|\WP_Error
    {
        $term = \apply_filters('pre_insert_term', $term, $taxonomy, $args);
        if (\is_wp_error($term)) {
            return $term;
        }
        if ($term === 0) {
            return new \WP_Error('invalid_term_id', 'Invalid term ID.');
        }
        if (trim((string) $term) === '') {
            return new \WP_Error('empty_term_name', 'A name is required for this term.');
        }
        $args = \wp_parse_args($args, self::DEFAULTS);
        if ((int) $args['parent'] > 0 && \term_exists((int) $args['parent']) === null) {
            return new \WP_Error('missing_parent', 'Parent term does not exist.');
        }
        $args['name'] = $term;
        $args['taxonomy'] = $taxonomy;
        $args = (array) \sanitize_term($args, $taxonomy, 'db');
        $name = (string) Slashes::strip((string) $args['name']);
        $taken = self::nameTaken($name, $taxonomy, $args);
        if ($taken !== null) {
            return $taken;
        }
        $slug = (string) \wp_unique_term_slug(empty($args['slug']) ? \sanitize_title($name) : (string) $args['slug'], (object) $args);
        $data = self::row(\apply_filters('wp_insert_term_data', ['name' => $name, 'slug' => $slug, 'term_group' => 0], $taxonomy, $args));
        $terms = new Terms(Runtime::current()->db);
        $termId = $terms->insertRow($data);
        if ($data['slug'] === '') {
            self::slugFromId($terms, $termId, $taxonomy, $data, $args);
        }
        $ttId = $terms->addToTaxonomy($termId, $taxonomy, (string) Slashes::strip((string) $args['description']), (int) $args['parent']);
        // Nothing else holds this name and slug now; a plugin may still name a term to keep instead.
        $duplicate = \apply_filters('wp_insert_term_duplicate_term_check', null, $term, $taxonomy, $args, $ttId);
        if (is_object($duplicate) && isset($duplicate->term_id)) {
            $terms->dropRows($termId, $ttId);
            \clean_term_cache((int) $duplicate->term_id, $taxonomy);
            return ['term_id' => (int) $duplicate->term_id, 'term_taxonomy_id' => (int) ($duplicate->term_taxonomy_id ?? 0)];
        }
        \do_action('create_term', $termId, $ttId, $taxonomy, $args);
        \do_action("create_{$taxonomy}", $termId, $ttId, $args);
        $termId = (int) \apply_filters('term_id_filter', $termId, $ttId, $args);
        \clean_term_cache($termId, $taxonomy);
        \do_action('created_term', $termId, $ttId, $taxonomy, $args);
        \do_action("created_{$taxonomy}", $termId, $ttId, $args);
        \do_action('saved_term', $termId, $ttId, $taxonomy, false, $args);
        \do_action("saved_{$taxonomy}", $termId, $ttId, false, $args);
        return ['term_id' => $termId, 'term_taxonomy_id' => $ttId];
    }

    /**
     * A changed term's ids, or why not.
     *
     * @param array<string, mixed> $changes
     * @return array{term_id: int, term_taxonomy_id: int}|\WP_Error
     */
    public static function update(int $termId, string $taxonomy, array $changes): array|\WP_Error
    {
        $term = \get_term($termId, $taxonomy);
        if (\is_wp_error($term)) {
            return $term;
        }
        if (!$term instanceof \WP_Term) {
            return new \WP_Error('invalid_term', 'Empty Term.');
        }
        $parsed = \wp_parse_args(array_merge((array) Slashes::add($term->to_array()), $changes), self::DEFAULTS);
        $args = (array) \sanitize_term($parsed, $taxonomy, 'db');
        $name = (string) Slashes::strip((string) $args['name']);
        $description = (string) Slashes::strip((string) $args['description']);
        $parsed['name'] = $name;
        $parsed['description'] = $description;
        if (trim($name) === '') {
            return new \WP_Error('empty_term_name', 'A name is required for this term.');
        }
        if ((int) $parsed['parent'] > 0 && \term_exists((int) $parsed['parent']) === null) {
            return new \WP_Error('missing_parent', 'Parent term does not exist.');
        }
        $asked = empty($args['slug']) ? '' : (string) $args['slug'];
        $parsed['slug'] = $asked === '' ? \sanitize_title($name) : $asked;
        $parent = (int) \apply_filters('wp_update_term_parent', $args['parent'], $termId, $taxonomy, $parsed, $args);
        $slug = self::updatedSlug((string) $parsed['slug'], $asked, $parent, $termId, $args);
        if ($slug instanceof \WP_Error) {
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
    private static function write(\WP_Term $term, array $data, string $description, int $parent, array $args): array
    {
        [$termId, $ttId, $taxonomy] = [(int) $term->term_id, (int) $term->term_taxonomy_id, (string) $term->taxonomy];
        $terms = new Terms(Runtime::current()->db);
        \do_action('edit_terms', $termId, $taxonomy, $args);
        $data = self::row(\apply_filters('wp_update_term_data', $data, $termId, $taxonomy, $args));
        $terms->updateRow($termId, $data);
        if ($data['slug'] === '') {
            $terms->updateRow($termId, ['slug' => \sanitize_title($data['name'], (string) $termId)] + $data);
        }
        \do_action('edited_terms', $termId, $taxonomy, $args);
        \do_action('edit_term_taxonomy', $ttId, $taxonomy, $args);
        $terms->describe($termId, $taxonomy, $description, $parent);
        \do_action('edited_term_taxonomy', $ttId, $taxonomy, $args);
        \do_action('edit_term', $termId, $ttId, $taxonomy, $args);
        \do_action("edit_{$taxonomy}", $termId, $ttId, $args);
        $termId = (int) \apply_filters('term_id_filter', $termId, $ttId, $args);
        \clean_term_cache($termId, $taxonomy);
        \do_action('edited_term', $termId, $ttId, $taxonomy, $args);
        \do_action("edited_{$taxonomy}", $termId, $ttId, $args);
        \do_action('saved_term', $termId, $ttId, $taxonomy, true, $args);
        \do_action("saved_{$taxonomy}", $termId, $ttId, true, $args);
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
    private static function nameTaken(string $name, string $taxonomy, array $args): ?\WP_Error
    {
        $matches = \get_terms(['taxonomy' => $taxonomy, 'name' => $name, 'hide_empty' => false, 'parent' => $args['parent'], 'update_term_meta_cache' => false]);
        $match = is_array($matches) ? ($matches[0] ?? null) : null;
        if (!$match instanceof \WP_Term) {
            return null;
        }
        if (!\is_taxonomy_hierarchical($taxonomy)) {
            return new \WP_Error('term_exists', 'A term with the name provided already exists in this taxonomy.', (int) $match->term_id);
        }
        $asked = (string) ($args['slug'] ?? '');
        if ($asked === '' || $asked === $match->slug) {
            return new \WP_Error('term_exists', 'A term with the name provided already exists with this parent.', (int) $match->term_id);
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
    private static function updatedSlug(string $slug, string $asked, int $parent, int $termId, array $args): string|\WP_Error
    {
        $holder = \get_term_by('slug', $slug, (string) $args['taxonomy']);
        if (!$holder instanceof \WP_Term || (int) $holder->term_id === $termId) {
            return $slug;
        }
        if ($asked === '' || $parent !== (int) $holder->parent) {
            return (string) \wp_unique_term_slug($slug, (object) $args);
        }
        return new \WP_Error('duplicate_term_slug', sprintf('The slug &#8220;%s&#8221; is already in use by another term.', $slug));
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
        \do_action('edit_terms', $termId, $taxonomy, $args);
        $terms->updateRow($termId, ['slug' => \sanitize_title('', (string) $termId)] + $data);
        \do_action('edited_terms', $termId, $taxonomy, $args);
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
