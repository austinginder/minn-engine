<?php

declare(strict_types=1);

namespace Minn\Content;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Minn\Db;
use Minn\Runtime\Runtime;

/** Site-wide options and the site's clock. */
final readonly class Site
{
    public function __construct(private Db $db)
    {
    }

    /** One option's raw value, or null when it is unset. */
    public function option(string $name): ?string
    {
        return $this->db->option($name);
    }

    /**
     * Writes an option, inserting it as autoloaded when it does not exist;
     * the option store hears of it. An $autoload given is written too, so a
     * cache the engine reads on demand (autoload "off") stays out of the
     * list every request loads.
     */
    public function setOption(string $name, string $value, ?string $autoload = null): void
    {
        $table = $this->db->table('options');
        if ($this->db->storedOption($name) === null) {
            $this->db->execute("INSERT INTO {$table} (option_name, option_value, autoload) VALUES (?, ?, ?)", [$name, $value, $autoload ?? 'auto']);
        } elseif ($autoload === null) {
            $this->db->execute("UPDATE {$table} SET option_value = ? WHERE option_name = ?", [$value, $name]);
        } else {
            $this->db->execute("UPDATE {$table} SET option_value = ?, autoload = ? WHERE option_name = ?", [$value, $autoload, $name]);
        }
        Runtime::optionWritten($name);
    }

    /** Removes an option row; the option store hears of it. */
    public function deleteOption(string $name): void
    {
        $this->db->execute("DELETE FROM {$this->db->table('options')} WHERE option_name = ?", [$name]);
        Runtime::optionWritten($name);
    }

    /**
     * A new post's comment or ping status, as the reference picks it: a post
     * follows the site's two defaults, an attachment follows the comment
     * default and never takes pings, a page and every other type start
     * closed; with plugins loaded, get_default_comment_status() decides
     * (support a plugin removed, the filter). $kind is "comment" or
     * "pingback".
     */
    public function defaultDiscussion(string $type, string $kind): string
    {
        // With plugins loaded, their say counts: a removed comments support or the filter.
        if (\Minn\Runtime\Runtime::booted() && \function_exists('get_default_comment_status')) {
            return \get_default_comment_status($type, $kind) === 'open' ? 'open' : 'closed';
        }
        $follows = $type === 'post' || ($type === 'attachment' && $kind === 'comment');
        $value = $this->option($kind === 'comment' ? 'default_comment_status' : 'default_ping_status') ?? 'open';
        return $follows && $value === 'open' ? 'open' : 'closed';
    }

    /**
     * The site's timezone (wp_timezone): the zone timezone_string names, else
     * gmt_offset as a fixed offset. With a zone set, the stored gmt_offset is
     * not read at all, as the reference does not read it.
     */
    public function timezone(): DateTimeZone
    {
        $name = (string) ($this->db->option('timezone_string') ?? '');
        if ($name !== '') {
            try {
                return new DateTimeZone($name);
            } catch (Exception) {
                // Not a zone PHP knows; the offset stands in.
            }
        }
        $seconds = (int) round(((float) ($this->db->option('gmt_offset') ?? 0)) * 3600);
        return new DateTimeZone(sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60)));
    }

    /** The site's offset from UTC in seconds now: the zone's, daylight saving included, or gmt_offset's. */
    public function gmtOffset(): int
    {
        return $this->timezone()->getOffset(new DateTimeImmutable('now'));
    }

    /** Now in the site's local time, MySQL format. */
    public function localNow(): string
    {
        return gmdate('Y-m-d H:i:s', time() + $this->gmtOffset());
    }

    /**
     * A site-local datetime from a write body ("2031-01-01T00:00:00") to
     * the pair of columns it fills, plus its timestamp.
     *
     * @return array{0: string, 1: string, 2: int} local, gmt, timestamp
     */
    public function localDate(string $input): array
    {
        $local = str_replace('T', ' ', $input);
        // The offset of the zone at that date, not today's: a summer date stays in summer time.
        $stamp = (new DateTimeImmutable($local, $this->timezone()))->getTimestamp();
        return [$local, gmdate('Y-m-d H:i:s', $stamp), $stamp];
    }

    /**
     * A GMT datetime from a write body (date_gmt) to the same pair of
     * columns, its local half in the site's zone at that date.
     *
     * @return array{0: string, 1: string, 2: int} local, gmt, timestamp
     */
    public function gmtDate(string $input): array
    {
        $stamp = (new DateTimeImmutable(str_replace('T', ' ', $input), new DateTimeZone('UTC')))->getTimestamp();
        return [(new DateTimeImmutable('@' . $stamp))->setTimezone($this->timezone())->format('Y-m-d H:i:s'), gmdate('Y-m-d H:i:s', $stamp), $stamp];
    }
}
