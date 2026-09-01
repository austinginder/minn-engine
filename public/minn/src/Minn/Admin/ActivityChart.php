<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Db;

/**
 * The overview's activity chart: published posts and pages plus every
 * comment, counted per bar. A window over 45 days is drawn in weeks,
 * anything shorter in days; each bar carries the (from, to] GMT bounds
 * the drill-down asks for.
 */
final readonly class ActivityChart
{
    public function __construct(
        private Db $db,
        private Site $site,
    ) {
    }

    /**
     * The bars for a window of days, counted from the database.
     *
     * @return list<array{label: string, value: int, from: string, to: string}>
     */
    public function bars(int $days, int $now): array
    {
        $since = gmdate('Y-m-d H:i:s', $now - $days * 86400);
        $dates = array_merge(
            array_column($this->db->rows(
                "SELECT post_date_gmt FROM {$this->db->table('posts')}
                 WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_date_gmt >= ?",
                [$since],
            ), 'post_date_gmt'),
            array_column($this->db->rows(
                "SELECT comment_date_gmt FROM {$this->db->table('comments')} WHERE comment_date_gmt >= ?",
                [$since],
            ), 'comment_date_gmt'),
        );
        return self::bucket($dates, $days, $now, $this->site->gmtOffset());
    }

    /**
     * GMT stamps into bars, oldest first. Labels are site-local: the day
     * for daily bars, "Week of" the bar's first day for weekly ones.
     *
     * @param list<string> $dates "Y-m-d H:i:s" in GMT
     * @return list<array{label: string, value: int, from: string, to: string}>
     */
    public static function bucket(array $dates, int $days, int $now, int $offset): array
    {
        $bucketDays = $days > 45 ? 7 : 1;
        $buckets = (int) ceil($days / $bucketDays);
        $span = $bucketDays * 86400;
        $series = array_fill(0, $buckets, 0);
        foreach ($dates as $date) {
            $age = $now - strtotime($date . ' UTC');
            $index = $buckets - 1 - (int) floor($age / $span);
            if ($index >= 0 && $index < $buckets) {
                $series[$index]++;
            }
        }
        $chart = [];
        foreach ($series as $index => $count) {
            $offsetDays = ($buckets - 1 - $index) * $bucketDays;
            $chart[] = [
                'label' => $bucketDays === 1
                    ? gmdate('M j', $now - $offsetDays * 86400 + $offset)
                    : 'Week of ' . gmdate('M j', $now - ($offsetDays + $bucketDays - 1) * 86400 + $offset),
                'value' => $count,
                'from' => gmdate('Y-m-d H:i:s', $now - ($buckets - $index) * $span),
                'to' => gmdate('Y-m-d H:i:s', $now - ($buckets - 1 - $index) * $span),
            ];
        }
        return $chart;
    }
}
