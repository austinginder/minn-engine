<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use DateTimeImmutable;

/**
 * One month as the calendar widget and block draw it: a table whose caption
 * names the month, whose head lists the week from the site's first day, and
 * whose cells link the days a post was published on to that day's archive;
 * today's cell carries the id the stylesheet lights; the nav below links
 * the nearest months with posts on either side. Markup, whitespace and the
 * attribute order of the two padding cells follow the reference byte for
 * byte, since themes style the table by those hooks.
 */
final readonly class Calendar
{
    private const FIXED_DATE = 'F j, Y';

    /** @param Closure(int, int, ?int): string $link the month archive URL, or the day's when a day is given */
    public function __construct(
        private int $year,
        private int $month,
        private int $weekStart,
        private CalendarLabels $labels,
        private Closure $link,
    ) {
    }

    /**
     * The table and its navigation.
     *
     * @param list<int> $postDays days of the month with a published post
     * @param array{int, int}|null $previous the nearest earlier month with posts, as [year, month]
     * @param array{int, int}|null $next the nearest later month with posts
     * @param array{int, int, int}|null $today the site's current date, as [year, month, day]
     */
    public function render(array $postDays, ?array $previous, ?array $next, ?array $today): string
    {
        return $this->table($postDays, $today) . $this->navigation($previous, $next);
    }

    /** @param list<int> $postDays */
    private function table(array $postDays, ?array $today): string
    {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $this->year, $this->month));
        $days = (int) $first->format('t');
        $pad = ((int) $first->format('w') - $this->weekStart + 7) % 7;
        $out = '<table id="wp-calendar" class="wp-calendar-table">'
            . "\n\t<caption>" . $this->labels->months[$this->month] . ' ' . $this->year . '</caption>'
            . "\n\t<thead>\n\t<tr>" . $this->head() . "\n\t</tr>\n\t</thead>\n\t<tbody>\n\t<tr>\n\t\t";
        if ($pad > 0) {
            $out .= '<td colspan="' . $pad . '" class="pad">&nbsp;</td>';
        }
        $column = $pad;
        for ($day = 1; $day <= $days; $day++) {
            if ($column === 7) {
                $out .= "\n\t</tr>\n\t<tr>\n\t\t";
                $column = 0;
            }
            $out .= $this->cell($day, $postDays, $today);
            $column++;
        }
        if ($column < 7) {
            $out .= "\n\t\t" . '<td class="pad" colspan="' . (7 - $column) . '">&nbsp;</td>';
        }
        return $out . "\n\t</tr>\n\t</tbody>\n\t</table>";
    }

    private function head(): string
    {
        $out = '';
        for ($offset = 0; $offset < 7; $offset++) {
            $name = $this->labels->weekdays[($this->weekStart + $offset) % 7];
            $out .= "\n\t\t" . '<th scope="col" aria-label="' . $name . '">' . $this->labels->weekdayShort[$name] . '</th>';
        }
        return $out;
    }

    /**
     * @param list<int> $postDays
     * @param array{int, int, int}|null $today
     */
    private function cell(int $day, array $postDays, ?array $today): string
    {
        $inner = (string) $day;
        if (in_array($day, $postDays, true)) {
            $label = 'Posts published on ' . $this->labels->months[$this->month] . ' ' . $day . ', ' . $this->year;
            $inner = '<a href="' . ($this->link)($this->year, $this->month, $day) . '" aria-label="' . $label . '">' . $day . '</a>';
        }
        $open = $today === [$this->year, $this->month, $day] ? '<td id="today">' : '<td>';
        return $open . $inner . '</td>';
    }

    /**
     * @param array{int, int}|null $previous
     * @param array{int, int}|null $next
     */
    private function navigation(?array $previous, ?array $next): string
    {
        $before = $previous === null ? '&nbsp;' : '<a href="' . ($this->link)($previous[0], $previous[1], null) . '">&laquo; ' . $this->abbreviation($previous[1]) . '</a>';
        $after = $next === null ? '&nbsp;' : '<a href="' . ($this->link)($next[0], $next[1], null) . '">' . $this->abbreviation($next[1]) . ' &raquo;</a>';
        return '<nav aria-label="Previous and next months" class="wp-calendar-nav">'
            . "\n\t\t" . '<span class="wp-calendar-nav-prev">' . $before . '</span>'
            . "\n\t\t" . '<span class="pad">&nbsp;</span>'
            . "\n\t\t" . '<span class="wp-calendar-nav-next">' . $after . '</span>'
            . "\n\t</nav>";
    }

    private function abbreviation(int $month): string
    {
        return $this->labels->monthAbbrev[$this->labels->months[$month]];
    }
}
