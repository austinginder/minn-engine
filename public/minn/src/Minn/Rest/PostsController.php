<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
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
        $query = ListQuery::fromRequest($request);
        $context = Context::of($request);
        $statuses = $this->visibleStatuses($request, $type, $context);
        $needsAuth = $context->isEdit() || $statuses !== ['publish'];
        $userId = $needsAuth ? $this->caller->id() : 0;
        $others = $this->caller->can(TypeCapabilities::editOthers($type));

        [$where, $params] = $this->visibility($type, $statuses, $needsAuth, $others);
        [$narrowing, $narrowingParams] = $query->clauses();
        $where .= $narrowing;
        $params = [...$params, ...$narrowingParams];
        if ($query->menuOrder !== null) {
            $where .= ' AND menu_order = ?';
            $params[] = $query->menuOrder;
        }

        $table = $this->db->table('posts');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
        if ($query->isPastTheEnd($total)) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        $rows = PostRecord::fromRows($this->db->rows(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$this->orderSql($query)} LIMIT ?, ?",
            [...$params, $query->offset(), $query->perPage],
        ));
        if ($context->isEdit() && !$others) {
            // Edit context drops the rows the caller cannot edit AFTER the page was cut: a
            // page of five may come back with one item while the totals still count them all.
            $rows = array_values(array_filter($rows, fn (PostRecord $post) => $this->caller->can('edit_post', $post->id)));
        }
        $objects = $context->isEdit()
            ? array_map(fn (PostRecord $post) => $this->object->edit($post, $userId), $rows)
            : array_map(fn (PostRecord $post) => $this->object->view($post), $rows);
        return Reply::list($objects, $total, $query->totalPages($total), Fields::fromQuery($request->query));
    }

    /**
     * The statuses this caller may list. Public callers see only 'publish';
     * any other status and the edit context need a caller who can edit this
     * type, and a status beyond publish is a parameter error without that cap.
     *
     * @return list<string>
     */
    private function visibleStatuses(Request $request, string $type, Context $context): array
    {
        $publicOnly = ['publish'];
        $requested = $request->has('status')
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $request->query('status')))))
            : $publicOnly;
        $beyondPublic = array_diff($requested, $publicOnly) !== [];
        $editCap = TypeCapabilities::edit($type);
        if ($beyondPublic && !$this->caller->can($editCap)) {
            $inner = ['code' => 'rest_forbidden_status', 'message' => 'Status is forbidden.', 'data' => ['status' => $this->caller->id() > 0 ? 403 : 401]];
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'Status is forbidden.'], 'details' => ['status' => $inner]]);
        }
        if (!$context->isEdit() && !$beyondPublic) {
            return $publicOnly;
        }
        $refusal = 'Sorry, you are not allowed to edit posts in this post type.';
        $this->caller->require('rest_forbidden_context', $refusal, 401);
        if (!$this->caller->can($editCap)) {
            throw new RestError('rest_forbidden_context', $refusal, 403);
        }
        return $requested;
    }

    /**
     * The clause that keeps the rows this caller may see: another author's
     * unpublished posts need edit_others_*, their private ones read_private_*.
     *
     * @param list<string> $statuses
     * @return array{string, list<mixed>}
     */
    private function visibility(string $type, array $statuses, bool $needsAuth, bool $others): array
    {
        $where = 'post_type = ? AND post_status IN (?)';
        $params = [$type, $statuses];
        if ($needsAuth && !$others) {
            $where .= " AND (post_status = 'publish' OR post_author = ?)";
            $params[] = $this->caller->id();
        } elseif ($needsAuth && in_array('private', $statuses, true) && !$this->caller->can(TypeCapabilities::readPrivate($type))) {
            $where .= " AND (post_status <> 'private' OR post_author = ?)";
            $params[] = $this->caller->id();
        }
        return [$where, $params];
    }

    private function orderSql(ListQuery $query): string
    {
        $orderBy = self::ORDER_BY[$query->orderBy] ?? 'post_date';
        if ($orderBy === 'include') {
            // The include list is its own order; the order parameter does not reverse it.
            return $query->include === [] ? "post_date {$query->order}" : 'FIELD(ID, ' . implode(',', $query->include) . ')';
        }
        if ($orderBy === 'post_date') {
            // Same-date rows: the reference lists the newest id first on a plain
            // list and the oldest first once search or include narrows the query.
            $ties = $query->include !== [] || $query->isSearch() ? 'ASC' : 'DESC';
            return "post_date {$query->order}, ID {$ties}";
        }
        return "{$orderBy} {$query->order}";
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
        $row = PostRecord::fromRow($row);
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
