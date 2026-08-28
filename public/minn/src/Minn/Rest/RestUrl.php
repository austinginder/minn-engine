<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Front\Permalinks;

/**
 * REST URLs in the form the reference emits for the site's permalink mode:
 * {home}/wp-json/wp/v2/... when pretty, otherwise
 * {home}/index.php?rest_route=/wp/v2/... with the route value URL-encoded
 * when query args ride along.
 */
final readonly class RestUrl
{
    public function __construct(private Permalinks $permalinks)
    {
    }

    public function home(string $path = ''): string
    {
        return $this->permalinks->url($path);
    }

    /** @param array<string, string|int> $args */
    public function to(string $route, array $args = []): string
    {
        $pretty = $this->permalinks->isPretty();
        if ($args === []) {
            return $this->home($pretty ? '/wp-json' . $route : '/index.php?rest_route=' . $route);
        }
        $url = $this->home($pretty ? '/wp-json' . $route : '/index.php?rest_route=' . rawurlencode($route));
        $separator = $pretty ? '?' : '&';
        foreach ($args as $key => $value) {
            $url .= $separator . $key . '=' . rawurlencode((string) $value);
            $separator = '&';
        }
        return $url;
    }

    /** The curies entry every wp/v2 _links block ends with. */
    public static function curies(): array
    {
        return [['name' => 'wp', 'href' => 'https://api.w.org/{rel}', 'templated' => true]];
    }
}
