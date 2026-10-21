<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * The command a mailer pipes a message to for sendmail and qmail (probe
 * db-mail-rewrite-doubts): sendmail reads the recipients from the message
 * and ignores lone dots (-i -t), qmail takes nothing more; the envelope
 * sender goes as -f only when it is a valid, shell-safe address, whatever
 * the sendmail options say.
 */
final class SendmailLine
{
    /** The command line for a mailer kind ("sendmail" or "qmail"), the sender given only when it may be passed. */
    public static function command(string $path, string $kind, ?string $sender): string
    {
        $from = $sender !== null && $sender !== '' ? '-f' . escapeshellarg($sender) : '';
        $path = escapeshellcmd($path);
        if ($kind === 'qmail') {
            return $from === '' ? $path : "{$path} {$from}";
        }
        return $path . ($from === '' ? '' : " {$from}") . ' -i -t';
    }
}
