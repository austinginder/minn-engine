<?php

declare(strict_types=1);

namespace Minn\Theme;

/**
 * The facts about a page that decide the body classes a classic theme's
 * page carries beyond the view tokens: a signed-in reader, a custom
 * logo, responsive embeds, and the Minn bar.
 */
final readonly class BodyFacts
{
    public function __construct(
        public bool $loggedIn = false,
        public bool $customLogo = false,
        public bool $embedResponsive = true,
        public bool $bar = false,
    ) {
    }
}
