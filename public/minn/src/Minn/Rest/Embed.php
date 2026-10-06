<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Router;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * The _embed decoration and the embed context. Every embeddable link in an
 * object's _links is answered in-process as the same caller with
 * context=embed and placed under _embedded[rel]; a rel whose every embed
 * came back empty or as an error is left out, while an empty list beside a
 * full one stays (wp:term keeps its empty tags list next to the categories).
 * Lists have _fields applied per item before the links are read, so a
 * _fields value without _links embeds nothing there; a single object embeds
 * first and is filtered afterwards. The embed context is the view object
 * cut to the reference's key set for that kind of item.
 */
final class Embed
{
    private const KEYS = [
        'post' => ['id', 'date', 'slug', 'type', 'link', 'title', 'excerpt', 'author', 'featured_media', '_links'],
        'media' => ['id', 'date', 'slug', 'type', 'link', 'title', 'author', 'featured_media', 'caption', 'alt_text', 'media_type', 'mime_type', 'media_details', 'source_url', '_links'],
        'user' => ['id', 'name', 'url', 'description', 'link', 'slug', 'avatar_urls', '_links'],
        'term' => ['id', 'link', 'name', 'slug', 'taxonomy', '_links'],
        'comment' => ['id', 'parent', 'author', 'author_name', 'author_url', 'date', 'content', 'link', 'type', 'author_avatar_urls', '_links'],
    ];

    /** @var array<string, ?array> answered hrefs, so a list of posts by one author asks once */
    private array $cache = [];

    public function __construct(
        private readonly Router $router,
        private readonly Types $types,
        private readonly Taxonomies $taxonomies,
    ) {
    }

    /** Null when _embed is absent; [] for every rel; otherwise the rels asked for. @return ?list<string> */
    public static function requested(array $query): ?array
    {
        if (!array_key_exists('_embed', $query)) {
            return null;
        }
        $raw = $query['_embed'];
        if (is_array($raw)) {
            $raw = implode(',', $raw);
        }
        $raw = (string) $raw;
        if ($raw === '' || $raw === '1' || $raw === 'true') {
            return [];
        }
        $rels = array_values(array_filter(array_map(trim(...), explode(',', $raw)), static fn (string $s) => $s !== ''));
        return $rels;
    }

    /** Decorates a finished response: the embed context, then _embedded, then the deferred _fields. */
    public function decorate(Request $request, Response $response): Response
    {
        $rels = self::requested($request->query);
        $embedContext = Context::of($request) === Context::Embed;
        if (($rels === null && !$embedContext) || $response->status >= 300 || $response->body === '') {
            return $response;
        }
        $data = json_decode($response->body, true);
        if (!is_array($data)) {
            return $response;
        }
        $kind = self::kind($request->path);
        if (array_is_list($data)) {
            foreach ($data as &$item) {
                if (is_array($item) && !array_is_list($item)) {
                    $item = $this->decorateItem($request, $item, $rels, $embedContext ? $kind : null);
                }
            }
            unset($item);
        } else {
            $data = $this->decorateItem($request, $data, $rels, $embedContext ? $kind : null);
            $fields = Fields::fromQuery($request->query);
            if ($fields !== null && $fields->deferred) {
                // Asking for _embedded on a single object keeps its _links too.
                $data = $fields->withLinksForEmbedded()->apply($data);
            }
        }
        return new Response($response->status, $response->headers, (string) json_encode($data), $response->cookies);
    }

    /** @param ?list<string> $rels */
    private function decorateItem(Request $request, array $item, ?array $rels, ?string $kind): array
    {
        if ($kind !== null) {
            $item = self::context($kind, $item);
        }
        if ($rels === null || !isset($item['_links']) || !is_array($item['_links'])) {
            return $item;
        }
        $embedded = [];
        foreach ($item['_links'] as $rel => $links) {
            if ($rels !== [] && !in_array($rel, $rels, true)) {
                continue;
            }
            $embeds = [];
            foreach (is_array($links) ? $links : [] as $link) {
                if (empty($link['embeddable']) || !isset($link['href'])) {
                    continue;
                }
                $embeds[] = $this->answer($request, (string) $link['href']) ?? [];
            }
            if ($embeds === [] || array_filter($embeds) === []) {
                continue;
            }
            $embedded[$rel] = $embeds;
        }
        if ($embedded !== []) {
            $item['_embedded'] = $embedded;
        }
        return $item;
    }

    /** The linked object in embed context, or null when the link answers with an error. */
    private function answer(Request $request, string $href): ?array
    {
        if (array_key_exists($href, $this->cache)) {
            return $this->cache[$href];
        }
        [$path, $query] = self::target($href);
        $query['context'] = 'embed';
        unset($query['_embed'], $query['_fields']);
        $inner = new Request(Method::Get, $path, $query, $request->headers, $request->cookies, '', $request->secure, $request->host, [], [], $request->remoteAddress, $request->server);
        try {
            // Plugins preparing the linked object see its own request, in embed context.
            $response = Runtime::booted() ? RuntimePrepare::during(RuntimeRoutes::wpRequest($inner), fn () => $this->router->dispatch($inner)) : $this->router->dispatch($inner);
        } catch (RestError) {
            $response = null;
        }
        $data = $response === null || $response->status >= 300 ? null : json_decode($response->body, true);
        if (is_array($data)) {
            $kind = self::kind($path);
            if (array_is_list($data)) {
                $data = array_map(fn ($row) => is_array($row) && $kind !== null ? self::context($kind, $row) : $row, $data);
            } elseif ($kind !== null) {
                $data = self::context($kind, $data);
            }
        } else {
            $data = null;
        }
        return $this->cache[$href] = $data;
    }

    /** A REST href in either URL form, as the route path and its query. @return array{0: string, 1: array<string, string>} */
    private static function target(string $href): array
    {
        $parts = parse_url($href);
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query = array_map(static fn ($v) => is_string($v) ? $v : '', $query);
        if (isset($query['rest_route'])) {
            $path = (string) $query['rest_route'];
            unset($query['rest_route']);
        } else {
            $path = (string) ($parts['path'] ?? '/');
            $at = strpos($path, '/wp-json');
            $path = $at === false ? $path : substr($path, $at + strlen('/wp-json'));
        }
        return ['/' . trim($path, '/'), $query];
    }

    /** Which key set a route's objects take in embed context; null for routes without one. */
    private function kind(string $path): ?string
    {
        if (!preg_match('#^/wp/v2/([^/]+)(?:/|$)#', $path, $m)) {
            return null;
        }
        $base = $m[1];
        return match (true) {
            $base === 'media' => 'media',
            $base === 'users' => 'user',
            $base === 'comments' => 'comment',
            $this->taxonomyBase($base) => 'term',
            $this->types->slugForRestBase($base) !== null => 'post',
            default => null,
        };
    }

    private function taxonomyBase(string $base): bool
    {
        foreach ($this->taxonomies->all() as $taxonomy) {
            if (($taxonomy['rest_base'] ?? '') === $base) {
                return true;
            }
        }
        return false;
    }

    /** An item cut to the embed shape of its object type (a post type, a taxonomy, attachment, user or comment); any other kept whole. @param array<string, mixed> $item @return array<string, mixed> */
    public static function shape(string $type, array $item): array
    {
        $kind = match (true) {
            $type === 'attachment' => 'media',
            $type === 'user', $type === 'comment' => $type,
            \taxonomy_exists($type) => 'term',
            \post_type_exists($type) => 'post',
            default => null,
        };
        return $kind === null ? $item : array_intersect_key($item, array_flip(self::KEYS[$kind]));
    }

    /** The embed shape, with the fields plugin code registered for the item's type to show in embed. */
    private static function context(string $kind, array $object): array
    {
        $type = match ($kind) {
            'post' => (string) ($object['type'] ?? ''),
            'term' => (string) ($object['taxonomy'] ?? ''),
            'media' => 'attachment',
            default => $kind,
        };
        return array_intersect_key($object, array_flip([...self::KEYS[$kind], ...RegisteredFields::shownIn($type, 'embed')]));
    }
}
