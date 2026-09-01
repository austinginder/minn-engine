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

    /** One recipient or several, and the two things every mail has. */
    public static function to(string|array $to, string $subject, string $body): self
    {
        return new self(array_values((array) $to), $subject, $body);
    }
}
