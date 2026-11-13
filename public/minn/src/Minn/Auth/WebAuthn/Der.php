<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/** The few DER shapes (ITU-T X.690) a public key needs to be handed to OpenSSL as a PEM SubjectPublicKeyInfo. */
final class Der
{
    /** A SEQUENCE of already encoded parts. */
    public static function sequence(string $content): string
    {
        return self::element(0x30, $content);
    }

    /** An OBJECT IDENTIFIER from its encoded body, written as hex. */
    public static function oid(string $hex): string
    {
        return self::element(0x06, (string) hex2bin($hex));
    }

    /** A non-negative INTEGER from big-endian bytes, a zero byte added when the top bit would read as a sign. */
    public static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return self::element(0x02, $bytes);
    }

    /** A BIT STRING with no unused bits. */
    public static function bitString(string $bytes): string
    {
        return self::element(0x03, "\x00" . $bytes);
    }

    /** A SubjectPublicKeyInfo as PEM text. */
    public static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function element(int $tag, string $content): string
    {
        $length = strlen($content);
        if ($length < 0x80) {
            return chr($tag) . chr($length) . $content;
        }
        $digits = ltrim(pack('N', $length), "\x00");
        return chr($tag) . chr(0x80 | strlen($digits)) . $digits . $content;
    }
}
