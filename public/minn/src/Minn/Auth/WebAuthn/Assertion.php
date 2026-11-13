<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/** A sign-in a stored passkey proved: the counter to keep and whether the passkey is synced now. */
final readonly class Assertion
{
    public function __construct(public int $signCount, public bool $backedUp)
    {
    }
}
