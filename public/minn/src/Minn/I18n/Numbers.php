<?php

declare(strict_types=1);

namespace Minn\I18n;

use Minn\Runtime\Runtime;

/** Numbers written for people: grouped by thousands, to the decimals asked. */
final class Numbers
{
    /** A number grouped by thousands (number_format_i18n), as the filter leaves it. */
    public static function format(mixed $number, mixed $decimals = 0): mixed
    {
        return Runtime::hooks()->filter('number_format_i18n', [number_format((float) $number, abs((int) $decimals), '.', ','), $number, $decimals]);
    }
}
