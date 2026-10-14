<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\NoCache;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\StatusText;

/**
 * What WordPress's request functions say and send: the protocol the
 * request came over, a status line and the no-cache headers sent as plugin
 * code sends them, the origin a request names and whether it is allowed,
 * and whether the browser is a phone's.
 */
final class RequestHeaders
{
    /** The protocol the request came over (wp_get_server_protocol): HTTP/1.1, 2, 2.0 or 3 as the server says, anything else HTTP/1.0. */
    public static function protocol(): string
    {
        $protocol = Request::serverVariable('SERVER_PROTOCOL') ?? '';
        return in_array($protocol, ['HTTP/1.1', 'HTTP/2', 'HTTP/2.0', 'HTTP/3'], true) ? $protocol : 'HTTP/1.0';
    }

    /**
     * A status line sent (status_header), through status_header; nothing for
     * a code without a reason phrase, outside a web request, or once the
     * head is out.
     */
    public static function status(mixed $code, mixed $description = ''): void
    {
        $description = $description ?: StatusText::of(abs((int) $code));
        if ($description === '') {
            return;
        }
        $protocol = self::protocol();
        $line = (string) Runtime::hooks()->filter('status_header', ["{$protocol} {$code} {$description}", $code, $description, $protocol]);
        Response::emitStatus($line, (int) $code);
    }

    /** The no-cache headers sent now (nocache_headers); one set to false (Last-Modified) is taken off instead. */
    public static function noCache(): void
    {
        Response::emitNoCache(NoCache::headers());
    }

    /** The origin a request names (get_http_origin) as PHP sees it, else the request's own, as http_origin leaves it. */
    public static function origin(): mixed
    {
        $server = Request::serverVariable('HTTP_ORIGIN');
        $origin = $server !== null ? (string) $server : (string) (Runtime::current()->request?->header('origin') ?? '');
        return Runtime::hooks()->filter('http_origin', [$origin]);
    }

    /**
     * The origins allowed to call (get_allowed_http_origins): the home and
     * site hosts over http and https, repeats dropped before
     * allowed_http_origins adds its own.
     *
     * @return list<string>
     */
    public static function allowedOrigins(): mixed
    {
        $home = parse_url(SiteUrls::home());
        $site = parse_url(SiteUrls::site());
        $origins = array_values(array_unique(['http://' . ($home['host'] ?? ''), 'https://' . ($home['host'] ?? ''), 'http://' . ($site['host'] ?? ''), 'https://' . ($site['host'] ?? '')]));
        return Runtime::hooks()->filter('allowed_http_origins', [$origins]);
    }

    /** The origin when it is one allowed (is_allowed_http_origin), '' otherwise, as allowed_http_origin leaves it. */
    public static function allowedOrigin(mixed $origin = null): mixed
    {
        $origin ??= self::origin();
        if ($origin !== '' && !in_array($origin, self::allowedOrigins(), true)) {
            $origin = '';
        }
        return Runtime::hooks()->filter('allowed_http_origin', [$origin, $origin]);
    }

    /** Whether the browser is a phone's or a tablet's (wp_is_mobile), as wp_is_mobile leaves it. */
    public static function isMobile(): mixed
    {
        $agent = (string) (Request::serverVariable('HTTP_USER_AGENT') ?? '');
        return Runtime::hooks()->filter('wp_is_mobile', [$agent !== '' && preg_match('/Mobile|Android|Silk\/|Kindle|BlackBerry|Opera Mini|Opera Mobi/', $agent) === 1]);
    }
}
