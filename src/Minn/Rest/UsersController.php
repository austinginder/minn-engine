<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 users, read side. */
final readonly class UsersController
{
    private const ORDER_BY = [
        'id' => 'u.ID',
        'name' => 'u.display_name',
        'registered_date' => 'u.user_registered',
        'slug' => 'u.user_nicename',
        'email' => 'u.user_email',
    ];

    public function __construct(
        private Db $db,
        private Users $users,
        private UserObject $object,
        private Caller $caller,
    ) {
    }

    /** Requires a valid cookie AND a valid wp_rest nonce. */
    #[Route(Method::Get, '/wp/v2/users/me')]
    public function me(Request $request): Response
    {
        $user = $this->caller->require()->user;
        $fields = Fields::fromQuery($request->query);
        return Reply::item(
            $request->query('context') === 'edit' ? $this->object->edit($user) : $this->object->view($user, true),
            $fields,
        );
    }

    /** View context lists published authors; edit context lists everyone. */
    #[Route(Method::Get, '/wp/v2/users')]
    public function list(Request $request): Response
    {
        $self = $this->caller->id();
        $context = $request->query('context') === 'edit' ? 'edit' : 'view';
        if ($context === 'edit' && !$this->caller->can('list_users')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit users.');
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $order = strtoupper((string) $request->query('order', 'asc')) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = self::ORDER_BY[(string) $request->query('orderby', 'name')] ?? 'u.display_name';

        $where = $context === 'edit'
            ? '1 = 1'
            : "u.ID IN ( SELECT post_author FROM {$this->db->table('posts')}
               WHERE post_status = 'publish' AND post_type IN ('post','page') )";
        $params = [];
        $include = array_filter(array_map(intval(...), explode(',', (string) $request->query('include', ''))));
        if ($include !== []) {
            $where .= ' AND u.ID IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
            $params = array_values($include);
        }
        $table = $this->db->table('users');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} u WHERE {$where}", $params);
        $rows = $this->db->rows(
            "SELECT u.* FROM {$table} u WHERE {$where} ORDER BY {$orderBy} {$order} LIMIT ?, ?",
            [...$params, ($page - 1) * $perPage, $perPage],
        );
        $objects = $context === 'edit'
            ? array_map(fn (array $u) => $this->object->edit($u), $rows)
            : array_map(fn (array $u) => $this->object->view($u, (int) $u['ID'] === $self), $rows);
        return Reply::list($objects, $total, (int) ceil($total / $perPage), Fields::fromQuery($request->query));
    }

    #[Route(Method::Get, '/wp/v2/users/{id:\d+}')]
    public function single(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        $self = $this->caller->id();
        $fields = Fields::fromQuery($request->query);
        if ($request->query('context') === 'edit') {
            if ($self !== $userId && !$this->caller->can('list_users')) {
                throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this user.');
            }
            return Reply::item($this->object->edit($user), $fields);
        }
        return Reply::item($this->object->view($user, $self === $userId), $fields);
    }
}
