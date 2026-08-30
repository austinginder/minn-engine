<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Reader;
use Minn\Db;

/**
 * The query WP_Query runs: its variables become one SELECT over the posts
 * table with the joins the taxonomy, meta, and author conditions need.
 * Shapes and defaults follow contracts/fixtures/api/content.json.
 */
final class PostQuery
{
    /** @var list<string> */
    private array $where = [];
    /** @var list<mixed> */
    private array $params = [];
    /** @var list<string> */
    private array $joins = [];
    private bool $distinct = false;

    public function __construct(private readonly Db $db, private readonly Registry $registry)
    {
    }

    /**
     * @param array<string, mixed> $q
     * @return array{rows: list<array>, found: int, sticky: list<array>}
     */
    public function run(array $q, bool $isHome): array
    {
        $posts = $this->db->table('posts');
        $this->where = [];
        $this->params = [];
        $this->joins = [];
        $this->distinct = false;

        $this->types($q);
        $this->statuses($q);
        $this->singular($q);
        $this->authors($q);
        $this->parents($q);
        $this->ids($q);
        $this->search($q);
        $this->dates($q);
        $this->taxonomies($q);
        $this->meta($q);

        $clause = $this->where === [] ? '1=1' : implode(' AND ', $this->where);
        $join = implode(' ', $this->joins);
        $select = $this->distinct ? 'DISTINCT p.*' : 'p.*';

        $perPage = $this->perPage($q);
        $limit = '';
        $limitParams = [];
        if ($perPage > 0) {
            $offset = isset($q['offset']) && $q['offset'] !== '' ? max(0, (int) $q['offset']) : (max(1, (int) ($q['paged'] ?? 1)) - 1) * $perPage;
            $limit = ' LIMIT ? OFFSET ?';
            $limitParams = [$perPage, $offset];
        }
        [$order, $orderParams] = $this->order($q);
        $rows = $this->db->rows("SELECT {$select} FROM {$posts} p {$join} WHERE {$clause}{$order}{$limit}", [...$this->params, ...$orderParams, ...$limitParams]);
        $found = 0;
        if (empty($q['no_found_rows'])) {
            $found = $limit === '' ? count($rows) : (int) $this->db->value("SELECT COUNT(DISTINCT p.ID) FROM {$posts} p {$join} WHERE {$clause}", $this->params);
        }

        $sticky = [];
        if ($isHome && empty($q['ignore_sticky_posts']) && (int) ($q['paged'] ?? 0) <= 1) {
            $ids = array_map('intval', array_filter((array) Runtime::options()->get('sticky_posts') ?: []));
            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sticky = $this->db->rows("SELECT * FROM {$posts} WHERE ID IN ({$placeholders}) AND post_status = 'publish' ORDER BY post_date DESC", $ids);
            }
        }
        return ['rows' => $rows, 'found' => $found, 'sticky' => $sticky];
    }

    private function perPage(array $q): int
    {
        if (!empty($q['nopaging'])) {
            return 0;
        }
        $perPage = $q['posts_per_page'] ?? '';
        if ($perPage === '' || $perPage === null) {
            $perPage = (int) ($this->db->option('posts_per_page') ?? 10);
        }
        return (int) $perPage < 0 ? 0 : (int) $perPage;
    }

    private function types(array $q): void
    {
        $type = $q['post_type'] ?? '';
        if ($type === '' || $type === null) {
            $type = !empty($q['attachment']) || !empty($q['attachment_id']) ? 'attachment' : ((!empty($q['pagename']) || !empty($q['page_id'])) ? 'page' : (!empty($q['s']) ? 'any' : 'post'));
        }
        if ($type === 'any') {
            $types = [];
            foreach ($this->registry->postTypes() as $name => $row) {
                if (empty($row['exclude_from_search'])) {
                    $types[] = $name;
                }
            }
        } else {
            $types = array_values(array_map('strval', (array) $type));
        }
        if ($types === []) {
            $this->where[] = '1=0';
            return;
        }
        $this->where[] = 'p.post_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        array_push($this->params, ...$types);
    }

    private function statuses(array $q): void
    {
        $status = $q['post_status'] ?? '';
        if ($status === '' || $status === null) {
            $type = (string) (is_array($q['post_type'] ?? null) ? '' : ($q['post_type'] ?? ''));
            if ($type === 'attachment' || !empty($q['attachment']) || !empty($q['attachment_id'])) {
                $statuses = ['inherit'];
            } else {
                $statuses = ['publish'];
                $reader = Reader::current();
                if ($reader->readsPrivatePosts && $type !== 'page' || $reader->readsPrivatePages && $type === 'page') {
                    $statuses[] = 'private';
                }
            }
        } elseif ($status === 'any' || (is_array($status) && in_array('any', $status, true))) {
            $statuses = [];
            foreach ($this->registry->statuses() as $name => $row) {
                if (empty($row['exclude_from_search'])) {
                    $statuses[] = $name;
                }
            }
        } else {
            $statuses = is_array($status) ? array_values(array_map('strval', $status)) : preg_split('/[\s,]+/', (string) $status, -1, PREG_SPLIT_NO_EMPTY);
        }
        $this->where[] = 'p.post_status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        array_push($this->params, ...$statuses);
    }

    private function singular(array $q): void
    {
        if (!empty($q['p'])) {
            $this->where[] = 'p.ID = ?';
            $this->params[] = (int) $q['p'];
        }
        if (!empty($q['page_id'])) {
            $this->where[] = 'p.ID = ?';
            $this->params[] = (int) $q['page_id'];
        }
        if (!empty($q['attachment_id'])) {
            $this->where[] = 'p.ID = ?';
            $this->params[] = (int) $q['attachment_id'];
        }
        if (!empty($q['name'])) {
            $this->where[] = 'p.post_name = ?';
            $this->params[] = (string) $q['name'];
        }
        if (!empty($q['attachment'])) {
            $this->where[] = 'p.post_name = ?';
            $this->params[] = (string) $q['attachment'];
        }
        if (!empty($q['pagename'])) {
            $segments = array_values(array_filter(explode('/', trim((string) $q['pagename'], '/')), static fn ($s) => $s !== ''));
            $page = (new \Minn\Content\Posts($this->db))->pageByPath($segments, false);
            $this->where[] = 'p.ID = ?';
            $this->params[] = $page === null ? 0 : (int) $page['ID'];
        }
        if (!empty($q['title'])) {
            $this->where[] = 'p.post_title = ?';
            $this->params[] = (string) $q['title'];
        }
    }

    private function authors(array $q): void
    {
        if (isset($q['author']) && $q['author'] !== '' && $q['author'] !== null) {
            $ids = array_map('intval', preg_split('/[\s,]+/', (string) $q['author'], -1, PREG_SPLIT_NO_EMPTY));
            $in = array_values(array_filter($ids, static fn (int $id) => $id > 0));
            $out = array_map('abs', array_filter($ids, static fn (int $id) => $id < 0));
            if ($in !== []) {
                $this->where[] = 'p.post_author IN (' . implode(',', array_fill(0, count($in), '?')) . ')';
                array_push($this->params, ...$in);
            }
            if ($out !== []) {
                $this->where[] = 'p.post_author NOT IN (' . implode(',', array_fill(0, count($out), '?')) . ')';
                array_push($this->params, ...$out);
            }
        }
        if (!empty($q['author__in'])) {
            $ids = array_map('intval', (array) $q['author__in']);
            $this->where[] = 'p.post_author IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
        if (!empty($q['author__not_in'])) {
            $ids = array_map('intval', (array) $q['author__not_in']);
            $this->where[] = 'p.post_author NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
        if (!empty($q['author_name'])) {
            $id = $this->db->value("SELECT ID FROM {$this->db->table('users')} WHERE user_nicename = ? LIMIT 1", [(string) $q['author_name']]);
            $this->where[] = 'p.post_author = ?';
            $this->params[] = (int) ($id ?? 0);
        }
    }

    private function parents(array $q): void
    {
        if (isset($q['post_parent']) && $q['post_parent'] !== '' && $q['post_parent'] !== null) {
            $this->where[] = 'p.post_parent = ?';
            $this->params[] = (int) $q['post_parent'];
        }
        if (!empty($q['post_parent__in'])) {
            $ids = array_map('intval', (array) $q['post_parent__in']);
            $this->where[] = 'p.post_parent IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
        if (!empty($q['post_parent__not_in'])) {
            $ids = array_map('intval', (array) $q['post_parent__not_in']);
            $this->where[] = 'p.post_parent NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
    }

    private function ids(array $q): void
    {
        if (!empty($q['post__in'])) {
            $ids = array_map('intval', (array) $q['post__in']);
            $this->where[] = 'p.ID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
        if (!empty($q['post__not_in'])) {
            $ids = array_map('intval', (array) $q['post__not_in']);
            $this->where[] = 'p.ID NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($this->params, ...$ids);
        }
        if (!empty($q['post_name__in'])) {
            $names = array_map('strval', (array) $q['post_name__in']);
            $this->where[] = 'p.post_name IN (' . implode(',', array_fill(0, count($names), '?')) . ')';
            array_push($this->params, ...$names);
        }
        if (!empty($q['post_mime_type'])) {
            $parts = [];
            foreach ((array) $q['post_mime_type'] as $mime) {
                $mime = (string) $mime;
                if (str_contains($mime, '/')) {
                    $parts[] = 'p.post_mime_type = ?';
                    $this->params[] = $mime;
                } else {
                    $parts[] = 'p.post_mime_type LIKE ?';
                    $this->params[] = $mime . '/%';
                }
            }
            $this->where[] = '(' . implode(' OR ', $parts) . ')';
        }
    }

    private function search(array $q): void
    {
        $s = trim((string) ($q['s'] ?? ''));
        if ($s === '') {
            return;
        }
        $terms = !empty($q['sentence']) ? [$s] : preg_split('/[\s,]+/', $s, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $needle = '%' . addcslashes($term, '%_\\') . '%';
            $this->where[] = '(p.post_title LIKE ? OR p.post_excerpt LIKE ? OR p.post_content LIKE ?)';
            array_push($this->params, $needle, $needle, $needle);
        }
        $this->where[] = "p.post_password = ''";
    }

    private function dates(array $q): void
    {
        $year = (int) ($q['year'] ?? 0);
        $month = (int) ($q['monthnum'] ?? 0);
        $day = (int) ($q['day'] ?? 0);
        if (!empty($q['m'])) {
            $m = preg_replace('/[^0-9]/', '', (string) $q['m']);
            $year = (int) substr($m, 0, 4);
            $month = strlen($m) >= 6 ? (int) substr($m, 4, 2) : 0;
            $day = strlen($m) >= 8 ? (int) substr($m, 6, 2) : 0;
        }
        if ($year > 0) {
            $this->where[] = 'YEAR(p.post_date) = ?';
            $this->params[] = $year;
        }
        if ($month > 0) {
            $this->where[] = 'MONTH(p.post_date) = ?';
            $this->params[] = $month;
        }
        if ($day > 0) {
            $this->where[] = 'DAYOFMONTH(p.post_date) = ?';
            $this->params[] = $day;
        }
        foreach ((array) ($q['date_query'] ?? []) as $clause) {
            if (!is_array($clause)) {
                continue;
            }
            $column = in_array($clause['column'] ?? '', ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'], true) ? $clause['column'] : 'post_date';
            if (isset($clause['year'])) {
                $this->where[] = "YEAR(p.{$column}) = ?";
                $this->params[] = (int) $clause['year'];
            }
            if (isset($clause['month'])) {
                $this->where[] = "MONTH(p.{$column}) = ?";
                $this->params[] = (int) $clause['month'];
            }
            if (isset($clause['day'])) {
                $this->where[] = "DAYOFMONTH(p.{$column}) = ?";
                $this->params[] = (int) $clause['day'];
            }
            foreach (['after' => '>', 'before' => '<'] as $key => $operator) {
                if (!isset($clause[$key])) {
                    continue;
                }
                $bound = $clause[$key];
                if (is_array($bound)) {
                    $bound = sprintf('%04d-%02d-%02d %02d:%02d:%02d', (int) ($bound['year'] ?? date('Y')), (int) ($bound['month'] ?? ($key === 'after' ? 1 : 12)), (int) ($bound['day'] ?? ($key === 'after' ? 1 : 31)), (int) ($bound['hour'] ?? ($key === 'after' ? 0 : 23)), (int) ($bound['minute'] ?? ($key === 'after' ? 0 : 59)), (int) ($bound['second'] ?? ($key === 'after' ? 0 : 59)));
                } else {
                    $bound = date('Y-m-d H:i:s', (int) strtotime((string) $bound));
                }
                $operator .= !empty($clause['inclusive']) ? '=' : '';
                $this->where[] = "p.{$column} {$operator} ?";
                $this->params[] = $bound;
            }
        }
    }

    private function taxonomies(array $q): void
    {
        $clauses = [];
        $add = static function (string $taxonomy, string $field, array $terms, string $operator = 'IN', bool $children = true) use (&$clauses): void {
            $clauses[] = ['taxonomy' => $taxonomy, 'field' => $field, 'terms' => $terms, 'operator' => $operator, 'include_children' => $children];
        };
        if (!empty($q['cat'])) {
            $in = [];
            $out = [];
            foreach (preg_split('/[\s,]+/', (string) $q['cat'], -1, PREG_SPLIT_NO_EMPTY) as $id) {
                (int) $id < 0 ? $out[] = abs((int) $id) : $in[] = (int) $id;
            }
            if ($in !== []) {
                $add('category', 'term_id', $in);
            }
            if ($out !== []) {
                $add('category', 'term_id', $out, 'NOT IN');
            }
        }
        if (!empty($q['category_name'])) {
            $slugs = explode(',', (string) $q['category_name']);
            $add('category', 'slug', array_map(static fn ($s) => trim(basename((string) $s)), $slugs));
        }
        foreach (['category__in' => ['category', 'IN'], 'category__not_in' => ['category', 'NOT IN'], 'category__and' => ['category', 'AND'], 'tag__in' => ['post_tag', 'IN'], 'tag__not_in' => ['post_tag', 'NOT IN'], 'tag__and' => ['post_tag', 'AND']] as $var => [$taxonomy, $operator]) {
            if (!empty($q[$var])) {
                $add($taxonomy, 'term_id', array_map('intval', (array) $q[$var]), $operator, $var === 'category__in');
            }
        }
        foreach (['tag_slug__in' => 'IN', 'tag_slug__and' => 'AND'] as $var => $operator) {
            if (!empty($q[$var])) {
                $add('post_tag', 'slug', array_map('strval', (array) $q[$var]), $operator);
            }
        }
        if (!empty($q['tag'])) {
            $tag = (string) $q['tag'];
            if (str_contains($tag, ',')) {
                $add('post_tag', 'slug', array_map('trim', explode(',', $tag)));
            } elseif (str_contains($tag, '+')) {
                $add('post_tag', 'slug', array_map('trim', explode('+', $tag)), 'AND');
            } else {
                $add('post_tag', 'slug', [$tag]);
            }
        }
        if (!empty($q['tag_id'])) {
            $add('post_tag', 'term_id', [(int) $q['tag_id']]);
        }
        foreach ($this->registry->taxonomies() as $name => $taxonomy) {
            $var = $taxonomy['query_var'] ?? false;
            if (is_string($var) && $var !== '' && !in_array($var, ['category_name', 'tag'], true) && !empty($q[$var])) {
                $add($name, 'slug', array_map('trim', explode(',', (string) $q[$var])));
            }
        }
        $relation = 'AND';
        $raw = $q['tax_query'] ?? [];
        if (is_array($raw)) {
            if (isset($raw['relation'])) {
                $relation = strtoupper((string) $raw['relation']) === 'OR' ? 'OR' : 'AND';
                unset($raw['relation']);
            }
            foreach ($raw as $clause) {
                if (is_array($clause) && isset($clause['taxonomy'])) {
                    $add((string) $clause['taxonomy'], (string) ($clause['field'] ?? 'term_id'), array_values((array) ($clause['terms'] ?? [])), strtoupper((string) ($clause['operator'] ?? 'IN')), (bool) ($clause['include_children'] ?? true));
                }
            }
        }
        if ($clauses === []) {
            return;
        }
        $parts = [];
        foreach ($clauses as $clause) {
            $ttids = $this->termTaxonomyIds($clause);
            $relationships = $this->db->table('term_relationships');
            switch ($clause['operator']) {
                case 'NOT IN':
                    if ($ttids === []) {
                        $parts[] = '1=1';
                        break;
                    }
                    $parts[] = 'p.ID NOT IN (SELECT object_id FROM ' . $relationships . ' WHERE term_taxonomy_id IN (' . implode(',', array_fill(0, count($ttids), '?')) . '))';
                    array_push($this->params, ...$ttids);
                    break;
                case 'AND':
                    if ($ttids === []) {
                        $parts[] = '1=0';
                        break;
                    }
                    $parts[] = '(SELECT COUNT(DISTINCT term_taxonomy_id) FROM ' . $relationships . ' WHERE object_id = p.ID AND term_taxonomy_id IN (' . implode(',', array_fill(0, count($ttids), '?')) . ')) = ' . count($ttids);
                    array_push($this->params, ...$ttids);
                    break;
                case 'EXISTS':
                case 'NOT EXISTS':
                    $exists = 'EXISTS (SELECT 1 FROM ' . $relationships . ' tr JOIN ' . $this->db->table('term_taxonomy') . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = p.ID AND tt.taxonomy = ?)';
                    $parts[] = ($clause['operator'] === 'EXISTS' ? '' : 'NOT ') . $exists;
                    $this->params[] = $clause['taxonomy'];
                    break;
                default:
                    if ($ttids === []) {
                        $parts[] = '1=0';
                        break;
                    }
                    $parts[] = 'p.ID IN (SELECT object_id FROM ' . $relationships . ' WHERE term_taxonomy_id IN (' . implode(',', array_fill(0, count($ttids), '?')) . '))';
                    array_push($this->params, ...$ttids);
            }
        }
        $this->where[] = '(' . implode(" {$relation} ", $parts) . ')';
    }

    /** @return list<int> */
    private function termTaxonomyIds(array $clause): array
    {
        $taxonomy = $clause['taxonomy'];
        $terms = $clause['terms'];
        if ($terms === []) {
            return [];
        }
        $column = match ($clause['field']) {
            'slug' => 't.slug',
            'name' => 't.name',
            'term_taxonomy_id' => 'tt.term_taxonomy_id',
            default => 't.term_id',
        };
        $placeholders = implode(',', array_fill(0, count($terms), '?'));
        $rows = $this->db->rows(
            "SELECT tt.term_taxonomy_id, tt.term_id FROM {$this->db->table('term_taxonomy')} tt JOIN {$this->db->table('terms')} t ON t.term_id = tt.term_id WHERE tt.taxonomy = ? AND {$column} IN ({$placeholders})",
            [$taxonomy, ...array_values($terms)],
        );
        $ids = array_map(static fn (array $r) => (int) $r['term_taxonomy_id'], $rows);
        $hierarchical = (bool) ($this->registry->taxonomy($taxonomy)['hierarchical'] ?? false);
        if ($hierarchical && $clause['include_children'] && $clause['operator'] !== 'AND') {
            $parents = array_map(static fn (array $r) => (int) $r['term_id'], $rows);
            while ($parents !== []) {
                $placeholders = implode(',', array_fill(0, count($parents), '?'));
                $children = $this->db->rows("SELECT term_taxonomy_id, term_id FROM {$this->db->table('term_taxonomy')} WHERE taxonomy = ? AND parent IN ({$placeholders})", [$taxonomy, ...$parents]);
                $parents = [];
                foreach ($children as $child) {
                    if (!in_array((int) $child['term_taxonomy_id'], $ids, true)) {
                        $ids[] = (int) $child['term_taxonomy_id'];
                        $parents[] = (int) $child['term_id'];
                    }
                }
            }
        }
        return $ids;
    }

    private function meta(array $q): void
    {
        $clauses = [];
        if (!empty($q['meta_key'])) {
            $clause = ['key' => (string) $q['meta_key']];
            if (isset($q['meta_value']) && $q['meta_value'] !== '') {
                $clause['value'] = $q['meta_value'];
                $clause['compare'] = (string) ($q['meta_compare'] ?? '=');
            } elseif (isset($q['meta_value_num']) && $q['meta_value_num'] !== '') {
                $clause['value'] = $q['meta_value_num'];
                $clause['compare'] = (string) ($q['meta_compare'] ?? '=');
                $clause['type'] = 'NUMERIC';
            } elseif (!empty($q['meta_compare'])) {
                $clause['compare'] = (string) $q['meta_compare'];
            }
            $clauses[] = $clause;
        }
        $relation = 'AND';
        $raw = $q['meta_query'] ?? [];
        if (is_array($raw)) {
            if (isset($raw['relation'])) {
                $relation = strtoupper((string) $raw['relation']) === 'OR' ? 'OR' : 'AND';
                unset($raw['relation']);
            }
            foreach ($raw as $clause) {
                if (is_array($clause) && (isset($clause['key']) || isset($clause['value']))) {
                    $clauses[] = $clause;
                }
            }
        }
        if ($clauses === []) {
            return;
        }
        $parts = [];
        $meta = $this->db->table('postmeta');
        foreach ($clauses as $clause) {
            $key = (string) ($clause['key'] ?? '');
            $compare = strtoupper((string) ($clause['compare'] ?? (isset($clause['value']) && is_array($clause['value']) ? 'IN' : '=')));
            $type = strtoupper((string) ($clause['type'] ?? 'CHAR'));
            $cast = in_array($type, ['NUMERIC', 'DECIMAL', 'SIGNED'], true) ? 'CAST(meta_value AS SIGNED)' : ($type === 'UNSIGNED' ? 'CAST(meta_value AS UNSIGNED)' : 'meta_value');
            $keyClause = $key === '' ? '1=1' : 'meta_key = ?';
            $keyParams = $key === '' ? [] : [$key];
            if ($compare === 'NOT EXISTS') {
                $parts[] = "NOT EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause})";
                array_push($this->params, ...$keyParams);
                continue;
            }
            if ($compare === 'EXISTS' || !array_key_exists('value', $clause)) {
                $parts[] = "EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause})";
                array_push($this->params, ...$keyParams);
                continue;
            }
            $value = $clause['value'];
            switch ($compare) {
                case 'IN':
                case 'NOT IN':
                    $values = array_values((array) $value);
                    $valueClause = "{$cast} {$compare} (" . implode(',', array_fill(0, count($values), '?')) . ')';
                    $valueParams = $values;
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $values = array_values((array) $value);
                    $valueClause = "{$cast} {$compare} ? AND ?";
                    $valueParams = [$values[0] ?? '', $values[1] ?? ''];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                    $valueClause = "{$cast} {$compare} ?";
                    $valueParams = ['%' . addcslashes((string) $value, '%_\\') . '%'];
                    break;
                case 'REGEXP':
                case 'NOT REGEXP':
                case 'RLIKE':
                    $valueClause = "{$cast} {$compare} ?";
                    $valueParams = [(string) $value];
                    break;
                default:
                    $operator = in_array($compare, ['!=', '>', '>=', '<', '<='], true) ? $compare : '=';
                    $valueClause = "{$cast} {$operator} ?";
                    $valueParams = [is_array($value) ? ($value[0] ?? '') : $value];
            }
            $parts[] = "EXISTS (SELECT 1 FROM {$meta} WHERE post_id = p.ID AND {$keyClause} AND {$valueClause})";
            array_push($this->params, ...$keyParams, ...$valueParams);
        }
        $this->where[] = '(' . implode(" {$relation} ", $parts) . ')';
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function order(array $q): array
    {
        $orderby = $q['orderby'] ?? 'date';
        $direction = strtoupper((string) ($q['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $params = [];
        if ($orderby === 'none') {
            return ['', []];
        }
        $pairs = [];
        if (is_array($orderby)) {
            foreach ($orderby as $key => $value) {
                $pairs[] = [(string) $key, strtoupper((string) $value) === 'ASC' ? 'ASC' : 'DESC'];
            }
        } else {
            foreach (preg_split('/\s+/', trim((string) $orderby), -1, PREG_SPLIT_NO_EMPTY) as $key) {
                $pairs[] = [$key, $direction];
            }
        }
        $parts = [];
        foreach ($pairs as [$key, $dir]) {
            $column = match ($key) {
                'date', 'post_date' => 'p.post_date',
                'modified', 'post_modified' => 'p.post_modified',
                'title', 'post_title' => 'p.post_title',
                'name', 'post_name' => 'p.post_name',
                'ID', 'id' => 'p.ID',
                'author', 'post_author' => 'p.post_author',
                'type', 'post_type' => 'p.post_type',
                'parent', 'post_parent' => 'p.post_parent',
                'menu_order' => 'p.menu_order',
                'comment_count' => 'p.comment_count',
                'rand' => 'RAND()',
                'post__in' => !empty($q['post__in']) ? 'FIELD(p.ID,' . implode(',', array_map('intval', (array) $q['post__in'])) . ')' : null,
                'post_name__in' => !empty($q['post_name__in']) ? 'FIELD(p.post_name,' . implode(',', array_map(fn ($n) => "'" . $this->db->connection()->real_escape_string((string) $n) . "'", (array) $q['post_name__in'])) . ')' : null,
                'post_parent__in' => !empty($q['post_parent__in']) ? 'FIELD(p.post_parent,' . implode(',', array_map('intval', (array) $q['post_parent__in'])) . ')' : null,
                'meta_value' => !empty($q['meta_key']) ? '(SELECT meta_value FROM ' . $this->db->table('postmeta') . " WHERE post_id = p.ID AND meta_key = '" . $this->db->connection()->real_escape_string((string) $q['meta_key']) . "' LIMIT 1)" : null,
                'meta_value_num' => !empty($q['meta_key']) ? '(SELECT CAST(meta_value AS SIGNED) FROM ' . $this->db->table('postmeta') . " WHERE post_id = p.ID AND meta_key = '" . $this->db->connection()->real_escape_string((string) $q['meta_key']) . "' LIMIT 1)" : null,
                'relevance' => null,
                default => null,
            };
            if ($column === null) {
                continue;
            }
            $parts[] = in_array($key, ['rand', 'post__in', 'post_name__in', 'post_parent__in'], true) ? $column : "{$column} {$dir}";
        }
        if (!empty($q['s']) && (is_string($orderby) && in_array($orderby, ['date', 'relevance', ''], true))) {
            $needle = '%' . addcslashes(trim((string) $q['s']), '%_\\') . '%';
            $params[] = $needle;
            array_unshift($parts, '(p.post_title LIKE ?) DESC');
        }
        if ($parts === []) {
            $parts[] = "p.post_date {$direction}";
        }
        return [' ORDER BY ' . implode(', ', $parts), $params];
    }
}
