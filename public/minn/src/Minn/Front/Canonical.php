<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;

/**
 * Where a URL should redirect to, by the engine's own resolution: the
 * canonical form of a request (trailing slash, `?p=` to permalink, doubled
 * slashes, former slugs). The front controller applies this before any
 * plugin runs; the `redirect_canonical()` facade asks it again for a URL a
 * plugin names.
 */
final class Canonical
{
    /** The canonical URL of a request, or null when it already is one. */
    public static function location(Db $db, Request $current, ?string $url): ?string
    {
        $request = $url === null ? $current : self::requestFor($url, $current);
        $resolver = Resolver::fromDb($db, static fn (PostRecord $post): bool => Reader::current()->canEdit((int) $post['ID']));
        $resolution = $resolver->resolve($request);
        return $resolution->kind === Kind::Redirect ? $resolution->location : null;
    }

    private static function requestFor(string $url, Request $current): Request
    {
        $parts = parse_url($url) ?: [];
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        return new Request(
            Method::Get,
            (string) ($parts['path'] ?? '/'),
            $query,
            $current->headers,
            $current->cookies,
            '',
            ($parts['scheme'] ?? ($current->secure ? 'https' : 'http')) === 'https',
            (string) ($parts['host'] ?? $current->host),
        );
    }
}
