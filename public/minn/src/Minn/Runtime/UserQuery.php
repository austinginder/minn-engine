<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Db;

/**
 * The user listing behind WP_User_Query: role filtering through the
 * capabilities meta, include/exclude, column search with * wildcards,
 * ordering and paging. Returns raw user rows; the facade shapes them into
 * WP_User objects, string ids, or column records the reference's way.
 */
final readonly class UserQuery
{
    private const COLUMNS = ['ID', 'user_login', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'display_name'];

    public function __construct(private Db $db)
    {
    }

    /**
     * @param array<string, mixed> $args
     * @return array{rows: list<array>, total: int}
     */
    public function run(array $args): array
    {
        $where = ['1=1'];
        $params = [];
        $role = (string) ($args['role'] ?? '');
        if ($role !== '') {
            $where[] = "ID IN (SELECT user_id FROM {$this->db->table('usermeta')} WHERE meta_key = ? AND meta_value LIKE ?)";
            $params[] = $this->db->prefix() . 'capabilities';
            $params[] = '%"' . $role . '"%';
        }
        foreach ([['include', 'IN'], ['exclude', 'NOT IN']] as [$key, $op]) {
            $ids = array_values(array_filter(array_map('intval', (array) ($args[$key] ?? []))));
            if ($ids !== []) {
                $where[] = 'ID ' . $op . ' (' . implode(',', $ids) . ')';
            }
        }
        $search = (string) ($args['search'] ?? '');
        if ($search !== '') {
            $like = str_replace('*', '%', $search);
            $columns = array_values(array_intersect((array) ($args['search_columns'] ?? []), self::COLUMNS))
                ?: ['user_login', 'user_url', 'user_email', 'user_nicename', 'display_name'];
            $where[] = '(' . implode(' OR ', array_map(static fn (string $c) => "{$c} LIKE ?", $columns)) . ')';
            array_push($params, ...array_fill(0, count($columns), $like));
        }
        $orderby = in_array((string) ($args['orderby'] ?? 'user_login'), self::COLUMNS, true) ? (string) $args['orderby'] : 'user_login';
        $order = strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sql = "FROM {$this->db->table('users')} WHERE " . implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) {$sql}", $params);
        $number = (int) ($args['number'] ?? 0);
        $offset = (int) ($args['offset'] ?? (max(1, (int) ($args['paged'] ?? 1)) - 1) * max(0, $number));
        $limit = $number > 0 ? " LIMIT {$number} OFFSET {$offset}" : '';
        $rows = $this->db->rows("SELECT * {$sql} ORDER BY {$orderby} {$order}{$limit}", $params);
        return ['rows' => $rows, 'total' => $total];
    }
}
