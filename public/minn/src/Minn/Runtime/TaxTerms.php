<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\RowCache;
use Minn\I18n\Gettext;

/**
 * The terms a taxonomy query's clause names, found as the reference finds
 * them (probe tax-terms). The clause's terms are made unique (their keys
 * kept). Under a hierarchical taxonomy that includes children they are
 * turned into term ids, each followed by its children (the children first,
 * then the term, repeats kept). Then they are turned into term_taxonomy_ids.
 * Each turn is one terms query in no order for every term at once: as many
 * as there are terms, or all of them in a hierarchical taxonomy. No turn
 * runs when the terms are already in the field wanted. An AND clause fails
 * when a turn finds fewer terms than it has. The engine answers a turn
 * from what it has just read, without the database: one by a name it knows
 * (the theme's own term), and the second turn when the first found terms
 * with no children; a later lookup by a slug a turn found is answered too.
 */
final class TaxTerms
{
    /**
     * A clause's term_taxonomy_ids for its SQL (get_sql_for_clause), or null
     * when an AND clause names a term there is not.
     *
     * @param array<string, mixed> $given the clause: taxonomy, field, terms, include_children
     * @return list<int>|null
     */
    public static function ids(array $given, string $operator): ?array
    {
        $taxonomy = (string) ($given['taxonomy'] ?? '');
        $field = (string) ($given['field'] ?? 'term_id');
        $clause = ['taxonomy' => $taxonomy, 'field' => $field, 'terms' => array_unique((array) ($given['terms'] ?? [])), 'operator' => $operator];
        $known = null;
        if (!empty($given['include_children']) && Registered::hierarchicalTaxonomy($taxonomy)) {
            if (!self::turn($clause, 'term_id')) {
                return self::missing();
            }
            $found = array_map('intval', $clause['terms']);
            $merged = [];
            foreach ($found as $id) {
                $merged = array_merge($merged, (array) TermLookup::children($id, $taxonomy), [$id]);
            }
            // Terms the first turn found, none with children: the second finds them again.
            $known = $field !== 'term_id' && $merged === $found ? $found : null;
            $clause['terms'] = $merged;
        }
        if (!self::turn($clause, 'term_taxonomy_id', $known)) {
            return self::missing();
        }
        return array_map('intval', array_values($clause['terms']));
    }

    /**
     * A clause's terms turned into another field, as a plugin asks
     * (transform_query): an error in the clause's place when an AND clause
     * names a term there is not.
     *
     * @param array<string, mixed>|object $clause
     */
    public static function transform(mixed &$clause, mixed $to): void
    {
        if (is_array($clause) && !self::turn($clause, (string) $to)) {
            $clause = Handoff::newError('inexistent_terms', Gettext::text('Inexistent terms.'));
        }
    }

    /**
     * One turn of a clause's terms into another field; false when an AND
     * clause finds fewer terms than it has.
     *
     * @param array<string, mixed> $clause
     * @param list<int>|null $known the term ids the turn will find, when the engine has just read them
     */
    private static function turn(array &$clause, string $to, ?array $known = null): bool
    {
        $terms = (array) ($clause['terms'] ?? []);
        $field = (string) ($clause['field'] ?? 'term_id');
        if ($terms === [] || $field === $to) {
            return true;
        }
        $taxonomy = (string) ($clause['taxonomy'] ?? '');
        $args = ['taxonomy' => [$taxonomy], 'orderby' => 'none', 'number' => Registered::hierarchicalTaxonomy($taxonomy) ? 0 : count($terms), 'get' => 'all', 'update_term_meta_cache' => false];
        $args[in_array($field, ['slug', 'name', 'term_taxonomy_id'], true) ? $field : 'include'] = array_unique($terms);
        $query = Handoff::newTermQuery();
        $only = reset($terms);
        if ($known !== null) {
            sort($known);
            QueryAnswers::expect($query, static fn (): array => array_map(static fn (int $id): object => (object) ['term_id' => (string) $id], $known));
        } elseif ($field === 'name' && count($terms) === 1 && is_string($only)) {
            QueryAnswers::expect($query, static fn (): ?array => RowCache::peek('term_lookups', "{$taxonomy}|name|{$only}"));
        }
        $found = $query->query($args);
        $found = is_array($found) ? array_values($found) : [];
        if (strtoupper((string) ($clause['operator'] ?? 'IN')) === 'AND' && count($found) < count($terms)) {
            return false;
        }
        if ($field === 'slug') {
            // A slug names one term in its taxonomy, so a later lookup of a slug found here is answered.
            foreach ($found as $term) {
                RowCache::put('term_lookups', "{$taxonomy}|slug|{$term->slug}", [(object) ['term_id' => (string) $term->term_id]]);
            }
        }
        $clause['terms'] = array_map(static fn (object $term): mixed => $term->{$to} ?? null, $found);
        $clause['field'] = $to;
        return true;
    }

    /** A clause naming a term there is not: the reference's message asked for, nothing found. */
    private static function missing(): null
    {
        Gettext::text('Inexistent terms.');
        return null;
    }
}
