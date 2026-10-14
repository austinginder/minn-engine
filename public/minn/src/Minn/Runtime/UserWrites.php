<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\PasswordReset;
use Minn\Content\PostWriter;
use Minn\Content\Posts;
use Minn\Content\UserRecord;
use Minn\Content\Users;
use Minn\I18n\Gettext;
use Minn\Mail\ChangeNotices;
use Minn\Objects\User;
use Minn\Support\Entities;
use Minn\Support\Kses;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;
use Minn\Content\RowCache;

/**
 * Accounts written as WordPress's user functions write them: an account
 * made, changed (its row, profile meta and role, with the notices a
 * changed password or email sends) and deleted (its posts deleted or
 * handed on), a reset key issued, the lookups that guard a new account,
 * and the cached rows let go; each step with the actions and filters the
 * reference fires in its order.
 */
final class UserWrites
{
    /** Whether a username survives strict sanitising unchanged and is not empty (validate_username), as validate_username leaves it. */
    public static function validUsername(mixed $username): bool
    {
        $username = (string) $username;
        $valid = $username !== '' && Sanitize::user($username, true) === $username;
        return (bool) Runtime::hooks()->filter('validate_username', [$valid, $username]);
    }

    /** The id of the account with the login (username_exists), or false, as username_exists leaves it. */
    public static function usernameExists(mixed $username): mixed
    {
        $user = Handoff::userBy('login', $username);
        return Runtime::hooks()->filter('username_exists', [$user ? $user->ID : false, $username]);
    }

    /** The id of the account with the email (email_exists), or false, as email_exists leaves it. */
    public static function emailExists(mixed $email): mixed
    {
        $user = Handoff::userBy('email', $email);
        return Runtime::hooks()->filter('email_exists', [$user ? $user->ID : false, $email]);
    }

    /** Whether the user (the signed-in one when none is named) belongs to the site (is_user_member_of_blog). */
    public static function isMember(mixed $userId = 0, mixed $blogId = 0): bool
    {
        $userId = $userId ?: (int) CurrentUser::id();
        return $userId > 0 && Handoff::user($userId) !== false;
    }

    /** Whether the site offers application passwords (wp_is_application_passwords_available): over HTTPS or on a local site, as the filter leaves it. */
    public static function appPasswordsAvailable(): bool
    {
        $type = defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : (getenv('WP_ENVIRONMENT_TYPE') ?: 'production');
        $supported = SiteUrls::secure() || $type === 'local';
        return (bool) Runtime::hooks()->filter('wp_is_application_passwords_available', [$supported]);
    }

    /** Whether a user may sign in by application password (wp_is_application_passwords_available_for_user): the site allows them, then the filter. */
    public static function appPasswordsAvailableFor(mixed $user): bool
    {
        $user = is_numeric($user) ? Handoff::userBy('id', $user) : $user;
        if (!$user instanceof User || !$user->exists() || !self::appPasswordsAvailable()) {
            return false;
        }
        return (bool) Runtime::hooks()->filter('wp_is_application_passwords_available_for_user', [true, $user]);
    }

    /**
     * A fresh reset key (get_password_reset_key), its hash stored under the
     * time it was issued through the account's save; the refusal when the
     * account may not reset.
     */
    public static function resetKey(mixed $user): mixed
    {
        $user = self::row($user);
        if ($user === null) {
            return Handoff::newError('invalid_user', 'Invalid user.');
        }
        $hooks = Runtime::hooks();
        $hooks->actionDeprecated('retreive_password', [$user->login], '1.5.1', 'retrieve_password');
        $hooks->action('retrieve_password', [$user->login]);
        $allow = $hooks->filter('allow_password_reset', [true, $user->id]);
        if (!$allow) {
            return Handoff::newError('no_password_reset', 'Password reset is not allowed for this user');
        }
        if (Records::isError($allow)) {
            return $allow;
        }
        [$key, $stored] = PasswordReset::mint();
        $hooks->action('retrieve_password_key', [$user->login, $key]);
        $saved = self::update(['ID' => $user->id, 'user_activation_key' => $stored]);
        if (Records::isError($saved)) {
            return Handoff::newError('no_password_key_update', Gettext::text('Could not save password reset key to database.'));
        }
        return $key;
    }

    /** A new account (wp_create_user) from a login, password and email. */
    public static function create(mixed $username, mixed $password, mixed $email = ''): mixed
    {
        return self::insert(['user_login' => Slashes::add($username), 'user_email' => Slashes::add($email), 'user_pass' => $password]);
    }

    /**
     * An account made or changed (wp_insert_user): each field through its
     * pre_user_ filter first, in the reference's order; its id, or the
     * error saying why not.
     */
    public static function insert(mixed $userdata): mixed
    {
        $userdata = Slashes::strip(self::asArray($userdata));
        $update = !empty($userdata['ID']);
        $existing = $update ? Handoff::user((int) $userdata['ID']) : false;
        if ($update && !$existing) {
            return Handoff::newError('invalid_user_id', 'Invalid user ID.');
        }
        $fields = UserSave::fields($userdata, $existing ? $existing->to_array() : null);
        if ($fields instanceof Refusal) {
            return Handoff::newError($fields->code, $fields->message, $fields->data);
        }
        $given = $userdata;
        $userdata = array_replace($userdata, $fields);
        ['user_pass' => $pass] = $userdata + ['user_pass' => null];
        if ($existing && (string) ($pass ?? '') === (string) $existing->user_pass) {
            $userdata = array_diff_key($userdata, ['user_pass' => true]);
        }
        $insert = new UserInsert(
            static fn (string $login): string => $login,
            static fn (string $slug): string => $slug,
            static fn (string $email): bool => (bool) Sanitize::isEmail($email),
            static fn (string $login): bool => false,
            static fn (string $email): int => (int) self::emailExists($email),
            static fn (string $role): bool => isset(Runtime::current()->capabilities->roles()->all()[$role]),
        );
        $resolved = $insert->resolve($userdata, $existing ? $existing->to_array() : null);
        if ($resolved instanceof Refusal) {
            return Handoff::newError($resolved->code, $resolved->message, $resolved->data);
        }
        if (!$update) {
            return self::insertAccount(self::filteredRow(UserInsert::account($userdata, $resolved, (string) Runtime::options()->filtered('default_role')), $given), $given);
        }
        return self::updateProfile($existing, $userdata, $resolved, $given);
    }

    /**
     * An account changed (wp_update_user): the stored account and its
     * profile under the given fields, as the reference merges them, then
     * the notices a changed password or email sends.
     */
    public static function update(mixed $userdata): mixed
    {
        $userdata = self::asArray($userdata);
        $id = (int) ($userdata['ID'] ?? 0);
        if ($id <= 0 || !Handoff::user($id)) {
            return Handoff::newError('invalid_user_id', 'Invalid user ID.');
        }
        $before = Handoff::user($id)->to_array();
        $merged = $before;
        foreach ([...UserInsert::META_KEYS, 'infinite_scrolling'] as $key) {
            if (MetaData::exists('user', $id, $key)) {
                $merged[$key] = MetaData::get('user', $id, $key, true);
            }
        }
        $result = self::insert(array_merge(Slashes::add($merged), $userdata));
        if (!Records::isError($result)) {
            self::changeNotices($before, $userdata);
            Runtime::hooks()->action('wp_update_user', [$id, $userdata, $userdata]);
        }
        return $result;
    }

    /**
     * An account deleted (wp_delete_user): its posts deleted, or handed to
     * the account named; each meta key removed with its own actions, then
     * the row; false for an unknown account.
     */
    public static function delete(mixed $id, mixed $reassign = null): bool
    {
        $id = (int) $id;
        $user = Handoff::user($id);
        if (!$user) {
            return false;
        }
        $hooks = Runtime::hooks();
        $hooks->action('delete_user', [$id, $reassign, $user]);
        $db = Runtime::current()->db;
        if ($reassign === null) {
            foreach ((new PostLookup($db))->idsByAuthor($id) as $postId) {
                PostWrites::delete($postId, true);
            }
        } else {
            (new PostWriter($db, new Posts($db), Runtime::current()->site))->reassignAuthor($id, (int) $reassign);
        }
        foreach ((new Meta($db))->rowsOf('user', $id) as $row) {
            MetaData::deleteByMid('user', $row['meta_id']);
        }
        (new Users($db))->deleteRow($id);
        self::cleanCache($user);
        $hooks->action('deleted_user', [$id, $reassign, $user]);
        return true;
    }

    /** A user's cached rows and meta let go (clean_user_cache), plugins told. */
    public static function cleanCache(mixed $user): void
    {
        $cleaned = is_object($user) ? (int) ($user->ID ?? 0) : (int) $user;
        if ($cleaned > 0) {
            RowCache::forget('users', $cleaned);
            RowCache::forget('user_meta', $cleaned);
        }
        $user = is_numeric($user) ? Handoff::newUser((int) $user) : $user;
        if (!$user instanceof User || !$user->exists()) {
            return;
        }
        Handoff::cacheDelete($user->ID, 'users');
        Handoff::cacheDelete($user->user_login, 'userlogins');
        Handoff::cacheDelete($user->user_nicename, 'userslugs');
        Handoff::cacheDelete($user->user_email, 'useremail');
        Handoff::cacheDelete($user->ID, 'user_meta');
        Runtime::hooks()->action('clean_user_cache', [$user->ID, $user]);
    }

    /** An account's row from an id, a login, a user object, or an object with an ID. */
    public static function row(mixed $user): ?UserRecord
    {
        $users = new Users(Runtime::current()->db);
        if (is_object($user) && isset($user->ID)) {
            return $users->find((int) $user->ID);
        }
        if (is_numeric($user)) {
            return $users->find((int) $user);
        }
        return is_string($user) && $user !== '' ? $users->findByLogin($user) : null;
    }

    /** @return array<string, mixed> the account's fields from an array, a plain object or a user object */
    private static function asArray(mixed $userdata): array
    {
        if ($userdata instanceof \stdClass) {
            return get_object_vars($userdata);
        }
        if ($userdata instanceof User) {
            return $userdata->to_array();
        }
        return (array) $userdata;
    }

    /**
     * A new account as the reference writes it: the row, wp_set_password,
     * each profile meta added, the role, the caches let go, then
     * user_register. A role given as false leaves the account without one
     * for the caller to add.
     *
     * @param array<string, mixed> $account
     * @param array<string, mixed> $userdata
     */
    private static function insertAccount(array $account, array $userdata): int
    {
        $users = new Users(Runtime::current()->db);
        $id = $users->insertAccount($account);
        ['user_registered' => $registered] = $userdata + ['user_registered' => null];
        if (!empty($registered)) {
            $users->update($id, ['user_registered' => (string) $registered]);
        }
        $hooks = Runtime::hooks();
        $hooks->action('wp_set_password', [(string) $account['password'], $id, Handoff::newUser($id)]);
        foreach (UserSave::meta(Users::profileMeta($account), $id, $userdata) as $key => $value) {
            MetaData::add('user', $id, $key, $value);
        }
        $user = Handoff::newUser($id);
        $user->set_role(array_key_exists('role', $userdata) && $userdata['role'] === false ? false : $account['role']);
        self::cleanCache($user);
        $hooks->action('user_register', [$id, $userdata]);
        return $id;
    }

    /**
     * A new account's row through wp_pre_insert_user_data, as the reference
     * hands it (the password hashed), and back.
     *
     * @param array<string, mixed> $account
     * @param array<string, mixed> $userdata
     * @return array<string, mixed>
     */
    private static function filteredRow(array $account, array $userdata): array
    {
        ['user_registered' => $registered] = $userdata + ['user_registered' => null];
        ['display_name' => $displayName] = $account + ['display_name' => null];
        $row = ['user_pass' => (string) Handoff::hashPassword((string) $account['password']), 'user_nicename' => (string) $account['nicename'], 'user_email' => (string) $account['email'], 'user_url' => (string) $account['url'], 'user_registered' => (string) ($registered ?? gmdate('Y-m-d H:i:s')), 'user_activation_key' => '', 'display_name' => (string) $displayName, 'user_login' => (string) $account['login']];
        $data = UserSave::data(Slashes::strip($row), 0, $userdata);
        $map = ['login' => 'user_login', 'nicename' => 'user_nicename', 'email' => 'user_email', 'url' => 'user_url', 'display_name' => 'display_name'];
        foreach ($map as $field => $column) {
            if (array_key_exists($column, $data)) {
                $account[$field] = (string) $data[$column];
            }
        }
        return $account;
    }

    /**
     * The update half of wp_insert_user: the changed columns, the profile
     * meta, the role.
     *
     * @param array<string, mixed> $userdata
     * @param array<string, mixed> $resolved
     * @param array<string, mixed> $given
     */
    private static function updateProfile(User $existing, array $userdata, array $resolved, array $given = []): int
    {
        $id = $existing->ID;
        $stored = $existing->to_array();
        $columns = Slashes::strip(UserInsert::changes($userdata, $stored, $resolved, static fn (string $url): string => Kses::url($url), static fn (string $password): string => (string) Handoff::hashPassword($password)));
        $row = array_intersect_key(array_replace($stored, $columns), array_flip(['user_pass', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'user_activation_key', 'display_name']));
        foreach (UserSave::data($row, $id, $given ?: $userdata) as $column => $value) {
            if ($column !== 'user_login' && (string) $value !== (string) ($stored[$column] ?? '')) {
                $columns[$column] = (string) $value;
            }
        }
        if ($columns !== []) {
            (new Users(Runtime::current()->db))->update($id, $columns);
        }
        $hooks = Runtime::hooks();
        ['user_pass' => $hash] = $columns + ['user_pass' => null];
        if ($hash !== null) {
            ['user_pass' => $plain] = $userdata + ['user_pass' => null];
            $hooks->action('wp_set_password', [(string) $plain, $id, $existing]);
        }
        self::cleanCache($id);
        $meta = array_intersect_key($userdata, array_flip([...UserInsert::META_KEYS, 'infinite_scrolling']));
        foreach (UserSave::meta($meta, $id, $given ?: $userdata) as $key => $value) {
            MetaData::updateUser($id, $key, $value);
        }
        if ($resolved['role'] !== null) {
            Handoff::newUser($id)->set_role($resolved['role']);
        }
        self::cleanCache($id);
        $hooks->action('profile_update', [$id, $existing, $userdata]);
        if (array_key_exists('spam', $userdata) && $userdata['spam'] !== null && $userdata['spam'] !== $existing->spam) {
            $hooks->action('make_ham_user', [$id]);
        }
        return $id;
    }

    /**
     * The notices a user whose password or email address just changed is
     * mailed, to the address they had.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $userdata
     */
    private static function changeNotices(array $user, array $userdata): void
    {
        $options = Runtime::options();
        $hooks = Runtime::hooks();
        ['user_login' => $login, 'user_email' => $email] = $user + ['user_login' => null, 'user_email' => null];
        ['user_pass' => $newPassword, 'user_email' => $newEmail] = $userdata + ['user_pass' => null, 'user_email' => null];
        $site = Entities::decode((string) $options->filtered('blogname'), ENT_QUOTES);
        $values = ['USERNAME' => (string) $login, 'ADMIN_EMAIL' => (string) $options->filtered('admin_email'), 'EMAIL' => (string) $email, 'SITENAME' => $site, 'SITEURL' => SiteUrls::home()];
        $sends = [];
        if (!empty($newPassword) && $hooks->filter('send_password_change_email', [true, $user, $userdata])) {
            $sends['password_change_email'] = ChangeNotices::password();
        }
        if ($newEmail !== null && (string) $newEmail !== (string) $email && $hooks->filter('send_email_change_email', [true, $user, $userdata])) {
            $sends['email_change_email'] = ChangeNotices::email();
            $values['NEW_EMAIL'] = (string) $newEmail;
        }
        foreach ($sends as $filter => $template) {
            $mail = $hooks->filter($filter, [['to' => $email] + $template + ['headers' => ''], $user, $userdata]);
            Handoff::mail($mail['to'], sprintf($mail['subject'], $site), ChangeNotices::fill((string) $mail['message'], $values), $mail['headers']);
        }
    }
}
