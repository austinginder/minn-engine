<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/**
 * Authenticator data, read: the hash of the relying party id it signed for,
 * its flags, its signature counter and, when a passkey is being made, the new
 * credential's id and COSE key. Extension output is read past and not kept.
 * Bytes left over, or a length that runs past the end, are refused.
 */
final readonly class AuthenticatorData
{
    private const USER_PRESENT = 0x01;
    private const USER_VERIFIED = 0x04;
    private const BACKUP_ELIGIBLE = 0x08;
    private const BACKED_UP = 0x10;
    private const CREDENTIAL = 0x40;
    private const EXTENSIONS = 0x80;
    private const MAX_CREDENTIAL_ID = 1023;

    public function __construct(
        public string $rpIdHash,
        public int $flags,
        public int $signCount,
        public string $credentialId = '',
        public string $publicKey = '',
    ) {
    }

    /** The authenticator data the bytes hold. */
    public static function parse(string $bytes): self
    {
        if (strlen($bytes) < 37) {
            throw new Refused('authenticator', 'The passkey sent too little data.');
        }
        $flags = ord($bytes[32]);
        $count = unpack('N', substr($bytes, 33, 4));
        $offset = 37;
        $credentialId = '';
        $publicKey = '';
        if (($flags & self::CREDENTIAL) !== 0) {
            if (strlen($bytes) < $offset + 18) {
                throw new Refused('authenticator', 'The passkey sent too little data.');
            }
            $length = (int) unpack('n', substr($bytes, $offset + 16, 2))[1];
            $offset += 18;
            if ($length === 0 || $length > self::MAX_CREDENTIAL_ID || strlen($bytes) < $offset + $length) {
                throw new Refused('authenticator', 'The passkey id is malformed.');
            }
            $credentialId = substr($bytes, $offset, $length);
            $offset += $length;
            [, $end] = Cbor::decodeAt($bytes, $offset);
            $publicKey = substr($bytes, $offset, $end - $offset);
            $offset = $end;
        }
        if (($flags & self::EXTENSIONS) !== 0) {
            [, $offset] = Cbor::decodeAt($bytes, $offset);
        }
        if ($offset !== strlen($bytes)) {
            throw new Refused('authenticator', 'The passkey sent bytes after its data.');
        }
        return new self(substr($bytes, 0, 32), $flags, (int) ($count === false ? 0 : $count[1]), $credentialId, $publicKey);
    }

    /** Whether a person touched or otherwise answered the authenticator. */
    public function userPresent(): bool
    {
        return ($this->flags & self::USER_PRESENT) !== 0;
    }

    /** Whether the authenticator checked who the person is (Face ID, a PIN, a fingerprint). */
    public function userVerified(): bool
    {
        return ($this->flags & self::USER_VERIFIED) !== 0;
    }

    /** Whether the passkey may be synced to other devices (iCloud Keychain, a password manager). */
    public function backupEligible(): bool
    {
        return ($this->flags & self::BACKUP_ELIGIBLE) !== 0;
    }

    /** Whether the passkey is synced to other devices now. */
    public function backedUp(): bool
    {
        return ($this->flags & self::BACKED_UP) !== 0;
    }
}
