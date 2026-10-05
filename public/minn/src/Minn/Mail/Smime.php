<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * S/MIME signing of a composed MIME entity with a certificate and key on
 * disk: the entity goes through OpenSSL as a detached signature and comes
 * back as new MIME headers (multipart/signed) and the signed body.
 */
final class Smime
{
    /**
     * The signed entity's MIME headers and body, or null when signing fails.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function sign(string $entity, string $cert, string $key, string $passphrase, string $extraCerts): ?array
    {
        $in = (string) tempnam(sys_get_temp_dir(), 'minn-smime');
        $out = (string) tempnam(sys_get_temp_dir(), 'minn-smime');
        file_put_contents($in, $entity);
        $signer = 'file://' . (string) realpath($cert);
        $private = ['file://' . (string) realpath($key), $passphrase];
        $ok = $extraCerts === ''
            ? @openssl_pkcs7_sign($in, $out, $signer, $private, [])
            : @openssl_pkcs7_sign($in, $out, $signer, $private, [], PKCS7_DETACHED, (string) realpath($extraCerts));
        $signed = $ok ? (string) file_get_contents($out) : '';
        @unlink($in);
        @unlink($out);
        if (!$ok) {
            return null;
        }
        $parts = explode("\n\n", str_replace("\r\n", "\n", $signed), 2);
        return [Transfer::normalizeBreaks($parts[0]) . "\r\n", Transfer::normalizeBreaks($parts[1] ?? '')];
    }
}
