<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;
use Minn\Runtime\UserEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
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

/** wp/v2 users: me, list, single, and the create/update/delete-with-reassign the Users view drives. */
final readonly class UsersController
{
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

    /** The signed-in user. */
    #[Route(Method::Get, '/wp/v2/users/me', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function me(Request $request): Response
    {
        $user = $this->caller->require()->user;
        $fields = Fields::fromQuery($request->query);
        return Reply::item(
            Context::of($request)->isEdit() ? $this->object->edit($user) : $this->object->view($user),
            $fields,
        );
    }

    /** Updates the signed-in user; signed out there is no such user (404), and the Allow header leaves the writes out. */
    #[Route(Method::Post, '/wp/v2/users/me', policy: new Policy(Access::SignedIn, signIn: 'rest_user_invalid_id', signInMessage: 'Invalid user ID.', signInStatus: 404), body: [Args::USER_EDIT])]
    #[Route(Method::Put, '/wp/v2/users/me', policy: new Policy(Access::SignedIn, signIn: 'rest_user_invalid_id', signInMessage: 'Invalid user ID.', signInStatus: 404), body: [Args::USER_EDIT])]
    #[Route(Method::Patch, '/wp/v2/users/me', policy: new Policy(Access::SignedIn, signIn: 'rest_user_invalid_id', signInMessage: 'Invalid user ID.', signInStatus: 404), body: [Args::USER_EDIT])]
    public function updateMe(Request $request): Response
    {
        return $this->update($request, (string) $this->caller->id());
    }

    /** Deletes the signed-in user as users/{id} deletes any; signed out there is no such user (404). */
    #[Route(Method::Delete, '/wp/v2/users/me', policy: new Policy(Access::Cap, 'delete_users', signIn: 'rest_user_invalid_id', signInMessage: 'Invalid user ID.', signInStatus: 404, refuse: 'rest_user_cannot_delete', message: 'Sorry, you are not allowed to delete this user.'), args: [Args::USER_DELETE])]
    public function deleteMe(Request $request): Response
    {
        return $this->delete($request, (string) $this->caller->id());
    }

    /**
     * The users list as the reference serves it: the request's WP_User_Query
     * through rest_user_collection_params and rest_user_query (a reader who
     * may not list users sees published authors only), its totals (counted
     * again without the page when it found none), and the users it found.
     */
    #[Route(Method::Get, '/wp/v2/users', policy: new Policy(Access::Public), params: UserCollectionParams::class)]
    public function list(Request $request): Response
    {
        $context = Context::of($request);
        $params = UserCollectionParams::for([]);
        $wp = RuntimeRoutes::sanitized($request, $params);
        $this->listAllowed($wp, $context);
        $registered = (array) \apply_filters('rest_user_collection_params', $params);
        $types = (array) ($params['has_published_posts']['items']['enum'] ?? []);
        $reader = $this->caller->can('list_users') ? 'lister' : 'public';
        $args = (array) \apply_filters('rest_user_query', UserListArgs::of($wp, $registered, $request->method->value, $types, $reader), $wp);
        $query = new \WP_User_Query($args);
        [$total, $pages] = self::totals($query, $args);
        if ($request->method === Method::Head) {
            return Reply::list([], $total, $pages, null);
        }
        $objects = [];
        foreach ((array) $query->get_results() as $user) {
            if ($user instanceof \WP_User) {
                $record = UserRecord::fromRow((array) $user->data);
                $objects[] = $context->isEdit() ? $this->object->edit($record) : $this->object->view($record);
            }
        }
        return Reply::list($objects, $total, $pages, Fields::fromQuery($request->query));
    }

    /**
     * What a reader who may not list users may not ask the list for: the
     * edit context, an email or registration order, roles, capabilities;
     * the authors shorthand is for those who may edit posts.
     */
    private function listAllowed(\WP_REST_Request $wp, Context $context): void
    {
        $lister = $this->caller->can('list_users');
        $refusals = [
            ['rest_forbidden_context', 'Sorry, you are not allowed to edit users.', $context->isEdit() && !$lister],
            ['rest_forbidden_orderby', 'Sorry, you are not allowed to order users by this parameter.', in_array($wp['orderby'], ['email', 'registered_date'], true) && !$lister],
            ['rest_user_cannot_view', 'Sorry, you are not allowed to filter users by role.', !empty($wp['roles']) && !$lister],
            ['rest_user_cannot_view', 'Sorry, you are not allowed to filter users by capability.', !empty($wp['capabilities']) && !$lister],
            ['rest_forbidden_who', 'Sorry, you are not allowed to query users by this parameter.', ($wp['who'] ?? '') === 'authors' && !$this->caller->can('edit_posts')],
        ];
        foreach ($refusals as [$code, $message, $refused]) {
            if ($refused) {
                throw $this->caller->refuse($code, $message);
            }
        }
    }

    /**
     * The total and the page count: the query's own, or, when it found
     * none, a count without the page; pages by the query's own number.
     *
     * @param array<string, mixed> $args
     * @return array{0: int, 1: int}
     */
    private static function totals(\WP_User_Query $query, array $args): array
    {
        $perPage = (int) ($args['number'] ?? 0);
        $total = (int) $query->get_total();
        if ($total < 1) {
            unset($args['number'], $args['offset']);
            $total = (int) (new \WP_User_Query($args))->get_total();
        }
        return [$total, $perPage > 0 ? (int) ceil($total / $perPage) : 0];
    }

    /** One user. */
    #[Route(Method::Get, '/wp/v2/users/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::User, param: 'id'), args: [Args::CONTEXT])]
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
        return Reply::item($this->object->view($user), $fields);
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
    #[Route(Method::Post, '/wp/v2/users', policy: new Policy(Access::Cap, 'create_users', signIn: 'rest_cannot_create_user', signInMessage: 'Sorry, you are not allowed to create new users.', refuse: 'rest_cannot_create_user', message: 'Sorry, you are not allowed to create new users.'), body: [Args::USER_CREATE])]
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
        // A login the site would not take is a parameter error, as the reference's username argument refuses it.
        $login = (string) $body['username'];
        $invalid = self::loginRefusal($login);
        if ($invalid !== null) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): username', 400, ['params' => ['username' => $invalid], 'details' => ['username' => ['code' => 'rest_user_invalid_username', 'message' => $invalid, 'data' => ['status' => 400]]]]);
        }
        // Duplicate identities surface as the reference's bare error: 500, data null.
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
        $password = (string) $body['password'];
        $name = (string) ($body['name'] ?? '');
        $url = (string) ($body['url'] ?? '');
        $profile = [
            'nickname' => (string) ($body['nickname'] ?? $login),
            'first_name' => (string) ($body['first_name'] ?? ''),
            'last_name' => (string) ($body['last_name'] ?? ''),
            'description' => (string) ($body['description'] ?? ''),
            'locale' => (string) ($body['locale'] ?? ''),
        ];
        $account = ['login' => $login, 'email' => $email, 'password' => $password, 'role' => $role, 'display_name' => $name, 'url' => $url] + $profile;
        $userdata = ['user_login' => $login, 'user_email' => $email, 'user_pass' => $password, 'display_name' => $name, 'user_url' => $url] + $profile;
        $newId = (new UserEvents())->create($userdata, $role, $request, fn (): int => $this->users->createAccount($account));
        // The reference's REST create sends no mail and leaves no reset key:
        // the caller chose the password.
        $created = $this->users->find($newId);
        return Reply::item($this->object->edit($created), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->url->to('/wp/v2/users/' . $newId));
    }

    /** Updates a user. */
    #[Route(Method::Post, '/wp/v2/users/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_user', param: 'id', subject: Subject::User, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this user.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this user.'), body: [Args::USER_EDIT])]
    #[Route(Method::Put, '/wp/v2/users/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_user', param: 'id', subject: Subject::User, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this user.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this user.'), body: [Args::USER_EDIT])]
    #[Route(Method::Patch, '/wp/v2/users/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_user', param: 'id', subject: Subject::User, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this user.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this user.'), body: [Args::USER_EDIT])]
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
        // The role refusal is decided before anything is written, so a caller
        // without promote_users cannot land the other edits on the way to a 403.
        if (isset($body['roles'][0]) && !$this->caller->can('promote_users')) {
            throw $this->caller->refuse('rest_cannot_edit_roles', 'Sorry, you are not allowed to edit roles of this user.');
        }
        $role = isset($body['roles'][0]) ? $this->validRole((string) $body['roles'][0]) : null;
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
        $password = isset($body['password']) && (string) $body['password'] !== '' ? (string) $body['password'] : null;
        if ($password !== null) {
            $columns['user_pass'] = Password::hash($password);
        }
        $meta = [];
        foreach (['first_name', 'last_name', 'description', 'nickname', 'locale'] as $key) {
            if (isset($body[$key])) {
                $meta[$key] = $key === 'description' ? Kses::comment((string) $body[$key]) : Kses::text((string) $body[$key]);
            }
        }
        if (isset($body['meta']['show_admin_bar_front'])) {
            $meta['show_admin_bar_front'] = $body['meta']['show_admin_bar_front'] === 'false' ? 'false' : 'true';
        }
        // With plugins loaded the runtime takes the plain password, which wins over the hash, and hashes it itself.
        $userdata = ($password === null ? [] : ['user_pass' => $password]) + $columns + $meta;
        (new UserEvents())->update($userId, $userdata, $role, $request, function () use ($userId, $columns, $meta, $role): void {
            $this->users->update($userId, $columns);
            foreach ($meta as $key => $value) {
                $this->users->setMeta($userId, $key, $value);
            }
            if ($role !== null) {
                $prefix = $this->db->prefix();
                $this->users->setMeta($userId, "{$prefix}capabilities", Roles::serializeSingle($role));
                $this->users->setMeta($userId, "{$prefix}user_level", (string) Roles::level($role));
            }
        });
        return Reply::item($this->object->edit($this->users->find($userId)), Fields::fromQuery($request->query));
    }

    /** reassign is REQUIRED (checked before the user lookup), and so is force. */
    #[Route(Method::Delete, '/wp/v2/users/{id:[\d]+}', policy: new Policy(Access::Cap, 'delete_users', param: 'id', subject: Subject::User, signIn: 'rest_user_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this user.', refuse: 'rest_user_cannot_delete', message: 'Sorry, you are not allowed to delete this user.'), args: [Args::USER_DELETE])]
    public function delete(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $user = $this->users->find($userId);
        if (!$request->has('reassign')) {
            throw RestError::missingParams(['reassign']);
        }
        $reassign = (string) $request->query('reassign', '');
        // Nothing, "false" or a number (the reference's own sanitizer, not the integer type): anything else is refused.
        if ($reassign !== '' && $reassign !== 'false' && !is_numeric($reassign)) {
            throw RestError::invalidParam('reassign', 'Invalid user parameter(s).', 'rest_invalid_param', ['status' => 400]);
        }
        if ($user === null) {
            throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
        }
        if (!$this->caller->can('delete_users')) {
            throw $this->caller->refuse('rest_user_cannot_delete', 'Sorry, you are not allowed to delete this user.');
        }
        if (!$request->flag('force')) {
            throw new RestError('rest_trash_not_supported', "Users do not support trashing. Set 'force=true' to delete.", 501);
        }
        $target = 0;
        if ($reassign !== '' && $reassign !== 'false') {
            $target = (int) $reassign;
            if ($this->users->find($target) === null) {
                throw new RestError('rest_user_invalid_reassign', 'Invalid user ID for reassignment.', 400);
            }
        }
        $data = ['deleted' => true, 'previous' => array_diff_key($this->object->edit($user), ['_links' => true])];
        (new UserEvents())->delete($userId, $target > 0 ? $target : null, $data, $request, function () use ($userId, $target): void {
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
        });
        return Reply::item($data, Fields::fromQuery($request->query));
    }

    /**
     * Why a new login is refused, in the reference's words, or null: it must
     * come through strict sanitizing unchanged (validate_username, with
     * plugins loaded), and no illegal_user_logins entry may name it.
     */
    private static function loginRefusal(string $login): ?string
    {
        $valid = Runtime::booted() ? \validate_username($login) : preg_match('/^[a-zA-Z0-9 _.\-@]+$/', $login) === 1 && trim($login) === $login;
        if (!$valid) {
            return 'This username is invalid because it uses illegal characters. Please enter a valid username.';
        }
        $illegal = Runtime::booted() ? array_map('strtolower', (array) \apply_filters('illegal_user_logins', [])) : [];
        return in_array(strtolower($login), $illegal, true) ? 'Sorry, that username is not allowed.' : null;
    }
}
