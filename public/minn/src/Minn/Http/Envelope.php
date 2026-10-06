<?php

declare(strict_types=1);

namespace Minn\Http;

use Closure;
use Minn\RestError;

/**
 * What runs around a matched route once the router knows the route takes
 * the request: the place a host lets other code refuse, answer or edit it
 * (the runtime's REST server filters). The router hands over the argument
 * check's refusal and the policy's, unthrown, because that code may clear
 * the one and must never run the handler past the other. Without an
 * envelope the router throws them itself.
 */
interface Envelope
{
    /**
     * The answer to a matched request, refusals included.
     *
     * @param Closure(): Response $invoke the route's handler; it may throw RestError
     */
    public function around(Matched $matched, Request $request, ?RestError $invalid, ?RestError $refusal, Closure $invoke): Response;
}
