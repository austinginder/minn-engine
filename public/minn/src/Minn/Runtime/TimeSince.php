<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Support\Time;

/**
 * How long ago something was, as human_time_diff words it: the span
 * between two times in its largest whole unit ("3 days"), through
 * human_time_diff.
 */
final class TimeSince
{
    /** The span from one time to another (now when none is given). */
    public static function between(int $from, int $to = 0): string
    {
        $to = $to === 0 ? time() : $to;
        $diff = abs($to - $from);
        [$count, $unit] = Time::span($diff);
        $since = sprintf(Gettext::plural("%s {$unit}", "%s {$unit}s", $count), $count);
        return (string) Runtime::hooks()->filter('human_time_diff', [$since, $diff, $from, $to]);
    }
}
