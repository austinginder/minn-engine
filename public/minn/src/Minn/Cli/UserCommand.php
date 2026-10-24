<?php

declare(strict_types=1);

namespace Minn\Cli;

use Minn\Content\UserRecord;
use Minn\Login\OneTimeLink;
use WP_CLI;
use WP_CLI\Formatter;

/**
 * Users: the list and get views, and the one-time login link. The list and
 * get verbs take WP-CLI's bundled synopsis (Commands); a list the engine
 * does not answer itself (query arguments, users without a role, fields
 * beyond the record's own) goes back to the bundled command.
 */
final class UserCommand
{
    private const FIELDS = ['ID', 'user_login', 'display_name', 'user_email', 'user_registered', 'roles'];

    /** What the engine's own list reads: the role filter and the output arguments. */
    private const LIST_ARGS = ['role', 'field', 'fields', 'format'];

    /**
     * Lists users.
     *
     * ## OPTIONS
     *
     * [--role=<role>]
     * : Only display users with a certain role.
     *
     * [--<field>=<value>]
     * : Control output by one or more arguments of the user query.
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
        if (!self::ownList($assocArgs)) {
            Bundled::run('User_Command', 'list_', $args, $assocArgs);
            return;
        }
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
        // The stored record in its column order, each value the stored string, then the roles.
        $data = array_map('strval', $user->row());
        $data['roles'] = implode(', ', $runtime->capabilities->rolesOf($user->id));
        (new Formatter($assocArgs, self::FIELDS))->display_item($data);
    }

    /**
     * Prints a one-time login link for a user.
     *
     * The link signs the user in at wp-login.php once, within fifteen
     * minutes, and then expires (the contract of the captaincore helper;
     * `minn login` mints the same link without WP-CLI).
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
        WP_CLI::log($runtime->permalinks->url('/wp-login.php') . '?' . (new OneTimeLink($runtime->users))->mint($user));
    }

    /**
     * A user by the identifier WP-CLI's user fetcher takes (Users::identify),
     * warned about when an id and a login are the same number;
     * WP_CLI_FORCE_USER_LOGIN reads every identifier as a login.
     */
    private static function find(string $identifier): ?UserRecord
    {
        $users = Runtime::boot()->users;
        if (getenv('WP_CLI_FORCE_USER_LOGIN')) {
            return $users->findByLogin($identifier);
        }
        if (ctype_digit($identifier) && $users->find((int) $identifier) !== null && $users->findByLogin($identifier) !== null) {
            WP_CLI::warning(sprintf("Ambiguous user match detected (both ID and user_login exist for identifier '%d'). WP-CLI will default to the ID, but you can force user_login instead with WP_CLI_FORCE_USER_LOGIN=1.", (int) $identifier));
        }
        return $users->identify($identifier);
    }

    /** Whether the engine answers this list itself: only a role (not "none") and the record's own fields asked for. */
    private static function ownList(array $assocArgs): bool
    {
        if (array_diff(array_keys($assocArgs), self::LIST_ARGS) !== [] || ($assocArgs['role'] ?? '') === 'none') {
            return false;
        }
        $fields = isset($assocArgs['field']) ? [(string) $assocArgs['field']] : [];
        if (isset($assocArgs['fields'])) {
            $fields = array_merge($fields, array_map('trim', explode(',', (string) $assocArgs['fields'])));
        }
        return array_diff($fields, self::FIELDS) === [];
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
