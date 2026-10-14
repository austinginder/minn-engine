<?php

declare(strict_types=1);

namespace Minn\Objects;

/**
 * A comments query as the WordPress layer hands it to plugins
 * (WP_Comment_Query). The engine knows these objects by this mark and makes
 * them through Runtime\Handoff, so it never names the WordPress layer's
 * class.
 */
interface CommentsQuery
{
}
