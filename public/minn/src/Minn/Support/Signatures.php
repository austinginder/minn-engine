<?php

declare(strict_types=1);

namespace Minn\Support;

/**
 * A file checked against detached Ed25519 signatures of its SHA-384 hash,
 * as verify_file_signature checks it: a signature that does not decode to
 * the signature length is skipped, and for each one that does, every key
 * that does not decode to the key length is skipped too; the counts and the
 * hash are what the caller reports when nothing verifies.
 */
final class Signatures
{
    /**
     * Whether any signature verifies with any key, the file's hash in hex,
     * and how many keys and signatures were skipped.
     *
     * @param list<mixed> $signatures
     * @param list<mixed> $keys base64 public keys
     * @return array{verified: bool, hash: string, skipped_key: int, skipped_sig: int}
     */
    public static function check(string $file, array $signatures, array $keys): array
    {
        $hash = (string) hash_file('sha384', $file, true);
        $result = ['verified' => false, 'hash' => bin2hex($hash), 'skipped_key' => 0, 'skipped_sig' => 0];
        foreach ($signatures as $signature) {
            $raw = base64_decode((string) $signature, true);
            if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_BYTES) {
                $result['skipped_sig']++;
                continue;
            }
            foreach ($keys as $key) {
                $key = base64_decode((string) $key, true);
                if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    $result['skipped_key']++;
                    continue;
                }
                if (sodium_crypto_sign_verify_detached($raw, $hash, $key)) {
                    return ['verified' => true] + $result;
                }
            }
        }
        return $result;
    }
}
