<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\Posts;

/**
 * The archive periods wp_get_archives lists: months, years, days and weeks
 * that hold a published post of a type, newest first, each with its post
 * count and archive URL, plus the post-by-post and alphabetical listings.
 * Labels come from the caller's date formatter so the site's locale and
 * week start apply; the week label joins the week's first and last day.
 */
final readonly class Archives
{
    /**
     * @param Closure(string, string): string $date formats a MySQL datetime with a PHP date format
     * @param Closure(int, ?int, ?int): string $dateLink the year, month, or day archive URL
     * @param Closure(string): array{start: int, end: int} $week the week's first and last second around a datetime
     */
    public function __construct(
        private Posts $posts,
        private Closure $date,
        private Closure $dateLink,
        private Closure $week,
    ) {
    }

    /**
     * The periods of one granularity: monthly, yearly, daily, or weekly.
     *
     * @return list<array{period: array{year: int, month: int, day: int, week: int}, url: string, text: string, count: int}>
     */
    public function periods(string $granularity, string $type, string $order, int $limit, Closure $weekLink): array
    {
        $out = [];
        foreach ($this->posts->archiveBuckets($granularity, $type, $order, $limit) as $bucket) {
            $period = ['year' => $bucket['year'], 'month' => $bucket['month'], 'day' => $bucket['day'], 'week' => $bucket['week']];
            $out[] = ['period' => $period] + match ($granularity) {
                'yearly' => ['url' => ($this->dateLink)($bucket['year'], null, null), 'text' => sprintf('%d', $bucket['year']), 'count' => $bucket['count']],
                'daily' => ['url' => ($this->dateLink)($bucket['year'], $bucket['month'], $bucket['day']), 'text' => ($this->date)('F j, Y', $bucket['first']), 'count' => $bucket['count']],
                'weekly' => $this->weekPeriod($bucket, $weekLink),
                default => ['url' => ($this->dateLink)($bucket['year'], $bucket['month'], null), 'text' => ($this->date)('F Y', $bucket['first']), 'count' => $bucket['count']],
            };
        }
        return $out;
    }

    /**
     * @param array{year: int, week: int, first: string, count: int} $bucket
     * @return array{url: string, text: string, count: int}
     */
    private function weekPeriod(array $bucket, Closure $weekLink): array
    {
        $bounds = ($this->week)($bucket['first']);
        $text = ($this->date)('F j, Y', date('Y-m-d H:i:s', $bounds['start'])) . '&#8211;' . ($this->date)('F j, Y', date('Y-m-d H:i:s', $bounds['end']));
        return ['url' => $weekLink($bucket['year'], $bucket['week']), 'text' => $text, 'count' => $bucket['count']];
    }

    /**
     * The posts themselves, by date or by title.
     *
     * @param Closure(int, string): string $title the post's title as displayed (or its id when empty)
     * @param Closure(int): string $link
     * @return list<array{url: string, text: string, count: int}>
     */
    public function posts(string $type, string $orderBy, string $order, int $limit, Closure $title, Closure $link): array
    {
        $out = [];
        foreach ($this->posts->archiveList($type, $orderBy, $order, $limit) as $post) {
            $out[] = ['url' => $link($post->id), 'text' => $title($post->id, $post->title), 'count' => 0];
        }
        return $out;
    }
}
