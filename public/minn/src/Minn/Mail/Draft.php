<?php

declare(strict_types=1);

namespace Minn\Mail;

/**
 * Everything a message is composed from: the transport it goes out on
 * (which decides the line ending, the header line length, and where To,
 * Subject and Bcc are written), the envelope, the content, the headers,
 * and the attachments as [path or data, file name, name, encoding, type,
 * is data, disposition, content id]. $toHeader is "list" (To, or
 * undisclosed-recipients when there is neither To nor Cc) or "omit" (one
 * message per recipient, the address given to each send instead).
 */
final readonly class Draft
{
    public const VERSION = '7.1.1';

    /**
     * @param list<array{0: string, 1: string}> $to address and name pairs, as are $cc, $bcc and $replyTo
     * @param list<array{0: string, 1: string}> $customHeaders name and value pairs
     * @param list<array<int, mixed>> $attachments
     */
    public function __construct(
        public string $mailer,
        public string $eol,
        public string $charset,
        public string $contentType,
        public string $encoding,
        public string $subject,
        public string $body,
        public string $altBody,
        public string $ical,
        public string $from,
        public string $fromName,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public string $messageId,
        public string $messageDate,
        public ?int $priority,
        public string $xMailer,
        public string $confirmReadingTo,
        public array $customHeaders,
        public array $attachments,
        public string $toHeader = 'list',
    ) {
    }

    /** The longest header line the transport takes before it must fold. */
    public function lineLength(): int
    {
        return $this->mailer === 'mail' ? HeaderWords::MAIL_LINE : HeaderWords::SMTP_LINE;
    }

    /** Header text encoded for this message's charset and transport. */
    public function encodeHeader(string $text, string $position = 'text'): string
    {
        return HeaderWords::encode($text, $position, $this->charset, $this->lineLength(), $this->eol);
    }

    /** Which parts the message has: plain, or "alt", "inline", "attach" joined by "_". */
    public function messageType(): string
    {
        $parts = [];
        if ($this->altBody !== '') {
            $parts[] = 'alt';
        }
        foreach (['inline', 'attachment'] as $disposition) {
            foreach ($this->attachments as $attachment) {
                if (($attachment[6] ?? '') === $disposition) {
                    $parts[] = $disposition === 'inline' ? 'inline' : 'attach';
                    break;
                }
            }
        }
        return $parts === [] ? 'plain' : implode('_', $parts);
    }
}
