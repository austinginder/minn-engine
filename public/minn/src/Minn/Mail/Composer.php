<?php

declare(strict_types=1);

namespace Minn\Mail;

use RuntimeException;

/**
 * A message as the reference's mailer writes it: the header block (Date,
 * the address lines, Subject, Message-ID, X-Mailer, custom headers,
 * MIME-Version), the MIME header for its type, and the body, multipart
 * when it has an alternative, inline parts or attachments. Boundaries are
 * "b1=_{id}" to "b3=_{id}"; a 7-bit-clean body declared 8bit goes out as
 * 7bit us-ascii, and a body with a line past 998 characters as
 * quoted-printable.
 */
final class Composer
{
    private const ICAL_METHODS = ['REQUEST', 'PUBLISH', 'REPLY', 'ADD', 'CANCEL', 'REFRESH', 'COUNTER', 'DECLINECOUNTER'];

    /**
     * The body and the encoding a single-part message declares.
     *
     * @return array{body: string, encoding: string}
     * @throws RuntimeException when an attached file cannot be read (the message is the path)
     */
    public static function body(Draft $d, string $id): array
    {
        [$bodyEncoding, $bodyCharset] = self::encodingFor($d, $d->body);
        [$altEncoding, $altCharset] = self::encodingFor($d, $d->altBody);
        $type = $d->messageType();
        $eol = $d->eol;
        $part = static fn (string $boundary, string $charset, string $contentType, string $encoding, string $text): string => self::partHead($d, $boundary, $charset, $contentType, $encoding) . Transfer::encode($text, $encoding, $eol) . $eol;
        $main = static fn (string $boundary, string $contentType = ''): string => $part($boundary, $bodyCharset, $contentType, $bodyEncoding, $d->body);
        $alt = static fn (string $boundary): string => $part($boundary, $altCharset, 'text/plain', $altEncoding, $d->altBody);
        $body = match ($type) {
            'inline' => $main("b1=_{$id}") . self::attachAll($d, 'inline', "b1=_{$id}"),
            'attach' => $main("b1=_{$id}") . self::attachAll($d, 'attachment', "b1=_{$id}"),
            'inline_attach' => self::related($d, "b1=_{$id}", "b2=_{$id}") . $main("b2=_{$id}") . self::attachAll($d, 'inline', "b2=_{$id}") . $eol . self::attachAll($d, 'attachment', "b1=_{$id}"),
            'alt' => $alt("b1=_{$id}") . $main("b1=_{$id}", 'text/html') . self::calendar($d, "b1=_{$id}", $eol) . self::end($d, "b1=_{$id}"),
            'alt_inline' => $alt("b1=_{$id}") . self::related($d, "b1=_{$id}", "b2=_{$id}") . $main("b2=_{$id}", 'text/html') . self::attachAll($d, 'inline', "b2=_{$id}") . $eol . self::end($d, "b1=_{$id}"),
            'alt_attach' => self::alternative($d, "b1=_{$id}", "b2=_{$id}") . $alt("b2=_{$id}") . $main("b2=_{$id}", 'text/html') . self::calendar($d, "b2=_{$id}", '') . self::end($d, "b2=_{$id}") . $eol . self::attachAll($d, 'attachment', "b1=_{$id}"),
            'alt_inline_attach' => self::alternative($d, "b1=_{$id}", "b2=_{$id}") . $alt("b2=_{$id}") . self::related($d, "b2=_{$id}", "b3=_{$id}") . $main("b3=_{$id}", 'text/html') . self::attachAll($d, 'inline', "b3=_{$id}") . $eol . self::end($d, "b2=_{$id}") . $eol . self::attachAll($d, 'attachment', "b1=_{$id}"),
            default => Transfer::encode($d->body, $bodyEncoding, $eol),
        };
        return ['body' => $body, 'encoding' => $bodyEncoding];
    }

    /** The header block, MIME-Version and the MIME header included. */
    public static function headers(Draft $d, string $id, string $hostname, string $encoding): string
    {
        $lines = ['Date: ' . self::date($d->messageDate)];
        if ($d->mailer !== 'mail' && $d->toHeader === 'list') {
            $lines[] = match (true) {
                $d->to !== [] => self::addressLine($d, 'To', $d->to),
                $d->cc === [] => 'To: undisclosed-recipients:;',
                default => '',
            };
        }
        $lines[] = self::addressLine($d, 'From', [[trim($d->from), $d->fromName]]);
        foreach (['Cc' => $d->cc, 'Bcc' => in_array($d->mailer, ['mail', 'sendmail', 'qmail'], true) ? $d->bcc : [], 'Reply-To' => $d->replyTo] as $name => $list) {
            if ($list !== []) {
                $lines[] = self::addressLine($d, $name, $list);
            }
        }
        if ($d->mailer !== 'mail') {
            $lines[] = 'Subject: ' . $d->encodeHeader(self::secure(trim($d->subject)));
        }
        $lines[] = 'Message-ID: ' . self::messageId($d->messageId, $id, $hostname);
        foreach (self::extraLines($d) as $line) {
            $lines[] = $line;
        }
        $lines = array_filter($lines, static fn ($line) => $line !== '');
        return implode($d->eol, $lines) . $d->eol . 'MIME-Version: 1.0' . $d->eol . self::mimeHeaders($d, $id, $encoding);
    }

    /** The MIME header for the message's type: the content type (with its boundary) and the transfer encoding. */
    public static function mimeHeaders(Draft $d, string $id, string $encoding): string
    {
        $eol = $d->eol;
        $multipart = match ($d->messageType()) {
            'inline' => 'multipart/related',
            'attach', 'inline_attach', 'alt_attach', 'alt_inline_attach' => 'multipart/mixed',
            'alt', 'alt_inline' => 'multipart/alternative',
            default => null,
        };
        if ($multipart === null) {
            $head = "Content-Type: {$d->contentType}; charset={$d->charset}{$eol}";
            return $head . ($encoding !== '7bit' ? "Content-Transfer-Encoding: {$encoding}{$eol}" : '');
        }
        $head = "Content-Type: {$multipart};{$eol} boundary=\"b1=_{$id}\"{$eol}";
        return $head . ($d->encoding === '8bit' ? "Content-Transfer-Encoding: 8bit{$eol}" : '');
    }

    /** The To and Subject lines PHP's mail() takes as arguments, written after the headers in the sent copy. */
    public static function mailOnlyHeaders(Draft $d): string
    {
        return self::addressLine($d, 'To', $d->to) . $d->eol . 'Subject: ' . $d->encodeHeader(self::secure(trim($d->subject))) . $d->eol;
    }

    /**
     * "Name: a, b" for a list of [address, name] pairs.
     *
     * @param list<array{0: string, 1: string}> $list
     */
    public static function addressLine(Draft $d, string $name, array $list): string
    {
        return $name . ': ' . implode(', ', array_map(static fn (array $pair): string => self::address($d, $pair), $list));
    }

    /**
     * One address as a header writes it: the address alone, or the name (as a phrase) and the address in angle brackets.
     *
     * @param array{0: string, 1: string} $pair
     */
    public static function address(Draft $d, array $pair): string
    {
        $name = trim((string) ($pair[1] ?? ''));
        if ($name === '') {
            return self::secure((string) $pair[0]);
        }
        return $d->encodeHeader(self::secure($name), 'phrase') . ' <' . self::secure((string) $pair[0]) . '>';
    }

    /** Header text with any line break taken out. */
    public static function secure(string $text): string
    {
        return trim(str_replace(["\r", "\n"], '', $text));
    }

    /** @return array{0: string, 1: string} the encoding and charset a body part declares */
    private static function encodingFor(Draft $d, string $text): array
    {
        [$encoding, $charset] = [$d->encoding, $d->charset];
        if ($encoding === '8bit' && !Transfer::has8bit($text)) {
            [$encoding, $charset] = ['7bit', 'us-ascii'];
        }
        if ($d->encoding !== 'base64' && Transfer::hasLongLine($text)) {
            $encoding = 'quoted-printable';
        }
        return [$encoding, $charset];
    }

    private static function partHead(Draft $d, string $boundary, string $charset, string $contentType, string $encoding): string
    {
        $eol = $d->eol;
        $charset = $charset === '' ? $d->charset : $charset;
        $contentType = $contentType === '' ? $d->contentType : $contentType;
        $encoding = $encoding === '' ? $d->encoding : $encoding;
        $head = "--{$boundary}{$eol}Content-Type: {$contentType}; charset={$charset}{$eol}";
        return $head . ($encoding !== '7bit' ? "Content-Transfer-Encoding: {$encoding}{$eol}" : '') . $eol;
    }

    private static function related(Draft $d, string $outer, string $inner): string
    {
        $eol = $d->eol;
        return "--{$outer}{$eol}Content-Type: multipart/related;{$eol} boundary=\"{$inner}\";{$eol} type=\"text/html\"{$eol}{$eol}";
    }

    private static function alternative(Draft $d, string $outer, string $inner): string
    {
        $eol = $d->eol;
        return "--{$outer}{$eol}Content-Type: multipart/alternative;{$eol} boundary=\"{$inner}\"{$eol}{$eol}";
    }

    private static function end(Draft $d, string $boundary): string
    {
        return $d->eol . '--' . $boundary . '--' . $d->eol;
    }

    /**
     * A text/calendar part when the message carries one, followed by $after;
     * its method is the calendar's own (upper-cased) when it is a known one.
     */
    private static function calendar(Draft $d, string $boundary, string $after): string
    {
        if ($d->ical === '') {
            return '';
        }
        $method = preg_match('/^METHOD:([a-z]+)\r?$/mi', $d->ical, $m) && in_array(strtoupper($m[1]), self::ICAL_METHODS, true) ? strtoupper($m[1]) : 'REQUEST';
        return self::partHead($d, $boundary, '', 'text/calendar; method=' . $method, '') . Transfer::encode($d->ical, $d->encoding, $d->eol) . $after;
    }

    /** The date given when it reads as a moment already past, else now; either way as RFC 5322 writes it. */
    private static function date(string $given): string
    {
        $date = $given === '' ? false : date_create($given);
        return $date !== false && $date->getTimestamp() < time() ? $date->format('D, j M Y H:i:s O') : date('D, j M Y H:i:s O');
    }

    /** Every attachment of one disposition as parts of the boundary, then the boundary's end; an identical one, or a repeated inline id, goes once. */
    private static function attachAll(Draft $d, string $disposition, string $boundary): string
    {
        $eol = $d->eol;
        $out = '';
        $seen = [];
        $ids = [];
        foreach ($d->attachments as $a) {
            if (($a[6] ?? '') !== $disposition || isset($seen[$hash = hash('sha256', serialize($a))])) {
                continue;
            }
            $seen[$hash] = true;
            if ($disposition === 'inline' && isset($ids[(string) $a[7]])) {
                continue;
            }
            $ids[(string) $a[7]] = true;
            $out .= self::attachmentHead($d, $a, $boundary) . Transfer::encode(self::attachmentData($a), (string) $a[3], $eol) . $eol;
        }
        return $out . "--{$boundary}--{$eol}";
    }

    /** @param array<int, mixed> $a */
    private static function attachmentHead(Draft $d, array $a, string $boundary): string
    {
        $eol = $d->eol;
        $name = $d->encodeHeader(self::secure((string) $a[2]));
        $head = "--{$boundary}{$eol}Content-Type: {$a[4]}" . ($name !== '' ? '; name=' . AddressRules::quoted($name) : '') . $eol;
        if ($a[3] !== '7bit') {
            $head .= "Content-Transfer-Encoding: {$a[3]}{$eol}";
        }
        if ($a[6] === 'inline') {
            $head .= 'Content-ID: <' . $d->encodeHeader(self::secure((string) $a[7])) . '>' . $eol;
        }
        if ((string) $a[6] === '') {
            return $head . $eol;
        }
        return $head . 'Content-Disposition: ' . $a[6] . ($name !== '' ? '; filename=' . AddressRules::quoted($name) : '') . $eol . $eol;
    }

    /** @param array<int, mixed> $a */
    private static function attachmentData(array $a): string
    {
        if ($a[5]) {
            return (string) $a[0];
        }
        $data = @file_get_contents((string) $a[0]);
        if ($data === false) {
            throw new RuntimeException((string) $a[0]);
        }
        return $data;
    }

    /** The message id given when it is well formed, else one made from the id and the host name. */
    public static function messageId(string $given, string $id, string $hostname): string
    {
        return preg_match('/^<[^<>@\s]+@[^<>@\s]+>$/', $given) ? $given : '<' . $id . '@' . $hostname . '>';
    }

    /** @return list<string> X-Priority, X-Mailer, Disposition-Notification-To and the custom headers */
    private static function extraLines(Draft $d): array
    {
        $lines = [];
        if ($d->priority !== null) {
            $lines[] = 'X-Priority: ' . $d->priority;
        }
        if ($d->xMailer === '') {
            $lines[] = 'X-Mailer: PHPMailer ' . Draft::VERSION . ' (https://github.com/PHPMailer/PHPMailer)';
        } elseif (trim($d->xMailer) !== '') {
            $lines[] = 'X-Mailer: ' . trim($d->xMailer);
        }
        if ($d->confirmReadingTo !== '') {
            $lines[] = 'Disposition-Notification-To: <' . trim($d->confirmReadingTo) . '>';
        }
        foreach ($d->customHeaders as [$name, $value]) {
            $lines[] = trim((string) $name) . ': ' . $d->encodeHeader(trim((string) $value));
        }
        return $lines;
    }
}
