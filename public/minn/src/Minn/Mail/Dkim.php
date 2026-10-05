<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * DKIM signatures (RFC 6376) as the reference's mailer writes them:
 * rsa-sha256, relaxed header and simple body canonicalization, the
 * standard headers signed in the order they appear (plus any extra ones
 * the caller names), an optional identity, the signed headers copied into
 * z= when asked, and the signature folded in 73-character pieces.
 */
final class Dkim
{
    private const SIGNED = ['from', 'to', 'cc', 'date', 'subject', 'reply-to', 'message-id', 'content-type', 'mime-version', 'x-mailer'];

    private bool $copyHeaders = false;

    /** @param string $key the private key (PEM text) */
    public function __construct(
        private readonly string $domain,
        private readonly string $selector,
        private readonly string $key,
        private readonly string $passphrase,
        private readonly string $identity,
    ) {
    }

    /** The same signer, copying the signed headers into z= as well. */
    public function copyingHeaders(): self
    {
        $copy = clone $this;
        $copy->copyHeaders = true;
        return $copy;
    }

    /** Text with everything outside the DKIM-safe printable set written as =XX. */
    public static function quotedPrintable(string $text): string
    {
        return (string) preg_replace_callback('/[^\x21-\x3A\x3C\x3E-\x7E]/', static fn (array $m): string => sprintf('=%02X', ord($m[0])), $text);
    }

    /** Relaxed header canonicalization: unfolded, names lower-cased, runs of space collapsed, values trimmed. */
    public static function headers(string $text): string
    {
        $lines = explode("\r\n", (string) preg_replace('/\r\n[ \t]+/', ' ', $text));
        foreach ($lines as $index => $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $lines[$index] = strtolower(trim($name)) . ':' . trim((string) preg_replace('/[ \t]+/', ' ', $value));
        }
        return implode("\r\n", $lines);
    }

    /** Simple body canonicalization: CRLF line endings and exactly one at the end. */
    public static function body(string $body): string
    {
        if ($body === '') {
            return "\r\n";
        }
        return rtrim(Transfer::normalizeBreaks($body, "\r\n"), "\r\n") . "\r\n";
    }

    /** The base64 RSA-SHA256 signature of the text, or null when the key cannot be read. */
    public function sign(string $text): ?string
    {
        $key = $this->passphrase !== '' ? openssl_pkey_get_private($this->key, $this->passphrase) : openssl_pkey_get_private($this->key);
        if ($key === false || !openssl_sign($text, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }
        return base64_encode($signature);
    }

    /**
     * The DKIM-Signature header for a message, signed at $time.
     *
     * @param list<string> $extra lower-case names of further headers to sign
     */
    public function signatureHeader(string $headerBlock, string $subject, string $body, int $time, array $extra, string $eol): ?string
    {
        if (stripos($headerBlock, 'Subject') === false) {
            $headerBlock .= 'Subject: ' . $subject . $eol;
        }
        [$names, $lines, $copied] = $this->signedHeaders($headerBlock, $extra);
        $head = 'DKIM-Signature: v=1; d=' . $this->domain . '; s=' . $this->selector . ';' . $eol
            . ' a=rsa-sha256; q=dns/txt; t=' . $time . '; c=relaxed/simple;' . $eol
            . ' h=' . implode(':', $names) . ';' . $eol
            . ($this->identity !== '' ? ' i=' . $this->identity . ';' . $eol : '')
            . ($this->copyHeaders ? ' z=' . implode($eol . ' |', $copied) . ';' . $eol : '')
            . ' bh=' . base64_encode(hash('sha256', self::body($body), true)) . ';' . $eol
            . ' b=';
        $signature = $this->sign(self::headers(Transfer::normalizeBreaks(implode($eol, $lines) . $eol . $head, "\r\n")));
        if ($signature === null) {
            return null;
        }
        return Transfer::normalizeBreaks($head . implode($eol . ' ', str_split($signature, 73)), $eol);
    }

    /**
     * @param list<string> $extra
     * @return array{0: list<string>, 1: list<string>, 2: list<string>} names, header lines and z= copies
     */
    private function signedHeaders(string $headerBlock, array $extra): array
    {
        $names = $lines = $copied = [];
        $unfolded = (string) preg_replace('/\r?\n[ \t]+/', ' ', $headerBlock);
        foreach (preg_split('/\r?\n/', $unfolded) ?: [] as $line) {
            if (!preg_match('/^([^:\s]+)\s*:\s*(.*)$/', $line, $m)) {
                continue;
            }
            $lower = strtolower($m[1]);
            if (in_array($lower, self::SIGNED, true) || in_array($lower, $extra, true)) {
                $names[] = $m[1];
                $lines[] = $line;
                $copied[] = $m[1] . ':' . self::quotedPrintable($m[2]);
            }
        }
        return [$names, $lines, $copied];
    }
}
