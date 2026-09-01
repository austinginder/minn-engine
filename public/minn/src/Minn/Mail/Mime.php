<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * A full MIME message from recorded mailer state, for the PHPMailer facade:
 * address headers, custom headers, the single-part body, and a
 * multipart/mixed wrap with base64 attachments when files ride along.
 */
final readonly class Mime
{
    /**
     * A whole MIME message, headers and body, from its parts.
     *
     * @param list<array{0: string, 1: string}> $to [address, name]
     * @param list<array{0: string, 1: string}> $cc
     * @param list<array{0: string, 1: string}> $bcc
     * @param list<array{0: string, 1: string}> $replyTo
     * @param list<array{0: string, 1: string}> $customHeaders [name, value]
     * @param list<array{0: string, 1: string}> $attachments [path, name]
     */
    public static function compose(
        string $from,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        array $replyTo,
        string $subject,
        string $body,
        string $contentType,
        string $charset,
        array $customHeaders,
        array $attachments,
        string $sender = '',
    ): string {
        $headers = 'Date: ' . date(DATE_RFC2822) . "\r\n"
            . 'To: ' . self::addressList($to) . "\r\n"
            . 'From: ' . Mailer::address($from, $fromName) . "\r\n";
        foreach ([['Cc', $cc], ['Reply-To', $replyTo]] as [$name, $list]) {
            if ($list !== []) {
                $headers .= $name . ': ' . self::addressList($list) . "\r\n";
            }
        }
        if ($sender !== '') {
            $headers .= 'Sender: ' . $sender . "\r\n";
        }
        $headers .= 'Subject: ' . Mailer::encodeHeader($subject) . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (gethostname() ?: 'localhost') . ">\r\n"
            . "MIME-Version: 1.0\r\n";
        foreach ($customHeaders as [$name, $value]) {
            $headers .= trim($name) . ': ' . trim($value) . "\r\n";
        }
        [$typeHeaders, $payload] = self::payload($body, $contentType, $charset, $attachments);
        return $headers . $typeHeaders . "\r\n" . $payload;
    }

    /**
     * @param list<array{0: string, 1: string}> $attachments
     * @return array{0: string, 1: string} the content-type headers and the encoded payload
     */
    private static function payload(string $body, string $contentType, string $charset, array $attachments): array
    {
        $text = (string) preg_replace('/\r?\n/', "\r\n", $body);
        $part = 'Content-Type: ' . $contentType . '; charset=' . $charset . "\r\nContent-Transfer-Encoding: 8bit\r\n";
        if ($attachments === []) {
            return [$part, $text];
        }
        $boundary = 'minn-' . bin2hex(random_bytes(12));
        $payload = "--{$boundary}\r\n" . $part . "\r\n" . $text . "\r\n";
        foreach ($attachments as [$path, $name]) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }
            $filename = $name !== '' ? $name : basename($path);
            $payload .= "--{$boundary}\r\n"
                . 'Content-Type: application/octet-stream; name="' . $filename . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $filename . "\"\r\n\r\n"
                . chunk_split(base64_encode($contents)) . "\r\n";
        }
        $payload .= "--{$boundary}--\r\n";
        return ['Content-Type: multipart/mixed; boundary="' . $boundary . "\"\r\n", $payload];
    }

    /** @param list<array{0: string, 1: string}> $list */
    private static function addressList(array $list): string
    {
        return implode(', ', array_map(static fn (array $entry) => Mailer::address($entry[0], $entry[1]), $list));
    }
}
