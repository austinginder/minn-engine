<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/** A passkey a registration ceremony proved: its id, its COSE key as sent, and what the authenticator said about it. */
final readonly class NewPasskey
{
    public function __construct(
        public string $credentialId,
        public string $publicKey,
        public int $algorithm,
        public int $signCount,
        public bool $backupEligible,
        public bool $backedUp,
    ) {
    }
}
