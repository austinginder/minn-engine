<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * The parameters a route takes when they depend on what its captures name
 * (a post type's collection takes its own taxonomies, its own orderby
 * values, whatever plugins add to it), rather than on a fixed set.
 */
interface RouteParams
{
    /**
     * The route's parameters for these captures, as the index publishes them; none when the captures name nothing.
     *
     * @param array<string, string> $captures
     * @return array<string, array<string, mixed>>
     */
    public static function for(array $captures): array;
}
