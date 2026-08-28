<?php

declare(strict_types=1);

namespace Minn\Auth;

/** A validated session: the user row and the raw session token behind it. */
final readonly class Authenticated
{
    public function __construct(
        public array $user,
        public string $token,
    ) {
    }

    public function id(): int
    {
        return (int) $this->user['ID'];
    }
}
