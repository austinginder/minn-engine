<?php

declare(strict_types=1);

namespace Minn\Auth;

/** A validated session: the user row and the raw session token behind it. */
final readonly class Authenticated
{
    /** @param array<string, mixed>|null $applicationPassword the record that authenticated this call, when Basic auth did */
    public function __construct(
        public array $user,
        public string $token,
        public ?array $applicationPassword = null,
    ) {
    }

    public function id(): int
    {
        return (int) $this->user['ID'];
    }
}
