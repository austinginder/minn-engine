<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\UserRecord;
use Minn\Auth\PasswordReset;
use Minn\Auth\Roles;
use Minn\Mail\Mailer;
use WP_CLI;
use WP_CLI\Formatter;

/** Users: the list and get views, and the one-time login link. */
final class UserCommand
{
    private const FIELDS = ['ID', 'user_login', 'display_name', 'user_email', 'user_registered', 'roles'];

    /**
     * Lists users.
     *
     * ## OPTIONS
     *
     * [--role=<role>]
     * : Only display users with a certain role.
     *
     * [--field=<field>]
     * : Prints the value of a single field for each user.
     *
     * [--fields=<fields>]
     * : Limit the output to specific object fields.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - ids
     *   - json
     *   - count
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function list(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $role = (string) ($assocArgs['role'] ?? '');
        $rows = $runtime->db->rows("SELECT * FROM {$runtime->db->table('users')} ORDER BY user_login ASC");
        $items = [];
        foreach ($rows as $row) {
            $roles = $runtime->capabilities->rolesOf((int) $row['ID']);
            if ($role !== '' && !in_array($role, $roles, true)) {
                continue;
            }
            $items[] = self::item($row, $roles, ',');
        }
        $format = (string) ($assocArgs['format'] ?? 'table');
        if ($format === 'ids' || $format === 'count') {
            $items = array_map(static fn (array $item) => $item['ID'], $items);
        }
        (new Formatter($assocArgs, self::FIELDS))->display_items($items);
    }

    /**
     * Gets details about a user.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, user email, or user login.
     *
     * [--field=<field>]
     * : Instead of returning the whole user, returns the value of a single field.
     *
     * [--fields=<fields>]
     * : Get a specific subset of the user's fields.
     *
     * [--format=<format>]
     * : Render output in a particular format.
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * @when before_wp_load
     */
    public function get(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $user = self::find($args[0]) ?? WP_CLI::error("Invalid user ID, email or login: '{$args[0]}'");
        // A single user reads in the reference's record order, its id as the stored string.
        $data = [
            'ID' => (string) $user->id,
            'user_login' => $user->login,
            'user_email' => $user->email,
            'user_registered' => $user->registered,
            'display_name' => $user->displayName,
            'roles' => implode(', ', $runtime->capabilities->rolesOf($user->id)),
        ];
        (new Formatter($assocArgs, array_keys($data)))->display_item($data);
    }

    /**
     * Prints a one-time login link for a user.
     *
     * The link signs the user in at wp-login.php once, within fifteen
     * minutes, and then expires (the contract of the captaincore helper).
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, user email, or user login.
     *
     * @when before_wp_load
     */
    public function login(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $user = self::find($args[0]) ?? WP_CLI::error("User not found: {$args[0]}");
        $token = substr(sha1(random_bytes(24)), 0, 7);
        // Stored raw, the captaincore-helper mu-plugin's own format: with a
        // hide-login plugin standing $pagenow the helper validates this token
        // itself, and it compares the stored value to the link verbatim. The
        // trade-off (a database read yields the link) is bounded by the
        // 15-minute single-use window and matches every real Cove site.
        $runtime->users->setMeta($user->id, 'cove_login_token', $token);
        $runtime->users->setMeta($user->id, 'cove_login_token_time', (string) time());
        WP_CLI::log($runtime->permalinks->url('/wp-login.php') . '?user_id=' . $user->id . '&cove_login_token=' . $token);
    }

    /**
     * Creates a new user.
     *
     * ## OPTIONS
     *
     * <user-login>
     * : The login of the user to create.
     *
     * <user-email>
     * : The email address of the user to create.
     *
     * [--role=<role>]
     * : The role of the user to create. Default: the site's default role.
     *
     * [--user_pass=<password>]
     * : The user password. Default: randomly generated.
     *
     * [--display_name=<name>]
     * : The display name.
     *
     * [--user_nicename=<nice_name>]
     * : A URL-friendly name. Default: the login.
     *
     * [--user_url=<url>]
     * : The user's URL.
     *
     * [--nickname=<nickname>]
     * : The nickname. Default: the login.
     *
     * [--first_name=<first_name>]
     * : The first name.
     *
     * [--last_name=<last_name>]
     * : The last name.
     *
     * [--description=<description>]
     * : A description.
     *
     * [--send-email]
     * : Send the new user a password-reset email.
     *
     * [--porcelain]
     * : Output just the new user id.
     *
     * @when before_wp_load
     */
    public function create(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        [$login, $email] = $args;
        if ($runtime->users->findByLogin($login) !== null) {
            WP_CLI::error("The '{$login}' username is already registered.");
        }
        if ($runtime->users->findByEmail($email) !== null) {
            WP_CLI::error('Sorry, that email address is already used!');
        }
        $role = (string) ($assocArgs['role'] ?? ($runtime->site->option('default_role') ?? 'subscriber'));
        if (!isset($runtime->capabilities->roles()->all()[$role])) {
            WP_CLI::error("Role doesn't exist: {$role}");
        }
        $generated = !isset($assocArgs['user_pass']);
        $password = $generated ? self::randomPassword() : (string) $assocArgs['user_pass'];
        $first = (string) ($assocArgs['first_name'] ?? '');
        $last = (string) ($assocArgs['last_name'] ?? '');
        $display = (string) ($assocArgs['display_name'] ?? '');
        if ($display === '' && ($first !== '' || $last !== '')) {
            $display = trim($first . ' ' . $last);
        }
        $account = [
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'display_name' => $display !== '' ? $display : $login,
            'url' => (string) ($assocArgs['user_url'] ?? ''),
            'nickname' => (string) ($assocArgs['nickname'] ?? $login),
            'first_name' => $first,
            'last_name' => $last,
            'description' => (string) ($assocArgs['description'] ?? ''),
        ];
        if (isset($assocArgs['user_nicename']) && (string) $assocArgs['user_nicename'] !== '') {
            $account['nicename'] = (string) $assocArgs['user_nicename'];
        }
        $id = $runtime->users->createAccount($account);
        if (isset($assocArgs['send-email'])) {
            $user = $runtime->users->find($id);
            if ($user !== null) {
                $key = (new PasswordReset($runtime->users))->issue($user);
                $home = rtrim((string) ($runtime->site->option('home') ?? ''), '/');
                $link = $home . '/wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($login);
                Mailer::forSite($runtime->site)->send(Mailer::noticesFor($runtime->site)->loginDetails($login, $email, $link));
            }
        }
        if (isset($assocArgs['porcelain'])) {
            WP_CLI::log((string) $id);
            return;
        }
        WP_CLI::success("Created user {$id}.");
        if ($generated) {
            WP_CLI::log('Password: ' . $password);
        }
    }

    /**
     * Updates an existing user.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, email, or login.
     *
     * [--user_pass=<password>]
     * : A new password.
     *
     * [--user_email=<email>]
     * : A new email.
     *
     * [--display_name=<name>]
     * : A new display name.
     *
     * [--first_name=<first_name>]
     * : A new first name.
     *
     * [--last_name=<last_name>]
     * : A new last name.
     *
     * [--role=<role>]
     * : A new role.
     *
     * @when before_wp_load
     */
    public function update(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $user = self::find($args[0]);
        if ($user === null) {
            WP_CLI::warning("Invalid user ID, email or login: '{$args[0]}'");
            WP_CLI::error('No valid users found.');
        }
        $id = $user->id;
        $columns = [];
        if (isset($assocArgs['user_pass'])) {
            $columns['user_pass'] = \Minn\Auth\Password::hash((string) $assocArgs['user_pass']);
        }
        if (isset($assocArgs['user_email'])) {
            $columns['user_email'] = (string) $assocArgs['user_email'];
        }
        if (isset($assocArgs['display_name'])) {
            $columns['display_name'] = (string) $assocArgs['display_name'];
        }
        if (isset($assocArgs['user_url'])) {
            $columns['user_url'] = (string) $assocArgs['user_url'];
        }
        if ($columns !== []) {
            $runtime->users->update($id, $columns);
        }
        foreach (['first_name', 'last_name', 'nickname', 'description'] as $meta) {
            if (isset($assocArgs[$meta])) {
                $runtime->users->setMeta($id, $meta, (string) $assocArgs[$meta]);
            }
        }
        if (isset($assocArgs['role'])) {
            $role = (string) $assocArgs['role'];
            if (!isset($runtime->capabilities->roles()->all()[$role])) {
                WP_CLI::error("Role doesn't exist: {$role}");
            }
            $prefix = $runtime->db->prefix();
            $runtime->users->setMeta($id, "{$prefix}capabilities", Roles::serializeSingle($role));
            $runtime->users->setMeta($id, "{$prefix}user_level", (string) Roles::level($role));
        }
        WP_CLI::success("Updated user {$id}.");
    }

    /**
     * Deletes a user.
     *
     * ## OPTIONS
     *
     * <user>
     * : User ID, email, or login.
     *
     * [--reassign=<id>]
     * : User ID to reassign posts to.
     *
     * [--yes]
     * : Answer yes to the confirmation.
     *
     * @when before_wp_load
     */
    public function delete(array $args, array $assocArgs): void
    {
        $runtime = Runtime::boot();
        $user = self::find($args[0]) ?? WP_CLI::error("Invalid user ID, email or login: '{$args[0]}'");
        $id = $user->id;
        if (!isset($assocArgs['yes'])) {
            WP_CLI::confirm("Are you sure you want to delete this user?");
        }
        $posts = $runtime->db->table('posts');
        if (isset($assocArgs['reassign']) && ctype_digit((string) $assocArgs['reassign'])) {
            $runtime->db->execute("UPDATE {$posts} SET post_author = ? WHERE post_author = ?", [(int) $assocArgs['reassign'], $id]);
        } else {
            $runtime->db->execute("DELETE FROM {$posts} WHERE post_author = ?", [$id]);
        }
        $runtime->users->delete($id);
        $home = (string) ($runtime->site->option('home') ?? '');
        WP_CLI::success("Removed user {$id} from {$home}.");
    }

    private static function randomPassword(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_[]{}<>~`+=,.;:/?|';
        $out = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < 24; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    private static function find(string $identifier): ?UserRecord
    {
        $users = Runtime::boot()->users;
        return match (true) {
            ctype_digit($identifier) => $users->find((int) $identifier),
            str_contains($identifier, '@') => $users->findByEmail($identifier),
            default => $users->findByLogin($identifier),
        };
    }

    /** @param list<string> $roles */
    private static function item(array|UserRecord $row, array $roles, string $glue): array
    {
        return [
            'ID' => (int) $row['ID'],
            'user_login' => (string) $row['user_login'],
            'display_name' => (string) $row['display_name'],
            'user_email' => (string) $row['user_email'],
            'user_registered' => (string) $row['user_registered'],
            'roles' => implode($glue, $roles),
        ];
    }
}
