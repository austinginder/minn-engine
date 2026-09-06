<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The printf placeholders plugin code hands wpdb::prepare, filled the way
 * the reference fills them: a bare %s is escaped and quoted; a numbered
 * (%1$s), padded (%5s) or precise (%.2f) one is formatted and escaped but
 * never quoted, so a plugin can name a table with it; %d and %f cast; %i
 * backticks an identifier; %% is a literal. Numbered placeholders address
 * the argument list directly while unnumbered ones count from the first
 * on their own, as vsprintf does. A placeholder with no argument behind it
 * empties the whole query, as the reference does.
 */
final class Placeholders
{
    private const SPEC = '/%(?:(\d+)\$)?([-+ 0]*)(\d*)(?:\.(\d+))?([sdfFi%])/';

    /**
     * Fills a query's placeholders from the arguments; null when one has no argument.
     *
     * @param list<mixed> $args
     * @param callable(string): string $escape the connection's string escape
     */
    public static function fill(string $query, array $args, callable $escape): ?string
    {
        $query = str_replace(["'%s'", '"%s"'], '%s', $query);
        $args = array_values($args);
        $next = 0;
        $short = false;
        $filled = preg_replace_callback(self::SPEC, static function (array $m) use (&$next, &$short, $args, $escape): string {
            [$whole, $number, $flags, $width, $precision, $type] = $m;
            if ($type === '%') {
                return '%';
            }
            $position = $number !== '' ? (int) $number - 1 : $next++;
            if (!array_key_exists($position, $args)) {
                $short = true;
                return '';
            }
            $value = $args[$position];
            $spec = '%' . $flags . $width . ($precision !== '' ? '.' . $precision : '') . ($type === 'i' ? 's' : $type);
            return match ($type) {
                'd' => sprintf($spec, (int) $value),
                'f', 'F' => sprintf($spec, (float) $value),
                'i' => sprintf($spec, '`' . str_replace('`', '``', self::text($value)) . '`'),
                default => self::quoted($whole, sprintf($spec, $escape(self::text($value)))),
            };
        }, $query);
        return $short ? null : (string) $filled;
    }

    /** A bare %s is the one placeholder the reference wraps in quotes. */
    private static function quoted(string $placeholder, string $escaped): string
    {
        return $placeholder === '%s' ? "'" . $escaped . "'" : $escaped;
    }

    private static function text(mixed $value): string
    {
        return is_array($value) || is_object($value) ? '' : (string) $value;
    }
}
