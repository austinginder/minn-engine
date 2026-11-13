<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/** A CBOR byte string, kept apart from a text string so a reader can insist on the one it expects. */
final readonly class CborBytes
{
    public function __construct(public string $value)
    {
    }
}
