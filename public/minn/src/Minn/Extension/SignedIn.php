<?php

declare(strict_types=1);

namespace Minn\Extension;

use Minn\Content\UserRecord;
use Minn\Http\Response;

/** A sign-in that worked (Accounts::signIn): who, and the response carrying the session's cookies. */
final readonly class SignedIn
{
    public function __construct(
        public UserRecord $user,
        public Response $response,
    ) {
    }
}
