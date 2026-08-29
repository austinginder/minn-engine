<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Db;

/**
 * Term reads in the shapes plugin code asks for: get_terms() arguments to
 * rows, the tree filters (child_of, exclude_tree), the fields shapes, and
 * the single-term lookups. Behaviour pinned by contracts/fixtures/api/content.json.
 */
final readonly class TermQuery
{
    public const DEFAULTS = ['taxonomy' => null, 'object_ids' => null, 'orderby' => 'name', 'order' => 'ASC', 'hide_empty' => true, 'include' => [], 'exclude' => [], 'exclude_tree' => [], 'number' => '', 'offset' => '', 'fields' => 'all', 'count' => false, 'name' => '', 'slug' => '', 'term_taxonomy_id' => '', 'hierarchical' => true, 'search' => '', 'name__like' => '', 'description__like' => '', 'pad_counts' => false, 'get' => '', 'child_of' => 0, 'parent' => '', 'childless' => false, 'cache_domain' => 'core', 'update_term_meta_cache' => true, 'meta_query' => '', 'meta_key' => '', 'meta_value' => ''];

    private const COLUMNS = 't.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count';

    /** @param Closure(string): string $slug the slug sanitiser, so the reference's filters apply */
    public function __construct(private Db $db, private Closure $slug)
    {
    }

    /** A term row joined with its taxonomy row, by id (and taxonomy when known). @return array<string, mixed>|null */
    public function row(int $termId, ?string $taxonomy): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . " FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE t.term_id = ?";
        $params = [$termId];
        if ($taxonomy !== null) {
            $sql .= ' AND tt.taxonomy = ?';
            $params[] = $taxonomy;
        }
        return $this->db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
    }

    /**
     * The term id and taxonomy a field value names; null when nothing matches
     * or the field is not one a term can be found by.
     *
     * @return array{term_id: int, taxonomy: string}|null
     */
    public function find(string $field, mixed $value, ?string $taxonomy): ?array
    {
        $column = match ($field) {
            'slug' => 't.slug',
            'name' => 't.name',
            'term_taxonomy_id' => 'tt.term_taxonomy_id',
            'id', 'ID', 'term_id' => 't.term_id',
            default => null,
        };
        if ($column === null) {
            return null;
        }
        if ($field === 'slug') {
            $value = ($this->slug)((string) $value);
        }
        if ($value === '' || $value === null) {
            return null;
        }
        $sql = "SELECT t.term_id, tt.taxonomy FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE {$column} = ?";
        $params = [$value];
        if ($taxonomy !== null) {
            $sql .= ' AND tt.taxonomy = ?';
            $params[] = $taxonomy;
        }
        $row = $this->db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
        return $row === null ? null : ['term_id' => (int) $row['term_id'], 'taxonomy' => (string) $row['taxonomy']];
    }

    /**
     * Whether a term (by id, or by slug or name) exists, optionally under a
     * taxonomy and parent; the ids when it does.
     *
     * @return array{term_id: int, term_taxonomy_id: int}|null
     */
    public function exists(int|string $term, ?string $taxonomy, ?int $parent): ?array
    {
        $sql = "SELECT t.term_id, tt.term_taxonomy_id FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id WHERE ";
        $params = [];
        if (is_int($term) || ctype_digit($term)) {
            $sql .= 't.term_id = ?';
            $params[] = (int) $term;
        } else {
            $sql .= '(t.slug = ? OR t.name = ?)';
            $params[] = ($this->slug)($term);
            $params[] = $term;
        }
        if ($taxonomy !== null) {
            $sql .= ' AND tt.taxonomy = ?';
            $params[] = $taxonomy;
            if ($parent !== null && $parent > 0) {
                $sql .= ' AND tt.parent = ?';
                $params[] = $parent;
            }
        }
        $row = $this->db->row($sql . ' ORDER BY tt.term_taxonomy_id ASC LIMIT 1', $params);
        return $row === null ? null : ['term_id' => (int) $row['term_id'], 'term_taxonomy_id' => (int) $row['term_taxonomy_id']];
    }

    /** get_terms() arguments normalised: the "get all" shortcut, integer lists, sanitised slugs. */
    public function normalise(array $args): array
    {
        $args += self::DEFAULTS;
        if ($args['get'] === 'all') {
            $args['hide_empty'] = false;
            $args['childless'] = false;
            $args['child_of'] = 0;
            $args['pad_counts'] = false;
        }
        return $args;
    }

    /** How many terms the arguments match. */
    public function count(array $args, ?array $taxonomies): int
    {
        [$join, $clause, $params] = $this->where($args, $taxonomies);
        return (int) $this->db->value("SELECT COUNT(DISTINCT tt.term_taxonomy_id) FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id {$join} WHERE {$clause}", $params);
    }

    /**
     * The term rows the arguments match, ordered and paged, with the tree
     * filters applied.
     *
     * @param list<string>|null $taxonomies
     * @return list<array<string, mixed>>
     */
    public function rows(array $args, ?array $taxonomies): array
    {
        [$join, $clause, $params] = $this->where($args, $taxonomies);
        $order = strtoupper((string) $args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $include = self::ids($args['include']);
        $orderby = match ((string) $args['orderby']) {
            'name' => 't.name',
            'slug' => 't.slug',
            'term_group' => 't.term_group',
            'term_id', 'id' => 't.term_id',
            'count' => 'tt.count',
            'description' => 'tt.description',
            'parent' => 'tt.parent',
            'term_taxonomy_id' => 'tt.term_taxonomy_id',
            'term_order' => $join !== '' ? 'tr.term_order' : 't.name',
            'include' => $include !== [] ? 'FIELD(t.term_id,' . implode(',', $include) . ')' : 't.name',
            'slug__in' => 't.slug',
            'none' => '',
            default => 't.name',
        };
        $orderClause = $orderby === '' ? '' : " ORDER BY {$orderby} {$order}";
        $limit = '';
        $limitParams = [];
        if ($args['number'] !== '' && (int) $args['number'] > 0) {
            $limit = ' LIMIT ? OFFSET ?';
            $limitParams = [(int) $args['number'], (int) $args['offset']];
        } elseif ($args['offset'] !== '' && (int) $args['offset'] > 0) {
            $limit = ' LIMIT 18446744073709551615 OFFSET ?';
            $limitParams = [(int) $args['offset']];
        }
        $rows = $this->db->rows('SELECT DISTINCT ' . self::COLUMNS . " FROM {$this->db->table('terms')} t JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id {$join} WHERE {$clause}{$orderClause}{$limit}", [...$params, ...$limitParams]);
        $rows = array_map(static fn (array $r) => ['term_id' => (int) $r['term_id'], 'term_taxonomy_id' => (int) $r['term_taxonomy_id'], 'parent' => (int) $r['parent'], 'count' => (int) $r['count'], 'term_group' => (int) $r['term_group']] + $r, $rows);
        if ((int) $args['child_of'] > 0) {
            $rows = self::descendants($rows, (int) $args['child_of']);
        }
        foreach ((array) $args['exclude_tree'] as $tree) {
            $excluded = array_map(static fn (array $r) => $r['term_id'], self::descendants($rows, (int) $tree));
            $excluded[] = (int) $tree;
            $rows = array_values(array_filter($rows, static fn (array $r) => !in_array($r['term_id'], $excluded, true)));
        }
        return $rows;
    }

    /** The rows under a term, however deep, in source order. @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private static function descendants(array $rows, int $root): array
    {
        $wanted = [$root];
        $kept = [];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($rows as $row) {
                if (in_array($row['parent'], $wanted, true) && !in_array($row['term_id'], $wanted, true)) {
                    $kept[] = $row;
                    $wanted[] = $row['term_id'];
                    $changed = true;
                }
            }
        }
        return $kept;
    }

    /** @return array{0: string, 1: string, 2: list<mixed>} the join, the WHERE clause, its parameters */
    private function where(array $args, ?array $taxonomies): array
    {
        $where = [];
        $params = [];
        $join = '';
        if ($taxonomies !== null) {
            $where[] = 'tt.taxonomy IN (' . self::marks(count($taxonomies)) . ')';
            array_push($params, ...$taxonomies);
        }
        $objectIds = $args['object_ids'] === null ? [] : array_map('intval', (array) $args['object_ids']);
        if ($objectIds !== []) {
            $join = "JOIN {$this->db->table('term_relationships')} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id";
            $where[] = 'tr.object_id IN (' . self::marks(count($objectIds)) . ')';
            array_push($params, ...$objectIds);
        }
        if ($args['hide_empty'] && $objectIds === []) {
            $where[] = 'tt.count > 0';
        }
        foreach (['include' => 't.term_id IN', 'exclude' => 't.term_id NOT IN'] as $key => $test) {
            $ids = self::ids($args[$key]);
            if ($ids !== []) {
                $where[] = $test . ' (' . self::marks(count($ids)) . ')';
                array_push($params, ...$ids);
            }
        }
        if ($args['name'] !== '' && $args['name'] !== []) {
            $names = array_map('strval', (array) $args['name']);
            $where[] = 't.name IN (' . self::marks(count($names)) . ')';
            array_push($params, ...$names);
        }
        if ($args['slug'] !== '' && $args['slug'] !== []) {
            $slugs = array_map(fn ($s) => ($this->slug)((string) $s), (array) $args['slug']);
            $where[] = 't.slug IN (' . self::marks(count($slugs)) . ')';
            array_push($params, ...$slugs);
        }
        if ($args['term_taxonomy_id'] !== '' && $args['term_taxonomy_id'] !== []) {
            $ids = array_map('intval', (array) $args['term_taxonomy_id']);
            $where[] = 'tt.term_taxonomy_id IN (' . self::marks(count($ids)) . ')';
            array_push($params, ...$ids);
        }
        if ($args['parent'] !== '' && $args['parent'] !== null) {
            $where[] = 'tt.parent = ?';
            $params[] = (int) $args['parent'];
        }
        if ($args['search'] !== '') {
            $needle = self::like((string) $args['search']);
            $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
            array_push($params, $needle, $needle);
        }
        if ($args['name__like'] !== '') {
            $where[] = 't.name LIKE ?';
            $params[] = self::like((string) $args['name__like']);
        }
        if ($args['description__like'] !== '') {
            $where[] = 'tt.description LIKE ?';
            $params[] = self::like((string) $args['description__like']);
        }
        if ($args['childless']) {
            $where[] = "NOT EXISTS (SELECT 1 FROM {$this->db->table('term_taxonomy')} c WHERE c.parent = t.term_id AND c.taxonomy = tt.taxonomy)";
        }
        foreach ($this->metaClauses($args) as $clause) {
            if (array_key_exists('value', $clause)) {
                $where[] = "EXISTS (SELECT 1 FROM {$this->db->table('termmeta')} m WHERE m.term_id = t.term_id AND m.meta_key = ? AND m.meta_value = ?)";
                $params[] = (string) $clause['key'];
                $params[] = is_array($clause['value']) ? (string) reset($clause['value']) : (string) $clause['value'];
            } else {
                $where[] = "EXISTS (SELECT 1 FROM {$this->db->table('termmeta')} m WHERE m.term_id = t.term_id AND m.meta_key = ?)";
                $params[] = (string) $clause['key'];
            }
        }
        return [$join, $where === [] ? '1=1' : implode(' AND ', $where), $params];
    }

    /** @return list<array{key: string, value?: mixed}> */
    private function metaClauses(array $args): array
    {
        $clauses = is_array($args['meta_query']) ? $args['meta_query'] : [];
        if ($args['meta_key'] !== '') {
            $clauses[] = ['key' => $args['meta_key']] + ($args['meta_value'] !== '' ? ['value' => $args['meta_value']] : []);
        }
        return array_values(array_filter($clauses, static fn ($c) => is_array($c) && isset($c['key'])));
    }

    /** Every term id under a term, however deep. @return list<int> */
    public function children(int $termId, string $taxonomy): array
    {
        $out = [];
        $queue = [$termId];
        while ($queue !== []) {
            $rows = $this->db->rows("SELECT term_id FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ? AND parent IN (" . self::marks(count($queue)) . ')', [$taxonomy, ...$queue]);
            $queue = [];
            foreach ($rows as $row) {
                if (!in_array((int) $row['term_id'], $out, true)) {
                    $out[] = (int) $row['term_id'];
                    $queue[] = (int) $row['term_id'];
                }
            }
        }
        return $out;
    }

    /** The object ids attached to any of the terms in any of the taxonomies. @param list<int> $termIds @param list<string> $taxonomies @return list<int> */
    public function objectsIn(array $termIds, array $taxonomies, string $order): array
    {
        if ($termIds === [] || $taxonomies === []) {
            return [];
        }
        $direction = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $rows = $this->db->rows("SELECT DISTINCT tr.object_id FROM {$this->db->table('term_relationships')} tr JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy IN (" . self::marks(count($taxonomies)) . ') AND tt.term_id IN (' . self::marks(count($termIds)) . ") ORDER BY tr.object_id {$direction}", [...$taxonomies, ...$termIds]);
        return array_map(static fn (array $r) => (int) $r['object_id'], $rows);
    }

    /**
     * The fields shapes get_terms() and wp_get_object_terms() share, over
     * term objects with the reference's public properties.
     *
     * @param list<object> $terms
     */
    public static function shape(array $terms, string $fields): array
    {
        $map = static function (string $key) use ($terms): array {
            $out = [];
            foreach ($terms as $t) {
                $out[$t->term_id] = $t->$key;
            }
            return $out;
        };
        return match ($fields) {
            'ids' => array_map(static fn (object $t) => $t->term_id, $terms),
            'tt_ids' => array_map(static fn (object $t) => $t->term_taxonomy_id, $terms),
            'names' => array_map(static fn (object $t) => $t->name, $terms),
            'slugs' => array_map(static fn (object $t) => $t->slug, $terms),
            'id=>name' => $map('name'),
            'id=>slug' => $map('slug'),
            'id=>parent' => $map('parent'),
            default => array_values($terms),
        };
    }

    /** @return list<int> */
    private static function ids(mixed $list): array
    {
        if ($list === '' || $list === null || $list === []) {
            return [];
        }
        $items = is_array($list) ? $list : preg_split('/[\s,]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_map('intval', $items ?: [])));
    }

    private static function marks(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }

    private static function like(string $needle): string
    {
        return '%' . addcslashes($needle, '%_\\') . '%';
    }
}
