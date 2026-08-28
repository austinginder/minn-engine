<?php

declare(strict_types=1);

namespace Minn\Mail;

/** One outgoing plain-text email. */
final readonly class Message
{
    /** @param list<string> $to */
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public string $fromEmail = '',
        public string $fromName = '',
    ) {
    }
}
