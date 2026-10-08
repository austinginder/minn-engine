<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Db;

/**
 * The links manager's table, as get_bookmarks and wp_insert_link read and
 * write it (probe plugin-queue4). A query narrows by the ids included (which
 * set aside every other narrowing), the ids excluded, the link categories
 * (joined, so a link filed under two of them comes back twice, with the
 * join's columns), visibility and a search over the address, name and
 * description; it sorts by one or more of the link's columns (name when none
 * is known), ascending unless told otherwise.
 */
final readonly class Links
{
    /** The columns a link row holds, in the table's order. */
    public const FIELDS = ['link_id', 'link_url', 'link_name', 'link_image', 'link_target', 'link_description', 'link_visible', 'link_owner', 'link_rating', 'link_updated', 'link_rel', 'link_notes', 'link_rss'];

    private const ORDERS = ['name' => 'link_name', 'url' => 'link_url', 'rating' => 'link_rating', 'id' => 'link_id', 'owner' => 'link_owner', 'visible' => 'link_visible', 'updated' => 'link_updated', 'notes' => 'link_notes', 'description' => 'link_description', 'rel' => 'link_rel', 'target' => 'link_target', 'image' => 'link_image', 'rss' => 'link_rss', 'length' => 'length', 'rand' => 'RAND()'];

    public function __construct(private Db $db)
    {
    }

    /** A link's row, its values as strings the way the reference reads them. @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $id > 0 ? $this->db->row('SELECT * FROM ' . $this->db->table('links') . ' WHERE link_id = ? LIMIT 1', [$id]) : null;
        return $row === null ? null : self::strings($row);
    }

    /**
     * The links a get_bookmarks call asks for.
     *
     * @param array<string, mixed> $args orderby, order, limit, hide_invisible, show_updated, include, exclude, search
     * @param list<int> $categories link category term ids, empty for any
     * @param int $recentMinutes how recent an update counts as recent, when show_updated asks
     * @return list<array<string, mixed>>
     */
    public function matching(array $args, array $categories, int $recentMinutes): array
    {
        $links = $this->db->table('links');
        $include = self::ids($args['include'] ?? '');
        [$where, $params] = $include !== [] ? ['link_id IN (?)', [$include]] : $this->narrowing($args, $categories);
        $order = strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $columns = array_values(array_filter(array_map(static fn (string $key) => self::ORDERS[trim($key)] ?? null, explode(',', strtolower((string) ($args['orderby'] ?? 'name'))))));
        $orderBy = implode(', ', array_map(static fn (string $column) => $column === 'RAND()' ? $column : "{$column} {$order}", $columns ?: ['link_name']));
        $select = '*' . (in_array('length', $columns, true) ? ', CHAR_LENGTH(link_name) AS length' : '')
            . (!empty($args['show_updated']) ? ', (link_updated > DATE_SUB(NOW(), INTERVAL ' . $recentMinutes . ' MINUTE)) AS recently_updated, UNIX_TIMESTAMP(link_updated) AS link_updated_f' : '');
        $join = $include === [] && $categories !== [] ? ' INNER JOIN ' . $this->db->table('term_relationships') . ' AS tr ON (link_id = tr.object_id) INNER JOIN ' . $this->db->table('term_taxonomy') . " AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'link_category'" : '';
        $limit = (int) ($args['limit'] ?? -1) > -1 ? ' LIMIT ' . (int) $args['limit'] : '';
        return array_map(self::strings(...), $this->db->rows("SELECT {$select} FROM {$links}{$join} WHERE {$where} ORDER BY {$orderBy}{$limit}", $params));
    }

    /** @return array{string, list<mixed>} the WHERE and its parameters when no ids are included */
    private function narrowing(array $args, array $categories): array
    {
        $where = ['1=1'];
        $params = [];
        if (($exclude = self::ids($args['exclude'] ?? '')) !== []) {
            $where[] = 'link_id NOT IN (?)';
            $params[] = $exclude;
        }
        if ($categories !== []) {
            $where[] = 'tt.term_id IN (?)';
            $params[] = $categories;
        }
        if (!empty($args['hide_invisible'])) {
            $where[] = "link_visible = 'Y'";
        }
        if (($search = (string) ($args['search'] ?? '')) !== '') {
            $like = '%' . addcslashes($search, '_%\\') . '%';
            $where[] = '(link_url LIKE ? OR link_name LIKE ? OR link_description LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * A link written: a new row when the id is 0, else that row's columns
     * set. The id it was written under.
     *
     * @param array<string, mixed> $fields columns of FIELDS
     */
    public function save(int $id, array $fields): int
    {
        $fields = array_intersect_key($fields, array_flip(array_diff(self::FIELDS, ['link_id'])));
        $table = $this->db->table('links');
        if ($id > 0) {
            $set = implode(', ', array_map(static fn (string $column) => "{$column} = ?", array_keys($fields)));
            $this->db->execute("UPDATE {$table} SET {$set} WHERE link_id = ?", [...array_values($fields), $id]);
            return $id;
        }
        $this->db->execute("INSERT INTO {$table} (" . implode(', ', array_keys($fields)) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', array_values($fields));
        return $this->db->insertId();
    }

    /** A link's row removed. */
    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('links') . ' WHERE link_id = ?', [$id]);
    }

    /** @param array<string, mixed> $row @return array<string, string|null> */
    private static function strings(array $row): array
    {
        return array_map(static fn ($value) => $value === null ? null : (string) $value, $row);
    }

    /** @return list<int> */
    private static function ids(mixed $list): array
    {
        return array_values(array_filter(array_map('intval', is_array($list) ? $list : preg_split('/[\s,]+/', (string) $list)), static fn (int $id) => $id > 0));
    }
}
