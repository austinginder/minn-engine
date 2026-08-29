<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 posts and pages, read side. */
final readonly class PostsController
{
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
        $context = $request->query('context') === 'edit' ? 'edit' : 'view';

        // Public callers see only 'publish'; any other status and the edit
        // context need a caller who can edit this type.
        $publicOnly = ['publish'];
        $requested = $request->has('status')
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $request->query('status')))))
            : $publicOnly;
        $needsAuth = $context === 'edit' || array_diff($requested, $publicOnly) !== [];
        $userId = 0;
        if ($needsAuth) {
            $refusal = 'Sorry, you are not allowed to edit posts in this post type.';
            $userId = $this->caller->require('rest_forbidden_context', $refusal, 401)->id();
            if (!$this->caller->can($type === 'page' ? 'edit_pages' : 'edit_posts')) {
                throw new RestError('rest_forbidden_context', $refusal, 403);
            }
        }
        $statuses = $needsAuth ? $requested : $publicOnly;

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = [$type, ...$statuses];
        $where = "post_type = ? AND post_status IN ({$placeholders})";
        // Another author's unpublished posts need edit_others_*; private ones read_private_*.
        if ($needsAuth && !$this->caller->can($type === 'page' ? 'edit_others_pages' : 'edit_others_posts')) {
            $where .= " AND (post_status = 'publish' OR post_author = ?)";
            $params[] = $userId;
        } elseif ($needsAuth && in_array('private', $statuses, true) && !$this->caller->can($type === 'page' ? 'read_private_pages' : 'read_private_posts')) {
            $where .= " AND (post_status <> 'private' OR post_author = ?)";
            $params[] = $userId;
        }
        $author = $request->query('author');
        if ($author !== null && ctype_digit($author)) {
            $where .= ' AND post_author = ?';
            $params[] = (int) $author;
        }

        $table = $this->db->table('posts');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
        $totalPages = (int) ceil($total / $perPage);
        if ($page > 1 && $page > $totalPages) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        $rows = $this->db->rows(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY post_date DESC LIMIT ?, ?",
            [...$params, ($page - 1) * $perPage, $perPage],
        );
        $objects = $context === 'edit'
            ? array_map(fn (array $row) => $this->object->edit($row, $userId), $rows)
            : array_map(fn (array $row) => $this->object->view($row), $rows);
        return Reply::list($objects, $total, $totalPages, Fields::fromQuery($request->query));
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
        if ($request->query('context') === 'edit') {
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
