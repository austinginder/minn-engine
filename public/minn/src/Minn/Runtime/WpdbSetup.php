<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;

/**
 * How the database object plugins hold as $wpdb is set up (probe
 * db-mail-rewrite-doubts): it connects through its own db_connect, which
 * sets the connection's names with prepare; set_prefix answers with the
 * prefix it had, names the old tables too, and with set_table_names off
 * changes only the base prefix; set_blog_id names one site's tables from
 * the base prefix; CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE name the
 * user tables; set_sql_mode reads the session's modes without a query of
 * its own; a database that cannot be selected leaves the object not ready.
 * One site only: the engine is never a network.
 */
final class WpdbSetup
{
    /** Sets the object up over the engine's connection (wpdb::__construct). */
    public static function construct(object $db, mixed $user, mixed $password, mixed $name, mixed $host): void
    {
        [$db->dbuser, $db->dbpassword, $db->dbname, $db->dbhost] = [$user, $password, $name, $host];
        $db->init_charset();
        $db->db_connect();
        $db->set_prefix(Runtime::current()->db->prefix());
    }

    /** Takes the engine's connection and sets its names (wpdb::db_connect): true. */
    public static function connect(object $db): bool
    {
        $db->dbh = Runtime::current()->db->connection();
        $db->set_charset($db->dbh);
        $db->ready = true;
        return true;
    }

    /** Sets a connection's charset and its names, with the collation when there is one (wpdb::set_charset). */
    public static function charset(object $db, mixed $dbh, mixed $charset, mixed $collate): void
    {
        $charset ??= $db->charset;
        $collate ??= $db->collate;
        if (!$dbh instanceof \mysqli || !$charset || !$db->has_cap('collation')) {
            return;
        }
        $dbh->set_charset((string) $charset);
        $names = (string) $db->prepare('SET NAMES %s', $charset) . ($collate ? (string) $db->prepare(' COLLATE %s', $collate) : '');
        $dbh->query($db->remove_placeholder_escape($names));
    }

    /**
     * Sets the table prefix (wpdb::set_prefix): the base prefix it had (the
     * new one the first time), or invalid_db_prefix for a prefix that is not
     * letters, digits and underscores.
     */
    public static function prefix(object $db, mixed $prefix, mixed $setTableNames): mixed
    {
        if (preg_match('/[^a-z0-9_]/i', (string) $prefix)) {
            return Handoff::newError('invalid_db_prefix', Gettext::text('Invalid database prefix'));
        }
        $old = $db->base_prefix ?? $prefix;
        $db->base_prefix = $prefix;
        if ($setTableNames) {
            $db->prefix = $prefix;
            self::name($db, $db->tables('all'));
            self::name($db, $db->tables('old'));
        }
        return $old;
    }

    /** Switches the site the object answers for (wpdb::set_blog_id): the id it had. */
    public static function blog(object $db, mixed $blogId, mixed $networkId): int
    {
        if (!empty($networkId)) {
            $db->siteid = (int) $networkId;
        }
        $old = (int) $db->blogid;
        $db->blogid = (int) $blogId;
        $db->prefix = $db->base_prefix;
        self::name($db, $db->tables('blog'));
        self::name($db, $db->tables('old'));
        return $old;
    }

    /**
     * The tables of a scope (wpdb::tables), by name to prefixed name unless
     * the prefix is not wanted; the user tables are the custom ones where
     * wp-config names them.
     */
    public static function tables(object $db, mixed $scope, mixed $prefix): array
    {
        $tables = match ($scope) {
            'blog' => $db->tables,
            'global' => $db->global_tables,
            'ms_global' => $db->ms_global_tables,
            'old' => $db->old_tables,
            default => array_merge($db->global_tables, $db->tables),
        };
        if (!$prefix) {
            return $tables;
        }
        $out = [];
        foreach ($tables as $table) {
            $out[$table] = (in_array($table, $db->global_tables, true) ? $db->base_prefix : $db->prefix) . $table;
        }
        $custom = ['users' => 'CUSTOM_USER_TABLE', 'usermeta' => 'CUSTOM_USER_META_TABLE'];
        foreach ($custom as $table => $constant) {
            if (isset($out[$table]) && defined($constant)) {
                $out[$table] = (string) constant($constant);
            }
        }
        return $out;
    }

    /** Selects a database; one that cannot be selected leaves the object not ready (wpdb::select). */
    public static function select(object $db, mixed $name, mixed $dbh): void
    {
        $dbh ??= $db->dbh;
        try {
            $selected = $dbh->select_db((string) $name);
        } catch (\mysqli_sql_exception) {
            $selected = false;
        }
        if (!$selected) {
            $db->ready = false;
        }
    }

    /**
     * Sets the session's SQL modes (wpdb::set_sql_mode): the given ones, or the
     * session's own read from the connection, less the modes
     * incompatible_sql_modes names.
     */
    public static function sqlMode(object $db, mixed $modes): void
    {
        if (empty($modes)) {
            $result = $db->dbh->query('SELECT @@SESSION.sql_mode');
            $modes = $result instanceof \mysqli_result ? (string) ($result->fetch_row()[0] ?? '') : '';
        }
        $current = is_array($modes) ? implode(',', $modes) : (string) $modes;
        $incompatible = (array) Runtime::hooks()->filter('incompatible_sql_modes', [\Minn\Db::INCOMPATIBLE_MODES]);
        \Minn\Db::current()->setModes(\Minn\Db::compatibleModes($current, array_map('strval', $incompatible)));
    }

    /** @param array<string, string> $tables names each table property */
    private static function name(object $db, array $tables): void
    {
        foreach ($tables as $table => $name) {
            $db->{$table} = $name;
        }
    }
}
