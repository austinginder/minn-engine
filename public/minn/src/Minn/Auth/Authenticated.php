<?php

declare(strict_types=1);

namespace Minn\Auth;

use Minn\Content\UserRecord;

/** A validated session: the user row and the raw session token behind it. */
final readonly class Authenticated
{
    /** @param array<string, mixed>|null $applicationPassword the record that authenticated this call, when Basic auth did */
    public function __construct(
        public UserRecord $user,
        public string $token,
        public ?array $applicationPassword = null,
    ) {
    }

    /** The signed-in user's id. */
    public function id(): int
    {
        return $this->user->id;
    }
}
