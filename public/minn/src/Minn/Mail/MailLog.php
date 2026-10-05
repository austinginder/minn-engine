<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The engine's development transport: each message as one JSON line
 * (time, from, to, subject, body) appended to wp-content/minn-mail.log,
 * nothing sent.
 */
final class MailLog
{
    /** The site's log file. */
    public static function forSite(): string
    {
        return ABSPATH . 'wp-content/minn-mail.log';
    }

    /**
     * Appends one message; false when the file cannot be written.
     *
     * @param list<string> $to
     */
    public static function write(string $file, string $from, string $fromName, array $to, string $subject, string $body): bool
    {
        $line = json_encode([
            'time' => gmdate('c'),
            'from' => $fromName === '' ? $from : "{$fromName} <{$from}>",
            'to' => array_values($to),
            'subject' => $subject,
            'body' => $body,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
    }
}
