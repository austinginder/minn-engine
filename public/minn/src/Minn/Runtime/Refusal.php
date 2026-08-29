<?php

declare(strict_types=1);

namespace Minn\Runtime;

/** A refused operation, the way plugin code expects to read it: a code, a message, optional data. The facade turns it into WP_Error. */
final readonly class Refusal
{
    public function __construct(public string $code, public string $message, public mixed $data = null)
    {
    }
}
