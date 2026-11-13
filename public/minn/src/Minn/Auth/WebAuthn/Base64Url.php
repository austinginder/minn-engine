<?php

declare(strict_types=1);

namespace Minn\Auth\WebAuthn;

/** Base64 in the URL alphabet with no padding, the form every WebAuthn value travels in as JSON. */
final class Base64Url
{
    /** The text form of some bytes. */
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** The bytes a text form holds, or null when it is not one (padding, the other alphabet and stray characters are refused). */
    public static function decode(string $text): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $text) !== 1 || strlen($text) % 4 === 1) {
            return null;
        }
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        return $bytes === false ? null : $bytes;
    }
}
