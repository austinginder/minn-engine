<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A user's sessions as the WordPress layer hands them to plugins
 * (WP_Session_Tokens, or the manager session_token_manager names): made,
 * read, verified and ended. The engine knows them by this mark and asks
 * for them through Runtime\Handoff, so it never names the WordPress
 * layer's class.
 */
interface SessionTokens
{
}
