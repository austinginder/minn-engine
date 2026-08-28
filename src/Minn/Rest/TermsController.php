<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 categories and tags, read side. */
final readonly class TermsController
{
    private const ORDER_BY = ['name' => 't.name', 'count' => 'tt.count', 'id' => 't.term_id', 'slug' => 't.slug'];

    public function __construct(
        private Db $db,
        private TermObject $object,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/{base:categories|tags}')]
    public function list(Request $request, string $base): Response
    {
        $config = TermObject::config($base);
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $order = strtoupper((string) $request->query('order', 'asc')) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = self::ORDER_BY[(string) $request->query('orderby', 'name')] ?? 't.name';

        $where = 'tt.taxonomy = ?';
        $params = [$config['taxonomy']];
        $search = (string) $request->query('search', '');
        if ($search !== '') {
            $where .= ' AND t.name LIKE ?';
            $params[] = '%' . addcslashes($search, '%_\\') . '%';
        }
        $include = array_filter(array_map(intval(...), explode(',', (string) $request->query('include', ''))));
        if ($include !== []) {
            $where .= ' AND t.term_id IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
            $params = [...$params, ...array_values($include)];
        }

        $terms = $this->db->table('terms');
        $taxonomy = $this->db->table('term_taxonomy');
        $total = (int) $this->db->value(
            "SELECT COUNT(*) FROM {$terms} t JOIN {$taxonomy} tt ON tt.term_id = t.term_id WHERE {$where}",
            $params,
        );
        $rows = $this->db->rows(
            "SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent
             FROM {$terms} t JOIN {$taxonomy} tt ON tt.term_id = t.term_id
             WHERE {$where} ORDER BY {$orderBy} {$order} LIMIT ?, ?",
            [...$params, ($page - 1) * $perPage, $perPage],
        );
        return Reply::list(
            array_map(fn (array $term) => $this->object->view($term, $base), $rows),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    #[Route(Method::Get, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    public function single(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $row = $this->db->row(
            "SELECT t.term_id, t.name, t.slug, tt.description, tt.count, tt.parent
             FROM {$this->db->table('terms')} t
             JOIN {$this->db->table('term_taxonomy')} tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = ? AND t.term_id = ? LIMIT 1",
            [$config['taxonomy'], (int) $id],
        );
        if ($row === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        return Reply::item($this->object->view($row, $base), Fields::fromQuery($request->query));
    }
}
