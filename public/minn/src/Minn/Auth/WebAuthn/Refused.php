<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

use RuntimeException;

/**
 * A passkey ceremony that did not pass. The reason is a short stable word
 * naming the check (origin, challenge, signature, counter, ...), for logs and
 * suites; the message is plain text for a person. Callers show people one
 * message for every reason, so a refusal never coaches a forger.
 */
final class Refused extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
