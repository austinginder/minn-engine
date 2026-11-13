<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

use SodiumException;

/**
 * A passkey's public key, from the COSE map (RFC 9052/9053) an authenticator
 * hands over when the passkey is made. Three kinds are verified, the three
 * the creation options ask for: ES256 (an EC2 key on P-256), EdDSA (an OKP
 * Ed25519 key) and RS256 (an RSA key of at least 2048 bits). Anything else is
 * refused when the passkey is made, never at sign-in.
 */
final readonly class CoseKey
{
    public const ES256 = -7;
    public const EDDSA = -8;
    public const RS256 = -257;

    /** The algorithms the creation options offer, in the order offered. */
    public const OFFERED = [self::ES256, self::EDDSA, self::RS256];

    private function __construct(public int $algorithm, private string $material)
    {
    }

    /** The key a stored COSE encoding describes. */
    public static function fromBytes(string $cose): self
    {
        return self::fromMap(Cbor::decode($cose));
    }

    /** The key a decoded COSE map describes, refused when its type, curve, algorithm or size is not one Minn verifies. */
    public static function fromMap(mixed $map): self
    {
        if (!is_array($map)) {
            throw new Refused('key', 'The passkey sent no key.');
        }
        $key = match ([$map[1] ?? null, $map[3] ?? null]) {
            [2, self::ES256] => self::ec2($map),
            [1, self::EDDSA] => self::okp($map),
            [3, self::RS256] => self::rsa($map),
            default => throw new Refused('key', 'The passkey uses a kind of key this site does not accept.'),
        };
        return $key;
    }

    /** Whether the signature over the data was made by this key. */
    public function verifies(string $data, string $signature): bool
    {
        if ($this->algorithm === self::EDDSA) {
            try {
                return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES && sodium_crypto_sign_verify_detached($signature, $data, $this->material);
            } catch (SodiumException) {
                return false;
            }
        }
        return openssl_verify($data, $signature, $this->material, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @param array<int|string, mixed> $map */
    private static function ec2(array $map): self
    {
        $x = self::bytes($map, -2, 32);
        $y = self::bytes($map, -3, 32);
        if (($map[-1] ?? null) !== 1) {
            throw new Refused('key', 'The passkey key is not on the P-256 curve.');
        }
        $algorithm = Der::sequence(Der::oid('2a8648ce3d0201') . Der::oid('2a8648ce3d030107'));
        return self::checked(self::ES256, Der::pem(Der::sequence($algorithm . Der::bitString("\x04" . $x . $y))));
    }

    /** @param array<int|string, mixed> $map */
    private static function okp(array $map): self
    {
        if (($map[-1] ?? null) !== 6) {
            throw new Refused('key', 'The passkey key is not an Ed25519 key.');
        }
        return new self(self::EDDSA, self::bytes($map, -2, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES));
    }

    /** @param array<int|string, mixed> $map */
    private static function rsa(array $map): self
    {
        $modulus = ltrim(self::bytes($map, -1, null), "\x00");
        $exponent = ltrim(self::bytes($map, -2, null), "\x00");
        if (strlen($modulus) < 256 || strlen($modulus) > 1024 || $exponent === '' || strlen($exponent) > 8) {
            throw new Refused('key', 'The passkey RSA key is too small or malformed.');
        }
        $algorithm = Der::sequence(Der::oid('2a864886f70d010101') . "\x05\x00");
        $public = Der::sequence(Der::integer($modulus) . Der::integer($exponent));
        return self::checked(self::RS256, Der::pem(Der::sequence($algorithm . Der::bitString($public))));
    }

    /** A key OpenSSL agrees is a key (an EC point off the curve is refused here). */
    private static function checked(int $algorithm, string $pem): self
    {
        if (openssl_pkey_get_public($pem) === false) {
            while (openssl_error_string() !== false) {
            }
            throw new Refused('key', 'The passkey key is not a valid public key.');
        }
        return new self($algorithm, $pem);
    }

    /** @param array<int|string, mixed> $map */
    private static function bytes(array $map, int $label, ?int $length): string
    {
        $value = $map[$label] ?? null;
        if (!$value instanceof CborBytes || ($length !== null && strlen($value->value) !== $length)) {
            throw new Refused('key', 'The passkey key is malformed.');
        }
        return $value->value;
    }
}
