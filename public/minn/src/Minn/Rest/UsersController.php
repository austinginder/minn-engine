<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\UserRecord;
use Minn\Auth\Password;
use Minn\Auth\Roles;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Support\Kses;
use Minn\Auth\PasswordReset;
use Minn\Mail\Mailer;

/** wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives. */
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
        private Site $site,
        private UserObject $object,
        private RestUrl $url,
        private Caller $caller,
        private Roles $roles,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/users/me')]
    public function me(Request $request): Response
    {
        $user = $this->caller->require()->user;
        $fields = Fields::fromQuery($request->query);
        return Reply::item(
            Context::of($request)->isEdit() ? $this->object->edit($user) : $this->object->view($user, true),
            $fields,
        );
    }

    /** View context lists published authors; edit context lists everyone. */
    #[Route(Method::Get, '/wp/v2/users')]
    public function list(Request $request): Response
    {
        $self = $this->caller->id();
        $context = Context::of($request);
        if ($context->isEdit() && !$this->caller->can('list_users')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit users.');
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $order = strtoupper((string) $request->query('order', 'asc')) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = self::ORDER_BY[(string) $request->query('orderby', 'name')] ?? 'u.display_name';
        if ($request->query('orderby') === 'email' && !$this->caller->can('list_users')) {
            throw $this->caller->refuse('rest_forbidden_orderby', 'Sorry, you are not allowed to order users by this parameter.');
        }

        $where = $context->isEdit()
            ? '1 = 1'
            : "u.ID IN ( SELECT post_author FROM {$this->db->table('posts')}
               WHERE post_status = 'publish' AND post_type IN ('post','page') )";
        $params = [];
        $include = array_values(array_filter(array_map(intval(...), explode(',', (string) $request->query('include', ''))), static fn (int $id) => $id > 0));
        if ($include !== []) {
            $where .= ' AND u.ID IN (?)';
            $params[] = $include;
        }
        $exclude = array_values(array_filter(array_map(intval(...), explode(',', (string) $request->query('exclude', ''))), static fn (int $id) => $id > 0));
        if ($exclude !== []) {
            $where .= ' AND u.ID NOT IN (?)';
            $params[] = $exclude;
        }
        $slug = (string) $request->query('slug', '');
        if ($slug !== '') {
            $slugs = array_values(array_filter(explode(',', $slug), static fn (string $s) => $s !== ''));
            if ($slugs !== []) {
                $where .= ' AND u.user_nicename IN (?)';
                $params[] = $slugs;
            }
        }
        foreach (preg_split('/\s+/', trim((string) $request->query('search', ''))) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $where .= ' AND (u.user_login LIKE ? OR u.user_nicename LIKE ? OR u.display_name LIKE ?)';
            $params = [...$params, $like, $like, $like];
        }
        $table = $this->db->table('users');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} u WHERE {$where}", $params);
        $rows = $this->db->rows(
            "SELECT u.* FROM {$table} u WHERE {$where} ORDER BY {$orderBy} {$order} LIMIT ?, ?",
            [...$params, ($page - 1) * $perPage, $perPage],
        );
        $objects = $context->isEdit()
            ? array_map(fn (UserRecord $u) => $this->object->edit($u), UserRecord::fromRows($rows))
            : array_map(fn (UserRecord $u) => $this->object->view($u, $u->id === $self), UserRecord::fromRows($rows));
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
        if (Context::of($request)->isEdit()) {
            if ($self !== $userId && !$this->caller->can('list_users')) {
                throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this user.');
            }
            return Reply::item($this->object->edit($user), $fields);
        }
        // A user without published content is not public; only list_users (or the user) sees the profile.
        if ($self !== $userId && !$this->caller->can('list_users') && !$this->hasPublishedContent($userId)) {
            throw $this->caller->refuse('rest_user_cannot_view', 'Sorry, you are not allowed to list users.');
        }
        return Reply::item($this->object->view($user, $self === $userId), $fields);
    }

    /** A new account hears about itself with a link to choose a password. */
    private function welcome(?UserRecord $user): void
    {
        if ($user === null) {
            return;
        }
        $key = (new PasswordReset($this->users))->issue($user);
        $home = rtrim((string) ($this->site->option('home') ?? ''), '/');
        $link = $home . '/wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode((string) $user['user_login']);
        Mailer::forSite($this->site)->send(
            Mailer::noticesFor($this->site)->loginDetails((string) $user['user_login'], (string) $user['user_email'], $link),
        );
    }

    private function hasPublishedContent(int $userId): bool
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM {$this->db->table('posts')} WHERE post_author = ? AND post_status = 'publish' AND post_type IN ('post', 'page')",
            [$userId],
        ) > 0;
    }

    /** A role must be registered, as the reference insists. */
    private function validRole(string $role): string
    {
        if (!isset($this->roles->all()[$role])) {
            throw new RestError('rest_user_invalid_role', "The role {$role} does not exist.", 400);
        }
        return $role;
    }

    private static function validEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Engine-created users carry real scheme hashes and the full default meta set. */
    #[Route(Method::Post, '/wp/v2/users')]
    public function create(Request $request): Response
    {
        $refusal = 'Sorry, you are not allowed to create new users.';
        $this->caller->require('rest_cannot_create_user', $refusal);
        if (!$this->caller->can('create_users')) {
            throw new RestError('rest_cannot_create_user', $refusal, 403);
        }
        $body = $request->json();
        $missing = array_values(array_filter(['username', 'email', 'password'], static fn (string $key) => (string) ($body[$key] ?? '') === ''));
        if ($missing !== []) {
            throw RestError::missingParams($missing);
        }
        // Duplicate identities surface as the reference's bare error: 500, data null.
        $login = (string) $body['username'];
        $email = (string) $body['email'];
        if ($this->users->findByLogin($login) !== null) {
            throw RestError::bare('existing_user_login', 'Sorry, that username already exists!');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw RestError::bare('existing_user_email', 'Sorry, that email address is already used!');
        }
        $role = $this->validRole((string) ($body['roles'][0] ?? ($this->site->option('default_role') ?? 'subscriber')));
        if (!self::validEmail($email)) {
            throw new RestError('rest_user_invalid_email', 'Invalid email address.', 400);
        }
        $newId = $this->users->createAccount([
            'login' => $login,
            'email' => $email,
            'password' => (string) $body['password'],
            'role' => $role,
            'display_name' => (string) ($body['name'] ?? '') !== '' ? (string) $body['name'] : $login,
            'url' => (string) ($body['url'] ?? ''),
            'nickname' => (string) ($body['nickname'] ?? $login),
            'first_name' => (string) ($body['first_name'] ?? ''),
            'last_name' => (string) ($body['last_name'] ?? ''),
            'description' => (string) ($body['description'] ?? ''),
            'locale' => (string) ($body['locale'] ?? ''),
        ]);
        $created = $this->users->find($newId);
        $this->welcome($created);
        return Reply::item($this->object->edit($created), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to('/wp/v2/users/' . $newId));
    }

    #[Route(Method::Post, '/wp/v2/users/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/users/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/users/{id:\d+}')]
    public function update(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $self = $this->caller->id();
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        if ($self !== $userId && !$this->caller->can('edit_users')) {
            throw $this->caller->refuse('rest_cannot_edit', 'Sorry, you are not allowed to edit this user.');
        }
        $body = $request->json();
        $columns = [];
        if (isset($body['name'])) {
            $columns['display_name'] = Kses::text((string) $body['name']);
        }
        if (isset($body['email'])) {
            $email = (string) $body['email'];
            $other = $this->users->findByEmail($email);
            if (!self::validEmail($email) || ($other !== null && (int) $other['ID'] !== $userId)) {
                throw new RestError('rest_user_invalid_email', 'Invalid email address.', 400);
            }
            $columns['user_email'] = $email;
        }
        if (isset($body['url'])) {
            $columns['user_url'] = Kses::url((string) $body['url']);
        }
        if (isset($body['slug'])) {
            $columns['user_nicename'] = $this->users->uniqueNicename((string) $body['slug'], $userId);
        }
        if (isset($body['password']) && (string) $body['password'] !== '') {
            $columns['user_pass'] = Password::hash((string) $body['password']);
        }
        $this->users->update($userId, $columns);
        foreach (['first_name', 'last_name', 'description', 'nickname', 'locale'] as $key) {
            if (isset($body[$key])) {
                $value = $key === 'description' ? Kses::filter((string) $body[$key], Kses::COMMENT) : Kses::text((string) $body[$key]);
                $this->users->setMeta($userId, $key, $value);
            }
        }
        if (isset($body['meta']['show_admin_bar_front'])) {
            $this->users->setMeta($userId, 'show_admin_bar_front', $body['meta']['show_admin_bar_front'] === 'false' ? 'false' : 'true');
        }
        if (isset($body['roles'][0])) {
            if (!$this->caller->can('promote_users')) {
                throw $this->caller->refuse('rest_cannot_edit_roles', 'Sorry, you are not allowed to edit roles of this user.');
            }
            $role = $this->validRole((string) $body['roles'][0]);
            $prefix = $this->db->prefix();
            $this->users->setMeta($userId, "{$prefix}capabilities", Roles::serializeSingle($role));
            $this->users->setMeta($userId, "{$prefix}user_level", (string) Roles::level($role));
        }
        return Reply::item($this->object->edit($this->users->find($userId)), Fields::fromQuery($request->query));
    }

    /** reassign is REQUIRED (checked before the user lookup), and so is force. */
    #[Route(Method::Delete, '/wp/v2/users/{id:\d+}')]
    public function delete(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $user = $this->users->find($userId);
        if (!$request->has('reassign')) {
            throw RestError::missingParams(['reassign']);
        }
        if ($user === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        if (!$this->caller->can('delete_users')) {
            throw $this->caller->refuse('rest_user_cannot_delete', 'Sorry, you are not allowed to delete this user.');
        }
        if (!filter_var($request->query('force', ''), FILTER_VALIDATE_BOOLEAN)) {
            throw new RestError('rest_trash_not_supported', "Users do not support trashing. Set 'force=true' to delete.", 501);
        }
        $reassign = (string) $request->query('reassign', '');
        $target = 0;
        if ($reassign !== '' && $reassign !== 'false') {
            $target = (int) $reassign;
            if ($this->users->find($target) === null) {
                throw new RestError('rest_user_invalid_reassign', 'Invalid user ID for reassignment.', 400);
            }
        }
        $previous = $this->object->edit($user);
        $posts = $this->db->table('posts');
        if ($target > 0) {
            $this->db->execute("UPDATE {$posts} SET post_author = ? WHERE post_author = ?", [$target, $userId]);
        } else {
            // No reassignment: the user's posts are deleted, as the reference does.
            foreach ($this->db->rows("SELECT ID FROM {$posts} WHERE post_author = ?", [$userId]) as $row) {
                $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ?", [(int) $row['ID']]);
                $this->db->execute("DELETE FROM {$posts} WHERE ID = ?", [(int) $row['ID']]);
            }
        }
        $this->users->delete($userId);
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }
}
