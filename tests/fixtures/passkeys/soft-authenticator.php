<?php
/**
 * A software authenticator for the passkey suites: it makes a key, answers
 * the create and get ceremonies with the bytes a browser would hand a site,
 * and lets a case change any one part (the origin, the flags, the counter,
 * the signature) to prove the engine refuses it. Built from the WebAuthn and
 * CBOR specifications, never from another implementation.
 */

declare(strict_types=1);

/** A CBOR byte string, for the test encoder. */
final class MinnTestCborBytes
{
    public function __construct(public string $value)
    {
    }
}

/** A CBOR map even when its keys would read as a list (an empty attStmt). */
final class MinnTestCborMap
{
    /** @param array<int|string, mixed> $entries */
    public function __construct(public array $entries)
    {
    }
}

final class MinnTestCbor
{
    public static function encode(mixed $value): string
    {
        return match (true) {
            $value instanceof MinnTestCborBytes => self::head(2, strlen($value->value)) . $value->value,
            $value instanceof MinnTestCborMap => self::map($value->entries),
            is_int($value) => $value >= 0 ? self::head(0, $value) : self::head(1, -1 - $value),
            is_string($value) => self::head(3, strlen($value)) . $value,
            $value === false => "\xf4",
            $value === true => "\xf5",
            $value === null => "\xf6",
            is_array($value) && array_is_list($value) => self::head(4, count($value)) . implode('', array_map(self::encode(...), $value)),
            is_array($value) => self::map($value),
        };
    }

    public static function head(int $major, int $n): string
    {
        $type = $major << 5;
        return match (true) {
            $n < 24 => chr($type | $n),
            $n < 0x100 => chr($type | 24) . chr($n),
            $n < 0x10000 => chr($type | 25) . pack('n', $n),
            $n < 0x100000000 => chr($type | 26) . pack('N', $n),
            default => chr($type | 27) . pack('J', $n),
        };
    }

    /** @param array<int|string, mixed> $entries */
    private static function map(array $entries): string
    {
        $out = self::head(5, count($entries));
        foreach ($entries as $key => $item) {
            $out .= self::encode($key) . self::encode($item);
        }
        return $out;
    }
}

final class MinnTestAuthenticator
{
    public string $credentialId;
    public string $cose;
    public int $count = 0;
    private mixed $secret;

    public function __construct(public string $kind = 'es256')
    {
        $this->credentialId = random_bytes(32);
        if ($kind === 'eddsa') {
            $pair = sodium_crypto_sign_keypair();
            $this->secret = sodium_crypto_sign_secretkey($pair);
            $this->cose = MinnTestCbor::encode(new MinnTestCborMap([1 => 1, 3 => -8, -1 => 6, -2 => new MinnTestCborBytes(sodium_crypto_sign_publickey($pair))]));
            return;
        }
        if ($kind === 'rs256') {
            $this->secret = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
            $rsa = openssl_pkey_get_details($this->secret)['rsa'];
            $this->cose = MinnTestCbor::encode(new MinnTestCborMap([1 => 3, 3 => -257, -1 => new MinnTestCborBytes($rsa['n']), -2 => new MinnTestCborBytes($rsa['e'])]));
            return;
        }
        $this->secret = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $ec = openssl_pkey_get_details($this->secret)['ec'];
        $this->cose = MinnTestCbor::encode(new MinnTestCborMap([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => new MinnTestCborBytes(str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT)),
            -3 => new MinnTestCborBytes(str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT)),
        ]));
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * The registration response's two byte strings.
     *
     * @param array<string, mixed> $o type, challenge, origin, crossOrigin, rpId, flags, fmt, attStmt, cose, trailing
     * @return array{clientDataJSON: string, attestationObject: string}
     */
    public function create(string $rpId, string $origin, string $challenge, array $o = []): array
    {
        $client = $this->client($o['type'] ?? 'webauthn.create', $o['challenge'] ?? $challenge, $o['origin'] ?? $origin, $o);
        $flags = $o['flags'] ?? (0x01 | 0x04 | 0x40);
        $data = hash('sha256', $o['rpId'] ?? $rpId, true) . chr($flags) . pack('N', $this->count)
            . str_repeat("\x00", 16) . pack('n', strlen($this->credentialId)) . $this->credentialId . ($o['cose'] ?? $this->cose) . ($o['trailing'] ?? '');
        $attestation = MinnTestCbor::encode(new MinnTestCborMap([
            'fmt' => $o['fmt'] ?? 'none',
            'attStmt' => new MinnTestCborMap($o['attStmt'] ?? []),
            'authData' => new MinnTestCborBytes($data),
        ]));
        return ['clientDataJSON' => $client, 'attestationObject' => $attestation];
    }

    /**
     * A sign-in response's three byte strings; the counter moves first unless the case fixes it.
     *
     * @param array<string, mixed> $o type, challenge, origin, crossOrigin, rpId, flags, count, corrupt
     * @return array{clientDataJSON: string, authenticatorData: string, signature: string}
     */
    public function get(string $rpId, string $origin, string $challenge, array $o = []): array
    {
        $this->count = $o['count'] ?? ($this->count === 0 && ($o['keepsNoCount'] ?? false) ? 0 : $this->count + 1);
        $client = $this->client($o['type'] ?? 'webauthn.get', $o['challenge'] ?? $challenge, $o['origin'] ?? $origin, $o);
        $data = hash('sha256', $o['rpId'] ?? $rpId, true) . chr($o['flags'] ?? (0x01 | 0x04)) . pack('N', $this->count);
        $signature = $this->sign($data . hash('sha256', $client, true));
        if ($o['corrupt'] ?? false) {
            $signature[10] = chr(ord($signature[10]) ^ 0x01);
        }
        return ['clientDataJSON' => $client, 'authenticatorData' => $data, 'signature' => $signature];
    }

    private function client(string $type, string $challenge, string $origin, array $o): string
    {
        return (string) json_encode(['type' => $type, 'challenge' => self::b64($challenge), 'origin' => $origin, 'crossOrigin' => $o['crossOrigin'] ?? false], JSON_UNESCAPED_SLASHES);
    }

    private function sign(string $data): string
    {
        if ($this->kind === 'eddsa') {
            return sodium_crypto_sign_detached($data, $this->secret);
        }
        openssl_sign($data, $signature, $this->secret, OPENSSL_ALGO_SHA256);
        return $signature;
    }
}
