<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * A refusal a route judges after its arguments and before its policy: the
 * reference turns some requests away at the head of its permission check,
 * before it looks at the caller at all (a post created with an id).
 */
interface Refusal
{
    /** Throws the refusal when the request earns it; returns quietly otherwise. */
    public static function refuse(Request $request): void;
}
