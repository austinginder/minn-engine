<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Auth\TypeCapabilities;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 posts and pages, read side. */
final readonly class PostsController
{
    private const ORDER_BY = [
        'date' => 'post_date',
        'modified' => 'post_modified',
        'title' => 'post_title',
        'slug' => 'post_name',
        'id' => 'ID',
        'author' => 'post_author',
        'menu_order' => 'menu_order',
        'include' => 'include',
    ];

    public function __construct(
        private Db $db,
        private Posts $posts,
        private PostObject $object,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/{base:posts|pages}')]
    public function list(Request $request, string $base): Response
    {
        return $this->serveList($request, $base === 'pages' ? 'page' : 'post');
    }

    public function serveList(Request $request, string $type): Response
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $context = Context::of($request);

        // Public callers see only 'publish'; any other status and the edit
        // context need a caller who can edit this type.
        $publicOnly = ['publish'];
        $requested = $request->has('status')
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $request->query('status')))))
            : $publicOnly;
        $needsAuth = $context->isEdit() || array_diff($requested, $publicOnly) !== [];
        $editCap = TypeCapabilities::edit($type);
        // A status beyond publish is a parameter error for a caller without the type's edit cap.
        if (array_diff($requested, $publicOnly) !== [] && !$this->caller->can($editCap)) {
            $inner = ['code' => 'rest_forbidden_status', 'message' => 'Status is forbidden.', 'data' => ['status' => $this->caller->id() > 0 ? 403 : 401]];
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'Status is forbidden.'], 'details' => ['status' => $inner]]);
        }
        $userId = 0;
        if ($needsAuth) {
            $refusal = 'Sorry, you are not allowed to edit posts in this post type.';
            $userId = $this->caller->require('rest_forbidden_context', $refusal, 401)->id();
            if (!$this->caller->can($editCap)) {
                throw new RestError('rest_forbidden_context', $refusal, 403);
            }
        }
        $statuses = $needsAuth ? $requested : $publicOnly;

        $params = [$type, $statuses];
        $where = 'post_type = ? AND post_status IN (?)';
        $others = $this->caller->can(TypeCapabilities::editOthers($type));
        if ($needsAuth && !$others) {
            // Another author's unpublished posts need edit_others_*; private ones read_private_*.
            $where .= " AND (post_status = 'publish' OR post_author = ?)";
            $params[] = $userId;
        } elseif ($needsAuth && in_array('private', $statuses, true) && !$this->caller->can(TypeCapabilities::readPrivate($type))) {
            $where .= " AND (post_status <> 'private' OR post_author = ?)";
            $params[] = $userId;
        }
        $author = $request->query('author');
        if ($author !== null && $author !== '') {
            $ids = self::ids($author);
            if ($ids !== []) {
                $where .= ' AND post_author IN (?)';
                $params[] = $ids;
            }
        }
        $authorExclude = $request->query('author_exclude');
        if ($authorExclude !== null && $authorExclude !== '') {
            $ids = self::ids($authorExclude);
            if ($ids !== []) {
                $where .= ' AND post_author NOT IN (?)';
                $params[] = $ids;
            }
        }
        $include = self::ids((string) $request->query('include', ''));
        if ($include !== []) {
            $where .= ' AND ID IN (?)';
            $params[] = $include;
        }
        $exclude = self::ids((string) $request->query('exclude', ''));
        if ($exclude !== []) {
            $where .= ' AND ID NOT IN (?)';
            $params[] = $exclude;
        }
        $slug = (string) $request->query('slug', '');
        if ($slug !== '') {
            $slugs = array_values(array_filter(explode(',', $slug), static fn (string $s) => $s !== ''));
            $where .= ' AND post_name IN (?)';
            $params[] = $slugs;
        }
        $parent = $request->query('parent');
        if ($parent !== null && $parent !== '') {
            $ids = self::ids($parent, true);
            if ($ids !== []) {
                $where .= ' AND post_parent IN (?)';
                $params[] = $ids;
            }
        }
        $parentExclude = $request->query('parent_exclude');
        if ($parentExclude !== null && $parentExclude !== '') {
            $ids = self::ids($parentExclude, true);
            if ($ids !== []) {
                $where .= ' AND post_parent NOT IN (?)';
                $params[] = $ids;
            }
        }
        $menuOrder = $request->query('menu_order');
        if ($menuOrder !== null && $menuOrder !== '' && preg_match('/^-?\d+$/', $menuOrder) === 1) {
            $where .= ' AND menu_order = ?';
            $params[] = (int) $menuOrder;
        }
        // Every whitespace-separated word must appear in the title, excerpt, or content.
        foreach (preg_split('/\s+/', trim((string) $request->query('search', ''))) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $where .= ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)';
            $params = [...$params, $like, $like, $like];
        }
        $orderBy = self::ORDER_BY[(string) $request->query('orderby', 'date')] ?? 'post_date';
        $order = strtoupper((string) $request->query('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        if ($orderBy === 'post_date') {
            // Same-date rows: the reference lists the newest id first on a plain
            // list and the oldest first once search or include narrows the query.
            $ties = $include !== [] || trim((string) $request->query('search', '')) !== '' ? 'ASC' : 'DESC';
            $orderBy = "post_date {$order}, ID {$ties}";
            $order = '';
        }
        if ($orderBy === 'include') {
            // The include list is its own order; the order parameter does not reverse it.
            $orderBy = $include === [] ? 'post_date' : 'FIELD(ID, ' . implode(',', $include) . ')';
            $order = '';
        }

        $table = $this->db->table('posts');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
        $totalPages = (int) ceil($total / $perPage);
        if ($page > 1 && $page > $totalPages) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        $rows = $this->db->rows(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderBy} {$order} LIMIT ?, ?",
            [...$params, ($page - 1) * $perPage, $perPage],
        );
        if ($context->isEdit() && !$others) {
            // Edit context drops the rows the caller cannot edit AFTER the page was cut: a
            // page of five may come back with one item while the totals still count them all.
            $rows = array_values(array_filter($rows, fn (array $row) => $this->caller->can('edit_post', (int) $row['ID'])));
        }
        $objects = $context->isEdit()
            ? array_map(fn (array $row) => $this->object->edit($row, $userId), $rows)
            : array_map(fn (array $row) => $this->object->view($row), $rows);
        return Reply::list($objects, $total, $totalPages, Fields::fromQuery($request->query));
    }

    /** @return list<int> */
    private static function ids(string $csv, bool $keepZero = false): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $n = (int) $part;
            if ($n !== 0 || $keepZero) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:\d+}')]
    public function single(Request $request, string $base, string $id): Response
    {
        return $this->serveSingle($request, $base === 'pages' ? 'page' : 'post', $id);
    }

    public function serveSingle(Request $request, string $type, string $id): Response
    {
        $postId = (int) $id;
        $row = $this->db->row(
            "SELECT * FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = ? LIMIT 1",
            [$postId, $type],
        );
        if ($row === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        $fields = Fields::fromQuery($request->query);
        if (Context::of($request)->isEdit()) {
            if (!$this->caller->can('edit_post', $postId)) {
                throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this post.');
            }
            return Reply::item($this->object->edit($row, $this->caller->id()), $fields);
        }
        // A non-published post is visible only to a reader who can edit it.
        if ($row['post_status'] !== 'publish' && !$this->caller->can('read_post', $postId)) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
        return Reply::item($this->object->view($row), $fields);
    }
}
