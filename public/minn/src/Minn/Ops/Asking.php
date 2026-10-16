<?php

declare(strict_types=1);

namespace Minn\Ops;

/**
 * Why the engine asks the update service: the daily pass, once every 24
 * hours (marked X-Minn-Check: daily, which the service counts as one site
 * running that day), or anything else (a person checking, an update needing
 * a fresh answer), which goes unmarked.
 */
enum Asking
{
    case Daily;
    case Now;

    /** The request headers, with the daily mark when this is the daily pass. @param list<string> $headers @return list<string> */
    public function headers(array $headers): array
    {
        return $this === self::Daily ? [...$headers, 'X-Minn-Check: daily'] : $headers;
    }
}
