<?php

declare(strict_types=1);

namespace Minn\Front;

/**
 * Whether a resolution may answer with a canonical redirect. A GET or HEAD
 * follows them (the trailing slash, the pretty permalink for ?p=, the
 * guessed destination); any other method holds and resolves the path as
 * typed, as the reference's redirect_canonical bails on a POST.
 */
enum Redirects
{
    case Follow;
    case Hold;

    /** The mode a request's method allows. */
    public static function forMethod(\Minn\Http\Method $method): self
    {
        return $method->canonicalRedirects() ? self::Follow : self::Hold;
    }

    /** Whether a canonical redirect may be answered. */
    public function follows(): bool
    {
        return $this === self::Follow;
    }
}
