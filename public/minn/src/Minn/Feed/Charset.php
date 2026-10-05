<?php

declare(strict_types=1);

namespace Minn\Feed;

/**
 * A feed document as UTF-8. The encoding comes from a byte-order mark,
 * then the XML declaration, then the Content-Type charset, then UTF-8; a
 * document in another encoding is converted and its declaration rewritten,
 * so the parser only ever reads UTF-8.
 */
final class Charset
{
    /** The document converted to UTF-8, with no byte-order mark. */
    public static function toUtf8(string $body, string $contentType = ''): string
    {
        foreach (["\xEF\xBB\xBF" => 'UTF-8', "\xFE\xFF" => 'UTF-16BE', "\xFF\xFE" => 'UTF-16LE'] as $mark => $encoding) {
            if (str_starts_with($body, $mark)) {
                return self::convert(substr($body, strlen($mark)), $encoding);
            }
        }
        return self::convert($body, self::declared($body) ?? self::fromContentType($contentType) ?? 'UTF-8');
    }

    /** The encoding the XML declaration names, if it names one. */
    public static function declared(string $body): ?string
    {
        if (preg_match('/^\s*<\?xml[^>]*\bencoding\s*=\s*["\']([A-Za-z0-9._:-]+)["\']/', $body, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function fromContentType(string $contentType): ?string
    {
        return preg_match('/charset\s*=\s*["\']?([A-Za-z0-9._:-]+)/i', $contentType, $m) ? $m[1] : null;
    }

    private static function convert(string $body, string $encoding): string
    {
        $normal = strtoupper(str_replace('_', '-', $encoding));
        if (in_array($normal, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            return $body;
        }
        $from = match ($normal) {
            'ISO-8859-1', 'LATIN1', 'LATIN-1', 'WINDOWS-1252', 'CP1252' => 'Windows-1252',
            default => $encoding,
        };
        try {
            $converted = mb_convert_encoding($body, 'UTF-8', $from);
        } catch (\ValueError) {
            return $body;
        }
        return (string) preg_replace('/^(\s*<\?xml[^>]*\bencoding\s*=\s*)(["\'])[^"\']*\2/', '$1$2UTF-8$2', $converted, 1);
    }
}
