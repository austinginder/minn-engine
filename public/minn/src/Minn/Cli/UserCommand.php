<?php

declare(strict_types=1);

namespace Minn\Cli;

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
            'ID' => (string) $user['ID'],
            'user_login' => (string) $user['user_login'],
            'user_email' => (string) $user['user_email'],
            'user_registered' => (string) $user['user_registered'],
            'display_name' => (string) $user['display_name'],
            'roles' => implode(', ', $runtime->capabilities->rolesOf((int) $user['ID'])),
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
        $runtime->users->setMeta((int) $user['ID'], 'cove_login_token', $token);
        $runtime->users->setMeta((int) $user['ID'], 'cove_login_token_time', (string) time());
        WP_CLI::log($runtime->permalinks->url('/wp-login.php') . '?user_id=' . (int) $user['ID'] . '&cove_login_token=' . $token);
    }

    private static function find(string $identifier): ?array
    {
        $users = Runtime::boot()->users;
        return match (true) {
            ctype_digit($identifier) => $users->find((int) $identifier),
            str_contains($identifier, '@') => $users->findByEmail($identifier),
            default => $users->findByLogin($identifier),
        };
    }

    /** @param list<string> $roles */
    private static function item(array $row, array $roles, string $glue): array
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
