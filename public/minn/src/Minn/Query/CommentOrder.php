<?php

declare(strict_types=1);

namespace Minn\Query;

use Minn\Support\Lists;

/**
 * WP_Comment_Query's ORDER BY and LIMIT as the reference writes them
 * (probe wp-comment-query-sql): no orderby means comment_date_gmt in the
 * order asked; a list, a string of keys or a map of keys to directions
 * each become a column, a meta value or the FIELD() list comment__in
 * gives; a key it does not know is dropped (none left means
 * comment_date_gmt), and unless comment_ID or comment__in is among them a
 * comment_ID tiebreak follows, in the first date clause's direction or
 * DESC.
 */
final class CommentOrder
{
    private const COLUMNS = ['comment_agent', 'comment_approved', 'comment_author', 'comment_author_email', 'comment_author_IP', 'comment_author_url', 'comment_content', 'comment_date', 'comment_date_gmt', 'comment_ID', 'comment_karma', 'comment_parent', 'comment_post_ID', 'comment_type', 'user_id'];
    private const IDS = 'comment__in';

    /** ASC when asked for, DESC for anything else. */
    public static function direction(mixed $order): string
    {
        return is_string($order) && strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * The ORDER BY body for the query's orderby and order ('' for none).
     *
     * @param array<string, mixed> $qv
     * @param array<array-key, array<string, mixed>> $metaClauses the meta query's clauses, by name
     */
    public static function build(array $qv, string $table, string $metaTable, array $metaClauses): string
    {
        ['orderby' => $orderby, 'order' => $order] = $qv + ['orderby' => '', 'order' => ''];
        $order = self::direction($order);
        if ($orderby === 'none') {
            return '';
        }
        if (empty($orderby)) {
            return "{$table}.comment_date_gmt {$order}";
        }
        $parts = [];
        $byId = false;
        foreach (is_array($orderby) ? $orderby : (array) preg_split('/[,\s]/', (string) $orderby) as $key => $value) {
            if (!$value) {
                continue;
            }
            [$by, $dir] = is_int($key) ? [(string) $value, $order] : [(string) $key, self::direction($value)];
            $parsed = self::clause($by, $qv, $table, $metaTable, $metaClauses);
            if ($parsed !== '') {
                // The given order has no direction, and is a tiebreak of its own.
                $parts[] = $by === self::IDS ? $parsed : "{$parsed} {$dir}";
                $byId = $byId || $by === self::IDS || $by === 'comment_ID';
            }
        }
        $parts = $parts ?: ["{$table}.comment_date_gmt {$order}"];
        if (!$byId) {
            $parts[] = "{$table}.comment_ID " . self::tiebreak($parts);
        }
        return implode(', ', $parts);
    }

    /**
     * One orderby key as SQL, or '' when the reference does not take it: a
     * column, the meta value (as a number when asked), a named meta
     * clause's cast value, or the FIELD() list keeping comment__in's order.
     *
     * @param array<string, mixed> $qv
     * @param array<array-key, array<string, mixed>> $metaClauses
     */
    public static function clause(string $by, array $qv, string $table, string $metaTable, array $metaClauses): string
    {
        $metaKey = is_string($qv['meta_key'] ?? null) ? $qv['meta_key'] : '';
        $named = $metaClauses[$by] ?? null;
        return match (true) {
            in_array($by, self::COLUMNS, true) => "{$table}.{$by}",
            $by === 'meta_value' || ($metaKey !== '' && $by === $metaKey) => "{$metaTable}.meta_value",
            $by === 'meta_value_num' => "{$metaTable}.meta_value+0",
            $by === self::IDS => self::field($qv[self::IDS] ?? [], $table),
            is_array($named) => "CAST({$named['alias']}.meta_value AS {$named['cast']})",
            default => '',
        };
    }

    /** LIMIT from number, and the offset (or the page when no offset is given). @param array<string, mixed> $qv */
    public static function limits(array $qv): string
    {
        $number = abs((int) ($qv['number'] ?? 0));
        if ($number === 0) {
            return '';
        }
        $offset = abs((int) ($qv['offset'] ?? 0));
        $start = $offset ?: max(0, $number * (abs((int) ($qv['paged'] ?? 1)) - 1));
        return "LIMIT {$start},{$number}";
    }

    /** The comment__in ids in the order given, or '' when none are. */
    private static function field(mixed $given, string $table): string
    {
        $ids = Lists::ids($given);
        return $ids === [] ? '' : "FIELD( {$table}.comment_ID, " . implode(',', $ids) . ' )';
    }

    /** The tiebreak's direction: the first date clause's, else DESC. @param list<string> $parts */
    private static function tiebreak(array $parts): string
    {
        foreach ($parts as $part) {
            if (preg_match('/comment_date(?:_gmt)? (ASC|DESC)$/', $part, $match) === 1) {
                return $match[1];
            }
        }
        return 'DESC';
    }
}
