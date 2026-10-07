<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Reader;

/**
 * Who the request is, as the reference settles it before init: the user
 * determine_current_user names (the engine's own session, through
 * wp_validate_auth_cookie, when no plugin hooks it), which the request's
 * reader then follows. A plugin that signs in by its own token or cookie
 * (JWT, OAuth, single sign-on) is heard; one that names nobody signs the
 * request out.
 */
final class CurrentUser
{
    /** Settles the request's user and reader; returns the user's id (0 for nobody). */
    public static function settle(Runtime $runtime): int
    {
        $id = (int) \get_current_user_id();
        $reader = $runtime->reader;
        if ($id !== $reader->userId) {
            $runtime->identify($id > 0 ? Reader::forUser($id, $runtime->capabilities, $reader->postPassword) : Reader::anonymous($reader->postPassword));
        }
        return $id;
    }
}
