<?php

declare(strict_types=1);

namespace Minn\Http;

use RuntimeException;

/** Thrown by Exchange::throw() when no response arrived or it was not a 2xx; the exchange rides along. */
final class RequestFailed extends RuntimeException
{
    public function __construct(public readonly Exchange $reply, string $message)
    {
        parent::__construct($message, $reply->code);
    }
}
