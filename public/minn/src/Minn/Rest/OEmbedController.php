<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * oembed/1.0 as the reference answers it (probe oembed). embed is the
 * provider side: one of the site's own addresses as embed data (the post
 * through url_to_postid and oembed_request_post_id), "Not Found" for
 * anything else; format=xml is served as XML on the way out
 * (RuntimeRoutes::serve). proxy is the editor's consumer side, for anyone
 * who can edit posts: the site's own addresses answered locally, anything
 * else fetched through WP_oEmbed with the editor's size, its markup
 * through oembed_result, and kept in a transient for a day
 * (rest_oembed_ttl) under the request's arguments.
 */
final readonly class OEmbedController
{
    private const EMBED = [
        'url' => ['description' => 'The URL of the resource for which to fetch oEmbed data.', 'type' => 'string', 'format' => 'uri', 'required' => true],
        'format' => ['default' => 'json', 'required' => false],
        'maxwidth' => ['default' => 600, 'required' => false],
    ];

    private const PROXY = [
        'url' => ['description' => 'The URL of the resource for which to fetch oEmbed data.', 'type' => 'string', 'format' => 'uri', 'required' => true],
        'format' => ['description' => 'The oEmbed format to use.', 'type' => 'string', 'default' => 'json', 'enum' => ['json', 'xml'], 'required' => false],
        'maxwidth' => ['description' => 'The maximum width of the embed frame in pixels.', 'type' => 'integer', 'default' => 600, 'required' => false],
        'maxheight' => ['description' => 'The maximum height of the embed frame in pixels.', 'type' => 'integer', 'required' => false],
        'discover' => ['description' => 'Whether to perform an oEmbed discovery request for unsanctioned providers.', 'type' => 'boolean', 'default' => true, 'required' => false],
    ];

    public function __construct(private Caller $caller)
    {
    }

    /** One of the site's own posts as embed data. */
    #[Route(Method::Get, '/oembed/1.0/embed', policy: new Policy(Access::Public), args: [self::EMBED])]
    public function embed(Request $request): Response
    {
        self::requireRuntime();
        $url = (string) $request->query['url'];
        $postId = (int) \apply_filters('oembed_request_post_id', \url_to_postid($url), $url);
        $data = \get_oembed_response_data($postId, \absint($request->query['maxwidth'] ?? 600));
        if (!$data) {
            throw new RestError('oembed_invalid_url', 'Not Found', 404);
        }
        return Reply::item($data, null);
    }

    /** Another site's embed, fetched for the editor. */
    #[Route(Method::Get, '/oembed/1.0/proxy', policy: new Policy(Access::Public), args: [self::PROXY])]
    public function proxy(Request $request): Response
    {
        self::requireRuntime();
        if (!$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to make proxied oEmbed requests.');
        }
        $args = $request->query + ['format' => 'json', 'maxwidth' => 600, 'discover' => true];
        unset($args['_wpnonce'], $args['rest_route']);
        $url = (string) $args['url'];
        $local = \get_oembed_response_data_for_url($url, ['width' => (int) $args['maxwidth']] + $args);
        if ($local) {
            return Reply::item($local, null);
        }
        $key = 'oembed_' . md5(serialize($args));
        $cached = \get_transient($key);
        if (!empty($cached)) {
            return Reply::item($cached, null);
        }
        $fetch = ['width' => (int) $args['maxwidth'], 'discover' => \rest_sanitize_boolean($args['discover'])] + (isset($args['maxheight']) ? ['height' => (int) $args['maxheight']] : []);
        $data = \_wp_oembed_get_object()->get_data($url, $fetch);
        if ($data === false) {
            throw new RestError('oembed_invalid_url', 'Not Found', 404);
        }
        $data->html = \apply_filters('oembed_result', \_wp_oembed_get_object()->data2html((object) $data, $url), $url, $fetch);
        \set_transient($key, $data, (int) \apply_filters('rest_oembed_ttl', DAY_IN_SECONDS, $url, $fetch));
        return Reply::item($data, null);
    }

    /** Embed data comes from the runtime's own functions. */
    private static function requireRuntime(): void
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
    }
}
