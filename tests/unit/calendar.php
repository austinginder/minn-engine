<?php

declare(strict_types=1);

use Minn\Front\Calendar;
use Minn\Front\CalendarLabels;

/** The calendar, byte for byte as the reference drew these months (captured 2026-09-06, Monday first). */
$link = static fn (int $y, int $m, ?int $d): string => sprintf('https://minn.localhost/%04d/%02d/', $y, $m) . ($d === null ? '' : sprintf('%02d/', $d));
$monday = static fn (int $year, int $month, CalendarLabels $labels): Calendar => new Calendar($year, $month, 1, $labels, $link);
$expected = json_decode('{"september": "<table id=\\"wp-calendar\\" class=\\"wp-calendar-table\\">\\n\\t<caption>September 2026</caption>\\n\\t<thead>\\n\\t<tr>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Monday\\">M</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Tuesday\\">T</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Wednesday\\">W</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Thursday\\">T</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Friday\\">F</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Saturday\\">S</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Sunday\\">S</th>\\n\\t</tr>\\n\\t</thead>\\n\\t<tbody>\\n\\t<tr>\\n\\t\\t<td colspan=\\"1\\" class=\\"pad\\">&nbsp;</td><td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td id=\\"today\\">6</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>7</td><td>8</td><td>9</td><td>10</td><td>11</td><td>12</td><td>13</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>14</td><td>15</td><td>16</td><td>17</td><td>18</td><td>19</td><td>20</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>21</td><td>22</td><td>23</td><td>24</td><td>25</td><td>26</td><td>27</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>28</td><td>29</td><td>30</td>\\n\\t\\t<td class=\\"pad\\" colspan=\\"4\\">&nbsp;</td>\\n\\t</tr>\\n\\t</tbody>\\n\\t</table><nav aria-label=\\"Previous and next months\\" class=\\"wp-calendar-nav\\">\\n\\t\\t<span class=\\"wp-calendar-nav-prev\\"><a href=\\"https://minn.localhost/2026/08/\\">&laquo; Aug</a></span>\\n\\t\\t<span class=\\"pad\\">&nbsp;</span>\\n\\t\\t<span class=\\"wp-calendar-nav-next\\">&nbsp;</span>\\n\\t</nav>", "august": "<table id=\\"wp-calendar\\" class=\\"wp-calendar-table\\">\\n\\t<caption>August 2026</caption>\\n\\t<thead>\\n\\t<tr>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Monday\\">M</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Tuesday\\">T</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Wednesday\\">W</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Thursday\\">T</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Friday\\">F</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Saturday\\">S</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Sunday\\">S</th>\\n\\t</tr>\\n\\t</thead>\\n\\t<tbody>\\n\\t<tr>\\n\\t\\t<td colspan=\\"5\\" class=\\"pad\\">&nbsp;</td><td>1</td><td>2</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td><td>9</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>10</td><td>11</td><td>12</td><td>13</td><td>14</td><td>15</td><td>16</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>17</td><td>18</td><td>19</td><td>20</td><td>21</td><td>22</td><td>23</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>24</td><td>25</td><td>26</td><td>27</td><td><a href=\\"https://minn.localhost/2026/08/28/\\" aria-label=\\"Posts published on August 28, 2026\\">28</a></td><td>29</td><td>30</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>31</td>\\n\\t\\t<td class=\\"pad\\" colspan=\\"6\\">&nbsp;</td>\\n\\t</tr>\\n\\t</tbody>\\n\\t</table><nav aria-label=\\"Previous and next months\\" class=\\"wp-calendar-nav\\">\\n\\t\\t<span class=\\"wp-calendar-nav-prev\\">&nbsp;</span>\\n\\t\\t<span class=\\"pad\\">&nbsp;</span>\\n\\t\\t<span class=\\"wp-calendar-nav-next\\">&nbsp;</span>\\n\\t</nav>", "augustAbbreviated": "<table id=\\"wp-calendar\\" class=\\"wp-calendar-table\\">\\n\\t<caption>August 2026</caption>\\n\\t<thead>\\n\\t<tr>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Monday\\">Mon</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Tuesday\\">Tue</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Wednesday\\">Wed</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Thursday\\">Thu</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Friday\\">Fri</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Saturday\\">Sat</th>\\n\\t\\t<th scope=\\"col\\" aria-label=\\"Sunday\\">Sun</th>\\n\\t</tr>\\n\\t</thead>\\n\\t<tbody>\\n\\t<tr>\\n\\t\\t<td colspan=\\"5\\" class=\\"pad\\">&nbsp;</td><td>1</td><td>2</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td><td>9</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>10</td><td>11</td><td>12</td><td>13</td><td>14</td><td>15</td><td>16</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>17</td><td>18</td><td>19</td><td>20</td><td>21</td><td>22</td><td>23</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>24</td><td>25</td><td>26</td><td>27</td><td><a href=\\"https://minn.localhost/2026/08/28/\\" aria-label=\\"Posts published on August 28, 2026\\">28</a></td><td>29</td><td>30</td>\\n\\t</tr>\\n\\t<tr>\\n\\t\\t<td>31</td>\\n\\t\\t<td class=\\"pad\\" colspan=\\"6\\">&nbsp;</td>\\n\\t</tr>\\n\\t</tbody>\\n\\t</table><nav aria-label=\\"Previous and next months\\" class=\\"wp-calendar-nav\\">\\n\\t\\t<span class=\\"wp-calendar-nav-prev\\">&nbsp;</span>\\n\\t\\t<span class=\\"pad\\">&nbsp;</span>\\n\\t\\t<span class=\\"wp-calendar-nav-next\\">&nbsp;</span>\\n\\t</nav>", "julyNav": "<nav aria-label=\\"Previous and next months\\" class=\\"wp-calendar-nav\\">\\n\\t\\t<span class=\\"wp-calendar-nav-prev\\">&nbsp;</span>\\n\\t\\t<span class=\\"pad\\">&nbsp;</span>\\n\\t\\t<span class=\\"wp-calendar-nav-next\\"><a href=\\"https://minn.localhost/2026/08/\\">Aug &raquo;</a></span>\\n\\t</nav>"}', true);
return [
    'a month with today, no posts, and an earlier month to link' => static function () use ($monday, $expected) {
        $out = $monday(2026, 9, CalendarLabels::english())->render([], [2026, 8], null, [2026, 9, 6]);
        return $out === $expected['september'] ?: $out;
    },
    'a month with a post day links it, with no months on either side' => static function () use ($monday, $expected) {
        $out = $monday(2026, 8, CalendarLabels::english())->render([28], null, null, [2026, 9, 6]);
        return $out === $expected['august'] ?: $out;
    },
    'abbreviated weekdays head the columns when asked' => static function () use ($monday, $expected) {
        $out = $monday(2026, 8, CalendarLabels::englishAbbreviated())->render([28], null, null, [2026, 9, 6]);
        return $out === $expected['augustAbbreviated'] ?: $out;
    },
    'a later month with posts links forward' => static function () use ($monday, $expected) {
        $out = $monday(2026, 7, CalendarLabels::english())->render([], null, [2026, 8], [2026, 9, 6]);
        return substr($out, strpos($out, '<nav')) === $expected['julyNav'] ?: $out;
    },
    'a Sunday-first week moves the padding' => static function () use ($link) {
        $out = (new Calendar(2026, 8, 0, CalendarLabels::english(), $link))->render([], null, null, null);
        return str_contains($out, '<th scope="col" aria-label="Sunday">S</th>' . "\n\t\t" . '<th scope="col" aria-label="Monday">M</th>')
            && str_contains($out, "<tr>\n\t\t" . '<td colspan="6" class="pad">&nbsp;</td><td>1</td>')
            && str_contains($out, '<td>30</td><td>31</td>' . "\n\t\t" . '<td class="pad" colspan="5">&nbsp;</td>') ?: $out;
    },
    'a month that fills its last week has no trailing pad' => static function () use ($link) {
        $out = (new Calendar(2026, 2, 0, CalendarLabels::english(), $link))->render([], null, null, null);
        return str_contains($out, '<td>28</td>' . "\n\t</tr>\n\t</tbody>") && !str_contains($out, 'colspan="7"') ?: $out;
    },
    'today with posts links inside the today cell' => static function () use ($link) {
        $out = (new Calendar(2026, 8, 1, CalendarLabels::english(), $link))->render([28], null, null, [2026, 8, 28]);
        return str_contains($out, '<td id="today"><a href="https://minn.localhost/2026/08/28/" aria-label="Posts published on August 28, 2026">28</a></td>') ?: $out;
    },
];
