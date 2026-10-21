<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Escape;
use Minn\Support\Lists;

/**
 * What the database object plugins hold as $wpdb does when it runs and reads
 * (probe db-mail-rewrite-doubts): a query is heard by the query filter while
 * the last one's state is still there, and is not run at all while the
 * object is not ready; the readers ask check_safe_collation before running
 * one, give null for an empty value, and give nothing when asked to read
 * without a query (get_row, get_results) or in an output they do not know;
 * prepare's notices; and print_error, which keeps every error in
 * $EZSQL_ERROR and logs it with its caller. Members the object keeps
 * protected are reached in its own scope.
 */
final class WpdbReads
{
    private const LOG_LINE = 'WordPress database error %1$s for query %2$s';

    /** Runs a query through the object, as wpdb::query: the number of rows read or written, true, or false. */
    public static function query(object $db, mixed $query): int|bool
    {
        if (!$db->ready) {
            (function (): void {
                $this->check_current_query = true;
            })->call($db);
            return false;
        }
        $query = (string) Runtime::hooks()->filter('query', [$db->remove_placeholder_escape((string) $query)]);
        if (empty($query)) {
            return false;
        }
        $db->flush();
        $db->last_query = $query;
        $logged = defined('SAVEQUERIES') && constant('SAVEQUERIES');
        if ($logged) {
            $db->timer_start();
        }
        [$result, $error, $ran] = self::run($db, $query);
        $db->num_queries += $ran ? 1 : 0;
        if ($logged && $ran) {
            $db->log_query($query, $db->timer_stop(), $db->get_caller(), $db->time_start, []);
        }
        $writes = preg_match('/^\s*(insert|delete|update|replace)\s/i', $query) === 1;
        if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
            $db->insert_id = $result === false ? 0 : (int) $db->dbh->insert_id;
        }
        if ($result === false) {
            $db->last_error = $error;
            $db->print_error();
            return false;
        }
        if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            return true;
        }
        if ($writes) {
            $db->rows_affected = (int) $db->dbh->affected_rows;
            \Minn\Db::wrote($query);
            return $db->rows_affected;
        }
        return $result instanceof \mysqli_result ? self::keep($db, $result) : true;
    }

    /** One value of the last result (or of a query run first): null for an empty one. */
    public static function value(object $db, mixed $query, mixed $x, mixed $y): mixed
    {
        if ($query) {
            self::ask($db, $query);
        }
        $row = $db->last_result[(int) $y] ?? null;
        $value = $row ? (array_values(get_object_vars($row))[(int) $x] ?? null) : null;
        return $value === '' ? null : $value;
    }

    /** One row of a query, as an object or an array; null with no query, or in an output it does not know. */
    public static function row(object $db, mixed $query, mixed $output, mixed $y): mixed
    {
        if (!$query) {
            return null;
        }
        self::ask($db, $query);
        $row = $db->last_result[(int) $y] ?? null;
        if ($row === null) {
            return null;
        }
        return match (true) {
            self::isObjectOutput($output) => $row,
            $output === 'ARRAY_A' => get_object_vars($row),
            $output === 'ARRAY_N' => array_values(get_object_vars($row)),
            default => null,
        };
    }

    /**
     * One column of the last result (or of a query run first), each value read
     * through the object's own get_var.
     *
     * @return list<mixed>
     */
    public static function column(object $db, mixed $query, mixed $x): array
    {
        if ($query) {
            self::ask($db, $query);
        }
        $out = [];
        for ($i = 0, $rows = count((array) $db->last_result); $i < $rows; $i++) {
            $out[$i] = $db->get_var(null, $x, $i);
        }
        return $out;
    }

    /** The rows of a query in an output shape; null with no query, or in an output it does not know. */
    public static function rows(object $db, mixed $query, mixed $output): ?array
    {
        if (!$query) {
            return null;
        }
        self::ask($db, $query);
        $rows = (array) $db->last_result;
        if (self::isObjectOutput($output)) {
            return $rows;
        }
        return in_array($output, ['OBJECT_K', 'ARRAY_A', 'ARRAY_N'], true) ? Lists::shapeRows($rows, (string) $output) : null;
    }

    /**
     * A query with its placeholders filled (Placeholders), every literal % as
     * the placeholder escape query() takes back out (wpdb::prepare). One
     * argument that is a list stands for the arguments. Reported through
     * doing_it_wrong: an argument it cannot use, a query with no % at all, a
     * count that does not match (too few arguments empty the query), and a
     * list of other than one value for a query with one placeholder (null).
     *
     * @param list<mixed> $args
     */
    public static function prepare(object $db, mixed $query, array $args): ?string
    {
        if ($query === null) {
            return null;
        }
        $query = (string) $query;
        $list = count($args) === 1 && is_array($args[0]) ? $args[0] : null;
        $args = $list === null ? $args : array_values($list);
        $prepared = Placeholders::prepare($query, $args, static fn (string $value): string => $db->_real_escape($value));
        if ($list !== null && $prepared['placeholders'] === 1 && count($list) !== 1) {
            Notices::doingItWrong('wpdb::prepare', Gettext::text('The query only expected one placeholder, but an array of multiple placeholders was sent.'), '4.9.0');
            return null;
        }
        foreach ($prepared['unsupported'] as $type) {
            Notices::doingItWrong('wpdb::prepare', sprintf(Gettext::text('Unsupported value type (%s).'), $type), '4.8.2');
        }
        if (!str_contains($query, '%')) {
            Notices::doingItWrong('wpdb::prepare', sprintf(Gettext::text('The query argument of %s must have a placeholder.'), 'wpdb::prepare()'), '3.9.0');
        }
        if ($prepared['placeholders'] !== count($args)) {
            Notices::doingItWrong('wpdb::prepare', sprintf(Gettext::text('The query does not contain the correct number of placeholders (%1$d) for the number of arguments passed (%2$d).'), $prepared['placeholders'], count($args)), '4.8.3');
        }
        return $prepared['query'] === null ? '' : $db->add_placeholder_escape($prepared['query']);
    }

    /**
     * Reports a database error: kept in $EZSQL_ERROR, then (unless errors are
     * suppressed) logged with the calls that led to it. The markup to print
     * while errors are shown, else ''.
     */
    public static function printError(object $db, mixed $str): string
    {
        $str = $str === '' || $str === null ? (string) $db->last_error : (string) $str;
        $GLOBALS['EZSQL_ERROR'][] = ['query' => $db->last_query, 'error_str' => $str];
        if ($db->suppress_errors) {
            return '';
        }
        $caller = (string) $db->get_caller();
        error_log(sprintf(self::LOG_LINE, $str, (string) $db->last_query) . ($caller !== '' ? " made by {$caller}" : ''));
        if (!$db->show_errors) {
            return '';
        }
        return '<div id="error"><p class="wpdberror"><strong>WordPress database error:</strong> [' . Escape::html($str) . ']<br /><code>' . Escape::html((string) $db->last_query) . '</code></p></div>';
    }

    /** Runs a reader's query, skipping the text check when check_safe_collation finds it safe. */
    private static function ask(object $db, mixed $query): void
    {
        (function () use ($query): void {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }
        })->call($db);
        $db->query($query);
    }

    /**
     * Sends a query, unless it holds text its table cannot store.
     *
     * @return array{0: mixed, 1: string, 2: bool} the result, the error, whether it reached the server
     */
    private static function run(object $db, string $query): array
    {
        $invalid = (function () use ($query): bool {
            $check = $this->check_current_query;
            $this->check_current_query = true;
            return $check && !$this->check_ascii($query) && !\Minn\Support\Utf8::fits($query, WpdbFields::queryCharset($this, $query));
        })->call($db);
        if ($invalid) {
            return [false, 'WordPress database error: Could not perform query because it contains invalid data.', false];
        }
        try {
            $result = $db->dbh->query($query);
            return [$result, (string) $db->dbh->error, true];
        } catch (\mysqli_sql_exception $e) {
            return [false, $e->getMessage(), true];
        }
    }

    /** A read's rows kept on the object: the count of them. */
    private static function keep(object $db, \mysqli_result $result): int
    {
        $db->col_info = $result->fetch_fields();
        (function () use ($db): void {
            $this->last_fields = $db->col_info;
        })->call($db);
        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        $result->free();
        $db->last_result = $rows;
        return $db->num_rows = count($rows);
    }

    /** OBJECT in any case; the other outputs are matched exactly. */
    private static function isObjectOutput(mixed $output): bool
    {
        return is_string($output) && strtoupper($output) === 'OBJECT';
    }
}
