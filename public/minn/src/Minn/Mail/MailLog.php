<?php

declare(strict_types=1);

namespace Minn\Mail;

use Minn\Json;

/**
 * The engine's development transport: each message as one JSON line
 * (time, from, to, subject, body) appended to wp-content/minn-mail.log.php,
 * nothing sent. The file opens with a line that returns from any request
 * for it, so a web server that runs PHP never serves the reset links it
 * holds (audit Low: it was a plain .log under the webroot).
 */
final class MailLog
{
    /** The first line of the file: PHP that returns before the rest is sent. */
    public const GUARD = "<?php return; ?>\n";

    /** The site's log file. */
    public static function forSite(): string
    {
        return ABSPATH . 'wp-content/minn-mail.log.php';
    }

    /**
     * The messages a log file holds, oldest first (its guard line aside).
     *
     * @return list<array<string, mixed>>
     */
    public static function read(string $file): array
    {
        $messages = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $message = Json::decode($line, or: null);
            if (is_array($message)) {
                $messages[] = $message;
            }
        }
        return $messages;
    }

    /**
     * Appends one message; false when the file cannot be written.
     *
     * @param list<string> $to
     */
    public static function write(string $file, string $from, string $fromName, array $to, string $subject, string $body): bool
    {
        $line = Json::encode([
            'time' => gmdate('c'),
            'from' => $fromName === '' ? $from : "{$fromName} <{$from}>",
            'to' => array_values($to),
            'subject' => $subject,
            'body' => $body,
        ]) . "\n";
        return file_put_contents($file, (is_file($file) ? '' : self::GUARD) . $line, FILE_APPEND | LOCK_EX) !== false;
    }
}
