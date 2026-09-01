<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Content\Site;
use Minn\Content\Terms;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;

/** wp/v2 categories and tags: list, single, and the create/update/delete the taxonomy admin drives. */
final readonly class TermsController
{
    private const ORDER_BY = ['name' => 't.name', 'count' => 'tt.count', 'id' => 't.term_id', 'slug' => 't.slug'];

    public function __construct(
        private Db $db,
        private Terms $terms,
        private Site $site,
        private TermObject $object,
        private Caller $caller,
    ) {
    }

    /** The categories or tags list. */
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
        $include = array_values(array_filter(array_map(intval(...), explode(',', (string) $request->query('include', ''))), static fn (int $id) => $id > 0));
        if ($include !== []) {
            $where .= ' AND t.term_id IN (?)';
            $params[] = $include;
        }
        $exclude = array_values(array_filter(array_map(intval(...), explode(',', (string) $request->query('exclude', ''))), static fn (int $id) => $id > 0));
        if ($exclude !== []) {
            $where .= ' AND t.term_id NOT IN (?)';
            $params[] = $exclude;
        }
        $slug = (string) $request->query('slug', '');
        if ($slug !== '') {
            $slugs = array_values(array_filter(explode(',', $slug), static fn (string $s) => $s !== ''));
            if ($slugs !== []) {
                $where .= ' AND t.slug IN (?)';
                $params[] = $slugs;
            }
        }
        $post = $request->query('post');
        if ($post !== null && ctype_digit($post)) {
            if ($this->db->value("SELECT ID FROM {$this->db->table('posts')} WHERE ID = ?", [(int) $post]) === null) {
                throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 400);
            }
            $where .= " AND tt.term_taxonomy_id IN (SELECT term_taxonomy_id FROM {$this->db->table('term_relationships')} WHERE object_id = ?)";
            $params[] = (int) $post;
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
            array_map(fn (TermRecord $term) => $this->object->view($term, $base), TermRecord::fromRows($rows)),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    /** One category or tag. */
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
        return Reply::item($this->object->view(TermRecord::fromRow($row), $base), Fields::fromQuery($request->query));
    }

    /** Tags are open to edit_posts holders; categories need manage_categories. */
    #[Route(Method::Post, '/wp/v2/{base:categories|tags}')]
    public function create(Request $request, string $base): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $refusal = 'Sorry, you are not allowed to create terms in this taxonomy.';
        $this->caller->require('rest_cannot_create', $refusal);
        if (!$this->caller->can($taxonomy === 'post_tag' ? 'edit_posts' : 'manage_categories')) {
            throw new RestError('rest_cannot_create', $refusal, 403);
        }
        $body = $request->json();
        $name = Kses::text((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw RestError::missingParams(['name']);
        }
        // A same-name term is the reference's term_exists refusal, which hands
        // the existing id back in data AND in an additional_data list.
        $existing = $this->terms->idByName($name, $taxonomy);
        if ($existing !== null) {
            throw new RestError(
                'term_exists',
                'A term with the name provided already exists in this taxonomy.',
                400,
                ['term_id' => $existing],
                ['additional_data' => [$existing, $existing]],
            );
        }
        $slug = $this->terms->uniqueSlug((string) ($body['slug'] ?? '') !== '' ? (string) $body['slug'] : $name, $taxonomy);
        $termId = $this->terms->create(
            $name,
            $slug,
            $taxonomy,
            Kses::filter((string) ($body['description'] ?? ''), Kses::COMMENT),
            $config['has_parent'] ? (int) ($body['parent'] ?? 0) : 0,
        );
        return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to("/wp/v2/{$base}/{$termId}"));
    }

    /** Updates a category or tag. */
    #[Route(Method::Post, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    public function update(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $termId = (int) $id;
        $term = $this->terms->row($termId, $taxonomy);
        if ($term === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$this->caller->can('manage_categories')) {
            throw $this->caller->refuse('rest_cannot_update', 'Sorry, you are not allowed to edit this term.');
        }
        $body = $request->json();
        if (isset($body['name']) || isset($body['slug'])) {
            $this->terms->rename(
                $termId,
                isset($body['name']) ? Kses::text((string) $body['name']) : (string) $term['name'],
                isset($body['slug']) ? $this->terms->uniqueSlug((string) $body['slug'], $taxonomy, $termId) : (string) $term['slug'],
            );
        }
        if (isset($body['description']) || ($config['has_parent'] && isset($body['parent']))) {
            $this->terms->describe(
                $termId,
                $taxonomy,
                isset($body['description']) ? Kses::filter((string) $body['description'], Kses::COMMENT) : (string) $term['description'],
                $config['has_parent'] && isset($body['parent']) ? (int) $body['parent'] : (int) $term['parent'],
            );
        }
        return Reply::item($this->object->view($this->terms->row($termId, $taxonomy), $base), Fields::fromQuery($request->query));
    }

    /** The default category is capability-denied before the force check. */
    #[Route(Method::Delete, '/wp/v2/{base:categories|tags}/{id:\d+}')]
    public function delete(Request $request, string $base, string $id): Response
    {
        $config = TermObject::config($base);
        $taxonomy = $config['taxonomy'];
        $termId = (int) $id;
        $term = $this->terms->row($termId, $taxonomy);
        if ($term === null) {
            throw new RestError('rest_term_invalid', 'Term does not exist.', 404);
        }
        if (!$this->caller->can('manage_categories')) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this term.');
        }
        if ($taxonomy === 'category' && $termId === (int) ($this->site->option('default_category') ?? 0)) {
            throw new RestError('rest_cannot_delete', 'Sorry, you are not allowed to delete this term.', 403);
        }
        if (!filter_var($request->query('force', ''), FILTER_VALIDATE_BOOLEAN)) {
            throw new RestError('rest_trash_not_supported', "Terms do not support trashing. Set 'force=true' to delete.", 501);
        }
        $previous = $this->object->view($term, $base);
        $this->terms->delete(TermRecord::fromRow($term->row() + ['taxonomy' => $taxonomy]), $config['has_parent']);
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }
}
